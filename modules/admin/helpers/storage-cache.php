<?php

declare(strict_types=1);

function sr_admin_thumbnail_cache_root(): string
{
    return SR_ROOT . '/storage/cache/thumbnails';
}

function sr_admin_thumbnail_cache_filters_from_request(): array
{
    return [
        'date_from' => sr_admin_thumbnail_cache_date_filter(sr_get_string('date_from', 20)),
        'date_to' => sr_admin_thumbnail_cache_date_filter(sr_get_string('date_to', 20)),
        'module_key' => sr_admin_thumbnail_cache_module_filter(sr_get_string('module_key', 40)),
    ];
}

function sr_admin_thumbnail_cache_date_filter(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) === 1 ? $value : '';
}

function sr_admin_thumbnail_cache_module_filter(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    return preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $value) === 1 ? $value : '';
}

function sr_admin_thumbnail_cache_scan(array $filters = []): array
{
    $root = sr_admin_thumbnail_cache_root();
    $rows = [];
    $totalBytes = 0;
    $dateCounts = [];
    $dateBytes = [];
    $variantCounts = [];
    $oldestAt = '';
    $newestAt = '';

    if (!is_dir($root)) {
        return sr_admin_thumbnail_cache_scan_result($rows, [
            'total_count' => 0,
            'total_bytes' => 0,
            'oldest_at' => '',
            'newest_at' => '',
            'date_counts' => [],
            'date_bytes' => [],
            'variant_counts' => [],
        ]);
    }

    $fromTimestamp = sr_admin_thumbnail_cache_filter_start_timestamp((string) ($filters['date_from'] ?? ''));
    $toTimestamp = sr_admin_thumbnail_cache_filter_end_timestamp((string) ($filters['date_to'] ?? ''));
    $moduleFilter = sr_admin_thumbnail_cache_module_filter((string) ($filters['module_key'] ?? ''));
    $rootRealPath = realpath($root);
    if (!is_string($rootRealPath)) {
        return sr_admin_thumbnail_cache_scan_result($rows, [
            'total_count' => 0,
            'total_bytes' => 0,
            'oldest_at' => '',
            'newest_at' => '',
            'date_counts' => [],
            'date_bytes' => [],
            'variant_counts' => [],
        ]);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootRealPath, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $path = $fileInfo->getPathname();
        $relative = sr_admin_thumbnail_cache_relative_path($rootRealPath, $path);
        $parsed = sr_admin_thumbnail_cache_parse_relative_path($relative);
        if ($parsed === null) {
            continue;
        }
        if ($moduleFilter !== '' && (string) ($parsed['module_key'] ?? '') !== $moduleFilter) {
            continue;
        }

        $mtime = (int) $fileInfo->getMTime();
        if (($fromTimestamp > 0 && $mtime < $fromTimestamp) || ($toTimestamp > 0 && $mtime > $toTimestamp)) {
            continue;
        }

        $sizeBytes = max(0, (int) $fileInfo->getSize());
        $modifiedAt = date('Y-m-d H:i:s', $mtime);
        $dateKey = date('Y-m-d', $mtime);
        $variantKey = (string) $parsed['variant_key'];

        $rows[] = [
            'relative_path' => $relative,
            'public_path' => sr_thumbnail_public_cache_url('cache/thumbnails/' . $relative),
            'module_key' => (string) $parsed['module_key'],
            'hash_prefix' => (string) $parsed['hash_prefix'],
            'source_hash' => (string) $parsed['source_hash'],
            'variant_key' => $variantKey,
            'source_version' => (string) $parsed['source_version'],
            'extension' => (string) $parsed['extension'],
            'size_bytes' => $sizeBytes,
            'modified_at' => $modifiedAt,
        ];

        $totalBytes += $sizeBytes;
        $dateCounts[$dateKey] = (int) ($dateCounts[$dateKey] ?? 0) + 1;
        $dateBytes[$dateKey] = (int) ($dateBytes[$dateKey] ?? 0) + $sizeBytes;
        $variantCounts[$variantKey] = (int) ($variantCounts[$variantKey] ?? 0) + 1;
        if ($oldestAt === '' || $modifiedAt < $oldestAt) {
            $oldestAt = $modifiedAt;
        }
        if ($newestAt === '' || $modifiedAt > $newestAt) {
            $newestAt = $modifiedAt;
        }
    }

    usort($rows, function (array $left, array $right): int {
        return [(string) $right['modified_at'], (string) $right['relative_path']] <=> [(string) $left['modified_at'], (string) $left['relative_path']];
    });
    krsort($dateCounts);
    krsort($dateBytes);
    ksort($variantCounts);

    return sr_admin_thumbnail_cache_scan_result($rows, [
        'total_count' => count($rows),
        'total_bytes' => $totalBytes,
        'oldest_at' => $oldestAt,
        'newest_at' => $newestAt,
        'date_counts' => $dateCounts,
        'date_bytes' => $dateBytes,
        'variant_counts' => $variantCounts,
    ]);
}

function sr_admin_thumbnail_cache_scan_result(array $rows, array $summary): array
{
    return [
        'rows' => $rows,
        'summary' => $summary,
    ];
}

