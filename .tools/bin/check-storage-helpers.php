#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/common.php';
require_once $root . '/core/helpers/upload.php';

if (!function_exists('sr_url')) {
    function sr_url(string $path): string
    {
        return (string) ($GLOBALS['sr_storage_helper_test_base_path'] ?? '') . $path;
    }
}

require_once $root . '/core/helpers/storage.php';
require_once $root . '/modules/admin/helpers/storage-cache.php';

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
$developmentVersionedSignedUrl = sr_storage_signed_url('s3', 'banner/images/test.jpg', 300, ['versionId' => 'test-version-123'], $developmentHttpConfig);
sr_storage_helper_assert(
    str_contains($developmentVersionedSignedUrl, 'versionId=test-version-123'),
    'S3 signed URL should include VersionId when requested for exact object reads.'
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
    foreach ([
        'function sr_thumbnail_supported',
        'function sr_thumbnail_variant_key',
        'function sr_thumbnail_source_version',
        'function sr_thumbnail_public_url',
        'function sr_thumbnail_public_cache_url',
        'function sr_thumbnail_delete_variants',
        'function sr_storage_copy_to_temp_file',
        'function sr_storage_http_download_to_temp_file',
        '$detectedImageMime',
        'max_source_pixels',
        "storage/cache/thumbnails",
    ] as $marker) {
        sr_storage_helper_assert(
            strpos($storageHelperSource, $marker) !== false,
            'Storage helper must expose thumbnail helper marker: ' . $marker
        );
    }
}

$htaccess = file_get_contents($root . '/.htaccess');
$devRouter = file_get_contents($root . '/.tools/bin/dev-router.php');
$nginxSample = file_get_contents($root . '/docs/deployment/nginx-saanraan.conf');
$coreHelpers = file_get_contents($root . '/core/helpers.php');
$frontController = file_get_contents($root . '/index.php');
$communityAttachments = file_get_contents($root . '/modules/community/helpers/attachments.php');
$communityPosts = file_get_contents($root . '/modules/community/helpers/posts.php');
$communityListSkin = file_get_contents($root . '/modules/community/skins/basic/list.php');
$adminPaths = file_get_contents($root . '/modules/admin/paths.php');
$adminNavigation = file_get_contents($root . '/modules/admin/helpers/navigation.php');
$adminStorageCacheAction = file_get_contents($root . '/modules/admin/actions/storage-cache.php');
$adminStorageCacheView = file_get_contents($root . '/modules/admin/views/storage-cache.php');
sr_storage_helper_assert(
    is_string($htaccess)
        && strpos($htaccess, 'storage/cache/thumbnails/[a-f0-9]{2}') !== false
        && strpos($htaccess, 'storage/cache/thumbnails/[a-z][a-z0-9_]{1,39}') !== false,
    'Apache rules must allow only generated thumbnail cache images under storage/cache/thumbnails.'
);
sr_storage_helper_assert(
    is_string($devRouter) && strpos($devRouter, '$thumbnailCacheRequest') !== false,
    'Dev router must allow only generated thumbnail cache images under storage/cache/thumbnails.'
);
sr_storage_helper_assert(
    is_string($nginxSample)
        && strpos($nginxSample, '^/storage/cache/thumbnails/[a-f0-9]{2}') !== false
        && strpos($nginxSample, '^/storage/cache/thumbnails/[a-z][a-z0-9_]{1,39}') !== false,
    'Nginx sample must allow only generated thumbnail cache images under storage/cache/thumbnails.'
);
sr_storage_helper_assert(
    is_string($coreHelpers)
        && strpos($coreHelpers, "/core/helpers/output.php") !== false
        && strpos($coreHelpers, "/core/helpers/storage.php") !== false
        && strpos($coreHelpers, "/core/helpers/output.php") < strpos($coreHelpers, "/core/helpers/storage.php"),
    'Core helpers must load output helpers before storage helpers so thumbnail cache URLs can use sr_url when available.'
);
sr_storage_helper_assert(
    is_string($frontController)
        && strpos($frontController, "require SR_ROOT . '/core/helpers.php';") !== false
        && strpos($frontController, "require SR_ROOT . '/core/helpers.php';") < strpos($frontController, 'sr_enabled_module_contract_files'),
    'Front controller must load core helpers before module routes so modules can reference thumbnail helpers.'
);
sr_storage_helper_assert(
    is_string($communityAttachments)
        && strpos($communityAttachments, 'function sr_community_post_list_thumbnail_url') !== false
        && strpos($communityAttachments, 'sr_community_asset_event_required($paidReadConfig)') !== false
        && strpos($communityAttachments, 'sr_thumbnail_delete_variants') !== false,
    'Community attachment helpers must expose public-list thumbnail URL and cache cleanup guards.'
);
sr_storage_helper_assert(
    is_string($communityPosts)
        && strpos($communityPosts, 'list_image_attachment_id') !== false
        && strpos($communityPosts, 'list_image_storage_key') !== false
        && strpos($communityPosts, 'list_image_checksum_sha256') !== false,
    'Community board list query must include first image attachment fields and checksum metadata for thumbnail generation.'
);
sr_storage_helper_assert(
    is_string($communityListSkin)
        && strpos($communityListSkin, 'sr_community_post_list_thumbnail_url') !== false
        && strpos($communityListSkin, 'loading="lazy"') !== false,
    'Community basic list skin must consume the list thumbnail helper.'
);
sr_storage_helper_assert(
    is_string($adminPaths)
        && strpos($adminPaths, "GET /admin/storage-cache") !== false
        && strpos($adminPaths, "POST /admin/storage-cache") !== false,
    'Admin paths must expose storage cache GET and POST routes.'
);
sr_storage_helper_assert(
    is_string($adminNavigation) && strpos($adminNavigation, "/admin/storage-cache") !== false,
    'Admin navigation must expose the storage cache screen.'
);
sr_storage_helper_assert(
    is_string($adminStorageCacheAction)
        && strpos($adminStorageCacheAction, "storage-cache', 'delete'") !== false
        && strpos($adminStorageCacheAction, 'sr_require_csrf()') !== false
        && strpos($adminStorageCacheAction, 'sr_audit_log($pdo') !== false,
    'Admin storage cache cleanup must require delete permission, CSRF, and audit logging.'
);
sr_storage_helper_assert(
    is_string($adminStorageCacheView)
        && strpos($adminStorageCacheView, 'type="date"') !== false
        && strpos($adminStorageCacheView, 'sr_csrf_field()') !== false
        && strpos($adminStorageCacheView, 'confirm_text') !== false,
    'Admin storage cache view must expose date filters and guarded cleanup form.'
);

