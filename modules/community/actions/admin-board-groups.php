<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';
if (sr_module_enabled($pdo, 'banner') && is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (sr_module_enabled($pdo, 'popup_layer') && is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
if (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/board-groups', 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$communityBoardGroupsPage = isset($communityBoardGroupsPage) ? (string) $communityBoardGroupsPage : 'list';
if (!in_array($communityBoardGroupsPage, ['list', 'new', 'edit'], true)) {
    $communityBoardGroupsPage = 'list';
}
if (sr_request_method() === 'GET' && in_array($communityBoardGroupsPage, ['new', 'edit'], true)) {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/board-groups', 'edit');
}
$allowedGroupStatuses = sr_community_board_group_statuses();
$allowedReadPolicies = sr_community_policy_values('read');
$allowedWritePolicies = sr_community_policy_values('write');
$allowedCommentPolicies = sr_community_policy_values('comment');
$settings = sr_community_settings($pdo);
$maxLevel = sr_community_max_level_value($settings);
$editorOptions = sr_editor_options($pdo);
$reactionPresetOptions = sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$assetModuleOptions = sr_community_asset_module_options($pdo);
$assetPolicySets = sr_community_asset_policy_sets($pdo);
$publicBanners = function_exists('sr_banner_public_banners') && sr_module_enabled($pdo, 'banner')
    ? sr_banner_public_banners($pdo)
    : [];
$publicBannerIds = [];
foreach ($publicBanners as $publicBanner) {
    $publicBannerIds[(int) $publicBanner['id']] = true;
}
$publicPopupLayers = function_exists('sr_popup_layer_public_layers') && sr_module_enabled($pdo, 'popup_layer')
    ? sr_popup_layer_public_layers($pdo)
    : [];
$publicPopupLayerIds = [];
foreach ($publicPopupLayers as $publicPopupLayer) {
    $publicPopupLayerIds[(int) $publicPopupLayer['id']] = true;
}
$publicBannerSettingLabels = sr_community_public_banner_setting_labels();
$publicPopupLayerSettingLabels = sr_community_public_popup_layer_setting_labels();
$publicDisplaySettingLabels = sr_community_public_display_setting_labels();
$boardGroupListFilters = [
    'status' => sr_admin_get_allowed_array('status', $allowedGroupStatuses, 30),
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
if (!in_array($boardGroupListFilters['field'], ['all', 'key', 'title'], true)) {
    $boardGroupListFilters['field'] = 'all';
}
$memberGroups = sr_member_groups($pdo);
$enabledMemberGroups = [];
$enabledMemberGroupKeys = [];
foreach ($memberGroups as $memberGroup) {
    if ((string) ($memberGroup['status'] ?? '') !== 'enabled') {
        continue;
    }

    $enabledMemberGroups[] = $memberGroup;
    $enabledMemberGroupKeys[] = (string) $memberGroup['group_key'];
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/board-groups', $intent === 'delete_group' ? 'delete' : 'edit');

    if ($intent === 'delete_group') {
        $groupIdValue = sr_post_string('group_id', 20);
        $groupId = preg_match('/\A[1-9][0-9]*\z/', $groupIdValue) === 1 ? (int) $groupIdValue : 0;
        $deleteResult = sr_community_delete_board_group($pdo, $groupId);
        $errors = array_merge($errors, is_array($deleteResult['errors'] ?? null) ? $deleteResult['errors'] : []);
        $group = is_array($deleteResult['group'] ?? null) ? $deleteResult['group'] : null;
        if ($errors === [] && is_array($group)) {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.board_group.deleted',
                'target_type' => 'community_board_group',
                'target_id' => (string) $groupId,
                'result' => 'success',
                'message' => 'Community board group deleted.',
                'metadata' => [
                    'group_key' => (string) ($group['group_key'] ?? ''),
                    'title' => (string) ($group['title'] ?? ''),
                    'deleted_settings' => (int) ($deleteResult['deleted_settings'] ?? 0),
                    'detached_boards' => (int) ($deleteResult['detached_boards'] ?? 0),
                ],
            ]);
            $notice = '게시판 그룹을 삭제했습니다.';
        }

        sr_admin_flash_result(sr_admin_action_result($errors, $notice));
        sr_redirect('/admin/community/board-groups');
    } elseif (in_array($intent, ['create_group', 'update_group'], true)) {
        $groupId = 0;
        if ($intent === 'update_group') {
            $groupIdValue = sr_post_string('group_id', 20);
            $groupId = preg_match('/\A[1-9][0-9]*\z/', $groupIdValue) === 1 ? (int) $groupIdValue : 0;
            if (!is_array(sr_community_board_group_by_id($pdo, $groupId))) {
                $errors[] = sr_t('community::action.error.board_group_not_found');
            }
        }

        $groupKey = strtolower(trim(sr_post_string('group_key', 60)));
        $title = sr_post_string('title', 120);
        $description = sr_post_string_without_truncation('description', 2000);
        $status = sr_post_string('status', 30);
        $sortOrder = sr_admin_post_int_in_range('sort_order', 0, 1000000);
        $existingGroup = $intent === 'update_group' && $groupId > 0 ? sr_community_board_group_by_id($pdo, $groupId) : null;
        $saveGroupKey = $intent === 'update_group' && is_array($existingGroup) ? (string) ($existingGroup['group_key'] ?? '') : $groupKey;

        if ($intent === 'create_group' && !sr_community_board_group_key_is_valid($saveGroupKey)) {
            $errors[] = sr_t('community::action.admin.board_group_key_invalid');
        }
        if ($title === '') {
            $errors[] = sr_t('community::action.admin.board_group_title_required');
        }
        if ($description === null) {
            $errors[] = sr_t('community::action.admin.board_group_description_too_long');
            $description = '';
        }
        if (!in_array($status, $allowedGroupStatuses, true)) {
            $errors[] = sr_t('community::action.admin.board_group_status_invalid');
        }
        if ($sortOrder === null) {
            $errors[] = sr_t('community::action.admin.board_group_sort_order_invalid');
            $sortOrder = 0;
        }
        if ($errors === [] && $intent === 'create_group' && sr_community_board_group_by_key($pdo, $saveGroupKey) !== null) {
            $errors[] = sr_t('community::action.admin.board_group_key_duplicate');
        }

        if ($errors === []) {
            if ($intent === 'create_group') {
                $groupId = sr_community_create_board_group($pdo, [
                    'group_key' => $saveGroupKey,
                    'title' => $title,
                    'description' => (string) $description,
                    'status' => $status,
                    'sort_order' => (int) $sortOrder,
                ]);
                $eventType = 'community.board_group.created';
                $notice = sr_t('community::action.admin.board_group_created');
            } else {
                sr_community_update_board_group($pdo, $groupId, [
                    'title' => $title,
                    'description' => (string) $description,
                    'status' => $status,
                    'sort_order' => (int) $sortOrder,
                ]);
                $eventType = 'community.board_group.updated';
                $notice = sr_t('community::action.admin.board_group_updated');
            }

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => $eventType,
                'target_type' => 'community_board_group',
                'target_id' => (string) $groupId,
                'result' => 'success',
                'message' => 'Community board group saved.',
                'metadata' => [
                    'group_key' => $saveGroupKey,
                    'status' => $status,
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect($intent === 'create_group' ? '/admin/community/board-groups' : '/admin/community/board-groups/edit?id=' . (string) $groupId);
        }

        sr_admin_flash_result(sr_admin_action_result($errors, ''));
        sr_redirect($intent === 'update_group' && $groupId > 0 ? '/admin/community/board-groups/edit?id=' . (string) $groupId : '/admin/community/board-groups/new');

    } else {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }
}

$boardGroupStatusCounts = sr_community_admin_board_group_status_counts($pdo, $allowedGroupStatuses);
$boardGroupSort = sr_admin_sort_from_request(sr_community_admin_board_group_sort_options(), sr_community_admin_board_group_default_sort());
$boardGroupPagination = sr_admin_pagination_from_total(
    $pdo,
    $communityBoardGroupsPage === 'list' ? sr_community_admin_board_group_count($pdo, $boardGroupListFilters) : 0
);
$boardGroups = [];
if ($communityBoardGroupsPage === 'list') {
    $boardGroups = sr_community_admin_board_groups($pdo, $boardGroupListFilters, (int) $boardGroupPagination['per_page'], sr_admin_pagination_offset($boardGroupPagination), $boardGroupSort);
}

$boardGroupSettings = [];
foreach ($boardGroups as $boardGroup) {
    $boardGroupSettings[(int) $boardGroup['id']] = sr_community_board_group_settings($pdo, (int) $boardGroup['id']);
}

$editBoardGroup = null;
if ($communityBoardGroupsPage === 'edit') {
    $editGroupIdValue = isset($_GET['edit_id']) ? (string) $_GET['edit_id'] : '';
    $editGroupId = preg_match('/\A[1-9][0-9]*\z/', $editGroupIdValue) === 1 ? (int) $editGroupIdValue : 0;
    $editBoardGroup = sr_community_board_group_by_id($pdo, $editGroupId);

    if (!is_array($editBoardGroup)) {
        sr_render_error(404, sr_t('community::action.error.board_group_not_found'));
    }

    $boardGroupSettings[(int) $editBoardGroup['id']] = sr_community_board_group_settings($pdo, (int) $editBoardGroup['id']);
}

include SR_ROOT . '/modules/community/views/admin-board-groups.php';
