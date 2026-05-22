<?php

$pointAdminPage = isset($pointAdminPage) ? (string) $pointAdminPage : 'balances';
$adminPageTitle = '포인트 잔액';
if ($pointAdminPage === 'transactions') {
    $adminPageTitle = '포인트 거래 내역';
}
$accountLookupFilter = isset($accountLookupFilter) && is_array($accountLookupFilter) ? $accountLookupFilter : ['field' => 'all', 'keyword' => (string) ($accountIdentifierFilter ?? '')];
$pointReferenceTypeOptions = [
    '' => '없음',
    'order' => '주문',
    'payment' => '결제',
    'refund' => '환불',
    'support_ticket' => '고객문의',
    'event' => '이벤트',
    'migration' => '데이터 이관',
];
$pointAdjustModalAccounts = [];
if ($pointAdminPage === 'balances') {
    $pointAdjustModalSeen = [];
    $pointAdjustModalAccounts[] = [
        'id' => 0,
        'account_public_hash' => '',
        'display_name' => '',
        'email' => '',
        'balance' => 0,
    ];
    if (isset($selectedAccount) && is_array($selectedAccount)) {
        $pointSelectedHash = (string) ($selectedAccount['account_public_hash'] ?? '');
        if ($pointSelectedHash !== '') {
            $pointAdjustModalAccounts[] = [
                'id' => (int) ($selectedAccount['id'] ?? 0),
                'account_public_hash' => $pointSelectedHash,
                'display_name' => (string) ($selectedAccount['display_name'] ?? ''),
                'email' => (string) ($selectedAccount['email'] ?? ''),
                'balance' => (int) ($selectedBalance ?? 0),
            ];
            $pointAdjustModalSeen[$pointSelectedHash] = true;
        }
    }
    if (isset($balances) && is_array($balances)) {
        foreach ($balances as $pointBalanceModalAccount) {
            if (!is_array($pointBalanceModalAccount)) {
                continue;
            }
            $pointBalanceHash = (string) ($pointBalanceModalAccount['account_public_hash'] ?? '');
            if ($pointBalanceHash === '' || isset($pointAdjustModalSeen[$pointBalanceHash])) {
                continue;
            }
            $pointAdjustModalAccounts[] = [
                'id' => (int) ($pointBalanceModalAccount['account_id'] ?? 0),
                'account_public_hash' => $pointBalanceHash,
                'display_name' => (string) ($pointBalanceModalAccount['display_name'] ?? ''),
                'email' => (string) ($pointBalanceModalAccount['email'] ?? ''),
                'balance' => (int) ($pointBalanceModalAccount['balance'] ?? 0),
            ];
            $pointAdjustModalSeen[$pointBalanceHash] = true;
        }
    }
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url($pointAdminPage === 'transactions' ? '/admin/points/transactions' : '/admin/points/balances')); ?>" class="admin-filter admin-asset-member-filter ui-form-theme">
    <div class="admin-filter-grid admin-asset-member-search-grid">
        <div class="admin-filter-field">
            <label for="point-member-search-field" class="admin-filter-label">검색 조건</label>
            <select name="field" id="point-member-search-field" class="form-select admin-filter-input">
                <?php foreach (['all' => '전체', 'hash' => '해시 아이디', 'email' => '이메일', 'login_id' => '로그인 아이디', 'name' => '이름'] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($accountLookupFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-asset-member-filter-keyword">
            <label for="point-member-search-keyword" class="admin-filter-label">검색어</label>
            <input type="text" id="point-member-search-keyword" name="q" value="<?php echo sr_e((string) ($accountLookupFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="해시 아이디, 이메일, 로그인 아이디, 이름">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<?php if (is_array($selectedAccount)) { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/points/balances?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light">잔액 보기</a>
            <a href="<?php echo sr_e(sr_url('/admin/points/transactions?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light">거래 내역 보기</a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">회원 <strong><?php echo sr_e(sr_admin_member_display_name_preview($selectedAccount)); ?></strong></span>
            <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($selectedAccount)); ?></span>
            <span class="admin-summary-meta">잔액 <strong><?php echo sr_e(number_format((int) $selectedBalance)); ?> P</strong></span>
        </div>
    </div>
<?php } elseif ((string) ($accountLookupFilter['keyword'] ?? '') !== '') { ?>
    <p class="admin-empty-state">회원을 찾을 수 없습니다.</p>
<?php } ?>

