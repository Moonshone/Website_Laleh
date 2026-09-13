<?php

declare(strict_types=1);

final class PublicMessageException extends RuntimeException
{
}

require_once dirname(__DIR__) . '/includes/media-security.php';

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_rejected_upload(array $file, string $message): void
{
    try {
        validate_news_image_upload($file, false);
    } catch (PublicMessageException) {
        return;
    }
    throw new RuntimeException($message);
}

$temporaryDirectory = sys_get_temp_dir() . '/laleh-media-test-' . bin2hex(random_bytes(6));
mkdir($temporaryDirectory, 0700);

try {
    $validPng = $temporaryDirectory . '/valid.png';
    file_put_contents($validPng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    $validFile = ['error' => UPLOAD_ERR_OK, 'tmp_name' => $validPng, 'size' => filesize($validPng), 'name' => 'picture.png'];
    assert_same('png', validate_news_image_upload($validFile, false), 'A valid PNG must be accepted');

    assert_rejected_upload(array_replace($validFile, ['name' => 'test.php']), 'A PHP extension must be rejected');
    assert_rejected_upload(array_replace($validFile, ['name' => 'test.jpg.php']), 'A multiple PHP extension must be rejected');
    assert_same('png', validate_news_image_upload(array_replace($validFile, ['name' => '../picture.png']), false), 'Traversal text cannot influence the canonical extension');

    $fakeJpeg = $temporaryDirectory . '/fake.jpg';
    file_put_contents($fakeJpeg, '<?php echo "not an image";');
    assert_rejected_upload(['error' => UPLOAD_ERR_OK, 'tmp_name' => $fakeJpeg, 'size' => filesize($fakeJpeg), 'name' => 'fake.jpg'], 'A fake JPEG must be rejected');
    assert_rejected_upload(array_replace($validFile, ['size' => 8 * 1024 * 1024 + 1]), 'An oversized image must be rejected');

    assert_same('assets/images/work.jpg', safe_media_url('assets/images/work.jpg'), 'Relative images must remain valid');
    assert_same('/uploads/news/work.webp', safe_media_url('/uploads/news/work.webp'), 'Root-relative images must remain valid');
    assert_same('https://www.youtube.com/watch?v=abc', safe_media_url('https://www.youtube.com/watch?v=abc'), 'HTTPS media must remain valid');
    assert_same(null, safe_media_url('javascript:alert(1)'), 'javascript URLs must be rejected');
    assert_same(null, safe_media_url('data:image/svg+xml,<svg onload=alert(1)>'), 'data URLs must be rejected');
    assert_same(null, safe_media_url('//attacker.example/video'), 'Scheme-relative external URLs must be rejected');
    assert_same('https://www.youtube.com/embed/abc_123', safe_youtube_embed_url('https://youtu.be/abc_123'), 'YouTube embeds must be canonicalised');
    assert_same(null, safe_youtube_embed_url('https://attacker.example/embed/abc_123'), 'Non-allowlisted iframe hosts must be rejected');

    $payload = 'image.jpg" onerror="alert(1)';
    $escaped = htmlspecialchars((string) safe_media_url($payload), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (str_contains($escaped, '" onerror="')) {
        throw new RuntimeException('An image URL must not break out of its HTML attribute');
    }
} finally {
    foreach (glob($temporaryDirectory . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($temporaryDirectory);
}

echo "Media security tests passed.\n";
