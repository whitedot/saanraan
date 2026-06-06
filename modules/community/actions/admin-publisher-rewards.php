<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/publisher-rewards', 'view');

$publisherRewardFilters = sr_community_publisher_reward_filters_from_request();
$publisherRewardPagination = sr_admin_pagination_from_total($pdo, sr_community_publisher_reward_count($pdo, $publisherRewardFilters));
$publisherRewardLogs = sr_community_publisher_reward_logs($pdo, (int) $publisherRewardPagination['per_page'], sr_admin_pagination_offset($publisherRewardPagination), $publisherRewardFilters);

include SR_ROOT . '/modules/community/views/admin-publisher-rewards.php';
