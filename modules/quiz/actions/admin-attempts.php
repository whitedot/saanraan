<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz/attempts', 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = (array) ($flashResult['errors'] ?? []);
$notice = (string) ($flashResult['notice'] ?? '');

if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz/attempts', 'edit');
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    $postErrors = [];
    $postNotice = '';
    if ($intent !== 'reclaim_reward') {
        $postErrors[] = '지원하지 않는 퀴즈 시도 작업입니다.';
    }

    $grantIdValue = sr_post_string('grant_id', 30);
    $amountValue = sr_post_string('amount', 30);
    $grantId = preg_match('/\A[1-9][0-9]*\z/', $grantIdValue) === 1 ? (int) $grantIdValue : 0;
    $amount = preg_match('/\A[1-9][0-9]*\z/', $amountValue) === 1 ? (int) $amountValue : 0;
    $reason = sr_quiz_clean_text(sr_post_string('reason', 255), 255);

    if ($postErrors === [] && $grantId < 1) {
        $postErrors[] = '회수할 보상 grant를 선택해야 합니다.';
    }
    if ($postErrors === [] && $amount < 1) {
        $postErrors[] = '회수 금액은 1 이상이어야 합니다.';
    }

    $grant = $postErrors === [] ? sr_quiz_reward_grant_by_id($pdo, $grantId) : null;
    if ($postErrors === [] && !is_array($grant)) {
        $postErrors[] = '회수할 퀴즈 보상을 찾을 수 없습니다.';
    }

    if ($postErrors === [] && is_array($grant)) {
        $assetOptions = sr_quiz_asset_options($pdo);
        $result = sr_quiz_reclaim_reward_grant($pdo, $grant, $assetOptions, $amount, (int) ($account['id'] ?? 0), $reason);
        if (empty($result['ok'])) {
            $postErrors = (array) ($result['errors'] ?? ['퀴즈 보상 회수에 실패했습니다.']);
        } else {
            $postNotice = '퀴즈 보상을 회수했습니다.';
        }
    }

    sr_admin_flash_result(sr_admin_action_result($postErrors, $postNotice));
    sr_redirect(sr_admin_post_return_url('/admin/quiz/attempts'));
}

