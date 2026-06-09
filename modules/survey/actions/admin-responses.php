<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys/responses', 'view');

if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys/responses', 'edit');
    sr_require_csrf();
    $responseId = (int) sr_post_string('response_id', 20);
    $qualityStatus = sr_survey_clean_key(sr_post_string('quality_status', 30), 30);
    $qualityNote = sr_survey_clean_text(sr_post_string('quality_note', 1000), 1000);
    $errors = [];
    if ($responseId < 1) {
        $errors[] = '응답을 선택하세요.';
    }
    if (!in_array($qualityStatus, sr_survey_quality_statuses(), true)) {
        $errors[] = '품질 상태 값이 올바르지 않습니다.';
    }
    if ($errors === []) {
        $stmt = $pdo->prepare('UPDATE sr_survey_responses SET quality_status = :quality_status, quality_note = :quality_note, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'quality_status' => $qualityStatus,
            'quality_note' => $qualityNote,
            'updated_at' => sr_now(),
            'id' => $responseId,
        ]);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) ($account['id'] ?? 0),
            'actor_type' => 'admin',
            'event_type' => 'survey.response.quality_updated',
            'target_type' => 'survey.response',
            'target_id' => (string) $responseId,
            'result' => 'success',
            'message' => 'Survey response quality status updated.',
            'metadata' => ['quality_status' => $qualityStatus],
        ]);
    }
    $redirectQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $errors === [] ? '응답 품질 상태를 저장했습니다.' : ''), '/admin/surveys/responses' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
}

$surveyId = max(0, (int) sr_get_string('survey_id', 20));
$qualityFilter = sr_survey_clean_key(sr_get_string('quality_status', 30), 30);
if ($qualityFilter !== '' && !in_array($qualityFilter, sr_survey_quality_statuses(), true)) {
    $qualityFilter = '';
}
$keyword = sr_survey_clean_single_line(sr_get_string('q', 120), 120);

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
if ($keyword !== '') {
    $where[] = '(s.survey_key LIKE :keyword OR s.title LIKE :keyword OR CAST(r.id AS CHAR) = :keyword_exact OR CAST(r.account_id AS CHAR) = :keyword_exact)';
    $params['keyword'] = '%' . $keyword . '%';
    $params['keyword_exact'] = $keyword;
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM sr_survey_responses r
     INNER JOIN sr_survey_forms s ON s.id = r.survey_id
     WHERE ' . $whereSql
);
$countStmt->execute($params);
$pagination = sr_admin_pagination_from_total($pdo, (int) $countStmt->fetchColumn());

$stmt = $pdo->prepare(
    'SELECT r.*, s.survey_key, s.title
     FROM sr_survey_responses r
     INNER JOIN sr_survey_forms s ON s.id = r.survey_id
     WHERE ' . $whereSql . '
     ORDER BY r.submitted_at DESC, r.id DESC
     LIMIT :limit OFFSET :offset'
);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', (int) $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', sr_admin_pagination_offset($pagination), PDO::PARAM_INT);
$stmt->execute();
$responses = $stmt->fetchAll();

$surveyStmt = $pdo->query('SELECT id, survey_key, title FROM sr_survey_forms WHERE deleted_at IS NULL ORDER BY updated_at DESC, id DESC LIMIT 300');
$surveyOptions = $surveyStmt->fetchAll();
$responseDetailFilterOpen = $surveyId > 0 || $qualityFilter !== '';
$qualityFilterOptions = [];
foreach (sr_survey_quality_statuses() as $status) {
    $qualityFilterOptions[$status] = sr_survey_quality_status_label($status);
}
$responseActionQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
$responseActionSuffix = $responseActionQuery !== '' ? '?' . $responseActionQuery : '';

function sr_survey_admin_preview_text(string $value, int $maxLength = 240): string
{
    if (strlen($value) <= $maxLength) {
        return $value;
    }

    return substr($value, 0, $maxLength) . '...';
}