$variantA = sr_thumbnail_variant_key(['height' => 90, 'width' => 160, 'quality' => 82, 'mode' => 'cover', 'format' => 'source']);
$variantB = sr_thumbnail_variant_key(['width' => 160, 'height' => 90, 'mode' => 'cover']);
sr_storage_helper_assert($variantA === $variantB, 'Thumbnail variant key must be stable for equivalent normalized options.');
sr_storage_helper_assert(
    sr_thumbnail_module_key(['module_key' => 'community']) === 'community' && sr_thumbnail_module_key(['module_key' => '../bad']) === 'common',
    'Thumbnail module key helper must accept safe module keys and fall back for unsafe keys.'
);
sr_storage_helper_assert(
    preg_match('/\A[a-f0-9]{16}\z/', sr_thumbnail_source_version(['checksum_sha256' => str_repeat('a', 64)])) === 1,
    'Thumbnail source version helper must normalize checksum versions to a safe short token.'
);
sr_storage_helper_assert(
    sr_thumbnail_source_version([], [
        'content_length' => 123,
        'metadata' => [
            'version_id' => 's3-version-1',
            'etag' => 'etag-1',
            'last_modified' => 'Sun, 14 Jun 2026 00:00:00 GMT',
        ],
    ]) !== sr_thumbnail_source_version([], [
        'content_length' => 123,
        'metadata' => [
            'version_id' => 's3-version-2',
            'etag' => 'etag-1',
            'last_modified' => 'Sun, 14 Jun 2026 00:00:00 GMT',
        ],
    ]),
    'Thumbnail source version helper must prefer S3 version markers when available.'
);
sr_storage_helper_assert(
    sr_thumbnail_public_url(new PDO('sqlite::memory:'), [
        'public' => true,
        'storage_driver' => 'local',
        'storage_key' => '../bad.png',
        'public_url' => '/fallback.png',
        'mime_type' => 'image/png',
    ], ['width' => 160, 'height' => 90]) === '/fallback.png',
    'Thumbnail helper must reject unsafe source keys and fall back without traversal.'
);
$GLOBALS['sr_storage_helper_test_base_path'] = '/subdir';
$thumbnailCacheFixture = 'cache/thumbnails/aa/' . str_repeat('a', 64) . '_w160_h90_cover_q82_source_1.jpg';
sr_storage_helper_assert(
    sr_thumbnail_public_cache_url($thumbnailCacheFixture) === '/subdir/storage/cache/thumbnails/aa/' . str_repeat('a', 64) . '_w160_h90_cover_q82_source_1.jpg',
    'Thumbnail public cache URL must keep legacy cache URL compatibility.'
);
$versionedThumbnailCacheFixture = 'cache/thumbnails/community/aa/' . str_repeat('a', 64) . '_w160_h90_cover_q82_source_' . str_repeat('c', 16) . '.jpg';
sr_storage_helper_assert(
    sr_thumbnail_public_cache_url($versionedThumbnailCacheFixture) === '/subdir/storage/cache/thumbnails/community/aa/' . str_repeat('a', 64) . '_w160_h90_cover_q82_source_' . str_repeat('c', 16) . '.jpg',
    'Thumbnail public cache URL must respect the configured base path for module-key cache paths.'
);
sr_storage_helper_assert(
    sr_thumbnail_public_cache_url('../bad.jpg') === '',
    'Thumbnail public cache URL helper must reject paths outside the generated cache pattern.'
);
unset($GLOBALS['sr_storage_helper_test_base_path']);

