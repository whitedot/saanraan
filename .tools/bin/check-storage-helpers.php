#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/upload.php';
require_once $root . '/core/helpers/storage.php';

$errors = [];

function sr_storage_helper_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

$productionHttpConfig = [
    'env' => 'production',
    'storage' => [
        'default' => 's3',
        's3' => [
            'bucket' => 'sr-bucket',
            'region' => 'ap-northeast-2',
            'access_key' => 'test-access-key',
            'secret_key' => 'test-secret-key',
            'endpoint' => 'http://s3.local',
            'public_base_url' => 'http://cdn.local',
        ],
    ],
];

sr_storage_helper_assert(
    !sr_storage_s3_ready($productionHttpConfig),
    'Production S3 config with HTTP URLs should not be ready.'
);
sr_storage_helper_assert(
    sr_storage_public_url('s3', 'banner/images/test.jpg', $productionHttpConfig) === '',
    'Production S3 public URL should be blank when config contains HTTP URLs.'
);
sr_storage_helper_assert(
    sr_storage_signed_url('s3', 'banner/images/test.jpg', 300, [], $productionHttpConfig) === '',
    'Production S3 signed URL should be blank when config contains HTTP endpoint.'
);
try {
    sr_storage_s3_presigned_url($productionHttpConfig, 'banner/images/test.jpg', 300);
    $errors[] = 'Production S3 presigned URL should reject HTTP endpoint.';
} catch (RuntimeException $exception) {
}

$developmentHttpConfig = $productionHttpConfig;
$developmentHttpConfig['env'] = 'development';
sr_storage_helper_assert(
    sr_storage_s3_ready($developmentHttpConfig),
    'Development S3 config should allow HTTP-compatible local endpoints.'
);
$developmentSignedUrl = sr_storage_signed_url('s3', 'banner/images/test.jpg', 300, [], $developmentHttpConfig);
sr_storage_helper_assert(
    str_starts_with($developmentSignedUrl, 'http://sr-bucket.s3.local/banner/images/test.jpg?'),
    'Development S3 signed URL should keep HTTP-compatible local endpoint support.'
);
sr_storage_helper_assert(
    sr_storage_public_url('s3', 'banner/images/test.jpg', $developmentHttpConfig) === 'http://cdn.local/banner/images/test.jpg',
    'Development S3 public URL should keep HTTP-compatible local endpoint support.'
);

$storageHelperSource = file_get_contents($root . '/core/helpers/storage.php');
if (is_string($storageHelperSource)) {
    sr_storage_helper_assert(
        strpos($storageHelperSource, '!@mkdir($directory, 0755, true)') !== false,
        'Local storage directory creation should suppress raw mkdir warnings and throw a controlled exception.'
    );
}

if ($errors !== []) {
    fwrite(STDERR, "storage helper checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "storage helper checks completed.\n";
