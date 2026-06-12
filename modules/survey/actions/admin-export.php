<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys/responses', 'view');

$surveyId = max(0, (int) sr_get_string('survey_id', 20));
$exportType = sr_survey_clean_key(sr_get_string('type', 30), 30);
if (!in_array($exportType, ['raw', 'analysis', 'codebook'], true)) {
    $exportType = 'raw';
}
$includeTest = sr_get_string('include_test', 5) === '1';
$qualityFilter = sr_survey_clean_key(sr_get_string('quality_status', 30), 30);
if ($qualityFilter !== '' && !in_array($qualityFilter, sr_survey_quality_statuses(), true)) {
    $qualityFilter = '';
}

$exportLimits = sr_survey_admin_export_limits();
$exportLimit = $exportLimits[$exportType];

sr_audit_log($pdo, [
    'actor_account_id' => (int) ($account['id'] ?? 0),
    'actor_type' => 'admin',
    'event_type' => 'survey.responses.exported',
    'target_type' => $surveyId > 0 ? 'survey.form' : 'survey.responses',
    'target_id' => $surveyId > 0 ? (string) $surveyId : 'all',
    'result' => 'success',
    'message' => 'Survey responses exported.',
    'metadata' => ['type' => $exportType, 'quality_status' => $qualityFilter, 'include_test' => $includeTest, 'limit' => $exportLimit],
]);

sr_send_download_headers('text/csv; charset=UTF-8', 'saanraan-survey-' . $exportType . '-' . date('Ymd-His') . '.csv');
$output = fopen('php://output', 'wb');
if ($output === false) {
    sr_finish_response();
}
fwrite($output, "\xEF\xBB\xBF");

if ($exportType === 'codebook') {
    sr_survey_csv_row($output, ['survey_id', 'survey_key', 'survey_title', 'questionnaire_version', 'question_key', 'question_type', 'prompt', 'required', 'analysis_note', 'choice_key', 'choice_label', 'is_other', 'is_nonresponse', 'number_min', 'number_max', 'scale_points', 'nonresponse_policy']);
    foreach (sr_survey_admin_export_codebook_rows($pdo, $surveyId, $exportLimits['codebook']) as $row) {
        sr_survey_csv_row($output, [
            (int) ($row['survey_id'] ?? 0),
            (string) ($row['survey_key'] ?? ''),
            (string) ($row['title'] ?? ''),
            (int) ($row['questionnaire_version'] ?? 1),
            (string) ($row['question_key'] ?? ''),
            (string) ($row['question_type'] ?? ''),
            (string) ($row['prompt'] ?? ''),
            (int) ($row['required'] ?? 0),
            (string) ($row['analysis_note'] ?? ''),
            (string) ($row['choice_key'] ?? ''),
            (string) ($row['choice_label'] ?? ''),
            (int) ($row['is_other'] ?? 0),
            (int) ($row['is_nonresponse'] ?? 0),
            (string) ($row['number_min'] ?? ''),
            (string) ($row['number_max'] ?? ''),
            (string) ($row['scale_points'] ?? ''),
            (string) ($row['nonresponse_policy'] ?? ''),
        ]);
    }
    fclose($output);
    sr_finish_response();
}

if ($exportType === 'analysis') {
    sr_survey_csv_row($output, ['response_id', 'survey_id', 'survey_key', 'quality_status', 'submitted_at', 'question_key', 'choice_key', 'answer_text', 'answer_number', 'other_text']);
    foreach (sr_survey_admin_export_analysis_rows($pdo, $surveyId, $qualityFilter, $includeTest, $exportLimits['analysis']) as $row) {
        sr_survey_csv_row($output, [
            (int) ($row['id'] ?? 0),
            (int) ($row['survey_id'] ?? 0),
            (string) ($row['survey_key'] ?? ''),
            (string) ($row['quality_status'] ?? ''),
            (string) ($row['submitted_at'] ?? ''),
            (string) ($row['question_key'] ?? ''),
            (string) ($row['choice_key'] ?? ''),
            (string) ($row['answer_text'] ?? ''),
            (string) ($row['answer_number'] ?? ''),
            (string) ($row['other_text'] ?? ''),
        ]);
    }
    fclose($output);
    sr_finish_response();
}

sr_survey_csv_row($output, ['response_id', 'survey_id', 'survey_key', 'survey_title', 'account_id', 'status', 'quality_status', 'is_test', 'submitted_at', 'rewarded_at', 'answer_snapshot_json', 'consent_snapshot_json', 'metadata_snapshot_json']);

foreach (sr_survey_admin_export_raw_rows($pdo, $surveyId, $qualityFilter, $includeTest, $exportLimits['raw']) as $row) {
    sr_survey_csv_row($output, [
        (int) ($row['id'] ?? 0),
        (int) ($row['survey_id'] ?? 0),
        (string) ($row['survey_key'] ?? ''),
        (string) ($row['title'] ?? ''),
        $row['account_id'] === null ? '' : (int) $row['account_id'],
        (string) ($row['status'] ?? ''),
        (string) ($row['quality_status'] ?? ''),
        (int) ($row['is_test'] ?? 0),
        (string) ($row['submitted_at'] ?? ''),
        (string) ($row['rewarded_at'] ?? ''),
        (string) ($row['answer_snapshot_json'] ?? ''),
        (string) ($row['consent_snapshot_json'] ?? ''),
        (string) ($row['metadata_snapshot_json'] ?? ''),
    ]);
}
fclose($output);
sr_finish_response();
