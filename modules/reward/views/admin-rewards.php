<?php

$rewardAdminPage = isset($rewardAdminPage) ? (string) $rewardAdminPage : 'balances';
$adminPageTitle = '적립금 잔액';
if ($rewardAdminPage === 'transactions') {
    $adminPageTitle = '적립금 거래 내역';
}
$accountLookupFilter = isset($accountLookupFilter) && is_array($accountLookupFilter) ? $accountLookupFilter : ['field' => 'all', 'keyword' => (string) ($accountIdentifierFilter ?? '')];
$rewardReferenceTypeOptions = [
    '' => '없음',
    'order' => '주문',
    'payment' => '결제',
    'refund' => '환불',
    'support_ticket' => '고객문의',
    'event' => '이벤트',
    'migration' => '데이터 이관',
];
$rewardAdjustModalAccounts = [];
if ($rewardAdminPage === 'balances') {
    $rewardAdjustModalSeen = [];
    $rewardAdjustModalAccounts[] = [
        'id' => 0,
        'account_public_hash' => '',
        'display_name' => '',
        'email' => '',
        'balance' => 0,
    ];
    if (isset($selectedAccount) && is_array($selectedAccount)) {
        $rewardSelectedHash = (string) ($selectedAccount['account_public_hash'] ?? '');
        if ($rewardSelectedHash !== '') {
            $rewardAdjustModalAccounts[] = [
                'id' => (int) ($selectedAccount['id'] ?? 0),
                'account_public_hash' => $rewardSelectedHash,
                'display_name' => (string) ($selectedAccount['display_name'] ?? ''),
                'email' => (string) ($selectedAccount['email'] ?? ''),
                'balance' => (int) ($selectedBalance ?? 0),
            ];
            $rewardAdjustModalSeen[$rewardSelectedHash] = true;
        }
    }
    if (isset($balances) && is_array($balances)) {
        foreach ($balances as $rewardBalanceModalAccount) {
            if (!is_array($rewardBalanceModalAccount)) {
                continue;
            }
            $rewardBalanceHash = (string) ($rewardBalanceModalAccount['account_public_hash'] ?? '');
            if ($rewardBalanceHash === '' || isset($rewardAdjustModalSeen[$rewardBalanceHash])) {
                continue;
            }
            $rewardAdjustModalAccounts[] = [
                'id' => (int) ($rewardBalanceModalAccount['account_id'] ?? 0),
                'account_public_hash' => $rewardBalanceHash,
                'display_name' => (string) ($rewardBalanceModalAccount['display_name'] ?? ''),
                'email' => (string) ($rewardBalanceModalAccount['email'] ?? ''),
                'balance' => (int) ($rewardBalanceModalAccount['balance'] ?? 0),
            ];
            $rewardAdjustModalSeen[$rewardBalanceHash] = true;
        }
    }
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url($rewardAdminPage === 'transactions' ? '/admin/rewards/transactions' : '/admin/rewards/balances')); ?>" class="admin-filter admin-asset-member-filter ui-form-theme">
    <div class="admin-filter-grid admin-asset-member-search-grid">
        <div class="admin-filter-field">
            <label for="reward-member-search-field" class="admin-filter-label">검색 조건</label>
            <select name="field" id="reward-member-search-field" class="form-select admin-filter-input">
                <?php foreach (['all' => '전체', 'hash' => '해시 아이디', 'email' => '이메일', 'login_id' => '로그인 아이디', 'name' => '이름'] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($accountLookupFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-asset-member-filter-keyword">
            <label for="reward-member-search-keyword" class="admin-filter-label">검색어</label>
            <input type="text" id="reward-member-search-keyword" name="q" value="<?php echo sr_e((string) ($accountLookupFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="해시 아이디, 이메일, 로그인 아이디, 이름">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<?php if (is_array($selectedAccount)) { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/rewards/balances?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light">잔액 보기</a>
            <a href="<?php echo sr_e(sr_url('/admin/rewards/transactions?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light">거래 내역 보기</a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">회원 <strong><?php echo sr_e(sr_admin_member_display_name_preview($selectedAccount)); ?></strong></span>
            <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($selectedAccount)); ?></span>
            <span class="admin-summary-meta">잔액 <strong><?php echo sr_e(number_format((int) $selectedBalance)); ?> 원</strong></span>
        </div>
    </div>
<?php } elseif ((string) ($accountLookupFilter['keyword'] ?? '') !== '') { ?>
    <p class="admin-empty-state">회원을 찾을 수 없습니다.</p>
<?php } ?>