if (extension_loaded('gd') && function_exists('imagecreatefrompng') && function_exists('imagepng')) {
    $fixtureDir = $root . '/storage/cache/check-storage-helpers';
    if (!is_dir($fixtureDir)) {
        @mkdir($fixtureDir, 0755, true);
    }
    $fixturePath = $fixtureDir . '/thumbnail-source.png';
    $fixtureImage = imagecreatetruecolor(8, 8);
    if ($fixtureImage instanceof GdImage) {
        $fixtureColor = imagecolorallocate($fixtureImage, 220, 40, 40);
        if ($fixtureColor !== false) {
            imagefilledrectangle($fixtureImage, 0, 0, 7, 7, $fixtureColor);
        }
        imagepng($fixtureImage, $fixturePath);
        imagedestroy($fixtureImage);
    }
    $storageKey = 'cache/check-storage-helpers/thumbnail-source.png';
    $source = [
        'public' => true,
        'module_key' => 'community',
        'storage_driver' => 'local',
        'storage_key' => $storageKey,
        'mime_type' => 'application/octet-stream',
        'checksum_sha256' => hash_file('sha256', $fixturePath) ?: '',
        'public_url' => '/fallback.png',
    ];
    sr_storage_helper_assert(
        sr_thumbnail_public_url(new PDO('sqlite::memory:'), $source, ['width' => 160, 'height' => 90, 'max_source_bytes' => 1]) === '/fallback.png',
        'Thumbnail helper must apply source byte limits to local sources before generating a variant.'
    );
    $thumbnailUrl = sr_thumbnail_public_url(new PDO('sqlite::memory:'), $source, [
        'width' => 160,
        'height' => 90,
        'mode' => 'cover',
        'quality' => 82,
    ]);
    sr_storage_helper_assert(
        preg_match('#\A/storage/cache/thumbnails/community/[a-f0-9]{2}/[a-f0-9]{64}_w160_h90_cover_q82_source_[a-f0-9]{16}\.png\z#', $thumbnailUrl) === 1,
        'Thumbnail helper must use the actual image MIME and return a module-key source-version public cache URL when GD can generate the variant.'
    );
    $thumbnailPath = $root . parse_url($thumbnailUrl, PHP_URL_PATH);
    sr_storage_helper_assert(is_file($thumbnailPath), 'Thumbnail helper must create the cache image file.');
    sr_storage_helper_assert(
        sr_thumbnail_public_url(new PDO('sqlite::memory:'), $source, ['width' => 160, 'height' => 90]) === $thumbnailUrl,
        'Thumbnail helper must reuse an existing variant cache file.'
    );
    sr_thumbnail_delete_variants($source);
    sr_storage_helper_assert(!is_file($thumbnailPath), 'Thumbnail helper must delete cached variants for the source.');
    @unlink($root . '/storage/' . $storageKey);
    @unlink($fixturePath);
}

