<?php

$communityBoardsPage = isset($communityBoardsPage) ? (string) $communityBoardsPage : 'list';
$adminPageTitle = '커뮤니티 게시판 관리';
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
$boardListFilters = isset($boardListFilters) && is_array($boardListFilters) ? $boardListFilters : ['status' => [], 'group_id' => 0, 'field' => 'all', 'q' => ''];
$boardSort = isset($boardSort) && is_array($boardSort) ? $boardSort : sr_community_admin_board_default_sort();
$boardStatusCounts = isset($boardStatusCounts) && is_array($boardStatusCounts) ? $boardStatusCounts : [];
$totalBoards = (int) ($boardStatusCounts['total'] ?? count($boards ?? []));
$boardGroupSettings = isset($boardGroupSettings) && is_array($boardGroupSettings) ? $boardGroupSettings : [];
$selectedBoardStatuses = is_array($boardListFilters['status'] ?? null) ? $boardListFilters['status'] : [];

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
$memberSearchUrl = sr_url('/admin/community/boards/member-search');
$assetModuleChoiceOptions = [];
$reactionPresetOptions = isset($reactionPresetOptions) && is_array($reactionPresetOptions) ? $reactionPresetOptions : ['' => '리액션 기본값'];
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
$selectedBoard = is_array($editBoard ?? null) ? $editBoard : [];
$newBoardGroupId = isset($newBoardGroupId) ? (int) $newBoardGroupId : 0;
$newBoardDefaults = sr_community_board_default_settings(
    $settings,
    $newBoardGroupId > 0 && is_array($boardGroupSettings[$newBoardGroupId] ?? null) ? $boardGroupSettings[$newBoardGroupId] : []
);
$formBoard = $communityBoardsPage === 'edit' ? $selectedBoard : array_merge($newBoardDefaults, [
    'board_group_id' => $newBoardGroupId,
    'board_key' => '',
    'title' => '',
    'description' => '',
    'sort_order' => 0,
]);
$communityBoardAssetAuditUrl = $communityBoardsPage === 'edit'
    ? sr_admin_asset_settings_audit_url('community.board.asset_settings.updated', 'community_board', (string) (int) ($formBoard['id'] ?? 0))
    : '';
