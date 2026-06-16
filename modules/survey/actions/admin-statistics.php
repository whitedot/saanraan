<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys/statistics', 'view');

$requestedSurveyId = max(0, (int) sr_get_string('survey_id', 20));
$surveyId = $requestedSurveyId;
$surveyAutoSelected = false;
$surveyOptions = $pdo->query('SELECT id, survey_key, title FROM sr_survey_forms WHERE deleted_at IS NULL ORDER BY updated_at DESC, id DESC LIMIT 300')->fetchAll();
$survey = null;
if ($surveyId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $surveyId]);
    $row = $stmt->fetch();
    $survey = is_array($row) ? $row : null;
}
if (!is_array($survey) && is_array($surveyOptions[0] ?? null)) {
    $surveyId = (int) $surveyOptions[0]['id'];
    $surveyAutoSelected = $requestedSurveyId < 1;
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $surveyId]);
    $row = $stmt->fetch();
    $survey = is_array($row) ? $row : null;
}

$summary = ['total_count' => 0, 'accepted_count' => 0, 'flagged_count' => 0, 'excluded_count' => 0, 'anonymous_count' => 0];
$questions = [];
$choiceStats = [];
$numberStats = [];
if (is_array($survey)) {
    $summary = sr_survey_statistics_summary($pdo, $surveyId);
    $questions = sr_survey_questions_with_choices($pdo, $surveyId);
    $choiceStats = sr_survey_statistics_choice_counts($pdo, $surveyId);
    $numberStats = sr_survey_statistics_number_stats($pdo, $surveyId);
}

$adminPageTitle = '설문 통계';
$adminPageSubtitle = '선택한 설문의 응답 현황과 문항별 결과를 확인합니다. 테스트·제외 응답은 통계에 포함하지 않습니다.';
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/surveys/statistics');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/surveys/statistics')); ?>" class="filtering-form filtering filtering-plain admin-survey-statistics-filter ui-form-theme">
    <div class="filtering-fields filtering-fields-fit admin-survey-statistics-filter-row">
        <div class="admin-survey-statistics-primary-filter">
            <label for="survey_statistics_survey_id" class="filtering-label">설문</label>
            <div class="admin-survey-statistics-filter-controls">
                <select id="survey_statistics_survey_id" name="survey_id" class="form-select form-control-full">
                    <?php foreach ($surveyOptions as $surveyOption): ?>
                        <option value="<?php echo sr_e((string) (int) $surveyOption['id']); ?>"<?php echo $surveyId === (int) $surveyOption['id'] ? ' selected' : ''; ?>><?php echo sr_e((string) $surveyOption['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </div>
</form>

<?php if (!is_array($survey)): ?>
    <section class="admin-card card"><div class="card-body admin-empty-state">통계를 볼 설문이 없습니다.</div></section>
<?php else: ?>
    <section class="admin-card card admin-survey-statistics-summary-card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?php echo sr_e((string) $survey['title']); ?></h2>
                <p class="admin-dashboard-meta"><?php echo $surveyAutoSelected ? '설문을 선택하지 않아 최근 수정 설문을 자동으로 표시합니다. ' : ''; ?>문항별 통계는 제외 응답과 테스트 응답을 계산에서 뺍니다.</p>
            </div>
        </div>
        <div class="card-body">
            <dl class="admin-dashboard-site-grid">
                <div><dt>전체 응답</dt><dd><?php echo sr_e(number_format((int) ($summary['total_count'] ?? 0))); ?>건</dd></div>
                <div><dt>포함</dt><dd><?php echo sr_e(number_format((int) ($summary['accepted_count'] ?? 0))); ?>건</dd></div>
                <div><dt>검토</dt><dd><?php echo sr_e(number_format((int) ($summary['flagged_count'] ?? 0))); ?>건</dd></div>
                <div><dt>제외</dt><dd><?php echo sr_e(number_format((int) ($summary['excluded_count'] ?? 0))); ?>건</dd></div>
                <div><dt>익명</dt><dd><?php echo sr_e(number_format((int) ($summary['anonymous_count'] ?? 0))); ?>건</dd></div>
            </dl>
        </div>
    </section>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">문항별 통계</h2>
            <div class="admin-survey-statistics-export-actions">
                <a class="btn btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys/export?' . http_build_query(['survey_id' => $surveyId, 'type' => 'analysis'], '', '&', PHP_QUERY_RFC3986))); ?>"><?php echo sr_material_icon_html('download'); ?>분석 CSV</a>
                <a class="btn btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys/export?' . http_build_query(['survey_id' => $surveyId, 'type' => 'codebook'], '', '&', PHP_QUERY_RFC3986))); ?>"><?php echo sr_material_icon_html('download'); ?>코드북 CSV</a>
            </div>
        </div>
        <div class="admin-list-summary-row">
            <p class="admin-list-summary">총 <strong><?php echo sr_e(number_format(count($questions))); ?>개</strong> 문항 · 선택형과 숫자형 문항은 표에서 요약하고, 텍스트 응답은 CSV에서 확인합니다.</p>
        </div>
        <div class="table-wrapper">
            <table class="table table-list">
                <caption class="sr-only">설문 문항별 통계 목록</caption>
                <thead class="ui-table-head">
                    <tr>
                        <th>문항</th>
                        <th>유형</th>
                        <th>결과</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($questions === []): ?>
                        <tr><td colspan="3" class="admin-empty-state">통계를 표시할 문항이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($questions as $question): ?>
                        <?php $questionKey = (string) ($question['question_key'] ?? ''); ?>
                        <tr>
                            <td class="admin-table-break">
                                <strong><?php echo sr_e((string) ($question['prompt'] ?? '')); ?></strong><br>
                                <span class="admin-summary-meta">관리용 키: <?php echo sr_e((string) ($question['question_key'] ?? '')); ?></span>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_survey_question_type_label((string) ($question['question_type'] ?? ''))); ?></td>
                            <td class="admin-table-break">
                                <?php if (in_array((string) ($question['question_type'] ?? ''), ['single_choice', 'multiple_choice'], true)): ?>
                                    <?php foreach ((array) ($question['choices'] ?? []) as $choice): ?>
                                        <?php $count = (int) ($choiceStats[$questionKey][(string) ($choice['choice_key'] ?? '')] ?? 0); ?>
                                        <div><?php echo sr_e((string) ($choice['label'] ?? '')); ?>: <?php echo sr_e(number_format($count)); ?>건</div>
                                    <?php endforeach; ?>
                                <?php elseif (isset($numberStats[$questionKey])): ?>
                                    <?php $stat = $numberStats[$questionKey]; ?>
                                    <div>응답 <?php echo sr_e(number_format((int) ($stat['answer_count'] ?? 0))); ?>건 · 평균 <?php echo sr_e(number_format((float) ($stat['average_value'] ?? 0), 2)); ?> · 최소 <?php echo sr_e((string) ($stat['min_value'] ?? '')); ?> · 최대 <?php echo sr_e((string) ($stat['max_value'] ?? '')); ?></div>
                                <?php else: ?>
                                    <span class="admin-summary-meta">텍스트 응답은 CSV 내보내기에서 확인합니다.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
