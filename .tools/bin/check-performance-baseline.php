#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers/output.php';

$errors = [];

function sr_performance_baseline_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_performance_baseline_read(string $file): string
{
    if (!is_file($file)) {
        sr_performance_baseline_error('Required file is missing: ' . $file);
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_performance_baseline_error('Required file cannot be read: ' . $file);
        return '';
    }

    return $contents;
}

function sr_performance_baseline_require_markers(string $file, array $markers): void
{
    $contents = sr_performance_baseline_read($file);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_performance_baseline_error('Performance baseline marker missing in ' . $file . ': ' . $marker);
        }
    }
}

function sr_performance_baseline_assert_same(string $label, string $expected, string $actual): void
{
    if ($expected !== $actual) {
        sr_performance_baseline_error($label . ' mismatch: expected ' . $expected . ', got ' . $actual);
    }
}

sr_performance_baseline_assert_same(
    'Download cache-control public immutable policy',
    'public, max-age=31536000, immutable',
    sr_download_cache_control(' public, max-age=31536000, immutable ')
);
sr_performance_baseline_assert_same(
    'Download cache-control private no-store policy',
    'private, no-store, no-cache, must-revalidate',
    sr_download_cache_control('private, no-store, no-cache, must-revalidate')
);
sr_performance_baseline_assert_same(
    'Download cache-control empty fallback',
    'no-store, no-cache, must-revalidate',
    sr_download_cache_control('')
);
sr_performance_baseline_assert_same(
    'Download cache-control control character fallback',
    'no-store, no-cache, must-revalidate',
    sr_download_cache_control("private, max-age=300\r\nX-Test: injected")
);
sr_performance_baseline_assert_same(
    'Download cache-control unsafe character fallback',
    'no-store, no-cache, must-revalidate',
    sr_download_cache_control('private, max-age=300()')
);

sr_performance_baseline_require_markers('docs/performance-baseline-evidence.md', [
    '관리자 대형 목록',
    '목록 쿼리 제한',
    '인덱스 안전선',
    '캐시 경로',
    'HTML 응답 캐시',
    '동적 HTML no-store',
    'sitemap/export 상한',
    '관리자 CSV export 상한',
    '고부하 작업',
    '.tools/bin/check-performance-baseline.php',
    '.tools/bin/check-community-board-copy-limits.php',
]);

sr_performance_baseline_require_markers('docs/performance-policy.md', [
    '로그인 상태 HTML 파일 캐시',
    '관리자 화면 HTML 캐시',
    '개인정보 export 결과 캐시',
    '자산 잔액/권리 상태 장기 캐시',
    '관리자 CSV export',
    '페이지네이션',
    '인덱스',
    '핵심 인덱스',
]);

sr_performance_baseline_require_markers('docs/admin-ui-guide.md', [
    '고부하 관리자 작업',
    '작업 테이블형',
    'lock_token',
    'query snapshot',
    'drift',
]);

sr_performance_baseline_require_markers('modules/community/helpers/board-copy.php', [
    'function sr_community_board_copy_limits(): array',
    "'posts' => 500",
    "'comments' => 5000",
    "'attachments' => 500",
    "'bytes' => 314572800",
    'function sr_community_board_copy_batch_errors(array $counts): array',
]);

sr_performance_baseline_require_markers('core/helpers/output.php', [
    'function sr_rich_text_purifier_cache_dir(): string',
    "SR_ROOT . '/storage/cache/htmlpurifier'",
    'function sr_stylesheet_tag(',
    'filemtime($file)',
    'function sr_send_download_headers(string $contentType, string $filename, string $disposition = \'attachment\', ?int $contentLength = null',
    "header('Cache-Control: ' . sr_download_cache_control(\$cacheControl))",
    'function sr_download_cache_control(string $cacheControl): string',
    'no-store, no-cache, must-revalidate',
]);

sr_performance_baseline_require_markers('core/helpers/runtime.php', [
    "header('Cache-Control: no-store, no-cache, must-revalidate')",
    "header('Pragma: no-cache')",
]);

sr_performance_baseline_require_markers('.tools/bin/smoke-http.php', [
    "'cache-control' => 'no-store'",
]);

sr_performance_baseline_require_markers('modules/admin/helpers/pagination.php', [
    'function sr_admin_pagination_from_total(PDO $pdo, int $total, string $pageParam = \'page\'): array',
    'function sr_admin_pagination_offset(array $pagination): int',
    'function sr_admin_pagination_html(array $pagination, string $label): string',
    'function sr_admin_paginate_array(PDO $pdo, array $rows, string $pageParam = \'page\'): array',
]);