$communityBoardManagerPermissions = sr_community_board_manager_permission_options();
$communityBoardManagers = $communityBoardsPage === 'edit' ? sr_community_board_managers($pdo, (int) ($formBoard['id'] ?? 0)) : [];
$communityBoardSectionNavItems = [
    'community-board-section-basic' => '기본 정보',
    'community-board-section-extra-fields' => '추가 입력 항목',
    'community-board-section-seo' => 'SEO/OG',
    'community-board-section-policy' => '접근/작성',
    'community-board-section-reaction' => '리액션',
    'community-board-section-privacy-consent' => '개인정보 동의',
    'community-board-section-policy-attachments' => '첨부',
    'community-board-section-banner' => '배너',
    'community-board-section-popup' => '팝업',
    'community-board-section-assets' => '포인트/금액',
    'community-board-section-order' => '정렬',
];
if ($communityBoardsPage === 'edit') {
    $communityBoardSectionNavItems += [
        'community-board-section-managers' => '관리권한',
        'community-board-section-categories' => '카테고리',
    ];
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

    <?php $boardDetailFilterOpen = $selectedBoardStatuses !== [] || (int) ($boardListFilters['group_id'] ?? 0) > 0; ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="filtering-form admin-community-board-filter ui-form-theme">
        <div class="filtering-fields admin-community-board-search-grid">
            <div class="filtering filtering-card<?php echo $boardDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
                <div class="filtering-fields">
                    <div class="filtering-field admin-community-board-filter-field">
                        <label for="community_admin_boards_field" class="filtering-label">검색조건</label>
                        <select id="community_admin_boards_field" name="field" class="form-select filtering-input">
                            <?php foreach (['all' => sr_t('community::ui.all.a4b69faf'), 'key' => sr_t('community::ui.key.cf056766'), 'title' => sr_t('community::ui.name.253d1510'), 'group' => sr_t('community::ui.text.5d908ddd')] as $fieldValue => $fieldLabel) { ?>
                                <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($boardListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                                    <?php echo sr_e($fieldLabel); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filtering-field-fill filtering-field admin-community-board-filter-keyword">
                        <label for="community_admin_boards_q" class="filtering-label"><?php echo sr_e(sr_t('community::ui.search.bda397fc')); ?></label>
                        <input id="community_admin_boards_q" type="text" name="q" value="<?php echo sr_e((string) ($boardListFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('community::ui.key.name.9f150e7e')); ?>">
                    </div>
                </div>
                <div id="community_admin_boards_detail_filters" class="filtering-body" data-filtering-body<?php echo $boardDetailFilterOpen ? '' : ' hidden'; ?>>
                    <div class="filtering-field admin-community-board-filter-status">
                        <span class="filtering-label"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></span>
                        <?php echo sr_admin_filter_toggle_group_html('community_admin_boards_status_filter', 'status', sr_admin_code_label_options($allowedStatuses, 'content_status'), $selectedBoardStatuses, sr_t('community::ui.all.a4b69faf')); ?>
                    </div>
                    <div class="filtering-field admin-community-board-filter-group">
                        <span class="filtering-label"><?php echo sr_e(sr_t('community::ui.text.ec060706')); ?></span>
                        <?php
                        $boardGroupFilterOptions = [];
                        foreach ($boardGroups as $boardGroup) {
                            $boardGroupId = (string) (int) ($boardGroup['id'] ?? 0);
                            if ($boardGroupId !== '0') {
                                $boardGroupFilterOptions[$boardGroupId] = (string) ($boardGroup['title'] ?? '');
                            }
                        }
                        $selectedBoardGroupFilterIds = (int) ($boardListFilters['group_id'] ?? 0) > 0 ? [(string) (int) $boardListFilters['group_id']] : [];
                        echo sr_admin_filter_radio_toggle_group_html('community_admin_boards_group_filter', 'group_id', $boardGroupFilterOptions, $selectedBoardGroupFilterIds, sr_t('community::ui.all.a4b69faf'));
                        ?>
                    </div>
                </div>
                <div class="filtering-actions">
                    <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $boardDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="community_admin_boards_detail_filters">상세검색</button>
                    <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                    <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
                </div>
            </div>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('community::ui.list.a62deef1')); ?></h2>
            <a href="<?php echo sr_e(sr_url('/admin/community/boards/new' . ((int) ($boardListFilters['group_id'] ?? 0) > 0 ? '?group_id=' . rawurlencode((string) (int) $boardListFilters['group_id']) : ''))); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('community::ui.text.97f92efb')); ?></a>
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
                    <th<?php echo sr_admin_sort_aria('board_key', $boardSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.key.cf056766'), 'board_key', $boardSort, sr_community_admin_board_sort_options(), sr_community_admin_board_default_sort()); ?></th>
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
                                <a href="<?php echo sr_e(sr_url('/admin/community/boards/copy?id=' . rawurlencode((string) $board['id']))); ?>" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e('복사'); ?>" title="<?php echo sr_e('복사'); ?>"><?php echo sr_material_icon_html('content_copy'); ?></a>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="admin-inline-form">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="delete_board">
                                    <input type="hidden" name="board_id" value="<?php echo sr_e((string) $board['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="게시판 삭제" title="게시판 삭제" onclick="return confirm('이 게시판을 삭제할까요? 게시글, 댓글, 첨부파일, 시리즈 연결도 함께 삭제됩니다. 외부 운영 참조가 있으면 삭제되지 않습니다.');"><?php echo sr_material_icon_html('delete'); ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
	        </table>
	        </div>
        <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('open_in_new'); ?> <?php echo sr_e(sr_t('community::ui.text.910d9d5a')); ?></span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> <?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?></span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('content_copy'); ?> 복사</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('delete'); ?> 게시판 삭제</span>
        </div>
	    </section>
    <?php echo sr_admin_pagination_html($boardPagination, '게시판 목록 페이지'); ?>
    <?php $communityStorageCleanupFailures = is_array($communityStorageCleanupFailures ?? null) ? $communityStorageCleanupFailures : []; ?>
    <?php if ($communityStorageCleanupFailures !== []) { ?>
        <section class="admin-card admin-list-card card admin-list-form">
            <div class="card-header">
                <h2 class="card-title">저장소 정리 실패</h2>
                <p class="admin-dashboard-meta">게시판 삭제 후 남은 첨부 파일 정리 대상입니다.</p>
            </div>
            <div class="table-wrapper">
                <table class="table">
                    <caption class="sr-only">커뮤니티 저장소 정리 실패 목록</caption>
                    <thead class="ui-table-head">
                        <tr>
                            <th>대상</th>
                            <th>저장소</th>
                            <th>시도</th>
                            <th>오류</th>
                            <th class="text-end">작업</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($communityStorageCleanupFailures as $cleanupFailure) { ?>
                            <tr>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($cleanupFailure['source_type'] ?? '')); ?> #<?php echo sr_e((string) (int) ($cleanupFailure['source_id'] ?? 0)); ?></td>
                                <td class="admin-table-break"><code><?php echo sr_e((string) ($cleanupFailure['storage_driver'] ?? 'local')); ?>:<?php echo sr_e((string) ($cleanupFailure['storage_key'] ?? '')); ?></code></td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($cleanupFailure['attempt_count'] ?? 0)); ?>회</td>
                                <td class="admin-table-break"><?php echo sr_e((string) ($cleanupFailure['last_error'] ?? '')); ?></td>
                                <td class="admin-table-actions-cell">
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="retry_storage_cleanup_failure">
                                        <input type="hidden" name="failure_id" value="<?php echo sr_e((string) (int) ($cleanupFailure['id'] ?? 0)); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">재시도</button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php } ?>
<?php } else { ?>
    <nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="게시판 설정 섹션">
        <?php $communityBoardSectionNavIndex = 0; ?>
        <?php foreach ($communityBoardSectionNavItems as $sectionId => $sectionLabel) { ?>
            <a href="#<?php echo sr_e((string) $sectionId); ?>" class="tab-trigger-underline-justified<?php echo $communityBoardSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $communityBoardSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
                <?php echo sr_e((string) $sectionLabel); ?>
            </a>
            <?php $communityBoardSectionNavIndex++; ?>
        <?php } ?>
    </nav>

    <form method="post" action="<?php echo sr_e(sr_url($communityBoardsPage === 'edit' ? '/admin/community/boards/update' : '/admin/community/boards/create')); ?>" class="admin-form ui-form-theme">
        <section id="community-board-section-basic" class="admin-card card" data-admin-section-anchor>
            <h2><?php echo sr_e($communityBoardsPage === 'edit' ? sr_t('community::ui.edit.e92ca332') : sr_t('community::ui.text.713b7a18')); ?></h2>
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

        <section id="community-board-section-extra-fields" class="admin-card admin-list-card card admin-list-form" data-admin-section-anchor data-community-extra-fields-builder>
            <div class="card-header">
                <h2 class="card-title">추가 입력 항목</h2>
                <div class="admin-row-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="community-extra-field-modal" data-overlay="#community-extra-field-modal" data-community-extra-field-add>항목 추가</button>
                </div>
            </div>
            <p class="admin-form-help">게시글 작성/수정 폼에서 받을 추가 항목을 관리합니다. 항목 추가, 수정, 정렬, 제거 후 게시판 저장을 눌러야 최종 반영됩니다.</p>
            <div class="table-wrapper" data-community-extra-field-table-wrap hidden>
                <table class="table" data-community-extra-field-table>
                    <caption class="sr-only">추가 입력 항목 목록</caption>
                    <thead class="ui-table-head">
                        <tr>
                            <th>순서</th>
                            <th>라벨</th>
                            <th>유형</th>
                            <th>표시</th>
                            <th>개인정보</th>
                            <th class="text-end">작업</th>
                        </tr>
                    </thead>
                    <tbody data-community-extra-field-list></tbody>
                </table>
            </div>
            <p class="admin-empty-state" data-community-extra-field-empty hidden>추가 입력 항목이 없습니다.</p>
            <textarea id="community_admin_boards_extra_fields_json" name="extra_fields_json" hidden data-community-extra-fields-json><?php echo sr_e($boardField($formBoard, 'extra_fields_json', '[]')); ?></textarea>
            <div class="admin-setting-source-line admin-setting-source-line-end">
                <span class="sr-only">저장 범위</span>
                <?php echo $settingSourceRadioHtml('source_extra_fields_json', $boardSettingSource($formBoard, 'extra_fields_json')); ?>
            </div>
        </section>

        <section id="community-board-section-seo" class="admin-card card" data-admin-section-anchor>
            <h2>SEO/OG 메타</h2>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_seo_title">SEO 제목</label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_seo_title" type="text" name="seo_title" maxlength="160" value="<?php echo sr_e($boardField($formBoard, 'seo_title')); ?>" class="form-input form-control-full">
                    <?php echo $settingSourceRadioHtml('source_seo_title', $boardSettingSource($formBoard, 'seo_title')); ?>
                    <p class="admin-form-help">비워 두면 게시판 이름을 사용합니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_seo_description">SEO 설명</label>
                <div class="admin-form-field">
                    <textarea id="community_admin_boards_seo_description" name="seo_description" rows="3" cols="60" maxlength="255" class="form-textarea form-control-full"><?php echo sr_e($boardField($formBoard, 'seo_description')); ?></textarea>
                    <?php echo $settingSourceRadioHtml('source_seo_description', $boardSettingSource($formBoard, 'seo_description')); ?>
                    <p class="admin-form-help">비워 두면 게시판 설명을 사용합니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_og_title">OG 제목</label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_og_title" type="text" name="og_title" maxlength="160" value="<?php echo sr_e($boardField($formBoard, 'og_title')); ?>" class="form-input form-control-full">
                    <?php echo $settingSourceRadioHtml('source_og_title', $boardSettingSource($formBoard, 'og_title')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_og_description">OG 설명</label>
                <div class="admin-form-field">
                    <textarea id="community_admin_boards_og_description" name="og_description" rows="3" cols="60" maxlength="255" class="form-textarea form-control-full"><?php echo sr_e($boardField($formBoard, 'og_description')); ?></textarea>
                    <?php echo $settingSourceRadioHtml('source_og_description', $boardSettingSource($formBoard, 'og_description')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_og_image_url">OG 이미지 URL</label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_og_image_url" type="url" name="og_image_url" maxlength="255" value="<?php echo sr_e($boardField($formBoard, 'og_image_url')); ?>" class="form-input form-control-full" placeholder="/storage/seo/example.webp">
                    <?php echo $settingSourceRadioHtml('source_og_image_url', $boardSettingSource($formBoard, 'og_image_url')); ?>
                    <p class="admin-form-help">http(s) URL 또는 /로 시작하는 내부 경로만 사용할 수 있습니다. 게시글 지정 이미지가 없을 때 게시판 기본값으로 사용됩니다.</p>
                </div>
            </div>
        </section>

        <section id="community-board-section-policy" class="admin-card card" data-admin-section-anchor>
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
                    <?php echo sr_admin_member_group_key_badge_select_html('community_admin_boards_read_group_keys', 'read_group_keys', is_array($formBoard['read_group_keys'] ?? null) ? $formBoard['read_group_keys'] : [], $enabledMemberGroups); ?>
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
                    <?php echo sr_admin_member_group_key_badge_select_html('community_admin_boards_write_group_keys', 'write_group_keys', is_array($formBoard['write_group_keys'] ?? null) ? $formBoard['write_group_keys'] : [], $enabledMemberGroups); ?>
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
                    <?php echo sr_admin_member_group_key_badge_select_html('community_admin_boards_comment_group_keys', 'comment_group_keys', is_array($formBoard['comment_group_keys'] ?? null) ? $formBoard['comment_group_keys'] : [], $enabledMemberGroups); ?>
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
                <label class="form-label" for="community_admin_boards_category_enabled">카테고리 사용</label>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="community_admin_boards_category_enabled">
                        <input id="community_admin_boards_category_enabled" type="checkbox" name="category_enabled" value="1" class="form-switch form-choice-dark"<?php echo $boardField($formBoard, 'category_enabled', '1') === '1' ? ' checked' : ''; ?> data-community-category-enabled>
                        <?php echo sr_admin_choice_label_html('게시글 작성/목록에서 카테고리 선택과 필터 사용'); ?>
                    </label>
                    <?php echo $settingSourceRadioHtml('source_category_enabled', $boardSettingSource($formBoard, 'category_enabled')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php
                $communityBoardCategoriesForPolicy = is_array($formBoard['categories'] ?? null) ? $formBoard['categories'] : [];
                $communityBoardActiveCategoryCount = 0;
                foreach ($communityBoardCategoriesForPolicy as $communityBoardCategoryForPolicy) {
                    if ((string) ($communityBoardCategoryForPolicy['status'] ?? '') === 'enabled') {
                        $communityBoardActiveCategoryCount++;
                    }
                }
                $communityBoardCategoryRequiredSelectable = $communityBoardActiveCategoryCount > 0;
                $communityBoardCategoryRequiredChecked = $communityBoardCategoryRequiredSelectable && $boardField($formBoard, 'category_required', '0') === '1';
                ?>
                <label class="form-label" for="community_admin_boards_category_required">카테고리 필수</label>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="community_admin_boards_category_required">
                        <input id="community_admin_boards_category_required" type="checkbox" name="category_required" value="1" class="form-switch form-choice-dark"<?php echo $communityBoardCategoryRequiredChecked ? ' checked' : ''; ?><?php echo $communityBoardCategoryRequiredSelectable ? '' : ' disabled'; ?> data-community-category-required>
                        <?php echo sr_admin_choice_label_html('게시글 작성/수정 시 카테고리를 반드시 선택'); ?>
                    </label>
                    <?php echo $settingSourceRadioHtml('source_category_required', $boardSettingSource($formBoard, 'category_required')); ?>
                    <p class="admin-form-help">활성 카테고리가 1개 이상 있을 때만 켤 수 있습니다. 필수로 선택하면 카테고리 사용도 함께 켜집니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_secret_posts_enabled">비밀글</label>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="community_admin_boards_secret_posts_enabled">
                        <input id="community_admin_boards_secret_posts_enabled" type="checkbox" name="secret_posts_enabled" value="1" class="form-switch form-choice-dark"<?php echo $boardField($formBoard, 'secret_posts_enabled', !empty($settings['secret_posts_enabled']) ? '1' : '0') === '1' ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('게시글 작성/수정 시 비밀글 선택 허용'); ?>
                    </label>
                    <?php echo $settingSourceRadioHtml('source_secret_posts_enabled', $boardSettingSource($formBoard, 'secret_posts_enabled')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_secret_comments_enabled">비밀 댓글</label>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="community_admin_boards_secret_comments_enabled">
                        <input id="community_admin_boards_secret_comments_enabled" type="checkbox" name="secret_comments_enabled" value="1" class="form-switch form-choice-dark"<?php echo $boardField($formBoard, 'secret_comments_enabled', !empty($settings['secret_comments_enabled']) ? '1' : '0') === '1' ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('댓글 작성/수정 시 비밀 댓글 선택 허용'); ?>
                    </label>
                    <?php echo $settingSourceRadioHtml('source_secret_comments_enabled', $boardSettingSource($formBoard, 'secret_comments_enabled')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_post_edit_lock_comment_count">게시글 수정 잠금 댓글 수 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_post_edit_lock_comment_count" type="number" name="post_edit_lock_comment_count" min="0" max="1000000" value="<?php echo sr_e($boardField($formBoard, 'post_edit_lock_comment_count', '0')); ?>" required class="form-input">
                    <?php echo $settingSourceRadioHtml('source_post_edit_lock_comment_count', $boardSettingSource($formBoard, 'post_edit_lock_comment_count')); ?>
                    <p class="admin-form-help">0이면 댓글 수로 게시글 수정을 잠그지 않습니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_post_delete_lock_comment_count">게시글 삭제 잠금 댓글 수 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_post_delete_lock_comment_count" type="number" name="post_delete_lock_comment_count" min="0" max="1000000" value="<?php echo sr_e($boardField($formBoard, 'post_delete_lock_comment_count', '0')); ?>" required class="form-input">
                    <?php echo $settingSourceRadioHtml('source_post_delete_lock_comment_count', $boardSettingSource($formBoard, 'post_delete_lock_comment_count')); ?>
                    <p class="admin-form-help">0이면 댓글 수로 게시글 삭제를 잠그지 않습니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_post_body_min_length">게시글 본문 최소 길이 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_post_body_min_length" type="number" name="post_body_min_length" min="0" max="20000" value="<?php echo sr_e($boardField($formBoard, 'post_body_min_length', '0')); ?>" required class="form-input">
                    <?php echo $settingSourceRadioHtml('source_post_body_min_length', $boardSettingSource($formBoard, 'post_body_min_length')); ?>
                    <p class="admin-form-help">0이면 최소 길이를 검사하지 않습니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_post_body_max_length">게시글 본문 최대 길이 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_post_body_max_length" type="number" name="post_body_max_length" min="0" max="20000" value="<?php echo sr_e($boardField($formBoard, 'post_body_max_length', '0')); ?>" required class="form-input">
                    <?php echo $settingSourceRadioHtml('source_post_body_max_length', $boardSettingSource($formBoard, 'post_body_max_length')); ?>
                    <p class="admin-form-help">0이면 최대 길이를 검사하지 않습니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_comment_body_min_length">댓글 본문 최소 길이 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_comment_body_min_length" type="number" name="comment_body_min_length" min="0" max="5000" value="<?php echo sr_e($boardField($formBoard, 'comment_body_min_length', '0')); ?>" required class="form-input">
                    <?php echo $settingSourceRadioHtml('source_comment_body_min_length', $boardSettingSource($formBoard, 'comment_body_min_length')); ?>
                    <p class="admin-form-help">0이면 최소 길이를 검사하지 않습니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_comment_body_max_length">댓글 본문 최대 길이 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_comment_body_max_length" type="number" name="comment_body_max_length" min="0" max="5000" value="<?php echo sr_e($boardField($formBoard, 'comment_body_max_length', '0')); ?>" required class="form-input">
                    <?php echo $settingSourceRadioHtml('source_comment_body_max_length', $boardSettingSource($formBoard, 'comment_body_max_length')); ?>
                    <p class="admin-form-help">0이면 최대 길이를 검사하지 않습니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_list_excerpt_enabled">목록 본문 요약</label>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="community_admin_boards_list_excerpt_enabled">
                        <input id="community_admin_boards_list_excerpt_enabled" type="checkbox" name="list_excerpt_enabled" value="1" class="form-switch form-choice-dark"<?php echo $boardField($formBoard, 'list_excerpt_enabled', '0') === '1' ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('게시글 목록에 본문 요약 표시'); ?>
                    </label>
                    <?php echo $settingSourceRadioHtml('source_list_excerpt_enabled', $boardSettingSource($formBoard, 'list_excerpt_enabled')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_list_excerpt_length">목록 본문 요약 길이 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_list_excerpt_length" type="number" name="list_excerpt_length" min="1" max="1000" value="<?php echo sr_e($boardField($formBoard, 'list_excerpt_length', '120')); ?>" required class="form-input">
                    <?php echo $settingSourceRadioHtml('source_list_excerpt_length', $boardSettingSource($formBoard, 'list_excerpt_length')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_list_per_page">목록 페이지당 글 수 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_list_per_page" type="number" name="list_per_page" min="1" max="100" value="<?php echo sr_e($boardField($formBoard, 'list_per_page', (string) ($settings['posts_per_page'] ?? 20))); ?>" required class="form-input">
                    <?php echo $settingSourceRadioHtml('source_list_per_page', $boardSettingSource($formBoard, 'list_per_page')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_list_default_sort">목록 기본 정렬 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="community_admin_boards_list_default_sort" name="list_default_sort" class="form-select" required>
                        <?php foreach (sr_community_board_list_sort_values() as $communitySortKey) { ?>
                            <option value="<?php echo sr_e($communitySortKey); ?>"<?php echo $boardField($formBoard, 'list_default_sort', 'latest') === $communitySortKey ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($communitySortKey, 'sort')); ?></option>
                        <?php } ?>
                    </select>
                    <?php echo $settingSourceRadioHtml('source_list_default_sort', $boardSettingSource($formBoard, 'list_default_sort')); ?>
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
        </section>

        <section id="community-board-section-reaction" class="admin-card card" data-admin-section-anchor>
            <h2>리액션</h2>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_reaction_post_preset_key">게시글 리액션 프리셋</label>
                <div class="admin-form-field">
                    <select id="community_admin_boards_reaction_post_preset_key" name="reaction_post_preset_key" class="form-select">
                        <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                            <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo $boardField($formBoard, 'reaction_post_preset_key', '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                        <?php } ?>
                    </select>
                    <?php echo $settingSourceRadioHtml('source_reaction_post_preset_key', $boardSettingSource($formBoard, 'reaction_post_preset_key')); ?>
                    <p class="admin-form-help">비어 있으면 게시판 그룹 설정을, 그룹 설정도 비어 있으면 커뮤니티 환경설정을 사용합니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_reaction_comment_preset_key">댓글 리액션 프리셋</label>
                <div class="admin-form-field">
                    <select id="community_admin_boards_reaction_comment_preset_key" name="reaction_comment_preset_key" class="form-select">
                        <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                            <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo $boardField($formBoard, 'reaction_comment_preset_key', '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                        <?php } ?>
                    </select>
                    <?php echo $settingSourceRadioHtml('source_reaction_comment_preset_key', $boardSettingSource($formBoard, 'reaction_comment_preset_key')); ?>
                </div>
            </div>
        </section>

        <section id="community-board-section-privacy-consent" class="admin-card card" data-admin-section-anchor>
            <h2>개인정보 수집 및 이용동의</h2>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_privacy_consent_enabled">동의 사용</label>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="community_admin_boards_privacy_consent_enabled">
                        <input id="community_admin_boards_privacy_consent_enabled" type="checkbox" name="privacy_consent_enabled" value="1" class="form-switch form-choice-dark"<?php echo $boardField($formBoard, 'privacy_consent_enabled', '0') === '1' ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('이 게시판 제출 흐름에 개인정보 수집 및 이용동의를 표시하고 서버에서 검증'); ?>
                    </label>
                    <?php echo $settingSourceRadioHtml('source_privacy_consent_enabled', $boardSettingSource($formBoard, 'privacy_consent_enabled')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_privacy_consent_document_key">정책 문서 키</label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_privacy_consent_document_key" type="text" name="privacy_consent_document_key" maxlength="80" pattern="[a-z][a-z0-9_]{2,79}" value="<?php echo sr_e($boardField($formBoard, 'privacy_consent_document_key', 'community_privacy_default')); ?>" class="form-input form-control-full" data-admin-key-input>
                    <?php echo $settingSourceRadioHtml('source_privacy_consent_document_key', $boardSettingSource($formBoard, 'privacy_consent_document_key')); ?>
                    <input type="hidden" name="privacy_consent_title" value="">
                    <input type="hidden" name="privacy_consent_version" value="">
                    <input type="hidden" name="privacy_consent_body" value="">
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">적용 대상</span>
                <div class="admin-form-field">
                    <?php foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) { ?>
                        <?php $privacyConsentSettingKey = 'privacy_consent_require_' . $privacyConsentTargetKey; ?>
                        <label class="admin-form-check form-label" for="community_admin_boards_<?php echo sr_e($privacyConsentSettingKey); ?>">
                            <input id="community_admin_boards_<?php echo sr_e($privacyConsentSettingKey); ?>" type="checkbox" name="<?php echo sr_e($privacyConsentSettingKey); ?>" value="1" class="form-checkbox"<?php echo $boardField($formBoard, $privacyConsentSettingKey, '0') === '1' ? ' checked' : ''; ?>>
                            <?php echo sr_admin_choice_label_html(sr_community_privacy_consent_label($privacyConsentTargetKey)); ?>
                        </label>
                        <?php echo $settingSourceRadioHtml('source_' . $privacyConsentSettingKey, $boardSettingSource($formBoard, $privacyConsentSettingKey)); ?>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section id="community-board-section-policy-attachments" class="admin-card card" data-admin-section-anchor>
            <h2>첨부</h2>
            <div class="admin-form-row">
                <div class="form-label admin-form-label-help"><?php echo $communityBoardHelpButtonHtml(sr_t('community::ui.text.c3bd14cb'), $communityBoardHelp['attachments']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.c3bd14cb')); ?></span></div>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_boards_image_uploads_enabled">
                                            <input id="modules_community_admin_boards_image_uploads_enabled" type="checkbox" name="image_uploads_enabled" value="1" class="form-switch form-choice-dark"<?php echo (int) $boardField($formBoard, 'image_uploads_enabled', '1') === 1 ? ' checked' : ''; ?>>
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
                                            <input id="modules_community_admin_boards_file_uploads_enabled" type="checkbox" name="file_uploads_enabled" value="1" class="form-switch form-choice-dark"<?php echo in_array($boardField($formBoard, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
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

        <section id="community-board-section-banner" class="admin-card card" data-admin-section-anchor>
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

        <section id="community-board-section-popup" class="admin-card card" data-admin-section-anchor>
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

        <section id="community-board-section-assets" class="admin-card card" data-admin-section-anchor>
            <h2>
                <span><?php echo sr_e(sr_t('community::ui.member.415a098e')); ?></span>
                <?php if ($communityBoardAssetAuditUrl !== '') { ?>
                    <span class="admin-form-actions">
                        <a href="<?php echo sr_e($communityBoardAssetAuditUrl); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('포인트/금액 설정 변경 이력'); ?></a>
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
                    <?php $assetSourceId = 'community_board_' . (string) $assetPrefix . '_asset_source'; ?>
                    <?php $usesCompositeAsset = sr_community_asset_prefix_uses_composite((string) $assetPrefix); ?>
                    <?php $usesGroupedAssetAmounts = $usesCompositeAsset; ?>
                    <?php $selectedAssetModules = sr_community_asset_module_keys_from_value($boardField($formBoard, $assetPrefix . '_asset_module', ''), true); ?>
                    <div class="admin-form-row">
                        <div class="form-label admin-form-label-help"><?php echo $communityBoardHelpButtonHtml($assetLabel, $communityBoardHelp['asset_settings']['id']); ?><span><?php echo sr_e($assetLabel); ?> 사용</span></div>
                        <div class="admin-form-field">
                            <div class="admin-asset-setting-line">
                                <div class="admin-asset-setting-control">
                                    <div class="admin-asset-setting-primary">
                                        <label class="admin-form-check form-label" for="<?php echo sr_e($assetEnabledId); ?>">
                                            <input id="<?php echo sr_e($assetEnabledId); ?>" type="checkbox" name="<?php echo sr_e($assetPrefix); ?>_enabled" value="1" class="form-switch form-choice-dark"<?php echo in_array($boardField($formBoard, $assetPrefix . '_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
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
                        <span class="form-label"><?php echo sr_e($assetLabel . ' 자산 설정'); ?></span>
                        <div class="admin-form-field">
                            <?php if ($usesGroupedAssetAmounts) { ?>
                                <div class="admin-asset-setting-target" data-admin-asset-enable-target="#<?php echo sr_e($assetEnabledId); ?>">
                                    <?php echo sr_community_asset_grouped_amount_inputs_html($assetSourceId, (string) $assetPrefix . '_asset_module', (string) $assetPrefix . '_amounts', $assetModuleOptions, $selectedAssetModules, $boardField($formBoard, $assetPrefix . '_amounts_json', ''), (int) $boardField($formBoard, $assetPrefix . '_amount', '0'), sr_t('community::ui.asset.amount.0df01f4b', ['label' => $assetLabel]), sr_t('community::ui.text.3e195cdd')); ?>
                                </div>
                            <?php } else { ?>
                                <div class="admin-asset-setting-target admin-asset-single-setting-target" data-admin-asset-enable-target="#<?php echo sr_e($assetEnabledId); ?>">
                                    <select id="<?php echo sr_e($assetSourceId); ?>" name="<?php echo sr_e($assetPrefix); ?>_asset_module" class="form-select" data-admin-asset-unit-select>
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
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e('community_board_' . (string) $assetPrefix . '_policy_set_ids'); ?>"><?php echo sr_e('회원 그룹별 적용'); ?></label>
                        <div class="admin-form-field admin-policy-set-field">
                            <div class="admin-asset-setting-scope admin-asset-setting-scope-inline">
                                <?php echo $settingSourceRadioHtml('source_' . (string) $assetPrefix . '_policy_set_id', $boardSettingSource($formBoard, (string) $assetPrefix . '_policy_set_id')); ?>
                            </div>
                            <?php echo sr_community_asset_policy_set_checkboxes_html('community_board_' . (string) $assetPrefix . '_policy_set_ids', (string) $assetPrefix . '_policy_set_ids', $assetPolicySets ?? [], sr_community_asset_policy_set_ids_with_legacy($boardField($formBoard, $assetPrefix . '_group_policies_json', ''), (int) $boardField($formBoard, $assetPrefix . '_policy_set_id', '0')), $usesCompositeAsset ? 'use' : 'grant', '#' . $assetSourceId, $pdo); ?>
                            <p class="admin-form-help">도움말: 선택한 회원 그룹별 적용이 회원의 그룹, 레벨, 대상 항목에 맞는 실제 금액을 계산합니다. 세트의 계산 방식과 조정값은 커뮤니티 회원 그룹별 설정 화면에서 관리합니다.</p>
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
                        <div class="admin-form-row">
                            <span class="form-label">게시자 리워드</span>
                            <div class="admin-form-field">
                                <label class="admin-form-check form-label" for="community_board_paid_attachment_download_publisher_reward_enabled">
                                    <input id="community_board_paid_attachment_download_publisher_reward_enabled" type="checkbox" name="paid_attachment_download_publisher_reward_enabled" value="1" class="form-switch form-choice-dark"<?php echo $boardField($formBoard, 'paid_attachment_download_publisher_reward_enabled', '0') === '1' ? ' checked' : ''; ?>>
                                    <?php echo sr_admin_choice_label_html('첨부 다운로드 차감 성공 시 게시자에게 리워드 지급'); ?>
                                </label>
                                <?php echo $settingSourceRadioHtml('source_paid_attachment_download_publisher_reward_enabled', $boardSettingSource($formBoard, 'paid_attachment_download_publisher_reward_enabled')); ?>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="community_board_paid_attachment_download_publisher_reward_rate">게시자 리워드 지급률</label>
                            <div class="admin-form-field">
                                <div class="input-group admin-asset-single-amount-group">
                                    <input id="community_board_paid_attachment_download_publisher_reward_rate" type="number" min="0" max="100" name="paid_attachment_download_publisher_reward_rate" value="<?php echo sr_e($boardField($formBoard, 'paid_attachment_download_publisher_reward_rate', '0')); ?>" class="form-input">
                                    <span class="input-group-text">%</span>
                                </div>
                                <?php echo $settingSourceRadioHtml('source_paid_attachment_download_publisher_reward_rate', $boardSettingSource($formBoard, 'paid_attachment_download_publisher_reward_rate')); ?>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>

        <section id="community-board-section-order" class="admin-card card" data-admin-section-anchor>
            <h2><?php echo sr_e(sr_t('community::ui.text.3788952d')); ?></h2>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_boards_sort_order', sr_t('community::ui.text.7d2dc215'), $communityBoardHelp['sort_order']['id'], $communityBoardHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <input id="community_admin_boards_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e($boardField($formBoard, 'sort_order', '0')); ?>" required class="form-input">
                </div>
            </div>
        </section>
        <?php if ($communityBoardsPage === 'edit') { ?>
            <?php $boardDeleteModalId = 'community-board-delete-modal'; ?>
        <?php } ?>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.list.f07b3200')); ?></a>
            <?php if ($communityBoardsPage === 'edit') { ?>
                <a href="<?php echo sr_e(sr_url('/admin/community/boards/copy?id=' . rawurlencode((string) $formBoard['id']))); ?>" class="btn btn-solid-light"><?php echo sr_e('복사'); ?></a>
                <button type="button" class="btn btn-outline-danger" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($boardDeleteModalId); ?>" data-overlay="#<?php echo sr_e($boardDeleteModalId); ?>">삭제</button>
            <?php } ?>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e($communityBoardsPage === 'edit' ? sr_t('community::ui.text.16f64fe4') : sr_t('community::ui.text.167eff27')); ?></button>
        </div>
    </form>

    <div id="community-extra-field-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="community-extra-field-modal-label" aria-hidden="true" inert data-community-extra-field-modal>
        <div class="modal-dialog modal-dialog-lg">
            <div class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="community-extra-field-modal-label" class="modal-title" data-community-extra-field-modal-title>추가 입력 항목</h3>
                    <button type="button" class="modal-close" aria-label="닫기" data-overlay="#community-extra-field-modal"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" value="" data-community-extra-field-index>
                    <div class="admin-form-row">
                        <label class="form-label" for="community_extra_field_key">관리용 키 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <input id="community_extra_field_key" type="text" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input data-community-extra-field-input="key" data-overlay-focus class="form-input">
                            <p class="admin-form-help">소문자, 숫자, _만 사용합니다.</p>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="community_extra_field_label">라벨 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <input id="community_extra_field_label" type="text" maxlength="120" required data-community-extra-field-input="label" class="form-input form-control-full">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="community_extra_field_type">유형 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <select id="community_extra_field_type" required data-community-extra-field-input="type" class="form-select">
                                <option value="text">텍스트</option>
                                <option value="textarea">긴 텍스트</option>
                                <option value="select">선택</option>
                                <option value="checkbox">체크박스</option>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row" data-community-extra-field-options-row>
                        <label class="form-label" for="community_extra_field_options">선택지 <span class="sr-required-label" data-community-extra-field-options-required hidden>(필수)</span></label>
                        <div class="admin-form-field">
                            <textarea id="community_extra_field_options" rows="4" maxlength="6000" data-community-extra-field-input="options" class="form-textarea form-control-full"></textarea>
                            <p class="admin-form-help">한 줄에 하나씩 입력합니다.</p>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <span class="form-label">필수 여부</span>
                        <div class="admin-form-field">
                            <label class="admin-form-check form-label" for="community_extra_field_required">
                                <input id="community_extra_field_required" type="checkbox" value="1" class="form-switch form-choice-dark" data-community-extra-field-input="required">
                                <?php echo sr_admin_choice_label_html('필수 입력'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="community_extra_field_visibility">공개 범위</label>
                        <div class="admin-form-field">
                            <select id="community_extra_field_visibility" data-community-extra-field-input="visibility" class="form-select">
                                <option value="public">공개</option>
                                <option value="admin">관리자 전용</option>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <span class="form-label">표시 위치</span>
                        <div class="admin-form-field">
                            <label class="admin-form-check form-label" for="community_extra_field_show_on_view">
                                <input id="community_extra_field_show_on_view" type="checkbox" value="1" class="form-switch form-choice-dark" data-community-extra-field-input="show_on_view">
                                <?php echo sr_admin_choice_label_html('본문 화면 표시'); ?>
                            </label>
                            <label class="admin-form-check form-label" for="community_extra_field_show_in_admin">
                                <input id="community_extra_field_show_in_admin" type="checkbox" value="1" class="form-switch form-choice-dark" data-community-extra-field-input="show_in_admin">
                                <?php echo sr_admin_choice_label_html('관리자 목록 표시'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="community_extra_field_privacy_purpose">개인정보 목적</label>
                        <div class="admin-form-field">
                            <input id="community_extra_field_privacy_purpose" type="text" maxlength="255" data-community-extra-field-input="privacy_purpose" class="form-input form-control-full">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="community_extra_field_export_policy">Export 정책</label>
                        <div class="admin-form-field">
                            <select id="community_extra_field_export_policy" data-community-extra-field-input="export_policy" class="form-select">
                                <option value="include">포함</option>
                                <option value="exclude">제외</option>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="community_extra_field_cleanup_policy">Cleanup 정책</label>
                        <div class="admin-form-field">
                            <select id="community_extra_field_cleanup_policy" data-community-extra-field-input="cleanup_policy" class="form-select">
                                <option value="anonymize">익명화</option>
                                <option value="retain">보관</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-note">
                    <p class="admin-form-help">이 모달의 적용 버튼은 현재 게시판 저장 form의 추가 입력 항목 값만 바꿉니다. 최종 반영은 게시판 저장 버튼을 눌러야 합니다.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#community-extra-field-modal">닫기</button>
                    <button type="button" class="btn btn-solid-primary modal-action" data-community-extra-field-save>적용</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($communityBoardsPage === 'edit') { ?>
        <?php $boardDeleteCheck = sr_community_can_delete_board($pdo, (int) ($formBoard['id'] ?? 0)); ?>
        <?php $boardDeleteReferences = is_array($boardDeleteCheck['references'] ?? null) ? $boardDeleteCheck['references'] : []; ?>
        <?php $boardDeleteTargetRecords = (int) ($boardDeleteReferences['posts'] ?? 0) + (int) ($boardDeleteReferences['comments'] ?? 0) + (int) ($boardDeleteReferences['attachments'] ?? 0) + (int) ($boardDeleteReferences['series'] ?? 0); ?>
        <?php $boardDeleteLoad = sr_admin_high_load_assessment([
            'target_records' => $boardDeleteTargetRecords,
            'file_operations' => (int) ($boardDeleteReferences['attachments'] ?? 0),
            'table_count' => 8,
            'long_transaction' => true,
            'rollback_limited' => true,
        ]); ?>
        <?php $boardDeleteConfirmText = '삭제 ' . (string) ($formBoard['board_key'] ?? ''); ?>
        <div id="<?php echo sr_e($boardDeleteModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($boardDeleteModalId); ?>-label" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="modal-content admin-form ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($boardDeleteModalId); ?>-label" class="modal-title">게시판 삭제</h3>
                        <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($boardDeleteModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="delete_board">
                        <input type="hidden" name="board_id" value="<?php echo sr_e((string) ($formBoard['id'] ?? 0)); ?>">
                        <p class="admin-form-help">
                            게시판을 삭제하면 게시판 설정, 설정 소스, 카테고리가 함께 삭제됩니다.
                            게시글 <?php echo sr_e((string) (int) ($boardDeleteCheck['references']['posts'] ?? 0)); ?>건,
                            댓글 <?php echo sr_e((string) (int) ($boardDeleteCheck['references']['comments'] ?? 0)); ?>건,
                            첨부 <?php echo sr_e((string) (int) ($boardDeleteCheck['references']['attachments'] ?? 0)); ?>건,
                            시리즈 <?php echo sr_e((string) (int) ($boardDeleteCheck['references']['series'] ?? 0)); ?>건,
                            외부 참조 <?php echo sr_e((string) array_sum(array_map('intval', is_array($boardDeleteCheck['external_references'] ?? null) ? $boardDeleteCheck['external_references'] : []))); ?>건.
                            현재 편집 중인 변경사항은 삭제 실행 전에 저장되지 않습니다.
                        </p>
                        <dl class="admin-meta-list">
                            <dt><?php echo sr_e('부하 등급'); ?></dt>
                            <dd><?php echo sr_e((string) $boardDeleteLoad['label']); ?></dd>
                            <dt><?php echo sr_e('중단/실패 시 상태'); ?></dt>
                            <dd><?php echo sr_e((string) $boardDeleteLoad['failure_state']); ?></dd>
                            <dt><?php echo sr_e('권장 실행 시점'); ?></dt>
                            <dd><?php echo sr_e((string) $boardDeleteLoad['recommended_time']); ?></dd>
                        </dl>
                        <div class="admin-form-row">
                            <label class="form-label" for="community_board_delete_confirm_text">삭제 확인 문구 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                            <div class="admin-form-field">
                                <input id="community_board_delete_confirm_text" type="text" name="delete_confirm_text" maxlength="80" class="form-input" required>
                                <p class="admin-form-help"><?php echo sr_e('삭제하려면 "' . $boardDeleteConfirmText . '"를 입력하세요.'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($boardDeleteModalId); ?>">닫기</button>
                        <button type="submit" class="btn btn-outline-danger modal-action">삭제</button>
                    </div>
                </form>
            </div>
        </div>

        <section id="community-board-section-managers" class="admin-card admin-list-card card admin-list-form" data-admin-section-anchor>
            <div class="card-header">
                <h2 class="card-title">관리권한</h2>
                <div class="admin-row-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="community-board-manager-grant-modal" data-overlay="#community-board-manager-grant-modal">관리권한 부여</button>
                </div>
            </div>
            <p class="admin-form-help">특정 회원에게 이 게시판에 한정된 관리 작업 권한을 부여합니다. 이 권한은 게시글 본문 수정 권한으로 확대되지 않습니다. 권한 부여와 회수는 위 게시판 기본 설정 저장과 별도로 즉시 반영됩니다.</p>
            <div class="table-wrapper">
                <table class="table">
                    <caption class="sr-only">게시판 관리권한 목록</caption>
                    <thead class="ui-table-head">
                        <tr>
                            <th>회원</th>
                            <th>권한</th>
                            <th>부여일</th>
                            <th class="text-end">작업</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($communityBoardManagers === []) { ?>
                            <tr>
                                <td colspan="4" class="admin-empty-state">부여된 관리권한이 없습니다.</td>
                            </tr>
                        <?php } ?>
                        <?php foreach ($communityBoardManagers as $manager) { ?>
                            <tr>
                                <td class="admin-table-break">
                                    <?php echo sr_e(sr_community_report_account_label(
                                        sr_community_author_display_name_from_row([
                                            'author_public_name_snapshot' => '',
                                            'author_display_name' => (string) ($manager['display_name'] ?? ''),
                                            'author_nickname' => (string) ($manager['nickname'] ?? ''),
                                            'author_account_status' => (string) ($manager['account_status'] ?? ''),
                                        ], $memberSettings ?? null),
                                        (int) ($manager['account_id'] ?? 0),
                                        (string) ($manager['account_status'] ?? '')
                                    )); ?>
                                </td>
                                <td><?php echo sr_e((string) ($communityBoardManagerPermissions[(string) ($manager['permission_key'] ?? '')] ?? (string) ($manager['permission_key'] ?? ''))); ?></td>
                                <td class="admin-table-nowrap"><?php echo sr_community_time_html((string) ($manager['created_at'] ?? '')); ?></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards/update')); ?>" class="admin-inline-form">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="board_manager_revoke">
                                            <input type="hidden" name="board_id" value="<?php echo sr_e((string) $formBoard['id']); ?>">
                                            <input type="hidden" name="manager_id" value="<?php echo sr_e((string) (int) ($manager['id'] ?? 0)); ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="관리권한 회수" title="회수"><?php echo sr_material_icon_html('delete'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div id="community-board-manager-grant-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="community-board-manager-grant-modal-label" aria-hidden="true" inert>
                <div class="modal-dialog modal-dialog-lg">
                    <form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards/update')); ?>" class="modal-content admin-form ui-form-theme" data-community-board-manager-grant-form>
                        <div class="modal-header">
                            <h3 id="community-board-manager-grant-modal-label" class="modal-title">관리권한 부여</h3>
                            <button type="button" class="modal-close" aria-label="닫기" data-overlay="#community-board-manager-grant-modal"><?php echo sr_material_icon_html('close'); ?></button>
                        </div>
                        <div class="modal-body">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="board_manager_grant">
                            <input type="hidden" name="board_id" value="<?php echo sr_e((string) $formBoard['id']); ?>">
                            <input type="hidden" name="account_id" value="" data-community-board-manager-account-id>
                            <div class="admin-form-row">
                                <label class="form-label" for="community_board_manager_account_identifier">회원 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                <div class="admin-form-field">
                                    <div class="admin-lookup-control">
                                        <input id="community_board_manager_account_identifier" type="text" value="" class="form-input" maxlength="120" readonly required data-community-board-manager-selected-member data-overlay-focus placeholder="회원을 검색해 선택하세요.">
                                        <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="community-board-manager-member-lookup-modal" data-overlay="#community-board-manager-member-lookup-modal" data-community-board-manager-member-lookup-open data-target="#community_board_manager_account_identifier">회원 검색</button>
                                    </div>
                                </div>
                            </div>
                            <div class="admin-form-row">
                                <span class="form-label">권한 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                <div class="admin-form-field">
                                    <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group">
                                        <?php $boardManagerPermissionIndex = 0; ?>
                                        <?php $boardManagerPermissionLastIndex = max(0, count($communityBoardManagerPermissions) - 1); ?>
                                        <?php foreach ($communityBoardManagerPermissions as $permissionKey => $permissionLabel) { ?>
                                            <?php $permissionInputId = 'community_board_manager_permission_' . (string) $permissionKey; ?>
                                            <?php $permissionGroupClass = $boardManagerPermissionIndex === 0 ? 'btn-group-start' : ($boardManagerPermissionIndex === $boardManagerPermissionLastIndex ? 'btn-group-end' : 'btn-group-middle'); ?>
                                            <span class="filtering-toggle-item">
                                                <input id="<?php echo sr_e($permissionInputId); ?>" type="checkbox" name="permission_keys[]" value="<?php echo sr_e((string) $permissionKey); ?>" class="form-choice-toggle-input sr-only">
                                                <label for="<?php echo sr_e($permissionInputId); ?>" class="btn btn-choice-light <?php echo sr_e($permissionGroupClass); ?>"><?php echo sr_admin_choice_label_html((string) $permissionLabel); ?></label>
                                            </span>
                                            <?php $boardManagerPermissionIndex++; ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer-note">
                            <p class="admin-form-help">이 작업은 게시판 기본 설정 저장과 별도로 관리권한만 추가합니다. 현재 수정 중인 게시판 입력값은 함께 저장되지 않습니다.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-solid-light modal-action" data-overlay="#community-board-manager-grant-modal">닫기</button>
                            <button type="submit" class="btn btn-solid-primary modal-action">권한 부여</button>
                        </div>
                    </form>
                </div>
            </div>
            <div id="community-board-manager-member-lookup-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="community-board-manager-member-lookup-modal-label" aria-hidden="true" inert data-admin-return-overlay="#community-board-manager-grant-modal">
                <div class="modal-dialog admin-lookup-dialog">
                    <div class="modal-content ui-form-theme">
                        <div class="modal-header">
                            <h3 id="community-board-manager-member-lookup-modal-label" class="modal-title">회원 검색</h3>
                            <button type="button" class="modal-close" aria-label="닫기" data-overlay="#community-board-manager-member-lookup-modal"><?php echo sr_material_icon_html('close'); ?></button>
                        </div>
                        <div class="modal-body">
                            <form class="admin-lookup-search-form" data-community-board-manager-member-search data-search-url="<?php echo sr_e($memberSearchUrl); ?>">
                                <select name="field" class="form-select" aria-label="회원 검색 조건" data-community-board-manager-member-field>
                                    <option value="all">전체</option>
                                    <option value="hash">해시 ID</option>
                                    <option value="email">이메일</option>
                                    <option value="login_id">로그인 ID</option>
                                    <option value="name">이름</option>
                                </select>
                                <input type="text" name="q" maxlength="120" class="form-input" placeholder="이메일, 로그인 ID, 이름" data-community-board-manager-member-keyword data-overlay-focus>
                                <button type="submit" class="btn btn-solid-primary" data-community-board-manager-member-search-button>검색</button>
                            </form>
                            <div class="admin-lookup-results" data-community-board-manager-member-results>
                                <p class="admin-empty-state admin-lookup-empty">검색어를 입력해 회원을 찾으세요.</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-solid-primary modal-action" data-overlay="#community-board-manager-grant-modal" data-community-board-manager-return>권한 부여로 돌아가기</button>
                            <button type="button" class="btn btn-solid-light modal-action" data-overlay="#community-board-manager-member-lookup-modal">닫기</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="community-board-section-categories" class="admin-card admin-list-card card admin-list-form" data-admin-section-anchor>
            <?php $boardCategories = is_array($formBoard['categories'] ?? null) ? $formBoard['categories'] : []; ?>
            <div class="card-header">
                <h2 class="card-title">게시판 카테고리</h2>
                <div class="admin-row-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="community-category-create-modal" data-overlay="#community-category-create-modal">카테고리 추가</button>
                </div>
            </div>
            <p class="admin-form-help">게시글 작성자가 선택할 게시판 안의 분류를 관리합니다. 카테고리 추가, 수정, 삭제는 위 게시판 기본 설정 저장과 별도로 즉시 반영됩니다.</p>
            <div class="table-wrapper">
                <table class="table">
                    <caption class="sr-only">게시판 카테고리 목록</caption>
                    <thead class="ui-table-head">
                        <tr>
                            <th>관리용 키</th>
                            <th>이름</th>
                            <th>상태</th>
                            <th>정렬</th>
                            <th class="text-end">작업</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($boardCategories === []) { ?>
                            <tr>
                                <td colspan="5" class="admin-empty-state">등록된 카테고리가 없습니다.</td>
                            </tr>
                        <?php } ?>
                        <?php foreach ($boardCategories as $category) { ?>
                            <?php $categoryModalId = 'community-category-edit-modal-' . (string) (int) ($category['id'] ?? 0); ?>
                            <tr>
                                <td><code><?php echo sr_e((string) $category['category_key']); ?></code></td>
                                <td class="admin-table-break">
                                    <strong><?php echo sr_e((string) $category['title']); ?></strong>
                                    <?php if (trim((string) ($category['description'] ?? '')) !== '') { ?>
                                        <span class="admin-summary-meta"><?php echo sr_e((string) $category['description']); ?></span>
                                    <?php } ?>
                                </td>
                                <td class="admin-table-nowrap"><span class="admin-status <?php echo (string) ($category['status'] ?? '') === 'enabled' ? 'is-normal' : 'is-blocked'; ?>"><?php echo sr_e(sr_admin_code_label((string) $category['status'], 'content_status')); ?></span></td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) $category['sort_order']); ?></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="카테고리 수정" title="수정" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($categoryModalId); ?>" data-overlay="#<?php echo sr_e($categoryModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards/update')); ?>" class="admin-inline-form">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="category_delete">
                                            <input type="hidden" name="board_id" value="<?php echo sr_e((string) $formBoard['id']); ?>">
                                            <input type="hidden" name="category_id" value="<?php echo sr_e((string) $category['id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="카테고리 삭제" title="삭제" onclick="return confirm('이 카테고리를 삭제할까요? 참조 중인 게시글이 있으면 삭제되지 않습니다.');"><?php echo sr_material_icon_html('delete'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div id="community-category-create-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="community-category-create-modal-label" aria-hidden="true" inert>
                <div class="modal-dialog modal-dialog-lg">
                    <form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards/update')); ?>" class="modal-content admin-form ui-form-theme">
                        <div class="modal-header">
                            <h3 id="community-category-create-modal-label" class="modal-title">카테고리 추가</h3>
                            <button type="button" class="modal-close" aria-label="닫기" data-overlay="#community-category-create-modal"><?php echo sr_material_icon_html('close'); ?></button>
                        </div>
                        <div class="modal-body">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="category_create">
                            <input type="hidden" name="board_id" value="<?php echo sr_e((string) $formBoard['id']); ?>">
                            <div class="admin-form-row">
                                <label class="form-label" for="community_category_key_new">카테고리 관리용 키 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                <div class="admin-form-field">
                                    <input id="community_category_key_new" type="text" name="category_key" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input data-admin-key-suggest-source="#community_category_title_new" data-admin-key-suggest-fallback="category_<?php echo sr_e((string) (count($categories ?? []) + 1)); ?>" data-overlay-focus class="form-input">
                                </div>
                            </div>
                            <div class="admin-form-row">
                                <label class="form-label" for="community_category_title_new">이름 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                <div class="admin-form-field">
                                    <input id="community_category_title_new" type="text" name="category_title" maxlength="120" required class="form-input">
                                </div>
                            </div>
                            <div class="admin-form-row">
                                <label class="form-label" for="community_category_description_new">설명</label>
                                <div class="admin-form-field">
                                    <textarea id="community_category_description_new" name="category_description" rows="2" cols="60" class="form-textarea"></textarea>
                                </div>
                            </div>
                            <div class="admin-form-row">
                                <label class="form-label" for="community_category_status_new">상태 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                <div class="admin-form-field">
                                    <select id="community_category_status_new" name="category_status" class="form-select" required>
                                        <option value="enabled">사용</option>
                                        <option value="disabled">미사용</option>
                                    </select>
                                </div>
                            </div>
                            <div class="admin-form-row">
                                <label class="form-label" for="community_category_sort_new">정렬 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                <div class="admin-form-field">
                                    <input id="community_category_sort_new" type="number" name="category_sort_order" min="0" max="1000000" value="0" required class="form-input">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer-note">
                            <p class="admin-form-help">이 작업은 게시판 기본 설정 저장과 별도로 새 카테고리만 추가합니다. 현재 수정 중인 게시판 입력값은 함께 저장되지 않습니다.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-solid-light modal-action" data-overlay="#community-category-create-modal">닫기</button>
                            <button type="submit" class="btn btn-solid-primary modal-action">카테고리 추가</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php foreach ($boardCategories as $category) { ?>
                <?php $categoryModalId = 'community-category-edit-modal-' . (string) (int) ($category['id'] ?? 0); ?>
                <div id="<?php echo sr_e($categoryModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($categoryModalId); ?>-label" aria-hidden="true" inert>
                    <div class="modal-dialog modal-dialog-lg">
                        <form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards/update')); ?>" class="modal-content admin-form ui-form-theme">
                            <div class="modal-header">
                                <h3 id="<?php echo sr_e($categoryModalId); ?>-label" class="modal-title">카테고리 수정</h3>
                                <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($categoryModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                            </div>
                            <div class="modal-body">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="category_update">
                                <input type="hidden" name="board_id" value="<?php echo sr_e((string) $formBoard['id']); ?>">
                                <input type="hidden" name="category_id" value="<?php echo sr_e((string) $category['id']); ?>">
                                <div class="admin-form-row">
                                    <span class="form-label">관리용 키</span>
                                    <div class="admin-form-field">
                                        <code><?php echo sr_e((string) $category['category_key']); ?></code>
                                    </div>
                                </div>
                                <div class="admin-form-row">
                                    <label class="form-label" for="<?php echo sr_e($categoryModalId); ?>-title">이름 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                    <div class="admin-form-field">
                                        <input id="<?php echo sr_e($categoryModalId); ?>-title" type="text" name="category_title" maxlength="120" value="<?php echo sr_e((string) $category['title']); ?>" required class="form-input" data-overlay-focus>
                                    </div>
                                </div>
                                <div class="admin-form-row">
                                    <label class="form-label" for="<?php echo sr_e($categoryModalId); ?>-description">설명</label>
                                    <div class="admin-form-field">
                                        <textarea id="<?php echo sr_e($categoryModalId); ?>-description" name="category_description" rows="2" cols="60" maxlength="2000" class="form-textarea"><?php echo sr_e((string) ($category['description'] ?? '')); ?></textarea>
                                    </div>
                                </div>
                                <div class="admin-form-row">
                                    <label class="form-label" for="<?php echo sr_e($categoryModalId); ?>-status">상태 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                    <div class="admin-form-field">
                                        <select id="<?php echo sr_e($categoryModalId); ?>-status" name="category_status" class="form-select" required>
                                            <option value="enabled"<?php echo (string) $category['status'] === 'enabled' ? ' selected' : ''; ?>>사용</option>
                                            <option value="disabled"<?php echo (string) $category['status'] === 'disabled' ? ' selected' : ''; ?>>미사용</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="admin-form-row">
                                    <label class="form-label" for="<?php echo sr_e($categoryModalId); ?>-sort">정렬 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                    <div class="admin-form-field">
                                        <input id="<?php echo sr_e($categoryModalId); ?>-sort" type="number" name="category_sort_order" min="0" max="1000000" value="<?php echo sr_e((string) $category['sort_order']); ?>" required class="form-input">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer-note">
                                <p class="admin-form-help">이 작업은 게시판 기본 설정 저장과 별도로 이 카테고리만 수정합니다. 현재 수정 중인 게시판 입력값은 함께 저장되지 않습니다.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($categoryModalId); ?>">닫기</button>
                                <button type="submit" class="btn btn-solid-primary modal-action">수정</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </section>
    <?php } ?>

    <?php echo sr_admin_help_modal_html($memberGroupAccessHelpModalId, sr_t('community::ui.member_group_access_help_title'), $memberGroupAccessHelpBodyHtml); ?>
    <?php foreach ($communityBoardHelp as $communityBoardHelpModal) { ?>
        <?php echo sr_admin_help_modal_html((string) $communityBoardHelpModal['id'], (string) $communityBoardHelpModal['title'], (string) $communityBoardHelpModal['body']); ?>
    <?php } ?>
<?php } ?>

<?php if (in_array($communityBoardsPage, ['new', 'edit'], true)) { ?>
<script>
(function () {
    function communityExtraFieldAllowedType(value) {
        return ['text', 'textarea', 'select', 'checkbox'].indexOf(value) !== -1 ? value : 'text';
    }

    function communityExtraFieldNormalize(raw) {
        var definitions = [];
        var seen = {};
        if (!Array.isArray(raw)) {
            return definitions;
        }
        raw.forEach(function (item) {
            if (!item || typeof item !== 'object' || definitions.length >= 20) {
                return;
            }
            var key = String(item.key || '').trim().toLowerCase();
            if (!/^[a-z][a-z0-9_]{1,59}$/.test(key) || seen[key]) {
                return;
            }
            var label = String(item.label || '').replace(/\s+/g, ' ').trim().slice(0, 120);
            if (label === '') {
                return;
            }
            var type = communityExtraFieldAllowedType(String(item.type || 'text'));
            var options = [];
            if (type === 'select') {
                if (Array.isArray(item.options)) {
                    item.options.forEach(function (option) {
                        var value = String(option || '').replace(/\s+/g, ' ').trim().slice(0, 120);
                        if (value !== '' && options.indexOf(value) === -1 && options.length < 50) {
                            options.push(value);
                        }
                    });
                }
                if (options.length === 0) {
                    return;
                }
            }
            definitions.push({
                key: key,
                label: label,
                type: type,
                required: !!item.required,
                options: options,
                visibility: String(item.visibility || 'public') === 'admin' ? 'admin' : 'public',
                show_on_view: Object.prototype.hasOwnProperty.call(item, 'show_on_view') ? !!item.show_on_view : true,
                show_in_admin: !!item.show_in_admin,
                privacy_purpose: String(item.privacy_purpose || '').trim(),
                export_policy: String(item.export_policy || 'include') === 'exclude' ? 'exclude' : 'include',
                cleanup_policy: String(item.cleanup_policy || 'anonymize') === 'retain' ? 'retain' : 'anonymize'
            });
            seen[key] = true;
        });
        return definitions;
    }

    function communityExtraFieldParse(textarea) {
        if (!textarea) {
            return [];
        }
        try {
            return communityExtraFieldNormalize(JSON.parse(textarea.value || '[]'));
        } catch (error) {
            return [];
        }
    }

    function communityExtraFieldTypeLabel(type) {
        var labels = {
            text: '텍스트',
            textarea: '긴 텍스트',
            select: '선택',
            checkbox: '체크박스'
        };
        return labels[type] || labels.text;
    }

    function communityExtraFieldPolicyLabel(field) {
        var exportLabel = field.export_policy === 'exclude' ? 'Export 제외' : 'Export 포함';
        var cleanupLabel = field.cleanup_policy === 'retain' ? '보관' : '익명화';
        return exportLabel + ' / ' + cleanupLabel;
    }

    function communityExtraFieldRandomKey(definitions) {
        var existing = {};
        communityExtraFieldNormalize(definitions).forEach(function (field) {
            existing[field.key] = true;
        });
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var randomPart = function () {
            var value = '';
            var bytes = null;
            if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
                bytes = new Uint8Array(10);
                window.crypto.getRandomValues(bytes);
            }
            for (var i = 0; i < 10; i++) {
                var index = bytes ? bytes[i] % chars.length : Math.floor(Math.random() * chars.length);
                value += chars.charAt(index);
            }
            return value;
        };
        for (var attempt = 0; attempt < 20; attempt++) {
            var key = 'field_' + randomPart();
            if (!existing[key]) {
                return key;
            }
        }

        return 'field_' + String(Date.now()).slice(-10);
    }

    function communityExtraFieldWrite(root, definitions) {
        var textarea = root ? root.querySelector('[data-community-extra-fields-json]') : null;
        if (!textarea) {
            return;
        }
        textarea.value = JSON.stringify(communityExtraFieldNormalize(definitions), null, 2);
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function communityExtraFieldRender(root) {
        var textarea = root ? root.querySelector('[data-community-extra-fields-json]') : null;
        var list = root ? root.querySelector('[data-community-extra-field-list]') : null;
        var empty = root ? root.querySelector('[data-community-extra-field-empty]') : null;
        var tableWrap = root ? root.querySelector('[data-community-extra-field-table-wrap]') : null;
        if (!textarea || !list) {
            return;
        }
        var definitions = communityExtraFieldParse(textarea);
        list.innerHTML = '';
        if (empty) {
            empty.hidden = definitions.length > 0;
        }
        if (tableWrap) {
            tableWrap.hidden = definitions.length === 0;
        }
        definitions.forEach(function (field, index) {
            var row = document.createElement('tr');

            var orderCell = document.createElement('td');
            orderCell.className = 'admin-table-actions-cell';
            var orderGroup = document.createElement('div');
            orderGroup.className = 'admin-row-actions';
            [
                ['up', '위로', 'arrow_upward'],
                ['down', '아래로', 'arrow_downward']
            ].forEach(function (action) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm btn-icon btn-solid-light';
                button.setAttribute('aria-label', action[1]);
                button.setAttribute('title', action[1]);
                button.setAttribute('data-community-extra-field-action', action[0]);
                button.setAttribute('data-community-extra-field-index-value', String(index));
                button.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">' + action[2] + '</span>';
                orderGroup.appendChild(button);
            });
            orderCell.appendChild(orderGroup);
            row.appendChild(orderCell);

            var labelCell = document.createElement('td');
            labelCell.textContent = field.label + (field.required ? ' (필수)' : '');
            row.appendChild(labelCell);

            var typeCell = document.createElement('td');
            typeCell.textContent = communityExtraFieldTypeLabel(field.type);
            row.appendChild(typeCell);

            var displayCell = document.createElement('td');
            var display = [];
            display.push(field.visibility === 'admin' ? '관리자 전용' : '공개');
            if (field.show_on_view) {
                display.push('본문');
            }
            if (field.show_in_admin) {
                display.push('관리자 목록');
            }
            displayCell.textContent = display.join(' / ');
            row.appendChild(displayCell);

            var privacyCell = document.createElement('td');
            privacyCell.textContent = (field.privacy_purpose || '목적 없음') + ' / ' + communityExtraFieldPolicyLabel(field);
            row.appendChild(privacyCell);

            var actionCell = document.createElement('td');
            actionCell.className = 'admin-table-actions-cell';
            var actionGroup = document.createElement('div');
            actionGroup.className = 'admin-row-actions';
            [
                ['edit', '수정', 'edit'],
                ['remove', '제거', 'delete']
            ].forEach(function (action) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = action[0] === 'remove' ? 'btn btn-sm btn-icon btn-outline-danger' : 'btn btn-sm btn-icon btn-solid-light';
                button.setAttribute('aria-label', action[1]);
                button.setAttribute('title', action[1]);
                button.setAttribute('data-community-extra-field-action', action[0]);
                button.setAttribute('data-community-extra-field-index-value', String(index));
                button.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">' + action[2] + '</span>';
                actionGroup.appendChild(button);
            });
            actionCell.appendChild(actionGroup);
            row.appendChild(actionCell);

            list.appendChild(row);
        });
    }

    function communityExtraFieldInput(modal, name) {
        return modal ? modal.querySelector('[data-community-extra-field-input="' + name + '"]') : null;
    }

    function communityExtraFieldSetModal(modal, field, index) {
        if (!modal) {
            return;
        }
        field = field || {
            key: '',
            label: '',
            type: 'text',
            required: false,
            options: [],
            visibility: 'public',
            show_on_view: true,
            show_in_admin: false,
            privacy_purpose: '',
            export_policy: 'include',
            cleanup_policy: 'anonymize'
        };
        var indexInput = modal.querySelector('[data-community-extra-field-index]');
        var title = modal.querySelector('[data-community-extra-field-modal-title]');
        if (indexInput) {
            indexInput.value = typeof index === 'number' && index >= 0 ? String(index) : '';
        }
        if (title) {
            title.textContent = typeof index === 'number' && index >= 0 ? '추가 입력 항목 수정' : '추가 입력 항목 추가';
        }
        communityExtraFieldInput(modal, 'key').value = field.key || '';
        communityExtraFieldInput(modal, 'label').value = field.label || '';
        communityExtraFieldInput(modal, 'type').value = communityExtraFieldAllowedType(field.type || 'text');
        communityExtraFieldInput(modal, 'options').value = Array.isArray(field.options) ? field.options.join("\n") : '';
        communityExtraFieldInput(modal, 'required').checked = !!field.required;
        communityExtraFieldInput(modal, 'visibility').value = field.visibility === 'admin' ? 'admin' : 'public';
        communityExtraFieldInput(modal, 'show_on_view').checked = Object.prototype.hasOwnProperty.call(field, 'show_on_view') ? !!field.show_on_view : true;
        communityExtraFieldInput(modal, 'show_in_admin').checked = !!field.show_in_admin;
        communityExtraFieldInput(modal, 'privacy_purpose').value = field.privacy_purpose || '';
        communityExtraFieldInput(modal, 'export_policy').value = field.export_policy === 'exclude' ? 'exclude' : 'include';
        communityExtraFieldInput(modal, 'cleanup_policy').value = field.cleanup_policy === 'retain' ? 'retain' : 'anonymize';
        communityExtraFieldSyncOptions(modal);
    }

    function communityExtraFieldSyncOptions(modal) {
        var type = communityExtraFieldInput(modal, 'type');
        var options = communityExtraFieldInput(modal, 'options');
        var requiredLabel = modal ? modal.querySelector('[data-community-extra-field-options-required]') : null;
        var isSelect = type && type.value === 'select';
        if (options) {
            options.required = !!isSelect;
            options.closest('.admin-form-row').hidden = !isSelect;
        }
        if (requiredLabel) {
            requiredLabel.hidden = !isSelect;
        }
    }

    function communityExtraFieldCollect(modal, definitions) {
        var keyInput = communityExtraFieldInput(modal, 'key');
        var labelInput = communityExtraFieldInput(modal, 'label');
        var typeInput = communityExtraFieldInput(modal, 'type');
        var optionsInput = communityExtraFieldInput(modal, 'options');
        var indexInput = modal ? modal.querySelector('[data-community-extra-field-index]') : null;
        var index = indexInput && indexInput.value !== '' ? parseInt(indexInput.value, 10) : -1;
        [keyInput, labelInput, typeInput, optionsInput].forEach(function (input) {
            if (input && typeof input.setCustomValidity === 'function') {
                input.setCustomValidity('');
            }
        });
        if (!keyInput || !labelInput || !typeInput || !optionsInput) {
            return null;
        }
        keyInput.value = keyInput.value.trim().toLowerCase();
        labelInput.value = labelInput.value.replace(/\s+/g, ' ').trim();
        var duplicate = definitions.some(function (field, fieldIndex) {
            return field.key === keyInput.value && fieldIndex !== index;
        });
        if (duplicate) {
            keyInput.setCustomValidity('이미 사용 중인 관리용 키입니다.');
        }
        var type = communityExtraFieldAllowedType(typeInput.value);
        var options = optionsInput.value.split(/\r?\n/).map(function (value) {
            return value.replace(/\s+/g, ' ').trim().slice(0, 120);
        }).filter(function (value, optionIndex, values) {
            return value !== '' && values.indexOf(value) === optionIndex;
        }).slice(0, 50);
        if (type === 'select' && options.length === 0) {
            optionsInput.setCustomValidity('선택지는 하나 이상 입력해 주세요.');
        }
        var controls = [keyInput, labelInput, typeInput, optionsInput];
        for (var i = 0; i < controls.length; i++) {
            if (controls[i] && typeof controls[i].checkValidity === 'function' && !controls[i].checkValidity()) {
                controls[i].reportValidity();
                return null;
            }
        }
        return {
            index: index,
            field: {
                key: keyInput.value,
                label: labelInput.value,
                type: type,
                required: !!communityExtraFieldInput(modal, 'required').checked,
                options: type === 'select' ? options : [],
                visibility: communityExtraFieldInput(modal, 'visibility').value === 'admin' ? 'admin' : 'public',
                show_on_view: !!communityExtraFieldInput(modal, 'show_on_view').checked,
                show_in_admin: !!communityExtraFieldInput(modal, 'show_in_admin').checked,
                privacy_purpose: communityExtraFieldInput(modal, 'privacy_purpose').value.trim(),
                export_policy: communityExtraFieldInput(modal, 'export_policy').value === 'exclude' ? 'exclude' : 'include',
                cleanup_policy: communityExtraFieldInput(modal, 'cleanup_policy').value === 'retain' ? 'retain' : 'anonymize'
            }
        };
    }

    function communityExtraFieldInit() {
        var root = document.querySelector('[data-community-extra-fields-builder]');
        var modal = document.querySelector('[data-community-extra-field-modal]');
        var textarea = root ? root.querySelector('[data-community-extra-fields-json]') : null;
        if (!root || !modal || !textarea) {
            return;
        }
        communityExtraFieldRender(root);
        textarea.addEventListener('change', function () {
            communityExtraFieldRender(root);
        });
        var type = communityExtraFieldInput(modal, 'type');
        if (type) {
            type.addEventListener('change', function () {
                communityExtraFieldSyncOptions(modal);
            });
        }
    }

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

    function syncPolicy(kind) {
        var policy = document.querySelector('[data-community-policy="' + kind + '"]');
        var group = document.getElementById('community_admin_boards_' + kind + '_group_keys');
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
        var readGroup = document.getElementById('community_admin_boards_read_group_keys');
        var sourceGroup = document.getElementById('community_admin_boards_' + kind + '_group_keys');
        if (!readGroup || !sourceGroup || kind === 'read') {
            return;
        }

        Object.keys(selectedGroupValues(sourceGroup)).forEach(function (value) {
            addGroupValue(readGroup, value);
        });
    }

    function syncWritableGroupsFromRead() {
        var readGroup = document.getElementById('community_admin_boards_read_group_keys');
        if (!readGroup) {
            return;
        }

        var readable = selectedGroupValues(readGroup);
        ['write', 'comment'].forEach(function (kind) {
            var group = document.getElementById('community_admin_boards_' + kind + '_group_keys');
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

    function boardManagerMemberSummary(item) {
        var parts = [];
        if (item.account_public_hash) {
            parts.push(item.account_public_hash);
        }
        if (item.email) {
            parts.push(item.email);
        }
        if (item.display_name) {
            parts.push(item.display_name);
        }
        return parts.join(' · ');
    }

    function boardManagerMemberResultsRoot(searchRoot) {
        var modal = searchRoot && searchRoot.closest ? searchRoot.closest('.modal-overlay') : null;
        return modal ? modal.querySelector('[data-community-board-manager-member-results]') : null;
    }

    function renderBoardManagerMemberResults(searchRoot, items) {
        var results = boardManagerMemberResultsRoot(searchRoot);
        if (!results) {
            return;
        }

        results.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'admin-empty-state admin-lookup-empty';
            empty.textContent = '검색 결과가 없습니다.';
            results.appendChild(empty);
            return;
        }

        var list = document.createElement('div');
        list.className = 'admin-lookup-results-list';
        items.forEach(function (item) {
            var summary = boardManagerMemberSummary(item) || (item.account_public_hash || ('#' + item.id));
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'admin-lookup-result-button';
            button.setAttribute('data-community-board-manager-member-pick', '');
            button.setAttribute('data-account-id', item.id || '');
            button.setAttribute('data-account-label', summary);
            button.setAttribute('data-account-status', item.status || '');

            var title = document.createElement('strong');
            title.textContent = item.display_name || item.account_public_hash || ('#' + item.id);
            button.appendChild(title);

            var meta = document.createElement('div');
            meta.className = 'admin-lookup-result-meta';
            [item.email || '', item.status || '', item.account_public_hash || ''].forEach(function (value) {
                if (value === '') {
                    return;
                }
                var span = document.createElement('span');
                span.textContent = value;
                meta.appendChild(span);
            });
            button.appendChild(meta);

            list.appendChild(button);
        });
        results.appendChild(list);
    }

    function runBoardManagerMemberSearch(searchRoot) {
        var url = searchRoot ? (searchRoot.getAttribute('data-search-url') || '') : '';
        var field = searchRoot ? searchRoot.querySelector('[data-community-board-manager-member-field]') : null;
        var keyword = searchRoot ? searchRoot.querySelector('[data-community-board-manager-member-keyword]') : null;
        var results = boardManagerMemberResultsRoot(searchRoot);
        if (url === '' || !field || !keyword || !results) {
            return;
        }

        results.innerHTML = '<p class="admin-empty-state admin-lookup-empty">검색 중입니다.</p>';
        var params = new URLSearchParams();
        params.set('field', field.value || 'all');
        params.set('q', keyword.value || '');
        params.set('limit', '10');

        fetch(url + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        }).then(function (response) {
            return response.ok ? response.json() : {items: []};
        }).then(function (payload) {
            renderBoardManagerMemberResults(searchRoot, payload.items || []);
        }).catch(function () {
            results.innerHTML = '<p class="admin-empty-state admin-lookup-empty">회원 검색 중 오류가 발생했습니다.</p>';
        });
    }

    function setBoardManagerMember(source) {
        var form = document.querySelector('[data-community-board-manager-grant-form]');
        if (!form || !source) {
            return;
        }

        var accountId = form.querySelector('[data-community-board-manager-account-id]');
        var selectedMember = form.querySelector('[data-community-board-manager-selected-member]');
        if (accountId) {
            accountId.value = source.getAttribute('data-account-id') || '';
        }
        if (selectedMember) {
            selectedMember.value = source.getAttribute('data-account-label') || '';
            selectedMember.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function syncCategoryPolicy() {
        var categoryEnabled = document.querySelector('[data-community-category-enabled]');
        var categoryRequired = document.querySelector('[data-community-category-required]');
        if (!categoryEnabled || !categoryRequired) {
            return;
        }
        if (categoryRequired.checked) {
            categoryEnabled.checked = true;
        } else if (!categoryEnabled.checked) {
            categoryRequired.checked = false;
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
        form.addEventListener('submit', function () {
            syncWritableGroupsFromRead();
            syncCategoryPolicy();
        });
    }
    var categoryEnabled = document.querySelector('[data-community-category-enabled]');
    var categoryRequired = document.querySelector('[data-community-category-required]');
    if (categoryEnabled) {
        categoryEnabled.addEventListener('change', syncCategoryPolicy);
    }
    if (categoryRequired) {
        categoryRequired.addEventListener('change', syncCategoryPolicy);
    }
    syncCategoryPolicy();
    syncWritableGroupsFromRead();
    syncPolicy('read');
    syncBoardGroupRequired();
    var groupSelect = document.querySelector('[data-community-board-group-select]');
    if (groupSelect) {
        groupSelect.addEventListener('change', function () {
            syncBoardGroupRequired();
        });
    }
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
    communityExtraFieldInit();

    document.addEventListener('submit', function (event) {
        var searchForm = event.target.closest && event.target.closest('[data-community-board-manager-member-search]');
        if (!searchForm) {
            return;
        }

        event.preventDefault();
        runBoardManagerMemberSearch(searchForm);
    });

    document.addEventListener('click', function (event) {
        var extraFieldAdd = event.target.closest && event.target.closest('[data-community-extra-field-add]');
        if (extraFieldAdd) {
            var extraFieldModal = document.querySelector('[data-community-extra-field-modal]');
            var extraFieldRootForAdd = document.querySelector('[data-community-extra-fields-builder]');
            var extraFieldTextareaForAdd = extraFieldRootForAdd ? extraFieldRootForAdd.querySelector('[data-community-extra-fields-json]') : null;
            var extraFieldDefinitionsForAdd = communityExtraFieldParse(extraFieldTextareaForAdd);
            communityExtraFieldSetModal(extraFieldModal, {
                key: communityExtraFieldRandomKey(extraFieldDefinitionsForAdd),
                label: '',
                type: 'text',
                required: false,
                options: [],
                visibility: 'public',
                show_on_view: true,
                show_in_admin: false,
                privacy_purpose: '',
                export_policy: 'include',
                cleanup_policy: 'anonymize'
            }, -1);
            return;
        }

        var extraFieldAction = event.target.closest && event.target.closest('[data-community-extra-field-action]');
        if (extraFieldAction) {
            event.preventDefault();
            var extraFieldRoot = document.querySelector('[data-community-extra-fields-builder]');
            var extraFieldTextarea = extraFieldRoot ? extraFieldRoot.querySelector('[data-community-extra-fields-json]') : null;
            var extraFieldDefinitions = communityExtraFieldParse(extraFieldTextarea);
            var extraFieldIndex = parseInt(extraFieldAction.getAttribute('data-community-extra-field-index-value') || '-1', 10);
            var extraFieldActionName = extraFieldAction.getAttribute('data-community-extra-field-action') || '';
            if (extraFieldIndex < 0 || extraFieldIndex >= extraFieldDefinitions.length) {
                return;
            }
            if (extraFieldActionName === 'edit') {
                var openButton = document.querySelector('[data-community-extra-field-add]');
                if (openButton) {
                    openButton.click();
                }
                communityExtraFieldSetModal(document.querySelector('[data-community-extra-field-modal]'), extraFieldDefinitions[extraFieldIndex], extraFieldIndex);
                return;
            }
            if (extraFieldActionName === 'up' && extraFieldIndex > 0) {
                var previous = extraFieldDefinitions[extraFieldIndex - 1];
                extraFieldDefinitions[extraFieldIndex - 1] = extraFieldDefinitions[extraFieldIndex];
                extraFieldDefinitions[extraFieldIndex] = previous;
            } else if (extraFieldActionName === 'down' && extraFieldIndex < extraFieldDefinitions.length - 1) {
                var next = extraFieldDefinitions[extraFieldIndex + 1];
                extraFieldDefinitions[extraFieldIndex + 1] = extraFieldDefinitions[extraFieldIndex];
                extraFieldDefinitions[extraFieldIndex] = next;
            } else if (extraFieldActionName === 'remove') {
                extraFieldDefinitions.splice(extraFieldIndex, 1);
            }
            communityExtraFieldWrite(extraFieldRoot, extraFieldDefinitions);
            communityExtraFieldRender(extraFieldRoot);
            return;
        }

        var extraFieldSave = event.target.closest && event.target.closest('[data-community-extra-field-save]');
        if (extraFieldSave) {
            event.preventDefault();
            var saveRoot = document.querySelector('[data-community-extra-fields-builder]');
            var saveTextarea = saveRoot ? saveRoot.querySelector('[data-community-extra-fields-json]') : null;
            var saveModal = document.querySelector('[data-community-extra-field-modal]');
            var saveDefinitions = communityExtraFieldParse(saveTextarea);
            var collected = communityExtraFieldCollect(saveModal, saveDefinitions);
            if (!collected) {
                return;
            }
            if (collected.index >= 0 && collected.index < saveDefinitions.length) {
                saveDefinitions[collected.index] = collected.field;
            } else {
                saveDefinitions.push(collected.field);
            }
            communityExtraFieldWrite(saveRoot, saveDefinitions);
            communityExtraFieldRender(saveRoot);
            var closeButton = saveModal ? saveModal.querySelector('.modal-close') : null;
            if (closeButton) {
                closeButton.click();
            }
            return;
        }

        var searchButton = event.target.closest && event.target.closest('[data-community-board-manager-member-search-button]');
        if (searchButton) {
            event.preventDefault();
            var searchRoot = searchButton.closest('[data-community-board-manager-member-search]');
            if (searchRoot) {
                runBoardManagerMemberSearch(searchRoot);
            }
            return;
        }

        var pickButton = event.target.closest && event.target.closest('[data-community-board-manager-member-pick]');
        if (pickButton) {
            setBoardManagerMember(pickButton);
            var returnButton = document.querySelector('[data-community-board-manager-return]');
            if (returnButton) {
                returnButton.click();
            }
            return;
        }

        var lookupOpen = event.target.closest && event.target.closest('[data-community-board-manager-member-lookup-open]');
        if (lookupOpen) {
            var target = document.querySelector(lookupOpen.getAttribute('data-target') || '');
            var modal = document.querySelector(lookupOpen.getAttribute('data-overlay') || '');
            if (target && modal) {
                var query = modal.querySelector('[data-community-board-manager-member-keyword]');
                if (query && query.value === '') {
                    query.value = target.value;
                }
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        var keyword = event.target.closest && event.target.closest('[data-community-board-manager-member-keyword]');
        if (!keyword || event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        var searchRoot = keyword.closest('[data-community-board-manager-member-search]');
        if (searchRoot) {
            runBoardManagerMemberSearch(searchRoot);
        }
    });
})();
</script>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
