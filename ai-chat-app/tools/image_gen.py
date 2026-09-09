#!/usr/bin/env python3
"""Local text-to-image specialist. Never uses chat/LLM models."""
from __future__ import annotations

import argparse
import os
import subprocess
import sys
import time
import traceback
from pathlib import Path

# Windows + huggingface: xet/symlinks raise Errno 22.
os.environ.setdefault("HF_HUB_DISABLE_XET", "1")
os.environ.setdefault("HF_HUB_DISABLE_SYMLINKS", "1")
os.environ.setdefault("HF_HUB_ENABLE_HF_TRANSFER", "0")

ROOT = Path(__file__).resolve().parents[1]
DATA = ROOT / "data"
READY = DATA / "image-backend.ready"
LOG = DATA / "image-backend.log"
LOCK = DATA / "image-backend.lock"
CACHE = DATA / "image-models"
os.environ.setdefault("HF_HOME", str(CACHE))
os.environ.setdefault("HUGGINGFACE_HUB_CACHE", str(CACHE))

MODELS = [
    "segmind/tiny-sd",
]


def log(msg: str) -> None:
    DATA.mkdir(parents=True, exist_ok=True)
    line = time.strftime("%Y-%m-%d %H:%M:%S ") + msg + "\n"
    with LOG.open("a", encoding="utf-8") as fh:
        fh.write(line)
        fh.flush()
    print(msg, flush=True)


def pip_install(packages: list[str]) -> None:
    subprocess.check_call(
        [sys.executable, "-m", "pip", "install", "--disable-pip-version-check", *packages],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.STDOUT,
    )


def ensure_deps() -> None:
    try:
        import diffusers  # noqa: F401
        import huggingface_hub  # noqa: F401
    except Exception:
        log("installing diffusers")
        pip_install(["diffusers", "accelerate", "huggingface_hub"])


def pid_alive(pid: int) -> bool:
    if pid <= 0:
        return False
    if os.name == "nt":
        try:
            r = subprocess.run(
                ["tasklist", "/FI", f"PID eq {pid}"],
                capture_output=True,
                text=True,
                timeout=5,
            )
            out = r.stdout or ""
            return str(pid) in out and "No tasks" not in out
        except Exception:
            return False
    try:
        os.kill(pid, 0)
        return True
    except OSError:
        return False


def acquire_lock(timeout: int = 120) -> int:
    DATA.mkdir(parents=True, exist_ok=True)
    start = time.time()
    while True:
        try:
            fd = os.open(str(LOCK), os.O_CREAT | os.O_EXCL | os.O_WRONLY)
            os.write(fd, str(os.getpid()).encode())
            return fd
        except FileExistsError:
            try:
                old = int((LOCK.read_text(encoding="utf-8") or "0").strip() or "0")
            except Exception:
                old = 0
            if old and not pid_alive(old):
                try:
                    LOCK.unlink()
                    continue
                except OSError:
                    pass
            if time.time() - start > timeout:
                raise RuntimeError("image backend is busy")
            time.sleep(1)


def release_lock(fd: int) -> None:
    try:
        os.close(fd)
    except OSError:
        pass
    try:
        LOCK.unlink(missing_ok=True)
    except OSError:
        pass


def find_local_snapshot(name: str) -> Path | None:
    folder = CACHE / f"models--{name.replace('/', '--')}" / "snapshots"
    if not folder.is_dir():
        return None
    snaps = sorted((p for p in folder.iterdir() if p.is_dir()), key=lambda p: p.stat().st_mtime, reverse=True)
    return snaps[0] if snaps else None


def snapshot_complete(path: Path) -> bool:
    def has_weights(folder: Path) -> bool:
        names = [
            "diffusion_pytorch_model.safetensors",
            "diffusion_pytorch_model.bin",
            "pytorch_model.safetensors",
            "pytorch_model.bin",
        ]
        return any((folder / n).is_file() and (folder / n).stat().st_size > 1_000_000 for n in names)

    return has_weights(path / "unet") and has_weights(path / "vae")


