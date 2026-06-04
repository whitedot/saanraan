<?php

$depositAdminPage = isset($depositAdminPage) ? (string) $depositAdminPage : 'balances';
$adminPageTitle = sr_t('deposit::ui.deposit.2a642cec');
if ($depositAdminPage === 'transactions') {
    $adminPageTitle = sr_t('deposit::ui.deposit.93f727b8');
}
$accountLookupFilter = isset($accountLookupFilter) && is_array($accountLookupFilter) ? $accountLookupFilter : ['field' => 'all', 'keyword' => (string) ($accountIdentifierFilter ?? '')];
$balanceSort = isset($balanceSort) && is_array($balanceSort) ? $balanceSort : sr_admin_asset_balance_default_sort();
$transactionSort = isset($transactionSort) && is_array($transactionSort) ? $transactionSort : sr_admin_asset_transaction_default_sort();
$depositReferenceTypeOptions = [
    '' => sr_t('deposit::ui.text.72ea3d64'),
    'order' => sr_t('deposit::ui.text.d64a64f0'),
    'payment' => sr_t('deposit::ui.text.8d4f3299'),
    'refund' => sr_t('deposit::ui.text.edda9108'),
    'support_ticket' => sr_t('deposit::ui.text.9ce226a0'),
    'event' => sr_t('deposit::ui.text.46b289bb'),
    'migration' => sr_t('deposit::ui.text.2e52928e'),
];
$depositAdjustTransactionTypes = array_values(array_filter($allowedTransactionTypes, static function (string $type): bool {
    return $type !== 'refund';
}));
$depositHelpOpenLabel = sr_t('deposit::help.open');
$depositHelpButtonHtml = static function (string $label, string $modalId) use ($depositHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $depositHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$depositHelpBodyHtml = static function (array $bodyKeys): string {
    $html = '';
    foreach ($bodyKeys as $bodyKey) {
        $html .= '<p>' . sr_e(sr_t((string) $bodyKey)) . '</p>';
    }

    return $html;
};
$depositHelp = [
    'member_hash' => [
        'id' => 'deposit-help-member-hash-modal',
        'title' => sr_t('deposit::help.member_hash.title'),
        'body_html' => $depositHelpBodyHtml([
            'deposit::help.member_hash.body.1',
            'deposit::help.member_hash.body.2',
        ]),
    ],
    'transaction_type' => [
        'id' => 'deposit-help-transaction-type-modal',
        'title' => sr_t('deposit::help.transaction_type.title'),
        'body_html' => $depositHelpBodyHtml([
            'deposit::help.transaction_type.body.1',
            'deposit::help.transaction_type.body.2',
        ]),
    ],
    'amount' => [
        'id' => 'deposit-help-amount-modal',
        'title' => sr_t('deposit::help.amount.title'),
        'body_html' => $depositHelpBodyHtml([
            'deposit::help.amount.body.1',
            'deposit::help.amount.body.2',
        ]),
    ],
    'reason' => [
        'id' => 'deposit-help-reason-modal',
        'title' => sr_t('deposit::help.reason.title'),
        'body_html' => $depositHelpBodyHtml([
            'deposit::help.reason.body.1',
            'deposit::help.reason.body.2',
        ]),
    ],
    'reference_type' => [
        'id' => 'deposit-help-reference-type-modal',
        'title' => sr_t('deposit::help.reference_type.title'),
        'body_html' => $depositHelpBodyHtml([
            'deposit::help.reference_type.body.1',
            'deposit::help.reference_type.body.2',
        ]),
    ],
    'reference_id' => [
        'id' => 'deposit-help-reference-id-modal',
        'title' => sr_t('deposit::help.reference_id.title'),
        'body_html' => $depositHelpBodyHtml([
            'deposit::help.reference_id.body.1',
            'deposit::help.reference_id.body.2',
        ]),
    ],
    'refund_amount' => [
        'id' => 'deposit-help-refund-amount-modal',
        'title' => sr_t('deposit::help.refund_amount.title'),
        'body_html' => $depositHelpBodyHtml([
            'deposit::help.refund_amount.body.1',
            'deposit::help.refund_amount.body.2',
        ]),
    ],
    'refund_reason' => [
        'id' => 'deposit-help-refund-reason-modal',
        'title' => sr_t('deposit::help.refund_reason.title'),
        'body_html' => $depositHelpBodyHtml([
            'deposit::help.refund_reason.body.1',
            'deposit::help.refund_reason.body.2',
        ]),
    ],
];
$depositAdjustModalAccounts = [];
if ($depositAdminPage === 'balances') {
    $depositAdjustModalSeen = [];
    $depositAdjustModalAccounts[] = [
        'id' => 0,
        'account_public_hash' => '',
        'display_name' => '',
        'email' => '',
        'balance' => 0,
    ];
    if (isset($selectedAccount) && is_array($selectedAccount)) {
        $depositSelectedHash = (string) ($selectedAccount['account_public_hash'] ?? '');
        if ($depositSelectedHash !== '') {
            $depositAdjustModalAccounts[] = [
                'id' => (int) ($selectedAccount['id'] ?? 0),
                'account_public_hash' => $depositSelectedHash,
                'display_name' => (string) ($selectedAccount['display_name'] ?? ''),
                'email' => (string) ($selectedAccount['email'] ?? ''),
                'balance' => (int) ($selectedBalance ?? 0),
            ];
            $depositAdjustModalSeen[$depositSelectedHash] = true;
        }
    }
    if (isset($balances) && is_array($balances)) {
        foreach ($balances as $depositBalanceModalAccount) {
            if (!is_array($depositBalanceModalAccount)) {
                continue;
            }
            $depositBalanceHash = (string) ($depositBalanceModalAccount['account_public_hash'] ?? '');
            if ($depositBalanceHash === '' || isset($depositAdjustModalSeen[$depositBalanceHash])) {
                continue;
            }
            $depositAdjustModalAccounts[] = [
                'id' => (int) ($depositBalanceModalAccount['account_id'] ?? 0),
                'account_public_hash' => $depositBalanceHash,
                'display_name' => (string) ($depositBalanceModalAccount['display_name'] ?? ''),
                'email' => (string) ($depositBalanceModalAccount['email'] ?? ''),
                'balance' => (int) ($depositBalanceModalAccount['balance'] ?? 0),
            ];
            $depositAdjustModalSeen[$depositBalanceHash] = true;
        }
    }
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url($depositAdminPage === 'transactions' ? '/admin/deposits/transactions' : '/admin/deposits/balances')); ?>" class="table-filtering-form table-filtering table-filtering-plain admin-asset-member-filter ui-form-theme">
    <div class="table-filtering-fields admin-asset-member-search-grid">
        <div class="table-filtering-field">
            <label for="deposit-member-search-field" class="table-filtering-label">검색조건</label>
            <select name="field" id="deposit-member-search-field" class="form-select table-filtering-input">
                <?php foreach (['all' => sr_t('deposit::ui.all.a4b69faf'), 'hash' => sr_t('deposit::ui.text.93971787'), 'email' => sr_t('deposit::ui.email.3b7dbc4c'), 'login_id' => sr_t('deposit::ui.login.0cdb28b5'), 'name' => sr_t('deposit::ui.name.253d1510')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($accountLookupFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="table-filtering-field admin-asset-member-filter-keyword">
            <label for="deposit-member-search-keyword" class="table-filtering-label"><?php echo sr_e(sr_t('deposit::ui.search.bda397fc')); ?></label>
            <input type="text" id="deposit-member-search-keyword" name="q" value="<?php echo sr_e((string) ($accountLookupFilter['keyword'] ?? '')); ?>" class="form-input table-filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('deposit::ui.email.login.name.c26ba637')); ?>">
        </div>
        <button type="submit" class="btn btn-solid-primary table-filtering-submit"><?php echo sr_e(sr_t('deposit::ui.search.4b8d541e')); ?></button>
    </div>
</form>

<?php if (is_array($selectedAccount)) { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/deposits/balances?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('deposit::ui.text.7bc75ef8')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/deposits/transactions?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('deposit::ui.text.87e27fc1')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('deposit::ui.member.e335b899')); ?> <strong><?php echo sr_e(sr_admin_member_display_name_preview($selectedAccount)); ?></strong></span>
            <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($selectedAccount)); ?></span>
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('deposit::ui.text.b099377c')); ?> <strong><?php echo sr_e(number_format((int) $selectedBalance)); ?> <?php echo sr_e(sr_t('deposit::ui.text.c19fd678')); ?></strong></span>
        </div>
    </div>
<?php } elseif ((string) ($accountLookupFilter['keyword'] ?? '') !== '') { ?>
    <p class="admin-empty-state"><?php echo sr_e(sr_t('deposit::ui.member.8f3d9a93')); ?></p>
<?php } ?>

<?php if ($depositAdminPage === 'transactions') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header"><h2 class="card-title"><?php echo sr_e(sr_t('deposit::ui.text.ce41e3f6')); ?></h2></div>
        <div class="admin-list-summary-row">
            <?php if (empty($transactionSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="예치금 거래 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($transactionPagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table admin-asset-transaction-table">
            <thead class="ui-table-head">
                <tr>
                    <th>회원 정보</th>
                    <th<?php echo sr_admin_sort_aria('member', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('deposit::ui.member.e335b899'), 'member', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('transaction_type', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('deposit::ui.text.5cf2792b'), 'transaction_type', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('amount', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('deposit::ui.text.5c705e1a'), 'amount', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('balance_after', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('deposit::ui.text.87f9c4c8'), 'balance_after', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('reason', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('deposit::ui.text.ab9442a2'), 'reason', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('reference_type', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('deposit::ui.text.fbc8ad58'), 'reference_type', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('created_at', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('deposit::ui.text.5efd3ddd'), 'created_at', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('deposit::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state"><?php echo sr_e(sr_t('deposit::ui.deposit.730694c5')); ?></td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($transactions as $transaction) { ?>
                        <tr>
                            <td><a href="<?php echo sr_e(sr_url('/admin/members/edit?id=' . rawurlencode((string) $transaction['account_id']))); ?>" class="btn btn-sm btn-solid-light">회원 정보</a></td>
                            <td><?php echo sr_e(sr_admin_member_display_name_preview($transaction)); ?><br><?php echo sr_e(sr_admin_member_email_display($transaction)); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $transaction['transaction_type'], 'transaction_type')); ?></td>
                            <td><?php echo sr_e(number_format((int) $transaction['amount'])); ?> <?php echo sr_e(sr_t('deposit::ui.text.c19fd678')); ?></td>
                            <td><?php echo sr_e(number_format((int) $transaction['balance_after'])); ?> <?php echo sr_e(sr_t('deposit::ui.text.c19fd678')); ?></td>
                            <td><?php echo sr_e((string) $transaction['reason']); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $transaction['reference_type'], 'reference_type')); ?></td>
                            <td><?php echo sr_e((string) $transaction['created_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <?php if ((int) ($transaction['amount'] ?? 0) < 0 && (string) ($transaction['transaction_type'] ?? '') !== 'refund') { ?>
                                        <?php $depositTransactionRefundModalId = 'deposit-refund-modal-' . (int) ($transaction['id'] ?? 0); ?>
                                        <a href="<?php echo sr_e(sr_url('/admin/deposits/transactions?account_identifier=' . rawurlencode((string) $transaction['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($depositTransactionRefundModalId); ?>" data-overlay="#<?php echo sr_e($depositTransactionRefundModalId); ?>"><?php echo sr_e(sr_t('deposit::ui.text.edda9108')); ?></a>
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
    <?php echo sr_admin_pagination_html($transactionPagination, '예치금 거래 목록 페이지'); ?>
<?php } else { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('deposit::ui.text.b62aead1')); ?></h2>
            <?php $depositHeaderAdjustModalId = is_array($selectedAccount) ? 'deposit-adjust-modal-' . (int) ($selectedAccount['id'] ?? 0) : 'deposit-adjust-modal-0'; ?>
            <?php $depositHeaderAdjustUrl = is_array($selectedAccount) ? '/admin/deposits/balances?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']) : '/admin/deposits/balances'; ?>
            <a href="<?php echo sr_e(sr_url($depositHeaderAdjustUrl)); ?>" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($depositHeaderAdjustModalId); ?>" data-overlay="#<?php echo sr_e($depositHeaderAdjustModalId); ?>"><?php echo sr_e(sr_t('deposit::ui.text.7535b737')); ?></a>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($balanceSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="예치금 잔액 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($balancePagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table admin-asset-balance-table">
            <thead class="ui-table-head">
                <tr>
                    <th>회원 정보</th>
                    <th<?php echo sr_admin_sort_aria('member', $balanceSort); ?>><?php echo sr_admin_sort_header_html(sr_t('deposit::ui.member.e335b899'), 'member', $balanceSort, sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $balanceSort); ?>><?php echo sr_admin_sort_header_html(sr_t('deposit::ui.status.e10195a1'), 'status', $balanceSort, sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('balance', $balanceSort); ?>><?php echo sr_admin_sort_header_html(sr_t('deposit::ui.text.b099377c'), 'balance', $balanceSort, sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('updated_at', $balanceSort); ?>><?php echo sr_admin_sort_header_html(sr_t('deposit::ui.edit.d3a98476'), 'updated_at', $balanceSort, sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort()); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('deposit::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($balances === []) { ?>
                    <tr>
                        <td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('deposit::ui.deposit.6d4d4d20')); ?></td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($balances as $balance) { ?>
                        <tr>
                            <td><a href="<?php echo sr_e(sr_url('/admin/members/edit?id=' . rawurlencode((string) $balance['account_id']))); ?>" class="btn btn-sm btn-solid-light">회원 정보</a></td>
                            <td><?php echo sr_e(sr_admin_member_display_name_preview($balance)); ?><br><?php echo sr_e(sr_admin_member_email_display($balance)); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $balance['status'], 'member_status')); ?></td>
                            <td><?php echo sr_e(number_format((int) $balance['balance'])); ?> <?php echo sr_e(sr_t('deposit::ui.text.c19fd678')); ?></td>
                            <td><?php echo sr_e((string) $balance['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/deposits/transactions?account_identifier=' . rawurlencode((string) $balance['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('deposit::ui.text.754ef98b')); ?></a>
                                    <?php $depositBalanceAdjustModalId = 'deposit-adjust-modal-' . (int) ($balance['account_id'] ?? 0); ?>
                                    <a href="<?php echo sr_e(sr_url('/admin/deposits/balances?account_identifier=' . rawurlencode((string) $balance['account_public_hash']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('deposit::ui.text.b9d9b240')); ?>" title="<?php echo sr_e(sr_t('deposit::ui.text.b9d9b240')); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($depositBalanceAdjustModalId); ?>" data-overlay="#<?php echo sr_e($depositBalanceAdjustModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php echo sr_admin_pagination_html($balancePagination, '예치금 잔액 목록 페이지'); ?>
<?php } ?>

<?php if ($depositAdminPage === 'balances' && $depositAdjustModalAccounts !== []) { ?>
    <?php foreach ($depositAdjustModalAccounts as $depositAdjustModalAccount) { ?>
        <?php
        $depositAdjustModalId = 'deposit-adjust-modal-' . (int) $depositAdjustModalAccount['id'];
        $depositAdjustFieldPrefix = 'deposit_adjust_' . (int) $depositAdjustModalAccount['id'];
        $depositAdjustAccountInputId = $depositAdjustFieldPrefix . '_account_identifier';
        $depositAdjustReferenceTypeInputId = $depositAdjustFieldPrefix . '_reference_type';
        $depositAdjustReferenceIdInputId = $depositAdjustFieldPrefix . '_reference_id';
        $depositAdjustMemberLookupModalId = $depositAdjustFieldPrefix . '_member_lookup_modal';
        $depositAdjustReferenceLookupModalId = $depositAdjustFieldPrefix . '_reference_lookup_modal';
        ?>
        <div id="<?php echo sr_e($depositAdjustModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($depositAdjustFieldPrefix); ?>_title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/deposits/balances' . ((string) $depositAdjustModalAccount['account_public_hash'] !== '' ? '?account_identifier=' . rawurlencode((string) $depositAdjustModalAccount['account_public_hash']) : ''))); ?>" class="modal-content ui-form-theme" data-admin-reference-pair>
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($depositAdjustFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e(sr_t('deposit::ui.deposit.87b00814')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('deposit::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($depositAdjustModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <?php if ((string) $depositAdjustModalAccount['account_public_hash'] !== '') { ?>
                            <input type="hidden" name="account_identifier" value="<?php echo sr_e((string) $depositAdjustModalAccount['account_public_hash']); ?>">
                            <div class="admin-summary-stats">
                                <span class="admin-summary-meta"><?php echo sr_e(sr_t('deposit::ui.member.e335b899')); ?> <strong><?php echo sr_e(sr_admin_member_display_name_preview($depositAdjustModalAccount)); ?></strong></span>
                                <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($depositAdjustModalAccount)); ?></span>
                                <span class="admin-summary-meta"><?php echo sr_e(sr_t('deposit::ui.text.4993967a')); ?> <strong><?php echo sr_e(number_format((int) $depositAdjustModalAccount['balance'])); ?> <?php echo sr_e(sr_t('deposit::ui.text.c19fd678')); ?></strong></span>
                            </div>
                        <?php } else { ?>
                            <div class="admin-form-row">
                                <?php echo sr_admin_form_label_help_html($depositAdjustAccountInputId, sr_t('deposit::ui.member.900e04a5'), $depositHelp['member_hash']['id'], $depositHelpOpenLabel, true); ?>
                                <div class="admin-form-field">
                                    <div class="admin-lookup-control">
                                        <input id="<?php echo sr_e($depositAdjustAccountInputId); ?>" type="text" name="account_identifier" value="<?php echo sr_e($accountIdentifierFilter); ?>" class="form-input" maxlength="80" required data-overlay-focus>
                                        <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($depositAdjustMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($depositAdjustMemberLookupModalId); ?>" data-admin-member-lookup-open data-target="#<?php echo sr_e($depositAdjustAccountInputId); ?>"><?php echo sr_e(sr_t('deposit::ui.member.search.f7a330b0')); ?></button>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="admin-form-row">
                            <?php echo sr_admin_form_label_help_html($depositAdjustFieldPrefix . '_transaction_type', sr_t('deposit::ui.text.3a7bc5ac'), $depositHelp['transaction_type']['id'], $depositHelpOpenLabel, true); ?>
                            <div class="admin-form-field">
                                <select id="<?php echo sr_e($depositAdjustFieldPrefix); ?>_transaction_type" name="transaction_type" class="form-select">
                                    <?php foreach ($depositAdjustTransactionTypes as $type) { ?>
                                        <option value="<?php echo sr_e($type); ?>"><?php echo sr_e(sr_admin_code_label($type, 'transaction_type')); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <?php echo sr_admin_form_label_help_html($depositAdjustFieldPrefix . '_amount', sr_t('deposit::ui.text.5c705e1a'), $depositHelp['amount']['id'], $depositHelpOpenLabel, true); ?>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($depositAdjustFieldPrefix); ?>_amount" type="number" name="amount" step="1" required class="form-input" data-overlay-focus>
                                <p class="admin-form-help"><?php echo sr_e(sr_t('deposit::ui.active.2db1fd9d')); ?></p>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <?php echo sr_admin_form_label_help_html($depositAdjustFieldPrefix . '_reason', sr_t('deposit::ui.text.ab9442a2'), $depositHelp['reason']['id'], $depositHelpOpenLabel, true); ?>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($depositAdjustFieldPrefix); ?>_reason" type="text" name="reason" maxlength="255" required class="form-input form-control-full">
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($depositAdjustFieldPrefix); ?>_approval_account_identifier">대액 승인자</label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($depositAdjustFieldPrefix); ?>_approval_account_identifier" type="text" name="approval_account_identifier" maxlength="80" class="form-input">
                                <p class="admin-form-help">1,000,000 초과 조정에는 처리자와 다른 편집 권한 보유 승인자의 회원 식별자가 필요합니다.</p>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($depositAdjustFieldPrefix); ?>_approval_note">승인 사유</label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($depositAdjustFieldPrefix); ?>_approval_note" type="text" name="approval_note" maxlength="255" class="form-input form-control-full">
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <div class="form-label admin-form-label-help"><?php echo $depositHelpButtonHtml(sr_t('deposit::ui.text.200e7df1'), $depositHelp['reference_type']['id']); ?><label for="<?php echo sr_e($depositAdjustReferenceTypeInputId); ?>"><?php echo sr_e(sr_t('deposit::ui.text.200e7df1')); ?> <span class="sr-required-label" data-admin-reference-type-required hidden><?php echo sr_e(sr_t('deposit::ui.required.1f227c67')); ?></span></label></div>
                            <div class="admin-form-field">
                                <select id="<?php echo sr_e($depositAdjustReferenceTypeInputId); ?>" name="reference_type" class="form-select" data-admin-reference-type>
                                    <?php foreach ($depositReferenceTypeOptions as $referenceTypeValue => $referenceTypeLabel) { ?>
                                        <option value="<?php echo sr_e($referenceTypeValue); ?>"><?php echo sr_e($referenceTypeLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <div class="form-label admin-form-label-help"><?php echo $depositHelpButtonHtml(sr_t('deposit::ui.id.e89e337e'), $depositHelp['reference_id']['id']); ?><label for="<?php echo sr_e($depositAdjustReferenceIdInputId); ?>"><?php echo sr_e(sr_t('deposit::ui.id.e89e337e')); ?> <span class="sr-required-label" data-admin-reference-id-required hidden><?php echo sr_e(sr_t('deposit::ui.required.1f227c67')); ?></span></label></div>
                            <div class="admin-form-field">
                                <div class="admin-lookup-control">
                                    <input id="<?php echo sr_e($depositAdjustReferenceIdInputId); ?>" type="text" name="reference_id" maxlength="120" class="form-input" data-admin-reference-id>
                                    <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($depositAdjustReferenceLookupModalId); ?>" data-overlay="#<?php echo sr_e($depositAdjustReferenceLookupModalId); ?>" data-admin-reference-lookup-open data-type-target="#<?php echo sr_e($depositAdjustReferenceTypeInputId); ?>" data-id-target="#<?php echo sr_e($depositAdjustReferenceIdInputId); ?>"><?php echo sr_e(sr_t('deposit::ui.search.3acacadd')); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($depositAdjustModalId); ?>"><?php echo sr_e(sr_t('deposit::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('deposit::ui.save.5fb92622')); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $assetAdjustLookup = [
            'field_prefix' => $depositAdjustFieldPrefix,
            'member_input_id' => (string) $depositAdjustModalAccount['account_public_hash'] === '' ? $depositAdjustAccountInputId : '',
            'reference_type_id' => $depositAdjustReferenceTypeInputId,
            'reference_id_id' => $depositAdjustReferenceIdInputId,
            'return_overlay_id' => $depositAdjustModalId,
            'member_search_url' => sr_url('/admin/members/search'),
            'reference_search_url' => sr_url('/admin/deposits/reference-search'),
            'reference_options' => $depositReferenceTypeOptions,
        ];
        include SR_ROOT . '/modules/admin/views/asset-adjust-lookup-modals.php';
        ?>
    <?php } ?>
<?php } ?>

<?php if ($depositAdminPage === 'transactions' && $transactions !== []) { ?>
    <?php foreach ($transactions as $depositRefundTransaction) { ?>
        <?php if ((int) ($depositRefundTransaction['amount'] ?? 0) >= 0 || (string) ($depositRefundTransaction['transaction_type'] ?? '') === 'refund') { ?>
            <?php continue; ?>
        <?php } ?>
        <?php
        $depositRefundTransactionId = (int) ($depositRefundTransaction['id'] ?? 0);
        $depositRefundModalId = 'deposit-refund-modal-' . $depositRefundTransactionId;
        $depositRefundFieldPrefix = 'deposit_refund_' . $depositRefundTransactionId;
        $depositRefundReferenceId = 'deposit_transaction:' . $depositRefundTransactionId;
        $depositRefundDefaultAmount = abs((int) ($depositRefundTransaction['amount'] ?? 0));
        ?>
        <div id="<?php echo sr_e($depositRefundModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($depositRefundFieldPrefix); ?>_title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/deposits/transactions?account_identifier=' . rawurlencode((string) $depositRefundTransaction['account_public_hash']))); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($depositRefundFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e(sr_t('deposit::ui.deposit.6398981e')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('deposit::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($depositRefundModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="transaction_type" value="refund">
                        <input type="hidden" name="reference_type" value="refund">
                        <input type="hidden" name="account_identifier" value="<?php echo sr_e((string) $depositRefundTransaction['account_public_hash']); ?>">
                        <input type="hidden" name="reference_id" value="<?php echo sr_e($depositRefundReferenceId); ?>">
                        <div class="admin-summary-stats">
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('deposit::ui.member.e335b899')); ?> <strong><?php echo sr_e(sr_admin_member_display_name_preview($depositRefundTransaction)); ?></strong></span>
                            <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($depositRefundTransaction)); ?></span>
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('deposit::ui.text.64d5a726')); ?> <strong><?php echo sr_e(number_format((int) $depositRefundTransaction['amount'])); ?> <?php echo sr_e(sr_t('deposit::ui.text.c19fd678')); ?></strong></span>
                        </div>
                        <div class="admin-form-row">
                            <?php echo sr_admin_form_label_help_html($depositRefundFieldPrefix . '_amount', sr_t('deposit::ui.text.a27af6c4'), $depositHelp['refund_amount']['id'], $depositHelpOpenLabel, true); ?>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($depositRefundFieldPrefix); ?>_amount" type="number" name="amount" value="<?php echo sr_e((string) $depositRefundDefaultAmount); ?>" step="1" min="1" required class="form-input" data-overlay-focus>
                                <p class="admin-form-help"><?php echo sr_e(sr_t('deposit::ui.deposit.save.4f951908')); ?></p>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <?php echo sr_admin_form_label_help_html($depositRefundFieldPrefix . '_reason', sr_t('deposit::ui.text.ab9442a2'), $depositHelp['refund_reason']['id'], $depositHelpOpenLabel, true); ?>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($depositRefundFieldPrefix); ?>_reason" type="text" name="reason" value="<?php echo sr_e(sr_t('deposit::ui.text.5b471750') . (string) $depositRefundTransactionId . sr_t('deposit::ui.text.0a4d3a11')); ?>" maxlength="255" required class="form-input form-control-full">
                                <p class="admin-form-help"><?php echo sr_e(sr_t('deposit::ui.id.0b5dbcb4')); ?> <?php echo sr_e($depositRefundReferenceId); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($depositRefundModalId); ?>"><?php echo sr_e(sr_t('deposit::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('deposit::ui.save.ae2ff036')); ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<?php foreach ($depositHelp as $depositHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $depositHelpModal['id'], (string) $depositHelpModal['title'], (string) $depositHelpModal['body_html']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
