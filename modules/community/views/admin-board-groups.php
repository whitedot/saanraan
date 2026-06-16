<?php

$communityBoardGroupsPage = isset($communityBoardGroupsPage) ? (string) $communityBoardGroupsPage : 'list';
$adminPageTitle = '커뮤니티 게시판 그룹 관리';
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
$boardGroupListFilters = isset($boardGroupListFilters) && is_array($boardGroupListFilters) ? $boardGroupListFilters : ['status' => [], 'field' => 'all', 'q' => ''];
$boardGroupSort = isset($boardGroupSort) && is_array($boardGroupSort) ? $boardGroupSort : sr_community_admin_board_group_default_sort();
$boardGroupStatusCounts = isset($boardGroupStatusCounts) && is_array($boardGroupStatusCounts) ? $boardGroupStatusCounts : [];
$totalBoardGroups = (int) ($boardGroupStatusCounts['total'] ?? count($boardGroups ?? []));
$selectedBoardGroupStatuses = is_array($boardGroupListFilters['status'] ?? null) ? $boardGroupListFilters['status'] : [];
$adminPageTitleUrl = sr_admin_page_title_reset_url($communityBoardGroupsPage === 'list', '/admin/community/board-groups');

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
$privacyConsentDocumentOptions = sr_community_privacy_consent_policy_document_options($pdo, (string) ($settings['privacy_consent_document_key'] ?? ''));
if (isset($formGroupSettings) && is_array($formGroupSettings)) {
    $privacyConsentDocumentOptions += sr_community_privacy_consent_policy_document_options($pdo, (string) ($formGroupSettings['privacy_consent_document_key'] ?? ''));
}
foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) {
    $privacyConsentDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey);
    $privacyConsentDocumentOptions += sr_community_privacy_consent_policy_document_options($pdo, (string) ($settings[$privacyConsentDocumentSettingKey] ?? ''));
    if (isset($formGroupSettings) && is_array($formGroupSettings)) {
        $privacyConsentDocumentOptions += sr_community_privacy_consent_policy_document_options($pdo, (string) ($formGroupSettings[$privacyConsentDocumentSettingKey] ?? ''));
    }
}
$privacyConsentDocumentSelectOptionsHtml = static function (string $selectedDocumentKey) use ($privacyConsentDocumentOptions): string {
    $html = '<option value="">' . sr_e('선택 안 함') . '</option>';
    foreach ($privacyConsentDocumentOptions as $privacyConsentDocumentKey => $privacyConsentDocumentOption) {
        $privacyConsentDocumentTitle = is_array($privacyConsentDocumentOption)
            ? (string) ($privacyConsentDocumentOption['title'] ?? $privacyConsentDocumentKey)
            : (string) $privacyConsentDocumentOption;
        $html .= '<option value="' . sr_e((string) $privacyConsentDocumentKey) . '"' . ($selectedDocumentKey === (string) $privacyConsentDocumentKey ? ' selected' : '') . '>'
            . sr_e($privacyConsentDocumentTitle)
            . '</option>';
    }

    return $html;
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
$reactionPresetOptions = isset($reactionPresetOptions) && is_array($reactionPresetOptions) ? $reactionPresetOptions : ['' => '리액션 기본값'];
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
    return '<div class="form-label form-label-help">'
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
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.ca286213')); ?> <strong><?php echo sr_e((string) $totalBoardGroups); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups?status=enabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.active.93c558d7')); ?> <?php echo sr_e((string) ($boardGroupStatusCounts['enabled'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups?status=disabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.92cdef3c')); ?> <?php echo sr_e((string) ($boardGroupStatusCounts['disabled'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups?status=archived')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.2e4099ba')); ?> <?php echo sr_e((string) ($boardGroupStatusCounts['archived'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        </div>
    </div>

    <?php $boardGroupDetailFilterOpen = $selectedBoardGroupStatuses !== []; ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="filtering-form admin-community-board-group-filter ui-form-theme">
        <div class="filtering-fields admin-community-board-group-search-grid">
            <div class="filtering filtering-card<?php echo $boardGroupDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
                <div class="filtering-fields">
                    <div class="filtering-field admin-community-board-group-filter-field">
                        <label for="community_admin_board_groups_field" class="filtering-label">검색조건</label>
                        <select id="community_admin_board_groups_field" name="field" class="form-select filtering-input">
                            <?php foreach (['all' => sr_t('community::ui.all.a4b69faf'), 'key' => sr_t('community::ui.key.1057ecca'), 'title' => sr_t('community::ui.name.253d1510')] as $fieldValue => $fieldLabel) { ?>
                                <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($boardGroupListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                                    <?php echo sr_e($fieldLabel); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filtering-field-fill filtering-field admin-community-board-group-filter-keyword">
                        <label for="community_admin_board_groups_q" class="filtering-label"><?php echo sr_e(sr_t('community::ui.search.bda397fc')); ?></label>
                        <input id="community_admin_board_groups_q" type="text" name="q" value="<?php echo sr_e((string) ($boardGroupListFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('community::ui.key.name.7852e80c')); ?>">
                    </div>
                </div>
                <div id="community_admin_board_groups_detail_filters" class="filtering-body" data-filtering-body<?php echo $boardGroupDetailFilterOpen ? '' : ' hidden'; ?>>
                    <div class="filtering-field admin-community-board-group-filter-status">
                        <span class="filtering-label"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></span>
                        <?php echo sr_admin_filter_toggle_group_html('community_admin_board_groups_status_filter', 'status', sr_admin_code_label_options($allowedGroupStatuses, 'content_status'), $selectedBoardGroupStatuses, sr_t('community::ui.all.a4b69faf')); ?>
                    </div>
                </div>
                <div class="filtering-actions">
                    <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $boardGroupDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="community_admin_board_groups_detail_filters">상세검색</button>
                    <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                    <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
                </div>
            </div>
        </div>
    </form>

    <section class="card admin-list-card admin-list-form">
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
        <table class="table table-list admin-community-board-group-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('community::ui.community.list.339c91e7')); ?></caption>
            <thead>
                <tr>
                    <th<?php echo sr_admin_sort_aria('group_key', $boardGroupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.key.1057ecca'), 'group_key', $boardGroupSort, sr_community_admin_board_group_sort_options(), sr_community_admin_board_group_default_sort()); ?></th>
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
                                <a href="<?php echo sr_e(sr_url('/admin/community/boards/new?group_id=' . rawurlencode((string) $boardGroup['id']))); ?>" class="btn btn-sm btn-icon btn-solid-light" aria-label="이 그룹에 게시판 추가" title="이 그룹에 게시판 추가"><?php echo sr_material_icon_html('add'); ?></a>
                                <a href="<?php echo sr_e(sr_url('/admin/community/board-groups/edit?id=' . rawurlencode((string) $boardGroup['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="admin-inline-form">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="delete_group">
                                    <input type="hidden" name="group_id" value="<?php echo sr_e((string) $boardGroup['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="게시판 그룹 삭제" title="게시판 그룹 삭제" onclick="return confirm('이 게시판 그룹을 삭제할까요? 연결 게시판은 삭제하지 않고 그룹 연결만 해제합니다. 외부 참조가 있으면 삭제되지 않습니다.');"><?php echo sr_material_icon_html('delete'); ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('add'); ?> 이 그룹에 게시판 추가</span>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> <?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?></span>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('delete'); ?> 게시판 그룹 삭제</span>
    </div>
    <?php echo sr_admin_status_description_list_html('content_status', sr_admin_code_label_options(['enabled', 'disabled'], 'content_status')); ?>
</section>
    <?php echo sr_admin_pagination_html($boardGroupPagination, '게시판 그룹 목록 페이지'); ?>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url($communityBoardGroupsPage === 'edit' ? '/admin/community/board-groups/update' : '/admin/community/board-groups/create')); ?>" class="admin-form ui-form-theme">
        <section id="community-board-group-section-basic" class="card" data-admin-section-anchor>
            <h2><?php echo sr_e($communityBoardGroupsPage === 'edit' ? sr_t('community::ui.edit.669f4ac3') : sr_t('community::ui.text.08aafae8')); ?></h2>
            <p class="form-help">게시판 그룹은 여러 게시판을 묶어 공개 목록과 사이트 메뉴 후보로 관리하는 운영 단위입니다.</p>
            <?php echo sr_csrf_field(); ?>
            <?php if ($communityBoardGroupsPage === 'edit') { ?>
                <input type="hidden" name="group_id" value="<?php echo sr_e((string) $formBoardGroup['id']); ?>">
                <div class="form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('community::ui.key.1057ecca')); ?></span>
                    <div class="form-field">
                        <code><?php echo sr_e((string) $formBoardGroup['group_key']); ?></code>
                    </div>
                </div>
            <?php } else { ?>
                <div class="form-row">
                    <label class="form-label" for="community_admin_board_groups_group_key"><?php echo sr_e(sr_t('community::ui.key.1057ecca')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <input id="community_admin_board_groups_group_key" type="text" name="group_key" maxlength="60" value="<?php echo sr_e($groupField($formBoardGroup, 'group_key')); ?>" class="form-input" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input data-admin-key-suggest-source="#community_admin_board_groups_title" data-admin-key-suggest-fallback="board_group">
                    </div>
                </div>
            <?php } ?>
            <div class="form-row">
                <label class="form-label" for="community_admin_board_groups_title"><?php echo sr_e(sr_t('community::ui.name.253d1510')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="form-field">
                    <input id="community_admin_board_groups_title" type="text" name="title" maxlength="120" value="<?php echo sr_e($groupField($formBoardGroup, 'title')); ?>" class="form-input form-control-full" required>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="community_admin_board_groups_description"><?php echo sr_e(sr_t('community::ui.text.8c3f651d')); ?></label>
                <div class="form-field">
                    <textarea id="community_admin_board_groups_description" name="description" rows="3" cols="60" class="form-textarea"><?php echo sr_e($groupField($formBoardGroup, 'description')); ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_board_groups_status', sr_t('community::ui.status.e10195a1'), $communityBoardGroupHelp['status']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="community_admin_board_groups_status" name="status" class="form-select">
                                            <?php foreach ($allowedGroupStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $status === $groupField($formBoardGroup, 'status', 'enabled') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_board_groups_sort_order', sr_t('community::ui.text.7d2dc215'), $communityBoardGroupHelp['sort_order']['id'], $communityBoardGroupHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="community_admin_board_groups_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e($groupField($formBoardGroup, 'sort_order', '0')); ?>" required class="form-input">
                </div>
            </div>
        </section>

        <?php if ($communityBoardGroupsPage === 'edit') { ?>
            <?php $boardGroupDeleteModalId = 'community-board-group-delete-modal'; ?>
        <?php } ?>
        <div class="form-sticky-actions form-actions form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.list.f07b3200')); ?></a>
            <?php if ($communityBoardGroupsPage === 'edit') { ?>
                <button type="button" class="btn btn-outline-danger" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($boardGroupDeleteModalId); ?>" data-overlay="#<?php echo sr_e($boardGroupDeleteModalId); ?>">삭제</button>
            <?php } ?>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e($communityBoardGroupsPage === 'edit' ? sr_t('community::ui.text.086f3a3e') : sr_t('community::ui.text.22129319')); ?></button>
        </div>
    </form>

    <?php if ($communityBoardGroupsPage === 'edit') { ?>
        <?php $boardGroupDeleteCheck = sr_community_can_delete_board_group($pdo, (int) ($formBoardGroup['id'] ?? 0)); ?>
        <div id="<?php echo sr_e($boardGroupDeleteModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($boardGroupDeleteModalId); ?>-label" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="modal-content admin-form ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($boardGroupDeleteModalId); ?>-label" class="modal-title">게시판 그룹 삭제</h3>
                        <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($boardGroupDeleteModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="delete_group">
                        <input type="hidden" name="group_id" value="<?php echo sr_e((string) ($formBoardGroup['id'] ?? 0)); ?>">
                        <p class="form-help">
                            게시판 그룹을 삭제하면 그룹 정보가 삭제되고,
                            연결 게시판 <?php echo sr_e((string) (int) ($boardGroupDeleteCheck['references']['boards'] ?? 0)); ?>건은 삭제하지 않고 그룹 연결만 해제됩니다.
                            외부 참조 <?php echo sr_e((string) array_sum(array_map('intval', is_array($boardGroupDeleteCheck['external_references'] ?? null) ? $boardGroupDeleteCheck['external_references'] : []))); ?>건.
                            현재 편집 중인 변경사항은 삭제 실행 전에 저장되지 않습니다.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($boardGroupDeleteModalId); ?>">닫기</button>
                        <button type="submit" class="btn btn-outline-danger modal-action">삭제</button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>

    <?php echo sr_admin_help_modal_html($memberGroupAccessHelpModalId, sr_t('community::ui.member_group_access_help_title'), $memberGroupAccessHelpBodyHtml); ?>
    <?php foreach ($communityBoardGroupHelp as $communityBoardGroupHelpModal) { ?>
        <?php echo sr_admin_help_modal_html((string) $communityBoardGroupHelpModal['id'], (string) $communityBoardGroupHelpModal['title'], (string) $communityBoardGroupHelpModal['body']); ?>
    <?php } ?>
<?php } ?>

<?php if (in_array($communityBoardGroupsPage, ['new', 'edit'], true)) { ?>
<script>
(function () {
    var privacyConsentEnabled = document.querySelector('[data-community-privacy-consent-enabled]');
    var privacyConsentControls = document.querySelector('[data-community-privacy-consent-controls]');
    if (privacyConsentEnabled && privacyConsentControls) {
        function syncPrivacyConsentControls() {
            var requiredLabel = privacyConsentControls.parentNode ? privacyConsentControls.parentNode.querySelector('[data-community-privacy-consent-required]') : null;
            if (requiredLabel) {
                requiredLabel.hidden = !privacyConsentEnabled.checked;
            }
            Array.prototype.slice.call(privacyConsentControls.querySelectorAll('[data-community-privacy-consent-document]')).forEach(function (select) {
                select.disabled = !privacyConsentEnabled.checked;
                select.required = false;
            });
        }

        privacyConsentEnabled.addEventListener('change', syncPrivacyConsentControls);
        syncPrivacyConsentControls();
    }

    function syncPolicy(kind) {
        var policy = document.querySelector('[data-community-policy="' + kind + '"]');
        var group = document.getElementById('community_admin_board_groups_group_' + kind + '_group_keys');
        if (!policy || !group) {
            return;
        }
        var first = group.querySelector('[data-admin-select-badge-value]');
        if (first && typeof first.setCustomValidity === 'function') {
            first.setCustomValidity('');
        }
    }

    function selectedGroupValues(group) {
        var values = {};
        if (!group) {
            return values;
        }
        Array.prototype.slice.call(group.querySelectorAll('[data-admin-select-badge-value]')).forEach(function (input) {
            if (input.value) {
                values[input.value] = true;
            }
        });
        return values;
    }

    function addGroupValue(group, value) {
        var select = group ? group.querySelector('[data-admin-select-badge-list-select]') : null;
        if (!select || !value || selectedGroupValues(group)[value]) {
            return;
        }
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function removeGroupValue(group, value) {
        if (!group || !value) {
            return;
        }
        Array.prototype.slice.call(group.querySelectorAll('[data-admin-select-badge-value]')).forEach(function (input) {
            if (input.value === value) {
                var item = input.closest('[data-admin-select-badge-item]');
                if (item) {
                    var button = item.querySelector('[data-admin-select-badge-remove]');
                    if (button) {
                        button.click();
                    } else {
                        item.remove();
                    }
                }
            }
        });
    }

    function mirrorSelectedGroupsToRead(kind) {
        var readGroup = document.getElementById('community_admin_board_groups_group_read_group_keys');
        var sourceGroup = document.getElementById('community_admin_board_groups_group_' + kind + '_group_keys');
        if (!readGroup || !sourceGroup || kind === 'read') {
            return;
        }

        Object.keys(selectedGroupValues(sourceGroup)).forEach(function (value) {
            addGroupValue(readGroup, value);
        });
    }

    function syncWritableGroupsFromRead() {
        var readGroup = document.getElementById('community_admin_board_groups_group_read_group_keys');
        if (!readGroup) {
            return;
        }

        var readable = selectedGroupValues(readGroup);
        ['write', 'comment'].forEach(function (kind) {
            var group = document.getElementById('community_admin_board_groups_group_' + kind + '_group_keys');
            if (!group) {
                return;
            }
            Object.keys(selectedGroupValues(group)).forEach(function (value) {
                if (!readable[value]) {
                    removeGroupValue(group, value);
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
            group.addEventListener('click', function (event) {
                var changedSelection = event.target && event.target.closest
                    ? event.target.closest('[data-admin-select-badge-remove], [data-admin-select-badge-clear]')
                    : null;
                if (!changedSelection) {
                    return;
                }
                window.setTimeout(function () {
                    if (kind === 'read') {
                        syncWritableGroupsFromRead();
                    }
                    syncPolicy(kind);
                    syncPolicy('read');
                }, 0);
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
