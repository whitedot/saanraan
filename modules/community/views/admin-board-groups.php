<?php

$communityBoardGroupsPage = isset($communityBoardGroupsPage) ? (string) $communityBoardGroupsPage : 'list';
$adminPageTitle = sr_t('community::ui.community.59e9d360');
$adminPageSubtitle = sr_t('community::ui.status.search.e64383a1');
$adminContainerClass = 'admin-page-community-board-group-list admin-ui-scope';
if ($communityBoardGroupsPage === 'new') {
    $adminPageTitle = sr_t('community::ui.text.08aafae8');
    $adminPageSubtitle = sr_t('community::ui.text.65ec2e98');
    $adminContainerClass = 'admin-page-community-board-group-form admin-ui-scope';
} elseif ($communityBoardGroupsPage === 'edit') {
    $adminPageTitle = sr_t('community::ui.edit.669f4ac3');
    $adminPageSubtitle = sr_t('community::ui.edit.af3674d1');
    $adminContainerClass = 'admin-page-community-board-group-form admin-ui-scope';
}
$boardGroupListFilters = isset($boardGroupListFilters) && is_array($boardGroupListFilters) ? $boardGroupListFilters : ['status' => '', 'field' => 'all', 'q' => ''];
$boardGroupStatusCounts = isset($boardGroupStatusCounts) && is_array($boardGroupStatusCounts) ? $boardGroupStatusCounts : [];
$totalBoardGroups = (int) ($boardGroupStatusCounts['total'] ?? count($boardGroups ?? []));

