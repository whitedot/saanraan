#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('TOY_ROOT', $root);

require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/upload.php';

$errors = [];

function toy_upload_helper_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

toy_upload_helper_assert(
    toy_upload_filename("../bad\r\nname.php") === 'bad-name.php',
    'Upload filename should remove path and control characters.'
);
toy_upload_helper_assert(
    toy_upload_extension('PHOTO.JPG') === 'jpg',
    'Upload extension should be lowercased.'
);
toy_upload_helper_assert(
    toy_upload_normalize_extensions(['.jpg', 'JPG', 'png', '../php']) === ['jpg', 'png'],
    'Upload extension allowlist should normalize safe unique extensions.'
);
toy_upload_helper_assert(
    toy_upload_is_executable_extension('php'),
    'PHP extension should be blocked as executable.'
);
toy_upload_helper_assert(
    preg_match('/\A[a-f0-9]{32}\.jpg\z/', toy_upload_random_filename('jpg')) === 1,
    'Random upload filename should preserve safe extension.'
);

$tmpFile = tempnam(sys_get_temp_dir(), 'toy-upload-');
if (!is_string($tmpFile)) {
    $errors[] = 'Temporary upload test file cannot be created.';
} else {
    file_put_contents($tmpFile, "hello\n");
    $validated = toy_upload_validate_file([
        'error' => UPLOAD_ERR_OK,
        'name' => 'hello.txt',
        'tmp_name' => $tmpFile,
        'size' => 6,
    ], [
        'max_bytes' => 100,
        'allowed_extensions' => ['txt'],
        'require_uploaded_file' => false,
    ]);

    toy_upload_helper_assert(
        $validated['extension'] === 'txt' && $validated['size'] === 6 && $validated['checksum'] === hash_file('sha256', $tmpFile),
        'Upload validator should return normalized metadata.'
    );

    try {
        toy_upload_validate_file([
            'error' => UPLOAD_ERR_OK,
            'name' => 'shell.php',
            'tmp_name' => $tmpFile,
            'size' => 6,
        ], [
            'allowed_extensions' => ['php'],
            'require_uploaded_file' => false,
        ]);
        $errors[] = 'Upload validator should reject executable extensions even if listed.';
    } catch (RuntimeException $exception) {
    }

    $directory = dirname($tmpFile);
    toy_upload_helper_assert(
        basename(toy_upload_safe_target_path($directory, 'safe.txt')) === 'safe.txt',
        'Upload target path should allow safe filenames.'
    );
    try {
        toy_upload_safe_target_path($directory, '../bad.txt');
        $errors[] = 'Upload target path should reject traversal-like filenames.';
    } catch (RuntimeException $exception) {
    }
    try {
        toy_upload_safe_target_path($directory, 'stored.php');
        $errors[] = 'Upload target path should reject executable target filenames.';
    } catch (RuntimeException $exception) {
    }
    $existingTarget = $directory . DIRECTORY_SEPARATOR . basename($tmpFile) . '-existing.txt';
    file_put_contents($existingTarget, 'existing');
    try {
        toy_upload_assert_target_path_writable($existingTarget, false);
        $errors[] = 'Upload target state should reject existing files without overwrite.';
    } catch (RuntimeException $exception) {
    }
    toy_upload_assert_target_path_writable($existingTarget, true);
    unlink($existingTarget);

    $directoryTarget = $directory . DIRECTORY_SEPARATOR . basename($tmpFile) . '-target-dir';
    mkdir($directoryTarget);
    try {
        toy_upload_assert_target_path_writable($directoryTarget, true);
        $errors[] = 'Upload target state should reject directory targets even with overwrite.';
    } catch (RuntimeException $exception) {
    }
    rmdir($directoryTarget);

    unlink($tmpFile);
}

$config = ['app_key' => 'upload-helper-test-key'];
$token = toy_download_token_create($config, 'attachment.download', 'attachment:1', 300, 1000);
toy_upload_helper_assert(
    toy_download_token_verify($config, (string) $token['token'], (string) $token['token_hash'], 'attachment.download', 'attachment:1', (int) $token['expires_at'], 1100),
    'Download token should verify before expiration.'
);
toy_upload_helper_assert(
    !toy_download_token_verify($config, (string) $token['token'], (string) $token['token_hash'], 'attachment.download', 'attachment:2', (int) $token['expires_at'], 1100),
    'Download token should bind the subject.'
);
toy_upload_helper_assert(
    !toy_download_token_verify($config, (string) $token['token'], (string) $token['token_hash'], 'attachment.download', 'attachment:1', (int) $token['expires_at'], 2000),
    'Download token should expire.'
);

$sourcePng = tempnam(sys_get_temp_dir(), 'toy-image-');
$targetPng = tempnam(sys_get_temp_dir(), 'toy-image-target-');
if (is_string($sourcePng) && is_string($targetPng)) {
    file_put_contents($sourcePng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true));
    $reencoded = toy_upload_reencode_image($sourcePng, $targetPng, 'png', ['max_pixels' => 10]);
    toy_upload_helper_assert(
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
