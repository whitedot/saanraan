<?php

$rewardAdminPage = isset($rewardAdminPage) ? (string) $rewardAdminPage : 'balances';
$adminPageTitle = sr_t('reward::ui.text.abe10d3e');
if ($rewardAdminPage === 'transactions') {
    $adminPageTitle = sr_t('reward::ui.text.abaae118');
}
$accountLookupFilter = isset($accountLookupFilter) && is_array($accountLookupFilter) ? $accountLookupFilter : ['field' => 'all', 'keyword' => (string) ($accountIdentifierFilter ?? '')];
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, $rewardAdminPage === 'transactions' ? '/admin/rewards/transactions' : '/admin/rewards/balances');
$balanceSort = isset($balanceSort) && is_array($balanceSort) ? $balanceSort : sr_admin_asset_balance_default_sort();
$transactionSort = isset($transactionSort) && is_array($transactionSort) ? $transactionSort : sr_admin_asset_transaction_default_sort();
$rewardReclaimRemainingAmounts = isset($rewardReclaimRemainingAmounts) && is_array($rewardReclaimRemainingAmounts) ? $rewardReclaimRemainingAmounts : [];
$rewardReferenceTypeOptions = [
    '' => sr_t('reward::ui.text.72ea3d64'),
    'order' => sr_t('reward::ui.text.d64a64f0'),
    'payment' => sr_t('reward::ui.text.8d4f3299'),
    'refund' => sr_t('reward::ui.text.edda9108'),
    'reclaim' => sr_t('reward::ui.text.f7cd7185'),
    'support_ticket' => sr_t('reward::ui.text.9ce226a0'),
    'event' => sr_t('reward::ui.text.46b289bb'),
    'migration' => sr_t('reward::ui.text.2e52928e'),
];
$rewardAdjustTransactionTypes = array_values(array_filter($allowedTransactionTypes, static function (string $type): bool {
    return !in_array($type, ['refund', 'reclaim'], true);
}));
$rewardAdjustReferenceTypeOptions = array_filter($rewardReferenceTypeOptions, static function (string $referenceType): bool {
    return $referenceType !== 'reclaim';
}, ARRAY_FILTER_USE_KEY);
$rewardHelpOpenLabel = sr_t('reward::help.open');
$rewardHelpButtonHtml = static function (string $label, string $modalId) use ($rewardHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $rewardHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$rewardHelpBodyHtml = static function (array $bodyKeys): string {
    $html = '';
    foreach ($bodyKeys as $bodyKey) {
        $html .= '<p>' . sr_e(sr_t((string) $bodyKey)) . '</p>';
    }

    return $html;
};
$rewardHelp = [
    'member_hash' => [
        'id' => 'reward-help-member-hash-modal',
        'title' => sr_t('reward::help.member_hash.title'),
        'body_html' => $rewardHelpBodyHtml([
            'reward::help.member_hash.body.1',
            'reward::help.member_hash.body.2',
        ]),
    ],
    'transaction_type' => [
        'id' => 'reward-help-transaction-type-modal',
        'title' => sr_t('reward::help.transaction_type.title'),
        'body_html' => $rewardHelpBodyHtml([
            'reward::help.transaction_type.body.1',
            'reward::help.transaction_type.body.2',
        ]),
    ],
    'amount' => [
        'id' => 'reward-help-amount-modal',
        'title' => sr_t('reward::help.amount.title'),
        'body_html' => $rewardHelpBodyHtml([
            'reward::help.amount.body.1',
            'reward::help.amount.body.2',
        ]),
    ],
    'reason' => [
        'id' => 'reward-help-reason-modal',
        'title' => sr_t('reward::help.reason.title'),
        'body_html' => $rewardHelpBodyHtml([
            'reward::help.reason.body.1',
            'reward::help.reason.body.2',
        ]),
    ],
    'reference_type' => [
        'id' => 'reward-help-reference-type-modal',
        'title' => sr_t('reward::help.reference_type.title'),
        'body_html' => $rewardHelpBodyHtml([
            'reward::help.reference_type.body.1',
            'reward::help.reference_type.body.2',
        ]),
    ],
    'reference_id' => [
        'id' => 'reward-help-reference-id-modal',
        'title' => sr_t('reward::help.reference_id.title'),
        'body_html' => $rewardHelpBodyHtml([
            'reward::help.reference_id.body.1',
            'reward::help.reference_id.body.2',
        ]),
    ],
    'refund_amount' => [
        'id' => 'reward-help-refund-amount-modal',
        'title' => sr_t('reward::help.refund_amount.title'),
        'body_html' => $rewardHelpBodyHtml([
            'reward::help.refund_amount.body.1',
            'reward::help.refund_amount.body.2',
        ]),
    ],
    'refund_reason' => [
        'id' => 'reward-help-refund-reason-modal',
        'title' => sr_t('reward::help.refund_reason.title'),
        'body_html' => $rewardHelpBodyHtml([
            'reward::help.refund_reason.body.1',
            'reward::help.refund_reason.body.2',
        ]),
    ],
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

<form method="get" action="<?php echo sr_e(sr_url($rewardAdminPage === 'transactions' ? '/admin/rewards/transactions' : '/admin/rewards/balances')); ?>" class="filtering-form filtering filtering-plain admin-asset-member-filter ui-form-theme">
    <div class="filtering-fields admin-asset-member-search-grid">
        <div class="filtering-field">
            <label for="reward-member-search-field" class="filtering-label">검색조건</label>
            <select name="field" id="reward-member-search-field" class="form-select filtering-input">
                <?php foreach (['all' => sr_t('reward::ui.all.a4b69faf'), 'hash' => sr_t('reward::ui.text.93971787'), 'email' => sr_t('reward::ui.email.3b7dbc4c'), 'login_id' => sr_t('reward::ui.login.0cdb28b5'), 'name' => sr_t('reward::ui.name.253d1510')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($accountLookupFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="filtering-field admin-asset-member-filter-keyword">
            <label for="reward-member-search-keyword" class="filtering-label"><?php echo sr_e(sr_t('reward::ui.search.bda397fc')); ?></label>
            <input type="text" id="reward-member-search-keyword" name="q" value="<?php echo sr_e((string) ($accountLookupFilter['keyword'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('reward::ui.email.login.name.c26ba637')); ?>">
        </div>
        <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('reward::ui.search.4b8d541e')); ?></button>
    </div>
</form>

<?php if (is_array($selectedAccount)) { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/rewards/balances?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('reward::ui.text.7bc75ef8')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/rewards/transactions?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('reward::ui.text.87e27fc1')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('reward::ui.member.e335b899')); ?> <strong><?php echo sr_e(sr_admin_member_display_name_preview($selectedAccount)); ?></strong></span>
            <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($selectedAccount)); ?></span>
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('reward::ui.text.b099377c')); ?> <strong><?php echo sr_e(number_format((int) $selectedBalance)); ?> <?php echo sr_e(sr_t('reward::ui.text.c19fd678')); ?></strong></span>
        </div>
    </div>
<?php } elseif ((string) ($accountLookupFilter['keyword'] ?? '') !== '') { ?>
    <p class="admin-empty-state"><?php echo sr_e(sr_t('reward::ui.member.8f3d9a93')); ?></p>
<?php } ?>

<?php if ($rewardAdminPage === 'transactions') { ?>
    <section class="card admin-list-card admin-list-form">
        <div class="card-header"><h2 class="card-title"><?php echo sr_e(sr_t('reward::ui.text.ce41e3f6')); ?></h2></div>
        <div class="admin-list-summary-row">
            <?php if (empty($transactionSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="적립금 거래 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($transactionPagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table table-list admin-asset-transaction-table">
            <thead>
                <tr>
                    <th>회원 정보</th>
                    <th<?php echo sr_admin_sort_aria('member', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('reward::ui.member.e335b899'), 'member', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('transaction_type', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('reward::ui.text.5cf2792b'), 'transaction_type', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('amount', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('reward::ui.text.5c705e1a'), 'amount', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('balance_after', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('reward::ui.text.87f9c4c8'), 'balance_after', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('reason', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('reward::ui.text.ab9442a2'), 'reason', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('reference_type', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('reward::ui.text.fbc8ad58'), 'reference_type', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('created_at', $transactionSort); ?>><?php echo sr_admin_sort_header_html(sr_t('reward::ui.text.5efd3ddd'), 'created_at', $transactionSort, sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort()); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('reward::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state"><?php echo sr_e(sr_t('reward::ui.text.b1a1ff6f')); ?></td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($transactions as $transaction) { ?>
                        <tr>
                            <td><a href="<?php echo sr_e(sr_url('/admin/members/edit?id=' . rawurlencode((string) $transaction['account_id']))); ?>" class="btn btn-sm btn-solid-light">회원 정보</a></td>
                            <td><?php echo sr_e(sr_admin_member_display_name_preview($transaction)); ?><br><?php echo sr_e(sr_admin_member_email_display($transaction)); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $transaction['transaction_type'], 'transaction_type')); ?></td>
                            <td><?php echo sr_e(number_format((int) $transaction['amount'])); ?> <?php echo sr_e(sr_t('reward::ui.text.c19fd678')); ?></td>
                            <td><?php echo sr_e(number_format((int) $transaction['balance_after'])); ?> <?php echo sr_e(sr_t('reward::ui.text.c19fd678')); ?></td>
                            <td><?php echo sr_e((string) $transaction['reason']); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $transaction['reference_type'], 'reference_type')); ?></td>
                            <td><?php echo sr_reward_time_html((string) $transaction['created_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <?php if ((int) ($transaction['amount'] ?? 0) < 0 && !in_array((string) ($transaction['transaction_type'] ?? ''), ['refund', 'reclaim'], true)) { ?>
                                        <?php $rewardTransactionRefundModalId = 'reward-refund-modal-' . (int) ($transaction['id'] ?? 0); ?>
                                        <a href="<?php echo sr_e(sr_url('/admin/rewards/transactions?account_identifier=' . rawurlencode((string) $transaction['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($rewardTransactionRefundModalId); ?>" data-overlay="#<?php echo sr_e($rewardTransactionRefundModalId); ?>"><?php echo sr_e(sr_t('reward::ui.text.edda9108')); ?></a>
                                    <?php } ?>
                                    <?php $rewardTransactionReclaimRemaining = (int) ($rewardReclaimRemainingAmounts[(int) ($transaction['id'] ?? 0)] ?? 0); ?>
                                    <?php if ($rewardTransactionReclaimRemaining > 0) { ?>
                                        <?php $rewardTransactionReclaimModalId = 'reward-reclaim-modal-' . (int) ($transaction['id'] ?? 0); ?>
                                        <a href="<?php echo sr_e(sr_url('/admin/rewards/transactions?account_identifier=' . rawurlencode((string) $transaction['account_public_hash']))); ?>" class="btn btn-sm btn-outline-danger" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($rewardTransactionReclaimModalId); ?>" data-overlay="#<?php echo sr_e($rewardTransactionReclaimModalId); ?>"><?php echo sr_e(sr_t('reward::ui.text.f7cd7185')); ?></a>
                                    <?php } elseif (in_array((string) ($transaction['transaction_type'] ?? ''), ['refund', 'reclaim'], true)) { ?>
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
        <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> <?php echo sr_e(sr_t('reward::ui.text.b9d9b240')); ?></span>
        </div>
	    </section>
    <?php echo sr_admin_pagination_html($transactionPagination, '적립금 거래 목록 페이지'); ?>
<?php } else { ?>
    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('reward::ui.text.b62aead1')); ?></h2>
            <?php $rewardHeaderAdjustModalId = is_array($selectedAccount) ? 'reward-adjust-modal-' . (int) ($selectedAccount['id'] ?? 0) : 'reward-adjust-modal-0'; ?>
            <?php $rewardHeaderAdjustUrl = is_array($selectedAccount) ? '/admin/rewards/balances?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']) : '/admin/rewards/balances'; ?>
            <a href="<?php echo sr_e(sr_url($rewardHeaderAdjustUrl)); ?>" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($rewardHeaderAdjustModalId); ?>" data-overlay="#<?php echo sr_e($rewardHeaderAdjustModalId); ?>"><?php echo sr_e(sr_t('reward::ui.text.7535b737')); ?></a>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($balanceSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="적립금 잔액 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($balancePagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table table-list admin-asset-balance-table">
            <thead>
                <tr>
                    <th<?php echo sr_admin_sort_aria('member', $balanceSort); ?>><?php echo sr_admin_sort_header_html(sr_t('reward::ui.member.e335b899'), 'member', $balanceSort, sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $balanceSort); ?>><?php echo sr_admin_sort_header_html(sr_t('reward::ui.status.e10195a1'), 'status', $balanceSort, sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('balance', $balanceSort); ?>><?php echo sr_admin_sort_header_html(sr_t('reward::ui.text.b099377c'), 'balance', $balanceSort, sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('updated_at', $balanceSort); ?>><?php echo sr_admin_sort_header_html(sr_t('reward::ui.edit.d3a98476'), 'updated_at', $balanceSort, sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort()); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('reward::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($balances === []) { ?>
                    <tr>
                        <td colspan="5" class="admin-empty-state"><?php echo sr_e(sr_t('reward::ui.text.f99f4979')); ?></td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($balances as $balance) { ?>
                        <tr>
                            <td><?php echo sr_e(sr_admin_member_display_name_preview($balance)); ?><br><?php echo sr_e(sr_admin_member_email_display($balance)); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $balance['status'], 'member_status')); ?></td>
                            <td><?php echo sr_e(number_format((int) $balance['balance'])); ?> <?php echo sr_e(sr_t('reward::ui.text.c19fd678')); ?></td>
                            <td><?php echo sr_reward_time_html((string) $balance['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/members/edit?id=' . rawurlencode((string) $balance['account_id']))); ?>" class="btn btn-sm btn-icon btn-solid-light" target="_blank" rel="noopener noreferrer" aria-label="회원 정보 바로가기" title="회원 정보 바로가기"><?php echo sr_material_icon_html('open_in_new'); ?></a>
                                    <a href="<?php echo sr_e(sr_url('/admin/rewards/transactions?account_identifier=' . rawurlencode((string) $balance['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('reward::ui.text.754ef98b')); ?></a>
                                    <?php $rewardBalanceAdjustModalId = 'reward-adjust-modal-' . (int) ($balance['account_id'] ?? 0); ?>
                                    <a href="<?php echo sr_e(sr_url('/admin/rewards/balances?account_identifier=' . rawurlencode((string) $balance['account_public_hash']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('reward::ui.text.b9d9b240')); ?>" title="<?php echo sr_e(sr_t('reward::ui.text.b9d9b240')); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($rewardBalanceAdjustModalId); ?>" data-overlay="#<?php echo sr_e($rewardBalanceAdjustModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php echo sr_admin_pagination_html($balancePagination, '적립금 잔액 목록 페이지'); ?>
<?php } ?>

<?php if ($rewardAdminPage === 'balances' && $rewardAdjustModalAccounts !== []) { ?>
    <?php foreach ($rewardAdjustModalAccounts as $rewardAdjustModalAccount) { ?>
        <?php
        $rewardAdjustModalId = 'reward-adjust-modal-' . (int) $rewardAdjustModalAccount['id'];
        $rewardAdjustFieldPrefix = 'reward_adjust_' . (int) $rewardAdjustModalAccount['id'];
        $rewardAdjustAccountInputId = $rewardAdjustFieldPrefix . '_account_identifier';
        $rewardAdjustMemberLookupModalId = $rewardAdjustFieldPrefix . '_member_lookup_modal';
        ?>
        <div id="<?php echo sr_e($rewardAdjustModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/rewards/balances' . ((string) $rewardAdjustModalAccount['account_public_hash'] !== '' ? '?account_identifier=' . rawurlencode((string) $rewardAdjustModalAccount['account_public_hash']) : ''))); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e(sr_t('reward::ui.text.3e77739a')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('reward::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($rewardAdjustModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <?php if ((string) $rewardAdjustModalAccount['account_public_hash'] !== '') { ?>
                            <input type="hidden" name="account_identifier" value="<?php echo sr_e((string) $rewardAdjustModalAccount['account_public_hash']); ?>">
                            <div class="admin-summary-stats">
                                <span class="admin-summary-meta"><?php echo sr_e(sr_t('reward::ui.member.e335b899')); ?> <strong><?php echo sr_e(sr_admin_member_display_name_preview($rewardAdjustModalAccount)); ?></strong></span>
                                <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($rewardAdjustModalAccount)); ?></span>
                                <span class="admin-summary-meta"><?php echo sr_e(sr_t('reward::ui.text.4993967a')); ?> <strong><?php echo sr_e(number_format((int) $rewardAdjustModalAccount['balance'])); ?> <?php echo sr_e(sr_t('reward::ui.text.c19fd678')); ?></strong></span>
                            </div>
                        <?php } else { ?>
                            <div class="form-row">
                                <?php echo sr_admin_form_label_help_html($rewardAdjustAccountInputId, sr_t('reward::ui.member.900e04a5'), $rewardHelp['member_hash']['id'], $rewardHelpOpenLabel, true); ?>
                                <div class="form-field">
                                    <div class="admin-lookup-control">
                                        <input id="<?php echo sr_e($rewardAdjustAccountInputId); ?>" type="text" name="account_identifier" value="<?php echo sr_e($accountIdentifierFilter); ?>" class="form-input" maxlength="80" required data-overlay-focus>
                                        <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($rewardAdjustMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($rewardAdjustMemberLookupModalId); ?>" data-admin-member-lookup-open data-target="#<?php echo sr_e($rewardAdjustAccountInputId); ?>"><?php echo sr_e(sr_t('reward::ui.member.search.f7a330b0')); ?></button>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html($rewardAdjustFieldPrefix . '_transaction_type', sr_t('reward::ui.text.3a7bc5ac'), $rewardHelp['transaction_type']['id'], $rewardHelpOpenLabel, true); ?>
                            <div class="form-field">
                                <select id="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_transaction_type" name="transaction_type" class="form-select">
                                    <?php foreach ($rewardAdjustTransactionTypes as $type) { ?>
                                        <option value="<?php echo sr_e($type); ?>"><?php echo sr_e(sr_admin_code_label($type, 'transaction_type')); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html($rewardAdjustFieldPrefix . '_amount', sr_t('reward::ui.text.5c705e1a'), $rewardHelp['amount']['id'], $rewardHelpOpenLabel, true); ?>
                            <div class="form-field">
                                <input id="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_amount" type="number" name="amount" step="1" required class="form-input" data-overlay-focus>
                                <p class="form-help"><?php echo sr_e(sr_t('reward::ui.active.d2de5076')); ?></p>
                            </div>
                        </div>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html($rewardAdjustFieldPrefix . '_reason', sr_t('reward::ui.text.ab9442a2'), $rewardHelp['reason']['id'], $rewardHelpOpenLabel, true); ?>
                            <div class="form-field">
                                <input id="<?php echo sr_e($rewardAdjustFieldPrefix); ?>_reason" type="text" name="reason" maxlength="255" required class="form-input form-control-full">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($rewardAdjustModalId); ?>"><?php echo sr_e(sr_t('reward::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('reward::ui.save.5fb92622')); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $assetAdjustLookup = [
            'field_prefix' => $rewardAdjustFieldPrefix,
            'member_input_id' => (string) $rewardAdjustModalAccount['account_public_hash'] === '' ? $rewardAdjustAccountInputId : '',
            'return_overlay_id' => $rewardAdjustModalId,
            'member_search_url' => sr_url('/admin/members/search'),
        ];
        include SR_ROOT . '/modules/admin/views/asset-adjust-lookup-modals.php';
        ?>
    <?php } ?>
<?php } ?>

<?php if ($rewardAdminPage === 'transactions' && $transactions !== []) { ?>
    <?php foreach ($transactions as $rewardRefundTransaction) { ?>
        <?php if ((int) ($rewardRefundTransaction['amount'] ?? 0) >= 0 || in_array((string) ($rewardRefundTransaction['transaction_type'] ?? ''), ['refund', 'reclaim'], true)) { ?>
            <?php continue; ?>
        <?php } ?>
        <?php
        $rewardRefundTransactionId = (int) ($rewardRefundTransaction['id'] ?? 0);
        $rewardRefundModalId = 'reward-refund-modal-' . $rewardRefundTransactionId;
        $rewardRefundFieldPrefix = 'reward_refund_' . $rewardRefundTransactionId;
        $rewardRefundReferenceId = 'reward_transaction:' . $rewardRefundTransactionId;
        $rewardRefundDefaultAmount = abs((int) ($rewardRefundTransaction['amount'] ?? 0));
        ?>
        <div id="<?php echo sr_e($rewardRefundModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($rewardRefundFieldPrefix); ?>_title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/rewards/transactions?account_identifier=' . rawurlencode((string) $rewardRefundTransaction['account_public_hash']))); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($rewardRefundFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e(sr_t('reward::ui.text.2515f98b')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('reward::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($rewardRefundModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="transaction_type" value="refund">
                        <input type="hidden" name="reference_type" value="refund">
                        <input type="hidden" name="account_identifier" value="<?php echo sr_e((string) $rewardRefundTransaction['account_public_hash']); ?>">
                        <input type="hidden" name="reference_id" value="<?php echo sr_e($rewardRefundReferenceId); ?>">
                        <div class="admin-summary-stats">
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('reward::ui.member.e335b899')); ?> <strong><?php echo sr_e(sr_admin_member_display_name_preview($rewardRefundTransaction)); ?></strong></span>
                            <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($rewardRefundTransaction)); ?></span>
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('reward::ui.text.64d5a726')); ?> <strong><?php echo sr_e(number_format((int) $rewardRefundTransaction['amount'])); ?> <?php echo sr_e(sr_t('reward::ui.text.c19fd678')); ?></strong></span>
                        </div>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html($rewardRefundFieldPrefix . '_amount', sr_t('reward::ui.text.a27af6c4'), $rewardHelp['refund_amount']['id'], $rewardHelpOpenLabel, true); ?>
                            <div class="form-field">
                                <input id="<?php echo sr_e($rewardRefundFieldPrefix); ?>_amount" type="number" name="amount" value="<?php echo sr_e((string) $rewardRefundDefaultAmount); ?>" step="1" min="1" required class="form-input" data-overlay-focus>
                                <p class="form-help"><?php echo sr_e(sr_t('reward::ui.save.1a5bb64e')); ?></p>
                            </div>
                        </div>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html($rewardRefundFieldPrefix . '_reason', sr_t('reward::ui.text.ab9442a2'), $rewardHelp['refund_reason']['id'], $rewardHelpOpenLabel, true); ?>
                            <div class="form-field">
                                <input id="<?php echo sr_e($rewardRefundFieldPrefix); ?>_reason" type="text" name="reason" value="<?php echo sr_e(sr_t('reward::ui.text.5b471750') . (string) $rewardRefundTransactionId . sr_t('reward::ui.text.0a4d3a11')); ?>" maxlength="255" required class="form-input form-control-full">
                                <p class="form-help"><?php echo sr_e(sr_t('reward::ui.id.0b5dbcb4')); ?> <?php echo sr_e($rewardRefundReferenceId); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($rewardRefundModalId); ?>"><?php echo sr_e(sr_t('reward::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('reward::ui.save.ae2ff036')); ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<?php if ($rewardAdminPage === 'transactions' && $transactions !== []) { ?>
    <?php foreach ($transactions as $rewardReclaimTransaction) { ?>
        <?php
        $rewardReclaimTransactionId = (int) ($rewardReclaimTransaction['id'] ?? 0);
        $rewardReclaimRemainingAmount = (int) ($rewardReclaimRemainingAmounts[$rewardReclaimTransactionId] ?? 0);
        if ($rewardReclaimRemainingAmount <= 0) {
            continue;
        }
        $rewardReclaimModalId = 'reward-reclaim-modal-' . $rewardReclaimTransactionId;
        $rewardReclaimFieldPrefix = 'reward_reclaim_' . $rewardReclaimTransactionId;
        $rewardReclaimReferenceId = sr_reward_reclaim_reference_id($rewardReclaimTransactionId);
        ?>
        <div id="<?php echo sr_e($rewardReclaimModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($rewardReclaimFieldPrefix); ?>_title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/rewards/transactions?account_identifier=' . rawurlencode((string) $rewardReclaimTransaction['account_public_hash']))); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($rewardReclaimFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e(sr_t('reward::ui.text.f35d7f2f')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('reward::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($rewardReclaimModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="transaction_type" value="reclaim">
                        <input type="hidden" name="reference_type" value="reclaim">
                        <input type="hidden" name="account_identifier" value="<?php echo sr_e((string) $rewardReclaimTransaction['account_public_hash']); ?>">
                        <input type="hidden" name="reference_id" value="<?php echo sr_e($rewardReclaimReferenceId); ?>">
                        <div class="admin-summary-stats">
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('reward::ui.member.e335b899')); ?> <strong><?php echo sr_e(sr_admin_member_display_name_preview($rewardReclaimTransaction)); ?></strong></span>
                            <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($rewardReclaimTransaction)); ?></span>
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('reward::ui.text.6c31ec31')); ?> <strong><?php echo sr_e(number_format((int) $rewardReclaimTransaction['amount'])); ?> <?php echo sr_e(sr_t('reward::ui.text.c19fd678')); ?></strong></span>
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('reward::ui.text.b00f056b')); ?> <strong><?php echo sr_e(number_format($rewardReclaimRemainingAmount)); ?> <?php echo sr_e(sr_t('reward::ui.text.c19fd678')); ?></strong></span>
                        </div>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html($rewardReclaimFieldPrefix . '_amount', sr_t('reward::ui.text.5c705e1a'), $rewardHelp['amount']['id'], $rewardHelpOpenLabel, true); ?>
                            <div class="form-field">
                                <input id="<?php echo sr_e($rewardReclaimFieldPrefix); ?>_amount" type="number" name="amount" value="-<?php echo sr_e((string) $rewardReclaimRemainingAmount); ?>" step="1" min="-<?php echo sr_e((string) $rewardReclaimRemainingAmount); ?>" max="-1" required class="form-input" data-overlay-focus>
                                <p class="form-help"><?php echo sr_e(sr_t('reward::action.admin.reclaim_amount_exceeds_target')); ?></p>
                            </div>
                        </div>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html($rewardReclaimFieldPrefix . '_reason', sr_t('reward::ui.text.ab9442a2'), $rewardHelp['reason']['id'], $rewardHelpOpenLabel, true); ?>
                            <div class="form-field">
                                <input id="<?php echo sr_e($rewardReclaimFieldPrefix); ?>_reason" type="text" name="reason" value="<?php echo sr_e(sr_t('reward::ui.text.5b471750') . (string) $rewardReclaimTransactionId . ' ' . sr_t('reward::ui.text.f7cd7185')); ?>" maxlength="255" required class="form-input form-control-full">
                                <p class="form-help"><?php echo sr_e(sr_t('reward::ui.text.f5f89fb1')); ?> <?php echo sr_e($rewardReclaimReferenceId); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($rewardReclaimModalId); ?>"><?php echo sr_e(sr_t('reward::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-outline-danger modal-action"><?php echo sr_e(sr_t('reward::ui.text.30f8531c')); ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<?php foreach ($rewardHelp as $rewardHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $rewardHelpModal['id'], (string) $rewardHelpModal['title'], (string) $rewardHelpModal['body_html']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