$flashResult = sr_admin_pop_flash_result();
$errors = (array) ($flashResult['errors'] ?? []);
$notice = (string) ($flashResult['notice'] ?? '');
$adminPageTitle = '설문 응답 관리';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/surveys/responses')); ?>" class="filtering-form admin-survey-response-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $responseDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields">
            <div class="filtering-field filtering-field-fill admin-survey-response-filter-keyword">
                <label for="survey_response_keyword" class="filtering-label">검색어</label>
                <input id="survey_response_keyword" type="text" name="q" value="<?php echo sr_e($keyword); ?>" class="form-input filtering-input" maxlength="120" placeholder="응답 ID, 회원 ID, 설문 key, 제목">
            </div>
        </div>
        <div id="survey_response_detail_filters" class="filtering-body" data-filtering-body<?php echo $responseDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field">
                <label for="survey_response_survey_id" class="filtering-label">설문</label>
                <select id="survey_response_survey_id" name="survey_id" class="form-select form-control-full">
                    <option value="">전체</option>
                    <?php foreach ($surveyOptions as $surveyOption): ?>
                        <option value="<?php echo sr_e((string) (int) $surveyOption['id']); ?>"<?php echo $surveyId === (int) $surveyOption['id'] ? ' selected' : ''; ?>><?php echo sr_e((string) $surveyOption['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtering-field">
                <span class="filtering-label">품질 상태</span>
                <?php echo sr_admin_filter_radio_toggle_group_html('survey_response_quality_status_filter', 'quality_status', $qualityFilterOptions, [$qualityFilter], '전체'); ?>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $responseDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="survey_response_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light" data-filtering-reset><?php echo sr_material_icon_html('restart_alt'); ?>초기화</button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            <a class="btn btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys/export?' . http_build_query(['survey_id' => $surveyId, 'quality_status' => $qualityFilter, 'type' => 'raw'], '', '&', PHP_QUERY_RFC3986))); ?>">원본 CSV</a>
            <a class="btn btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys/export?' . http_build_query(['survey_id' => $surveyId, 'quality_status' => $qualityFilter, 'type' => 'analysis'], '', '&', PHP_QUERY_RFC3986))); ?>">분석 CSV</a>
        </div>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">응답 목록</h2>
    </div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($pagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>제출일</th>
                    <th>설문</th>
                    <th>회원</th>
                    <th>품질</th>
                    <th>응답 스냅샷</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($responses === []): ?>
                    <tr><td colspan="6" class="admin-empty-state">조건에 맞는 응답이 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($responses as $response): ?>
                    <tr>
                        <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($response['submitted_at'] ?? '')); ?></td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($response['title'] ?? '')); ?></strong><br>
                            <span class="admin-summary-meta"><code><?php echo sr_e((string) ($response['survey_key'] ?? '')); ?></code> · 응답 #<?php echo sr_e((string) (int) ($response['id'] ?? 0)); ?></span>
                        </td>
                        <td class="admin-table-nowrap"><?php echo (int) ($response['account_id'] ?? 0) > 0 ? sr_e((string) (int) $response['account_id']) : '익명'; ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e(sr_survey_admin_status_class((string) ($response['quality_status'] ?? 'accepted'))); ?>"><?php echo sr_e(sr_survey_quality_status_label((string) ($response['quality_status'] ?? 'accepted'))); ?></span></td>
                        <td class="admin-table-break"><code><?php echo sr_e(sr_survey_admin_preview_text((string) ($response['answer_snapshot_json'] ?? ''))); ?></code></td>
                        <td>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/surveys/responses' . $responseActionSuffix)); ?>" class="admin-inline-form">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="response_id" value="<?php echo sr_e((string) (int) ($response['id'] ?? 0)); ?>">
                                <label class="sr-only" for="quality_note_<?php echo sr_e((string) (int) ($response['id'] ?? 0)); ?>">품질 메모</label>
                                <input id="quality_note_<?php echo sr_e((string) (int) ($response['id'] ?? 0)); ?>" type="text" name="quality_note" value="<?php echo sr_e((string) ($response['quality_note'] ?? '')); ?>" class="form-input" maxlength="1000" placeholder="메모">
                                <?php foreach (sr_survey_quality_statuses() as $status): ?>
                                    <?php if ((string) ($response['quality_status'] ?? '') === $status): ?>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <?php $statusLabel = sr_survey_quality_status_label($status); ?>
                                    <button type="submit" name="quality_status" value="<?php echo sr_e($status); ?>" class="btn btn-sm <?php echo sr_e(sr_admin_row_action_button_class($status)); ?>"<?php echo sr_admin_row_action_confirm_attr($status, $statusLabel); ?>><?php echo sr_e($statusLabel); ?></button>
                                <?php endforeach; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php echo sr_admin_pagination_html($pagination, '설문 응답 목록 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
