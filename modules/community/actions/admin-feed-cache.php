<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/feed-cache', 'view');
$canViewCommunityThumbnailFileCache = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/storage-cache', 'view');
$canViewCommunityEmbedManager = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/embed-manager', 'view');

sr_community_use_board_settings_runtime_cache();

$settings = sr_community_settings($pdo);
$boards = sr_community_enabled_boards($pdo);
$baselineBoards = sr_community_feed_cache_public_baseline_boards($boards);
$baselineBoardIds = sr_community_feed_cache_public_baseline_board_ids($boards);
$feedCacheStoreStatus = sr_community_feed_cache_persistent_store_status($pdo);
$feedCacheBoardRows = sr_community_feed_cache_admin_board_rows($pdo, $boards, $settings);
$feedCacheContextRows = sr_community_feed_cache_admin_context_rows($baselineBoardIds);

include SR_ROOT . '/modules/community/views/admin-feed-cache.php';
