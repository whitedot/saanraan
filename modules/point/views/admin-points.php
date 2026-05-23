<?php

$pointAdminPage = isset($pointAdminPage) ? (string) $pointAdminPage : 'balances';
$adminPageTitle = sr_t('point::ui.point.47719e8e');
if ($pointAdminPage === 'transactions') {
    $adminPageTitle = sr_t('point::ui.point.cd2b311f');
}
$accountLookupFilter = isset($accountLookupFilter) && is_array($accountLookupFilter) ? $accountLookupFilter : ['field' => 'all', 'keyword' => (string) ($accountIdentifierFilter ?? '')];
$pointReferenceTypeOptions = [
    '' => sr_t('point::ui.text.72ea3d64'),
    'order' => sr_t('point::ui.text.d64a64f0'),
    'payment' => sr_t('point::ui.text.8d4f3299'),
    'refund' => sr_t('point::ui.text.edda9108'),
    'support_ticket' => sr_t('point::ui.text.9ce226a0'),
    'event' => sr_t('point::ui.text.46b289bb'),
    'migration' => sr_t('point::ui.text.2e52928e'),
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
            <label for="point-member-search-field" class="admin-filter-label"><?php echo sr_e(sr_t('point::ui.search.b79bc9c8')); ?></label>
            <select name="field" id="point-member-search-field" class="form-select admin-filter-input">
                <?php foreach (['all' => sr_t('point::ui.all.a4b69faf'), 'hash' => sr_t('point::ui.text.93971787'), 'email' => sr_t('point::ui.email.3b7dbc4c'), 'login_id' => sr_t('point::ui.login.0cdb28b5'), 'name' => sr_t('point::ui.name.253d1510')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($accountLookupFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-asset-member-filter-keyword">
            <label for="point-member-search-keyword" class="admin-filter-label"><?php echo sr_e(sr_t('point::ui.search.bda397fc')); ?></label>
            <input type="text" id="point-member-search-keyword" name="q" value="<?php echo sr_e((string) ($accountLookupFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('point::ui.email.login.name.c26ba637')); ?>">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('point::ui.search.4b8d541e')); ?></button>
    </div>
</form>

<?php if (is_array($selectedAccount)) { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/points/balances?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('point::ui.text.7bc75ef8')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/points/transactions?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('point::ui.text.87e27fc1')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('point::ui.member.e335b899')); ?> <strong><?php echo sr_e(sr_admin_member_display_name_preview($selectedAccount)); ?></strong></span>
            <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($selectedAccount)); ?></span>
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('point::ui.text.b099377c')); ?> <strong><?php echo sr_e(number_format((int) $selectedBalance)); ?> P</strong></span>
        </div>
    </div>
<?php } elseif ((string) ($accountLookupFilter['keyword'] ?? '') !== '') { ?>
    <p class="admin-empty-state"><?php echo sr_e(sr_t('point::ui.member.8f3d9a93')); ?></p>
<?php } ?>

