<?php

declare(strict_types=1);

/**
 * Return a browser-safe local or HTTP(S) media URL, or null when the value uses
 * an executable/unexpected URI scheme or is otherwise malformed.
 */
function safe_media_url(mixed $value): ?string
{
    $url = trim((string) ($value ?? ''));
    if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1 || str_contains($url, '\\')) {
        return null;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return null;
    }

    if (isset($parts['scheme'])) {
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }
        return $url;
    }

    // A scheme-relative URL can silently switch origin and protocol. Local
    // paths (root-relative and document-relative) remain supported.
    if (isset($parts['host']) || str_starts_with($url, '//')) {
        return null;
    }

    return $url;
}

/** Convert only the YouTube URL forms used by the film catalogue to an embed URL. */
function safe_youtube_embed_url(string $url): ?string
{
    $safeUrl = safe_media_url($url);
    $parts = $safeUrl === null ? false : parse_url($safeUrl);
    if ($parts === false || !isset($parts['host'])) {
        return null;
    }

    $host = strtolower(rtrim((string) $parts['host'], '.'));
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }
    $path = trim((string) ($parts['path'] ?? ''), '/');
    $videoId = null;
    if ($host === 'youtu.be') {
        $videoId = explode('/', $path, 2)[0];
    } elseif (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
        if ($path === 'watch') {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $videoId = isset($query['v']) && is_string($query['v']) ? $query['v'] : null;
        } elseif (str_starts_with($path, 'embed/')) {
            $videoId = explode('/', substr($path, 6), 2)[0];
        }
    }

    $videoId = rawurldecode(trim((string) $videoId));
    if ($videoId === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $videoId) !== 1) {
        return null;
    }
    return 'https://www.youtube.com/embed/' . $videoId;
}

/** Validate the actual upload data and return its canonical extension. */
function validate_news_image_upload(array $file, bool $requireHttpUpload = true): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || empty($file['tmp_name'])
        || ($requireHttpUpload && !is_uploaded_file((string) $file['tmp_name']))) {
        throw new PublicMessageException('The image upload failed.');
    }

    $size = filter_var($file['size'] ?? null, FILTER_VALIDATE_INT);
    if ($size === false || $size < 1 || $size > 8 * 1024 * 1024) {
        throw new PublicMessageException('Images may not be larger than 8 MB.');
    }

    $allowed = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];
    $extension = strtolower(pathinfo(basename((string) ($file['name'] ?? '')), PATHINFO_EXTENSION));
    if (!isset($allowed[$extension])) {
        throw new PublicMessageException('Please upload a JPG, PNG, or WEBP image.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $file['tmp_name']);
    $imageInfo = @getimagesize((string) $file['tmp_name']);
    if (!is_string($mime) || !in_array($mime, $allowed[$extension], true)
        || $imageInfo === false || ($imageInfo['mime'] ?? '') !== $mime) {
        throw new PublicMessageException('Please upload a valid JPG, PNG, or WEBP image.');
    }

    return $mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/png' ? 'png' : 'webp');
}
