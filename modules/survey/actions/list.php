<?php

require_once __DIR__ . '/../helpers.php';

$settings = sr_survey_settings($pdo);
$surveyListGroupKey = sr_get_string('group', 64);
$surveyListGroup = $surveyListGroupKey !== '' ? sr_survey_group_by_key($pdo, $surveyListGroupKey, true) : null;
if ($surveyListGroupKey !== '' && !is_array($surveyListGroup)) {
    sr_render_error(404, '설문 그룹을 찾을 수 없습니다.');
}
$surveyListGroupId = is_array($surveyListGroup) ? (int) ($surveyListGroup['id'] ?? 0) : 0;
$surveyListPerPage = max(1, min(100, (int) ($settings['public_list_limit'] ?? 50)));
$surveyListPageInput = sr_get_string('page', 20);
$surveyListPage = preg_match('/\A[1-9][0-9]*\z/', $surveyListPageInput) === 1 ? (int) $surveyListPageInput : 1;
$surveyListCount = sr_survey_public_form_count($pdo, $surveyListGroupId);
$surveyListTotalPages = max(1, (int) ceil($surveyListCount / $surveyListPerPage));
$surveyListPage = min(max(1, $surveyListPage), $surveyListTotalPages);
$surveyListPagination = ['page' => $surveyListPage, 'total_pages' => $surveyListTotalPages];
$surveys = sr_survey_public_forms($pdo, $surveyListPerPage, ($surveyListPage - 1) * $surveyListPerPage, $surveyListGroupId);
$surveyThemeFallbackViewFile = sr_survey_skin_view_file($settings, 'list');
include sr_survey_public_view_file($pdo, $settings, 'list');