$adminCacheFixtureDir = $root . '/storage/cache/thumbnails/community/ab';
if (!is_dir($adminCacheFixtureDir)) {
    @mkdir($adminCacheFixtureDir, 0755, true);
}
$adminCacheFixtureRelative = 'community/ab/' . str_repeat('b', 64) . '_w160_h90_cover_q82_source_' . str_repeat('d', 16) . '.jpg';
$adminCacheFixturePath = $adminCacheFixtureDir . '/' . basename($adminCacheFixtureRelative);
file_put_contents($adminCacheFixturePath, 'thumbnail-cache-fixture');
touch($adminCacheFixturePath, strtotime('2001-01-02 12:00:00'));
$adminCacheScan = sr_admin_thumbnail_cache_scan([
    'date_from' => '2001-01-02',
    'date_to' => '2001-01-02',
    'module_key' => 'community',
]);
sr_storage_helper_assert(
    (int) (($adminCacheScan['summary']['total_count'] ?? 0)) >= 1,
    'Admin thumbnail cache scan must find generated-cache-pattern files in the selected date range and module filter.'
);
sr_storage_helper_assert(
    sr_admin_thumbnail_cache_parse_relative_path('../bad.jpg') === null,
    'Admin thumbnail cache parser must reject paths outside the generated cache pattern.'
);
$adminCacheCleanup = sr_admin_thumbnail_cache_cleanup([
    'date_from' => '2001-01-02',
    'date_to' => '2001-01-02',
    'module_key' => 'community',
]);
sr_storage_helper_assert(
    (int) ($adminCacheCleanup['deleted_count'] ?? 0) >= 1 && !is_file($adminCacheFixturePath),
    'Admin thumbnail cache cleanup must delete matching generated cache files.'
);

$adminCacheLimitFixtureDir = $root . '/storage/cache/thumbnails/community/cd';
if (!is_dir($adminCacheLimitFixtureDir)) {
    @mkdir($adminCacheLimitFixtureDir, 0755, true);
}
$adminCacheLimitFixturePaths = [];
foreach (['1', '2', '3'] as $index) {
    $relative = 'community/cd/' . str_repeat($index, 64) . '_w160_h90_cover_q82_source_' . str_repeat('e', 16) . '.jpg';
    $path = $adminCacheLimitFixtureDir . '/' . basename($relative);
    file_put_contents($path, 'thumbnail-cache-limit-fixture');
    touch($path, strtotime('2001-01-03 12:00:00'));
    $adminCacheLimitFixturePaths[] = $path;
}
$adminCacheLimitedCleanup = sr_admin_thumbnail_cache_cleanup([
    'date_from' => '2001-01-03',
    'date_to' => '2001-01-03',
    'module_key' => 'community',
], 2);
sr_storage_helper_assert(
    (int) ($adminCacheLimitedCleanup['deleted_count'] ?? 0) === 2 && !empty($adminCacheLimitedCleanup['limit_reached']),
    'Admin thumbnail cache cleanup must stop at the per-request limit and report remaining work.'
);
$adminCacheRemainingLimitFixtures = array_values(array_filter($adminCacheLimitFixturePaths, static fn (string $path): bool => is_file($path)));
sr_storage_helper_assert(
    count($adminCacheRemainingLimitFixtures) === 1,
    'Admin thumbnail cache limited cleanup must leave files beyond the per-request limit.'
);
sr_admin_thumbnail_cache_cleanup([
    'date_from' => '2001-01-03',
    'date_to' => '2001-01-03',
    'module_key' => 'community',
], 10);

if ($errors !== []) {
    fwrite(STDERR, "storage helper checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "storage helper checks completed.\n";
