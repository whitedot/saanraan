<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys/reward-logs', 'view');

$surveyRewardFilters = sr_survey_reward_log_filters_from_request();
$surveyRewardQuery = trim((string) ($surveyRewardFilters['q'] ?? ''));
if ($surveyRewardQuery !== ''
    && function_exists('sr_member_public_account_hash_is_valid')
    && function_exists('sr_admin_member_account_id_from_identifier')
    && sr_member_public_account_hash_is_valid(strtolower($surveyRewardQuery))
) {
    $surveyRewardFilters['q_account_id'] = sr_admin_member_account_id_from_identifier($pdo, sr_runtime_config(), $surveyRewardQuery);
}
$surveyRewardPagination = sr_admin_pagination_from_total($pdo, sr_survey_reward_log_count($pdo, $surveyRewardFilters));
$surveyRewardLogs = sr_survey_reward_logs($pdo, (int) $surveyRewardPagination['per_page'], sr_admin_pagination_offset($surveyRewardPagination), $surveyRewardFilters);
$surveyRewardSurveyOptionsStmt = $pdo->query('SELECT id, survey_key, title FROM sr_survey_forms WHERE deleted_at IS NULL ORDER BY updated_at DESC, id DESC LIMIT 300');
$surveyRewardSurveyOptions = $surveyRewardSurveyOptionsStmt ? $surveyRewardSurveyOptionsStmt->fetchAll() : [];
$surveyRewardDetailFilterOpen = (int) ($surveyRewardFilters['survey_id'] ?? 0) > 0
    || (string) ($surveyRewardFilters['status'] ?? '') !== ''
    || (string) ($surveyRewardFilters['provider'] ?? '') !== '';
$surveyRewardStatusOptions = [];
foreach (sr_survey_reward_log_statuses() as $status) {
    $surveyRewardStatusOptions[$status] = sr_survey_reward_log_status_label($status);
}
$surveyRewardProviderOptions = [];
foreach (sr_survey_reward_providers() as $provider) {
    $surveyRewardProviderOptions[$provider] = sr_survey_reward_provider_label($provider);
}

include SR_ROOT . '/modules/survey/views/admin-reward-logs.php';
