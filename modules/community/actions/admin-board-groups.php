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
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/board-groups', 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$communityBoardGroupsPage = isset($communityBoardGroupsPage) ? (string) $communityBoardGroupsPage : 'list';
if (!in_array($communityBoardGroupsPage, ['list', 'new', 'edit'], true)) {
    $communityBoardGroupsPage = 'list';
}
$allowedGroupStatuses = sr_community_board_group_statuses();
$allowedReadPolicies = sr_community_policy_values('read');
$allowedWritePolicies = sr_community_policy_values('write');
$allowedCommentPolicies = sr_community_policy_values('comment');
$maxLevel = sr_community_max_level_value();
$settings = sr_community_settings($pdo);
$assetModuleOptions = sr_community_asset_module_options($pdo);
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
    'status' => sr_get_string('status', 30),
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
if ($boardGroupListFilters['status'] !== '' && !in_array($boardGroupListFilters['status'], $allowedGroupStatuses, true)) {
    $boardGroupListFilters['status'] = '';
}
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
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/board-groups', 'edit');

    $intent = sr_post_string('intent', 40);

    if (in_array($intent, ['create_group', 'update_group'], true)) {
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
        $publicDisplaySettingValues = [];
        foreach ($publicDisplaySettingLabels as $displaySettingKey => $displaySettingLabel) {
            $publicDisplaySettingValues[$displaySettingKey] = sr_admin_post_int_in_range('group_' . $displaySettingKey, 0, 999999999);
        }
        $assetSettings = [];
        foreach (['post_reward', 'comment_reward', 'write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'] as $assetPrefix) {
            $assetSettings[$assetPrefix . '_enabled'] = ($_POST['group_' . $assetPrefix . '_enabled'] ?? '') === '1';
            $assetSettings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
                ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($_POST['group_' . $assetPrefix . '_asset_module'] ?? ''))
                : sr_community_asset_module_key(sr_post_string('group_' . $assetPrefix . '_asset_module', 20));
            $assetSettings[$assetPrefix . '_amount'] = sr_admin_post_int_in_range('group_' . $assetPrefix . '_amount', 0, 999999999);
        }
        $assetSettings['paid_read_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('group_paid_read_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('group_paid_attachment_download_charge_policy', 20), 'once');
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
                    $assetModules = sr_community_asset_module_keys_from_value($assetModule);
                    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
                        $errors[] = sr_t('community::action.admin.board_group_asset_modules_required_active', ['label' => $assetLabel]);
                    }
                } elseif (!isset($assetModuleOptions[$assetModule])) {
                    $errors[] = sr_t('community::action.admin.board_group_asset_module_inactive', [
                        'label' => $assetLabel,
                        'module' => sr_community_asset_module_label($assetModule),
                    ]);
                }
            }
        }

        if ($errors === [] && $intent === 'create_group' && sr_community_board_group_by_key($pdo, $groupKey) !== null) {
            $errors[] = sr_t('community::action.admin.board_group_key_duplicate');
        }

        if ($errors === []) {
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

            sr_community_set_board_group_setting($pdo, $groupId, 'read_policy', $readPolicy);
            sr_community_set_board_group_setting($pdo, $groupId, 'write_policy', $writePolicy);
            sr_community_set_board_group_setting($pdo, $groupId, 'comment_policy', $commentPolicy);
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
            foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                sr_community_set_board_group_setting($pdo, $groupId, (string) $displaySettingKey, (string) $displaySettingValue, 'int');
            }
            foreach ($assetSettings as $assetSettingKey => $assetSettingValue) {
                $valueType = is_bool($assetSettingValue) ? 'bool' : (is_int($assetSettingValue) ? 'int' : 'string');
                $settingValue = is_bool($assetSettingValue) ? ($assetSettingValue ? '1' : '0') : (string) $assetSettingValue;
                sr_community_set_board_group_setting($pdo, $groupId, (string) $assetSettingKey, $settingValue, $valueType);
            }

            $applySettingKeys = [];
            if (isset($_POST['apply_setting_keys']) && is_array($_POST['apply_setting_keys'])) {
                foreach ($_POST['apply_setting_keys'] as $settingKey) {
                    $settingKey = (string) $settingKey;
                    if (in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
                        $applySettingKeys[] = $settingKey;
                    }
                }
            }
            $applySettingKeys = array_values(array_unique($applySettingKeys));
            $appliedBoardCount = 0;
            if ($applySettingKeys !== []) {
                $appliedBoardCount = sr_community_apply_board_group_settings_to_boards($pdo, $groupId, $applySettingKeys);
                $notice .= sr_t('community::action.admin.board_group_settings_applied', ['count' => (string) $appliedBoardCount]);
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
                ],
            ]);

            if ($intent === 'create_group') {
                sr_admin_flash_result(sr_admin_action_result([], $notice));
                sr_redirect('/admin/community/board-groups');
            }
        }
    } else {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }
}

$boardGroups = sr_community_board_groups($pdo);
$boardGroupStatusCounts = ['total' => 0];
foreach ($allowedGroupStatuses as $status) {
    $boardGroupStatusCounts[$status] = 0;
}
foreach ($boardGroups as $boardGroup) {
    $status = (string) ($boardGroup['status'] ?? '');
    if (array_key_exists($status, $boardGroupStatusCounts)) {
        $boardGroupStatusCounts[$status]++;
    }
    $boardGroupStatusCounts['total']++;
}
if ($communityBoardGroupsPage === 'list') {
    $boardGroups = array_values(array_filter($boardGroups, static function (array $boardGroup) use ($boardGroupListFilters): bool {
        if ((string) $boardGroupListFilters['status'] !== '' && (string) ($boardGroup['status'] ?? '') !== (string) $boardGroupListFilters['status']) {
            return false;
        }

        $keyword = trim((string) $boardGroupListFilters['q']);
        if ($keyword === '') {
            return true;
        }

        $field = (string) $boardGroupListFilters['field'];
        $haystacks = [];
        if ($field === 'key') {
            $haystacks[] = (string) ($boardGroup['group_key'] ?? '');
        } elseif ($field === 'title') {
            $haystacks[] = (string) ($boardGroup['title'] ?? '');
        } else {
            $haystacks[] = (string) ($boardGroup['group_key'] ?? '');
            $haystacks[] = (string) ($boardGroup['title'] ?? '');
            $haystacks[] = (string) ($boardGroup['description'] ?? '');
        }

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && stripos($haystack, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }));
}
$boardGroupSettings = [];
foreach ($boardGroups as $boardGroup) {
    $boardGroupSettings[(int) $boardGroup['id']] = sr_community_board_group_settings($pdo, (int) $boardGroup['id']);
}

$editBoardGroup = null;
if ($communityBoardGroupsPage === 'edit') {
    $editGroupIdValue = isset($_GET['edit_id']) ? (string) $_GET['edit_id'] : '';
    $editGroupId = preg_match('/\A[1-9][0-9]*\z/', $editGroupIdValue) === 1 ? (int) $editGroupIdValue : 0;
    foreach ($boardGroups as $boardGroup) {
        if ((int) $boardGroup['id'] === $editGroupId) {
            $editBoardGroup = $boardGroup;
            break;
        }
    }

    if (!is_array($editBoardGroup)) {
        sr_render_error(404, sr_t('community::action.error.board_group_not_found'));
    }
}

include SR_ROOT . '/modules/community/views/admin-board-groups.php';
