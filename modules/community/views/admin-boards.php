<?php

$communityBoardsPage = isset($communityBoardsPage) ? (string) $communityBoardsPage : 'list';
$adminPageTitle = sr_t('community::ui.community.22fe030e');
$adminPageSubtitle = sr_t('community::ui.status.search.d3951518');
$adminContainerClass = 'admin-page-community-board-list admin-ui-scope';
if ($communityBoardsPage === 'new') {
    $adminPageTitle = sr_t('community::ui.text.713b7a18');
    $adminPageSubtitle = sr_t('community::ui.community.active.c7c009c1');
    $adminContainerClass = 'admin-page-community-board-form admin-ui-scope';
} elseif ($communityBoardsPage === 'edit') {
    $adminPageTitle = sr_t('community::ui.edit.e92ca332');
    $adminPageSubtitle = sr_t('community::ui.edit.d3b31e46');
    $adminContainerClass = 'admin-page-community-board-form admin-ui-scope';
}
$boardListFilters = isset($boardListFilters) && is_array($boardListFilters) ? $boardListFilters : ['status' => '', 'group_id' => 0, 'field' => 'all', 'q' => ''];
$boardSort = isset($boardSort) && is_array($boardSort) ? $boardSort : sr_community_admin_board_default_sort();
$boardStatusCounts = isset($boardStatusCounts) && is_array($boardStatusCounts) ? $boardStatusCounts : [];
$totalBoards = (int) ($boardStatusCounts['total'] ?? count($boards ?? []));
$boardGroupSettings = isset($boardGroupSettings) && is_array($boardGroupSettings) ? $boardGroupSettings : [];

