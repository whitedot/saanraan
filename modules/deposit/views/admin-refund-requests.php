<?php

$statusFilter = isset($statusFilter) ? (string) $statusFilter : '';
$requestStatusOptions = [
    '' => '전체',
    'pending' => '대기',
    'completed' => '완료',
    'rejected' => '반려',
    'canceled' => '취소',
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/deposits/refund-requests')); ?>" class="admin-filter ui-form-theme">
    <div class="admin-filter-grid">
        <div class="admin-filter-field">
            <label for="deposit-refund-status" class="admin-filter-label">상태</label>
            <select id="deposit-refund-status" name="status" class="form-select admin-filter-input">
                <?php foreach ($requestStatusOptions as $statusValue => $statusLabel) { ?>
                    <option value="<?php echo sr_e($statusValue); ?>"<?php echo $statusFilter === $statusValue ? ' selected' : ''; ?>><?php echo sr_e($statusLabel); ?></option>
                <?php } ?>
            </select>
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title">환불 신청 목록</h2></div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($pagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>신청일</th>
                    <th>회원</th>
                    <th>금액</th>
                    <th>환불 계좌</th>
                    <th>상태</th>
                    <th>요청 메모</th>
                    <th>처리 정보</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requests === []) { ?>
                    <tr><td colspan="8" class="admin-empty-state">환불 신청이 없습니다.</td></tr>
                <?php } else { ?>
                    <?php foreach ($requests as $request) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $request['requested_at']); ?></td>
                            <td>
                                <?php echo sr_e(sr_admin_member_display_name_preview($request)); ?><br>
                                <?php echo sr_e(sr_admin_member_email_display($request)); ?><br>
                                <span class="text-muted"><?php echo sr_e((string) $request['account_public_hash']); ?></span>
                            </td>
                            <td><?php echo sr_e(number_format((int) $request['amount'])); ?> 원</td>
                            <td><?php echo sr_e((string) $request['bank_name']); ?><br><?php echo sr_e((string) $request['bank_account_number']); ?><br><?php echo sr_e((string) $request['bank_account_holder']); ?></td>
                            <td><?php echo sr_e(sr_deposit_request_status_label((string) $request['status'])); ?></td>
                            <td><?php echo sr_e((string) $request['requester_note']); ?></td>
                            <td>
                                <?php echo sr_e((string) $request['admin_note']); ?>
                                <?php if (!empty($request['transaction_id'])) { ?>
                                    <br>거래 #<?php echo sr_e((string) $request['transaction_id']); ?>
                                <?php } ?>
                                <?php if (!empty($request['processed_at'])) { ?>
                                    <br><?php echo sr_e((string) $request['processed_at']); ?>
                                <?php } ?>
                            </td>
                            <td class="admin-table-actions-cell">
                                <?php if ((string) $request['status'] === 'pending') { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/deposits/refund-requests')); ?>" class="ui-form-theme">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="request_id" value="<?php echo sr_e((string) $request['id']); ?>">
                                        <div class="admin-row-actions">
                                            <input type="text" name="admin_note" maxlength="255" required class="form-input" placeholder="이체 확인 번호 또는 반려 사유">
                                            <button type="submit" name="intent" value="complete" class="btn btn-sm btn-solid-primary">완료</button>
                                            <button type="submit" name="intent" value="reject" class="btn btn-sm btn-outline-danger">반려</button>
                                        </div>
                                    </form>
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

<?php echo sr_admin_pagination_html($pagination, '예치금 환불 신청 목록 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