<?php if ($rewardAdminPage === 'transactions') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header"><h2 class="card-title">최근 거래</h2></div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>회원</th>
                    <th>유형</th>
                    <th>금액</th>
                    <th>거래 후 잔액</th>
                    <th>사유</th>
                    <th>참조</th>
                    <th>생성일</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions === []) { ?>
                    <tr>
                        <td colspan="8" class="admin-empty-state">적립금 거래가 없습니다.</td>
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
                            <td><?php echo sr_e(number_format((int) $transaction['amount'])); ?> 원</td>
                            <td><?php echo sr_e(number_format((int) $transaction['balance_after'])); ?> 원</td>
                            <td><?php echo sr_e((string) $transaction['reason']); ?></td>
                            <td><?php echo sr_e((string) $transaction['reference_type'] . ((string) $transaction['reference_id'] !== '' ? ':' . (string) $transaction['reference_id'] : '')); ?></td>
                            <td><?php echo sr_e((string) $transaction['created_at']); ?></td>
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
            <?php $rewardHeaderAdjustModalId = is_array($selectedAccount) ? 'reward-adjust-modal-' . (int) ($selectedAccount['id'] ?? 0) : 'reward-adjust-modal-0'; ?>
            <?php $rewardHeaderAdjustUrl = is_array($selectedAccount) ? '/admin/rewards/balances?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']) : '/admin/rewards/balances'; ?>
            <a href="<?php echo sr_e(sr_url($rewardHeaderAdjustUrl)); ?>" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($rewardHeaderAdjustModalId); ?>" data-overlay="#<?php echo sr_e($rewardHeaderAdjustModalId); ?>">조정하기</a>
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
                        <td colspan="6" class="admin-empty-state">적립금 잔액이 없습니다.</td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($balances as $balance) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $balance['account_public_hash']); ?></td>
                            <td><?php echo sr_e(sr_admin_member_display_name_preview($balance)); ?><br><?php echo sr_e(sr_admin_member_email_display($balance)); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $balance['status'], 'member_status')); ?></td>
                            <td><?php echo sr_e(number_format((int) $balance['balance'])); ?> 원</td>
                            <td><?php echo sr_e((string) $balance['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/rewards/transactions?account_identifier=' . rawurlencode((string) $balance['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light">거래 내역</a>
                                    <?php $rewardBalanceAdjustModalId = 'reward-adjust-modal-' . (int) ($balance['account_id'] ?? 0); ?>
                                    <a href="<?php echo sr_e(sr_url('/admin/rewards/balances?account_identifier=' . rawurlencode((string) $balance['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($rewardBalanceAdjustModalId); ?>" data-overlay="#<?php echo sr_e($rewardBalanceAdjustModalId); ?>">조정</a>
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

<?php if ($rewardAdminPage === 'balances' && $rewardAdjustModalAccounts !== []) { ?>
    <?php foreach ($rewardAdjustModalAccounts as $rewardAdjustModalAccount) { ?>
        <?php
        $rewardAdjustModalId = 'reward-adjust-modal-' . (int) $rewardAdjustModalAccount['id'];
        $rewardAdjustFieldPrefix = 'reward_adjust_' . (int) $rewardAdjustModalAccount['id'];
        $rewardAdjustAccountInputId = $rewardAdjustFieldPrefix . '_account_identifier';
        $rewardAdjustReferenceTypeInputId = $rewardAdjustFieldPrefix . '_reference_type';
        $rewardAdjustReferenceIdInputId = $rewardAdjustFieldPrefix . '_reference_id';
        $rewardAdjustMemberLookupModalId = $rewardAdjustFieldPrefix . '_member_lookup_modal';
        $rewardAdjustReferenceLookupModalId = $rewardAdjustFieldPrefix . '_reference_lookup_modal';
        ?>
        <div id="<?php echo sr_e($rewardAdjustModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/rewards/balances' . ((string) $rewardAdjustModalAccount['account_public_hash'] !== '' ? '?account_identifier=' . rawurlencode((string) $rewardAdjustModalAccount['account_public_hash']) : ''))); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_title" class="modal-title">적립금 조정</h3>
                        <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($rewardAdjustModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <?php if ((string) $rewardAdjustModalAccount['account_public_hash'] !== '') { ?>
                            <input type="hidden" name="account_identifier" value="<?php echo sr_e((string) $rewardAdjustModalAccount['account_public_hash']); ?>">
                            <div class="admin-summary-stats">
                                <span class="admin-summary-meta">회원 <strong><?php echo sr_e(sr_admin_member_display_name_preview($rewardAdjustModalAccount)); ?></strong></span>
                                <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($rewardAdjustModalAccount)); ?></span>
                                <span class="admin-summary-meta">현재 잔액 <strong><?php echo sr_e(number_format((int) $rewardAdjustModalAccount['balance'])); ?> 원</strong></span>
                            </div>
                        <?php } else { ?>
                            <div class="admin-form-row">
                                <label class="form-label" for="<?php echo sr_e($rewardAdjustAccountInputId); ?>">회원 공개 해시 <span class="sr-required-label">(필수)</span></label>
                                <div class="admin-form-field">
                                    <div class="admin-lookup-control">
                                        <input id="<?php echo sr_e($rewardAdjustAccountInputId); ?>" type="text" name="account_identifier" value="<?php echo sr_e($accountIdentifierFilter); ?>" class="form-input" maxlength="80" required data-overlay-focus>
                                        <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($rewardAdjustMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($rewardAdjustMemberLookupModalId); ?>" data-admin-member-lookup-open data-target="#<?php echo sr_e($rewardAdjustAccountInputId); ?>">회원 검색</button>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_transaction_type">거래 유형</label>
                            <div class="admin-form-field">
                                <select id="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_transaction_type" name="transaction_type" class="form-select">
                                    <?php foreach ($allowedTransactionTypes as $type) { ?>
                                        <option value="<?php echo sr_e($type); ?>"><?php echo sr_e(sr_admin_code_label($type, 'transaction_type')); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_amount">금액 <span class="sr-required-label">(필수)</span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_amount" type="number" name="amount" step="1" required class="form-input" data-overlay-focus>
                                <p class="admin-form-help">지급/환불은 양수, 사용/만료는 음수, 조정은 양수 또는 음수로 입력합니다.</p>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_reason">사유 <span class="sr-required-label">(필수)</span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_reason" type="text" name="reason" maxlength="255" required class="form-input form-control-full">
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($rewardAdjustReferenceTypeInputId); ?>">참조 유형</label>
                            <div class="admin-form-field">
                                <select id="<?php echo sr_e($rewardAdjustReferenceTypeInputId); ?>" name="reference_type" class="form-select">
                                    <?php foreach ($rewardReferenceTypeOptions as $referenceTypeValue => $referenceTypeLabel) { ?>
                                        <option value="<?php echo sr_e($referenceTypeValue); ?>"><?php echo sr_e($referenceTypeLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($rewardAdjustReferenceIdInputId); ?>">참조 ID</label>
                            <div class="admin-form-field">
                                <div class="admin-lookup-control">
                                    <input id="<?php echo sr_e($rewardAdjustReferenceIdInputId); ?>" type="text" name="reference_id" maxlength="120" class="form-input">
                                    <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($rewardAdjustReferenceLookupModalId); ?>" data-overlay="#<?php echo sr_e($rewardAdjustReferenceLookupModalId); ?>" data-admin-reference-lookup-open data-type-target="#<?php echo sr_e($rewardAdjustReferenceTypeInputId); ?>" data-id-target="#<?php echo sr_e($rewardAdjustReferenceIdInputId); ?>">참조 검색</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($rewardAdjustModalId); ?>">닫기</button>
                        <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $assetAdjustLookup = [
            'field_prefix' => $rewardAdjustFieldPrefix,
            'member_input_id' => (string) $rewardAdjustModalAccount['account_public_hash'] === '' ? $rewardAdjustAccountInputId : '',
            'reference_type_id' => $rewardAdjustReferenceTypeInputId,
            'reference_id_id' => $rewardAdjustReferenceIdInputId,
            'return_overlay_id' => $rewardAdjustModalId,
            'member_search_url' => sr_url('/admin/members/search'),
            'reference_search_url' => sr_url('/admin/rewards/reference-search'),
            'reference_options' => $rewardReferenceTypeOptions,
        ];
        include SR_ROOT . '/modules/admin/views/asset-adjust-lookup-modals.php';
        ?>
    <?php } ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
