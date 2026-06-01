<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/boards', 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$communityBoardsPage = isset($communityBoardsPage) ? (string) $communityBoardsPage : 'list';
if (!in_array($communityBoardsPage, ['list', 'new', 'edit'], true)) {
    $communityBoardsPage = 'list';
}
$allowedStatuses = sr_community_board_statuses();
$allowedReadPolicies = sr_community_policy_values('read');
$allowedWritePolicies = sr_community_policy_values('write');
$allowedCommentPolicies = sr_community_policy_values('comment');
$communitySkinOptions = sr_community_skin_options();
$settings = sr_community_settings($pdo);
$editorOptions = sr_editor_options($pdo);
$assetModuleOptions = sr_community_asset_module_options($pdo);
$assetPolicySets = sr_community_asset_policy_sets($pdo);
$maxLevel = sr_community_max_level_value($settings);
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
$publicDisplaySettingLabels = $publicBannerSettingLabels + $publicPopupLayerSettingLabels;
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

$boardGroups = sr_community_board_groups($pdo);
$boardGroupIds = [];
$boardGroupSettings = [];
foreach ($boardGroups as $boardGroup) {
    $boardGroupId = (int) $boardGroup['id'];
    $boardGroupIds[$boardGroupId] = true;
    $boardGroupSettings[$boardGroupId] = sr_community_board_group_settings($pdo, $boardGroupId);
}
$boardGroupFilterValue = sr_get_string('group_id', 20);
$boardGroupFilterId = preg_match('/\A[1-9][0-9]*\z/', $boardGroupFilterValue) === 1 ? (int) $boardGroupFilterValue : 0;
if ($boardGroupFilterId > 0 && !isset($boardGroupIds[$boardGroupFilterId])) {
    $boardGroupFilterId = 0;
}
$boardListFilters = [
    'status' => sr_get_string('status', 30),
    'group_id' => $boardGroupFilterId,
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
$newBoardGroupId = $communityBoardsPage === 'new' ? $boardGroupFilterId : 0;
if ($boardListFilters['status'] !== '' && !in_array($boardListFilters['status'], $allowedStatuses, true)) {
    $boardListFilters['status'] = '';
}
if (!in_array($boardListFilters['field'], ['all', 'key', 'title', 'group'], true)) {
    $boardListFilters['field'] = 'all';
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/boards', 'edit');

    $intent = sr_post_string('intent', 40);

    if (in_array($intent, ['category_create', 'category_update', 'category_delete'], true)) {
        $boardIdValue = sr_post_string('board_id', 20);
        $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
        $board = sr_community_board_by_id($pdo, $boardId);
        if (!is_array($board)) {
            $errors[] = sr_t('community::action.error.board_not_found');
        }

        $categoryId = 0;
        $category = null;
        if (in_array($intent, ['category_update', 'category_delete'], true)) {
            $categoryIdValue = sr_post_string('category_id', 20);
            $categoryId = preg_match('/\A[1-9][0-9]*\z/', $categoryIdValue) === 1 ? (int) $categoryIdValue : 0;
            $category = sr_community_category_by_id($pdo, $categoryId);
            if (!is_array($category) || (int) ($category['board_id'] ?? 0) !== $boardId) {
                $errors[] = '카테고리를 찾을 수 없습니다.';
            }
        }

        $categoryKey = strtolower(trim(sr_post_string('category_key', 60)));
        $categoryTitle = sr_post_string('category_title', 120);
        $categoryDescription = sr_post_string_without_truncation('category_description', 2000);
        $categoryStatus = sr_post_string('category_status', 30);
        $categorySortOrder = sr_admin_post_int_in_range('category_sort_order', 0, 1000000);

        if ($intent === 'category_create' && !sr_community_category_key_is_valid($categoryKey)) {
            $errors[] = '카테고리 key는 소문자, 숫자, _만 사용할 수 있으며 예약어는 사용할 수 없습니다.';
        }
        if ($intent !== 'category_delete' && $categoryTitle === '') {
            $errors[] = '카테고리 이름을 입력해 주세요.';
        }
        if ($intent !== 'category_delete' && !in_array($categoryStatus, sr_community_category_statuses(), true)) {
            $errors[] = '카테고리 상태 값이 올바르지 않습니다.';
        }
        if ($intent !== 'category_delete' && $categorySortOrder === null) {
            $errors[] = '카테고리 정렬값이 올바르지 않습니다.';
            $categorySortOrder = 0;
        }
        if ($intent !== 'category_delete' && !is_string($categoryDescription)) {
            $errors[] = '카테고리 설명이 너무 깁니다.';
            $categoryDescription = '';
        }
        if ($intent === 'category_create' && $errors === [] && sr_community_category_by_key($pdo, $boardId, $categoryKey) !== null) {
            $errors[] = '같은 게시판에 이미 같은 key의 카테고리가 있습니다.';
        }

        if ($errors === [] && is_array($board)) {
            if ($intent === 'category_create') {
                $createdCategoryId = sr_community_create_category($pdo, $boardId, [
                    'category_key' => $categoryKey,
                    'title' => $categoryTitle,
                    'description' => (string) $categoryDescription,
                    'status' => $categoryStatus,
                    'sort_order' => (int) $categorySortOrder,
                ]);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.category.created',
                    'target_type' => 'community_category',
                    'target_id' => (string) $createdCategoryId,
                    'result' => 'success',
                    'message' => 'Community category created.',
                    'metadata' => [
                        'board_key' => (string) $board['board_key'],
                        'category_key' => $categoryKey,
                        'status' => $categoryStatus,
                    ],
                ]);
                $notice = '카테고리를 추가했습니다.';
            } elseif ($intent === 'category_update' && is_array($category)) {
                sr_community_update_category($pdo, $categoryId, [
                    'title' => $categoryTitle,
                    'description' => (string) $categoryDescription,
                    'status' => $categoryStatus,
                    'sort_order' => (int) $categorySortOrder,
                ]);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.category.updated',
                    'target_type' => 'community_category',
                    'target_id' => (string) $categoryId,
                    'result' => 'success',
                    'message' => 'Community category updated.',
                    'metadata' => [
                        'board_key' => (string) $board['board_key'],
                        'category_key' => (string) $category['category_key'],
                        'before_status' => (string) $category['status'],
                        'after_status' => $categoryStatus,
                    ],
                ]);
                $notice = '카테고리를 수정했습니다.';
            } elseif ($intent === 'category_delete' && is_array($category)) {
                if (!sr_community_delete_category($pdo, $categoryId)) {
                    $errors[] = '참조 중인 게시글이 있는 카테고리는 삭제할 수 없습니다. 비활성으로 전환해 주세요.';
                } else {
                    sr_audit_log($pdo, [
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'admin',
                        'event_type' => 'community.category.deleted',
                        'target_type' => 'community_category',
                        'target_id' => (string) $categoryId,
                        'result' => 'success',
                        'message' => 'Community category deleted.',
                        'metadata' => [
                            'board_key' => (string) $board['board_key'],
                            'category_key' => (string) $category['category_key'],
                        ],
                    ]);
                    $notice = '카테고리를 삭제했습니다.';
                }
            }
        }

        if ($errors === []) {
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/community/boards/edit?id=' . (string) $boardId);
        }
    } elseif (in_array($intent, ['create', 'update'], true)) {
        $boardKey = strtolower(trim(sr_post_string('board_key', 60)));
        $title = sr_post_string('title', 120);
        $description = sr_post_string_without_truncation('description', 2000);
        $status = sr_post_string('status', 30);
        $readPolicy = sr_post_string('read_policy', 30);
        $writePolicy = sr_post_string('write_policy', 30);
        $commentPolicy = sr_post_string('comment_policy', 30);
        $skinKey = sr_post_string('skin_key', 40);
        $postEditorInput = sr_post_string('post_editor', 30);
        $postEditor = sr_community_post_editor_key($postEditorInput);
        $sortOrder = sr_admin_post_int_in_range('sort_order', 0, 1000000);
        $attachmentMaxBytes = sr_admin_post_int_in_range('attachment_max_bytes', 1024, 10485760);
        $attachmentMaxCount = sr_admin_post_int_in_range('attachment_max_count', 0, 10);
        $publicDisplaySettingValues = [];
        foreach ($publicDisplaySettingLabels as $displaySettingKey => $displaySettingLabel) {
            $publicDisplaySettingValues[$displaySettingKey] = sr_admin_post_int_in_range($displaySettingKey, 0, 999999999);
        }
        $imageUploadsEnabled = ($_POST['image_uploads_enabled'] ?? '') === '1';
        $fileUploadsEnabled = ($_POST['file_uploads_enabled'] ?? '') === '1';
        $fileAttachmentMaxBytes = sr_admin_post_int_in_range('file_attachment_max_bytes', 1024, 20971520);
        $fileAttachmentMaxCount = sr_admin_post_int_in_range('file_attachment_max_count', 0, 5);
        $fileAllowedExtensionsInput = sr_post_string_without_truncation('file_allowed_extensions', 1000);
        $fileAllowedExtensions = is_string($fileAllowedExtensionsInput) ? sr_community_file_extensions_from_input($fileAllowedExtensionsInput) : [];
        $readMinLevel = sr_admin_post_int_in_range('read_min_level', 0, $maxLevel);
        $writeMinLevel = sr_admin_post_int_in_range('write_min_level', 0, $maxLevel);
        $commentMinLevel = sr_admin_post_int_in_range('comment_min_level', 0, $maxLevel);
        $categoryRequired = ($_POST['category_required'] ?? '') === '1';
        $levelPostScore = sr_admin_post_int_in_range('level_post_score', 0, 10000);
        $levelCommentScore = sr_admin_post_int_in_range('level_comment_score', 0, 10000);
        $boardGroupId = sr_admin_post_int_in_range('board_group_id', 0, 999999999);
        $boardGroupId = is_int($boardGroupId) ? $boardGroupId : 0;
        $readGroupKeysInput = $_POST['read_group_keys'] ?? [];
        $writeGroupKeysInput = $_POST['write_group_keys'] ?? [];
        $commentGroupKeysInput = $_POST['comment_group_keys'] ?? [];
        $readGroupKeys = sr_community_board_group_keys_from_input_value($readGroupKeysInput);
        $writeGroupKeys = sr_community_board_group_keys_from_input_value($writeGroupKeysInput);
        $commentGroupKeys = sr_community_board_group_keys_from_input_value($commentGroupKeysInput);
        $assetSettings = [];
        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $policySetIds = sr_community_asset_policy_set_ids_from_value($_POST[$assetPrefix . '_policy_set_ids'] ?? []);
            $assetSettings[$assetPrefix . '_enabled'] = ($_POST[$assetPrefix . '_enabled'] ?? '') === '1';
            $assetSettings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
                ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($_POST[$assetPrefix . '_asset_module'] ?? '', true), true)
                : sr_community_asset_module_key_or_empty(sr_post_string($assetPrefix . '_asset_module', 20));
            $assetSettings[$assetPrefix . '_amount'] = sr_admin_post_int_in_range($assetPrefix . '_amount', 0, 999999999);
            $assetSettings[$assetPrefix . '_group_policies_json'] = sr_community_asset_policy_set_selection_json_from_ids($policySetIds);
            $assetSettings[$assetPrefix . '_policy_set_id'] = sr_community_asset_policy_set_first_id($policySetIds);
            if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                $assetModules = sr_community_asset_module_keys_from_value($assetSettings[$assetPrefix . '_asset_module'], true);
                $assetSettings[$assetPrefix . '_amounts_json'] = sr_community_asset_amounts_json_from_map(
                    sr_community_asset_amounts_from_post($assetPrefix . '_amounts', $assetModules, (int) ($assetSettings[$assetPrefix . '_amount'] ?? 0))
                );
                $assetSettings[$assetPrefix . '_amount'] = sr_community_asset_amount_total(
                    sr_community_asset_amounts_from_value($assetSettings[$assetPrefix . '_amounts_json'], $assetModules),
                    (int) ($assetSettings[$assetPrefix . '_amount'] ?? 0)
                );
            }
        }
        $legacyAssetPolicySource = sr_community_asset_policy_source(sr_post_string('asset_policy_source', 20));
        $legacyAssetSettingSource = $legacyAssetPolicySource === 'global' ? 'all' : $legacyAssetPolicySource;
        $assetSettingSources = [];
        $assetPrefixSources = [];
        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $legacyPrefixSource = sr_post_string('source_' . $assetPrefix, 20);
            if ($legacyPrefixSource === '') {
                $legacyPrefixSource = $legacyAssetSettingSource;
            }
            $assetModuleSource = sr_post_string('source_' . $assetPrefix . '_asset_module', 20);
            foreach (sr_community_asset_prefix_setting_keys((string) $assetPrefix) as $settingKey) {
                $postedSettingSource = sr_post_string('source_' . $settingKey, 20);
                if ($postedSettingSource === '' && in_array($settingKey, [$assetPrefix . '_amount', $assetPrefix . '_amounts_json'], true)) {
                    $postedSettingSource = $assetModuleSource;
                }
                $assetSettingSources[$settingKey] = sr_community_normalize_board_setting_source($postedSettingSource !== '' ? $postedSettingSource : $legacyPrefixSource);
            }
            $assetSettingSources[$assetPrefix . '_group_policies_json'] = $assetSettingSources[$assetPrefix . '_policy_set_id'] ?? $assetSettingSources[$assetPrefix . '_group_policies_json'];
            $assetPrefixSources[$assetPrefix] = $assetSettingSources[$assetPrefix . '_enabled'] ?? sr_community_normalize_board_setting_source($legacyPrefixSource);
        }
        $assetSettings['paid_read_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_read_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_attachment_download_charge_policy', 20), 'once');
        $assetSettingLabels = [];
        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $assetSettingLabels[$assetPrefix] = sr_community_asset_setting_label($assetPrefix);
        }
        $settingSources = [];
        foreach (sr_community_board_group_setting_keys() as $settingKey) {
            $settingSources[$settingKey] = sr_community_normalize_board_setting_source(sr_post_string('source_' . $settingKey, 20));
        }
        $boardSettingValues = [];
        $editingBoardId = 0;
        if ($intent === 'update') {
            $editingBoardIdValue = sr_post_string('board_id', 20);
            $editingBoardId = preg_match('/\A[1-9][0-9]*\z/', $editingBoardIdValue) === 1 ? (int) $editingBoardIdValue : 0;
        }

        if ($intent === 'create' && !sr_community_board_key_is_valid($boardKey)) {
            $errors[] = sr_t('community::action.admin.board_key_invalid');
        }

        if ($title === '') {
            $errors[] = sr_t('community::action.admin.board_title_required');
        }

        if ($description === null) {
            $errors[] = sr_t('community::action.admin.description_too_long');
            $description = '';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = sr_t('community::action.admin.board_status_invalid');
        }

        if (!in_array($readPolicy, $allowedReadPolicies, true)) {
            $errors[] = sr_t('community::action.admin.read_policy_invalid');
        }

        if (!in_array($writePolicy, $allowedWritePolicies, true)) {
            $errors[] = sr_t('community::action.admin.write_policy_invalid');
        }

        if (!in_array($commentPolicy, $allowedCommentPolicies, true)) {
            $errors[] = sr_t('community::action.admin.comment_policy_invalid');
        }

        if (!isset($communitySkinOptions[$skinKey])) {
            $errors[] = sr_t('community::action.admin.board_skin_invalid');
            $skinKey = 'basic';
        }

        if ($postEditorInput !== $postEditor || !array_key_exists($postEditor, $editorOptions)) {
            $errors[] = '게시판 에디터 값이 올바르지 않습니다.';
            $postEditor = 'textarea';
        }

        if ($sortOrder === null) {
            $errors[] = sr_t('community::action.admin.sort_order_invalid');
            $sortOrder = 0;
        }

        if ($attachmentMaxBytes === null) {
            $errors[] = sr_t('community::action.admin.image_max_bytes_invalid');
            $attachmentMaxBytes = 2097152;
        }

        if ($attachmentMaxCount === null) {
            $errors[] = sr_t('community::action.admin.image_max_count_invalid');
            $attachmentMaxCount = 1;
        }

        foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
            $displaySettingLabel = (string) ($publicDisplaySettingLabels[$displaySettingKey] ?? $displaySettingKey);
            if ($displaySettingValue === null) {
                $errors[] = sr_t('community::action.admin.display_value_invalid', ['label' => $displaySettingLabel]);
                $publicDisplaySettingValues[$displaySettingKey] = 0;
                continue;
            }

            if (isset($publicBannerSettingLabels[$displaySettingKey]) && $displaySettingValue > 0 && !isset($publicBannerIds[$displaySettingValue])) {
                $errors[] = sr_t('community::action.admin.display_banner_invalid', ['label' => $displaySettingLabel]);
            }

            if (isset($publicPopupLayerSettingLabels[$displaySettingKey]) && $displaySettingValue > 0 && !isset($publicPopupLayerIds[$displaySettingValue])) {
                $errors[] = sr_t('community::action.admin.display_popup_invalid', ['label' => $displaySettingLabel]);
            }
        }

        if ($fileAttachmentMaxBytes === null) {
            $errors[] = sr_t('community::action.admin.file_max_bytes_invalid');
            $fileAttachmentMaxBytes = 5242880;
        }

        if ($fileAttachmentMaxCount === null) {
            $errors[] = sr_t('community::action.admin.file_max_count_invalid');
            $fileAttachmentMaxCount = 3;
        }

        if (!is_string($fileAllowedExtensionsInput)) {
            $errors[] = sr_t('community::action.admin.file_extensions_too_long');
            $fileAllowedExtensions = [];
        } else {
            $invalidFileExtensions = sr_community_invalid_file_extensions_from_input($fileAllowedExtensionsInput);
            if ($invalidFileExtensions !== []) {
                $errors[] = sr_t('community::action.admin.file_extensions_invalid', ['extensions' => implode(', ', $invalidFileExtensions)]);
            }
        }

        if ($fileUploadsEnabled && $fileAllowedExtensions === []) {
            $errors[] = sr_t('community::action.admin.file_extensions_required');
        }

        if ($readMinLevel === null) {
            $errors[] = sr_t('community::action.admin.read_min_level_invalid', ['max' => (string) $maxLevel]);
            $readMinLevel = 0;
        }

        if ($writeMinLevel === null) {
            $errors[] = sr_t('community::action.admin.write_min_level_invalid', ['max' => (string) $maxLevel]);
            $writeMinLevel = 0;
        }

        if ($commentMinLevel === null) {
            $errors[] = sr_t('community::action.admin.comment_min_level_invalid', ['max' => (string) $maxLevel]);
            $commentMinLevel = 0;
        }

        if ($levelPostScore === null) {
            $errors[] = sr_t('community::action.admin.post_score_invalid');
            $levelPostScore = (int) $settings['level_post_score'];
        }

        if ($levelCommentScore === null) {
            $errors[] = sr_t('community::action.admin.comment_score_invalid');
            $levelCommentScore = (int) $settings['level_comment_score'];
        }

        if ($categoryRequired) {
            $categoryBoardId = $intent === 'update' ? $editingBoardId : 0;
            if ($categoryBoardId < 1 || sr_community_categories($pdo, $categoryBoardId, true) === []) {
                $errors[] = '활성 카테고리가 1개 이상 있어야 카테고리 필수를 켤 수 있습니다.';
            }
        }

        if ($boardGroupId > 0 && !isset($boardGroupIds[$boardGroupId])) {
            $errors[] = sr_t('community::action.admin.board_group_invalid');
        }

        foreach ($settingSources as $settingKey => $source) {
            if ($source === 'group' && $boardGroupId < 1) {
                $errors[] = sr_t('community::action.admin.setting_group_source_requires_group', ['setting' => $settingKey]);
            }
        }

        foreach ($assetSettingSources as $settingKey => $source) {
            if ($source === 'group' && $boardGroupId < 1) {
                $assetPrefix = sr_community_asset_prefix_from_setting_key((string) $settingKey);
                $assetLabel = (string) ($assetSettingLabels[$assetPrefix] ?? $settingKey);
                $errors[] = sr_t('community::action.admin.asset_group_source_requires_group', ['label' => $assetLabel]);
            }
        }

        foreach ([
            ['label' => sr_t('community::action.admin.label.read_group'), 'value' => $readGroupKeysInput],
            ['label' => sr_t('community::action.admin.label.write_group'), 'value' => $writeGroupKeysInput],
            ['label' => sr_t('community::action.admin.label.comment_group'), 'value' => $commentGroupKeysInput],
        ] as $groupKeyValidation) {
            $label = (string) $groupKeyValidation['label'];
            $groupKeysInput = $groupKeyValidation['value'];
            if (sr_community_board_group_keys_input_too_long($groupKeysInput)) {
                $errors[] = sr_t('community::action.admin.group_list_too_long', ['label' => $label]);
                continue;
            }

            $invalidGroupKeys = sr_community_invalid_board_group_keys_from_input_value($groupKeysInput);
            if ($invalidGroupKeys !== []) {
                $errors[] = sr_t('community::action.admin.group_keys_invalid', ['label' => $label, 'keys' => implode(', ', $invalidGroupKeys)]);
            }
        }

        foreach ([
            ['label' => sr_t('community::action.admin.label.read_group'), 'value' => $readGroupKeys],
            ['label' => sr_t('community::action.admin.label.write_group'), 'value' => $writeGroupKeys],
            ['label' => sr_t('community::action.admin.label.comment_group'), 'value' => $commentGroupKeys],
        ] as $groupKeyValidation) {
            $label = (string) $groupKeyValidation['label'];
            $groupKeys = $groupKeyValidation['value'];
            $unknownGroupKeys = array_values(array_diff($groupKeys, $enabledMemberGroupKeys));
            if ($unknownGroupKeys !== []) {
                $errors[] = sr_t('community::action.admin.group_keys_inactive', ['label' => $label, 'keys' => implode(', ', $unknownGroupKeys)]);
            }
        }

        $writeGroupKeys = array_values(array_intersect($writeGroupKeys, $readGroupKeys));
        $commentGroupKeys = array_values(array_intersect($commentGroupKeys, $readGroupKeys));

        foreach ($assetSettingLabels as $assetPrefix => $assetLabel) {
            if ($assetSettings[$assetPrefix . '_amount'] === null) {
                $errors[] = sr_t('community::action.admin.asset_amount_invalid', ['label' => $assetLabel]);
                $assetSettings[$assetPrefix . '_amount'] = 0;
            }

            if (($assetPrefixSources[$assetPrefix] ?? 'board') === 'board' && !empty($assetSettings[$assetPrefix . '_enabled']) && (int) $assetSettings[$assetPrefix . '_amount'] > 0) {
                $assetModule = (string) $assetSettings[$assetPrefix . '_asset_module'];
                if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                    $assetModules = sr_community_asset_module_keys_from_value($assetModule, true);
                    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
                        $errors[] = sr_t('community::action.admin.asset_modules_required_active', ['label' => $assetLabel]);
                    }
                    $amounts = sr_community_asset_amounts_from_value($assetSettings[$assetPrefix . '_amounts_json'] ?? '', $assetModules);
                    if (count($amounts) < count($assetModules)) {
                        $errors[] = sr_t('community::action.admin.asset_amounts_required', ['label' => $assetLabel]);
                    }
                } elseif (!isset($assetModuleOptions[$assetModule])) {
                    $errors[] = sr_t('community::action.admin.asset_module_inactive', [
                        'label' => $assetLabel,
                        'module' => sr_community_asset_module_label($assetModule, $pdo),
                    ]);
                }
            }
            $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, sr_community_asset_group_policies_from_value($assetSettings[$assetPrefix . '_group_policies_json'] ?? ''), $assetLabel));
            $assetPolicySetIds = sr_community_asset_policy_set_ids_with_legacy($assetSettings[$assetPrefix . '_group_policies_json'] ?? '', (int) ($assetSettings[$assetPrefix . '_policy_set_id'] ?? 0));
            $assetModulesForPolicy = sr_community_asset_module_keys_from_value((string) ($assetSettings[$assetPrefix . '_asset_module'] ?? ''), true);
            $errors = array_merge($errors, sr_community_asset_policy_set_ids_validation_errors($pdo, $assetPolicySetIds, $assetLabel));
            $errors = array_merge($errors, sr_community_asset_policy_set_asset_match_errors($pdo, $assetPolicySetIds, $assetModulesForPolicy, $assetLabel));
        }

        if ($errors === [] && $intent === 'create' && sr_community_board_by_key($pdo, $boardKey) !== null) {
            $errors[] = sr_t('community::action.admin.board_key_duplicate');
        }

        if ($errors === []) {
            $boardSettingValues = [
                'status' => $status,
                'skin_key' => $skinKey,
                'post_editor' => $postEditor,
                'read_policy' => $readPolicy,
                'write_policy' => $writePolicy,
                'comment_policy' => $commentPolicy,
                'read_group_keys' => sr_community_board_group_keys_setting_value($readGroupKeys),
                'write_group_keys' => sr_community_board_group_keys_setting_value($writeGroupKeys),
                'comment_group_keys' => sr_community_board_group_keys_setting_value($commentGroupKeys),
                'read_min_level' => (string) $readMinLevel,
                'write_min_level' => (string) $writeMinLevel,
                'comment_min_level' => (string) $commentMinLevel,
                'category_required' => $categoryRequired ? '1' : '0',
                'level_post_score' => (string) $levelPostScore,
                'level_comment_score' => (string) $levelCommentScore,
                'image_uploads_enabled' => $imageUploadsEnabled ? '1' : '0',
                'attachment_max_bytes' => (string) $attachmentMaxBytes,
                'attachment_max_count' => (string) $attachmentMaxCount,
                'file_uploads_enabled' => $fileUploadsEnabled ? '1' : '0',
                'file_attachment_max_bytes' => (string) $fileAttachmentMaxBytes,
                'file_attachment_max_count' => (string) $fileAttachmentMaxCount,
                'file_allowed_extensions' => implode(',', $fileAllowedExtensions),
            ];
            foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                $boardSettingValues[(string) $displaySettingKey] = (string) $displaySettingValue;
            }
        }

        if ($intent === 'create' && $errors === []) {
            $boardId = sr_community_create_board($pdo, [
                'board_group_id' => $boardGroupId,
                'board_key' => $boardKey,
                'title' => $title,
                'description' => (string) $description,
                'status' => $status,
                'read_policy' => $readPolicy,
                'write_policy' => $writePolicy,
                'comment_policy' => $commentPolicy,
                'image_uploads_enabled' => $imageUploadsEnabled,
                'sort_order' => (int) $sortOrder,
            ]);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.board.created',
                'target_type' => 'community_board',
                'target_id' => (string) $boardId,
                'result' => 'success',
                'message' => 'Community board created.',
                'metadata' => array_merge([
                    'board_key' => $boardKey,
                    'board_group_id' => $boardGroupId,
                    'status' => $status,
                    'image_uploads_enabled' => $imageUploadsEnabled,
                    'file_uploads_enabled' => $fileUploadsEnabled,
                    'attachment_max_bytes' => $attachmentMaxBytes,
                    'attachment_max_count' => $attachmentMaxCount,
                    'file_attachment_max_bytes' => $fileAttachmentMaxBytes,
                    'file_attachment_max_count' => $fileAttachmentMaxCount,
                    'file_allowed_extensions' => $fileAllowedExtensions,
                    'read_group_keys' => $readGroupKeys,
                    'write_group_keys' => $writeGroupKeys,
                    'comment_group_keys' => $commentGroupKeys,
                    'read_min_level' => $readMinLevel,
                    'write_min_level' => $writeMinLevel,
                    'comment_min_level' => $commentMinLevel,
                    'level_post_score' => $levelPostScore,
                    'level_comment_score' => $levelCommentScore,
                    'skin_key' => $skinKey,
                    'asset_settings' => $assetSettings,
                    'asset_prefix_sources' => $assetPrefixSources,
                    'asset_setting_sources' => $assetSettingSources,
                    'setting_sources' => $settingSources,
                ], $publicDisplaySettingValues),
            ]);
            sr_community_set_board_setting($pdo, $boardId, 'skin_key', $skinKey, 'string');
            sr_community_set_board_setting($pdo, $boardId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
            foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                sr_community_set_board_setting($pdo, $boardId, $displaySettingKey, (string) $displaySettingValue, 'int');
            }
            sr_community_set_board_setting($pdo, $boardId, 'file_uploads_enabled', $fileUploadsEnabled ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_bytes', (string) $fileAttachmentMaxBytes, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_count', (string) $fileAttachmentMaxCount, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'file_allowed_extensions', implode(',', $fileAllowedExtensions), 'string');
            sr_community_set_board_setting($pdo, $boardId, 'read_group_keys', sr_community_board_group_keys_setting_value($readGroupKeys), 'json');
            sr_community_set_board_setting($pdo, $boardId, 'write_group_keys', sr_community_board_group_keys_setting_value($writeGroupKeys), 'json');
            sr_community_set_board_setting($pdo, $boardId, 'comment_group_keys', sr_community_board_group_keys_setting_value($commentGroupKeys), 'json');
            sr_community_set_board_setting($pdo, $boardId, 'read_min_level', (string) $readMinLevel, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'write_min_level', (string) $writeMinLevel, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'comment_min_level', (string) $commentMinLevel, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'category_required', $categoryRequired ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'level_post_score', (string) $levelPostScore, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'level_comment_score', (string) $levelCommentScore, 'int');
            sr_community_save_board_asset_settings($pdo, $boardId, $assetSettings);
            foreach ($boardSettingValues as $settingKey => $settingValue) {
                sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, (string) ($settingSources[$settingKey] ?? 'board'), $settingValue);
            }
            foreach ($assetSettingSources as $settingKey => $source) {
                sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, $source, $assetSettings[$settingKey] ?? '');
            }

            $notice = sr_t('community::action.admin.board_created');
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/community/boards');
        } elseif ($intent === 'update' && $errors === []) {
            $boardIdValue = sr_post_string('board_id', 20);
            $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
            $board = sr_community_board_by_id($pdo, $boardId);
            if (!is_array($board)) {
                $errors[] = sr_t('community::action.error.board_not_found');
            }

            if ($errors === [] && is_array($board)) {
                $beforeAttachmentMaxBytes = sr_community_board_attachment_max_bytes($pdo, $boardId);
                $beforeAttachmentMaxCount = sr_community_board_attachment_max_count($pdo, $boardId);
                $beforePublicDisplaySettingValues = [];
                foreach ($publicDisplaySettingLabels as $displaySettingKey => $displaySettingLabel) {
                    $beforePublicDisplaySettingValues[$displaySettingKey] = (int) (sr_community_board_setting_value($pdo, $boardId, $displaySettingKey) ?? 0);
                }
                $beforeFileAttachmentMaxBytes = sr_community_board_file_attachment_max_bytes($pdo, $boardId);
                $beforeFileAttachmentMaxCount = sr_community_board_file_attachment_max_count($pdo, $boardId);
                $beforeFileAllowedExtensions = sr_community_board_file_allowed_extensions($pdo, $boardId);
                $beforeReadGroupKeys = sr_community_board_group_keys($pdo, $boardId, 'read_group_keys');
                $beforeWriteGroupKeys = sr_community_board_group_keys($pdo, $boardId, 'write_group_keys');
                $beforeCommentGroupKeys = sr_community_board_group_keys($pdo, $boardId, 'comment_group_keys');
                $beforeReadMinLevel = sr_community_board_min_level($pdo, $boardId, 'read_min_level');
                $beforeWriteMinLevel = sr_community_board_min_level($pdo, $boardId, 'write_min_level');
                $beforeCommentMinLevel = sr_community_board_min_level($pdo, $boardId, 'comment_min_level');
                $beforeCategoryRequired = sr_community_board_category_required($pdo, $boardId);
                $beforeLevelPostScore = sr_community_board_level_score($pdo, $boardId, 'level_post_score', $settings);
                $beforeLevelCommentScore = sr_community_board_level_score($pdo, $boardId, 'level_comment_score', $settings);
                $beforeSkinKey = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, $boardId, 'skin_key') ?? 'basic')]);
                $beforeAssetSettingSources = [];
                foreach (sr_community_asset_setting_keys() as $assetSettingKey) {
                    $beforeAssetSettingSources[$assetSettingKey] = sr_community_board_asset_setting_key_source($pdo, $boardId, (string) $assetSettingKey);
                }
                $beforeAssetSettings = [];
                foreach ($assetSettings as $assetSettingKey => $assetSettingValue) {
                    $beforeAssetSettings[$assetSettingKey] = sr_community_board_setting_value($pdo, $boardId, (string) $assetSettingKey);
                }
                $beforeCurrentBoardAssetSettingsForAudit = sr_community_board_asset_settings_for_audit($pdo, $boardId);
                sr_community_update_board($pdo, $boardId, [
                    'board_group_id' => $boardGroupId,
                    'title' => $title,
                    'description' => (string) $description,
                    'status' => $status,
                    'read_policy' => $readPolicy,
                    'write_policy' => $writePolicy,
                    'comment_policy' => $commentPolicy,
                    'image_uploads_enabled' => $imageUploadsEnabled,
                    'sort_order' => (int) $sortOrder,
                ]);
                sr_community_set_board_setting($pdo, $boardId, 'skin_key', $skinKey, 'string');
                sr_community_set_board_setting($pdo, $boardId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
                foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                    sr_community_set_board_setting($pdo, $boardId, $displaySettingKey, (string) $displaySettingValue, 'int');
                }
                sr_community_set_board_setting($pdo, $boardId, 'file_uploads_enabled', $fileUploadsEnabled ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_bytes', (string) $fileAttachmentMaxBytes, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_count', (string) $fileAttachmentMaxCount, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'file_allowed_extensions', implode(',', $fileAllowedExtensions), 'string');
                sr_community_set_board_setting($pdo, $boardId, 'read_group_keys', sr_community_board_group_keys_setting_value($readGroupKeys), 'json');
                sr_community_set_board_setting($pdo, $boardId, 'write_group_keys', sr_community_board_group_keys_setting_value($writeGroupKeys), 'json');
                sr_community_set_board_setting($pdo, $boardId, 'comment_group_keys', sr_community_board_group_keys_setting_value($commentGroupKeys), 'json');
                sr_community_set_board_setting($pdo, $boardId, 'read_min_level', (string) $readMinLevel, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'write_min_level', (string) $writeMinLevel, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'comment_min_level', (string) $commentMinLevel, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'category_required', $categoryRequired ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'level_post_score', (string) $levelPostScore, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'level_comment_score', (string) $levelCommentScore, 'int');
                foreach ($boardSettingValues as $settingKey => $settingValue) {
                    sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, (string) ($settingSources[$settingKey] ?? 'board'), $settingValue);
                }
                $boardAssetAudits = [];
                foreach ($assetSettingSources as $settingKey => $source) {
                    foreach (sr_community_board_scope_target_ids($pdo, $boardId, $boardGroupId, (string) $source) as $targetBoardId) {
                        $targetBoardId = (int) $targetBoardId;
                        if ($targetBoardId < 1) {
                            continue;
                        }

                        if (!isset($boardAssetAudits[$targetBoardId])) {
                            $targetBoard = sr_community_board_by_id($pdo, $targetBoardId);
                            $boardAssetAudits[$targetBoardId] = [
                                'board_key' => is_array($targetBoard) ? (string) ($targetBoard['board_key'] ?? '') : '',
                                'before_asset_settings' => $targetBoardId === $boardId ? $beforeCurrentBoardAssetSettingsForAudit : sr_community_board_asset_settings_for_audit($pdo, $targetBoardId),
                                'applied_setting_keys' => [],
                            ];
                        }
                        $boardAssetAudits[$targetBoardId]['applied_setting_keys'][(string) $settingKey] = true;
                    }
                }
                sr_community_save_board_asset_settings($pdo, $boardId, $assetSettings);
                foreach ($assetSettingSources as $settingKey => $source) {
                    sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, $source, $assetSettings[$settingKey] ?? '');
                }

                $publicDisplayMetadata = [];
                foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                    $publicDisplayMetadata['before_' . $displaySettingKey] = (int) ($beforePublicDisplaySettingValues[$displaySettingKey] ?? 0);
                    $publicDisplayMetadata['after_' . $displaySettingKey] = (int) $displaySettingValue;
                }

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.board.updated',
                    'target_type' => 'community_board',
                    'target_id' => (string) $boardId,
                    'result' => 'success',
                    'message' => 'Community board updated.',
                    'metadata' => array_merge([
                        'board_key' => (string) $board['board_key'],
                        'before_status' => (string) $board['status'],
                        'after_status' => $status,
                        'before_board_group_id' => (int) ($board['board_group_id'] ?? 0),
                        'after_board_group_id' => $boardGroupId,
                        'before_image_uploads_enabled' => (int) $board['image_uploads_enabled'] === 1,
                        'after_image_uploads_enabled' => $imageUploadsEnabled,
                        'after_file_uploads_enabled' => $fileUploadsEnabled,
                        'before_attachment_max_bytes' => $beforeAttachmentMaxBytes,
                        'after_attachment_max_bytes' => $attachmentMaxBytes,
                        'before_attachment_max_count' => $beforeAttachmentMaxCount,
                        'after_attachment_max_count' => $attachmentMaxCount,
                        'before_file_attachment_max_bytes' => $beforeFileAttachmentMaxBytes,
                        'after_file_attachment_max_bytes' => $fileAttachmentMaxBytes,
                        'before_file_attachment_max_count' => $beforeFileAttachmentMaxCount,
                        'after_file_attachment_max_count' => $fileAttachmentMaxCount,
                        'before_file_allowed_extensions' => $beforeFileAllowedExtensions,
                        'after_file_allowed_extensions' => $fileAllowedExtensions,
                        'before_read_group_keys' => $beforeReadGroupKeys,
                        'after_read_group_keys' => $readGroupKeys,
                        'before_write_group_keys' => $beforeWriteGroupKeys,
                        'after_write_group_keys' => $writeGroupKeys,
                        'before_comment_group_keys' => $beforeCommentGroupKeys,
                        'after_comment_group_keys' => $commentGroupKeys,
                        'before_read_min_level' => $beforeReadMinLevel,
                        'after_read_min_level' => $readMinLevel,
                        'before_write_min_level' => $beforeWriteMinLevel,
                        'after_write_min_level' => $writeMinLevel,
                        'before_comment_min_level' => $beforeCommentMinLevel,
                        'after_comment_min_level' => $commentMinLevel,
                        'before_category_required' => $beforeCategoryRequired,
                        'after_category_required' => $categoryRequired,
                        'before_level_post_score' => $beforeLevelPostScore,
                        'after_level_post_score' => $levelPostScore,
                        'before_level_comment_score' => $beforeLevelCommentScore,
                        'after_level_comment_score' => $levelCommentScore,
                        'before_skin_key' => $beforeSkinKey,
                        'after_skin_key' => $skinKey,
                        'before_asset_setting_sources' => $beforeAssetSettingSources,
                        'after_asset_prefix_sources' => $assetPrefixSources,
                        'after_asset_setting_sources' => $assetSettingSources,
                        'before_asset_settings' => $beforeAssetSettings,
                        'after_asset_settings' => $assetSettings,
                        'setting_sources' => $settingSources,
                    ], $publicDisplayMetadata),
                ]);
                foreach ($boardAssetAudits as $targetBoardId => $boardAssetAudit) {
                    $appliedSettingKeys = array_keys(is_array($boardAssetAudit['applied_setting_keys'] ?? null) ? $boardAssetAudit['applied_setting_keys'] : []);
                    sort($appliedSettingKeys);
                    sr_admin_audit_asset_settings_update($pdo, [
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'admin',
                        'event_type' => 'community.board.asset_settings.updated',
                        'target_type' => 'community_board',
                        'target_id' => (string) $targetBoardId,
                        'asset_settings_scope' => 'community.board',
                        'before_asset_settings' => is_array($boardAssetAudit['before_asset_settings'] ?? null) ? $boardAssetAudit['before_asset_settings'] : [],
                        'after_asset_settings' => sr_community_board_asset_settings_for_audit($pdo, (int) $targetBoardId),
                        'message' => 'Community board asset settings updated.',
                        'metadata' => [
                            'board_key' => (string) ($boardAssetAudit['board_key'] ?? ''),
                            'source' => 'community_board',
                            'source_board_key' => (string) $board['board_key'],
                            'applied_setting_keys' => $appliedSettingKeys,
                        ],
                    ]);
                }

                $notice = sr_t('community::action.admin.board_updated');
            }
        }
    } else {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }
}

