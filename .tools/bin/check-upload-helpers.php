#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/upload.php';

$errors = [];

function sr_upload_helper_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

sr_upload_helper_assert(
    sr_upload_filename("../bad\r\nname.php") === 'bad-name.php',
    'Upload filename should remove path and control characters.'
);
sr_upload_helper_assert(
    sr_upload_extension('PHOTO.JPG') === 'jpg',
    'Upload extension should be lowercased.'
);
sr_upload_helper_assert(
    sr_upload_normalize_extensions(['.jpg', 'JPG', 'png', '../php']) === ['jpg', 'png'],
    'Upload extension allowlist should normalize safe unique extensions.'
);
sr_upload_helper_assert(
    sr_upload_is_executable_extension('php'),
    'PHP extension should be blocked as executable.'
);
sr_upload_helper_assert(
    sr_upload_filename_has_executable_extension('avatar.php.jpg'),
    'Upload filename should detect executable extension segments.'
);
sr_upload_helper_assert(
    preg_match('/\A[a-f0-9]{32}\.jpg\z/', sr_upload_random_filename('jpg')) === 1,
    'Random upload filename should preserve safe extension.'
);
try {
    sr_upload_random_filename('php.jpg');
    $errors[] = 'Random upload filename should reject compound extension input.';
} catch (InvalidArgumentException $exception) {
}

$tmpFile = tempnam(sys_get_temp_dir(), 'sr-upload-');
if (!is_string($tmpFile)) {
    $errors[] = 'Temporary upload test file cannot be created.';
} else {
    file_put_contents($tmpFile, "hello\n");
    $detectedTextMime = sr_upload_detect_mime($tmpFile);
    if ($detectedTextMime !== '') {
        $validated = sr_upload_validate_file([
            'error' => UPLOAD_ERR_OK,
            'name' => 'hello.txt',
            'tmp_name' => $tmpFile,
            'size' => 6,
        ], [
            'max_bytes' => 100,
            'allowed_extensions' => ['txt'],
            'allowed_mime_types' => [$detectedTextMime, 'text/plain', 'application/octet-stream'],
            'require_uploaded_file' => false,
        ]);

        sr_upload_helper_assert(
            $validated['extension'] === 'txt' && $validated['size'] === 6 && $validated['checksum'] === hash_file('sha256', $tmpFile),
            'Upload validator should return normalized metadata.'
        );
    } else {
        try {
            sr_upload_validate_file([
                'error' => UPLOAD_ERR_OK,
                'name' => 'hello.txt',
                'tmp_name' => $tmpFile,
                'size' => 6,
            ], [
                'max_bytes' => 100,
                'allowed_extensions' => ['txt'],
                'allowed_mime_types' => ['text/plain', 'application/octet-stream'],
                'require_uploaded_file' => false,
            ]);
            $errors[] = 'Upload validator should reject files when MIME cannot be detected.';
        } catch (RuntimeException $exception) {
        }
    }
    try {
        sr_upload_validate_file([
            'error' => UPLOAD_ERR_OK,
            'name' => 'hello.txt',
            'tmp_name' => $tmpFile,
            'size' => 6,
        ], [
            'allowed_extensions' => ['txt'],
            'allowed_mime_types' => ['text/plain', 'application/octet-stream'],
            'require_uploaded_file' => false,
        ]);
        $errors[] = 'Upload validator should require an explicit max_bytes option.';
    } catch (RuntimeException $exception) {
    }
    try {
        sr_upload_validate_file([
            'error' => UPLOAD_ERR_OK,
            'name' => 'hello.txt',
            'tmp_name' => $tmpFile,
            'size' => 6,
        ], [
            'max_bytes' => 100,
            'allowed_mime_types' => ['text/plain', 'application/octet-stream'],
            'require_uploaded_file' => false,
        ]);
        $errors[] = 'Upload validator should require explicit allowed extensions.';
    } catch (RuntimeException $exception) {
    }
    try {
        sr_upload_validate_file([
            'error' => UPLOAD_ERR_OK,
            'name' => 'hello.txt',
            'tmp_name' => $tmpFile,
            'size' => 6,
        ], [
            'max_bytes' => 100,
            'allowed_extensions' => ['txt'],
            'require_uploaded_file' => false,
        ]);
        $errors[] = 'Upload validator should require explicit allowed MIME types.';
    } catch (RuntimeException $exception) {
    }

    try {
        sr_upload_validate_file([
            'error' => UPLOAD_ERR_OK,
            'name' => 'shell.php',
            'tmp_name' => $tmpFile,
            'size' => 6,
        ], [
            'max_bytes' => 100,
            'allowed_extensions' => ['php'],
            'allowed_mime_types' => ['text/plain', 'application/octet-stream'],
            'require_uploaded_file' => false,
        ]);
        $errors[] = 'Upload validator should reject executable extensions even if listed.';
    } catch (RuntimeException $exception) {
    }
    try {
        sr_upload_validate_file([
            'error' => UPLOAD_ERR_OK,
            'name' => 'shell.php.jpg',
            'tmp_name' => $tmpFile,
            'size' => 6,
        ], [
            'max_bytes' => 100,
            'allowed_extensions' => ['jpg'],
            'allowed_mime_types' => ['text/plain', 'application/octet-stream'],
            'require_uploaded_file' => false,
        ]);
        $errors[] = 'Upload validator should reject executable extension segments.';
    } catch (RuntimeException $exception) {
    }

    $directory = dirname($tmpFile);
    sr_upload_helper_assert(
        basename(sr_upload_safe_target_path($directory, 'safe.txt')) === 'safe.txt',
        'Upload target path should allow safe filenames.'
    );
    try {
        sr_upload_safe_target_path($directory, '../bad.txt');
        $errors[] = 'Upload target path should reject traversal-like filenames.';
    } catch (RuntimeException $exception) {
    }
    try {
        sr_upload_safe_target_path($directory, 'stored.php');
        $errors[] = 'Upload target path should reject executable target filenames.';
    } catch (RuntimeException $exception) {
    }
    try {
        sr_upload_safe_target_path($directory, 'stored.php.jpg');
        $errors[] = 'Upload target path should reject executable extension segments.';
    } catch (RuntimeException $exception) {
    }
    $existingTarget = $directory . DIRECTORY_SEPARATOR . basename($tmpFile) . '-existing.txt';
    file_put_contents($existingTarget, 'existing');
    try {
        sr_upload_assert_target_path_writable($existingTarget, false);
        $errors[] = 'Upload target state should reject existing files without overwrite.';
    } catch (RuntimeException $exception) {
    }
    sr_upload_assert_target_path_writable($existingTarget, true);
    unlink($existingTarget);

    $directoryTarget = $directory . DIRECTORY_SEPARATOR . basename($tmpFile) . '-target-dir';
    mkdir($directoryTarget);
    try {
        sr_upload_assert_target_path_writable($directoryTarget, true);
        $errors[] = 'Upload target state should reject directory targets even with overwrite.';
    } catch (RuntimeException $exception) {
    }
    rmdir($directoryTarget);

    unlink($tmpFile);
}