<?php if ($pointAdminPage === 'transactions') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header"><h2 class="card-title"><?php echo sr_e(sr_t('point::ui.text.ce41e3f6')); ?></h2></div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th><?php echo sr_e(sr_t('point::ui.member.e335b899')); ?></th>
                    <th><?php echo sr_e(sr_t('point::ui.text.5cf2792b')); ?></th>
                    <th><?php echo sr_e(sr_t('point::ui.text.4a12f983')); ?></th>
                    <th><?php echo sr_e(sr_t('point::ui.text.87f9c4c8')); ?></th>
                    <th><?php echo sr_e(sr_t('point::ui.text.ab9442a2')); ?></th>
                    <th><?php echo sr_e(sr_t('point::ui.text.fbc8ad58')); ?></th>
                    <th><?php echo sr_e(sr_t('point::ui.text.5efd3ddd')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('point::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state"><?php echo sr_e(sr_t('point::ui.point.f50f4b9d')); ?></td>
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
                                        <a href="<?php echo sr_e(sr_url('/admin/points/transactions?account_identifier=' . rawurlencode((string) $transaction['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($pointTransactionRefundModalId); ?>" data-overlay="#<?php echo sr_e($pointTransactionRefundModalId); ?>"><?php echo sr_e(sr_t('point::ui.text.edda9108')); ?></a>
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
            <h2 class="card-title"><?php echo sr_e(sr_t('point::ui.text.b62aead1')); ?></h2>
            <?php $pointHeaderAdjustModalId = is_array($selectedAccount) ? 'point-adjust-modal-' . (int) ($selectedAccount['id'] ?? 0) : 'point-adjust-modal-0'; ?>
            <?php $pointHeaderAdjustUrl = is_array($selectedAccount) ? '/admin/points/balances?account_identifier=' . rawurlencode((string) $selectedAccount['account_public_hash']) : '/admin/points/balances'; ?>
            <a href="<?php echo sr_e(sr_url($pointHeaderAdjustUrl)); ?>" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($pointHeaderAdjustModalId); ?>" data-overlay="#<?php echo sr_e($pointHeaderAdjustModalId); ?>"><?php echo sr_e(sr_t('point::ui.text.7535b737')); ?></a>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('point::ui.member.900e04a5')); ?></th>
                    <th><?php echo sr_e(sr_t('point::ui.member.e335b899')); ?></th>
                    <th><?php echo sr_e(sr_t('point::ui.status.e10195a1')); ?></th>
                    <th><?php echo sr_e(sr_t('point::ui.text.b099377c')); ?></th>
                    <th><?php echo sr_e(sr_t('point::ui.edit.d3a98476')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('point::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($balances === []) { ?>
                    <tr>
                        <td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('point::ui.point.8bceaefc')); ?></td>
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
                                    <a href="<?php echo sr_e(sr_url('/admin/points/transactions?account_identifier=' . rawurlencode((string) $balance['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('point::ui.text.754ef98b')); ?></a>
                                    <?php $pointBalanceAdjustModalId = 'point-adjust-modal-' . (int) ($balance['account_id'] ?? 0); ?>
                                    <a href="<?php echo sr_e(sr_url('/admin/points/balances?account_identifier=' . rawurlencode((string) $balance['account_public_hash']))); ?>" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($pointBalanceAdjustModalId); ?>" data-overlay="#<?php echo sr_e($pointBalanceAdjustModalId); ?>"><?php echo sr_e(sr_t('point::ui.text.b9d9b240')); ?></a>
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
                <form method="post" action="<?php echo sr_e(sr_url('/admin/points/balances' . ((string) $pointAdjustModalAccount['account_public_hash'] !== '' ? '?account_identifier=' . rawurlencode((string) $pointAdjustModalAccount['account_public_hash']) : ''))); ?>" class="modal-content ui-form-theme" data-admin-reference-pair>
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($pointAdjustFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e(sr_t('point::ui.point.c7027855')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('point::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($pointAdjustModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <?php if ((string) $pointAdjustModalAccount['account_public_hash'] !== '') { ?>
                            <input type="hidden" name="account_identifier" value="<?php echo sr_e((string) $pointAdjustModalAccount['account_public_hash']); ?>">
                            <div class="admin-summary-stats">
                                <span class="admin-summary-meta"><?php echo sr_e(sr_t('point::ui.member.e335b899')); ?> <strong><?php echo sr_e(sr_admin_member_display_name_preview($pointAdjustModalAccount)); ?></strong></span>
                                <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($pointAdjustModalAccount)); ?></span>
                                <span class="admin-summary-meta"><?php echo sr_e(sr_t('point::ui.text.4993967a')); ?> <strong><?php echo sr_e(number_format((int) $pointAdjustModalAccount['balance'])); ?> P</strong></span>
                            </div>
                        <?php } else { ?>
                            <div class="admin-form-row">
                                <label class="form-label" for="<?php echo sr_e($pointAdjustAccountInputId); ?>"><?php echo sr_e(sr_t('point::ui.member.900e04a5')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('point::ui.required.1f227c67')); ?></span></label>
                                <div class="admin-form-field">
                                    <div class="admin-lookup-control">
                                        <input id="<?php echo sr_e($pointAdjustAccountInputId); ?>" type="text" name="account_identifier" value="<?php echo sr_e($accountIdentifierFilter); ?>" class="form-input" maxlength="80" required data-overlay-focus>
                                        <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($pointAdjustMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($pointAdjustMemberLookupModalId); ?>" data-admin-member-lookup-open data-target="#<?php echo sr_e($pointAdjustAccountInputId); ?>"><?php echo sr_e(sr_t('point::ui.member.search.f7a330b0')); ?></button>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointAdjustFieldPrefix); ?>_transaction_type"><?php echo sr_e(sr_t('point::ui.text.3a7bc5ac')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('point::ui.required.1f227c67')); ?></span></label>
                            <div class="admin-form-field">
                                <select id="<?php echo sr_e($pointAdjustFieldPrefix); ?>_transaction_type" name="transaction_type" class="form-select">
                                    <?php foreach ($allowedTransactionTypes as $type) { ?>
                                        <option value="<?php echo sr_e($type); ?>"><?php echo sr_e(sr_admin_code_label($type, 'transaction_type')); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointAdjustFieldPrefix); ?>_amount"><?php echo sr_e(sr_t('point::ui.text.4a12f983')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('point::ui.required.1f227c67')); ?></span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($pointAdjustFieldPrefix); ?>_amount" type="number" name="amount" step="1" required class="form-input" data-overlay-focus>
                                <p class="admin-form-help"><?php echo sr_e(sr_t('point::ui.active.d2de5076')); ?></p>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointAdjustFieldPrefix); ?>_reason"><?php echo sr_e(sr_t('point::ui.text.ab9442a2')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('point::ui.required.1f227c67')); ?></span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($pointAdjustFieldPrefix); ?>_reason" type="text" name="reason" maxlength="255" required class="form-input form-control-full">
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointAdjustReferenceTypeInputId); ?>"><?php echo sr_e(sr_t('point::ui.text.200e7df1')); ?> <span class="sr-required-label" data-admin-reference-type-required hidden><?php echo sr_e(sr_t('point::ui.required.1f227c67')); ?></span></label>
                            <div class="admin-form-field">
                                <select id="<?php echo sr_e($pointAdjustReferenceTypeInputId); ?>" name="reference_type" class="form-select" data-admin-reference-type>
                                    <?php foreach ($pointReferenceTypeOptions as $referenceTypeValue => $referenceTypeLabel) { ?>
                                        <option value="<?php echo sr_e($referenceTypeValue); ?>"><?php echo sr_e($referenceTypeLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointAdjustReferenceIdInputId); ?>"><?php echo sr_e(sr_t('point::ui.id.e89e337e')); ?> <span class="sr-required-label" data-admin-reference-id-required hidden><?php echo sr_e(sr_t('point::ui.required.1f227c67')); ?></span></label>
                            <div class="admin-form-field">
                                <div class="admin-lookup-control">
                                    <input id="<?php echo sr_e($pointAdjustReferenceIdInputId); ?>" type="text" name="reference_id" maxlength="120" class="form-input" data-admin-reference-id>
                                    <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($pointAdjustReferenceLookupModalId); ?>" data-overlay="#<?php echo sr_e($pointAdjustReferenceLookupModalId); ?>" data-admin-reference-lookup-open data-type-target="#<?php echo sr_e($pointAdjustReferenceTypeInputId); ?>" data-id-target="#<?php echo sr_e($pointAdjustReferenceIdInputId); ?>"><?php echo sr_e(sr_t('point::ui.search.3acacadd')); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($pointAdjustModalId); ?>"><?php echo sr_e(sr_t('point::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('point::ui.save.5fb92622')); ?></button>
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
                        <h3 id="<?php echo sr_e($pointRefundFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e(sr_t('point::ui.point.d16a50d1')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('point::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($pointRefundModalId); ?>">
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
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('point::ui.text.da1026da')); ?> <strong>#<?php echo sr_e((string) $pointRefundTransactionId); ?></strong></span>
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('point::ui.member.e335b899')); ?> <strong><?php echo sr_e(sr_admin_member_display_name_preview($pointRefundTransaction)); ?></strong></span>
                            <span class="admin-summary-meta"><?php echo sr_e(sr_admin_member_email_display($pointRefundTransaction)); ?></span>
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('point::ui.text.18e61249')); ?> <strong><?php echo sr_e(number_format((int) $pointRefundTransaction['amount'])); ?> P</strong></span>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointRefundFieldPrefix); ?>_amount"><?php echo sr_e(sr_t('point::ui.text.42607c84')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('point::ui.required.1f227c67')); ?></span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($pointRefundFieldPrefix); ?>_amount" type="number" name="amount" value="<?php echo sr_e((string) $pointRefundDefaultAmount); ?>" step="1" min="1" required class="form-input" data-overlay-focus>
                                <p class="admin-form-help"><?php echo sr_e(sr_t('point::ui.point.save.fcebf5f3')); ?></p>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($pointRefundFieldPrefix); ?>_reason"><?php echo sr_e(sr_t('point::ui.text.ab9442a2')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('point::ui.required.1f227c67')); ?></span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($pointRefundFieldPrefix); ?>_reason" type="text" name="reason" value="<?php echo sr_e(sr_t('point::ui.text.5b471750') . (string) $pointRefundTransactionId . sr_t('point::ui.text.0a4d3a11')); ?>" maxlength="255" required class="form-input form-control-full">
                                <p class="admin-form-help"><?php echo sr_e(sr_t('point::ui.id.0b5dbcb4')); ?> <?php echo sr_e($pointRefundReferenceId); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($pointRefundModalId); ?>"><?php echo sr_e(sr_t('point::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('point::ui.save.ae2ff036')); ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
