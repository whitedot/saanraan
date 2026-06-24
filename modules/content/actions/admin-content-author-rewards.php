<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/author-rewards', 'view');

$authorRewardFilters = sr_content_author_reward_filters_from_request();
$authorRewardPagination = sr_admin_pagination_from_total($pdo, sr_content_author_reward_count($pdo, $authorRewardFilters));
$authorRewardLogs = sr_content_author_reward_logs($pdo, (int) $authorRewardPagination['per_page'], sr_admin_pagination_offset($authorRewardPagination), $authorRewardFilters);

include SR_ROOT . '/modules/content/views/admin-content-author-rewards.php';
