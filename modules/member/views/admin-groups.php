<?php

$memberGroupsPage = isset($memberGroupsPage) ? (string) $memberGroupsPage : 'groups';
$adminContainerClass = 'admin-page-member-groups admin-ui-scope';
$adminPageTitle = sr_t('member::ui.member.7482bebf');
if ($memberGroupsPage === 'group_form') {
    $adminPageTitle = is_array($editGroup) ? sr_t('member::ui.member.edit.c267c25d') : sr_t('member::ui.member.c879c4be');
} elseif ($memberGroupsPage === 'rules') {
    $adminPageTitle = sr_t('member::ui.member.bc3daeb8');
} elseif ($memberGroupsPage === 'rule_form') {
    $adminPageTitle = is_array($editRule) ? sr_t('member::ui.member.edit.8fa5d9e5') : sr_t('member::ui.member.ac78ee3c');
} elseif ($memberGroupsPage === 'evaluations') {
    $adminPageTitle = sr_t('member::ui.member.ec737c00');
}

include SR_ROOT . '/modules/admin/views/layout-header.php';
$groupListFilter = isset($groupListFilter) && is_array($groupListFilter) ? $groupListFilter : ['status' => '', 'field' => 'all', 'keyword' => ''];
$groupStatusCounts = isset($groupStatusCounts) && is_array($groupStatusCounts) ? $groupStatusCounts : [];
$membershipsByGroupId = isset($membershipsByGroupId) && is_array($membershipsByGroupId) ? $membershipsByGroupId : [];
$membershipLogsByGroupId = isset($membershipLogsByGroupId) && is_array($membershipLogsByGroupId) ? $membershipLogsByGroupId : [];
$totalGroups = (int) ($groupStatusCounts['total'] ?? count($groups));
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($memberGroupsPage === 'group_form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-groups/save')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo is_array($editGroup) ? sr_t('member::ui.edit.5784f889') : sr_t('member::ui.text.22129319'); ?></h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="group_id" value="<?php echo sr_e(is_array($editGroup) ? (string) $editGroup['id'] : ''); ?>">

            <?php if (is_array($editGroup)) { ?>
                <div class="admin-form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('member::ui.key.1057ecca')); ?></span>
                    <div class="admin-form-field">
                        <code><?php echo sr_e((string) $editGroup['group_key']); ?></code>
                    </div>
                </div>
            <?php } else { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_groups_group_key"><?php echo sr_e(sr_t('member::ui.key.1057ecca')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="member_admin_groups_group_key" type="text" name="group_key" maxlength="60" pattern="[a-z0-9_]+" inputmode="latin" autocapitalize="none" spellcheck="false" required class="form-input" data-admin-key-input>
                    </div>
                </div>
            <?php } ?>

            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_title"><?php echo sr_e(sr_t('member::ui.text.97e73d18')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="member_admin_groups_title" type="text" name="title" maxlength="120" value="<?php echo sr_e(is_array($editGroup) ? (string) $editGroup['title'] : ''); ?>" class="form-input form-control-full" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_description"><?php echo sr_e(sr_t('member::ui.text.8c3f651d')); ?></label>
                <div class="admin-form-field">
                    <textarea id="member_admin_groups_description" name="description" rows="3" cols="60" class="form-textarea"><?php echo sr_e(is_array($editGroup) ? (string) ($editGroup['description'] ?? '') : ''); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_status"><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="member_admin_groups_status" name="status" class="form-select">
                                            <?php $currentStatus = is_array($editGroup) ? (string) $editGroup['status'] : 'enabled'; ?>
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                                    <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_sort_order"><?php echo sr_e(sr_t('member::ui.text.7d2dc215')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="member_admin_groups_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e(is_array($editGroup) ? (string) $editGroup['sort_order'] : '0'); ?>" required class="form-input">
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/member-groups')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
        </div>
    </form>
