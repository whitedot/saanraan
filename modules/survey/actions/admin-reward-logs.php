<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys/reward-logs', 'view');

$surveyRewardFilters = sr_survey_reward_log_filters_from_request();
$surveyRewardPagination = sr_admin_pagination_from_total($pdo, sr_survey_reward_log_count($pdo, $surveyRewardFilters));
$surveyRewardLogs = sr_survey_reward_logs($pdo, (int) $surveyRewardPagination['per_page'], sr_admin_pagination_offset($surveyRewardPagination), $surveyRewardFilters);

include SR_ROOT . '/modules/survey/views/admin-reward-logs.php';
