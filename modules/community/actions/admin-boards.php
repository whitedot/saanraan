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
$assetModuleOptions = sr_community_asset_module_options($pdo);
$maxLevel = sr_community_max_level_value();
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

    if ($intent === 'update_skin') {
        $boardIdValue = sr_post_string('board_id', 20);
        $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
        $skinKey = sr_post_string('skin_key', 40);
        $board = sr_community_board_by_id($pdo, $boardId);
        if (!is_array($board)) {
            $errors[] = sr_t('community::action.error.board_not_found');
        }
        if (!isset($communitySkinOptions[$skinKey])) {
            $errors[] = sr_t('community::action.admin.board_skin_invalid');
            $skinKey = 'basic';
        }

        if ($errors === [] && is_array($board)) {
            $beforeSkinKey = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, $boardId, 'skin_key') ?? 'basic')]);
            sr_community_set_board_setting($pdo, $boardId, 'skin_key', $skinKey, 'string');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.board.skin_updated',
                'target_type' => 'community_board',
                'target_id' => (string) $boardId,
                'result' => 'success',
                'message' => 'Community board skin updated.',
                'metadata' => [
                    'board_key' => (string) $board['board_key'],
                    'before_skin_key' => $beforeSkinKey,
                    'after_skin_key' => $skinKey,
                ],
            ]);

            $notice = sr_t('community::action.admin.board_skin_saved');
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
            $assetSettings[$assetPrefix . '_enabled'] = ($_POST[$assetPrefix . '_enabled'] ?? '') === '1';
            $assetSettings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
                ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($_POST[$assetPrefix . '_asset_module'] ?? ''))
                : sr_community_asset_module_key(sr_post_string($assetPrefix . '_asset_module', 20));
            $assetSettings[$assetPrefix . '_amount'] = sr_admin_post_int_in_range($assetPrefix . '_amount', 0, 999999999);
        }
        $legacyAssetPolicySource = sr_community_asset_policy_source(sr_post_string('asset_policy_source', 20));
        $legacyAssetSettingSource = $legacyAssetPolicySource === 'global' ? 'all' : $legacyAssetPolicySource;
        $assetSettingSources = [];
        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $legacyPrefixSource = sr_post_string('source_' . $assetPrefix, 20);
            if ($legacyPrefixSource === '') {
                $legacyPrefixSource = $legacyAssetSettingSource;
            }
            foreach (sr_community_asset_prefix_setting_keys((string) $assetPrefix) as $settingKey) {
                $postedSource = sr_post_string('source_' . $settingKey, 20);
                $assetSettingSources[$settingKey] = $postedSource !== ''
                    ? sr_community_normalize_board_setting_source($postedSource)
                    : sr_community_normalize_board_setting_source($legacyPrefixSource);
            }
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

        foreach ($assetSettingLabels as $assetPrefix => $assetLabel) {
            if ($assetSettings[$assetPrefix . '_amount'] === null) {
                $errors[] = sr_t('community::action.admin.asset_amount_invalid', ['label' => $assetLabel]);
                $assetSettings[$assetPrefix . '_amount'] = 0;
            }

            if (($assetSettingSources[$assetPrefix] ?? 'board') === 'board' && !empty($assetSettings[$assetPrefix . '_enabled']) && (int) $assetSettings[$assetPrefix . '_amount'] > 0) {
                $assetModule = (string) $assetSettings[$assetPrefix . '_asset_module'];
                if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                    $assetModules = sr_community_asset_module_keys_from_value($assetModule);
                    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
                        $errors[] = sr_t('community::action.admin.asset_modules_required_active', ['label' => $assetLabel]);
                    }
                } elseif (!isset($assetModuleOptions[$assetModule])) {
                    $errors[] = sr_t('community::action.admin.asset_module_inactive', [
                        'label' => $assetLabel,
                        'module' => sr_community_asset_module_label($assetModule),
                    ]);
                }
            }
        }

        if ($errors === [] && $intent === 'create' && sr_community_board_by_key($pdo, $boardKey) !== null) {
            $errors[] = sr_t('community::action.admin.board_key_duplicate');
        }

        if ($errors === []) {
            $boardSettingValues = [
                'read_policy' => $readPolicy,
                'write_policy' => $writePolicy,
                'comment_policy' => $commentPolicy,
                'read_group_keys' => sr_community_board_group_keys_setting_value($readGroupKeys),
                'write_group_keys' => sr_community_board_group_keys_setting_value($writeGroupKeys),
                'comment_group_keys' => sr_community_board_group_keys_setting_value($commentGroupKeys),
                'read_min_level' => (string) $readMinLevel,
                'write_min_level' => (string) $writeMinLevel,
                'comment_min_level' => (string) $commentMinLevel,
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
                sr_community_set_board_setting($pdo, $boardId, 'level_post_score', (string) $levelPostScore, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'level_comment_score', (string) $levelCommentScore, 'int');
                sr_community_save_board_asset_settings($pdo, $boardId, $assetSettings);
                foreach ($boardSettingValues as $settingKey => $settingValue) {
                    sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, (string) ($settingSources[$settingKey] ?? 'board'), $settingValue);
                }
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
                        'before_level_post_score' => $beforeLevelPostScore,
                        'after_level_post_score' => $levelPostScore,
                        'before_level_comment_score' => $beforeLevelCommentScore,
                        'after_level_comment_score' => $levelCommentScore,
                        'before_skin_key' => $beforeSkinKey,
                        'after_skin_key' => $skinKey,
                        'before_asset_setting_sources' => $beforeAssetSettingSources,
                        'after_asset_setting_sources' => $assetSettingSources,
                        'before_asset_settings' => $beforeAssetSettings,
                        'after_asset_settings' => $assetSettings,
                        'setting_sources' => $settingSources,
                    ], $publicDisplayMetadata),
                ]);

                $notice = sr_t('community::action.admin.board_updated');
            }
        }
    } else {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }
}