<?php } elseif ($memberGroupsPage === 'groups') { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/member-groups')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.all.e078b14a')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.text.ca286213')); ?> <strong><?php echo sr_e((string) $totalGroups); ?><?php echo sr_e(sr_t('member::ui.text.a57ab057')); ?></strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups?status=enabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.active.93c558d7')); ?> <?php echo sr_e((string) ($groupStatusCounts['enabled'] ?? 0)); ?><?php echo sr_e(sr_t('member::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups?status=disabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.active.f54a7542')); ?> <?php echo sr_e((string) ($groupStatusCounts['disabled'] ?? 0)); ?><?php echo sr_e(sr_t('member::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups?status=archived')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.text.2e4099ba')); ?> <?php echo sr_e((string) ($groupStatusCounts['archived'] ?? 0)); ?><?php echo sr_e(sr_t('member::ui.text.a57ab057')); ?></a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/member-groups')); ?>" class="admin-filter admin-member-group-filter ui-form-theme">
        <div class="admin-filter-grid admin-member-group-search-grid">
            <div class="admin-filter-field">
                <label for="member-group-status-filter" class="admin-filter-label"><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></label>
                <select name="status" id="member-group-status-filter" class="form-select admin-filter-input">
                    <option value=""><?php echo sr_e(sr_t('member::ui.all.a4b69faf')); ?></option>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($groupListFilter['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="member-group-search-field" class="admin-filter-label"><?php echo sr_e(sr_t('member::ui.search.b79bc9c8')); ?></label>
                <select name="field" id="member-group-search-field" class="form-select admin-filter-input">
                    <?php foreach (['all' => sr_t('member::ui.all.a4b69faf'), 'key' => sr_t('member::ui.key.1057ecca'), 'title' => sr_t('member::ui.text.97e73d18'), 'description' => sr_t('member::ui.text.8c3f651d')] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($groupListFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-member-group-filter-keyword">
                <label for="member-group-search-keyword" class="admin-filter-label"><?php echo sr_e(sr_t('member::ui.search.bda397fc')); ?></label>
                <input type="text" id="member-group-search-keyword" name="q" value="<?php echo sr_e((string) ($groupListFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" placeholder="<?php echo sr_e(sr_t('member::ui.key.60df9e41')); ?>">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('member::ui.search.4b8d541e')); ?></button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.list.c78d8209')); ?></h2>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups/new')); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('member::ui.text.6de46476')); ?></a>
        </div>
        <div class="table-wrapper">
        <table class="table admin-member-group-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('member::ui.member.list.7b664c16')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>key</th>
                    <th><?php echo sr_e(sr_t('member::ui.text.97e73d18')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.member.984c7e2b')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.text.3788952d')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('member::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($groups === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.member.4ef35a24')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($groups as $group) { ?>
                    <?php
                    $groupId = (int) $group['id'];
                    $groupStatus = (string) $group['status'];
                    $statusClass = match ($groupStatus) {
                        'enabled' => 'is-normal',
                        'disabled' => 'is-blocked',
                        default => 'is-left',
                    };
                    $manualAssignModalId = 'member-group-manual-assign-modal-' . $groupId;
                    $assignmentHistoryModalId = 'member-group-assignment-history-modal-' . $groupId;
                    ?>
                    <tr>
                        <td><?php echo sr_e((string) $group['id']); ?></td>
                        <td class="admin-table-nowrap admin-member-group-key-cell"><?php echo sr_e((string) $group['group_key']); ?></td>
                        <td class="admin-member-group-title-cell"><?php echo sr_e((string) $group['title']); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($groupStatus, 'content_status')); ?></span></td>
                        <td class="admin-table-nowrap admin-member-group-number-cell"><?php echo sr_e((string) $group['active_member_count']); ?></td>
                        <td class="admin-table-nowrap admin-member-group-number-cell"><?php echo sr_e((string) $group['sort_order']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($manualAssignModalId); ?>" data-overlay="#<?php echo sr_e($manualAssignModalId); ?>"><?php echo sr_e(sr_t('member::ui.text.94e3ebac')); ?></button>
                                <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($assignmentHistoryModalId); ?>" data-overlay="#<?php echo sr_e($assignmentHistoryModalId); ?>"><?php echo sr_e(sr_t('member::ui.text.fb4e329c')); ?></button>
                                <a href="<?php echo sr_e(sr_url('/admin/member-groups/edit?id=' . rawurlencode((string) $group['id']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?></a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php foreach ($groups as $group) { ?>
        <?php
        $groupId = (int) $group['id'];
        $manualAssignModalId = 'member-group-manual-assign-modal-' . $groupId;
        $assignmentHistoryModalId = 'member-group-assignment-history-modal-' . $groupId;
        $manualAssignFieldPrefix = 'member_group_manual_assign_' . $groupId;
        $manualAssignAccountInputId = $manualAssignFieldPrefix . '_account_identifier';
        $manualAssignMemberLookupModalId = $manualAssignFieldPrefix . '_member_lookup_modal';
        $groupMemberships = isset($membershipsByGroupId[$groupId]) && is_array($membershipsByGroupId[$groupId]) ? $membershipsByGroupId[$groupId] : [];
        $groupMembershipLogs = isset($membershipLogsByGroupId[$groupId]) && is_array($membershipLogsByGroupId[$groupId]) ? $membershipLogsByGroupId[$groupId] : [];
        ?>
        <div id="<?php echo sr_e($manualAssignModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($manualAssignFieldPrefix); ?>_title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-assignments/grant')); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($manualAssignFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e(sr_t('member::ui.text.94e3ebac')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($manualAssignModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="group_id" value="<?php echo sr_e((string) $groupId); ?>">
                        <div class="admin-summary-stats">
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.member.7482bebf')); ?> <strong><?php echo sr_e((string) $group['title']); ?></strong></span>
                            <span class="admin-summary-meta"><?php echo sr_e((string) $group['group_key']); ?></span>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($manualAssignAccountInputId); ?>"><?php echo sr_e(sr_t('member::ui.member.hash.5a5dbe2b')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                            <div class="admin-form-field">
                                <div class="admin-lookup-control">
                                    <input id="<?php echo sr_e($manualAssignAccountInputId); ?>" type="text" name="account_identifier" class="form-input" maxlength="80" required data-overlay-focus>
                                    <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($manualAssignMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($manualAssignMemberLookupModalId); ?>" data-admin-member-lookup-open data-target="#<?php echo sr_e($manualAssignAccountInputId); ?>"><?php echo sr_e(sr_t('admin::ui.member.search.f7a330b0')); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($manualAssignModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('member::ui.text.41172d90')); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $assetAdjustLookup = [
            'field_prefix' => $manualAssignFieldPrefix,
            'member_input_id' => $manualAssignAccountInputId,
            'return_overlay_id' => $manualAssignModalId,
            'return_label' => sr_t('member::ui.text.94e3ebac'),
            'member_search_url' => sr_url('/admin/members/search'),
        ];
        include SR_ROOT . '/modules/admin/views/asset-adjust-lookup-modals.php';
        ?>

        <div id="<?php echo sr_e($assignmentHistoryModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($manualAssignFieldPrefix); ?>_history_title" aria-hidden="true" inert>
            <div class="modal-dialog modal-dialog-lg">
                <div class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($manualAssignFieldPrefix); ?>_history_title" class="modal-title"><?php echo sr_e(sr_t('member::ui.text.2680da81')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($assignmentHistoryModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="admin-summary-stats">
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.member.7482bebf')); ?> <strong><?php echo sr_e((string) $group['title']); ?></strong></span>
                            <span class="admin-summary-meta"><?php echo sr_e((string) $group['group_key']); ?></span>
                        </div>
                        <section class="admin-card admin-list-card card admin-list-form">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('member::ui.text.561bac1a')); ?></h4>
                            </div>
                            <div class="table-wrapper">
                                <table class="table">
                                    <thead class="ui-table-head">
                                        <tr>
                                            <th>ID</th>
                                            <th><?php echo sr_e(sr_t('member::ui.member.e335b899')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.text.5cf2792b')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.text.095ffbfb')); ?></th>
                                            <th class="text-end"><?php echo sr_e(sr_t('member::ui.text.8b179161')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($groupMemberships === []) { ?>
                                            <tr>
                                                <td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.text.6bfe04ee')); ?></td>
                                            </tr>
                                        <?php } ?>
                                        <?php foreach ($groupMemberships as $membership) { ?>
                                            <tr>
                                                <td><?php echo sr_e((string) $membership['id']); ?></td>
                                                <td>
                                                    <?php echo sr_e((string) $membership['account_public_hash']); ?><br>
                                                    <?php echo sr_e(sr_admin_member_display_name_preview($membership)); ?>
                                                </td>
                                                <td><?php echo sr_e(sr_admin_code_label((string) $membership['assignment_type'], 'assignment_type')); ?></td>
                                                <td><?php echo sr_e(sr_admin_code_label((string) $membership['status'], 'membership_status')); ?></td>
                                                <td><?php echo sr_e((string) ($membership['granted_at'] ?? '')); ?></td>
                                                <td class="admin-table-actions-cell">
                                                    <div class="admin-row-actions">
                                                        <?php if ((string) $membership['assignment_type'] === 'manual' && (string) $membership['status'] === 'active') { ?>
                                                            <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-assignments/revoke')); ?>">
                                                                <?php echo sr_csrf_field(); ?>
                                                                <input type="hidden" name="account_id" value="<?php echo sr_e((string) $membership['account_id']); ?>">
                                                                <input type="hidden" name="group_id" value="<?php echo sr_e((string) $membership['group_id']); ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo sr_e(sr_t('member::ui.text.293182ec')); ?></button>
                                                            </form>
                                                        <?php } else { ?>
                                                            <?php echo sr_e((string) ($membership['revoked_at'] ?? '')); ?>
                                                        <?php } ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="admin-card admin-list-card card admin-list-form">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('member::ui.text.2680da81')); ?></h4>
                            </div>
                            <div class="table-wrapper">
                                <table class="table">
                                    <thead class="ui-table-head">
                                        <tr>
                                            <th>ID</th>
                                            <th><?php echo sr_e(sr_t('member::ui.member.e335b899')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.text.46b289bb')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.text.4cd44bae')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.text.4692cef5')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($groupMembershipLogs === []) { ?>
                                            <tr>
                                                <td colspan="5" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.text.537aa44f')); ?></td>
                                            </tr>
                                        <?php } ?>
                                        <?php foreach ($groupMembershipLogs as $log) { ?>
                                            <tr>
                                                <td><?php echo sr_e((string) $log['id']); ?></td>
                                                <td>
                                                    <?php echo sr_e((string) $log['account_public_hash']); ?><br>
                                                    <?php echo sr_e(sr_admin_member_display_name_preview($log)); ?>
                                                </td>
                                                <td><?php echo sr_e(sr_admin_event_type_label((string) $log['event_type'])); ?></td>
                                                <td><?php echo sr_e((string) $log['message']); ?></td>
                                                <td><?php echo sr_e((string) $log['created_at']); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($assignmentHistoryModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
<?php } elseif ($memberGroupsPage === 'rules') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.text.1f9a70dc')); ?></h2>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('member::ui.text.6d2d8bf4')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.text.291ac971')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.text.8c3f651d')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.text.bf705e51')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($ruleDefinitions === []) { ?>
                    <tr>
                        <td colspan="4" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.member.93ca7cdf')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($ruleDefinitions as $definition) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $definition['source_module_key']); ?></td>
                        <td>
                            <?php echo sr_e((string) $definition['label']); ?><br>
                            <?php echo sr_e((string) $definition['rule_key']); ?>
                        </td>
                        <td><?php echo sr_e((string) $definition['description']); ?></td>
                        <td>
                            <?php if ($definition['params'] === []) { ?>
                                <?php echo sr_e(sr_t('member::ui.text.72ea3d64')); ?>
                            <?php } else { ?>
                                <ul>
                                    <?php foreach ($definition['params'] as $param) { ?>
                                        <li>
                                            <?php echo sr_e((string) $param['key']); ?>:
                                            <?php echo sr_e((string) $param['label']); ?>
                                            (<?php echo sr_e(sr_admin_code_label((string) $param['type'], 'setting_type')); ?>)
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.save.617f3ca3')); ?></h2>
            <a href="<?php echo sr_e(sr_url('/admin/member-group-rules/new')); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('member::ui.text.b5b997ea')); ?></a>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th><?php echo sr_e(sr_t('member::ui.text.5d908ddd')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.text.291ac971')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.text.ff41d4a4')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.text.4c544b45')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('member::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($groupRules === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.text.1998c6cf')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($groupRules as $rule) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $rule['id']); ?></td>
                        <td><?php echo sr_e((string) $rule['group_title']); ?></td>
                        <td>
                            <?php echo sr_e((string) $rule['source_module_key']); ?><br>
                            <?php echo sr_e((string) $rule['rule_key']); ?>
                        </td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $rule['evaluation_policy'], 'evaluation_policy')); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $rule['status'], 'content_status')); ?></td>
                        <td><?php echo sr_e((string) ($rule['last_evaluated_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/member-group-rules/edit?id=' . rawurlencode((string) $rule['id']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?></a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } elseif ($memberGroupsPage === 'rule_form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-rules/save')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo is_array($editRule) ? sr_t('member::ui.edit.6e308f62') : sr_t('member::ui.text.eee300ae'); ?></h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="rule_id" value="<?php echo sr_e(is_array($editRule) ? (string) $editRule['id'] : ''); ?>">
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_group_id"><?php echo sr_e(sr_t('member::ui.text.5034bb32')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="member_admin_groups_group_id" name="group_id" required class="form-select">
                                            <?php foreach ($groups as $group) { ?>
                                                <option value="<?php echo sr_e((string) $group['id']); ?>"<?php echo is_array($editRule) && (int) $editRule['group_id'] === (int) $group['id'] ? ' selected' : ''; ?>>
                                                    <?php echo sr_e((string) $group['title']); ?> (<?php echo sr_e((string) $group['group_key']); ?>)
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_definition_key"><?php echo sr_e(sr_t('member::ui.text.7a1e6434')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="member_admin_groups_definition_key" name="definition_key" required data-member-rule-definition class="form-select">
                                            <?php $currentDefinitionKey = is_array($editRule) ? (string) $editRule['source_module_key'] . ':' . (string) $editRule['rule_key'] : ''; ?>
                                            <?php foreach ($ruleDefinitions as $definitionKey => $definition) { ?>
                                                <option value="<?php echo sr_e((string) $definitionKey); ?>"<?php echo $currentDefinitionKey === (string) $definitionKey ? ' selected' : ''; ?>>
                                                    <?php echo sr_e((string) $definition['label']); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('member::ui.settings.7d7902a7')); ?></span>
                <div class="admin-form-field">
                    <?php
                    $currentRuleParams = [];
                    if (is_array($editRule)) {
                        $decodedRuleParams = json_decode((string) $editRule['rule_params_json'], true);
                        $currentRuleParams = is_array($decodedRuleParams) ? $decodedRuleParams : [];
                    }
                    ?>
                    <div class="member-rule-param-panels" data-member-rule-param-panels>
                        <?php foreach ($ruleDefinitions as $definitionKey => $definition) { ?>
                            <?php $panelActive = $currentDefinitionKey === (string) $definitionKey || ($currentDefinitionKey === '' && $definitionKey === array_key_first($ruleDefinitions)); ?>
                            <div class="member-rule-param-panel"<?php echo $panelActive ? '' : ' hidden'; ?> data-rule-param-panel="<?php echo sr_e((string) $definitionKey); ?>">
                                <?php if ((string) ($definition['description'] ?? '') !== '') { ?>
                                    <p><?php echo sr_e((string) $definition['description']); ?></p>
                                <?php } ?>
                                <?php if (($definition['params'] ?? []) === []) { ?>
                                    <p><?php echo sr_e(sr_t('member::ui.settings.1ca7d0dd')); ?></p>
                                <?php } ?>
                                <?php foreach ((array) ($definition['params'] ?? []) as $param) { ?>
                                    <?php
                                    $paramKey = (string) ($param['key'] ?? '');
                                    $paramType = (string) ($param['type'] ?? 'string');
                                    $paramValue = array_key_exists($paramKey, $currentRuleParams) ? $currentRuleParams[$paramKey] : ($param['default'] ?? '');
                                    $paramFieldId = 'member_rule_param_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', (string) $definitionKey . '_' . $paramKey);
                                    ?>
                                    <label class="admin-filter-field" for="<?php echo sr_e($paramFieldId); ?>">
                                        <span class="admin-filter-label"><?php echo sr_e((string) ($param['label'] ?? $paramKey)); ?></span>
                                        <?php if ($paramType === 'bool') { ?>
                                            <select id="<?php echo sr_e($paramFieldId); ?>" name="rule_param[<?php echo sr_e((string) $definitionKey); ?>][<?php echo sr_e($paramKey); ?>]"<?php echo $panelActive ? '' : ' disabled'; ?> class="form-select">
                                                <option value="1"<?php echo !empty($paramValue) ? ' selected' : ''; ?>><?php echo sr_e(sr_t('member::ui.text.2eb73fba')); ?></option>
                                                <option value="0"<?php echo empty($paramValue) ? ' selected' : ''; ?>><?php echo sr_e(sr_t('member::ui.text.4c490f1c')); ?></option>
                                            </select>
                                        <?php } elseif ($paramType === 'int' || $paramType === 'subject') { ?>
                                            <input id="<?php echo sr_e($paramFieldId); ?>" type="number" name="rule_param[<?php echo sr_e((string) $definitionKey); ?>][<?php echo sr_e($paramKey); ?>]" value="<?php echo sr_e((string) $paramValue); ?>"<?php echo isset($param['min']) ? ' min="' . sr_e((string) $param['min']) . '"' : ''; ?><?php echo isset($param['max']) ? ' max="' . sr_e((string) $param['max']) . '"' : ''; ?><?php echo $panelActive ? '' : ' disabled'; ?> class="form-input">
                                        <?php } else { ?>
                                            <input id="<?php echo sr_e($paramFieldId); ?>" type="text" name="rule_param[<?php echo sr_e((string) $definitionKey); ?>][<?php echo sr_e($paramKey); ?>]" value="<?php echo sr_e((string) $paramValue); ?>"<?php echo $panelActive ? '' : ' disabled'; ?> class="form-input">
                                        <?php } ?>
                                    </label>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <details class="admin-advanced-details">
                        <summary><?php echo sr_e(sr_t('member::ui.json.663601a1')); ?></summary>
                        <textarea name="rule_params_json" rows="4" cols="70" class="form-textarea"><?php echo sr_e(is_array($editRule) ? (string) $editRule['rule_params_json'] : '{}'); ?></textarea>
                    </details>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_evaluation_policy"><?php echo sr_e(sr_t('member::ui.text.c3054578')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="member_admin_groups_evaluation_policy" name="evaluation_policy" class="form-select">
                                            <?php foreach ($allowedEvaluationPolicies as $policy) { ?>
                                                <option value="<?php echo sr_e($policy); ?>"<?php echo is_array($editRule) && (string) $editRule['evaluation_policy'] === $policy ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'evaluation_policy')); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_status_2"><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="member_admin_groups_status_2" name="status" class="form-select">
                                            <?php foreach ($allowedRuleStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo is_array($editRule) && (string) $editRule['status'] === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/member-group-rules')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.save.95d3fea1')); ?></button>
        </div>
    </form>
<?php } elseif ($memberGroupsPage === 'evaluations') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-evaluations/account')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('member::ui.text.32fa0afb')); ?></h2>
            <?php echo sr_csrf_field(); ?>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('member::ui.member.9d5adfda')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                <div class="admin-form-field">
                    <select name="account_identifier_field" class="form-select" aria-label="<?php echo sr_e(sr_t('member::ui.member.a4cdf8ad')); ?>">
                        <option value="hash"><?php echo sr_e(sr_t('member::ui.text.93971787')); ?></option>
                        <option value="email"><?php echo sr_e(sr_t('member::ui.email.3b7dbc4c')); ?></option>
                        <option value="login_id"><?php echo sr_e(sr_t('member::ui.login.0cdb28b5')); ?></option>
                        <option value="name"><?php echo sr_e(sr_t('member::ui.name.253d1510')); ?></option>
                    </select>
                    <input type="text" name="account_identifier" maxlength="120" required class="form-input" aria-label="<?php echo sr_e(sr_t('member::ui.member.4806b16f')); ?>" placeholder="<?php echo sr_e(sr_t('member::ui.email.login.name.c26ba637')); ?>">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_source_module_key"><?php echo sr_e(sr_t('member::ui.key.d2f54e12')); ?></label>
                <div class="admin-form-field">
                    <input id="member_admin_groups_source_module_key" type="text" name="source_module_key" maxlength="60" pattern="[a-z0-9_]*" inputmode="latin" autocapitalize="none" spellcheck="false" class="form-input" data-admin-key-input>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.text.3d1d323a')); ?></button>
        </div>
    </form>

    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-evaluations/batch')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('member::ui.text.fdbee63b')); ?></h2>
            <?php echo sr_csrf_field(); ?>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_source_module_key_2"><?php echo sr_e(sr_t('member::ui.key.d2f54e12')); ?></label>
                <div class="admin-form-field">
                    <input id="member_admin_groups_source_module_key_2" type="text" name="source_module_key" maxlength="60" pattern="[a-z0-9_]*" inputmode="latin" autocapitalize="none" spellcheck="false" class="form-input" data-admin-key-input>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_limit"><?php echo sr_e(sr_t('member::ui.member.5138655c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="member_admin_groups_limit" type="number" name="limit" min="1" max="200" value="50" required class="form-input">
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.text.fdbee63b')); ?></button>
        </div>
    </form>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
