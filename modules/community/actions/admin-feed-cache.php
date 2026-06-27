<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/feed-cache', 'view');

$settings = sr_community_settings($pdo);
$boards = sr_community_enabled_boards($pdo);
$baselineBoards = sr_community_feed_cache_public_baseline_boards($boards);
$baselineBoardIds = sr_community_feed_cache_public_baseline_board_ids($boards);
$feedCacheStoreStatus = sr_community_feed_cache_persistent_store_status($pdo);
$feedCacheBoardRows = sr_community_feed_cache_admin_board_rows($pdo, $boards, $settings);
$feedCacheContextRows = sr_community_feed_cache_admin_context_rows($baselineBoardIds);
$feedCacheLatestPreview = [];
$feedCachePopularPreview = [];

if ($baselineBoardIds !== []) {
    foreach ([['latest', 10, 'feedCacheLatestPreview'], ['views', 5, 'feedCachePopularPreview']] as $previewSpec) {
        [$sort, $limit, $targetVariable] = $previewSpec;
        [$sql, $params] = sr_community_feed_cache_post_feed_query($pdo, $baselineBoardIds, (int) $limit, (string) $sort, 'admin_feed_board_id_');
        if ($sql === '') {
            continue;
        }
        $stmt = $pdo->prepare($sql);
        foreach ($params as $paramKey => $paramValue) {
            $stmt->bindValue($paramKey, (int) $paramValue, PDO::PARAM_INT);
        }
        $stmt->execute();
        $$targetVariable = $stmt->fetchAll();
    }
}

include SR_ROOT . '/modules/community/views/admin-feed-cache.php';