function sr_admin_thumbnail_cache_relative_path(string $rootRealPath, string $path): string
{
    $realPath = realpath($path);
    if (!is_string($realPath)) {
        return '';
    }

    $prefix = rtrim($rootRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($realPath, $prefix)) {
        return '';
    }

    return str_replace(DIRECTORY_SEPARATOR, '/', substr($realPath, strlen($prefix)));
}

function sr_admin_thumbnail_cache_parse_relative_path(string $relative): ?array
{
    if (preg_match('#\A([a-z][a-z0-9_]{1,39})/([a-f0-9]{2})/([a-f0-9]{64})_([A-Za-z0-9_]+)_([a-f0-9]{16,64})\.(jpe?g|png|gif|webp)\z#i', $relative, $matches) === 1) {
        return [
            'module_key' => strtolower($matches[1]),
            'hash_prefix' => strtolower($matches[2]),
            'source_hash' => strtolower($matches[3]),
            'variant_key' => $matches[4],
            'source_version' => strtolower($matches[5]),
            'extension' => strtolower($matches[6]),
        ];
    }

    if (preg_match('#\A([a-f0-9]{2})/([a-f0-9]{64})_([A-Za-z0-9_]+)_([0-9]+)\.(jpe?g|png|gif|webp)\z#i', $relative, $matches) === 1) {
        return [
            'module_key' => 'legacy',
            'hash_prefix' => strtolower($matches[1]),
            'source_hash' => strtolower($matches[2]),
            'variant_key' => $matches[3],
            'source_version' => $matches[4],
            'extension' => strtolower($matches[5]),
        ];
    }

    return null;
}

function sr_admin_thumbnail_cache_filter_start_timestamp(string $date): int
{
    if (sr_admin_thumbnail_cache_date_filter($date) === '') {
        return 0;
    }

    $timestamp = strtotime($date . ' 00:00:00');
    return is_int($timestamp) ? $timestamp : 0;
}

function sr_admin_thumbnail_cache_filter_end_timestamp(string $date): int
{
    if (sr_admin_thumbnail_cache_date_filter($date) === '') {
        return 0;
    }

    $timestamp = strtotime($date . ' 23:59:59');
    return is_int($timestamp) ? $timestamp : 0;
}

function sr_admin_thumbnail_cache_cleanup_limit(): int
{
    return 200;
}

function sr_admin_thumbnail_cache_cleanup(array $filters = [], int $limit = 0): array
{
    $root = sr_admin_thumbnail_cache_root();
    $deletedCount = 0;
    $deletedBytes = 0;
    $errors = [];
    $processedCount = 0;
    $limit = $limit > 0 ? $limit : sr_admin_thumbnail_cache_cleanup_limit();
    $limitReached = false;
    $rootRealPath = realpath($root);
    if (!is_string($rootRealPath)) {
        return [
            'deleted_count' => 0,
            'deleted_bytes' => 0,
            'errors' => [],
            'limit' => $limit,
            'limit_reached' => false,
        ];
    }

    $fromTimestamp = sr_admin_thumbnail_cache_filter_start_timestamp((string) ($filters['date_from'] ?? ''));
    $toTimestamp = sr_admin_thumbnail_cache_filter_end_timestamp((string) ($filters['date_to'] ?? ''));
    $moduleFilter = sr_admin_thumbnail_cache_module_filter((string) ($filters['module_key'] ?? ''));
    $prefix = rtrim($rootRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootRealPath, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $path = $fileInfo->getPathname();
        $relative = sr_admin_thumbnail_cache_relative_path($rootRealPath, $path);
        $parsed = sr_admin_thumbnail_cache_parse_relative_path($relative);
        if ($parsed === null) {
            continue;
        }
        if ($moduleFilter !== '' && (string) ($parsed['module_key'] ?? '') !== $moduleFilter) {
            continue;
        }

        $mtime = (int) $fileInfo->getMTime();
        if (($fromTimestamp > 0 && $mtime < $fromTimestamp) || ($toTimestamp > 0 && $mtime > $toTimestamp)) {
            continue;
        }

        $realPath = realpath($path);
        if (!is_string($realPath) || !str_starts_with($realPath, $prefix) || !is_file($realPath)) {
            continue;
        }

        if ($processedCount >= $limit) {
            $limitReached = true;
            break;
        }

        $processedCount++;
        $sizeBytes = max(0, (int) filesize($realPath));
        if (@unlink($realPath)) {
            $deletedCount++;
            $deletedBytes += $sizeBytes;
        } else {
            $errors[] = $relative;
        }
    }

    sr_admin_thumbnail_cache_prune_empty_dirs($rootRealPath);

    return [
        'deleted_count' => $deletedCount,
        'deleted_bytes' => $deletedBytes,
        'errors' => $errors,
        'limit' => $limit,
        'limit_reached' => $limitReached,
    ];
}

function sr_admin_thumbnail_cache_prune_empty_dirs(string $rootRealPath): void
{
    if (!is_dir($rootRealPath)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootRealPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo instanceof SplFileInfo && $fileInfo->isDir()) {
            $path = $fileInfo->getPathname();
            if (is_dir($path) && (glob($path . DIRECTORY_SEPARATOR . '*') ?: []) === []) {
                @rmdir($path);
            }
        }
    }
}
