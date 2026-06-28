<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/core/helpers/url-embed.php';

$urlEmbedCacheModuleKey = sr_url_embed_fragment_cache_admin_module_key((string) ($urlEmbedCacheModuleKey ?? ''));
$urlEmbedCacheAdminPath = (string) ($urlEmbedCacheAdminPath ?? '');
$urlEmbedCacheModuleLabel = (string) ($urlEmbedCacheModuleLabel ?? sr_url_embed_module_label($urlEmbedCacheModuleKey));
if ($urlEmbedCacheModuleKey === '' || $urlEmbedCacheAdminPath === '') {
    http_response_code(404);
    echo 'URL embed cache admin context is missing.';
    return;
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], $urlEmbedCacheAdminPath, 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$filters = sr_url_embed_fragment_cache_admin_filters_from_request($urlEmbedCacheModuleKey);
$canDeleteUrlEmbedFragmentCache = sr_admin_has_permission($pdo, (int) $account['id'], $urlEmbedCacheAdminPath, 'delete');

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $urlEmbedCacheAdminPath, 'delete');

    $postFilters = [
        'module_key' => $urlEmbedCacheModuleKey,
        'date_from' => sr_url_embed_fragment_cache_admin_date_filter(sr_post_string('date_from', 20)),
        'date_to' => sr_url_embed_fragment_cache_admin_date_filter(sr_post_string('date_to', 20)),
    ];
    $confirmText = trim(sr_post_string('confirm_text', 40));
    $postErrors = [];
    $postNotice = '';
    if ($confirmText !== '정리') {
        $postErrors[] = '정리를 실행하려면 확인 문구를 입력하세요.';
    }

    if ($postErrors === []) {
        $cleanupLimit = sr_url_embed_fragment_cache_admin_cleanup_limit();
        $cleanupResult = sr_url_embed_fragment_cache_admin_cleanup($postFilters, $cleanupLimit);
        $deletedCount = (int) ($cleanupResult['deleted_count'] ?? 0);
        $deletedBytes = (int) ($cleanupResult['deleted_bytes'] ?? 0);
        $limitReached = !empty($cleanupResult['limit_reached']);
        $cleanupErrors = isset($cleanupResult['errors']) && is_array($cleanupResult['errors']) ? $cleanupResult['errors'] : [];
        $postNotice = $urlEmbedCacheModuleLabel . ' 임베드 캐시 ' . number_format($deletedCount) . '개, ' . sr_format_bytes($deletedBytes) . '를 정리했습니다.';
        if ($limitReached) {
            $postNotice .= ' 한 번에 최대 ' . number_format($cleanupLimit) . '개씩 처리합니다. 남은 파일이 있으면 다시 정리해 주세요.';
        }
        if ($cleanupErrors !== []) {
            $postErrors[] = '일부 임베드 캐시 파일을 삭제하지 못했습니다: ' . implode(', ', array_slice(array_map('strval', $cleanupErrors), 0, 5));
        }

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'admin.url_embed_fragment_cache.cleaned',
            'target_type' => 'url_embed_fragment_cache',
            'target_id' => $urlEmbedCacheModuleKey,
            'result' => $postErrors === [] ? 'success' : 'partial',
            'message' => 'URL embed fragment cache cleaned.',
            'metadata' => [
                'module_key' => $urlEmbedCacheModuleKey,
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

    $query = http_build_query(array_filter([
        'date_from' => (string) $postFilters['date_from'],
        'date_to' => (string) $postFilters['date_to'],
    ], static fn (string $value): bool => $value !== ''));
    sr_admin_flash_result(sr_admin_action_result($postErrors, $postNotice));
    sr_redirect($urlEmbedCacheAdminPath . ($query !== '' ? '?' . $query : ''));
}

$cacheScan = sr_url_embed_fragment_cache_admin_scan($filters);
$urlEmbedCacheRows = isset($cacheScan['rows']) && is_array($cacheScan['rows']) ? $cacheScan['rows'] : [];
$urlEmbedCacheRowTotal = count($urlEmbedCacheRows);
$urlEmbedCacheRows = array_slice($urlEmbedCacheRows, 0, 500);
$urlEmbedCacheSummary = isset($cacheScan['summary']) && is_array($cacheScan['summary']) ? $cacheScan['summary'] : [];
$urlEmbedCacheCleanupLimit = sr_url_embed_fragment_cache_admin_cleanup_limit();

include SR_ROOT . '/core/views/admin-url-embed-fragment-cache.php';