// Reward columns in this list are aggregated from sr_quiz_reward_grants by sr_quiz_admin_attempts().
$attemptFilters = sr_quiz_admin_attempt_filters_from_request();
$attemptSortOptions = sr_quiz_admin_attempt_sort_options();
$attemptDefaultSort = sr_quiz_admin_attempt_default_sort();
$attemptSort = sr_admin_sort_from_request($attemptSortOptions, $attemptDefaultSort);
$attemptTotal = sr_quiz_admin_attempt_count($pdo, $attemptFilters);
$attemptPagination = sr_admin_pagination_from_total($pdo, $attemptTotal);
$attempts = sr_quiz_admin_attempts($pdo, $attemptFilters, (int) $attemptPagination['per_page'], sr_admin_pagination_offset($attemptPagination), $attemptSort);
$attemptIds = [];
foreach ($attempts as $attempt) {
    $attemptIds[] = (int) ($attempt['id'] ?? 0);
}
$attemptAssetOptions = sr_quiz_asset_options($pdo);
$attemptRewardGrants = sr_quiz_admin_reward_grants_for_attempts($pdo, $attemptIds, $attemptAssetOptions);
$attemptCanEdit = sr_admin_has_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz/attempts', 'edit');
$attemptReturnTo = sr_admin_current_get_url('/admin/quiz/attempts');
$attemptReclaimModals = [];
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
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/quiz/attempts');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

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
                    <th>결과</th>
                    <th<?php echo sr_admin_sort_aria('reward', $attemptSort); ?>><?php echo sr_admin_sort_header_html('보상', 'reward', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('submitted_at', $attemptSort); ?>><?php echo sr_admin_sort_header_html('제출일', 'submitted_at', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($attempts === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state">조건에 맞는 퀴즈 시도 내역이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($attempts as $attempt) { ?>
                    <?php
                    $attemptId = (int) ($attempt['id'] ?? 0);
                    $attemptStatus = (string) ($attempt['status'] ?? '');
                    $grantCount = (int) ($attempt['grant_count'] ?? 0);
                    $pendingCount = (int) ($attempt['pending_count'] ?? 0);
                    $grantedCount = (int) ($attempt['granted_count'] ?? 0);
                    $failedCount = (int) ($attempt['failed_count'] ?? 0);
                    $attemptGrants = (array) ($attemptRewardGrants[$attemptId] ?? []);
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
                            <span class="admin-summary-meta">관리용 키: <?php echo sr_e((string) ($attempt['quiz_key'] ?? '')); ?> · 시도 #<?php echo sr_e((string) (int) ($attempt['id'] ?? 0)); ?></span>
                            <?php if ((string) ($attempt['source_title_snapshot'] ?? '') !== '') { ?>
                                <br><span class="admin-summary-meta"><?php echo sr_e((string) ($attempt['source_title_snapshot'] ?? '')); ?></span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($attempt['account_id'] ?? 0)); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e(sr_quiz_admin_status_class($attemptStatus)); ?>"><?php echo sr_e(sr_quiz_attempt_status_label($attemptStatus)); ?></span></td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) ($attempt['total_score'] ?? '-')); ?></td>
                        <td class="admin-table-nowrap"><?php echo ((int) ($attempt['passed'] ?? 0) === 1) ? '예' : '아니오'; ?></td>
                        <td class="admin-table-break">
                            <?php if ((string) ($attempt['result_title'] ?? '') !== '') { ?>
                                <strong><?php echo sr_e((string) ($attempt['result_title'] ?? '')); ?></strong>
                                <?php if ((string) ($attempt['result_key'] ?? '') !== '') { ?>
                                    <br><span class="admin-summary-meta">결과 관리용 키: <?php echo sr_e((string) ($attempt['result_key'] ?? '')); ?></span>
                                <?php } ?>
                                <?php if ((string) ($attempt['result_summary'] ?? '') !== '') { ?>
                                    <br><span class="admin-summary-meta"><?php echo sr_e((string) ($attempt['result_summary'] ?? '')); ?></span>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="admin-summary-meta">결과 없음</span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-break">
                            <span class="admin-status <?php echo sr_e($rewardStatusClass); ?>"><?php echo sr_e($rewardLabel); ?></span>
                            <?php if ($grantCount > 0) { ?>
                                <br><span class="admin-summary-meta"><?php echo sr_e((string) ($attempt['reward_modules'] ?? '')); ?> <?php echo sr_e(number_format((int) ($attempt['reward_amount_total'] ?? 0))); ?></span>
                            <?php } ?>
                            <?php if ((string) ($attempt['error_message'] ?? '') !== '') { ?>
                                <br><span class="admin-summary-meta"><?php echo sr_e((string) $attempt['error_message']); ?></span>
                            <?php } ?>
                            <?php if ($attemptGrants !== []) { ?>
                                <div class="admin-quiz-reward-grants">
                                    <?php foreach ($attemptGrants as $grant) { ?>
                                        <?php
                                        $grantId = (int) ($grant['id'] ?? 0);
                                        $grantStatus = (string) ($grant['status'] ?? '');
                                        $grantReclaimStatus = is_array($grant['reclaim_status'] ?? null) ? $grant['reclaim_status'] : [];
                                        $grantRemainingAmount = (int) ($grantReclaimStatus['remaining_amount'] ?? 0);
                                        $grantCanReclaim = $attemptCanEdit && !empty($grantReclaimStatus['available']) && $grantRemainingAmount > 0;
                                        $grantModalId = 'quiz-reward-reclaim-modal-' . $grantId;
                                        if ($grantCanReclaim) {
                                            $attemptReclaimModals[] = [
                                                'id' => $grantModalId,
                                                'field_prefix' => 'quiz_reward_reclaim_' . $grantId,
                                                'grant' => $grant,
                                                'remaining_amount' => $grantRemainingAmount,
                                            ];
                                        }
                                        ?>
                                        <div class="admin-summary-meta admin-quiz-reward-grant">
                                            <span class="admin-status <?php echo sr_e(sr_quiz_admin_status_class($grantStatus)); ?>"><?php echo sr_e(sr_quiz_reward_grant_status_label($grantStatus)); ?></span>
                                            <span><?php echo sr_e(sr_quiz_reward_provider_label((string) ($grant['reward_provider'] ?? ''))); ?> · <?php echo sr_e((string) ($grant['reward_module'] ?? '')); ?> <?php echo sr_e(number_format((int) ($grant['reward_amount'] ?? 0))); ?></span>
                                            <?php if ($grantRemainingAmount > 0) { ?>
                                                <span>회수 가능 <?php echo sr_e(number_format($grantRemainingAmount)); ?></span>
                                            <?php } ?>
                                            <?php if ($grantCanReclaim) { ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($grantModalId); ?>" data-overlay="#<?php echo sr_e($grantModalId); ?>">회수</button>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_quiz_time_html((string) ($attempt['submitted_at'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php foreach ($attemptReclaimModals as $reclaimModal) { ?>
    <?php
    $reclaimGrant = is_array($reclaimModal['grant'] ?? null) ? $reclaimModal['grant'] : [];
    $reclaimGrantId = (int) ($reclaimGrant['id'] ?? 0);
    $reclaimModalId = (string) ($reclaimModal['id'] ?? '');
    $reclaimFieldPrefix = (string) ($reclaimModal['field_prefix'] ?? 'quiz_reward_reclaim');
    $reclaimRemainingAmount = (int) ($reclaimModal['remaining_amount'] ?? 0);
    if ($reclaimGrantId < 1 || $reclaimModalId === '' || $reclaimRemainingAmount < 1) {
        continue;
    }
    ?>
    <div id="<?php echo sr_e($reclaimModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($reclaimFieldPrefix); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/quiz/attempts')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($reclaimFieldPrefix); ?>_title" class="modal-title">퀴즈 보상 회수</h3>
                    <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($reclaimModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="reclaim_reward">
                    <input type="hidden" name="grant_id" value="<?php echo sr_e((string) $reclaimGrantId); ?>">
                    <input type="hidden" name="return_to" value="<?php echo sr_e($attemptReturnTo); ?>">
                    <div class="admin-summary-stats">
                        <span class="admin-summary-meta">Grant #<?php echo sr_e((string) $reclaimGrantId); ?></span>
                        <span class="admin-summary-meta">회원 ID <?php echo sr_e((string) (int) ($reclaimGrant['account_id'] ?? 0)); ?></span>
                        <span class="admin-summary-meta">지급 <?php echo sr_e(number_format((int) ($reclaimGrant['reward_amount'] ?? 0))); ?></span>
                        <span class="admin-summary-meta">회수 가능 <strong><?php echo sr_e(number_format($reclaimRemainingAmount)); ?></strong></span>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($reclaimFieldPrefix); ?>_amount">회수 금액 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($reclaimFieldPrefix); ?>_amount" type="number" name="amount" value="<?php echo sr_e((string) $reclaimRemainingAmount); ?>" step="1" min="1" max="<?php echo sr_e((string) $reclaimRemainingAmount); ?>" required class="form-input" data-overlay-focus>
                            <p class="admin-form-help">남은 회수 가능액을 초과하면 서버에서 거부됩니다.</p>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($reclaimFieldPrefix); ?>_reason">사유</label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($reclaimFieldPrefix); ?>_reason" type="text" name="reason" value="퀴즈 보상 회수: grant #<?php echo sr_e((string) $reclaimGrantId); ?>" maxlength="255" class="form-input form-control-full">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($reclaimModalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-outline-danger modal-action">회수</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<?php echo sr_admin_pagination_html($attemptPagination, '퀴즈 시도 목록 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
