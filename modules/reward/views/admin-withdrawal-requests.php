<?php

$statusFilter = isset($statusFilter) && is_array($statusFilter) ? $statusFilter : [];
$requestStatusOptions = [
    'pending' => '대기',
    'completed' => '완료',
    'rejected' => '거부',
    'canceled' => '취소',
];
$requestStatusValues = array_keys($requestStatusOptions);
$requestStatusCount = count($requestStatusValues);
$searchField = isset($searchField) ? (string) $searchField : 'all';
$searchKeyword = isset($searchKeyword) ? (string) $searchKeyword : '';
$requestBatchPendingCount = isset($requestBatchPendingCount) ? max(0, (int) $requestBatchPendingCount) : 0;
$requestBatchLimit = isset($requestBatchLimit) ? max(1, (int) $requestBatchLimit) : 100;
$searchFieldOptions = [
    'all' => '전체',
    'member' => '회원',
    'bank' => '계좌',
    'note' => '메모',
    'request' => '신청/거래 번호',
];
$requestListActionUrl = sr_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin/rewards/withdrawal-requests'));
$rewardWithdrawalHelp = [
    'id' => 'reward-withdrawal-processing-help',
    'title' => '적립금 출금 처리 도움말',
    'body' => '<p>‘완료’는 은행 이체를 실행하는 기능이 아닙니다. 운영자가 실제 출금 이체를 마친 뒤 이체 확인 번호나 처리 근거를 입력하고 완료하세요. 완료하면 신청 금액만큼 회원의 적립금 잔액을 차감하고 복원할 수 없는 거래 기록을 만듭니다.</p>'
        . '<p>‘거부’는 적립금을 차감하지 않고 신청만 거부 상태로 바꿉니다. 완료 처리 근거와 거부 사유는 회원의 출금 신청 내역에도 표시되므로 개인정보나 내부 전용 메모를 입력하지 마세요.</p>'
        . '<p>일괄처리는 실행 시점의 필터·검색 조건에 맞는 대기 신청을 대상으로 합니다. 화면을 연 뒤 신청이 변하면 표시된 건수와 실제 처리 건수가 다를 수 있습니다. 일부 신청이 잔액 부족이나 이미 처리된 상태로 실패해도 나머지 신청은 계속 처리합니다.</p>',
];
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/rewards/withdrawal-requests');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php $rewardWithdrawalDetailFilterOpen = $statusFilter !== []; ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/rewards/withdrawal-requests')); ?>" class="filtering-form admin-reward-request-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $rewardWithdrawalDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-reward-request-filter-grid">
            <div class="filtering-field admin-reward-request-filter-field">
                <label for="reward-withdrawal-search-field" class="filtering-label">검색조건</label>
                <select id="reward-withdrawal-search-field" name="field" class="form-select filtering-input">
                    <?php foreach ($searchFieldOptions as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo $searchField === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field filtering-field-fill admin-reward-request-filter-keyword">
                <label for="reward-withdrawal-search-keyword" class="filtering-label">검색어</label>
                <input id="reward-withdrawal-search-keyword" type="text" name="q" value="<?php echo sr_e($searchKeyword); ?>" class="form-input filtering-input" maxlength="120" placeholder="회원, 계좌, 메모, 신청 번호">
            </div>
        </div>
        <div id="reward_withdrawal_detail_filters" class="filtering-body" data-filtering-body<?php echo $rewardWithdrawalDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field admin-reward-request-filter-status">
                <span class="filtering-label">상태</span>
                <?php echo sr_admin_filter_radio_toggle_group_html('reward-withdrawal-status', 'status', $requestStatusOptions, $statusFilter, '전체'); ?>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $rewardWithdrawalDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="reward_withdrawal_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화</button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">출금 신청 목록</h2>
        <div class="card-actions">
            <button type="button" class="btn btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="reward-withdrawal-batch-modal" data-overlay="#reward-withdrawal-batch-modal">일괄처리</button>
        </div>
    </div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($pagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list">
            <thead>
                <tr>
                    <th>신청일</th>
                    <th>회원</th>
                    <th>금액</th>
                    <th>입금 계좌</th>
                    <th>상태</th>
                    <th>요청 메모</th>
                    <th>처리 정보</th>
                    <th class="text-end">
                        <span class="form-label-help">
                            <button type="button" class="admin-label-help-button" aria-label="출금 처리 도움말 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($rewardWithdrawalHelp['id']); ?>" data-overlay="#<?php echo sr_e($rewardWithdrawalHelp['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
                            <span>처리</span>
                        </span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requests === []) { ?>
                    <tr><td colspan="8" class="admin-empty-state">출금 신청이 없습니다.</td></tr>
                <?php } else { ?>
                    <?php foreach ($requests as $request) { ?>
                        <tr>
                            <td><?php echo sr_reward_time_html((string) $request['requested_at']); ?></td>
                            <td>
                                <?php echo sr_e(sr_admin_member_display_name_preview($request)); ?><br>
                                <?php echo sr_e(sr_admin_member_email_display($request)); ?><br>
                                <span class="text-muted"><?php echo sr_e((string) $request['account_public_hash']); ?></span>
                            </td>
                            <td><?php echo sr_e(number_format((int) $request['amount'])); ?> 원</td>
                            <td><?php echo sr_e((string) $request['bank_name']); ?><br><?php echo sr_e((string) $request['bank_account_number']); ?><br><?php echo sr_e((string) $request['bank_account_holder']); ?></td>
                            <td><?php echo sr_e(sr_reward_request_status_label((string) $request['status'])); ?></td>
                            <td><?php echo sr_e((string) $request['requester_note']); ?></td>
                            <td>
                                <?php echo sr_e((string) $request['admin_note']); ?>
                                <?php if (!empty($request['transaction_id'])) { ?>
                                    <br>거래 #<?php echo sr_e((string) $request['transaction_id']); ?>
                                <?php } ?>
                                <?php if (!empty($request['processed_at'])) { ?>
                                    <br><?php echo sr_reward_time_html((string) $request['processed_at']); ?>
                                <?php } ?>
                            </td>
                            <td class="admin-table-actions-cell">
                                <?php if ((string) $request['status'] === 'pending') { ?>
                                    <form method="post" action="<?php echo sr_e($requestListActionUrl); ?>" class="ui-form-theme">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="request_id" value="<?php echo sr_e((string) $request['id']); ?>">
                                        <div class="admin-row-actions">
                                            <input type="text" name="admin_note" maxlength="255" required class="form-input" placeholder="외부 이체 확인 번호 또는 처리 근거" aria-label="회원에게 표시할 출금 완료 처리 근거">
                                            <button type="submit" name="intent" value="complete" class="btn btn-sm btn-solid-primary">완료</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" aria-haspopup="dialog" aria-expanded="false" aria-controls="reward-withdrawal-reject-modal-<?php echo sr_e((string) $request['id']); ?>" data-overlay="#reward-withdrawal-reject-modal-<?php echo sr_e((string) $request['id']); ?>">거부</button>
                                        </div>
                                    </form>
                                    <div id="reward-withdrawal-reject-modal-<?php echo sr_e((string) $request['id']); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="reward-withdrawal-reject-modal-<?php echo sr_e((string) $request['id']); ?>-label" aria-hidden="true" inert>
                                        <div class="modal-dialog">
                                            <form method="post" action="<?php echo sr_e($requestListActionUrl); ?>" class="modal-content admin-form ui-form-theme">
                                                <div class="modal-header">
                                                    <h3 id="reward-withdrawal-reject-modal-<?php echo sr_e((string) $request['id']); ?>-label" class="modal-title">출금 신청 거부</h3>
                                                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#reward-withdrawal-reject-modal-<?php echo sr_e((string) $request['id']); ?>">
                                                        <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="request_id" value="<?php echo sr_e((string) $request['id']); ?>">
                                                    <input type="hidden" name="intent" value="reject">
                                                    <p class="form-help">거부하면 적립금은 차감하지 않고, 입력한 사유는 회원의 출금 신청 내역에 표시됩니다.</p>
                                                    <div class="form-row">
                                                        <label for="reward-withdrawal-reject-note-<?php echo sr_e((string) $request['id']); ?>" class="form-row-label">거부 사유 <span class="text-danger">(필수)</span></label>
                                                        <div class="form-field">
                                                            <input id="reward-withdrawal-reject-note-<?php echo sr_e((string) $request['id']); ?>" type="text" name="admin_note" maxlength="255" required class="form-input" placeholder="계좌 정보 오류, 대상 조건 미충족 등">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#reward-withdrawal-reject-modal-<?php echo sr_e((string) $request['id']); ?>">취소</button>
                                                    <button type="submit" class="btn btn-outline-danger modal-action">거부</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <span class="text-muted">-</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php echo sr_admin_pagination_html($pagination, '적립금 출금 신청 목록 페이지'); ?>

<div id="reward-withdrawal-batch-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="reward-withdrawal-batch-modal-label" aria-hidden="true" inert>
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e($requestListActionUrl); ?>" class="modal-content admin-form ui-form-theme">
            <div class="modal-header">
                <h3 id="reward-withdrawal-batch-modal-label" class="modal-title">일괄처리</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#reward-withdrawal-batch-modal">
                    <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                </button>
            </div>
            <div class="modal-body">
                <?php echo sr_csrf_field(); ?>
                <p class="form-help">
                    현재 필터와 검색 조건에 맞는 대기 출금 신청 <?php echo sr_e(number_format($requestBatchPendingCount)); ?>건을 처리합니다. 한 번에 최대 <?php echo sr_e(number_format($requestBatchLimit)); ?>건까지 처리하며, 실행 시점에 신청 상태가 바뀌면 실제 처리 건수가 다를 수 있습니다.
                </p>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('reward-withdrawal-batch-admin-note', '처리 메모', $rewardWithdrawalHelp['id'], '출금 처리 도움말 보기', true); ?>
                    <div class="form-field">
                        <input id="reward-withdrawal-batch-admin-note" type="text" name="admin_note" maxlength="255" required class="form-input" placeholder="공통 외부 이체 확인 번호 또는 거부 사유">
                        <p class="form-help">모든 처리 대상에 같은 메모를 저장하며 회원에게도 표시합니다.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#reward-withdrawal-batch-modal">취소</button>
                <button type="submit" name="intent" value="batch_reject" class="btn btn-outline-danger modal-action"<?php echo $requestBatchPendingCount < 1 || $requestBatchPendingCount > $requestBatchLimit ? ' disabled' : ''; ?>>일괄 거부</button>
                <button type="submit" name="intent" value="batch_complete" class="btn btn-solid-primary modal-action"<?php echo $requestBatchPendingCount < 1 || $requestBatchPendingCount > $requestBatchLimit ? ' disabled' : ''; ?>>일괄 완료</button>
            </div>
        </form>
    </div>
</div>

<?php echo sr_admin_help_modal_html($rewardWithdrawalHelp['id'], $rewardWithdrawalHelp['title'], $rewardWithdrawalHelp['body']); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
