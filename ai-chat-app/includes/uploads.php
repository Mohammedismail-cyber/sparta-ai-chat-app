<?php
/**
 * File/image/screenshot attachment handling.
 * Every upload is validated by real content (finfo), never by the
 * client-supplied filename or MIME header, and stored under a random
 * server-generated name so nothing user-controlled ever touches a path.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class UploadException extends Exception {}

function ensure_upload_dir(int $userId): string
{
    $dir = UPLOAD_DIR . '/' . $userId;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Validate and persist an uploaded file (from $_FILES) for a user.
 * Returns the inserted attachment row.
 * @throws UploadException
 */
function store_uploaded_file(array $file, int $userId, ?int $conversationId): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new UploadException('Invalid upload.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new UploadException('That file is too large.');
        case UPLOAD_ERR_NO_FILE:
            throw new UploadException('No file was received.');
        default:
            throw new UploadException('Upload failed. Please try again.');
    }

    if ($file['size'] <= 0 || $file['size'] > UPLOAD_MAX_BYTES) {
        $limitMb = round(UPLOAD_MAX_BYTES / 1024 / 1024);
        throw new UploadException("Files must be under {$limitMb}MB.");
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new UploadException('Invalid upload.');
    }

    // Trust the actual file bytes, not the client-supplied MIME type.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';

    if (!isset(UPLOAD_ALLOWED_MIME[$realMime])) {
        throw new UploadException('That file type isn\'t supported. Try an image, PDF, or text file.');
    }

    $ext = UPLOAD_ALLOWED_MIME[$realMime];
    $kind = str_starts_with($realMime, 'image/') ? 'image' : 'file';

    $storedName = bin2hex(random_bytes(20)) . '.' . $ext;
    $destDir = ensure_upload_dir($userId);
    $destPath = $destDir . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new UploadException('Could not save the uploaded file.');
    }
    chmod($destPath, 0640);

    $originalName = mb_substr(basename($file['name']), 0, 180);

    $stmt = db()->prepare(
        'INSERT INTO attachments (user_id, conversation_id, message_id, original_name, stored_name, mime_type, kind, size_bytes)
         VALUES (?, ?, NULL, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $conversationId ?: null, $originalName, $storedName, $realMime, $kind, $file['size']]);
    $id = (int) db()->lastInsertId();

    return [
        'id' => $id,
        'original_name' => $originalName,
        'mime_type' => $realMime,
        'kind' => $kind,
        'size_bytes' => (int) $file['size'],
    ];
}

/** Absolute path on disk for a given attachment row. */
function attachment_disk_path(array $attachment): string
{
    return UPLOAD_DIR . '/' . $attachment['user_id'] . '/' . $attachment['stored_name'];
}