$settingLabels = [
    'read_policy' => sr_t('community::ui.text.0b6c5dfd'),
    'write_policy' => sr_t('community::ui.text.4f05f6a8'),
    'comment_policy' => sr_t('community::ui.text.0550e13c'),
    'read_group_keys' => sr_t('community::ui.member.ecf858a4'),
    'write_group_keys' => sr_t('community::ui.member.e99a3ed2'),
    'comment_group_keys' => sr_t('community::ui.member.11859d69'),
    'read_min_level' => sr_t('community::ui.text.a783617f'),
    'write_min_level' => sr_t('community::ui.text.82530158'),
    'comment_min_level' => sr_t('community::ui.text.3eccb18c'),
    'level_post_score' => sr_t('community::ui.text.99092cba'),
    'level_comment_score' => sr_t('community::ui.text.96af1f5c'),
    'image_uploads_enabled' => sr_t('community::ui.text.c3bd14cb'),
    'attachment_max_bytes' => sr_t('community::ui.text.8ffcc807'),
    'attachment_max_count' => sr_t('community::ui.text.bf61ba9f'),
    'file_uploads_enabled' => sr_t('community::ui.text.fe95ead0'),
    'file_attachment_max_bytes' => sr_t('community::ui.text.bd99a9f3'),
    'file_attachment_max_count' => sr_t('community::ui.text.593790e4'),
    'file_allowed_extensions' => sr_t('community::ui.text.69600d46'),
    'banner_before_list_id' => sr_t('community::ui.list.banner.376dfc8d'),
    'banner_after_list_id' => sr_t('community::ui.list.banner.c7e77a71'),
    'banner_before_view_id' => sr_t('community::ui.banner.e30cc36a'),
    'banner_after_view_id' => sr_t('community::ui.banner.625de853'),
    'banner_before_form_id' => sr_t('community::ui.banner.8260e831'),
    'banner_after_form_id' => sr_t('community::ui.banner.a9644a02'),
    'popup_layer_list_id' => sr_t('community::ui.list.f79ff4fb'),
    'popup_layer_view_id' => sr_t('community::ui.text.eee37bdd'),
    'popup_layer_form_id' => sr_t('community::ui.text.0ba981f3'),
    'post_reward_enabled' => sr_t('community::ui.active.48fd7cc1'),
    'post_reward_asset_module' => sr_t('community::ui.text.0f2daed5'),
    'post_reward_amount' => sr_t('community::ui.text.1f6c4129'),
    'comment_reward_enabled' => sr_t('community::ui.active.56949e4e'),
    'comment_reward_asset_module' => sr_t('community::ui.text.ebfaf199'),
    'comment_reward_amount' => sr_t('community::ui.text.e2ff4fe0'),
    'write_charge_enabled' => sr_t('community::ui.active.98b7dd61'),
    'write_charge_asset_module' => sr_t('community::ui.text.6483117a'),
    'write_charge_amount' => sr_t('community::ui.text.3487ef2e'),
    'comment_charge_enabled' => sr_t('community::ui.active.5f0ef7af'),
    'comment_charge_asset_module' => sr_t('community::ui.text.9524324c'),
    'comment_charge_amount' => sr_t('community::ui.text.b34e5207'),
    'paid_read_enabled' => sr_t('community::ui.active.923da40e'),
    'paid_read_asset_module' => sr_t('community::ui.text.8122ce1d'),
    'paid_read_amount' => sr_t('community::ui.text.a37f6a68'),
    'paid_read_charge_policy' => sr_t('community::ui.text.05ead7ab'),
    'paid_attachment_download_enabled' => sr_t('community::ui.active.ac757b6f'),
    'paid_attachment_download_asset_module' => sr_t('community::ui.text.f0201cc5'),
    'paid_attachment_download_amount' => sr_t('community::ui.text.20f3933e'),
    'paid_attachment_download_charge_policy' => sr_t('community::ui.text.978f8b2e'),
];
$groupSettingValue = static function (array $settings, string $key, string $default): string {
    return (string) ($settings[$key] ?? $default);
};
$groupKeysSettingValue = static function (array $settings, string $key): array {
    $value = (string) ($settings[$key] ?? '');
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    $rawKeys = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
    return sr_community_normalize_board_group_keys(is_array($rawKeys) ? $rawKeys : []);
};
$groupField = static function (array $group, string $key, string $default = ''): string {
    return (string) ($group[$key] ?? $default);
};
$communityLevelSelectHtml = static function (string $id, string $name, int $selectedLevel): string {
    $selectedLevel = sr_community_normalize_level_value($selectedLevel);
    $html = '<select id="' . sr_e($id) . '" name="' . sr_e($name) . '" class="form-select">';
    for ($levelValue = 0; $levelValue <= sr_community_max_level_value(); $levelValue++) {
        $html .= '<option value="' . sr_e((string) $levelValue) . '"' . ($selectedLevel === $levelValue ? ' selected' : '') . '>';
        $html .= sr_e((string) $levelValue);
        $html .= '</option>';
    }

    return $html . '</select>';
};
$assetModuleOptions = isset($assetModuleOptions) && is_array($assetModuleOptions) ? $assetModuleOptions : [];
$assetModuleChoiceOptions = [];
foreach ($assetModuleOptions as $assetModule => $assetOption) {
    $assetModuleChoiceOptions[(string) $assetModule] = (string) ($assetOption['label'] ?? $assetModule);
}
$assetDeductionPriorityLabels = [];
foreach (sr_community_asset_deduction_order() as $assetModule) {
    if (isset($assetModuleChoiceOptions[$assetModule])) {
        $assetDeductionPriorityLabels[] = $assetModuleChoiceOptions[$assetModule];
    }
}
$assetDeductionPriorityHelp = $assetDeductionPriorityLabels !== []
    ? sr_t('community::ui.text.706623d8') . implode(' > ', $assetDeductionPriorityLabels)
    : sr_t('community::ui.text.3e195cdd');
$memberGroupAccessHelpModalId = 'community-board-group-member-group-access-help-modal';
$memberGroupAccessHelpBodyHtml = '<p>' . sr_e(sr_t('community::ui.member_group_access_help_policy')) . '</p>'
    . '<ul>'
    . '<li>' . sr_e(sr_t('community::ui.member_group_access_help_empty')) . '</li>'
    . '<li>' . sr_e(sr_t('community::ui.member_group_access_help_auto_read')) . '</li>'
    . '<li>' . sr_e(sr_t('community::ui.member_group_access_help_level')) . '</li>'
    . '</ul>';
