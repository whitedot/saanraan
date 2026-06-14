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
if (is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
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
$reactionPresetOptions = function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
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
        $readPolicy = sr_post_string('group_read_policy', 30);
        $writePolicy = sr_post_string('group_write_policy', 30);
        $commentPolicy = sr_post_string('group_comment_policy', 30);
        $postEditorInput = sr_post_string('group_post_editor', 30);
        $postEditor = sr_community_post_editor_key($postEditorInput);
        $attachmentMaxBytes = sr_admin_post_int_in_range('group_attachment_max_bytes', 1024, 10485760);
        $attachmentMaxCount = sr_admin_post_int_in_range('group_attachment_max_count', 0, 10);
        $imageUploadsEnabled = ($_POST['group_image_uploads_enabled'] ?? '') === '1';
        $fileUploadsEnabled = ($_POST['group_file_uploads_enabled'] ?? '') === '1';
        $fileAttachmentMaxBytes = sr_admin_post_int_in_range('group_file_attachment_max_bytes', 1024, 20971520);
        $fileAttachmentMaxCount = sr_admin_post_int_in_range('group_file_attachment_max_count', 0, 5);
        $fileAllowedExtensionsInput = sr_post_string_without_truncation('group_file_allowed_extensions', 1000);
        $fileAllowedExtensions = is_string($fileAllowedExtensionsInput) ? sr_community_file_extensions_from_input($fileAllowedExtensionsInput) : [];
        $readMinLevel = sr_admin_post_int_in_range('group_read_min_level', 0, $maxLevel);
        $writeMinLevel = sr_admin_post_int_in_range('group_write_min_level', 0, $maxLevel);
        $commentMinLevel = sr_admin_post_int_in_range('group_comment_min_level', 0, $maxLevel);
        $levelPostScore = sr_admin_post_int_in_range('group_level_post_score', 0, 10000);
        $levelCommentScore = sr_admin_post_int_in_range('group_level_comment_score', 0, 10000);
        $postEditLockCommentCount = sr_admin_post_int_in_range('group_post_edit_lock_comment_count', 0, 1000000);
        $postDeleteLockCommentCount = sr_admin_post_int_in_range('group_post_delete_lock_comment_count', 0, 1000000);
        $postBodyMinLength = sr_admin_post_int_in_range('group_post_body_min_length', 0, 20000);
        $postBodyMaxLength = sr_admin_post_int_in_range('group_post_body_max_length', 0, 20000);
        $commentBodyMinLength = sr_admin_post_int_in_range('group_comment_body_min_length', 0, 5000);
        $commentBodyMaxLength = sr_admin_post_int_in_range('group_comment_body_max_length', 0, 5000);
        $listExcerptEnabled = ($_POST['group_list_excerpt_enabled'] ?? '') === '1';
        $listExcerptLength = sr_admin_post_int_in_range('group_list_excerpt_length', 1, 1000);
        $listPerPage = sr_admin_post_int_in_range('group_list_per_page', 1, 100);
        $listDefaultSortInput = sr_post_string('group_list_default_sort', 20);
        $listDefaultSort = sr_community_board_list_sort_key($listDefaultSortInput);
        $reactionPostPresetKey = function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, sr_post_string('group_reaction_post_preset_key', 80)) : '';
        $reactionCommentPresetKey = function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, sr_post_string('group_reaction_comment_preset_key', 80)) : '';
        $privacyConsentEnabled = ($_POST['group_privacy_consent_enabled'] ?? '') === '1';
        $privacyConsentTitle = trim(sr_post_string('group_privacy_consent_title', 120));
        $privacyConsentBodyInput = sr_post_string_without_truncation('group_privacy_consent_body', 5000);
        $privacyConsentBody = is_string($privacyConsentBodyInput) ? trim($privacyConsentBodyInput) : '';
        $privacyConsentVersion = trim(sr_post_string('group_privacy_consent_version', 60));
        $privacyConsentRequirePost = ($_POST['group_privacy_consent_require_post'] ?? '') === '1';
        $privacyConsentRequireComment = ($_POST['group_privacy_consent_require_comment'] ?? '') === '1';
        $privacyConsentRequireAttachmentUpload = ($_POST['group_privacy_consent_require_attachment_upload'] ?? '') === '1';
        $extraFieldsInput = sr_post_string_without_truncation('group_extra_fields_json', 20000);
        $extraFieldDefinitionErrors = sr_community_extra_field_definitions_input_errors($extraFieldsInput);
        $extraFieldsJson = $extraFieldDefinitionErrors === [] && is_string($extraFieldsInput) ? sr_community_extra_field_definitions_json_from_input($extraFieldsInput) : null;
        $publicDisplaySettingValues = [];
        foreach ($publicDisplaySettingLabels as $displaySettingKey => $displaySettingLabel) {
            $publicDisplaySettingValues[$displaySettingKey] = sr_admin_post_int_in_range('group_' . $displaySettingKey, 0, 999999999);
        }
        $assetSettings = [];
        foreach (['post_reward', 'comment_reward', 'write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'] as $assetPrefix) {
            $policySetIds = sr_community_asset_policy_set_ids_from_value($_POST['group_' . $assetPrefix . '_policy_set_ids'] ?? []);
            $assetSettings[$assetPrefix . '_enabled'] = ($_POST['group_' . $assetPrefix . '_enabled'] ?? '') === '1';
            $assetSettings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
                ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($_POST['group_' . $assetPrefix . '_asset_module'] ?? '', true), true)
                : sr_community_asset_module_key_or_empty(sr_post_string('group_' . $assetPrefix . '_asset_module', 20));
            $assetSettings[$assetPrefix . '_amount'] = sr_admin_post_int_in_range('group_' . $assetPrefix . '_amount', 0, 999999999);
            $assetSettings[$assetPrefix . '_group_policies_json'] = sr_community_asset_policy_set_selection_json_from_ids($policySetIds);
            $assetSettings[$assetPrefix . '_policy_set_id'] = sr_community_asset_policy_set_first_id($policySetIds);
            if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                $assetModules = sr_community_asset_module_keys_from_value($assetSettings[$assetPrefix . '_asset_module'], true);
                $assetSettings[$assetPrefix . '_amounts_json'] = sr_community_asset_amounts_json_from_map(
                    sr_community_asset_amounts_from_post('group_' . $assetPrefix . '_amounts', $assetModules, (int) ($assetSettings[$assetPrefix . '_amount'] ?? 0))
                );
                $assetSettings[$assetPrefix . '_amount'] = sr_community_asset_amount_total(
                    sr_community_asset_amounts_from_value($assetSettings[$assetPrefix . '_amounts_json'], $assetModules),
                    (int) ($assetSettings[$assetPrefix . '_amount'] ?? 0)
                );
            }
        }
        $assetSettings['paid_read_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('group_paid_read_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('group_paid_attachment_download_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_publisher_reward_enabled'] = ($_POST['group_paid_attachment_download_publisher_reward_enabled'] ?? '') === '1';
        $assetSettings['paid_attachment_download_publisher_reward_rate'] = sr_admin_post_int_in_range('group_paid_attachment_download_publisher_reward_rate', 0, 100);
        $readGroupKeysInput = $_POST['group_read_group_keys'] ?? [];
        $writeGroupKeysInput = $_POST['group_write_group_keys'] ?? [];
        $commentGroupKeysInput = $_POST['group_comment_group_keys'] ?? [];
        $readGroupKeys = sr_community_board_group_keys_from_input_value($readGroupKeysInput);
        $writeGroupKeys = sr_community_board_group_keys_from_input_value($writeGroupKeysInput);
        $commentGroupKeys = sr_community_board_group_keys_from_input_value($commentGroupKeysInput);

        if ($intent === 'create_group' && !sr_community_board_group_key_is_valid($groupKey)) {
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

        foreach ([
            ['label' => sr_t('community::action.admin.label.read'), 'policy' => $readPolicy, 'allowed' => $allowedReadPolicies],
            ['label' => sr_t('community::action.admin.label.write'), 'policy' => $writePolicy, 'allowed' => $allowedWritePolicies],
            ['label' => sr_t('community::action.admin.label.comment'), 'policy' => $commentPolicy, 'allowed' => $allowedCommentPolicies],
        ] as $policyValidation) {
            $label = (string) $policyValidation['label'];
            if (!in_array((string) $policyValidation['policy'], $policyValidation['allowed'], true)) {
                $errors[] = sr_t('community::action.admin.board_group_policy_invalid', ['label' => $label]);
            }
        }

        if ($postEditorInput !== $postEditor || !array_key_exists($postEditor, $editorOptions)) {
            $errors[] = '게시판 그룹 에디터 값이 올바르지 않습니다.';
            $postEditor = 'textarea';
        }

        if ($attachmentMaxBytes === null) {
            $errors[] = sr_t('community::action.admin.board_group_image_max_bytes_invalid');
            $attachmentMaxBytes = 2097152;
        }

        if ($attachmentMaxCount === null) {
            $errors[] = sr_t('community::action.admin.board_group_image_max_count_invalid');
            $attachmentMaxCount = 1;
        }

        if ($fileAttachmentMaxBytes === null) {
            $errors[] = sr_t('community::action.admin.board_group_file_max_bytes_invalid');
            $fileAttachmentMaxBytes = 5242880;
        }

        if ($fileAttachmentMaxCount === null) {
            $errors[] = sr_t('community::action.admin.board_group_file_max_count_invalid');
            $fileAttachmentMaxCount = 3;
        }

        if (!is_string($fileAllowedExtensionsInput)) {
            $errors[] = sr_t('community::action.admin.board_group_file_extensions_too_long');
            $fileAllowedExtensions = [];
        } else {
            $invalidFileExtensions = sr_community_invalid_file_extensions_from_input($fileAllowedExtensionsInput);
            if ($invalidFileExtensions !== []) {
                $errors[] = sr_t('community::action.admin.board_group_file_extensions_invalid', ['extensions' => implode(', ', $invalidFileExtensions)]);
            }
        }

        if ($fileUploadsEnabled && $fileAllowedExtensions === []) {
            $errors[] = sr_t('community::action.admin.board_group_file_extensions_required');
        }

        if ($readMinLevel === null) {
            $errors[] = sr_t('community::action.admin.board_group_read_min_level_invalid', ['max' => (string) $maxLevel]);
            $readMinLevel = 0;
        }

        if ($writeMinLevel === null) {
            $errors[] = sr_t('community::action.admin.board_group_write_min_level_invalid', ['max' => (string) $maxLevel]);
            $writeMinLevel = 0;
        }

        if ($commentMinLevel === null) {
            $errors[] = sr_t('community::action.admin.board_group_comment_min_level_invalid', ['max' => (string) $maxLevel]);
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

        foreach ([
            'postEditLockCommentCount' => ['value' => $postEditLockCommentCount, 'message' => '게시판 그룹의 게시글 수정 잠금 댓글 수가 올바르지 않습니다.', 'fallback' => 0],
            'postDeleteLockCommentCount' => ['value' => $postDeleteLockCommentCount, 'message' => '게시판 그룹의 게시글 삭제 잠금 댓글 수가 올바르지 않습니다.', 'fallback' => 0],
            'postBodyMinLength' => ['value' => $postBodyMinLength, 'message' => '게시판 그룹의 게시글 본문 최소 길이가 올바르지 않습니다.', 'fallback' => 0],
            'postBodyMaxLength' => ['value' => $postBodyMaxLength, 'message' => '게시판 그룹의 게시글 본문 최대 길이가 올바르지 않습니다.', 'fallback' => 0],
            'commentBodyMinLength' => ['value' => $commentBodyMinLength, 'message' => '게시판 그룹의 댓글 본문 최소 길이가 올바르지 않습니다.', 'fallback' => 0],
            'commentBodyMaxLength' => ['value' => $commentBodyMaxLength, 'message' => '게시판 그룹의 댓글 본문 최대 길이가 올바르지 않습니다.', 'fallback' => 0],
            'listExcerptLength' => ['value' => $listExcerptLength, 'message' => '게시판 그룹의 목록 본문 요약 길이가 올바르지 않습니다.', 'fallback' => 120],
            'listPerPage' => ['value' => $listPerPage, 'message' => '게시판 그룹의 목록 페이지당 글 수가 올바르지 않습니다.', 'fallback' => 20],
        ] as $numericSettingKey => $numericSetting) {
            if ($numericSetting['value'] === null) {
                $errors[] = (string) $numericSetting['message'];
                ${$numericSettingKey} = (int) $numericSetting['fallback'];
            }
        }
        if ($postBodyMinLength > 0 && $postBodyMaxLength > 0 && $postBodyMinLength > $postBodyMaxLength) {
            $errors[] = '게시판 그룹의 게시글 본문 최소 길이는 최대 길이보다 클 수 없습니다.';
        }
        if ($commentBodyMinLength > 0 && $commentBodyMaxLength > 0 && $commentBodyMinLength > $commentBodyMaxLength) {
            $errors[] = '게시판 그룹의 댓글 본문 최소 길이는 최대 길이보다 클 수 없습니다.';
        }
        if ($listDefaultSortInput !== $listDefaultSort) {
            $errors[] = '게시판 그룹의 목록 기본 정렬 값이 올바르지 않습니다.';
        }

        foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
            $displaySettingLabel = (string) ($publicDisplaySettingLabels[$displaySettingKey] ?? $displaySettingKey);
            if ($displaySettingValue === null) {
                $errors[] = sr_t('community::action.admin.board_group_display_value_invalid', ['label' => $displaySettingLabel]);
                $publicDisplaySettingValues[$displaySettingKey] = 0;
                continue;
            }

            if (isset($publicBannerSettingLabels[$displaySettingKey]) && $displaySettingValue > 0 && !isset($publicBannerIds[$displaySettingValue])) {
                $errors[] = sr_t('community::action.admin.board_group_display_banner_invalid', ['label' => $displaySettingLabel]);
            }

            if (isset($publicPopupLayerSettingLabels[$displaySettingKey]) && $displaySettingValue > 0 && !isset($publicPopupLayerIds[$displaySettingValue])) {
                $errors[] = sr_t('community::action.admin.board_group_display_popup_invalid', ['label' => $displaySettingLabel]);
            }
        }

        foreach ([
            ['label' => sr_t('community::action.admin.label.board_group_read_member_group'), 'value' => $readGroupKeysInput],
            ['label' => sr_t('community::action.admin.label.board_group_write_member_group'), 'value' => $writeGroupKeysInput],
            ['label' => sr_t('community::action.admin.label.board_group_comment_member_group'), 'value' => $commentGroupKeysInput],
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
            ['label' => sr_t('community::action.admin.label.board_group_read_member_group'), 'value' => $readGroupKeys],
            ['label' => sr_t('community::action.admin.label.board_group_write_member_group'), 'value' => $writeGroupKeys],
            ['label' => sr_t('community::action.admin.label.board_group_comment_member_group'), 'value' => $commentGroupKeys],
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

        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $assetLabel = sr_community_asset_setting_label($assetPrefix);
            if ($assetSettings[$assetPrefix . '_amount'] === null) {
                $errors[] = sr_t('community::action.admin.board_group_asset_amount_invalid', ['label' => $assetLabel]);
                $assetSettings[$assetPrefix . '_amount'] = 0;
            }

            if (!empty($assetSettings[$assetPrefix . '_enabled']) && (int) $assetSettings[$assetPrefix . '_amount'] > 0) {
                $assetModule = (string) $assetSettings[$assetPrefix . '_asset_module'];
                if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                    $assetModules = sr_community_asset_module_keys_from_value($assetModule, true);
                    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
                        $errors[] = sr_t('community::action.admin.board_group_asset_modules_required_active', ['label' => $assetLabel]);
                    }
                    $amounts = sr_community_asset_amounts_from_value($assetSettings[$assetPrefix . '_amounts_json'] ?? '', $assetModules);
                    if (count($amounts) < count($assetModules)) {
                        $errors[] = sr_t('community::action.admin.asset_amounts_required', ['label' => $assetLabel]);
                    }
                } elseif (!isset($assetModuleOptions[$assetModule])) {
                    $errors[] = sr_t('community::action.admin.board_group_asset_module_inactive', [
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
        if ($assetSettings['paid_attachment_download_publisher_reward_rate'] === null) {
            $errors[] = '첨부 다운로드 게시자 리워드 지급률이 올바르지 않습니다.';
            $assetSettings['paid_attachment_download_publisher_reward_rate'] = 0;
        }
        foreach ([$reactionPostPresetKey, $reactionCommentPresetKey] as $reactionPresetKey) {
            if ($reactionPresetKey !== '' && !isset($reactionPresetOptions[$reactionPresetKey])) {
                $errors[] = '게시판 그룹 리액션 프리셋 값이 올바르지 않습니다.';
                break;
            }
        }
        if (!is_string($privacyConsentBodyInput)) {
            $errors[] = '개인정보 수집 및 이용동의 본문이 너무 깁니다.';
            $privacyConsentBody = '';
        }
        if ($privacyConsentEnabled) {
            if (!sr_community_submission_consents_table_exists($pdo)) {
                $errors[] = '개인정보 수집 및 이용동의 스키마 업데이트가 아직 적용되지 않았습니다.';
            }
            if ($privacyConsentTitle === '') {
                $errors[] = '개인정보 수집 및 이용동의 제목을 입력해 주세요.';
            }
            if ($privacyConsentBody === '') {
                $errors[] = '개인정보 수집 및 이용동의 본문을 입력해 주세요.';
            }
            if ($privacyConsentVersion === '') {
                $errors[] = '개인정보 수집 및 이용동의 버전을 입력해 주세요.';
            }
            if (!$privacyConsentRequirePost && !$privacyConsentRequireComment && !$privacyConsentRequireAttachmentUpload) {
                $errors[] = '개인정보 수집 및 이용동의 적용 대상을 하나 이상 선택해 주세요.';
            }
        }
        if ($extraFieldDefinitionErrors !== []) {
            $errors = array_merge($errors, $extraFieldDefinitionErrors);
            $extraFieldsJson = '[]';
        }

        if ($errors === [] && $intent === 'create_group' && sr_community_board_group_by_key($pdo, $groupKey) !== null) {
            $errors[] = sr_t('community::action.admin.board_group_key_duplicate');
        }

        if ($errors === []) {
            $beforeAssetSettings = $intent === 'update_group'
                ? sr_community_board_group_asset_settings_from_storage_for_audit($pdo, $groupId)
                : [];

            if ($intent === 'create_group') {
                $groupId = sr_community_create_board_group($pdo, [
                    'group_key' => $groupKey,
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

            $applySettingKeys = [];
            $appliedBoardAssetAudits = [];
            $assetApplySettingKeys = array_values(array_intersect($applySettingKeys, sr_community_board_group_asset_setting_keys()));
            if ($intent === 'update_group' && $assetApplySettingKeys !== []) {
                $stmt = $pdo->prepare(
                    'SELECT id, board_key
                     FROM sr_community_boards
                     WHERE board_group_id = :group_id
                     ORDER BY id ASC'
                );
                $stmt->execute(['group_id' => $groupId]);
                foreach ($stmt->fetchAll() as $board) {
                    $boardId = (int) ($board['id'] ?? 0);
                    if ($boardId < 1) {
                        continue;
                    }

                    $appliedBoardAssetAudits[] = [
                        'id' => $boardId,
                        'board_key' => (string) ($board['board_key'] ?? ''),
                        'before_asset_settings' => sr_community_board_asset_settings_for_audit($pdo, $boardId),
                    ];
                }
            }

            sr_community_set_board_group_setting($pdo, $groupId, 'read_policy', $readPolicy);
            sr_community_set_board_group_setting($pdo, $groupId, 'write_policy', $writePolicy);
            sr_community_set_board_group_setting($pdo, $groupId, 'comment_policy', $commentPolicy);
            sr_community_set_board_group_setting($pdo, $groupId, 'post_editor', $postEditor);
            sr_community_set_board_group_setting($pdo, $groupId, 'image_uploads_enabled', $imageUploadsEnabled ? '1' : '0', 'bool');
            sr_community_set_board_group_setting($pdo, $groupId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'file_uploads_enabled', $fileUploadsEnabled ? '1' : '0', 'bool');
            sr_community_set_board_group_setting($pdo, $groupId, 'file_attachment_max_bytes', (string) $fileAttachmentMaxBytes, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'file_attachment_max_count', (string) $fileAttachmentMaxCount, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'file_allowed_extensions', implode(',', $fileAllowedExtensions), 'string');
            sr_community_set_board_group_setting($pdo, $groupId, 'read_group_keys', sr_community_board_group_keys_setting_value($readGroupKeys), 'json');
            sr_community_set_board_group_setting($pdo, $groupId, 'write_group_keys', sr_community_board_group_keys_setting_value($writeGroupKeys), 'json');
            sr_community_set_board_group_setting($pdo, $groupId, 'comment_group_keys', sr_community_board_group_keys_setting_value($commentGroupKeys), 'json');
            sr_community_set_board_group_setting($pdo, $groupId, 'read_min_level', (string) $readMinLevel, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'write_min_level', (string) $writeMinLevel, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'comment_min_level', (string) $commentMinLevel, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'level_post_score', (string) $levelPostScore, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'level_comment_score', (string) $levelCommentScore, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'post_edit_lock_comment_count', (string) $postEditLockCommentCount, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'post_delete_lock_comment_count', (string) $postDeleteLockCommentCount, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'post_body_min_length', (string) $postBodyMinLength, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'post_body_max_length', (string) $postBodyMaxLength, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'comment_body_min_length', (string) $commentBodyMinLength, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'comment_body_max_length', (string) $commentBodyMaxLength, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'list_excerpt_enabled', $listExcerptEnabled ? '1' : '0', 'bool');
            sr_community_set_board_group_setting($pdo, $groupId, 'list_excerpt_length', (string) $listExcerptLength, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'list_per_page', (string) $listPerPage, 'int');
            sr_community_set_board_group_setting($pdo, $groupId, 'list_default_sort', $listDefaultSort, 'string');
            sr_community_set_board_group_setting($pdo, $groupId, 'reaction_post_preset_key', $reactionPostPresetKey, 'string');
            sr_community_set_board_group_setting($pdo, $groupId, 'reaction_comment_preset_key', $reactionCommentPresetKey, 'string');
            sr_community_set_board_group_setting($pdo, $groupId, 'privacy_consent_enabled', $privacyConsentEnabled ? '1' : '0', 'bool');
            sr_community_set_board_group_setting($pdo, $groupId, 'privacy_consent_title', $privacyConsentTitle !== '' ? $privacyConsentTitle : '개인정보 수집 및 이용동의', 'string');
            sr_community_set_board_group_setting($pdo, $groupId, 'privacy_consent_body', $privacyConsentBody, 'string');
            sr_community_set_board_group_setting($pdo, $groupId, 'privacy_consent_version', $privacyConsentVersion !== '' ? $privacyConsentVersion : '1', 'string');
            sr_community_set_board_group_setting($pdo, $groupId, 'privacy_consent_require_post', $privacyConsentRequirePost ? '1' : '0', 'bool');
            sr_community_set_board_group_setting($pdo, $groupId, 'privacy_consent_require_comment', $privacyConsentRequireComment ? '1' : '0', 'bool');
            sr_community_set_board_group_setting($pdo, $groupId, 'privacy_consent_require_attachment_upload', $privacyConsentRequireAttachmentUpload ? '1' : '0', 'bool');
            sr_community_set_board_group_setting($pdo, $groupId, 'extra_fields_json', $extraFieldsJson, 'json');
            foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                sr_community_set_board_group_setting($pdo, $groupId, (string) $displaySettingKey, (string) $displaySettingValue, 'int');
            }
            foreach ($assetSettings as $assetSettingKey => $assetSettingValue) {
                $valueType = is_bool($assetSettingValue) ? 'bool' : (is_int($assetSettingValue) ? 'int' : 'string');
                $settingValue = is_bool($assetSettingValue) ? ($assetSettingValue ? '1' : '0') : (string) $assetSettingValue;
                sr_community_set_board_group_setting($pdo, $groupId, (string) $assetSettingKey, $settingValue, $valueType);
            }
            $extraFieldDefinitionSyncedBoardCount = sr_community_sync_group_board_field_definitions(
                $pdo,
                $groupId,
                sr_community_extra_field_definitions_from_json($extraFieldsJson)
            );

            $appliedBoardCount = 0;
            foreach ($appliedBoardAssetAudits as $appliedBoardAssetAudit) {
                $appliedBoardId = (int) ($appliedBoardAssetAudit['id'] ?? 0);
                if ($appliedBoardId < 1) {
                    continue;
                }

                sr_admin_audit_asset_settings_update($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.board.asset_settings.updated',
                    'target_type' => 'community_board',
                    'target_id' => (string) $appliedBoardId,
                    'asset_settings_scope' => 'community.board',
                    'before_asset_settings' => is_array($appliedBoardAssetAudit['before_asset_settings'] ?? null) ? $appliedBoardAssetAudit['before_asset_settings'] : [],
                    'after_asset_settings' => sr_community_board_asset_settings_for_audit($pdo, $appliedBoardId),
                    'message' => 'Community board asset settings updated.',
                    'metadata' => [
                        'board_key' => (string) ($appliedBoardAssetAudit['board_key'] ?? ''),
                        'source' => 'community_board_group',
                        'group_key' => $groupKey,
                        'applied_setting_keys' => $assetApplySettingKeys,
                    ],
                ]);
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
                    'status' => $status,
                    'applied_setting_keys' => $applySettingKeys,
                    'applied_board_count' => $appliedBoardCount,
                    'extra_field_definition_synced_board_count' => $extraFieldDefinitionSyncedBoardCount,
                ],
            ]);
            if ($intent === 'update_group') {
                sr_admin_audit_asset_settings_update($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.board_group.asset_settings.updated',
                    'target_type' => 'community_board_group',
                    'target_id' => (string) $groupId,
                    'asset_settings_scope' => 'community.board_group',
                    'before_asset_settings' => $beforeAssetSettings,
                    'after_asset_settings' => sr_community_asset_settings_for_audit($assetSettings),
                    'message' => 'Community board group asset settings updated.',
                    'metadata' => [
                        'group_key' => $groupKey,
                        'applied_setting_keys' => $applySettingKeys,
                        'applied_board_count' => $appliedBoardCount,
                    ],
                ]);
            }

            if ($intent === 'create_group') {
                sr_admin_flash_result(sr_admin_action_result([], $notice));
                sr_redirect('/admin/community/board-groups');
            }
        }
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
