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

$where = ['s.deleted_at IS NULL'];
$params = [];
if ($surveyId > 0) {
    $where[] = 'r.survey_id = :survey_id';
    $params['survey_id'] = $surveyId;
}
if ($qualityFilter !== '') {
    $where[] = 'r.quality_status = :quality_status';
    $params['quality_status'] = $qualityFilter;
}
if (!$includeTest) {
    $where[] = 'r.is_test = 0';
}
$whereSql = implode(' AND ', $where);

sr_audit_log($pdo, [
    'actor_account_id' => (int) ($account['id'] ?? 0),
    'actor_type' => 'admin',
    'event_type' => 'survey.responses.exported',
    'target_type' => $surveyId > 0 ? 'survey.form' : 'survey.responses',
    'target_id' => $surveyId > 0 ? (string) $surveyId : 'all',
    'result' => 'success',
    'message' => 'Survey responses exported.',
    'metadata' => ['type' => $exportType, 'quality_status' => $qualityFilter, 'include_test' => $includeTest, 'limit' => 5000],
]);

function sr_survey_csv_cell(mixed $value): string
{
    $value = (string) $value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }

    return $value;
}

function sr_survey_csv_row($output, array $row): void
{
    fputcsv($output, array_map('sr_survey_csv_cell', $row));
}

sr_send_download_headers('text/csv; charset=UTF-8', 'saanraan-survey-' . $exportType . '-' . date('Ymd-His') . '.csv');
$output = fopen('php://output', 'wb');
if ($output === false) {
    sr_finish_response();
}
fwrite($output, "\xEF\xBB\xBF");

if ($exportType === 'codebook') {
    sr_survey_csv_row($output, ['survey_id', 'survey_key', 'survey_title', 'questionnaire_version', 'question_key', 'question_type', 'prompt', 'required', 'analysis_note', 'choice_key', 'choice_label', 'is_other', 'is_nonresponse', 'number_min', 'number_max', 'scale_points', 'nonresponse_policy']);
    $codebookWhere = ['s.deleted_at IS NULL'];
    $codebookParams = [];
    if ($surveyId > 0) {
        $codebookWhere[] = 's.id = :survey_id';
        $codebookParams['survey_id'] = $surveyId;
    }
    $stmt = $pdo->prepare(
        'SELECT s.id AS survey_id, s.survey_key, s.title, s.questionnaire_version,
                q.question_key, q.question_type, q.prompt, q.required, q.analysis_note, q.number_min, q.number_max, q.scale_points, q.nonresponse_policy,
                c.choice_key, c.label AS choice_label, c.is_other, c.is_nonresponse
         FROM sr_survey_forms s
         INNER JOIN sr_survey_questions q ON q.survey_id = s.id
         LEFT JOIN sr_survey_choices c ON c.question_id = q.id
         WHERE ' . implode(' AND ', $codebookWhere) . '
         ORDER BY s.id ASC, q.sort_order ASC, q.id ASC, c.sort_order ASC, c.id ASC
         LIMIT 10000'
    );
    $stmt->execute($codebookParams);
    while (($row = $stmt->fetch()) !== false) {
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
    $stmt = $pdo->prepare(
        'SELECT r.id, r.survey_id, s.survey_key, r.quality_status, r.submitted_at,
                a.question_key, a.choice_key, a.answer_text, a.answer_number, a.other_text
         FROM sr_survey_responses r
         INNER JOIN sr_survey_forms s ON s.id = r.survey_id
         INNER JOIN sr_survey_response_answers a ON a.response_id = r.id
         WHERE ' . $whereSql . '
           AND r.quality_status <> \'excluded\'
         ORDER BY r.submitted_at DESC, r.id DESC, a.id ASC
         LIMIT 20000'
    );
    $stmt->execute($params);
    while (($row = $stmt->fetch()) !== false) {
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

$stmt = $pdo->prepare(
    'SELECT r.id, r.survey_id, s.survey_key, s.title, r.account_id, r.status, r.quality_status, r.is_test, r.submitted_at, r.rewarded_at,
            r.answer_snapshot_json, r.consent_snapshot_json, r.metadata_snapshot_json
     FROM sr_survey_responses r
     INNER JOIN sr_survey_forms s ON s.id = r.survey_id
     WHERE ' . $whereSql . '
     ORDER BY r.submitted_at DESC, r.id DESC
     LIMIT 5000'
);
$stmt->execute($params);
while (($row = $stmt->fetch()) !== false) {
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
