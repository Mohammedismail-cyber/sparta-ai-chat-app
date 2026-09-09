<?php
/**
 * Finished HTML for website, mobile, image, video, and document previews.
 */

if (defined('SPARTA_RENDER_LOADED')) {
    return;
}
define('SPARTA_RENDER_LOADED', true);

require_once __DIR__ . '/scenes.php';

function hx(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function render_website(array $p): string
{
    [$ink, $paper, $accent] = $p['palette'];
    $scene = $p['scene'] ?? scene_from_prompt($p['name'] . ' ' . ($p['tagline'] ?? ''));
    $navItems = $p['nav'] ?: ['Home', 'Services', 'Story', 'Visit'];
    $nav = '';
    foreach ($navItems as $item) {
        $id = strtolower(preg_replace('/[^a-z]+/i', '', $item));
        if ($id === 'menu' || $id === 'work' || $id === 'home') {
            $id = $id === 'home' ? 'top' : 'services';
        }
        $nav .= '<a href="#' . hx($id) . '">' . hx($item) . '</a>';
    }

    $cards = '';
    foreach (array_values($p['menu']) as $i => $row) {
        $cards .= '<article class="card"><div class="card-art">' . scene_card_art($p['domain'] ?? 'studio', $i, $p['palette'])
            . '</div><div class="card-body"><h3>' . hx($row[0]) . '</h3><p>' . hx($row[1])
            . '</p><span>' . hx($row[2]) . '</span></div></article>';
    }

    $feats = '';
    foreach ($p['features'] as $f) {
        $feats .= '<li>' . hx($f) . '</li>';
    }
    $hours = '';
    foreach ($p['hours'] as $hr) {
        $hours .= '<div>' . hx($hr) . '</div>';
    }

    $heroArt = scene_svg($scene);

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . hx($p['name']) . '</title><style>'
        . ':root{--ink:' . $ink . ';--paper:' . $paper . ';--accent:' . $accent . '}'
        . '*{box-sizing:border-box}html{scroll-behavior:smooth}'
        . 'body{margin:0;font-family:Inter,system-ui,sans-serif;background:var(--paper);color:var(--ink)}'
        . 'a{color:inherit}'
        . 'nav.bar{position:sticky;top:0;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 7%;background:color-mix(in srgb,var(--paper) 88%,transparent);backdrop-filter:blur(16px);border-bottom:1px solid rgba(0,0,0,.06)}'
        . '.brand{font-family:Georgia,serif;font-weight:700;font-size:18px;letter-spacing:.02em}'
        . 'nav.bar .links{display:flex;gap:22px;font-size:13px}'
        . 'nav.bar .links a{text-decoration:none;opacity:.72}'
        . 'nav.bar .links a:hover{opacity:1}'
        . '.nav-cta{background:var(--ink);color:var(--paper);text-decoration:none;padding:10px 16px;border-radius:999px;font-size:13px}'
        . '.hero{display:grid;grid-template-columns:1.05fr .95fr;min-height:78vh;align-items:stretch}'
        . '.hero-copy{padding:72px 8% 64px;display:flex;flex-direction:column;justify-content:center}'
        . '.kicker{font-size:12px;letter-spacing:.16em;text-transform:uppercase;opacity:.55;margin-bottom:16px}'
        . 'h1{font-family:Georgia,serif;font-size:clamp(40px,6vw,72px);line-height:.95;letter-spacing:-.03em;margin:0 0 18px;max-width:14ch}'
        . '.lead{font-size:18px;line-height:1.55;opacity:.72;max-width:38ch}'
        . '.hero-actions{display:flex;gap:12px;margin-top:28px;flex-wrap:wrap}'
        . '.btn{background:var(--ink);color:var(--paper);text-decoration:none;padding:13px 20px;border-radius:999px;font-size:14px}'
        . '.btn.ghost{background:transparent;color:var(--ink);border:1px solid rgba(0,0,0,.16)}'
        . '.hero-art{min-height:420px;overflow:hidden}.hero-art svg{width:100%;height:100%;display:block;object-fit:cover}'
        . 'section{padding:80px 7%}'
        . 'h2{font-family:Georgia,serif;font-size:34px;margin:0 0 28px}'
        . '.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}'
        . '.card{background:#fff;border-radius:18px;overflow:hidden;border:1px solid rgba(0,0,0,.06);box-shadow:0 10px 30px rgba(0,0,0,.04)}'
        . '.card-art{height:150px}.card-art svg{width:100%;height:100%;display:block}'
        . '.card-body{padding:18px 18px 22px}'
        . '.card-body h3{margin:0 0 6px;font-size:17px}'
        . '.card-body p{margin:0;font-size:13.5px;line-height:1.5;opacity:.65}'
        . '.card-body span{display:inline-block;margin-top:10px;font-size:12px;font-weight:600;color:var(--accent)}'
        . '.story{display:grid;grid-template-columns:1.1fr .9fr;gap:48px;align-items:start}'
        . '.story p{font-size:20px;line-height:1.55;max-width:42ch}'
        . '.story ul{margin:0;padding-left:18px;line-height:1.8}'
        . '.visit{display:grid;grid-template-columns:1fr 1fr;gap:28px}'
        . 'footer.site{padding:36px 7% 48px;border-top:1px solid rgba(0,0,0,.08);display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:24px;font-size:13px;opacity:.75}'
        . 'footer.site strong{display:block;margin-bottom:8px;font-family:Georgia,serif;font-size:16px;opacity:1}'
        . '@media(max-width:860px){.hero,.grid,.story,.visit,footer.site{grid-template-columns:1fr}nav.bar .links{display:none}.hero-art{min-height:240px}}'
        . '</style></head><body id="top">'
        . '<nav class="bar"><div class="brand">' . hx($p['name']) . '</div><div class="links">' . $nav
        . '</div><a class="nav-cta" href="#visit">' . hx($p['cta']) . '</a></nav>'
        . '<header class="hero"><div class="hero-copy"><div class="kicker">' . hx($p['tagline']) . '</div>'
        . '<h1>' . hx($p['hero']) . '</h1><p class="lead">' . hx($p['story']) . '</p>'
        . '<div class="hero-actions"><a class="btn" href="#services">' . hx($p['cta']) . '</a>'
        . '<a class="btn ghost" href="#story">Our story</a></div></div>'
        . '<div class="hero-art">' . $heroArt . '</div></header>'
        . '<section id="services"><h2>Services</h2><div class="grid">' . $cards . '</div></section>'
        . '<section id="story" class="story"><div><h2>Story</h2><p>' . hx($p['story']) . '</p></div><ul>' . $feats . '</ul></section>'
        . '<section id="visit" class="visit"><div><h2>Hours</h2>' . $hours . '</div><div><h2>Visit</h2><div>' . hx($p['address']) . '</div></div></section>'
        . '<footer class="site"><div><strong>' . hx($p['name']) . '</strong>' . hx($p['tagline']) . '</div>'
        . '<div><strong>Explore</strong>Services<br>Story<br>Visit</div>'
        . '<div><strong>Contact</strong>' . hx($p['address']) . '</div></footer>'
        . '<script>document.querySelectorAll("nav a[href^=\'#\']").forEach(a=>a.addEventListener("click",e=>{const t=document.querySelector(a.getAttribute("href"));if(t){e.preventDefault();t.scrollIntoView({behavior:"smooth"})}}));</script>'
        . '</body></html>';
}

function render_mobile(array $p): string
{
    [$ink, $paper, $accent] = $p['palette'];
    $cards = '';
    foreach (array_values($p['menu']) as $i => $row) {
        $cards .= '<button class="row" data-i="' . $i . '"><div class="thumb">' . scene_card_art($p['domain'] ?? 'studio', $i, $p['palette'])
            . '</div><div><b>' . hx($row[0]) . '</b><span>' . hx($row[1]) . '</span></div><em>' . hx($row[2]) . '</em></button>';
    }
    $saved = '';
    foreach (array_slice($p['menu'], 0, 3) as $row) {
        $saved .= '<div class="saved"><b>' . hx($row[0]) . '</b><span>' . hx($row[1]) . '</span></div>';
    }
    $feats = '';
    foreach ($p['features'] as $f) {
        $feats .= '<div class="setting">' . hx($f) . '</div>';
    }

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . hx($p['name']) . '</title><style>'
        . '*{box-sizing:border-box}body{margin:0;min-height:100vh;background:#0c0c0e;display:flex;align-items:center;justify-content:center;font-family:Inter,system-ui,sans-serif}'
        . '.phone{width:390px;height:780px;border-radius:40px;overflow:hidden;background:' . $paper . ';color:' . $ink
        . ';display:flex;flex-direction:column;box-shadow:0 30px 80px rgba(0,0,0,.5);position:relative}'
        . '.notch{position:absolute;top:10px;left:50%;transform:translateX(-50%);width:118px;height:22px;background:#0c0c0e;border-radius:12px;z-index:3}'
        . '.status{padding:14px 22px 0;display:flex;justify-content:space-between;font-size:12px;font-weight:600}'
        . '.hero{margin:12px 16px 8px;border-radius:22px;overflow:hidden;height:168px;position:relative;color:#fff}'
        . '.hero svg{width:100%;height:100%;display:block}'
        . '.hero .label{position:absolute;left:16px;bottom:16px}'
        . '.hero h1{margin:0;font-size:26px}.hero p{margin:4px 0 0;opacity:.8;font-size:13px}'
        . '.chips{display:flex;gap:8px;padding:8px 16px;overflow:auto}'
        . '.chips button{border:0;background:#fff;border-radius:999px;padding:8px 12px;font-size:12px;font-weight:600;white-space:nowrap}'
        . '.screen{flex:1;overflow:auto;padding:4px 16px 12px;display:none}'
        . '.screen.on{display:block}'
        . '.row{width:100%;border:0;background:#fff;border-radius:16px;padding:10px;margin-bottom:10px;display:flex;gap:12px;align-items:center;text-align:left}'
        . '.thumb{width:56px;height:56px;border-radius:12px;overflow:hidden;flex-shrink:0}.thumb svg{width:100%;height:100%}'
        . '.row b{display:block;font-size:14px}.row span{display:block;font-size:12px;opacity:.6}'
        . '.row em{margin-left:auto;font-style:normal;color:' . $accent . ';font-size:12px;font-weight:700}'
        . '.saved,.setting{background:#fff;border-radius:14px;padding:14px;margin-bottom:8px}'
        . '.search{width:100%;border:0;background:#fff;border-radius:14px;padding:12px 14px;margin:8px 0 12px;font-size:14px}'
        . '.tab{height:68px;border-top:1px solid rgba(0,0,0,.08);display:flex;background:color-mix(in srgb,' . $paper . ' 90%,#fff)}'
        . '.tab button{flex:1;border:0;background:transparent;font-size:10px;font-weight:700;color:#9898a0}'
        . '.tab button.on{color:' . $ink . '}'
        . '#toast{display:none;margin:0 16px 8px;padding:10px 12px;border-radius:10px;background:' . $ink . ';color:' . $paper . ';font-size:12px}'
        . '</style></head><body><div class="phone"><div class="notch"></div>'
        . '<div class="status"><span>9:41</span><span>●●●</span></div>'
        . '<div class="hero">' . scene_svg($p['scene'] ?? scene_from_prompt($p['name']))
        . '<div class="label"><h1>' . hx($p['name']) . '</h1><p>' . hx($p['tagline']) . '</p></div></div>'
        . '<div class="chips"><button>Today</button><button>Popular</button><button>Saved</button></div>'
        . '<div id="toast">Saved to your list</div>'
        . '<div class="screen on" id="home">' . $cards . '</div>'
        . '<div class="screen" id="search"><input class="search" placeholder="Search ' . hx($p['name']) . '">' . $cards . '</div>'
        . '<div class="screen" id="saved">' . $saved . '</div>'
        . '<div class="screen" id="profile"><div class="setting"><b>' . hx($p['name']) . '</b><div>' . hx($p['address']) . '</div></div>' . $feats . '</div>'
        . '<div class="tab">'
        . '<button class="on" data-tab="home">Home</button>'
        . '<button data-tab="search">Search</button>'
        . '<button data-tab="saved">Saved</button>'
        . '<button data-tab="profile">You</button>'
        . '</div></div>'
        . '<script>const tabs=document.querySelectorAll(".tab button");const screens=document.querySelectorAll(".screen");'
        . 'tabs.forEach(b=>b.onclick=()=>{tabs.forEach(x=>x.classList.remove("on"));b.classList.add("on");screens.forEach(s=>s.classList.toggle("on",s.id===b.dataset.tab))});'
        . 'document.querySelectorAll(".row").forEach(c=>c.onclick=()=>{const t=document.getElementById("toast");t.style.display="block";setTimeout(()=>t.style.display="none",1200)});</script>'
        . '</body></html>';
}

function render_image(array $p): string
{
    $scene = $p['scene'] ?? scene_from_prompt($p['name'] . ' ' . ($p['tagline'] ?? ''));
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . hx($scene['title']) . '</title>'
        . '<style>html,body{margin:0;height:100%;background:#0a0a0c}svg{width:100%;height:100%;display:block}</style></head><body>'
        . scene_svg($scene)
        . '</body></html>';
}

function render_video(array $p): string
{
    $scene = $p['scene'] ?? scene_from_prompt($p['name'] . ' ' . ($p['tagline'] ?? ''));
    [$a, $b, $c] = $scene['palette'];
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . hx($scene['title']) . '</title><style>'
        . 'html,body{margin:0;height:100%;overflow:hidden;background:#000;color:#fff}'
        . '.stage{position:relative;height:100%}'
        . '.art{position:absolute;inset:0}.art svg{width:100%;height:100%;display:block;filter:saturate(1.1)}'
        . '.copy{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;background:linear-gradient(180deg,rgba(0,0,0,.15),rgba(0,0,0,.45))}'
        . 'h1{font-family:Georgia,serif;font-size:clamp(44px,8vw,92px);margin:0;letter-spacing:-.03em;animation:rise 1.6s ease}'
        . 'p{font-family:Inter,system-ui,sans-serif;opacity:0;animation:rise 1.4s ease 1.1s forwards}'
        . '.est{position:absolute;bottom:36px;width:100%;font-family:Inter,system-ui,sans-serif;font-size:12px;letter-spacing:.2em;opacity:0;animation:rise 1s ease 2s forwards}'
        . '@keyframes rise{from{opacity:0;transform:translateY(16px)}to{opacity:.95;transform:none}}'
        . '</style></head><body><div class="stage"><div class="art">' . scene_svg($scene) . '</div>'
        . '<div class="copy"><h1>' . hx($scene['title']) . '</h1><p>' . hx($scene['subtitle']) . '</p></div>'
        . '<div class="est">' . hx($p['address'] ?? '') . '</div></div></body></html>';
}

function render_document(array $p): string
{
    $rows = '';
    foreach ($p['menu'] as $row) {
        $rows .= '<tr><td>' . hx($row[0]) . '</td><td>' . hx($row[1]) . '</td><td>' . hx($row[2]) . '</td></tr>';
    }
    $feats = '<ul>';
    foreach ($p['features'] as $f) {
        $feats .= '<li>' . hx($f) . '</li>';
    }
    $feats .= '</ul>';
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . hx($p['name']) . ' — Brief</title><style>'
        . '@page{size:Letter;margin:22mm}body{margin:0;background:#e7e5e4;font-family:Georgia,serif;color:#1c1917}'
        . '.page{width:min(760px,92vw);margin:28px auto;background:#fff;padding:56px 60px 64px;min-height:980px;box-shadow:0 10px 40px rgba(0,0,0,.08)}'
        . '.kicker{font-family:Inter,system-ui,sans-serif;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#78716c}'
        . 'h1{font-size:40px;margin:10px 0 8px}h2{font-size:20px;margin:32px 0 10px}'
        . 'p,li,td{line-height:1.55}.lead{font-size:18px;color:#44403c}'
        . 'table{width:100%;border-collapse:collapse;margin-top:8px}td{padding:8px 0;border-bottom:1px solid #e7e5e4;font-size:14px}'
        . '.foot{margin-top:48px;font-family:Inter,system-ui,sans-serif;font-size:12px;color:#a8a29e}'
        . '</style></head><body><article class="page">'
        . '<div class="kicker">Project brief</div><h1>' . hx($p['name']) . '</h1>'
        . '<p class="lead">' . hx($p['tagline']) . '</p>'
        . '<h2>Overview</h2><p>' . hx($p['story']) . '</p>'
        . '<h2>Offer</h2><table>' . $rows . '</table>'
        . '<h2>What we stand on</h2>' . $feats
        . '<h2>Visit</h2><p>' . hx($p['address']) . '<br>' . hx(implode(' · ', $p['hours'])) . '</p>'
        . '<div class="foot">Prepared as a working document. Print or save as PDF from the browser.</div>'
        . '</article></body></html>';
}

function render_artifact_html(array $pack, string $kind): string
{
    return match ($kind) {
        'mobile'   => render_mobile($pack),
        'image'    => render_image($pack),
        'video'    => render_video($pack),
        'document' => render_document($pack),
        'slides'   => render_document($pack),
        default    => render_website($pack),
    };
}