$boardGroups = sr_community_board_groups($pdo);
$boards = sr_community_boards($pdo);
foreach ($boards as &$board) {
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
    $board['effective_read_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'read_min_level');
    $board['effective_write_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'write_min_level');
    $board['effective_comment_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'comment_min_level');
    $board['level_post_score'] = sr_community_board_own_level_score($pdo, (int) $board['id'], 'level_post_score', $settings);
    $board['level_comment_score'] = sr_community_board_own_level_score($pdo, (int) $board['id'], 'level_comment_score', $settings);
    $board['effective_level_post_score'] = sr_community_board_level_score($pdo, (int) $board['id'], 'level_post_score', $settings);
    $board['effective_level_comment_score'] = sr_community_board_level_score($pdo, (int) $board['id'], 'level_comment_score', $settings);
    $board['skin_key'] = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'skin_key') ?? 'basic')]);
    foreach (sr_community_asset_setting_keys() as $assetSettingKey) {
        $board['source_' . $assetSettingKey] = sr_community_board_asset_setting_key_source($pdo, (int) $board['id'], (string) $assetSettingKey);
    }
    foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
        $board[$assetPrefix . '_enabled'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_enabled', !empty($settings[$assetPrefix . '_enabled']) ? '1' : '0');
        $board[$assetPrefix . '_asset_module'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_asset_module', (string) ($settings[$assetPrefix . '_asset_module'] ?? 'point'));
        $board[$assetPrefix . '_amount'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_amount', (string) ($settings[$assetPrefix . '_amount'] ?? 0));
        if (in_array($assetPrefix, ['paid_read', 'paid_attachment_download'], true)) {
            $board[$assetPrefix . '_charge_policy'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_charge_policy', (string) ($settings[$assetPrefix . '_charge_policy'] ?? 'once'));
        }
    }
}
unset($board);

$boardStatusCounts = ['total' => 0];
foreach ($allowedStatuses as $status) {
    $boardStatusCounts[$status] = 0;
}
foreach ($boards as $board) {
    $status = (string) ($board['status'] ?? '');
    if (array_key_exists($status, $boardStatusCounts)) {
        $boardStatusCounts[$status]++;
    }
    $boardStatusCounts['total']++;
}

if ($communityBoardsPage === 'list') {
    $boards = array_values(array_filter($boards, static function (array $board) use ($boardListFilters): bool {
        if ((string) $boardListFilters['status'] !== '' && (string) ($board['status'] ?? '') !== (string) $boardListFilters['status']) {
            return false;
        }

        if ((int) $boardListFilters['group_id'] > 0 && (int) ($board['board_group_id'] ?? 0) !== (int) $boardListFilters['group_id']) {
            return false;
        }

        $keyword = trim((string) $boardListFilters['q']);
        if ($keyword === '') {
            return true;
        }

        $field = (string) $boardListFilters['field'];
        $haystacks = [];
        if ($field === 'key') {
            $haystacks[] = (string) ($board['board_key'] ?? '');
        } elseif ($field === 'title') {
            $haystacks[] = (string) ($board['title'] ?? '');
        } elseif ($field === 'group') {
            $haystacks[] = (string) ($board['board_group_title'] ?? '');
            $haystacks[] = (string) ($board['board_group_key'] ?? '');
        } else {
            $haystacks[] = (string) ($board['board_key'] ?? '');
            $haystacks[] = (string) ($board['title'] ?? '');
            $haystacks[] = (string) ($board['description'] ?? '');
            $haystacks[] = (string) ($board['board_group_title'] ?? '');
            $haystacks[] = (string) ($board['board_group_key'] ?? '');
        }

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && stripos($haystack, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }));
}

$editBoard = null;
if ($communityBoardsPage === 'edit') {
    $editBoardIdValue = isset($_GET['edit_id']) ? (string) $_GET['edit_id'] : '';
    $editBoardId = preg_match('/\A[1-9][0-9]*\z/', $editBoardIdValue) === 1 ? (int) $editBoardIdValue : 0;
    foreach ($boards as $board) {
        if ((int) $board['id'] === $editBoardId) {
            $editBoard = $board;
            break;
        }
    }

    if (!is_array($editBoard)) {
        sr_render_error(404, sr_t('community::action.error.board_not_found'));
    }
}

include SR_ROOT . '/modules/community/views/admin-boards.php';