$memberGroupAccessLabelHtml = static function (string $forId, string $label) use ($memberGroupAccessHelpModalId): string {
    return '<div class="form-label admin-form-label-help">'
        . '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . sr_t('community::ui.member_group_access_help_open')) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($memberGroupAccessHelpModalId) . '" data-overlay="#' . sr_e($memberGroupAccessHelpModalId) . '">'
        . sr_material_icon_html('help')
        . '</button>'
        . '<label for="' . sr_e($forId) . '">' . sr_e($label) . '</label>'
        . '</div>';
};
$selectedBoardGroup = is_array($editBoardGroup ?? null) ? $editBoardGroup : [];
$formBoardGroup = $communityBoardGroupsPage === 'edit' ? $selectedBoardGroup : [
    'group_key' => '',
    'title' => '',
    'description' => '',
    'status' => 'enabled',
    'sort_order' => 0,
];
$formGroupSettings = [];
if ($communityBoardGroupsPage === 'edit' && isset($formBoardGroup['id'])) {
    $formGroupSettings = is_array($boardGroupSettings[(int) $formBoardGroup['id']] ?? null) ? $boardGroupSettings[(int) $formBoardGroup['id']] : [];
}

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($communityBoardGroupsPage === 'list') { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.all.e078b14a')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.ca286213')); ?> <strong><?php echo sr_e((string) $totalBoardGroups); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups?status=enabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.active.93c558d7')); ?> <?php echo sr_e((string) ($boardGroupStatusCounts['enabled'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups?status=disabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.92cdef3c')); ?> <?php echo sr_e((string) ($boardGroupStatusCounts['disabled'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups?status=archived')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.2e4099ba')); ?> <?php echo sr_e((string) ($boardGroupStatusCounts['archived'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="admin-filter admin-community-board-group-filter ui-form-theme">
        <div class="admin-filter-grid admin-community-board-group-search-grid">
            <div class="admin-filter-field admin-community-board-group-filter-status">
                <label for="community_admin_board_groups_status_filter" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></label>
                <select id="community_admin_board_groups_status_filter" name="status" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($boardGroupListFilters['status'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.all.a4b69faf')); ?></option>
                    <?php foreach ($allowedGroupStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($boardGroupListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-board-group-filter-field">
                <label for="community_admin_board_groups_field" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.search.b79bc9c8')); ?></label>
                <select id="community_admin_board_groups_field" name="field" class="form-select admin-filter-input">
                    <?php foreach (['all' => sr_t('community::ui.all.a4b69faf'), 'key' => 'key', 'title' => sr_t('community::ui.name.253d1510')] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($boardGroupListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-board-group-filter-keyword">
                <label for="community_admin_board_groups_q" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.search.bda397fc')); ?></label>
                <input id="community_admin_board_groups_q" type="search" name="q" value="<?php echo sr_e((string) ($boardGroupListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('community::ui.key.name.7852e80c')); ?>">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('community::ui.list.8cd79e68')); ?></h2>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups/new')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('community::ui.text.1f051912')); ?></a>
        </div>
        <div class="table-wrapper">
        <table class="table admin-community-board-group-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('community::ui.community.list.339c91e7')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>key</th>
                    <th><?php echo sr_e(sr_t('community::ui.name.253d1510')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.text.d6d92d73')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('community::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($boardGroups === []) { ?>
                    <tr>
                        <td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.text.52772f45')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($boardGroups as $boardGroup) { ?>
                    <?php
                    $boardGroupStatus = (string) $boardGroup['status'];
                    $statusClass = match ($boardGroupStatus) {
                        'enabled' => 'is-normal',
                        'disabled' => 'is-blocked',
                        default => 'is-left',
                    };
                    ?>
                    <tr>
                        <td class="admin-table-nowrap community-id"><?php echo sr_e((string) $boardGroup['id']); ?></td>
                        <td class="admin-table-nowrap admin-community-board-group-key-cell"><?php echo sr_e((string) $boardGroup['group_key']); ?></td>
                        <td class="admin-table-break admin-community-board-group-title-cell"><?php echo sr_e((string) $boardGroup['title']); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($boardGroupStatus, 'content_status')); ?></span></td>
                        <td class="admin-table-nowrap">
                            <a href="<?php echo sr_e(sr_url('/admin/community/boards?group_id=' . rawurlencode((string) $boardGroup['id']))); ?>" class="btn btn-sm btn-solid-light">
                                <?php echo sr_e((string) ($boardGroup['board_count'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.8a680106')); ?>
                            </a>
                        </td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/community/board-groups/edit?id=' . rawurlencode((string) $boardGroup['id']))); ?>" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url($communityBoardGroupsPage === 'edit' ? '/admin/community/board-groups/update' : '/admin/community/board-groups/create')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo $communityBoardGroupsPage === 'edit' ? sr_t('community::ui.edit.669f4ac3') : sr_t('community::ui.text.08aafae8'); ?></h2>
            <?php echo sr_csrf_field(); ?>
            <?php if ($communityBoardGroupsPage === 'edit') { ?>
                <input type="hidden" name="group_id" value="<?php echo sr_e((string) $formBoardGroup['id']); ?>">
                <div class="admin-form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('community::ui.key.1057ecca')); ?></span>
                    <div class="admin-form-field">
                        <code><?php echo sr_e((string) $formBoardGroup['group_key']); ?></code>
                    </div>
                </div>
            <?php } else { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_key"><?php echo sr_e(sr_t('community::ui.key.1057ecca')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_key" type="text" name="group_key" maxlength="60" value="<?php echo sr_e($groupField($formBoardGroup, 'group_key')); ?>" class="form-input" pattern="[a-z0-9_]+" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input>
                    </div>
                </div>
            <?php } ?>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_board_groups_title"><?php echo sr_e(sr_t('community::ui.name.253d1510')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_board_groups_title" type="text" name="title" maxlength="120" value="<?php echo sr_e($groupField($formBoardGroup, 'title')); ?>" class="form-input form-control-full" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_board_groups_description"><?php echo sr_e(sr_t('community::ui.text.8c3f651d')); ?></label>
                <div class="admin-form-field">
                    <textarea id="community_admin_board_groups_description" name="description" rows="3" cols="60" class="form-textarea"><?php echo sr_e($groupField($formBoardGroup, 'description')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_board_groups_status"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="community_admin_board_groups_status" name="status" class="form-select">
                                            <?php foreach ($allowedGroupStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $status === $groupField($formBoardGroup, 'status', 'enabled') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_board_groups_sort_order"><?php echo sr_e(sr_t('community::ui.text.7d2dc215')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_board_groups_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e($groupField($formBoardGroup, 'sort_order', '0')); ?>" required class="form-input">
                </div>
            </div>
        </section>

        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('community::ui.settings.021ed27a')); ?></h2>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_read_policy"><?php echo sr_e(sr_t('community::ui.text.0b6c5dfd')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="community_admin_board_groups_group_read_policy" name="group_read_policy" class="form-select" data-community-policy="read">
                                                    <?php foreach ($allowedReadPolicies as $policy) { ?>
                                                        <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'read_policy', 'public') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php echo $memberGroupAccessLabelHtml('community_admin_board_groups_group_read_group_keys', sr_t('community::ui.member.ecf858a4')); ?>
                    <div class="admin-form-field">
                        <?php echo sr_admin_member_group_key_select_html('community_admin_board_groups_group_read_group_keys', 'group_read_group_keys', $groupKeysSettingValue($formGroupSettings, 'read_group_keys'), $enabledMemberGroups); ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_read_min_level"><?php echo sr_e(sr_t('community::ui.text.a783617f')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <?php echo $communityLevelSelectHtml('community_admin_board_groups_group_read_min_level', 'group_read_min_level', (int) $groupSettingValue($formGroupSettings, 'read_min_level', '0')); ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_write_policy"><?php echo sr_e(sr_t('community::ui.text.4f05f6a8')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="community_admin_board_groups_group_write_policy" name="group_write_policy" class="form-select" data-community-policy="write">
                                                    <?php foreach ($allowedWritePolicies as $policy) { ?>
                                                        <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'write_policy', 'member') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php echo $memberGroupAccessLabelHtml('community_admin_board_groups_group_write_group_keys', sr_t('community::ui.member.e99a3ed2')); ?>
                    <div class="admin-form-field">
                        <?php echo sr_admin_member_group_key_select_html('community_admin_board_groups_group_write_group_keys', 'group_write_group_keys', $groupKeysSettingValue($formGroupSettings, 'write_group_keys'), $enabledMemberGroups); ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_write_min_level"><?php echo sr_e(sr_t('community::ui.text.82530158')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <?php echo $communityLevelSelectHtml('community_admin_board_groups_group_write_min_level', 'group_write_min_level', (int) $groupSettingValue($formGroupSettings, 'write_min_level', '0')); ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_comment_policy"><?php echo sr_e(sr_t('community::ui.text.0550e13c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="community_admin_board_groups_group_comment_policy" name="group_comment_policy" class="form-select" data-community-policy="comment">
                                                    <?php foreach ($allowedCommentPolicies as $policy) { ?>
                                                        <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'comment_policy', 'member') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php echo $memberGroupAccessLabelHtml('community_admin_board_groups_group_comment_group_keys', sr_t('community::ui.member.11859d69')); ?>
                    <div class="admin-form-field">
                        <?php echo sr_admin_member_group_key_select_html('community_admin_board_groups_group_comment_group_keys', 'group_comment_group_keys', $groupKeysSettingValue($formGroupSettings, 'comment_group_keys'), $enabledMemberGroups); ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_comment_min_level"><?php echo sr_e(sr_t('community::ui.text.3eccb18c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <?php echo $communityLevelSelectHtml('community_admin_board_groups_group_comment_min_level', 'group_comment_min_level', (int) $groupSettingValue($formGroupSettings, 'comment_min_level', '0')); ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_level_post_score"><?php echo sr_e(sr_t('community::ui.text.99092cba')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_level_post_score" type="number" name="group_level_post_score" min="0" max="10000" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'level_post_score', (string) ($settings['level_post_score'] ?? 10))); ?>" required class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_level_comment_score"><?php echo sr_e(sr_t('community::ui.text.96af1f5c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_level_comment_score" type="number" name="group_level_comment_score" min="0" max="10000" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'level_comment_score', (string) ($settings['level_comment_score'] ?? 2))); ?>" required class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('community::ui.text.c3bd14cb')); ?></span>
                    <div class="admin-form-field">
                        <label class="admin-form-check form-label" for="modules_community_admin_board_groups_group_image_uploads_enabled">
                                                    <input id="modules_community_admin_board_groups_group_image_uploads_enabled" type="checkbox" name="group_image_uploads_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($formGroupSettings, 'image_uploads_enabled', '1'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                                    <?php echo sr_admin_choice_label_html(sr_t('community::ui.text.c3bd14cb')); ?>
                                                </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_attachment_max_bytes"><?php echo sr_e(sr_t('community::ui.bytes.e28899ac')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_attachment_max_bytes" type="number" name="group_attachment_max_bytes" min="1024" max="10485760" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'attachment_max_bytes', '2097152')); ?>" required class="form-input">
                        <p class="admin-form-help"><?php echo sr_e(sr_t('community::ui.bytes.help.f2f708d5')); ?></p>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_attachment_max_count"><?php echo sr_e(sr_t('community::ui.text.bf61ba9f')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_attachment_max_count" type="number" name="group_attachment_max_count" min="0" max="10" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'attachment_max_count', '1')); ?>" required class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('community::ui.text.fe95ead0')); ?></span>
                    <div class="admin-form-field">
                        <label class="admin-form-check form-label" for="modules_community_admin_board_groups_group_file_uploads_enabled">
                                                    <input id="modules_community_admin_board_groups_group_file_uploads_enabled" type="checkbox" name="group_file_uploads_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($formGroupSettings, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                                    <?php echo sr_admin_choice_label_html(sr_t('community::ui.text.fe95ead0')); ?>
                                                </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_file_attachment_max_bytes"><?php echo sr_e(sr_t('community::ui.bytes.9055a3dc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_file_attachment_max_bytes" type="number" name="group_file_attachment_max_bytes" min="1024" max="20971520" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_attachment_max_bytes', '5242880')); ?>" required class="form-input">
                        <p class="admin-form-help"><?php echo sr_e(sr_t('community::ui.bytes.help.f2f708d5')); ?></p>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_file_attachment_max_count"><?php echo sr_e(sr_t('community::ui.text.593790e4')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_file_attachment_max_count" type="number" name="group_file_attachment_max_count" min="0" max="5" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_attachment_max_count', '3')); ?>" required class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php $groupFileExtensionsRequired = in_array($groupSettingValue($formGroupSettings, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) && (int) $groupSettingValue($formGroupSettings, 'file_attachment_max_count', '3') > 0; ?>
                    <label class="form-label" for="community_admin_board_groups_group_file_allowed_extensions"><?php echo sr_e(sr_t('community::ui.text.69600d46')); ?> <span class="sr-required-label" data-community-file-extensions-required<?php echo $groupFileExtensionsRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_file_allowed_extensions" type="text" name="group_file_allowed_extensions" maxlength="1000" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_allowed_extensions', 'pdf,txt,csv,zip,doc,docx,xls,xlsx,ppt,pptx,hwp')); ?>" class="form-input form-control-full" placeholder="pdf, txt, zip" data-community-file-extensions<?php echo $groupFileExtensionsRequired ? ' required' : ''; ?>>
                    </div>
                </div>
        </section>

        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('community::ui.banner.ca341bdf')); ?></h2>
            <?php foreach (sr_community_public_banner_setting_labels() as $bannerSettingKey => $bannerSettingLabel) { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="<?php echo sr_e('community_board_group_' . (string) $bannerSettingKey); ?>"><?php echo sr_e((string) $bannerSettingLabel); ?></label>
                    <div class="admin-form-field">
                        <select id="<?php echo sr_e('community_board_group_' . (string) $bannerSettingKey); ?>" name="<?php echo sr_e('group_' . (string) $bannerSettingKey); ?>" class="form-select form-control-full">
                            <option value="0"><?php echo sr_e(sr_t('community::ui.active.4add3230')); ?></option>
                            <?php foreach ($publicBanners as $publicBanner) { ?>
                                <option value="<?php echo sr_e((string) $publicBanner['id']); ?>"<?php echo (int) $groupSettingValue($formGroupSettings, (string) $bannerSettingKey, '0') === (int) $publicBanner['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $publicBanner['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
        </section>

        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('community::ui.text.63ba6fa3')); ?></h2>
            <?php foreach (sr_community_public_popup_layer_setting_labels() as $popupLayerSettingKey => $popupLayerSettingLabel) { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="<?php echo sr_e('community_board_group_' . (string) $popupLayerSettingKey); ?>"><?php echo sr_e((string) $popupLayerSettingLabel); ?></label>
                    <div class="admin-form-field">
                        <select id="<?php echo sr_e('community_board_group_' . (string) $popupLayerSettingKey); ?>" name="<?php echo sr_e('group_' . (string) $popupLayerSettingKey); ?>" class="form-select form-control-full">
                            <option value="0"><?php echo sr_e(sr_t('community::ui.active.4add3230')); ?></option>
                            <?php foreach ($publicPopupLayers as $publicPopupLayer) { ?>
                                <option value="<?php echo sr_e((string) $publicPopupLayer['id']); ?>"<?php echo (int) $groupSettingValue($formGroupSettings, (string) $popupLayerSettingKey, '0') === (int) $publicPopupLayer['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $publicPopupLayer['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
        </section>

        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('community::ui.member.4eda7ba7')); ?></h2>
            <div class="admin-form-grid">
                <?php foreach ([
                    'post_reward' => sr_t('community::ui.text.a3cc976c'),
                    'comment_reward' => sr_t('community::ui.text.bb39df0e'),
                    'write_charge' => sr_t('community::ui.text.ce1392a2'),
                    'comment_charge' => sr_t('community::ui.text.629c5136'),
                    'paid_read' => sr_t('community::ui.text.c9b3e6f0'),
                    'paid_attachment_download' => sr_t('community::ui.text.5b864b9e'),
                ] as $assetPrefix => $assetLabel) { ?>
                        <?php $assetEnabledId = 'community_board_group_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', (string) $assetPrefix) . '_enabled'; ?>
                        <?php $usesCompositeAsset = sr_community_asset_prefix_uses_composite((string) $assetPrefix); ?>
                        <?php $selectedAssetModules = sr_community_asset_module_keys_from_value($groupSettingValue($formGroupSettings, $assetPrefix . '_asset_module', 'point')); ?>
                        <div class="admin-form-row">
                            <span class="form-label"><?php echo sr_e($assetLabel); ?></span>
                            <div class="admin-form-field">
                                <label class="admin-form-check form-label" for="<?php echo sr_e($assetEnabledId); ?>">
                                    <input id="<?php echo sr_e($assetEnabledId); ?>" type="checkbox" name="<?php echo sr_e('group_' . (string) $assetPrefix); ?>_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($formGroupSettings, $assetPrefix . '_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                    <?php echo sr_admin_choice_label_html($assetLabel . sr_t('community::ui.active.d11d5dbb')); ?>
                                </label>
                                <?php if ($usesCompositeAsset) { ?>
                                    <?php echo sr_admin_checkbox_list_html('community_board_group_' . (string) $assetPrefix . '_asset_module', 'group_' . (string) $assetPrefix . '_asset_module', $assetModuleChoiceOptions, $selectedAssetModules, sr_t('community::ui.text.3e195cdd')); ?>
                                    <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                                <?php } else { ?>
                                    <select name="<?php echo sr_e('group_' . (string) $assetPrefix); ?>_asset_module" class="form-select">
                                        <?php if ($assetModuleOptions === []) { ?>
                                            <option value=""><?php echo sr_e(sr_t('community::ui.text.3e195cdd')); ?></option>
                                        <?php } ?>
                                        <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                            <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo $groupSettingValue($formGroupSettings, $assetPrefix . '_asset_module', (string) ($settings[$assetPrefix . '_asset_module'] ?? 'point')) === (string) $assetModule ? ' selected' : ''; ?>>
                                                <?php echo sr_e((string) $assetOption['label']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                <?php } ?>
                                <input type="number" name="<?php echo sr_e('group_' . (string) $assetPrefix); ?>_amount" min="0" max="999999999" value="<?php echo sr_e($groupSettingValue($formGroupSettings, $assetPrefix . '_amount', (string) ($settings[$assetPrefix . '_amount'] ?? 0))); ?>" class="form-input">
                                <?php if ($assetPrefix === 'paid_read') { ?>
                                    <select name="group_paid_read_charge_policy" class="form-select">
                                        <option value="once"<?php echo $groupSettingValue($formGroupSettings, 'paid_read_charge_policy', 'once') === 'once' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.6eb4fe4e')); ?></option>
                                        <option value="every_view"<?php echo $groupSettingValue($formGroupSettings, 'paid_read_charge_policy', 'once') === 'every_view' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.53e8d077')); ?></option>
                                    </select>
                                <?php } elseif ($assetPrefix === 'paid_attachment_download') { ?>
                                    <select name="group_paid_attachment_download_charge_policy" class="form-select">
                                        <option value="once"<?php echo $groupSettingValue($formGroupSettings, 'paid_attachment_download_charge_policy', 'once') === 'once' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.6eb4fe4e')); ?></option>
                                        <option value="every_download"<?php echo $groupSettingValue($formGroupSettings, 'paid_attachment_download_charge_policy', 'once') === 'every_download' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.e9d14df2')); ?></option>
                                    </select>
                                <?php } ?>
                            </div>
                        </div>
                <?php } ?>
            </div>
        </section>
        <?php if ($communityBoardGroupsPage === 'edit') { ?>
            <section class="admin-card card">
                <h2><?php echo sr_e(sr_t('community::ui.text.206dd316')); ?></h2>
                <p><?php echo sr_e(sr_t('community::ui.settings.select.62b200b8')); ?></p>
                <?php foreach ($settingLabels as $settingKey => $settingLabel) { ?>
                    <label class="admin-form-check form-label" for="modules_community_admin_board_groups_apply_setting_keys">
                        <input id="modules_community_admin_board_groups_apply_setting_keys" type="checkbox" name="apply_setting_keys[]" value="<?php echo sr_e($settingKey); ?>" class="form-checkbox">
                        <?php echo sr_e($settingLabel); ?>
                    </label>
                <?php } ?>
            </section>
        <?php } ?>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo $communityBoardGroupsPage === 'edit' ? sr_t('community::ui.text.086f3a3e') : sr_t('community::ui.text.22129319'); ?></button>
        </div>
    </form>

    <?php echo sr_admin_help_modal_html($memberGroupAccessHelpModalId, sr_t('community::ui.member_group_access_help_title'), $memberGroupAccessHelpBodyHtml); ?>
<?php } ?>

<?php if (in_array($communityBoardGroupsPage, ['new', 'edit'], true)) { ?>
<script>
(function () {
    function syncPolicy(kind) {
        var policy = document.querySelector('[data-community-policy="' + kind + '"]');
        var group = document.getElementById('community_admin_board_groups_group_' + kind + '_group_keys');
        if (!policy || !group) {
            return;
        }
        var first = group.querySelector('input[type="checkbox"]');
        if (first && typeof first.setCustomValidity === 'function') {
            first.setCustomValidity('');
        }
    }

    function mirrorSelectedGroupsToRead(kind) {
        var readGroup = document.getElementById('community_admin_board_groups_group_read_group_keys');
        var sourceGroup = document.getElementById('community_admin_board_groups_group_' + kind + '_group_keys');
        if (!readGroup || !sourceGroup || kind === 'read') {
            return;
        }

        Array.prototype.slice.call(sourceGroup.querySelectorAll('input[type="checkbox"]:checked')).forEach(function (sourceCheck) {
            Array.prototype.slice.call(readGroup.querySelectorAll('input[type="checkbox"]')).forEach(function (readCheck) {
                if (readCheck.value === sourceCheck.value) {
                    readCheck.checked = true;
                }
            });
        });
    }

    function syncWritableGroupsFromRead() {
        var readGroup = document.getElementById('community_admin_board_groups_group_read_group_keys');
        if (!readGroup) {
            return;
        }

        var readable = {};
        Array.prototype.slice.call(readGroup.querySelectorAll('input[type="checkbox"]:checked')).forEach(function (readCheck) {
            readable[readCheck.value] = true;
        });
        ['write', 'comment'].forEach(function (kind) {
            var group = document.getElementById('community_admin_board_groups_group_' + kind + '_group_keys');
            if (!group) {
                return;
            }
            Array.prototype.slice.call(group.querySelectorAll('input[type="checkbox"]:checked')).forEach(function (check) {
                if (!readable[check.value]) {
                    check.checked = false;
                }
            });
        });
    }

    function syncFileExtensions() {
        var count = document.getElementById('community_admin_board_groups_group_file_attachment_max_count');
        var enabled = document.getElementById('modules_community_admin_board_groups_group_file_uploads_enabled');
        var input = document.querySelector('[data-community-file-extensions]');
        var label = document.querySelector('[data-community-file-extensions-required]');
        var needed = !!(enabled && enabled.checked && count && parseInt(count.value || '0', 10) > 0);
        if (input) {
            input.required = needed;
        }
        if (label) {
            label.hidden = !needed;
        }
    }

    ['read', 'write', 'comment'].forEach(function (kind) {
        var policy = document.querySelector('[data-community-policy="' + kind + '"]');
        var group = document.getElementById('community_admin_board_groups_group_' + kind + '_group_keys');
        if (policy) {
            policy.addEventListener('change', function () { syncPolicy(kind); });
        }
        if (group) {
            group.addEventListener('change', function () {
                if (kind === 'read') {
                    syncWritableGroupsFromRead();
                } else {
                    mirrorSelectedGroupsToRead(kind);
                }
                syncPolicy(kind);
                syncPolicy('read');
            });
        }
        syncPolicy(kind);
    });
    var form = document.querySelector('.admin-page-community-board-group-form form.admin-form');
    if (form) {
        form.addEventListener('submit', syncWritableGroupsFromRead);
    }
    syncWritableGroupsFromRead();
    syncPolicy('read');
    var count = document.getElementById('community_admin_board_groups_group_file_attachment_max_count');
    var enabled = document.getElementById('modules_community_admin_board_groups_group_file_uploads_enabled');
    if (count) {
        count.addEventListener('input', syncFileExtensions);
        count.addEventListener('change', syncFileExtensions);
    }
    if (enabled) {
        enabled.addEventListener('change', syncFileExtensions);
    }
    syncFileExtensions();
})();
</script>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
