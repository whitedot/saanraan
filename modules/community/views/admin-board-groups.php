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
$boardGroupSort = isset($boardGroupSort) && is_array($boardGroupSort) ? $boardGroupSort : sr_community_admin_board_group_default_sort();
$boardGroupStatusCounts = isset($boardGroupStatusCounts) && is_array($boardGroupStatusCounts) ? $boardGroupStatusCounts : [];
$totalBoardGroups = (int) ($boardGroupStatusCounts['total'] ?? count($boardGroups ?? []));

$settingLabels = [
    'post_editor' => '게시글 에디터',
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
$communityLevelSelectHtml = static function (string $id, string $name, int $selectedLevel) use ($settings): string {
    $selectedLevel = sr_community_normalize_level_value($selectedLevel, $settings);
    $html = '<select id="' . sr_e($id) . '" name="' . sr_e($name) . '" class="form-select">';
    for ($levelValue = 0; $levelValue <= sr_community_max_level_value($settings); $levelValue++) {
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
    ? sr_t('community::ui.text.706623d8') . implode(', ', $assetDeductionPriorityLabels)
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
$communityBoardGroupHelpOpenLabel = sr_t('community::help.open');
$communityBoardGroupHelpButtonHtml = static function (string $label, string $modalId) use ($communityBoardGroupHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $communityBoardGroupHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$communityBoardGroupHelpBodyHtml = static function (array $keys): string {
    $html = '';
    foreach ($keys as $key) {
        $html .= '<p>' . sr_e(sr_t((string) $key)) . '</p>';
    }

    return $html;
};
$communityBoardGroupHelp = [
    'status' => [
        'id' => 'community_board_group_help_status',
        'title' => sr_t('community::help.status.title'),
        'body' => $communityBoardGroupHelpBodyHtml(['community::help.status.body.1', 'community::help.status.body.2', 'community::help.status.body.3']),
    ],
    'policy' => [
        'id' => 'community_board_group_help_policy',
        'title' => sr_t('community::help.policy.title'),
        'body' => $communityBoardGroupHelpBodyHtml(['community::help.policy.body.1', 'community::help.policy.body.2']),
    ],
    'min_level' => [
        'id' => 'community_board_group_help_min_level',
        'title' => sr_t('community::help.min_level.title'),
        'body' => $communityBoardGroupHelpBodyHtml(['community::help.min_level.body.1', 'community::help.min_level.body.2']),
    ],
    'attachments' => [
        'id' => 'community_board_group_help_attachments',
        'title' => sr_t('community::help.attachments.title'),
        'body' => $communityBoardGroupHelpBodyHtml(['community::help.attachments.body.1', 'community::help.attachments.body.2']),
    ],
    'file_extensions' => [
        'id' => 'community_board_group_help_file_extensions',
        'title' => sr_t('community::help.file_extensions.title'),
        'body' => $communityBoardGroupHelpBodyHtml(['community::help.file_extensions.body.1', 'community::help.file_extensions.body.2']),
    ],
    'display_banner' => [
        'id' => 'community_board_group_help_display_banner',
        'title' => sr_t('community::help.display_banner.title'),
        'body' => $communityBoardGroupHelpBodyHtml(['community::help.display_banner.body.1', 'community::help.display_banner.body.2']),
    ],
    'display_popup' => [
        'id' => 'community_board_group_help_display_popup',
        'title' => sr_t('community::help.display_popup.title'),
        'body' => $communityBoardGroupHelpBodyHtml(['community::help.display_popup.body.1', 'community::help.display_popup.body.2']),
    ],
    'asset_settings' => [
        'id' => 'community_board_group_help_asset_settings',
        'title' => sr_t('community::help.asset_settings.title'),
        'body' => $communityBoardGroupHelpBodyHtml(['community::help.asset_settings.body.1', 'community::help.asset_settings.body.2', 'community::help.asset_settings.body.3']),
    ],
    'sort_order' => [
        'id' => 'community_board_group_help_sort_order',
        'title' => sr_t('community::help.sort_order.title'),
        'body' => $communityBoardGroupHelpBodyHtml(['community::help.sort_order.body.1', 'community::help.sort_order.body.2']),
    ],
];
$selectedBoardGroup = is_array($editBoardGroup ?? null) ? $editBoardGroup : [];
$formBoardGroup = $communityBoardGroupsPage === 'edit' ? $selectedBoardGroup : [
    'group_key' => '',
    'title' => '',
    'description' => '',
    'status' => 'enabled',
    'sort_order' => 0,
];
$formGroupSettings = [];
if ($communityBoardGroupsPage === 'new') {
    $formGroupSettings = sr_community_board_group_default_settings($settings);
}
if ($communityBoardGroupsPage === 'edit' && isset($formBoardGroup['id'])) {
    $formGroupSettings = is_array($boardGroupSettings[(int) $formBoardGroup['id']] ?? null) ? $boardGroupSettings[(int) $formBoardGroup['id']] : [];
}
$communityBoardGroupAssetAuditUrl = $communityBoardGroupsPage === 'edit'
    ? sr_admin_asset_settings_audit_url('community.board_group.asset_settings.updated', 'community_board_group', (string) (int) ($formBoardGroup['id'] ?? 0))
    : '';

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
        <div class="admin-list-summary-row">
            <?php if (empty($boardGroupSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_community_admin_board_group_sort_options(), sr_community_admin_board_group_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="게시판 그룹 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($boardGroupPagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table admin-community-board-group-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('community::ui.community.list.339c91e7')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th<?php echo sr_admin_sort_aria('group_key', $boardGroupSort); ?>><?php echo sr_admin_sort_header_html('key', 'group_key', $boardGroupSort, sr_community_admin_board_group_sort_options(), sr_community_admin_board_group_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('title', $boardGroupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.name.253d1510'), 'title', $boardGroupSort, sr_community_admin_board_group_sort_options(), sr_community_admin_board_group_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $boardGroupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.status.e10195a1'), 'status', $boardGroupSort, sr_community_admin_board_group_sort_options(), sr_community_admin_board_group_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('board_count', $boardGroupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.d6d92d73'), 'board_count', $boardGroupSort, sr_community_admin_board_group_sort_options(), sr_community_admin_board_group_default_sort()); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('community::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($boardGroups === []) { ?>
                    <tr>
                        <td colspan="5" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.text.52772f45')); ?></td>
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
                                <a href="<?php echo sr_e(sr_url('/admin/community/board-groups/edit?id=' . rawurlencode((string) $boardGroup['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php echo sr_admin_pagination_html($boardGroupPagination, '게시판 그룹 목록 페이지'); ?>
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
                        <input id="community_admin_board_groups_group_key" type="text" name="group_key" maxlength="60" value="<?php echo sr_e($groupField($formBoardGroup, 'group_key')); ?>" class="form-input" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input>
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
                <?php echo sr_admin_form_label_help_html('community_admin_board_groups_status', sr_t('community::ui.status.e10195a1'), $communityBoardGroupHelp['status']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="community_admin_board_groups_status" name="status" class="form-select">
                                            <?php foreach ($allowedGroupStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $status === $groupField($formBoardGroup, 'status', 'enabled') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_board_groups_sort_order', sr_t('community::ui.text.7d2dc215'), $communityBoardGroupHelp['sort_order']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <input id="community_admin_board_groups_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e($groupField($formBoardGroup, 'sort_order', '0')); ?>" required class="form-input">
                </div>
            </div>
        </section>

        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('community::ui.settings.021ed27a')); ?></h2>
                <p class="admin-form-help"><?php echo sr_e(sr_t('community::ui.group_defaults_help')); ?></p>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_post_editor">게시글 에디터 <span class="sr-required-label">(필수)</span></label>
                    <div class="admin-form-field">
                        <select id="community_admin_board_groups_group_post_editor" name="group_post_editor" class="form-select" required>
                            <?php foreach ($editorOptions as $editorKey => $editorLabel) { ?>
                                <option value="<?php echo sr_e((string) $editorKey); ?>"<?php echo $groupSettingValue($formGroupSettings, 'post_editor', 'textarea') === (string) $editorKey ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $editorLabel); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <p class="admin-form-help">새 게시판을 만들 때 참고할 그룹 기본값입니다. 기존 게시판 값은 자동 변경되지 않습니다.</p>
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php echo sr_admin_form_label_help_html('community_admin_board_groups_group_read_policy', sr_t('community::ui.text.0b6c5dfd'), $communityBoardGroupHelp['policy']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
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
                    <?php echo sr_admin_form_label_help_html('community_admin_board_groups_group_read_min_level', sr_t('community::ui.text.a783617f'), $communityBoardGroupHelp['min_level']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
                    <div class="admin-form-field">
                        <?php echo $communityLevelSelectHtml('community_admin_board_groups_group_read_min_level', 'group_read_min_level', (int) $groupSettingValue($formGroupSettings, 'read_min_level', '0')); ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php echo sr_admin_form_label_help_html('community_admin_board_groups_group_write_policy', sr_t('community::ui.text.4f05f6a8'), $communityBoardGroupHelp['policy']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
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
                    <?php echo sr_admin_form_label_help_html('community_admin_board_groups_group_write_min_level', sr_t('community::ui.text.82530158'), $communityBoardGroupHelp['min_level']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
                    <div class="admin-form-field">
                        <?php echo $communityLevelSelectHtml('community_admin_board_groups_group_write_min_level', 'group_write_min_level', (int) $groupSettingValue($formGroupSettings, 'write_min_level', '0')); ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php echo sr_admin_form_label_help_html('community_admin_board_groups_group_comment_policy', sr_t('community::ui.text.0550e13c'), $communityBoardGroupHelp['policy']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
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
                    <?php echo sr_admin_form_label_help_html('community_admin_board_groups_group_comment_min_level', sr_t('community::ui.text.3eccb18c'), $communityBoardGroupHelp['min_level']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
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
                    <div class="form-label admin-form-label-help"><?php echo $communityBoardGroupHelpButtonHtml(sr_t('community::ui.text.c3bd14cb'), $communityBoardGroupHelp['attachments']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.c3bd14cb')); ?></span></div>
                    <div class="admin-form-field">
                        <label class="admin-form-check form-label" for="modules_community_admin_board_groups_group_image_uploads_enabled">
                                                    <input id="modules_community_admin_board_groups_group_image_uploads_enabled" type="checkbox" name="group_image_uploads_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($formGroupSettings, 'image_uploads_enabled', '1'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                                    <?php echo sr_admin_choice_label_html(sr_t('community::ui.text.c3bd14cb')); ?>
                                                </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php echo sr_admin_form_label_help_html('community_admin_board_groups_group_attachment_max_bytes', sr_t('community::ui.bytes.e28899ac'), $communityBoardGroupHelp['attachments']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
                    <div class="admin-form-field">
                        <div class="input-group admin-input-unit">
                            <input id="community_admin_board_groups_group_attachment_max_bytes" type="number" name="group_attachment_max_bytes" min="1024" max="10485760" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'attachment_max_bytes', '2097152')); ?>" required class="form-input">
                            <span class="input-group-text">bytes</span>
                        </div>
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php echo sr_admin_form_label_help_html('community_admin_board_groups_group_attachment_max_count', sr_t('community::ui.text.bf61ba9f'), $communityBoardGroupHelp['attachments']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_attachment_max_count" type="number" name="group_attachment_max_count" min="0" max="10" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'attachment_max_count', '1')); ?>" required class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="form-label admin-form-label-help"><?php echo $communityBoardGroupHelpButtonHtml(sr_t('community::ui.text.fe95ead0'), $communityBoardGroupHelp['attachments']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.fe95ead0')); ?></span></div>
                    <div class="admin-form-field">
                        <label class="admin-form-check form-label" for="modules_community_admin_board_groups_group_file_uploads_enabled">
                                                    <input id="modules_community_admin_board_groups_group_file_uploads_enabled" type="checkbox" name="group_file_uploads_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($formGroupSettings, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                                    <?php echo sr_admin_choice_label_html(sr_t('community::ui.text.fe95ead0')); ?>
                                                </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php echo sr_admin_form_label_help_html('community_admin_board_groups_group_file_attachment_max_bytes', sr_t('community::ui.bytes.9055a3dc'), $communityBoardGroupHelp['attachments']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
                    <div class="admin-form-field">
                        <div class="input-group admin-input-unit">
                            <input id="community_admin_board_groups_group_file_attachment_max_bytes" type="number" name="group_file_attachment_max_bytes" min="1024" max="20971520" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_attachment_max_bytes', '5242880')); ?>" required class="form-input">
                            <span class="input-group-text">bytes</span>
                        </div>
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php echo sr_admin_form_label_help_html('community_admin_board_groups_group_file_attachment_max_count', sr_t('community::ui.text.593790e4'), $communityBoardGroupHelp['attachments']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_file_attachment_max_count" type="number" name="group_file_attachment_max_count" min="0" max="5" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_attachment_max_count', '3')); ?>" required class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <?php $groupFileExtensionsRequired = in_array($groupSettingValue($formGroupSettings, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) && (int) $groupSettingValue($formGroupSettings, 'file_attachment_max_count', '3') > 0; ?>
                    <div class="form-label admin-form-label-help"><?php echo $communityBoardGroupHelpButtonHtml(sr_t('community::ui.text.69600d46'), $communityBoardGroupHelp['file_extensions']['id']); ?><label for="community_admin_board_groups_group_file_allowed_extensions"><?php echo sr_e(sr_t('community::ui.text.69600d46')); ?> <span class="sr-required-label" data-community-file-extensions-required<?php echo $groupFileExtensionsRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label></div>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_file_allowed_extensions" type="text" name="group_file_allowed_extensions" maxlength="1000" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_allowed_extensions', 'pdf,txt,csv,zip,doc,docx,xls,xlsx,ppt,pptx,hwp')); ?>" class="form-input form-control-full" placeholder="pdf, txt, zip" data-community-file-extensions<?php echo $groupFileExtensionsRequired ? ' required' : ''; ?>>
                    </div>
                </div>
        </section>

        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('community::ui.banner.ca341bdf')); ?></h2>
            <?php foreach (sr_community_public_banner_setting_labels() as $bannerSettingKey => $bannerSettingLabel) { ?>
                <div class="admin-form-row">
                    <div class="form-label admin-form-label-help"><?php echo $communityBoardGroupHelpButtonHtml((string) $bannerSettingLabel, $communityBoardGroupHelp['display_banner']['id']); ?><label for="<?php echo sr_e('community_board_group_' . (string) $bannerSettingKey); ?>"><?php echo sr_e((string) $bannerSettingLabel); ?></label></div>
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
                    <div class="form-label admin-form-label-help"><?php echo $communityBoardGroupHelpButtonHtml((string) $popupLayerSettingLabel, $communityBoardGroupHelp['display_popup']['id']); ?><label for="<?php echo sr_e('community_board_group_' . (string) $popupLayerSettingKey); ?>"><?php echo sr_e((string) $popupLayerSettingLabel); ?></label></div>
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
            <h2>
                <span><?php echo sr_e(sr_t('community::ui.member.4eda7ba7')); ?></span>
                <?php if ($communityBoardGroupAssetAuditUrl !== '') { ?>
                    <span class="admin-form-actions">
                        <a href="<?php echo sr_e($communityBoardGroupAssetAuditUrl); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('자산 변경 이력'); ?></a>
                    </span>
                <?php } ?>
            </h2>
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
                        <?php $assetSourceId = 'community_board_group_' . (string) $assetPrefix . '_asset_source'; ?>
                        <?php $usesCompositeAsset = sr_community_asset_prefix_uses_composite((string) $assetPrefix); ?>
                        <?php $usesGroupedAssetAmounts = $usesCompositeAsset; ?>
                        <?php $selectedAssetModules = sr_community_asset_module_keys_from_value($groupSettingValue($formGroupSettings, $assetPrefix . '_asset_module', ''), true); ?>
                        <div class="admin-form-row">
                            <div class="form-label admin-form-label-help"><?php echo $communityBoardGroupHelpButtonHtml($assetLabel, $communityBoardGroupHelp['asset_settings']['id']); ?><span><?php echo sr_e($assetLabel); ?></span></div>
                            <div class="admin-form-field">
                                <label class="admin-form-check form-label" for="<?php echo sr_e($assetEnabledId); ?>">
                                    <input id="<?php echo sr_e($assetEnabledId); ?>" type="checkbox" name="<?php echo sr_e('group_' . (string) $assetPrefix); ?>_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($formGroupSettings, $assetPrefix . '_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                    <?php echo sr_admin_choice_label_html($assetLabel . sr_t('community::ui.active.d11d5dbb')); ?>
                                </label>
                                <?php if ($usesGroupedAssetAmounts) { ?>
                                    <input type="hidden" name="<?php echo sr_e('group_' . (string) $assetPrefix); ?>_amount" value="<?php echo sr_e($groupSettingValue($formGroupSettings, $assetPrefix . '_amount', (string) ($settings[$assetPrefix . '_amount'] ?? 0))); ?>">
                                <?php } ?>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <span class="form-label"><?php echo sr_e($assetLabel . ' 자산'); ?></span>
                            <div class="admin-form-field">
                                <?php if ($usesGroupedAssetAmounts) { ?>
                                    <div class="admin-asset-setting-target" data-admin-asset-enable-target="#<?php echo sr_e($assetEnabledId); ?>">
                                        <?php echo sr_community_asset_grouped_amount_inputs_html($assetSourceId, 'group_' . (string) $assetPrefix . '_asset_module', 'group_' . (string) $assetPrefix . '_amounts', $assetModuleOptions, $selectedAssetModules, $groupSettingValue($formGroupSettings, $assetPrefix . '_amounts_json', (string) ($settings[$assetPrefix . '_amounts_json'] ?? '')), (int) $groupSettingValue($formGroupSettings, $assetPrefix . '_amount', (string) ($settings[$assetPrefix . '_amount'] ?? 0)), sr_t('community::ui.asset.amount.0df01f4b', ['label' => $assetLabel]), sr_t('community::ui.text.3e195cdd')); ?>
                                    </div>
                                <?php } else { ?>
                                    <div class="admin-asset-setting-target admin-asset-single-setting-target" data-admin-asset-enable-target="#<?php echo sr_e($assetEnabledId); ?>">
                                        <select id="<?php echo sr_e($assetSourceId); ?>" name="<?php echo sr_e('group_' . (string) $assetPrefix); ?>_asset_module" class="form-select" data-admin-asset-unit-select>
                                            <option value=""><?php echo sr_e($assetModuleOptions === [] ? sr_t('community::ui.text.3e195cdd') : sr_t('community::ui.text.asset_none')); ?></option>
                                            <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                <option value="<?php echo sr_e((string) $assetModule); ?>" data-admin-asset-unit="<?php echo sr_e((string) ($assetOption['unit_label'] ?? '')); ?>"<?php echo $groupSettingValue($formGroupSettings, $assetPrefix . '_asset_module', (string) ($settings[$assetPrefix . '_asset_module'] ?? '')) === (string) $assetModule ? ' selected' : ''; ?>>
                                                    <?php echo sr_e((string) $assetOption['label']); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                        <?php echo sr_community_asset_single_amount_input_group_html('group_' . (string) $assetPrefix . '_amount', (int) $groupSettingValue($formGroupSettings, $assetPrefix . '_amount', (string) ($settings[$assetPrefix . '_amount'] ?? 0)), $assetModuleOptions, $groupSettingValue($formGroupSettings, $assetPrefix . '_asset_module', (string) ($settings[$assetPrefix . '_asset_module'] ?? '')), sr_t('community::ui.asset.amount.0df01f4b', ['label' => $assetLabel])); ?>
                                    </div>
                                <?php } ?>
                                <?php if ($usesCompositeAsset) { ?>
                                    <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e('community_board_group_' . (string) $assetPrefix . '_policy_set_ids'); ?>"><?php echo sr_e('멤버 그룹별 적용'); ?></label>
                            <div class="admin-form-field admin-policy-set-field">
                                <?php echo sr_community_asset_policy_set_checkboxes_html('community_board_group_' . (string) $assetPrefix . '_policy_set_ids', 'group_' . (string) $assetPrefix . '_policy_set_ids', $assetPolicySets ?? [], sr_community_asset_policy_set_ids_with_legacy($groupSettingValue($formGroupSettings, $assetPrefix . '_group_policies_json', (string) ($settings[$assetPrefix . '_group_policies_json'] ?? '')), (int) $groupSettingValue($formGroupSettings, $assetPrefix . '_policy_set_id', (string) ($settings[$assetPrefix . '_policy_set_id'] ?? 0))), $usesCompositeAsset ? 'use' : 'grant', '#' . $assetSourceId, $pdo); ?>
                                <p class="admin-form-help">도움말: 선택한 멤버 그룹별 적용이 멤버의 그룹, 레벨, 대상 자산에 맞는 실제 금액을 계산합니다. 세트의 계산 방식과 조정값은 커뮤니티 멤버 그룹별 적용 화면에서 관리합니다.</p>
                            </div>
                        </div>
                        <?php if ($assetPrefix === 'paid_read') { ?>
                            <div class="admin-form-row">
                                <label class="form-label" for="community_board_group_paid_read_charge_policy"><?php echo sr_e(sr_t('community::ui.text.05ead7ab')); ?></label>
                                <div class="admin-form-field">
                                    <select id="community_board_group_paid_read_charge_policy" name="group_paid_read_charge_policy" class="form-select">
                                        <option value="once"<?php echo $groupSettingValue($formGroupSettings, 'paid_read_charge_policy', 'once') === 'once' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.6eb4fe4e')); ?></option>
                                        <option value="every_view"<?php echo $groupSettingValue($formGroupSettings, 'paid_read_charge_policy', 'once') === 'every_view' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.53e8d077')); ?></option>
                                    </select>
                                </div>
                            </div>
                        <?php } elseif ($assetPrefix === 'paid_attachment_download') { ?>
                            <div class="admin-form-row">
                                <label class="form-label" for="community_board_group_paid_attachment_download_charge_policy"><?php echo sr_e(sr_t('community::ui.text.978f8b2e')); ?></label>
                                <div class="admin-form-field">
                                    <select id="community_board_group_paid_attachment_download_charge_policy" name="group_paid_attachment_download_charge_policy" class="form-select">
                                        <option value="once"<?php echo $groupSettingValue($formGroupSettings, 'paid_attachment_download_charge_policy', 'once') === 'once' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.6eb4fe4e')); ?></option>
                                        <option value="every_download"<?php echo $groupSettingValue($formGroupSettings, 'paid_attachment_download_charge_policy', 'once') === 'every_download' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.e9d14df2')); ?></option>
                                    </select>
                                </div>
                            </div>
                        <?php } ?>
                <?php } ?>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo $communityBoardGroupsPage === 'edit' ? sr_t('community::ui.text.086f3a3e') : sr_t('community::ui.text.22129319'); ?></button>
        </div>
    </form>

    <?php echo sr_admin_help_modal_html($memberGroupAccessHelpModalId, sr_t('community::ui.member_group_access_help_title'), $memberGroupAccessHelpBodyHtml); ?>
    <?php foreach ($communityBoardGroupHelp as $communityBoardGroupHelpModal) { ?>
        <?php echo sr_admin_help_modal_html((string) $communityBoardGroupHelpModal['id'], (string) $communityBoardGroupHelpModal['title'], (string) $communityBoardGroupHelpModal['body']); ?>
    <?php } ?>
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