$settingSourceLabels = [
    'board' => ['visible' => sr_t('community::ui.scope.current_only'), 'sr' => '적용', 'toast' => ''],
    'group' => ['visible' => sr_t('community::ui.scope.copy_group'), 'sr' => '적용', 'toast' => '이 설정을 같은 그룹 게시판에 적용합니다.'],
    'all' => ['visible' => sr_t('community::ui.scope.copy_all'), 'sr' => '적용', 'toast' => '이 설정을 전체 게시판에 적용합니다.'],
];
$settingSourceLabelHtml = static function (array $label): string {
    $srLabel = (string) ($label['sr'] ?? '');
    return sr_e((string) ($label['visible'] ?? '')) . ($srLabel !== '' ? '<span class="sr-only">' . sr_e($srLabel) . '</span>' : '');
};
$boardSettingSource = static function (array $board, string $key): string {
    if (array_key_exists('source_' . $key, $board)) {
        return sr_community_normalize_board_setting_source((string) $board['source_' . $key]);
    }

    $sources = is_array($board['setting_sources'] ?? null) ? $board['setting_sources'] : [];
    return sr_community_normalize_board_setting_source((string) ($sources[$key] ?? 'board'));
};
$assetPrefixSettingSource = static function (array $board, string $prefix) use ($boardSettingSource): string {
    foreach (sr_community_asset_prefix_setting_keys($prefix) as $settingKey) {
        if (array_key_exists('source_' . $settingKey, $board)) {
            return $boardSettingSource($board, $settingKey);
        }
    }

    return 'board';
};
$settingSourceRadioHtml = static function (string $name, string $selectedSource) use ($settingSourceLabels, $settingSourceLabelHtml): string {
    $selectedSource = array_key_exists($selectedSource, $settingSourceLabels) ? $selectedSource : 'board';
    $baseId = preg_replace('/[^a-zA-Z0-9_]+/', '_', $name);
    $html = sr_t('community::ui.div.class.admin.setting.source.67eda3ac');
    foreach ($settingSourceLabels as $source => $label) {
        $id = 'setting_source_' . $baseId . '_' . $source;
        $html .= '<label class="admin-form-check form-label" for="' . sr_e($id) . '">';
        $toast = (string) ($label['toast'] ?? '');
        $html .= '<input id="' . sr_e($id) . '" type="radio" name="' . sr_e($name) . '" value="' . sr_e($source) . '" class="form-radio"' . ($toast !== '' ? ' data-admin-scope-toast="' . sr_e($toast) . '"' : '') . ($selectedSource === $source ? ' checked' : '') . '>';
        $html .= $settingSourceLabelHtml($label);
        $html .= '</label>';
    }

    return $html . '</div>';
};
$boardArrayValue = static function (array $board, string $key): string {
    return implode(', ', is_array($board[$key] ?? null) ? $board[$key] : []);
};
$boardField = static function (array $board, string $key, string $default = ''): string {
    return (string) ($board[$key] ?? $default);
};
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
$memberGroupAccessHelpModalId = 'community-board-member-group-access-help-modal';
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
$communityBoardHelpOpenLabel = sr_t('community::help.open');
$communityBoardHelpButtonHtml = static function (string $label, string $modalId) use ($communityBoardHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $communityBoardHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$communityBoardHelpBodyHtml = static function (array $keys): string {
    $html = '';
    foreach ($keys as $key) {
        $html .= '<p>' . sr_e(sr_t((string) $key)) . '</p>';
    }

    return $html;
};
$communityBoardHelp = [
    'board_group' => [
        'id' => 'community_board_help_board_group',
        'title' => sr_t('community::help.board_group.title'),
        'body' => $communityBoardHelpBodyHtml(['community::help.board_group.body.1', 'community::help.board_group.body.2', 'community::help.board_group.body.3']),
    ],
    'status' => [
        'id' => 'community_board_help_status',
        'title' => sr_t('community::help.status.title'),
        'body' => $communityBoardHelpBodyHtml(['community::help.status.body.1', 'community::help.status.body.2', 'community::help.status.body.3']),
    ],
    'skin' => [
        'id' => 'community_board_help_skin',
        'title' => sr_t('community::help.skin.title'),
        'body' => $communityBoardHelpBodyHtml(['community::help.skin.body.1', 'community::help.skin.body.2']),
    ],
    'policy' => [
        'id' => 'community_board_help_policy',
        'title' => sr_t('community::help.policy.title'),
        'body' => $communityBoardHelpBodyHtml(['community::help.policy.body.1', 'community::help.policy.body.2']),
    ],
    'min_level' => [
        'id' => 'community_board_help_min_level',
        'title' => sr_t('community::help.min_level.title'),
        'body' => $communityBoardHelpBodyHtml(['community::help.min_level.body.1', 'community::help.min_level.body.2']),
    ],
    'attachments' => [
        'id' => 'community_board_help_attachments',
        'title' => sr_t('community::help.attachments.title'),
        'body' => $communityBoardHelpBodyHtml(['community::help.attachments.body.1', 'community::help.attachments.body.2']),
    ],
    'file_extensions' => [
        'id' => 'community_board_help_file_extensions',
        'title' => sr_t('community::help.file_extensions.title'),
        'body' => $communityBoardHelpBodyHtml(['community::help.file_extensions.body.1', 'community::help.file_extensions.body.2']),
    ],
    'display_banner' => [
        'id' => 'community_board_help_display_banner',
        'title' => sr_t('community::help.display_banner.title'),
        'body' => $communityBoardHelpBodyHtml(['community::help.display_banner.body.1', 'community::help.display_banner.body.2']),
    ],
    'display_popup' => [
        'id' => 'community_board_help_display_popup',
        'title' => sr_t('community::help.display_popup.title'),
        'body' => $communityBoardHelpBodyHtml(['community::help.display_popup.body.1', 'community::help.display_popup.body.2']),
    ],
    'asset_settings' => [
        'id' => 'community_board_help_asset_settings',
        'title' => sr_t('community::help.asset_settings.title'),
        'body' => $communityBoardHelpBodyHtml(['community::help.asset_settings.body.1', 'community::help.asset_settings.body.2', 'community::help.asset_settings.body.3']),
    ],
    'sort_order' => [
        'id' => 'community_board_help_sort_order',
        'title' => sr_t('community::help.sort_order.title'),
        'body' => $communityBoardHelpBodyHtml(['community::help.sort_order.body.1', 'community::help.sort_order.body.2']),
    ],
];
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
$selectedBoard = is_array($editBoard ?? null) ? $editBoard : [];
$newBoardDefaultSettings = $settings;
$newBoardPostEditor = sr_community_post_editor_key((string) ($settings['post_editor'] ?? 'textarea'));
$formBoard = $communityBoardsPage === 'edit' ? $selectedBoard : [
    'board_group_id' => 0,
    'board_key' => '',
    'title' => '',
    'description' => '',
    'status' => 'enabled',
    'read_policy' => (string) ($allowedReadPolicies[0] ?? 'public'),
    'write_policy' => (string) ($allowedWritePolicies[0] ?? 'member'),
    'comment_policy' => (string) ($allowedCommentPolicies[0] ?? 'member'),
    'image_uploads_enabled' => 1,
    'attachment_max_bytes' => 2097152,
    'attachment_max_count' => 1,
    'banner_before_list_id' => 0,
    'banner_after_list_id' => 0,
    'popup_layer_list_id' => 0,
    'banner_before_view_id' => 0,
    'banner_after_view_id' => 0,
    'popup_layer_view_id' => 0,
    'banner_before_form_id' => 0,
    'banner_after_form_id' => 0,
    'popup_layer_form_id' => 0,
    'file_uploads_enabled' => '0',
    'file_attachment_max_bytes' => 5242880,
    'file_attachment_max_count' => 3,
    'file_allowed_extensions' => ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp'],
    'sort_order' => 0,
    'read_group_keys' => [],
    'write_group_keys' => [],
    'comment_group_keys' => [],
    'read_min_level' => 0,
    'write_min_level' => 0,
    'comment_min_level' => 0,
    'level_post_score' => (string) ($settings['level_post_score'] ?? 10),
    'level_comment_score' => (string) ($settings['level_comment_score'] ?? 2),
    'skin_key' => 'basic',
    'post_editor' => $newBoardPostEditor,
    'post_reward_enabled' => !empty($newBoardDefaultSettings['post_reward_enabled']) ? '1' : '0',
    'post_reward_asset_module' => (string) ($newBoardDefaultSettings['post_reward_asset_module'] ?? ''),
    'post_reward_amount' => (string) ($newBoardDefaultSettings['post_reward_amount'] ?? 0),
    'post_reward_group_policies_json' => (string) ($newBoardDefaultSettings['post_reward_group_policies_json'] ?? ''),
    'post_reward_policy_set_id' => (string) ($newBoardDefaultSettings['post_reward_policy_set_id'] ?? 0),
    'post_reward_amounts_json' => (string) ($newBoardDefaultSettings['post_reward_amounts_json'] ?? ''),
    'comment_reward_enabled' => !empty($newBoardDefaultSettings['comment_reward_enabled']) ? '1' : '0',
    'comment_reward_asset_module' => (string) ($newBoardDefaultSettings['comment_reward_asset_module'] ?? ''),
    'comment_reward_amount' => (string) ($newBoardDefaultSettings['comment_reward_amount'] ?? 0),
    'comment_reward_group_policies_json' => (string) ($newBoardDefaultSettings['comment_reward_group_policies_json'] ?? ''),
    'comment_reward_policy_set_id' => (string) ($newBoardDefaultSettings['comment_reward_policy_set_id'] ?? 0),
    'comment_reward_amounts_json' => (string) ($newBoardDefaultSettings['comment_reward_amounts_json'] ?? ''),
    'write_charge_enabled' => !empty($newBoardDefaultSettings['write_charge_enabled']) ? '1' : '0',
    'write_charge_asset_module' => (string) ($newBoardDefaultSettings['write_charge_asset_module'] ?? ''),
    'write_charge_amount' => (string) ($newBoardDefaultSettings['write_charge_amount'] ?? 0),
    'write_charge_amounts_json' => (string) ($newBoardDefaultSettings['write_charge_amounts_json'] ?? ''),
    'write_charge_group_policies_json' => (string) ($newBoardDefaultSettings['write_charge_group_policies_json'] ?? ''),
    'write_charge_policy_set_id' => (string) ($newBoardDefaultSettings['write_charge_policy_set_id'] ?? 0),
    'comment_charge_enabled' => !empty($newBoardDefaultSettings['comment_charge_enabled']) ? '1' : '0',
    'comment_charge_asset_module' => (string) ($newBoardDefaultSettings['comment_charge_asset_module'] ?? ''),
    'comment_charge_amount' => (string) ($newBoardDefaultSettings['comment_charge_amount'] ?? 0),
    'comment_charge_amounts_json' => (string) ($newBoardDefaultSettings['comment_charge_amounts_json'] ?? ''),
    'comment_charge_group_policies_json' => (string) ($newBoardDefaultSettings['comment_charge_group_policies_json'] ?? ''),
    'comment_charge_policy_set_id' => (string) ($newBoardDefaultSettings['comment_charge_policy_set_id'] ?? 0),
    'paid_read_enabled' => !empty($newBoardDefaultSettings['paid_read_enabled']) ? '1' : '0',
    'paid_read_asset_module' => (string) ($newBoardDefaultSettings['paid_read_asset_module'] ?? ''),
    'paid_read_amount' => (string) ($newBoardDefaultSettings['paid_read_amount'] ?? 0),
    'paid_read_amounts_json' => (string) ($newBoardDefaultSettings['paid_read_amounts_json'] ?? ''),
    'paid_read_group_policies_json' => (string) ($newBoardDefaultSettings['paid_read_group_policies_json'] ?? ''),
    'paid_read_policy_set_id' => (string) ($newBoardDefaultSettings['paid_read_policy_set_id'] ?? 0),
    'paid_read_charge_policy' => (string) ($newBoardDefaultSettings['paid_read_charge_policy'] ?? 'once'),
    'paid_attachment_download_enabled' => !empty($newBoardDefaultSettings['paid_attachment_download_enabled']) ? '1' : '0',
    'paid_attachment_download_asset_module' => (string) ($newBoardDefaultSettings['paid_attachment_download_asset_module'] ?? ''),
    'paid_attachment_download_amount' => (string) ($newBoardDefaultSettings['paid_attachment_download_amount'] ?? 0),
    'paid_attachment_download_amounts_json' => (string) ($newBoardDefaultSettings['paid_attachment_download_amounts_json'] ?? ''),
    'paid_attachment_download_group_policies_json' => (string) ($newBoardDefaultSettings['paid_attachment_download_group_policies_json'] ?? ''),
    'paid_attachment_download_policy_set_id' => (string) ($newBoardDefaultSettings['paid_attachment_download_policy_set_id'] ?? 0),
    'paid_attachment_download_charge_policy' => (string) ($newBoardDefaultSettings['paid_attachment_download_charge_policy'] ?? 'once'),
];
$communityBoardAssetAuditUrl = $communityBoardsPage === 'edit'
    ? sr_admin_asset_settings_audit_url('community.board.asset_settings.updated', 'community_board', (string) (int) ($formBoard['id'] ?? 0))
    : '';
if ($communityBoardsPage !== 'edit') {
    foreach (sr_community_asset_setting_keys() as $assetSettingKey) {
        $formBoard['source_' . $assetSettingKey] = 'board';
    }
}

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($communityBoardsPage === 'list') { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.all.e078b14a')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.97d9bd67')); ?> <strong><?php echo sr_e((string) $totalBoards); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/community/boards?status=enabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.active.93c558d7')); ?> <?php echo sr_e((string) ($boardStatusCounts['enabled'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/community/boards?status=disabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.92cdef3c')); ?> <?php echo sr_e((string) ($boardStatusCounts['disabled'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/community/boards?status=archived')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.2e4099ba')); ?> <?php echo sr_e((string) ($boardStatusCounts['archived'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="admin-filter admin-community-board-filter ui-form-theme">
        <div class="admin-filter-grid admin-community-board-search-grid">
            <div class="admin-filter-field admin-community-board-filter-status">
                <label for="community_admin_boards_status_filter" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></label>
                <select id="community_admin_boards_status_filter" name="status" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($boardListFilters['status'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.all.a4b69faf')); ?></option>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($boardListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-board-filter-group">
                <label for="community_admin_boards_group_filter" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.text.ec060706')); ?></label>
                <select id="community_admin_boards_group_filter" name="group_id" class="form-select admin-filter-input">
                    <option value="0"<?php echo (int) ($boardListFilters['group_id'] ?? 0) === 0 ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.all.a4b69faf')); ?></option>
                    <?php foreach ($boardGroups as $boardGroup) { ?>
                        <option value="<?php echo sr_e((string) $boardGroup['id']); ?>"<?php echo (int) ($boardListFilters['group_id'] ?? 0) === (int) $boardGroup['id'] ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $boardGroup['title']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-board-filter-field">
                <label for="community_admin_boards_field" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.search.b79bc9c8')); ?></label>
                <select id="community_admin_boards_field" name="field" class="form-select admin-filter-input">
                    <?php foreach (['all' => sr_t('community::ui.all.a4b69faf'), 'key' => 'key', 'title' => sr_t('community::ui.name.253d1510'), 'group' => sr_t('community::ui.text.5d908ddd')] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($boardListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-board-filter-keyword">
                <label for="community_admin_boards_q" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.search.bda397fc')); ?></label>
                <input id="community_admin_boards_q" type="search" name="q" value="<?php echo sr_e((string) ($boardListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('community::ui.key.name.9f150e7e')); ?>">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('community::ui.list.a62deef1')); ?></h2>
            <a href="<?php echo sr_e(sr_url('/admin/community/boards/new')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('community::ui.text.97f92efb')); ?></a>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($boardSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_community_admin_board_sort_options(), sr_community_admin_board_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="게시판 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($boardPagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table admin-community-board-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('community::ui.community.list.90d528cf')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th<?php echo sr_admin_sort_aria('board_key', $boardSort); ?>><?php echo sr_admin_sort_header_html('key', 'board_key', $boardSort, sr_community_admin_board_sort_options(), sr_community_admin_board_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('title', $boardSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.name.253d1510'), 'title', $boardSort, sr_community_admin_board_sort_options(), sr_community_admin_board_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('board_group', $boardSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.5d908ddd'), 'board_group', $boardSort, sr_community_admin_board_sort_options(), sr_community_admin_board_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $boardSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.status.e10195a1'), 'status', $boardSort, sr_community_admin_board_sort_options(), sr_community_admin_board_default_sort()); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('community::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($boards === []) { ?>
                    <tr>
                        <td colspan="5" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.text.112bc2dd')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($boards as $board) { ?>
                    <?php
                    $boardStatus = (string) $board['status'];
                    $statusClass = match ($boardStatus) {
                        'enabled' => 'is-normal',
                        'disabled' => 'is-blocked',
                        default => 'is-left',
                    };
                    ?>
                    <tr>
                        <td class="admin-table-nowrap admin-community-board-key-cell"><?php echo sr_e((string) $board['board_key']); ?></td>
                        <td class="admin-table-break admin-community-board-title-cell"><?php echo sr_e((string) $board['title']); ?></td>
                        <td class="admin-table-break admin-community-board-group-cell"><?php echo sr_e((string) ($board['board_group_title'] ?? '')); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($boardStatus, 'content_status')); ?></span></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e(sr_t('community::ui.text.910d9d5a')); ?>" title="<?php echo sr_e(sr_t('community::ui.text.910d9d5a')); ?>"><?php echo sr_material_icon_html('open_in_new'); ?></a>
                                <a href="<?php echo sr_e(sr_url('/admin/community/boards/edit?id=' . rawurlencode((string) $board['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php echo sr_admin_pagination_html($boardPagination, '게시판 목록 페이지'); ?>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url($communityBoardsPage === 'edit' ? '/admin/community/boards/update' : '/admin/community/boards/create')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo $communityBoardsPage === 'edit' ? sr_t('community::ui.edit.e92ca332') : sr_t('community::ui.text.713b7a18'); ?></h2>
            <?php echo sr_csrf_field(); ?>
            <?php if ($communityBoardsPage === 'edit') { ?>
                <input type="hidden" name="board_id" value="<?php echo sr_e((string) $formBoard['id']); ?>">
                <div class="admin-form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('community::ui.key.cf056766')); ?></span>
                    <div class="admin-form-field">
                        <code><?php echo sr_e((string) $formBoard['board_key']); ?></code>
                    </div>
                </div>
            <?php } else { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_boards_board_key"><?php echo sr_e(sr_t('community::ui.key.cf056766')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="community_admin_boards_board_key" type="text" name="board_key" maxlength="60" value="<?php echo sr_e($boardField($formBoard, 'board_key')); ?>" class="form-input" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input>
                    </div>
                </div>
            <?php } ?>
            <div class="admin-form-row">
                <div class="form-label admin-form-label-help"><?php echo $communityBoardHelpButtonHtml(sr_t('community::ui.text.ec060706'), $communityBoardHelp['board_group']['id']); ?><label for="community_admin_boards_board_group_id"><?php echo sr_e(sr_t('community::ui.text.ec060706')); ?> <span class="sr-required-label" data-community-board-group-required hidden><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label></div>
                <div class="admin-form-field">
                    <select id="community_admin_boards_board_group_id" name="board_group_id" class="form-select" data-community-board-group-select>
                                            <option value="0"><?php echo sr_e(sr_t('community::ui.text.72ea3d64')); ?></option>
                                            <?php foreach ($boardGroups as $boardGroup) { ?>
                                                <?php
                                                $optionBoardGroupId = (int) $boardGroup['id'];
                                                $optionBoardGroupSettings = is_array($boardGroupSettings[$optionBoardGroupId] ?? null) ? $boardGroupSettings[$optionBoardGroupId] : [];
                                                $optionLevelPostScore = (string) ($optionBoardGroupSettings['level_post_score'] ?? ($settings['level_post_score'] ?? 10));
                                                $optionLevelCommentScore = (string) ($optionBoardGroupSettings['level_comment_score'] ?? ($settings['level_comment_score'] ?? 2));
                                                ?>
                                                <option value="<?php echo sr_e((string) $boardGroup['id']); ?>" data-level-post-score="<?php echo sr_e($optionLevelPostScore); ?>" data-level-comment-score="<?php echo sr_e($optionLevelCommentScore); ?>"<?php echo (int) $boardField($formBoard, 'board_group_id', '0') === (int) $boardGroup['id'] ? ' selected' : ''; ?>><?php echo sr_e((string) $boardGroup['title']); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_title"><?php echo sr_e(sr_t('community::ui.name.253d1510')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_title" type="text" name="title" maxlength="120" value="<?php echo sr_e($boardField($formBoard, 'title')); ?>" class="form-input form-control-full" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_description"><?php echo sr_e(sr_t('community::ui.text.8c3f651d')); ?></label>
                <div class="admin-form-field">
                    <textarea id="community_admin_boards_description" name="description" rows="3" cols="60" class="form-textarea"><?php echo sr_e($boardField($formBoard, 'description')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_status', sr_t('community::ui.status.e10195a1'), $communityBoardHelp['status']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="community_admin_boards_status" name="status" class="form-select">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $status === $boardField($formBoard, 'status', 'enabled') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                            <?php } ?>
                                        </select>
                    <?php echo $settingSourceRadioHtml('source_status', $boardSettingSource($formBoard, 'status')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_skin_key', sr_t('community::ui.text.83d35075'), $communityBoardHelp['skin']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="community_admin_boards_skin_key" name="skin_key" class="form-select">
                                            <?php foreach ($communitySkinOptions as $skinKey => $skinOption) { ?>
                                                <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $boardField($formBoard, 'skin_key', 'basic') === (string) $skinKey ? ' selected' : ''; ?>>
                                                    <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                    <?php echo $settingSourceRadioHtml('source_skin_key', $boardSettingSource($formBoard, 'skin_key')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_post_editor">게시글 에디터 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="community_admin_boards_post_editor" name="post_editor" class="form-select" required>
                        <?php foreach ($editorOptions as $editorKey => $editorLabel) { ?>
                            <option value="<?php echo sr_e((string) $editorKey); ?>"<?php echo $boardField($formBoard, 'post_editor', 'textarea') === (string) $editorKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $editorLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <?php echo $settingSourceRadioHtml('source_post_editor', $boardSettingSource($formBoard, 'post_editor')); ?>
                    <p class="admin-form-help">저장한 뒤에는 커뮤니티 환경설정이나 게시판 그룹 설정 변경의 영향을 받지 않습니다.</p>
                </div>
            </div>

        </section>

        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('community::ui.text.533748da')); ?></h2>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_read_policy', sr_t('community::ui.text.0b6c5dfd'), $communityBoardHelp['policy']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="community_admin_boards_read_policy" name="read_policy" class="form-select" data-community-policy="read">
                                            <?php foreach ($allowedReadPolicies as $policy) { ?>
                                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $boardField($formBoard, 'read_policy') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                            <?php } ?>
                    </select>
                    <?php echo $settingSourceRadioHtml('source_read_policy', $boardSettingSource($formBoard, 'read_policy')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo $memberGroupAccessLabelHtml('community_admin_boards_read_group_keys', sr_t('community::ui.member.ecf858a4')); ?>
                <div class="admin-form-field">
                    <?php echo sr_admin_member_group_key_select_html('community_admin_boards_read_group_keys', 'read_group_keys', is_array($formBoard['read_group_keys'] ?? null) ? $formBoard['read_group_keys'] : [], $enabledMemberGroups); ?>
                    <?php echo $settingSourceRadioHtml('source_read_group_keys', $boardSettingSource($formBoard, 'read_group_keys')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_read_min_level', sr_t('community::ui.text.a783617f'), $communityBoardHelp['min_level']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <?php echo $communityLevelSelectHtml('community_admin_boards_read_min_level', 'read_min_level', (int) $boardField($formBoard, 'read_min_level', '0')); ?>
                    <?php echo $settingSourceRadioHtml('source_read_min_level', $boardSettingSource($formBoard, 'read_min_level')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_write_policy', sr_t('community::ui.text.4f05f6a8'), $communityBoardHelp['policy']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="community_admin_boards_write_policy" name="write_policy" class="form-select" data-community-policy="write">
                                            <?php foreach ($allowedWritePolicies as $policy) { ?>
                                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $boardField($formBoard, 'write_policy') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                            <?php } ?>
                    </select>
                    <?php echo $settingSourceRadioHtml('source_write_policy', $boardSettingSource($formBoard, 'write_policy')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo $memberGroupAccessLabelHtml('community_admin_boards_write_group_keys', sr_t('community::ui.member.e99a3ed2')); ?>
                <div class="admin-form-field">
                    <?php echo sr_admin_member_group_key_select_html('community_admin_boards_write_group_keys', 'write_group_keys', is_array($formBoard['write_group_keys'] ?? null) ? $formBoard['write_group_keys'] : [], $enabledMemberGroups); ?>
                    <?php echo $settingSourceRadioHtml('source_write_group_keys', $boardSettingSource($formBoard, 'write_group_keys')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_write_min_level', sr_t('community::ui.text.82530158'), $communityBoardHelp['min_level']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <?php echo $communityLevelSelectHtml('community_admin_boards_write_min_level', 'write_min_level', (int) $boardField($formBoard, 'write_min_level', '0')); ?>
                    <?php echo $settingSourceRadioHtml('source_write_min_level', $boardSettingSource($formBoard, 'write_min_level')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_comment_policy', sr_t('community::ui.text.0550e13c'), $communityBoardHelp['policy']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="community_admin_boards_comment_policy" name="comment_policy" class="form-select" data-community-policy="comment">
                                            <?php foreach ($allowedCommentPolicies as $policy) { ?>
                                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $boardField($formBoard, 'comment_policy') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                            <?php } ?>
                    </select>
                    <?php echo $settingSourceRadioHtml('source_comment_policy', $boardSettingSource($formBoard, 'comment_policy')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo $memberGroupAccessLabelHtml('community_admin_boards_comment_group_keys', sr_t('community::ui.member.11859d69')); ?>
                <div class="admin-form-field">
                    <?php echo sr_admin_member_group_key_select_html('community_admin_boards_comment_group_keys', 'comment_group_keys', is_array($formBoard['comment_group_keys'] ?? null) ? $formBoard['comment_group_keys'] : [], $enabledMemberGroups); ?>
                    <?php echo $settingSourceRadioHtml('source_comment_group_keys', $boardSettingSource($formBoard, 'comment_group_keys')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_comment_min_level', sr_t('community::ui.text.3eccb18c'), $communityBoardHelp['min_level']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <?php echo $communityLevelSelectHtml('community_admin_boards_comment_min_level', 'comment_min_level', (int) $boardField($formBoard, 'comment_min_level', '0')); ?>
                    <?php echo $settingSourceRadioHtml('source_comment_min_level', $boardSettingSource($formBoard, 'comment_min_level')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_level_post_score"><?php echo sr_e(sr_t('community::ui.text.99092cba')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_level_post_score" type="number" name="level_post_score" min="0" max="10000" value="<?php echo sr_e($boardField($formBoard, 'level_post_score', (string) ($settings['level_post_score'] ?? 10))); ?>" required class="form-input" data-community-level-score="post">
                    <?php echo $settingSourceRadioHtml('source_level_post_score', $boardSettingSource($formBoard, 'level_post_score')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_level_comment_score"><?php echo sr_e(sr_t('community::ui.text.96af1f5c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_level_comment_score" type="number" name="level_comment_score" min="0" max="10000" value="<?php echo sr_e($boardField($formBoard, 'level_comment_score', (string) ($settings['level_comment_score'] ?? 2))); ?>" required class="form-input" data-community-level-score="comment">
                    <?php echo $settingSourceRadioHtml('source_level_comment_score', $boardSettingSource($formBoard, 'level_comment_score')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="form-label admin-form-label-help"><?php echo $communityBoardHelpButtonHtml(sr_t('community::ui.text.c3bd14cb'), $communityBoardHelp['attachments']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.c3bd14cb')); ?></span></div>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_boards_image_uploads_enabled">
                                            <input id="modules_community_admin_boards_image_uploads_enabled" type="checkbox" name="image_uploads_enabled" value="1" class="form-checkbox"<?php echo (int) $boardField($formBoard, 'image_uploads_enabled', '1') === 1 ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.text.c3bd14cb')); ?>
                                        </label>
                                    <?php echo $settingSourceRadioHtml('source_image_uploads_enabled', $boardSettingSource($formBoard, 'image_uploads_enabled')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_attachment_max_bytes', sr_t('community::ui.bytes.e28899ac'), $communityBoardHelp['attachments']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <div class="input-group admin-input-unit">
                        <input id="community_admin_boards_attachment_max_bytes" type="number" name="attachment_max_bytes" min="1024" max="10485760" value="<?php echo sr_e($boardField($formBoard, 'attachment_max_bytes', '2097152')); ?>" required class="form-input">
                        <span class="input-group-text">bytes</span>
                    </div>
                    <?php echo $settingSourceRadioHtml('source_attachment_max_bytes', $boardSettingSource($formBoard, 'attachment_max_bytes')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_attachment_max_count', sr_t('community::ui.text.bf61ba9f'), $communityBoardHelp['attachments']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <input id="community_admin_boards_attachment_max_count" type="number" name="attachment_max_count" min="0" max="10" value="<?php echo sr_e($boardField($formBoard, 'attachment_max_count', '1')); ?>" required class="form-input">
                    <?php echo $settingSourceRadioHtml('source_attachment_max_count', $boardSettingSource($formBoard, 'attachment_max_count')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="form-label admin-form-label-help"><?php echo $communityBoardHelpButtonHtml(sr_t('community::ui.text.fe95ead0'), $communityBoardHelp['attachments']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.fe95ead0')); ?></span></div>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_boards_file_uploads_enabled">
                                            <input id="modules_community_admin_boards_file_uploads_enabled" type="checkbox" name="file_uploads_enabled" value="1" class="form-checkbox"<?php echo in_array($boardField($formBoard, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.text.fe95ead0')); ?>
                                        </label>
                                    <?php echo $settingSourceRadioHtml('source_file_uploads_enabled', $boardSettingSource($formBoard, 'file_uploads_enabled')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_file_attachment_max_bytes', sr_t('community::ui.bytes.9055a3dc'), $communityBoardHelp['attachments']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <div class="input-group admin-input-unit">
                        <input id="community_admin_boards_file_attachment_max_bytes" type="number" name="file_attachment_max_bytes" min="1024" max="20971520" value="<?php echo sr_e($boardField($formBoard, 'file_attachment_max_bytes', '5242880')); ?>" required class="form-input">
                        <span class="input-group-text">bytes</span>
                    </div>
                    <?php echo $settingSourceRadioHtml('source_file_attachment_max_bytes', $boardSettingSource($formBoard, 'file_attachment_max_bytes')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_file_attachment_max_count', sr_t('community::ui.text.593790e4'), $communityBoardHelp['attachments']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <input id="community_admin_boards_file_attachment_max_count" type="number" name="file_attachment_max_count" min="0" max="5" value="<?php echo sr_e($boardField($formBoard, 'file_attachment_max_count', '3')); ?>" required class="form-input">
                    <?php echo $settingSourceRadioHtml('source_file_attachment_max_count', $boardSettingSource($formBoard, 'file_attachment_max_count')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php $boardFileExtensionsRequired = in_array($boardField($formBoard, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) && (int) $boardField($formBoard, 'file_attachment_max_count', '3') > 0; ?>
                <div class="form-label admin-form-label-help"><?php echo $communityBoardHelpButtonHtml(sr_t('community::ui.text.69600d46'), $communityBoardHelp['file_extensions']['id']); ?><label for="community_admin_boards_file_allowed_extensions"><?php echo sr_e(sr_t('community::ui.text.69600d46')); ?> <span class="sr-required-label" data-community-file-extensions-required<?php echo $boardFileExtensionsRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label></div>
                <div class="admin-form-field">
                    <input id="community_admin_boards_file_allowed_extensions" type="text" name="file_allowed_extensions" maxlength="1000" value="<?php echo sr_e($boardArrayValue($formBoard, 'file_allowed_extensions')); ?>" class="form-input form-control-full" placeholder="pdf, txt, zip" data-community-file-extensions<?php echo $boardFileExtensionsRequired ? ' required' : ''; ?>>
                    <?php echo $settingSourceRadioHtml('source_file_allowed_extensions', $boardSettingSource($formBoard, 'file_allowed_extensions')); ?>
                </div>
            </div>
        </section>

        <section class="admin-card card">
            <h2>
                <span><?php echo sr_e(sr_t('community::ui.banner.63182d60')); ?></span>
                <?php if (sr_module_enabled($pdo, 'banner')) { ?>
                    <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('community::ui.banner.42c18eb4')); ?></a>
                <?php } ?>
            </h2>
                <p><?php echo sr_e(sr_t('community::ui.banner.banner.save.select.b4ee12be')); ?></p>
                <?php foreach ($publicBannerSettingLabels as $bannerSettingKey => $bannerSettingLabel) { ?>
                    <div class="admin-form-row">
                        <div class="form-label admin-form-label-help"><?php echo $communityBoardHelpButtonHtml((string) $bannerSettingLabel, $communityBoardHelp['display_banner']['id']); ?><label for="<?php echo sr_e('community_board_' . (string) $bannerSettingKey); ?>"><?php echo sr_e((string) $bannerSettingLabel); ?></label></div>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e('community_board_' . (string) $bannerSettingKey); ?>" name="<?php echo sr_e((string) $bannerSettingKey); ?>" class="form-select form-control-full">
                                                                <option value="0"><?php echo sr_e(sr_t('community::ui.active.4add3230')); ?></option>
                                                                <?php foreach ($publicBanners as $publicBanner) { ?>
                                                                    <option value="<?php echo sr_e((string) $publicBanner['id']); ?>"<?php echo (int) $boardField($formBoard, (string) $bannerSettingKey, '0') === (int) $publicBanner['id'] ? ' selected' : ''; ?>>
                                                                        <?php echo sr_e((string) $publicBanner['title']); ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                            <?php echo $settingSourceRadioHtml('source_' . (string) $bannerSettingKey, $boardSettingSource($formBoard, (string) $bannerSettingKey)); ?>
                        </div>
                    </div>
                <?php } ?>
        </section>

        <section class="admin-card card">
            <h2>
                <span><?php echo sr_e(sr_t('community::ui.text.1063d585')); ?></span>
                <?php if (sr_module_enabled($pdo, 'popup_layer')) { ?>
                    <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('community::ui.text.f789aad9')); ?></a>
                <?php } ?>
            </h2>
                <p><?php echo sr_e(sr_t('community::ui.save.select.59a0fe09')); ?></p>
                <?php foreach ($publicPopupLayerSettingLabels as $popupLayerSettingKey => $popupLayerSettingLabel) { ?>
                    <div class="admin-form-row">
                        <div class="form-label admin-form-label-help"><?php echo $communityBoardHelpButtonHtml((string) $popupLayerSettingLabel, $communityBoardHelp['display_popup']['id']); ?><label for="<?php echo sr_e('community_board_' . (string) $popupLayerSettingKey); ?>"><?php echo sr_e((string) $popupLayerSettingLabel); ?></label></div>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e('community_board_' . (string) $popupLayerSettingKey); ?>" name="<?php echo sr_e((string) $popupLayerSettingKey); ?>" class="form-select form-control-full">
                                                                <option value="0"><?php echo sr_e(sr_t('community::ui.active.4add3230')); ?></option>
                                                                <?php foreach ($publicPopupLayers as $publicPopupLayer) { ?>
                                                                    <option value="<?php echo sr_e((string) $publicPopupLayer['id']); ?>"<?php echo (int) $boardField($formBoard, (string) $popupLayerSettingKey, '0') === (int) $publicPopupLayer['id'] ? ' selected' : ''; ?>>
                                                                        <?php echo sr_e((string) $publicPopupLayer['title']); ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                            <?php echo $settingSourceRadioHtml('source_' . (string) $popupLayerSettingKey, $boardSettingSource($formBoard, (string) $popupLayerSettingKey)); ?>
                        </div>
                    </div>
                <?php } ?>
        </section>

        <section class="admin-card card">
            <h2>
                <span><?php echo sr_e(sr_t('community::ui.member.415a098e')); ?></span>
                <?php if ($communityBoardAssetAuditUrl !== '') { ?>
                    <span class="admin-form-actions">
                        <a href="<?php echo sr_e($communityBoardAssetAuditUrl); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('자산 변경 이력'); ?></a>
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
                    <?php $assetEnabledId = 'community_board_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', (string) $assetPrefix) . '_enabled'; ?>
                    <?php $usesCompositeAsset = sr_community_asset_prefix_uses_composite((string) $assetPrefix); ?>
                    <?php $usesGroupedAssetAmounts = $usesCompositeAsset; ?>
                    <?php $selectedAssetModules = sr_community_asset_module_keys_from_value($boardField($formBoard, $assetPrefix . '_asset_module', ''), true); ?>
                    <div class="admin-form-row">
                        <div class="form-label admin-form-label-help"><?php echo $communityBoardHelpButtonHtml($assetLabel, $communityBoardHelp['asset_settings']['id']); ?><span><?php echo sr_e($assetLabel); ?></span></div>
                        <div class="admin-form-field">
                            <div class="admin-asset-setting-line">
                                <div class="admin-asset-setting-control">
                                    <div class="admin-asset-setting-primary">
                                        <label class="admin-form-check form-label" for="<?php echo sr_e($assetEnabledId); ?>">
                                            <input id="<?php echo sr_e($assetEnabledId); ?>" type="checkbox" name="<?php echo sr_e($assetPrefix); ?>_enabled" value="1" class="form-checkbox"<?php echo in_array($boardField($formBoard, $assetPrefix . '_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html($assetLabel . sr_t('community::ui.active.d11d5dbb')); ?>
                                        </label>
                                        <?php if ($usesGroupedAssetAmounts) { ?>
                                            <input type="hidden" name="<?php echo sr_e($assetPrefix); ?>_amount" value="<?php echo sr_e($boardField($formBoard, $assetPrefix . '_amount', '0')); ?>">
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="admin-asset-setting-scope">
                                    <?php echo $settingSourceRadioHtml('source_' . (string) $assetPrefix . '_enabled', $boardSettingSource($formBoard, (string) $assetPrefix . '_enabled')); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <span class="form-label"><?php echo sr_e($assetLabel . ' 자산'); ?></span>
                        <div class="admin-form-field">
                            <?php if ($usesGroupedAssetAmounts) { ?>
                                <div class="admin-asset-setting-target" data-admin-asset-enable-target="#<?php echo sr_e($assetEnabledId); ?>">
                                    <?php echo sr_community_asset_grouped_amount_inputs_html('community_board_' . (string) $assetPrefix . '_asset_amounts', (string) $assetPrefix . '_asset_module', (string) $assetPrefix . '_amounts', $assetModuleOptions, $selectedAssetModules, $boardField($formBoard, $assetPrefix . '_amounts_json', ''), (int) $boardField($formBoard, $assetPrefix . '_amount', '0'), sr_t('community::ui.asset.amount.0df01f4b', ['label' => $assetLabel]), sr_t('community::ui.text.3e195cdd')); ?>
                                </div>
                            <?php } else { ?>
                                <div class="admin-asset-setting-target admin-asset-single-setting-target" data-admin-asset-enable-target="#<?php echo sr_e($assetEnabledId); ?>">
                                    <select name="<?php echo sr_e($assetPrefix); ?>_asset_module" class="form-select" data-admin-asset-unit-select>
                                        <option value=""><?php echo sr_e($assetModuleOptions === [] ? sr_t('community::ui.text.3e195cdd') : sr_t('community::ui.text.asset_none')); ?></option>
                                        <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                            <option value="<?php echo sr_e((string) $assetModule); ?>" data-admin-asset-unit="<?php echo sr_e((string) ($assetOption['unit_label'] ?? '')); ?>"<?php echo $boardField($formBoard, $assetPrefix . '_asset_module', '') === (string) $assetModule ? ' selected' : ''; ?>>
                                                <?php echo sr_e((string) $assetOption['label']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <?php echo sr_community_asset_single_amount_input_group_html((string) $assetPrefix . '_amount', (int) $boardField($formBoard, $assetPrefix . '_amount', '0'), $assetModuleOptions, $boardField($formBoard, $assetPrefix . '_asset_module', ''), sr_t('community::ui.asset.amount.0df01f4b', ['label' => $assetLabel])); ?>
                                </div>
                            <?php } ?>
                            <?php if ($usesCompositeAsset) { ?>
                                <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                            <?php } ?>
                            <div class="admin-asset-setting-scope admin-asset-setting-scope-inline">
                                <?php echo $settingSourceRadioHtml('source_' . (string) $assetPrefix . '_asset_module', $boardSettingSource($formBoard, (string) $assetPrefix . '_asset_module')); ?>
                            </div>
                            <?php echo sr_community_asset_policy_set_select_html('community_board_' . (string) $assetPrefix . '_policy_set_id', (string) $assetPrefix . '_policy_set_id', $assetPolicySets ?? [], (int) $boardField($formBoard, $assetPrefix . '_policy_set_id', '0')); ?>
                            <p class="admin-form-help">회원 그룹/레벨 혜택은 커뮤니티 회원 그룹/레벨 혜택 화면에서 관리합니다.</p>
                            <div class="admin-asset-setting-scope admin-asset-setting-scope-inline">
                                <?php echo $settingSourceRadioHtml('source_' . (string) $assetPrefix . '_policy_set_id', $boardSettingSource($formBoard, (string) $assetPrefix . '_policy_set_id')); ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($assetPrefix === 'paid_read') { ?>
                        <div class="admin-form-row">
                            <label class="form-label" for="community_board_paid_read_charge_policy"><?php echo sr_e(sr_t('community::ui.text.05ead7ab')); ?></label>
                            <div class="admin-form-field">
                                <select id="community_board_paid_read_charge_policy" name="paid_read_charge_policy" class="form-select">
                                    <option value="once"<?php echo $boardField($formBoard, 'paid_read_charge_policy', 'once') === 'once' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.6eb4fe4e')); ?></option>
                                    <option value="every_view"<?php echo $boardField($formBoard, 'paid_read_charge_policy', 'once') === 'every_view' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.53e8d077')); ?></option>
                                </select>
                                <?php echo $settingSourceRadioHtml('source_paid_read_charge_policy', $boardSettingSource($formBoard, 'paid_read_charge_policy')); ?>
                            </div>
                        </div>
                    <?php } elseif ($assetPrefix === 'paid_attachment_download') { ?>
                        <div class="admin-form-row">
                            <label class="form-label" for="community_board_paid_attachment_download_charge_policy"><?php echo sr_e(sr_t('community::ui.text.978f8b2e')); ?></label>
                            <div class="admin-form-field">
                                <select id="community_board_paid_attachment_download_charge_policy" name="paid_attachment_download_charge_policy" class="form-select">
                                    <option value="once"<?php echo $boardField($formBoard, 'paid_attachment_download_charge_policy', 'once') === 'once' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.6eb4fe4e')); ?></option>
                                    <option value="every_download"<?php echo $boardField($formBoard, 'paid_attachment_download_charge_policy', 'once') === 'every_download' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.e9d14df2')); ?></option>
                                </select>
                                <?php echo $settingSourceRadioHtml('source_paid_attachment_download_charge_policy', $boardSettingSource($formBoard, 'paid_attachment_download_charge_policy')); ?>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>

        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('community::ui.text.3788952d')); ?></h2>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_sort_order', sr_t('community::ui.text.7d2dc215'), $communityBoardHelp['sort_order']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <input id="community_admin_boards_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e($boardField($formBoard, 'sort_order', '0')); ?>" required class="form-input">
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo $communityBoardsPage === 'edit' ? sr_t('community::ui.text.16f64fe4') : sr_t('community::ui.text.167eff27'); ?></button>
        </div>
    </form>

    <?php echo sr_admin_help_modal_html($memberGroupAccessHelpModalId, sr_t('community::ui.member_group_access_help_title'), $memberGroupAccessHelpBodyHtml); ?>
    <?php foreach ($communityBoardHelp as $communityBoardHelpModal) { ?>
        <?php echo sr_admin_help_modal_html((string) $communityBoardHelpModal['id'], (string) $communityBoardHelpModal['title'], (string) $communityBoardHelpModal['body']); ?>
    <?php } ?>
<?php } ?>

<?php if (in_array($communityBoardsPage, ['new', 'edit'], true)) { ?>
<script>
(function () {
    var isNewBoard = <?php echo $communityBoardsPage === 'new' ? 'true' : 'false'; ?>;
    var defaultLevelPostScore = '<?php echo sr_e((string) ($settings['level_post_score'] ?? 10)); ?>';
    var defaultLevelCommentScore = '<?php echo sr_e((string) ($settings['level_comment_score'] ?? 2)); ?>';
    var levelScoreTouched = {post: false, comment: false};

    function syncBoardGroupRequired() {
        var groupSelect = document.querySelector('[data-community-board-group-select]');
        var label = document.querySelector('[data-community-board-group-required]');
        if (!groupSelect) {
            return;
        }
        var syncDisabledLook = function (input) {
            var optionLabel = input && input.closest ? input.closest('.admin-form-check') : null;
            if (optionLabel) {
                optionLabel.classList.toggle('is-disabled-look', input.disabled);
            }
        };
        var hasGroup = groupSelect.value !== '0';
        Array.prototype.slice.call(document.querySelectorAll('input[name^="source_"]')).forEach(function (input) {
            if (input.value !== 'group') {
                return;
            }
            input.disabled = !hasGroup;
            syncDisabledLook(input);
            if (!hasGroup && input.checked) {
                var fallback = document.querySelector('input[name="' + input.name + '"][value="board"]');
                if (fallback) {
                    fallback.checked = true;
                }
            }
        });
        var needed = Array.prototype.slice.call(document.querySelectorAll('input[name^="source_"]')).some(function (input) {
            return input.checked && input.value === 'group';
        });
        groupSelect.required = needed;
        if (label) {
            label.hidden = !needed;
        }
    }

    function syncLevelScoreDefaults(force) {
        var groupSelect = document.querySelector('[data-community-board-group-select]');
        var post = document.querySelector('[data-community-level-score="post"]');
        var comment = document.querySelector('[data-community-level-score="comment"]');
        if (!isNewBoard || !groupSelect) {
            return;
        }

        var option = groupSelect.options[groupSelect.selectedIndex] || null;
        if (post && (force || !levelScoreTouched.post)) {
            post.value = option && option.dataset.levelPostScore ? option.dataset.levelPostScore : defaultLevelPostScore;
        }
        if (comment && (force || !levelScoreTouched.comment)) {
            comment.value = option && option.dataset.levelCommentScore ? option.dataset.levelCommentScore : defaultLevelCommentScore;
        }
    }

    function syncPolicy(kind) {
        var policy = document.querySelector('[data-community-policy="' + kind + '"]');
        var group = document.getElementById('community_admin_boards_' + kind + '_group_keys');
        if (!policy || !group) {
            return;
        }
        var first = group.querySelector('input[type="checkbox"]');
        if (first && typeof first.setCustomValidity === 'function') {
            first.setCustomValidity('');
        }
    }

    function mirrorSelectedGroupsToRead(kind) {
        var readGroup = document.getElementById('community_admin_boards_read_group_keys');
        var sourceGroup = document.getElementById('community_admin_boards_' + kind + '_group_keys');
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
        var readGroup = document.getElementById('community_admin_boards_read_group_keys');
        if (!readGroup) {
            return;
        }

        var readable = {};
        Array.prototype.slice.call(readGroup.querySelectorAll('input[type="checkbox"]:checked')).forEach(function (readCheck) {
            readable[readCheck.value] = true;
        });
        ['write', 'comment'].forEach(function (kind) {
            var group = document.getElementById('community_admin_boards_' + kind + '_group_keys');
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
        var count = document.getElementById('community_admin_boards_file_attachment_max_count');
        var enabled = document.getElementById('modules_community_admin_boards_file_uploads_enabled');
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
        var group = document.getElementById('community_admin_boards_' + kind + '_group_keys');
        var sourceRadios = document.querySelectorAll('input[name="source_' + kind + '_policy"]');
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
        sourceRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                syncPolicy(kind);
                syncBoardGroupRequired();
            });
        });
        syncPolicy(kind);
    });
    var form = document.querySelector('.admin-page-community-board-form form.admin-form');
    if (form) {
        form.addEventListener('submit', syncWritableGroupsFromRead);
    }
    syncWritableGroupsFromRead();
    syncPolicy('read');
    syncBoardGroupRequired();
    var groupSelect = document.querySelector('[data-community-board-group-select]');
    var postScore = document.querySelector('[data-community-level-score="post"]');
    var commentScore = document.querySelector('[data-community-level-score="comment"]');
    if (postScore) {
        postScore.addEventListener('input', function () {
            levelScoreTouched.post = true;
        });
    }
    if (commentScore) {
        commentScore.addEventListener('input', function () {
            levelScoreTouched.comment = true;
        });
    }
    if (groupSelect) {
        groupSelect.addEventListener('change', function () {
            syncLevelScoreDefaults(false);
            syncBoardGroupRequired();
        });
    }
    syncLevelScoreDefaults(false);
    var count = document.getElementById('community_admin_boards_file_attachment_max_count');
    var enabled = document.getElementById('modules_community_admin_boards_file_uploads_enabled');
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
