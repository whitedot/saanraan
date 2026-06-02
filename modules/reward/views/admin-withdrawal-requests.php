<?php

$statusFilter = isset($statusFilter) ? (string) $statusFilter : '';
$requestStatusOptions = [
    '' => '전체',
    'pending' => '대기',
    'completed' => '완료',
    'rejected' => '반려',
    'canceled' => '취소',
];
$requestStatusValues = array_keys($requestStatusOptions);
$requestStatusCount = count($requestStatusValues);
$searchField = isset($searchField) ? (string) $searchField : 'all';
$searchKeyword = isset($searchKeyword) ? (string) $searchKeyword : '';
$searchFieldOptions = [
    'all' => '전체',
    'member' => '회원',
    'bank' => '계좌',
    'note' => '메모',
    'request' => '신청/거래 번호',
];
$requestStatusUrl = static function (string $statusValue) use ($searchField, $searchKeyword): string {
    $params = [];
    if ($statusValue !== '') {
        $params['status'] = $statusValue;
    }
    if ($searchField !== 'all') {
        $params['field'] = $searchField;
    }
    if ($searchKeyword !== '') {
        $params['q'] = $searchKeyword;
    }

    return sr_url('/admin/rewards/withdrawal-requests' . ($params === [] ? '' : '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986)));
};
$requestListActionUrl = sr_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin/rewards/withdrawal-requests'));
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/rewards/withdrawal-requests')); ?>" class="admin-filter admin-reward-request-filter ui-form-theme">
    <div class="admin-filter-grid admin-reward-request-filter-grid">
        <div class="admin-filter-field admin-reward-request-filter-status">
            <span class="admin-filter-label">상태</span>
            <div class="admin-sort-button-group" role="group" aria-label="출금 신청 상태 필터">
                <?php foreach ($requestStatusValues as $requestStatusIndex => $statusValue) { ?>
                    <?php
                    $statusLabel = (string) $requestStatusOptions[$statusValue];
                    $statusGroupClass = $requestStatusIndex === 0
                        ? 'btn-group-start'
                        : ($requestStatusIndex === $requestStatusCount - 1 ? 'btn-group-end' : 'btn-group-middle');
                    $statusButtonClass = 'btn ' . $statusGroupClass . ' ' . ($statusFilter === $statusValue ? 'btn-solid-primary' : 'btn-solid-light');
                    ?>
                    <a href="<?php echo sr_e($requestStatusUrl($statusValue)); ?>" class="<?php echo sr_e($statusButtonClass); ?>"<?php echo $statusFilter === $statusValue ? ' aria-current="true"' : ''; ?>><?php echo sr_e($statusLabel); ?></a>
                <?php } ?>
            </div>
        </div>
        <?php if ($statusFilter !== '') { ?>
            <input type="hidden" name="status" value="<?php echo sr_e($statusFilter); ?>">
        <?php } ?>
        <div class="admin-filter-field admin-reward-request-filter-field">
            <label for="reward-withdrawal-search-field" class="admin-filter-label">검색 조건</label>
            <select id="reward-withdrawal-search-field" name="field" class="form-select admin-filter-input">
                <?php foreach ($searchFieldOptions as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo $searchField === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-reward-request-filter-keyword">
            <label for="reward-withdrawal-search-keyword" class="admin-filter-label">검색어</label>
            <input id="reward-withdrawal-search-keyword" type="search" name="q" value="<?php echo sr_e($searchKeyword); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="회원, 계좌, 메모, 신청 번호">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title">출금 신청 목록</h2></div>
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
                    <th>입금 계좌</th>
                    <th>상태</th>
                    <th>요청 메모</th>
                    <th>처리 정보</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requests === []) { ?>
                    <tr><td colspan="8" class="admin-empty-state">출금 신청이 없습니다.</td></tr>
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
                            <td><?php echo sr_e(sr_reward_request_status_label((string) $request['status'])); ?></td>
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
                                    <form method="post" action="<?php echo sr_e($requestListActionUrl); ?>" class="ui-form-theme">
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

<?php echo sr_admin_pagination_html($pagination, '적립금 출금 신청 목록 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