$config = ['app_key' => 'upload-helper-test-key'];
$token = sr_download_token_create($config, 'attachment.download', 'attachment:1', 300, 1000);
sr_upload_helper_assert(
    sr_download_token_verify($config, (string) $token['token'], (string) $token['token_hash'], 'attachment.download', 'attachment:1', (int) $token['expires_at'], 1100),
    'Download token should verify before expiration.'
);
sr_upload_helper_assert(
    !sr_download_token_verify($config, (string) $token['token'], (string) $token['token_hash'], 'attachment.download', 'attachment:2', (int) $token['expires_at'], 1100),
    'Download token should bind the subject.'
);
sr_upload_helper_assert(
    !sr_download_token_verify($config, (string) $token['token'], (string) $token['token_hash'], 'attachment.download', 'attachment:1', (int) $token['expires_at'], 2000),
    'Download token should expire.'
);

$sourcePng = tempnam(sys_get_temp_dir(), 'sr-image-');
$targetPng = tempnam(sys_get_temp_dir(), 'sr-image-target-');
if (is_string($sourcePng) && is_string($targetPng)) {
    file_put_contents($sourcePng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true));
    $reencoded = sr_upload_reencode_image($sourcePng, $targetPng, 'png', ['max_pixels' => 10]);
    sr_upload_helper_assert(
        $reencoded === false || (is_file($targetPng) && filesize($targetPng) > 0),
        'Image reencode helper should either be unavailable or write a target image.'
    );
    unlink($sourcePng);
    unlink($targetPng);
}

if ($errors !== []) {
    fwrite(STDERR, "upload helper checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "upload helper checks completed.\n";
