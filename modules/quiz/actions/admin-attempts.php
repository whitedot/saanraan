<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz/attempts', 'view');

// Reward columns in this list are aggregated from sr_quiz_reward_grants by sr_quiz_admin_attempts().
$attemptFilters = sr_quiz_admin_attempt_filters_from_request();
$attemptSortOptions = sr_quiz_admin_attempt_sort_options();
$attemptDefaultSort = sr_quiz_admin_attempt_default_sort();
$attemptSort = sr_admin_sort_from_request($attemptSortOptions, $attemptDefaultSort);
$attemptTotal = sr_quiz_admin_attempt_count($pdo, $attemptFilters);
$attemptPagination = sr_admin_pagination_from_total($pdo, $attemptTotal);
$attempts = sr_quiz_admin_attempts($pdo, $attemptFilters, (int) $attemptPagination['per_page'], sr_admin_pagination_offset($attemptPagination), $attemptSort);
$attemptDetailFilterOpen = (array) ($attemptFilters['status'] ?? []) !== [] || (array) ($attemptFilters['grant_status'] ?? []) !== [] || (string) ($attemptFilters['passed'] ?? '') !== '';

$attemptStatusOptions = [];
foreach (['submitted', 'scored', 'rewarded', 'failed'] as $status) {
    $attemptStatusOptions[$status] = sr_quiz_attempt_status_label($status);
}
$grantStatusOptions = [
    'none' => '보상 없음',
    'pending' => sr_quiz_reward_grant_status_label('pending'),
    'granted' => sr_quiz_reward_grant_status_label('granted'),
    'failed' => sr_quiz_reward_grant_status_label('failed'),
];

$adminPageTitle = '퀴즈 시도/보상 내역';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/quiz/attempts')); ?>" class="filtering-form admin-quiz-attempt-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $attemptDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields">
            <div class="filtering-field filtering-field-fill admin-quiz-attempt-filter-keyword">
                <label for="quiz_attempt_keyword_filter" class="filtering-label">검색어</label>
                <input id="quiz_attempt_keyword_filter" type="text" name="q" value="<?php echo sr_e((string) ($attemptFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="시도 ID, 회원 ID, 퀴즈 키, 제목, 출처 제목">
            </div>
        </div>
        <div id="quiz_attempt_detail_filters" class="filtering-body" data-filtering-body<?php echo $attemptDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field">
                <span class="filtering-label">시도 상태</span>
                <?php echo sr_admin_filter_toggle_group_html('quiz_attempt_status_filter', 'status', $attemptStatusOptions, (array) ($attemptFilters['status'] ?? []), '전체'); ?>
            </div>
            <div class="filtering-field">
                <span class="filtering-label">보상 상태</span>
                <?php echo sr_admin_filter_toggle_group_html('quiz_attempt_grant_status_filter', 'grant_status', $grantStatusOptions, (array) ($attemptFilters['grant_status'] ?? []), '전체'); ?>
            </div>
            <div class="filtering-field">
                <span class="filtering-label">통과</span>
                <?php echo sr_admin_filter_radio_toggle_group_html('quiz_attempt_passed_filter', 'passed', ['yes' => '예', 'no' => '아니오'], [(string) ($attemptFilters['passed'] ?? '')], '전체'); ?>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $attemptDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="quiz_attempt_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light" data-filtering-reset><?php echo sr_material_icon_html('restart_alt'); ?>초기화</button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
        </div>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">시도/보상 내역</h2>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($attemptSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($attemptSortOptions, $attemptDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="퀴즈 시도 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($attemptPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table admin-quiz-attempt-table">
            <thead class="ui-table-head">
                <tr>
                    <th<?php echo sr_admin_sort_aria('updated_at', $attemptSort); ?>><?php echo sr_admin_sort_header_html('갱신일', 'updated_at', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('quiz', $attemptSort); ?>><?php echo sr_admin_sort_header_html('퀴즈', 'quiz', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('account_id', $attemptSort); ?>><?php echo sr_admin_sort_header_html('회원 ID', 'account_id', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $attemptSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('total_score', $attemptSort); ?>><?php echo sr_admin_sort_header_html('점수', 'total_score', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th>통과</th>
                    <th<?php echo sr_admin_sort_aria('reward', $attemptSort); ?>><?php echo sr_admin_sort_header_html('보상', 'reward', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('submitted_at', $attemptSort); ?>><?php echo sr_admin_sort_header_html('제출일', 'submitted_at', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($attempts === []) { ?>
                    <tr>
                        <td colspan="8" class="admin-empty-state">조건에 맞는 퀴즈 시도 내역이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($attempts as $attempt) { ?>
                    <?php
                    $attemptStatus = (string) ($attempt['status'] ?? '');
                    $grantCount = (int) ($attempt['grant_count'] ?? 0);
                    $pendingCount = (int) ($attempt['pending_count'] ?? 0);
                    $grantedCount = (int) ($attempt['granted_count'] ?? 0);
                    $failedCount = (int) ($attempt['failed_count'] ?? 0);
                    $rewardStatusClass = 'is-blocked';
                    $rewardLabel = '보상 없음';
                    if ($failedCount > 0) {
                        $rewardStatusClass = 'is-left';
                        $rewardLabel = '실패 ' . number_format($failedCount) . '건';
                    } elseif ($pendingCount > 0) {
                        $rewardStatusClass = 'is-blocked';
                        $rewardLabel = '대기 ' . number_format($pendingCount) . '건';
                    } elseif ($grantedCount > 0) {
                        $rewardStatusClass = 'is-normal';
                        $rewardLabel = '지급 ' . number_format($grantedCount) . '건';
                    }
                    ?>
                    <tr>
                        <td class="admin-table-nowrap"><?php echo sr_quiz_time_html((string) ($attempt['updated_at'] ?? '')); ?></td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($attempt['title'] ?? '')); ?></strong><br>
                            <span class="admin-summary-meta"><code><?php echo sr_e((string) ($attempt['quiz_key'] ?? '')); ?></code> · 시도 #<?php echo sr_e((string) (int) ($attempt['id'] ?? 0)); ?></span>
                            <?php if ((string) ($attempt['source_title_snapshot'] ?? '') !== '') { ?>
                                <br><span class="admin-summary-meta"><?php echo sr_e((string) ($attempt['source_title_snapshot'] ?? '')); ?></span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($attempt['account_id'] ?? 0)); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e(sr_quiz_admin_status_class($attemptStatus)); ?>"><?php echo sr_e(sr_quiz_attempt_status_label($attemptStatus)); ?></span></td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) ($attempt['total_score'] ?? '-')); ?></td>
                        <td class="admin-table-nowrap"><?php echo ((int) ($attempt['passed'] ?? 0) === 1) ? '예' : '아니오'; ?></td>
                        <td class="admin-table-break">
                            <span class="admin-status <?php echo sr_e($rewardStatusClass); ?>"><?php echo sr_e($rewardLabel); ?></span>
                            <?php if ($grantCount > 0) { ?>
                                <br><span class="admin-summary-meta"><?php echo sr_e((string) ($attempt['reward_modules'] ?? '')); ?> <?php echo sr_e(number_format((int) ($attempt['reward_amount_total'] ?? 0))); ?></span>
                            <?php } ?>
                            <?php if ((string) ($attempt['error_message'] ?? '') !== '') { ?>
                                <br><span class="admin-summary-meta"><?php echo sr_e((string) $attempt['error_message']); ?></span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_quiz_time_html((string) ($attempt['submitted_at'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
<?php echo sr_admin_pagination_html($attemptPagination, '퀴즈 시도 목록 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
