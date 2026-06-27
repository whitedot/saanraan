<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/feed-cache', 'view');

$feedCacheStoreStatus = sr_community_feed_cache_persistent_store_status($pdo);
$feedCacheContextRows = sr_community_feed_cache_admin_context_rows($pdo);

include SR_ROOT . '/modules/community/views/admin-feed-cache.php';
