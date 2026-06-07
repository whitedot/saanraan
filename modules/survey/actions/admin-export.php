<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys/responses', 'view');

$surveyId = max(0, (int) sr_get_string('survey_id', 20));
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
$whereSql = implode(' AND ', $where);

sr_audit_log($pdo, [
    'actor_account_id' => (int) ($account['id'] ?? 0),
    'actor_type' => 'admin',
    'event_type' => 'survey.responses.exported',
    'target_type' => $surveyId > 0 ? 'survey.form' : 'survey.responses',
    'target_id' => $surveyId > 0 ? (string) $surveyId : 'all',
    'result' => 'success',
    'message' => 'Survey responses exported.',
    'metadata' => ['quality_status' => $qualityFilter, 'limit' => 5000],
]);

sr_send_download_headers('text/csv; charset=UTF-8', 'saanraan-survey-responses-' . date('Ymd-His') . '.csv');
$output = fopen('php://output', 'wb');
if ($output === false) {
    sr_finish_response();
}
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['response_id', 'survey_id', 'survey_key', 'survey_title', 'account_id', 'status', 'quality_status', 'is_test', 'submitted_at', 'rewarded_at', 'answer_snapshot_json', 'consent_snapshot_json', 'metadata_snapshot_json']);

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
    fputcsv($output, [
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