$communityAdminPrepareBoard = static function (array $board) use ($pdo, $settings, $publicDisplaySettingLabels): array {
    $board['setting_sources'] = sr_community_board_setting_sources($pdo, (int) $board['id']);
    $board['attachment_max_bytes'] = sr_community_board_own_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['attachment_max_count'] = sr_community_board_own_attachment_max_count($pdo, (int) $board['id'], $settings);
    foreach ($publicDisplaySettingLabels as $displaySettingKey => $displaySettingLabel) {
        $board[$displaySettingKey] = (int) (sr_community_board_setting_value($pdo, (int) $board['id'], $displaySettingKey) ?? 0);
    }
    $board['effective_attachment_max_bytes'] = sr_community_board_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['effective_attachment_max_count'] = sr_community_board_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['file_uploads_enabled'] = sr_community_effective_board_setting($pdo, $board, 'file_uploads_enabled', '0');
    $board['effective_file_uploads_enabled'] = sr_community_effective_board_file_uploads_enabled($pdo, $board) ? 1 : 0;
    $board['file_attachment_max_bytes'] = sr_community_board_own_file_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['file_attachment_max_count'] = sr_community_board_own_file_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['effective_file_attachment_max_bytes'] = sr_community_board_file_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['effective_file_attachment_max_count'] = sr_community_board_file_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['file_allowed_extensions'] = sr_community_board_own_file_allowed_extensions($pdo, (int) $board['id'], $settings);
    $board['effective_file_allowed_extensions'] = sr_community_board_file_allowed_extensions($pdo, (int) $board['id'], $settings);
    $board['read_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'read_group_keys');
    $board['write_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'write_group_keys');
    $board['comment_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'comment_group_keys');
    $board['effective_read_group_keys'] = sr_community_board_group_keys($pdo, (int) $board['id'], 'read_group_keys');
    $board['effective_write_group_keys'] = sr_community_board_group_keys($pdo, (int) $board['id'], 'write_group_keys');
    $board['effective_comment_group_keys'] = sr_community_board_group_keys($pdo, (int) $board['id'], 'comment_group_keys');
    $board['read_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'read_min_level');
    $board['write_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'write_min_level');
    $board['comment_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'comment_min_level');
    $board['category_required'] = sr_community_board_category_required($pdo, (int) $board['id']) ? '1' : '0';
    $board['effective_read_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'read_min_level');
    $board['effective_write_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'write_min_level');
    $board['effective_comment_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'comment_min_level');
    $board['categories'] = sr_community_categories($pdo, (int) $board['id'], false);
    $board['level_post_score'] = sr_community_board_own_level_score($pdo, (int) $board['id'], 'level_post_score', $settings);
    $board['level_comment_score'] = sr_community_board_own_level_score($pdo, (int) $board['id'], 'level_comment_score', $settings);
    $board['effective_level_post_score'] = sr_community_board_level_score($pdo, (int) $board['id'], 'level_post_score', $settings);
    $board['effective_level_comment_score'] = sr_community_board_level_score($pdo, (int) $board['id'], 'level_comment_score', $settings);
    $board['skin_key'] = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'skin_key') ?? 'basic')]);
    $board['post_editor'] = sr_community_post_editor_key((string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'post_editor') ?? 'textarea'));
    $board['effective_post_editor'] = sr_community_effective_post_editor($pdo, $board, $settings);
    foreach (sr_community_asset_setting_keys() as $assetSettingKey) {
        $board['source_' . $assetSettingKey] = sr_community_board_asset_setting_key_source($pdo, (int) $board['id'], (string) $assetSettingKey);
    }
    foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
        $board[$assetPrefix . '_enabled'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_enabled', !empty($settings[$assetPrefix . '_enabled']) ? '1' : '0');
        $board[$assetPrefix . '_asset_module'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_asset_module', (string) ($settings[$assetPrefix . '_asset_module'] ?? ''));
        $board[$assetPrefix . '_amount'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_amount', (string) ($settings[$assetPrefix . '_amount'] ?? 0));
        $board[$assetPrefix . '_group_policies_json'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_group_policies_json', (string) ($settings[$assetPrefix . '_group_policies_json'] ?? ''));
        $board[$assetPrefix . '_policy_set_id'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_policy_set_id', (string) ($settings[$assetPrefix . '_policy_set_id'] ?? 0));
        if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
            $board[$assetPrefix . '_amounts_json'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_amounts_json', (string) ($settings[$assetPrefix . '_amounts_json'] ?? ''));
        }
        if (in_array($assetPrefix, ['paid_read', 'paid_attachment_download'], true)) {
            $board[$assetPrefix . '_charge_policy'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_charge_policy', (string) ($settings[$assetPrefix . '_charge_policy'] ?? 'once'));
        }
    }

    return $board;
};

$boardStatusCounts = sr_community_admin_board_status_counts($pdo, $allowedStatuses);
$boardSort = sr_admin_sort_from_request(sr_community_admin_board_sort_options(), sr_community_admin_board_default_sort());
$boardPagination = sr_admin_pagination_from_total($pdo, $communityBoardsPage === 'list' ? sr_community_admin_board_count($pdo, $boardListFilters) : 0);
$boards = [];
if ($communityBoardsPage === 'list') {
    foreach (sr_community_admin_boards($pdo, $boardListFilters, (int) $boardPagination['per_page'], sr_admin_pagination_offset($boardPagination), $boardSort) as $board) {
        $boards[] = $communityAdminPrepareBoard($board);
    }
}

$editBoard = null;
if ($communityBoardsPage === 'edit') {
    $editBoardIdValue = isset($_GET['edit_id']) ? (string) $_GET['edit_id'] : '';
    $editBoardId = preg_match('/\A[1-9][0-9]*\z/', $editBoardIdValue) === 1 ? (int) $editBoardIdValue : 0;
    $editBoard = sr_community_board_by_id($pdo, $editBoardId);
    if (is_array($editBoard)) {
        $editBoard = $communityAdminPrepareBoard($editBoard);
    }

    if (!is_array($editBoard)) {
        sr_render_error(404, sr_t('community::action.error.board_not_found'));
    }
}

include SR_ROOT . '/modules/community/views/admin-boards.php';