$paginationPairs = [
    ['modules/admin/actions/modules.php', 'modules/admin/views/modules.php', 'sr_admin_paginate_array', 'sr_admin_pagination_html'],
    ['modules/admin/actions/roles.php', 'modules/admin/views/roles.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/admin/actions/audit-logs.php', 'modules/admin/views/audit-logs.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/member/actions/admin-members.php', 'modules/member/views/admin-members.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/member/actions/admin-groups.php', 'modules/member/views/admin-groups.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/content/actions/admin-contents.php', 'modules/content/views/admin-contents.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/content/actions/admin-content-groups.php', 'modules/content/views/admin-content-groups.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/content/actions/admin-series.php', 'modules/content/views/admin-series.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/content/actions/admin-download-files.php', 'modules/content/views/admin-download-files.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/content/actions/admin-file-downloads.php', 'modules/content/views/admin-file-downloads.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/content/actions/admin-content-author-rewards.php', 'modules/content/views/admin-content-author-rewards.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/community/actions/admin-posts.php', 'modules/community/views/admin-posts.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/community/actions/admin-boards.php', 'modules/community/views/admin-boards.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/community/actions/admin-board-groups.php', 'modules/community/views/admin-board-groups.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/community/actions/admin-reports.php', 'modules/community/views/admin-reports.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/community/actions/admin-series.php', 'modules/community/views/admin-series.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/community/actions/admin-publisher-rewards.php', 'modules/community/views/admin-publisher-rewards.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/point/actions/admin-points.php', 'modules/point/views/admin-points.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/reward/actions/admin-rewards.php', 'modules/reward/views/admin-rewards.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/deposit/actions/admin-deposits.php', 'modules/deposit/views/admin-deposits.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/reward/actions/admin-rewards-withdrawal-requests.php', 'modules/reward/views/admin-withdrawal-requests.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/deposit/actions/admin-deposits-refund-requests.php', 'modules/deposit/views/admin-refund-requests.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/asset_exchange/actions/admin-asset-exchange-logs.php', 'modules/asset_exchange/views/admin-asset-exchange-logs.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/coupon/actions/admin-coupons.php', 'modules/coupon/views/admin-coupons.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/notification/actions/admin-notifications.php', 'modules/notification/views/admin-notifications.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/notification/actions/admin-admin-notifications.php', 'modules/notification/views/admin-admin-notifications.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/privacy/actions/admin-privacy-requests.php', 'modules/privacy/views/admin-privacy-requests.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/quiz/actions/admin-quiz.php', 'modules/quiz/actions/admin-quiz.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/quiz/actions/admin-attempts.php', 'modules/quiz/actions/admin-attempts.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/survey/actions/admin-responses.php', 'modules/survey/actions/admin-responses.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/banner/actions/admin-banners.php', 'modules/banner/views/admin-banners.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/popup_layer/actions/admin-popup-layers.php', 'modules/popup_layer/views/admin-popup-layers.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
    ['modules/logo_manager/actions/admin-logo-manager.php', 'modules/logo_manager/views/admin-logo-manager.php', 'sr_admin_pagination_from_total', 'sr_admin_pagination_html'],
];

foreach ($paginationPairs as [$actionFile, $viewFile, $actionMarker, $viewMarker]) {
    sr_performance_baseline_require_markers($actionFile, [$actionMarker]);
    sr_performance_baseline_require_markers($viewFile, [$viewMarker]);
}

$limitedQueryFiles = [
    'modules/member/helpers/admin-members.php',
    'modules/member/helpers/groups.php',
    'modules/admin/helpers/roles.php',
    'modules/admin/helpers/audit-logs.php',
    'modules/content/helpers.php',
    'modules/content/helpers/series.php',
    'modules/content/helpers/files.php',
    'modules/community/helpers/posts.php',
    'modules/community/helpers/boards.php',
    'modules/community/helpers/reports.php',
    'modules/community/helpers/series.php',
    'modules/community/helpers/publisher-rewards.php',
    'modules/coupon/helpers.php',
    'modules/notification/helpers.php',
    'modules/privacy/helpers/requests.php',
    'modules/quiz/helpers.php',
    'modules/admin/helpers/asset-ledgers.php',
];

foreach ($limitedQueryFiles as $file) {
    $contents = sr_performance_baseline_read($file);
    if ($contents !== ''
        && !str_contains($contents, 'LIMIT :limit_value OFFSET :offset_value')
        && !str_contains($contents, 'sr_admin_pagination_offset($pagination)')
        && !(str_contains($contents, 'LIMIT') && str_contains($contents, 'OFFSET') && str_contains($contents, '$limit'))
    ) {
        sr_performance_baseline_error('Admin list helper does not expose an expected LIMIT/OFFSET marker: ' . $file);
    }
}

$requiredIndexMarkers = [
    'modules/point/install.sql' => [
        'UNIQUE KEY uq_sr_point_balances_account (account_id)',
        'KEY idx_sr_point_transactions_account_created (account_id, created_at)',
        'KEY idx_sr_point_transactions_expiration (expires_at, expires_remaining)',
        'KEY idx_sr_point_transactions_reference (reference_type, reference_id)',
    ],
    'modules/reward/install.sql' => [
        'UNIQUE KEY uq_sr_reward_balances_account (account_id)',
        'KEY idx_sr_reward_transactions_account_created (account_id, created_at)',
        'KEY idx_sr_reward_transactions_reference (reference_type, reference_id)',
        'KEY idx_sr_reward_withdrawal_requests_status_requested (status, requested_at)',
    ],
    'modules/deposit/install.sql' => [
        'UNIQUE KEY uq_sr_deposit_balances_account (account_id)',
        'KEY idx_sr_deposit_transactions_account_created (account_id, created_at)',
        'KEY idx_sr_deposit_transactions_reference (reference_type, reference_id)',
        'KEY idx_sr_deposit_refund_requests_status_requested (status, requested_at)',
    ],
    'modules/coupon/install.sql' => [
        'KEY idx_sr_coupon_definitions_status_target (status, target_type, target_id)',
        'KEY idx_sr_coupon_issues_account_status (account_id, status, expires_at, id)',
        'UNIQUE KEY uq_sr_coupon_redemptions_dedupe (dedupe_key)',
        'KEY idx_sr_coupon_redemptions_reference (reference_module, reference_type, reference_id)',
    ],
    'modules/notification/install.sql' => [
        'KEY idx_sr_notifications_account (account_id, status, read_at, id)',
        'KEY idx_sr_notification_deliveries_channel_status (channel, status, id)',
        'KEY idx_sr_admin_notifications_status (status, severity, updated_at, id)',
        'UNIQUE KEY uq_sr_notification_event_templates_key (module_key, event_key)',
    ],
    'modules/privacy/install.sql' => [
        'KEY idx_sr_privacy_requests_account (account_id)',
        'KEY idx_sr_privacy_requests_status (status)',
        'KEY idx_sr_privacy_requests_created (created_at)',
    ],
    'modules/content/install.sql' => [
        'KEY idx_sr_content_items_status_updated (status, updated_at)',
        'KEY idx_sr_content_comments_thread (content_id, status, thread_root_id, parent_comment_id, id)',
        'UNIQUE KEY uq_sr_content_asset_access_dedupe (dedupe_key)',
        'KEY idx_sr_content_file_downloads_refund (refund_status, refunded_at)',
        'UNIQUE KEY uq_sr_content_access_entitlements_account_subject (account_id, subject_type, subject_id, access_kind)',
    ],
    'modules/community/install.sql' => [
        'KEY idx_sr_community_posts_board_status_id (board_id, status, id)',
        'KEY idx_sr_community_comments_thread (post_id, status, thread_root_id, parent_comment_id, id)',
        'KEY idx_sr_community_board_copy_jobs_status_stage_updated (status, stage, updated_at, id)',
        'UNIQUE KEY uq_sr_community_asset_logs_dedupe (dedupe_key)',
        'UNIQUE KEY uq_sr_community_access_entitlements_account_subject (account_id, subject_type, subject_id, event_key)',
    ],
    'modules/asset_exchange/install.sql' => [
        'UNIQUE KEY uq_sr_asset_exchange_logs_group (exchange_group_id)',
        'KEY idx_sr_asset_exchange_logs_account_created (account_id, created_at)',
        'KEY idx_sr_asset_exchange_logs_reexchange (account_id, to_module_key, status, created_at)',
        'KEY idx_sr_asset_exchange_logs_status_created (status, created_at)',
    ],
];

foreach ($requiredIndexMarkers as $file => $markers) {
    sr_performance_baseline_require_markers($file, $markers);
}

foreach ([
    'modules/content/sitemap.php',
    'modules/community/sitemap.php',
    'modules/quiz/sitemap.php',
    'modules/survey/sitemap.php',
    'modules/content/menu-links.php',
    'modules/content/privacy-export.php',
    'modules/community/privacy-export.php',
    'modules/point/privacy-export.php',
    'modules/reward/privacy-export.php',
    'modules/deposit/privacy-export.php',
    'modules/coupon/privacy-export.php',
] as $file) {
    sr_performance_baseline_require_markers($file, ['LIMIT 1000']);
}

sr_performance_baseline_require_markers('modules/survey/helpers.php', [
    'function sr_survey_admin_export_limits(): array',
    "'raw' => 5000",
    "'analysis' => 20000",
    "'codebook' => 10000",
    'function sr_survey_admin_export_raw_rows',
    'function sr_survey_admin_export_analysis_rows',
    'function sr_survey_admin_export_codebook_rows',
    'function sr_survey_csv_cell',
    'LIMIT \' . (string) max(1, min(10000, $limit))',
    'LIMIT \' . (string) max(1, min(20000, $limit))',
    'LIMIT \' . (string) max(1, min(5000, $limit))',
]);
sr_performance_baseline_require_markers('modules/survey/actions/admin-export.php', [
    "'limit' => \$exportLimit",
    'sr_survey_csv_row',
    'sr_send_download_headers',
    "fopen('php://output', 'wb')",
    'sr_survey_admin_export_codebook_rows',
    'sr_survey_admin_export_analysis_rows',
    'sr_survey_admin_export_raw_rows',
]);

$allowedStorageCacheFiles = [
    '.tools/bin/dev-router.php' => true,
    '.tools/bin/check-storage-helpers.php' => true,
    'core/helpers/storage.php' => true,
    'core/helpers/output.php' => true,
    'modules/community/helpers/boards.php' => true,
    'modules/community/helpers/feed-cache.php' => true,
    'modules/embed_manager/helpers.php' => true,
    'docs/core-decisions.md' => true,
    'docs/dependency-policy.md' => true,
    'docs/customization-guide.md' => true,
    'docs/deployment-protection.md' => true,
    'docs/implementation-snapshot.md' => true,
    'docs/admin-ui-guide.md' => true,
    'docs/module-guide.md' => true,
    'docs/performance-policy.md' => true,
    'docs/performance-baseline-evidence.md' => true,
    'docs/plans/thumbnail-cache-s3-plan.md' => true,
    'docs/rich-text-sanitizer-policy.md' => true,
    'docs/release-process.md' => true,
    'docs/verification-status.md' => true,
    'docs/records/improvement-hardening-verification-2026-06-11.md' => true,
    'modules/htmlpurifier/README.md' => true,
    'modules/admin/helpers/storage-cache.php' => true,
    'modules/admin/views/storage-cache.php' => true,
    '.tools/bin/check-dependency-policy.php' => true,
    '.tools/bin/check-htmlpurifier-runtime.php' => true,
    '.tools/bin/release-preflight.php' => true,
    '.tools/bin/check-release-package-policy.php' => true,
    '.tools/bin/check-rich-text-sanitizer.php' => true,
    '.tools/bin/check-rich-text-sanitizer-policy.php' => true,
    '.tools/bin/check-performance-policy.php' => true,
    '.tools/bin/check-performance-baseline.php' => true,
];

$allowedCacheControlHeaders = [
    'core/helpers/runtime.php' => [
        "header('Cache-Control: no-store, no-cache, must-revalidate')",
    ],
    'core/helpers/output.php' => [
        "header('Cache-Control: ' . sr_download_cache_control(\$cacheControl))",
        "header('Cache-Control: ' . sr_download_cache_control(\$cacheControl))",
    ],
    'modules/admin/actions/icon-image.php' => [
        "header('Cache-Control: private, max-age=300')",
        "sr_send_file_headers(\$mimeType, \$sizeBytes, 'private, max-age=31536000, immutable')",
    ],
    'modules/banner/actions/image.php' => [
        "header('Cache-Control: private, max-age=300')",
        "sr_send_file_headers(\$mimeType, \$sizeBytes, 'public, max-age=31536000, immutable')",
    ],
    'modules/community/actions/admin-level-recalculate.php' => [
        "sr_json_response([",
        "'Cache-Control: no-store'",
    ],
    'modules/community/actions/attachment.php' => [
        "header('Cache-Control: private, max-age=300')",
        "sr_send_download_headers(\$mimeType, (string) \$attachment['original_name'], \$disposition, \$recordedSize, 'private, no-store, no-cache, must-revalidate')",
    ],
    'modules/community/helpers/body-files.php' => [
        "sr_send_file_headers(\$mimeType, (int) (\$head['content_length'] ?? 0), 'private, max-age=300')",
    ],
    'modules/content/actions/cover-image.php' => [
        "header('Cache-Control: private, max-age=300')",
        "sr_send_file_headers(\$mimeType, \$sizeBytes, 'public, max-age=31536000, immutable')",
    ],
    'modules/quiz/actions/cover-image.php' => [
        "header('Cache-Control: private, max-age=300')",
        "sr_send_file_headers(\$mimeType, \$sizeBytes, 'public, max-age=31536000, immutable')",
    ],
    'modules/content/actions/download.php' => [
        "header('Cache-Control: private, max-age=300')",
        "sr_send_download_headers(\$mimeType, (string) \$file['original_name'], 'attachment', \$recordedSize, 'private, no-store, no-cache, must-revalidate')",
    ],
    'modules/content/helpers/body-files.php' => [
        "sr_send_file_headers(\$mimeType, (int) (\$head['content_length'] ?? 0), 'private, max-age=300')",
    ],
    'modules/logo_manager/actions/image.php' => [
        "header('Cache-Control: private, max-age=300')",
        "sr_send_file_headers(",
        "'public, max-age=31536000, immutable'",
    ],
    'modules/reaction/actions/icon.php' => [
        "header('Cache-Control: private, max-age=300')",
        "sr_send_file_headers(\$mimeType, \$sizeBytes, 'public, max-age=31536000, immutable')",
    ],
    'modules/member/actions/avatar.php' => [
        "header('Cache-Control: private, max-age=300')",
        "sr_send_file_headers(\$mimeType, \$sizeBytes, 'public, max-age=31536000, immutable')",
    ],
    'modules/popup_layer/helpers/body-files.php' => [
        "sr_send_file_headers(\$mimeType, (int) (\$head['content_length'] ?? 0), 'private, max-age=300')",
    ],
    'modules/survey/actions/cover-image.php' => [
        "header('Cache-Control: private, max-age=300')",
        "sr_send_file_headers(\$mimeType, \$sizeBytes, 'public, max-age=31536000, immutable')",
    ],
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator('.', FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $current): bool {
            $path = str_replace('\\', '/', $current->getPathname());
            if ($current->isDir()) {
                if ($path !== '.' && is_dir($path . '/.git')) {
                    return false;
                }
                return !str_contains($path, '/.git')
                    && !str_contains($path, '/config')
                    && !str_contains($path, '/modules/htmlpurifier/vendor')
                    && !str_contains($path, '/modules/ckeditor/vendor');
            }

            return true;
        }
    )
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    $path = str_replace('\\', '/', $file->getPathname());
    if (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }

    $extension = strtolower($file->getExtension());
    if (!in_array($extension, ['php', 'md'], true)) {
        continue;
    }

    if (!$file->isReadable()) {
        continue;
    }

    $contents = file_get_contents($file->getPathname());
    if (!is_string($contents)) {
        continue;
    }

    if ($extension === 'php'
        && preg_match('/file_put_contents\s*\([^;]*(?:storage\/cache|\.html|\.htm)/is', $contents) === 1
    ) {
        sr_performance_baseline_error('Unexpected file-based HTML/cache write candidate: ' . $path);
    }

    if ($extension === 'php'
        && preg_match('/ob_get_clean\s*\(\s*\).*file_put_contents|file_put_contents\s*\([^;]*ob_get_clean\s*\(\s*\)/is', $contents) === 1
    ) {
        sr_performance_baseline_error('Unexpected buffered response write candidate: ' . $path);
    }

    if ($extension === 'php' && (str_starts_with($path, 'core/') || str_starts_with($path, 'modules/'))) {
        if (preg_match_all('/header\s*\(\s*[\'"]Cache-Control:\s*([^\'"]+)[\'"]\s*\)/i', $contents, $matches) > 0) {
            $headers = $matches[0];
            $allowedHeaders = $allowedCacheControlHeaders[$path] ?? [];
            $actualCounts = array_count_values($headers);
            $allowedCounts = array_count_values($allowedHeaders);
            foreach ($actualCounts as $headerCall => $count) {
                if (($allowedCounts[$headerCall] ?? 0) !== $count) {
                    sr_performance_baseline_error('Unexpected Cache-Control header candidate in ' . $path . ': ' . $headerCall);
                }
            }
            if (!isset($allowedCacheControlHeaders[$path])) {
                sr_performance_baseline_error('Cache-Control header file is missing from performance allowlist: ' . $path);
            }
        }
    }

    if (!str_contains($contents, 'storage/cache')) {
        continue;
    }

    if (!isset($allowedStorageCacheFiles[$path])) {
        sr_performance_baseline_error('Unexpected storage/cache reference: ' . $path);
    }
}

if ($errors !== []) {
    fwrite(STDERR, "performance baseline checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "performance baseline checks completed.\n";
