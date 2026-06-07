<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys/statistics', 'view');

$surveyId = max(0, (int) sr_get_string('survey_id', 20));
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
    $summaryStmt = $pdo->prepare(
        "SELECT COUNT(*) AS total_count,
                SUM(CASE WHEN quality_status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count,
                SUM(CASE WHEN quality_status = 'flagged' THEN 1 ELSE 0 END) AS flagged_count,
                SUM(CASE WHEN quality_status = 'excluded' THEN 1 ELSE 0 END) AS excluded_count,
                SUM(CASE WHEN account_id IS NULL THEN 1 ELSE 0 END) AS anonymous_count
         FROM sr_survey_responses
         WHERE survey_id = :survey_id
           AND is_test = 0"
    );
    $summaryStmt->execute(['survey_id' => $surveyId]);
    $summaryRow = $summaryStmt->fetch();
    if (is_array($summaryRow)) {
        $summary = array_merge($summary, $summaryRow);
    }
    $questions = sr_survey_questions_with_choices($pdo, $surveyId);
    $choiceStmt = $pdo->prepare(
        "SELECT a.question_key, a.choice_key, COUNT(*) AS answer_count
         FROM sr_survey_response_answers a
         INNER JOIN sr_survey_responses r ON r.id = a.response_id
         WHERE r.survey_id = :survey_id
           AND r.quality_status <> 'excluded'
           AND r.is_test = 0
           AND a.choice_key IS NOT NULL
           AND a.choice_key <> ''
         GROUP BY a.question_key, a.choice_key"
    );
    $choiceStmt->execute(['survey_id' => $surveyId]);
    foreach ($choiceStmt->fetchAll() as $row) {
        $questionKey = (string) ($row['question_key'] ?? '');
        foreach (array_filter(array_map('trim', explode(',', (string) ($row['choice_key'] ?? '')))) as $choiceKey) {
            $choiceStats[$questionKey][$choiceKey] = (int) ($choiceStats[$questionKey][$choiceKey] ?? 0) + (int) ($row['answer_count'] ?? 0);
        }
    }
    $numberStmt = $pdo->prepare(
        "SELECT a.question_key, COUNT(a.answer_number) AS answer_count, AVG(a.answer_number) AS average_value, MIN(a.answer_number) AS min_value, MAX(a.answer_number) AS max_value
         FROM sr_survey_response_answers a
         INNER JOIN sr_survey_responses r ON r.id = a.response_id
         WHERE r.survey_id = :survey_id
           AND r.quality_status <> 'excluded'
           AND r.is_test = 0
           AND a.answer_number IS NOT NULL
         GROUP BY a.question_key"
    );
    $numberStmt->execute(['survey_id' => $surveyId]);
    foreach ($numberStmt->fetchAll() as $row) {
        $numberStats[(string) ($row['question_key'] ?? '')] = $row;
    }
}

$adminPageTitle = '설문 통계';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/surveys/statistics')); ?>" class="filtering-form ui-form-theme">
    <div class="filtering filtering-card">
        <div class="filtering-fields">
            <div class="filtering-field filtering-field-fill">
                <label for="survey_statistics_survey_id" class="filtering-label">설문</label>
                <select id="survey_statistics_survey_id" name="survey_id" class="form-select">
                    <?php foreach ($surveyOptions as $surveyOption): ?>
                        <option value="<?php echo sr_e((string) (int) $surveyOption['id']); ?>"<?php echo $surveyId === (int) $surveyOption['id'] ? ' selected' : ''; ?>><?php echo sr_e((string) $surveyOption['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="submit" class="btn btn-solid-primary filtering-submit">보기</button>
            <?php if (is_array($survey)): ?>
                <a class="btn btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys/export?' . http_build_query(['survey_id' => $surveyId, 'type' => 'analysis'], '', '&', PHP_QUERY_RFC3986))); ?>">분석 CSV</a>
                <a class="btn btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys/export?' . http_build_query(['survey_id' => $surveyId, 'type' => 'codebook'], '', '&', PHP_QUERY_RFC3986))); ?>">코드북 CSV</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if (!is_array($survey)): ?>
    <section class="admin-card card"><div class="card-body admin-empty-state">통계를 볼 설문이 없습니다.</div></section>
<?php else: ?>
    <section class="admin-card card">
        <div class="card-header"><h2 class="card-title"><?php echo sr_e((string) $survey['title']); ?></h2></div>
        <div class="card-body">
            <dl class="admin-module-detail-list">
                <dt>전체 응답</dt><dd><?php echo sr_e(number_format((int) ($summary['total_count'] ?? 0))); ?>건</dd>
                <dt>포함</dt><dd><?php echo sr_e(number_format((int) ($summary['accepted_count'] ?? 0))); ?>건</dd>
                <dt>검토</dt><dd><?php echo sr_e(number_format((int) ($summary['flagged_count'] ?? 0))); ?>건</dd>
                <dt>제외</dt><dd><?php echo sr_e(number_format((int) ($summary['excluded_count'] ?? 0))); ?>건</dd>
                <dt>익명</dt><dd><?php echo sr_e(number_format((int) ($summary['anonymous_count'] ?? 0))); ?>건</dd>
            </dl>
        </div>
    </section>
    <section class="admin-card admin-list-card card">
        <div class="card-header"><h2 class="card-title">문항별 통계</h2></div>
        <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>문항</th>
                        <th>유형</th>
                        <th>결과</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $question): ?>
                        <?php $questionKey = (string) ($question['question_key'] ?? ''); ?>
                        <tr>
                            <td class="admin-table-break">
                                <strong><?php echo sr_e((string) ($question['prompt'] ?? '')); ?></strong><br>
                                <span class="admin-summary-meta"><code><?php echo sr_e((string) ($question['question_key'] ?? '')); ?></code></span>
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
