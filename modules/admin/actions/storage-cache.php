<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/storage-cache', 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$filters = sr_admin_thumbnail_cache_filters_from_request();

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/storage-cache', 'delete');

    $postFilters = [
        'date_from' => sr_admin_thumbnail_cache_date_filter(sr_post_string('date_from', 20)),
        'date_to' => sr_admin_thumbnail_cache_date_filter(sr_post_string('date_to', 20)),
        'module_key' => sr_admin_thumbnail_cache_module_filter(sr_post_string('module_key', 40)),
    ];
    $confirmText = trim(sr_post_string('confirm_text', 40));
    $postErrors = [];
    $postNotice = '';

    if ($confirmText !== '정리') {
        $postErrors[] = '정리를 실행하려면 확인 문구를 입력하세요.';
    }

    if ($postErrors === []) {
        $cleanupLimit = sr_admin_thumbnail_cache_cleanup_limit();
        $cleanupResult = sr_admin_thumbnail_cache_cleanup($postFilters, $cleanupLimit);
        $deletedCount = (int) ($cleanupResult['deleted_count'] ?? 0);
        $deletedBytes = (int) ($cleanupResult['deleted_bytes'] ?? 0);
        $limitReached = !empty($cleanupResult['limit_reached']);
        $cleanupErrors = isset($cleanupResult['errors']) && is_array($cleanupResult['errors']) ? $cleanupResult['errors'] : [];
        $postNotice = '썸네일 캐시 ' . number_format($deletedCount) . '개, ' . sr_format_bytes($deletedBytes) . '를 정리했습니다.';
        if ($limitReached) {
            $postNotice .= ' 한 번에 최대 ' . number_format($cleanupLimit) . '개씩 처리합니다. 남은 파일이 있으면 다시 정리해 주세요.';
        }
        if ($cleanupErrors !== []) {
            $postErrors[] = '일부 캐시 파일을 삭제하지 못했습니다: ' . implode(', ', array_slice(array_map('strval', $cleanupErrors), 0, 5));
        }

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'admin.thumbnail_cache.cleaned',
            'target_type' => 'storage_cache',
            'target_id' => 'thumbnails',
            'result' => $postErrors === [] ? 'success' : 'partial',
            'message' => 'Thumbnail cache cleaned.',
            'metadata' => [
                'date_from' => (string) $postFilters['date_from'],
                'date_to' => (string) $postFilters['date_to'],
                'deleted_count' => $deletedCount,
                'deleted_bytes' => $deletedBytes,
                'cleanup_limit' => $cleanupLimit,
                'limit_reached' => $limitReached,
                'failed_count' => count($cleanupErrors),
            ],
        ]);
    }

    $query = http_build_query(array_filter($postFilters, static fn (string $value): bool => $value !== ''));
    sr_admin_flash_result(sr_admin_action_result($postErrors, $postNotice));
    sr_redirect('/admin/storage-cache' . ($query !== '' ? '?' . $query : ''));
}

$moduleOptionFilters = $filters;
$moduleOptionFilters['module_key'] = '';
$moduleOptionScan = sr_admin_thumbnail_cache_scan($moduleOptionFilters);
$moduleOptions = [];
foreach ((array) ($moduleOptionScan['rows'] ?? []) as $moduleOptionRow) {
    $moduleOptionKey = (string) ($moduleOptionRow['module_key'] ?? '');
    if ($moduleOptionKey !== '') {
        $moduleOptions[$moduleOptionKey] = sr_admin_code_label($moduleOptionKey, 'module_key');
    }
}
if ((string) ($filters['module_key'] ?? '') !== '') {
    $moduleOptions[(string) $filters['module_key']] = sr_admin_code_label((string) $filters['module_key'], 'module_key');
}
asort($moduleOptions, SORT_NATURAL);

$cacheScan = sr_admin_thumbnail_cache_scan($filters);
$cacheRows = isset($cacheScan['rows']) && is_array($cacheScan['rows']) ? $cacheScan['rows'] : [];
$cacheRowTotal = count($cacheRows);
$cacheRows = array_slice($cacheRows, 0, 500);
$cacheSummary = isset($cacheScan['summary']) && is_array($cacheScan['summary']) ? $cacheScan['summary'] : [];
$canDeleteStorageCache = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/storage-cache', 'delete');

include SR_ROOT . '/modules/admin/views/storage-cache.php';