def convert_bins_to_safetensors(root: Path) -> None:
    import torch
    from safetensors.torch import save_file

    for bin_path in root.rglob("*.bin"):
        if bin_path.name not in {"diffusion_pytorch_model.bin", "pytorch_model.bin"}:
            continue
        out = bin_path.with_suffix(".safetensors")
        if out.is_file() and out.stat().st_size > 1_000_000:
            continue
        log(f"converting {bin_path.relative_to(root)}")
        payload = torch.load(bin_path, map_location="cpu", weights_only=True)
        if isinstance(payload, dict) and "state_dict" in payload:
            payload = payload["state_dict"]
        tensors = {
            key: value.contiguous()
            for key, value in payload.items()
            if hasattr(value, "contiguous")
        }
        save_file(tensors, str(out))
        log(f"wrote {out.name} ({out.stat().st_size} bytes)")
        if bin_path.parent.name == "text_encoder":
            clip = bin_path.with_name("model.safetensors")
            if not clip.is_file():
                clip.write_bytes(out.read_bytes())
                log(f"wrote {clip.name} for text encoder")


def download_model(name: str) -> Path:
    existing = find_local_snapshot(name)
    if existing and snapshot_complete(existing):
        log(f"using cached {name}")
        return existing

    from huggingface_hub import snapshot_download

    CACHE.mkdir(parents=True, exist_ok=True)
    log(f"pulling {name}")
    path = Path(
        snapshot_download(
            repo_id=name,
            cache_dir=str(CACHE),
        )
    )
    log(f"cached {name} at {path}")
    if not snapshot_complete(path):
        raise RuntimeError(f"{name} download is incomplete")
    return path


def load_from_path(local: Path, name: str):
    import torch
    from diffusers import DiffusionPipeline, StableDiffusionPipeline

    convert_bins_to_safetensors(local)
    log(f"loading {name}")
    try:
        pipe = StableDiffusionPipeline.from_pretrained(
            str(local),
            dtype=torch.float32,
            local_files_only=True,
            use_safetensors=True,
            safety_checker=None,
            requires_safety_checker=False,
        )
    except Exception:
        pipe = DiffusionPipeline.from_pretrained(
            str(local),
            dtype=torch.float32,
            local_files_only=True,
            use_safetensors=True,
        )
    if hasattr(pipe, "safety_checker"):
        pipe.safety_checker = None
    try:
        pipe.requires_safety_checker = False
    except Exception:
        pass
    pipe = pipe.to("cpu")
    READY.write_text(name + "\n" + time.strftime("%Y-%m-%dT%H:%M:%S"), encoding="utf-8")
    log(f"ready {name}")
    return pipe, name


def load_pipeline(allow_download: bool = False):
    last_err: Exception | None = None
    for name in MODELS:
        try:
            local = find_local_snapshot(name)
            if local and snapshot_complete(local):
                return load_from_path(local, name)
            if not allow_download:
                continue
            local = download_model(name)
            return load_from_path(local, name)
        except Exception as exc:
            last_err = exc
            log(f"failed {name}: {exc}")
            log(traceback.format_exc())
    if allow_download:
        raise RuntimeError(f"could not load an image model: {last_err}")
    return load_pipeline(allow_download=True)


def generate(prompt: str, out: Path) -> None:
    ensure_deps()
    fd = acquire_lock(180)
    try:
        pipe, name = load_pipeline()
        kwargs = {
            "prompt": prompt,
            "negative_prompt": "blurry, low quality, deformed, extra limbs, text, watermark",
            "width": 512,
            "height": 512,
            "num_inference_steps": 8,
            "guidance_scale": 7.0,
        }
        image = pipe(**kwargs).images[0]
        out.parent.mkdir(parents=True, exist_ok=True)
        image.save(out)
        log(f"wrote {out}")
    finally:
        release_lock(fd)


def prepare() -> None:
    log("prepare start")
    ensure_deps()
    fd = acquire_lock(180)
    try:
        load_pipeline()
    finally:
        release_lock(fd)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--prepare", action="store_true")
    parser.add_argument("--prompt", default="")
    parser.add_argument("--out", default="")
    args = parser.parse_args()
    try:
        if args.prepare:
            prepare()
            return 0
        if not args.prompt or not args.out:
            print("prompt and out required", file=sys.stderr)
            return 2
        generate(args.prompt, Path(args.out))
        return 0
    except Exception as exc:
        log(f"error: {exc}")
        log(traceback.format_exc())
        print(str(exc), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
