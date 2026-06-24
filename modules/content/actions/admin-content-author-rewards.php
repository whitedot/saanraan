<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/author-rewards', 'view');

$authorRewardFilters = sr_content_author_reward_filters_from_request();
$authorRewardQuery = trim((string) ($authorRewardFilters['q'] ?? ''));
if ($authorRewardQuery !== ''
    && function_exists('sr_member_public_account_hash_is_valid')
    && function_exists('sr_admin_member_account_id_from_identifier')
    && sr_member_public_account_hash_is_valid(strtolower($authorRewardQuery))
) {
    $authorRewardFilters['q_account_id'] = sr_admin_member_account_id_from_identifier($pdo, sr_runtime_config(), $authorRewardQuery);
}
$authorRewardPagination = sr_admin_pagination_from_total($pdo, sr_content_author_reward_count($pdo, $authorRewardFilters));
$authorRewardLogs = sr_content_author_reward_logs($pdo, (int) $authorRewardPagination['per_page'], sr_admin_pagination_offset($authorRewardPagination), $authorRewardFilters);

include SR_ROOT . '/modules/content/views/admin-content-author-rewards.php';