<?php if ($pointAdminPage === 'transactions') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header"><h2 class="card-title">최근 거래</h2></div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>회원</th>
                    <th>유형</th>
                    <th>수량</th>
                    <th>거래 후 잔액</th>
                    <th>사유</th>
                    <th>참조</th>
                    <th>생성일</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state">포인트 거래가 없습니다.</td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($transactions as $transaction) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $transaction['id']); ?></td>
                            <td>
                                <?php echo sr_e(sr_admin_member_display_name_preview($transaction)); ?><br>
                                <?php echo sr_e(sr_admin_member_email_display($transaction)); ?><br>
                                <?php echo sr_e((string) $transaction['account_public_hash']); ?>
                            </td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $transaction['transaction_type'], 'transaction_type')); ?></td>
                            <td><?php echo sr_e(number_format((int) $transaction['amount'])); ?> P</td>
                            <td><?php echo sr_e(number_format((int) $transaction['balance_after'])); ?> P</td>
                            <td><?php echo sr_e((string) $transaction['reason']); ?></td>
                            <td><?php echo sr_e((string) $transaction['reference_type'] . ((string) $transaction['reference_id'] !== '' ? ':' . (string) $transaction['reference_id'] : '')); ?></td>
                            <td><?php echo sr_e((string) $transaction['created_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <?php if ((string) ($transaction['transaction_type'] ?? '') !== 'refund') { ?>
                                        <?php $pointTransactionRefundModalId = 'point-refund-modal-' . (int) ($transaction['id'] ?? 0); ?>
                                        <a href="<?php echo sr_e(sr_url('/admin/points/transactions?account_identifier=' . rawurlencode((string) $transaction['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($pointTransactionRefundModalId); ?>" data-overlay="#<?php echo sr_e($pointTransactionRefundModalId); ?>">환불</a>
                                    <?php } else { ?>
                                        <span class="text-muted">-</span>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } else { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">최근 잔액</h2>
            <?php $pointHeaderAdjustModalId = is_array($selectedAccount) ? 'point-adjust-modal-' . (int) ($selectedAccount['id'] ?? 0) : 'point-adjust-modal-0'; ?>
            <?php $pointHeaderAdjustUrl = is_array($selectedAccount) ? '/admin/points/balances?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']) : '/admin/points/balances'; ?>
            <a href="<?php echo sr_e(sr_url($pointHeaderAdjustUrl)); ?>" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($pointHeaderAdjustModalId); ?>" data-overlay="#<?php echo sr_e($pointHeaderAdjustModalId); ?>">조정하기</a>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>회원 공개 해시</th>
                    <th>회원</th>
                    <th>상태</th>
                    <th>잔액</th>
                    <th>수정일</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($balances === []) { ?>
                    <tr>
                        <td colspan="6" class="admin-empty-state">포인트 잔액이 없습니다.</td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($balances as $balance) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $balance['account_public_hash']); ?></td>
                            <td><?php echo sr_e(sr_admin_member_display_name_preview($balance)); ?><br><?php echo sr_e(sr_admin_member_email_display($balance)); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $balance['status'], 'member_status')); ?></td>
                            <td><?php echo sr_e(number_format((int) $balance['balance'])); ?> P</td>
                            <td><?php echo sr_e((string) $balance['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/points/transactions?account_identifier=' . rawurlencode((string) $balance['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light">거래 내역</a>
                                    <?php $pointBalanceAdjustModalId = 'point-adjust-modal-' . (int) ($balance['account_id'] ?? 0); ?>
                                    <a href="<?php echo sr_e(sr_url('/admin/points/balances?account_identifier=' . rawurlencode((string) $balance['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($pointBalanceAdjustModalId); ?>" data-overlay="#<?php echo sr_e($pointBalanceAdjustModalId); ?>">조정</a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } ?>

<?php if ($pointAdminPage === 'balances' && $pointAdjustModalAccounts !== []) { ?>
    <?php foreach ($pointAdjustModalAccounts as $pointAdjustModalAccount) { ?>
        <?php
        $pointAdjustModalId = 'point-adjust-modal-' . (int) $pointAdjustModalAccount['id'];
        $pointAdjustFieldPrefix = 'point_adjust_' . (int) $pointAdjustModalAccount['id'];
        $pointAdjustAccountInputId = $pointAdjustFieldPrefix . '_account_identifier';
        $pointAdjustReferenceTypeInputId = $pointAdjustFieldPrefix . '_reference_type';
        $pointAdjustReferenceIdInputId = $pointAdjustFieldPrefix . '_reference_id';
        $pointAdjustMemberLookupModalId = $pointAdjustFieldPrefix . '_member_lookup_modal';
        $pointAdjustReferenceLookupModalId = $pointAdjustFieldPrefix . '_reference_lookup_modal';
        ?>
        <div id="<?php echo sr_e($pointAdjustModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($pointAdjustFieldPrefix); ?>_title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/points/balances' . ((string) $pointAdjustModalAccount['account_public_hash'] !== '' ? '?account_identifier=' . rawurlencode((string) $pointAdjustModalAccount['account_public_hash']) : ''))); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($pointAdjustFieldPrefix); ?>_title" class="modal-title">포인트 조정</h3>
                        <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($pointAdjustModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <?php if ((string) $pointAdjustModalAccount['account_public_hash'] !== '') { ?>
                            <input type="hidden" name="account_identifier" value="<?php echo sr_e((string) $pointAdjustModalAccount['account_public_hash']); ?>">
                            <div class="admin-summary-stats">
                                <span class="admin-summary-meta">회원 <strong><?php echo sr_e(sr_admin_member_display_name_preview($pointAdjustModalAccount)); ?></strong></span>
                                <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($pointAdjustModalAccount)); ?></span>
                                <span class="admin-summary-meta">현재 잔액 <strong><?php echo sr_e(number_format((int) $pointAdjustModalAccount['balance'])); ?> P</strong></span>
                            </div>
                        <?php } else { ?>
                            <div class="admin-form-row">
                                <label class="form-label" for="<?php echo sr_e($pointAdjustAccountInputId); ?>">회원 공개 해시 <span class="sr-required-label">(필수)</span></label>
                                <div class="admin-form-field">
                                    <div class="admin-lookup-control">
                                        <input id="<?php echo sr_e($pointAdjustAccountInputId); ?>" type="text" name="account_identifier" value="<?php echo sr_e($accountIdentifierFilter); ?>" class="form-input" maxlength="80" required data-overlay-focus>
                                        <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($pointAdjustMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($pointAdjustMemberLookupModalId); ?>" data-admin-member-lookup-open data-target="#<?php echo sr_e($pointAdjustAccountInputId); ?>">회원 검색</button>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointAdjustFieldPrefix); ?>_transaction_type">거래 유형</label>
                            <div class="admin-form-field">
                                <select id="<?php echo sr_e($pointAdjustFieldPrefix); ?>_transaction_type" name="transaction_type" class="form-select">
                                    <?php foreach ($allowedTransactionTypes as $type) { ?>
                                        <option value="<?php echo sr_e($type); ?>"><?php echo sr_e(sr_admin_code_label($type, 'transaction_type')); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointAdjustFieldPrefix); ?>_amount">수량 <span class="sr-required-label">(필수)</span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($pointAdjustFieldPrefix); ?>_amount" type="number" name="amount" step="1" required class="form-input" data-overlay-focus>
                                <p class="admin-form-help">지급/환불은 양수, 사용/만료는 음수, 조정은 양수 또는 음수로 입력합니다.</p>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointAdjustFieldPrefix); ?>_reason">사유 <span class="sr-required-label">(필수)</span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($pointAdjustFieldPrefix); ?>_reason" type="text" name="reason" maxlength="255" required class="form-input form-control-full">
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointAdjustReferenceTypeInputId); ?>">참조 유형</label>
                            <div class="admin-form-field">
                                <select id="<?php echo sr_e($pointAdjustReferenceTypeInputId); ?>" name="reference_type" class="form-select">
                                    <?php foreach ($pointReferenceTypeOptions as $referenceTypeValue => $referenceTypeLabel) { ?>
                                        <option value="<?php echo sr_e($referenceTypeValue); ?>"><?php echo sr_e($referenceTypeLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointAdjustReferenceIdInputId); ?>">참조 ID</label>
                            <div class="admin-form-field">
                                <div class="admin-lookup-control">
                                    <input id="<?php echo sr_e($pointAdjustReferenceIdInputId); ?>" type="text" name="reference_id" maxlength="120" class="form-input">
                                    <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($pointAdjustReferenceLookupModalId); ?>" data-overlay="#<?php echo sr_e($pointAdjustReferenceLookupModalId); ?>" data-admin-reference-lookup-open data-type-target="#<?php echo sr_e($pointAdjustReferenceTypeInputId); ?>" data-id-target="#<?php echo sr_e($pointAdjustReferenceIdInputId); ?>">참조 검색</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($pointAdjustModalId); ?>">닫기</button>
                        <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $assetAdjustLookup = [
            'field_prefix' => $pointAdjustFieldPrefix,
            'member_input_id' => (string) $pointAdjustModalAccount['account_public_hash'] === '' ? $pointAdjustAccountInputId : '',
            'reference_type_id' => $pointAdjustReferenceTypeInputId,
            'reference_id_id' => $pointAdjustReferenceIdInputId,
            'return_overlay_id' => $pointAdjustModalId,
            'member_search_url' => sr_url('/admin/members/search'),
            'reference_search_url' => sr_url('/admin/points/reference-search'),
            'reference_options' => $pointReferenceTypeOptions,
        ];
        include SR_ROOT . '/modules/admin/views/asset-adjust-lookup-modals.php';
        ?>
    <?php } ?>
<?php } ?>

<?php if ($pointAdminPage === 'transactions' && $transactions !== []) { ?>
    <?php foreach ($transactions as $pointRefundTransaction) { ?>
        <?php if ((string) ($pointRefundTransaction['transaction_type'] ?? '') === 'refund') { ?>
            <?php continue; ?>
        <?php } ?>
        <?php
        $pointRefundTransactionId = (int) ($pointRefundTransaction['id'] ?? 0);
        $pointRefundModalId = 'point-refund-modal-' . $pointRefundTransactionId;
        $pointRefundFieldPrefix = 'point_refund_' . $pointRefundTransactionId;
        $pointRefundReferenceId = 'point_transaction:' . $pointRefundTransactionId;
        $pointRefundDefaultAmount = abs((int) ($pointRefundTransaction['amount'] ?? 0));
        ?>
        <div id="<?php echo sr_e($pointRefundModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($pointRefundFieldPrefix); ?>_title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/points/transactions?account_identifier=' . rawurlencode((string) $pointRefundTransaction['account_public_hash']))); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($pointRefundFieldPrefix); ?>_title" class="modal-title">포인트 환불 처리</h3>
                        <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($pointRefundModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="transaction_type" value="refund">
                        <input type="hidden" name="reference_type" value="refund">
                        <input type="hidden" name="account_identifier" value="<?php echo sr_e((string) $pointRefundTransaction['account_public_hash']); ?>">
                        <input type="hidden" name="reference_id" value="<?php echo sr_e($pointRefundReferenceId); ?>">
                        <div class="admin-summary-stats">
                            <span class="admin-summary-meta">원거래 <strong>#<?php echo sr_e((string) $pointRefundTransactionId); ?></strong></span>
                            <span class="admin-summary-meta">회원 <strong><?php echo sr_e(sr_admin_member_display_name_preview($pointRefundTransaction)); ?></strong></span>
                            <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($pointRefundTransaction)); ?></span>
                            <span class="admin-summary-meta">원거래 수량 <strong><?php echo sr_e(number_format((int) $pointRefundTransaction['amount'])); ?> P</strong></span>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointRefundFieldPrefix); ?>_amount">환불 수량 <span class="sr-required-label">(필수)</span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($pointRefundFieldPrefix); ?>_amount" type="number" name="amount" value="<?php echo sr_e((string) $pointRefundDefaultAmount); ?>" step="1" min="1" required class="form-input" data-overlay-focus>
                                <p class="admin-form-help">환불 거래는 양수 포인트로 저장됩니다.</p>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointRefundFieldPrefix); ?>_reason">사유 <span class="sr-required-label">(필수)</span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($pointRefundFieldPrefix); ?>_reason" type="text" name="reason" value="<?php echo sr_e('거래 #' . (string) $pointRefundTransactionId . ' 환불'); ?>" maxlength="255" required class="form-input form-control-full">
                                <p class="admin-form-help">환불 참조 ID: <?php echo sr_e($pointRefundReferenceId); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($pointRefundModalId); ?>">닫기</button>
                        <button type="submit" class="btn btn-solid-primary modal-action">환불 저장</button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
