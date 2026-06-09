<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_community_settings($pdo);
$communitySettingsPage = isset($communitySettingsPage) ? (string) $communitySettingsPage : 'settings';
$communitySettingsPermissionPath = $communitySettingsPage === 'levels' ? '/admin/community/levels' : '/admin/community/settings';
sr_admin_require_permission($pdo, (int) $account['id'], $communitySettingsPermissionPath, 'view');
$communityLayoutOptions = sr_public_layout_options($pdo);
$editorOptions = sr_editor_options($pdo);
$siteMenuOptions = [];
if (sr_module_enabled($pdo, 'site_menu') && is_file(SR_ROOT . '/modules/site_menu/helpers.php')) {
    require_once SR_ROOT . '/modules/site_menu/helpers.php';
    $siteMenuOptions = sr_site_menu_options($pdo);
}
$assetModuleOptions = sr_community_asset_module_options($pdo);
$assetPolicySets = sr_community_asset_policy_sets($pdo);
$levels = sr_community_levels($pdo, $settings);
$maxLevel = sr_community_max_level_value($settings);
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
    sr_admin_require_permission($pdo, (int) $account['id'], $communitySettingsPermissionPath, 'edit');

    $intent = sr_post_string('intent', 40);

    if ($intent === 'save_settings') {
        $levelEnabled = ($_POST['level_enabled'] ?? '') === '1';
        $levelMaxValue = sr_admin_post_int_in_range('level_max_value', 1, 100);
        $levelAutoRecalculate = ($_POST['level_auto_recalculate'] ?? '') === '1';
        $levelPostScore = sr_admin_post_int_in_range('level_post_score', 0, 10000);
        $levelCommentScore = sr_admin_post_int_in_range('level_comment_score', 0, 10000);
        $messageWritePolicy = sr_community_message_write_policy(sr_post_string('message_write_policy', 40));
        $levelMaxForValidation = $levelMaxValue !== null ? $levelMaxValue : $maxLevel;
        $messageWriteMinLevel = sr_admin_post_int_in_range('message_write_min_level', 0, $levelMaxForValidation);
        $postEditorInput = sr_post_string('post_editor', 30);
        $postEditor = sr_community_post_editor_key($postEditorInput);
        $plainTextAutoLinkUrls = ($_POST['plain_text_auto_link_urls'] ?? '') === '1';
        $secretPostsEnabled = ($_POST['secret_posts_enabled'] ?? '') === '1';
        $secretCommentsEnabled = ($_POST['secret_comments_enabled'] ?? '') === '1';
        $messageWriteGroupKeysInput = $_POST['message_write_group_keys'] ?? [];
        $messageWriteGroupKeys = sr_community_board_group_keys_from_input_value($messageWriteGroupKeysInput);
        $onceHistoryPolicyInput = sr_post_string('once_history_policy', 40);
        $onceHistoryPolicy = sr_community_once_history_policy($onceHistoryPolicyInput);
        $layoutKey = sr_public_layout_normalize_key(sr_post_string('layout_key', 80));
        $layoutPrimaryMenuKey = sr_community_clean_layout_menu_key(sr_post_string('layout_primary_menu_key', 60));
        $layoutSecondaryMenuKey = sr_community_clean_layout_menu_key(sr_post_string('layout_secondary_menu_key', 60));
        $layoutTertiaryMenuKey = sr_community_clean_layout_menu_key(sr_post_string('layout_tertiary_menu_key', 60));
        $layoutQuaternaryMenuKey = sr_community_clean_layout_menu_key(sr_post_string('layout_quaternary_menu_key', 60));
        $layoutQuinaryMenuKey = sr_community_clean_layout_menu_key(sr_post_string('layout_quinary_menu_key', 60));
        $assetSettings = [];
        foreach (['post_reward', 'comment_reward', 'write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'] as $assetPrefix) {
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
        $assetSettings['post_reward_reversal_enabled'] = ($_POST['post_reward_reversal_enabled'] ?? '') === '1';
        $assetSettings['comment_reward_reversal_enabled'] = ($_POST['comment_reward_reversal_enabled'] ?? '') === '1';
        $assetSettings['paid_read_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_read_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_attachment_download_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_publisher_reward_enabled'] = ($_POST['paid_attachment_download_publisher_reward_enabled'] ?? '') === '1';
        $assetSettings['paid_attachment_download_publisher_reward_rate'] = sr_admin_post_int_in_range('paid_attachment_download_publisher_reward_rate', 0, 100);
        $beforeAssetSettings = sr_community_asset_settings_for_audit($settings, true);

        if ($levelPostScore === null) {
            $errors[] = sr_t('community::action.admin.post_score_invalid');
            $levelPostScore = (int) $settings['level_post_score'];
        }

        if ($levelMaxValue === null) {
            $errors[] = sr_t('community::action.admin.level_max_value_invalid');
            $levelMaxValue = (int) $settings['level_max_value'];
        }

        if ($levelCommentScore === null) {
            $errors[] = sr_t('community::action.admin.comment_score_invalid');
            $levelCommentScore = (int) $settings['level_comment_score'];
        }

        $levelMaxChanged = $levelMaxValue !== (int) $settings['level_max_value'];
        if ($levelMaxChanged && (
            sr_post_string('level_max_change_confirmed', 1) !== '1'
            || sr_post_string('level_max_change_confirm_text', 40) !== sr_t('community::ui.level_max_change_confirmation_text')
        )) {
            $errors[] = sr_t('community::action.admin.level_max_change_confirmation_required');
        }

        if ($messageWriteMinLevel === null) {
            $errors[] = sr_t('community::action.admin.message_min_level_invalid', ['max' => (string) $levelMaxForValidation]);
            $messageWriteMinLevel = (int) $settings['message_write_min_level'];
        }

        if (!isset($communityLayoutOptions[$layoutKey])) {
            $errors[] = sr_t('community::action.admin.layout_invalid');
            $layoutKey = sr_community_layout_key($settings, $site ?? null, $pdo);
        }
        foreach ([$layoutPrimaryMenuKey, $layoutSecondaryMenuKey, $layoutTertiaryMenuKey, $layoutQuaternaryMenuKey, $layoutQuinaryMenuKey] as $layoutMenuKey) {
            if ($layoutMenuKey !== '' && !isset($siteMenuOptions[$layoutMenuKey])) {
                $errors[] = '레이아웃 사이트 메뉴 값이 올바르지 않습니다.';
                break;
            }
        }
        if ($postEditorInput !== $postEditor || !array_key_exists($postEditor, $editorOptions)) {
            $errors[] = '게시글 에디터 값이 올바르지 않습니다.';
            $postEditor = (string) ($settings['post_editor'] ?? 'textarea');
        }
        if ($onceHistoryPolicyInput !== $onceHistoryPolicy) {
            $errors[] = sr_t('community::action.admin.once_history_policy_invalid');
            $onceHistoryPolicy = (string) ($settings['once_history_policy'] ?? 'all_access');
        }

        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $assetLabel = sr_community_asset_setting_label($assetPrefix);
            if ($assetSettings[$assetPrefix . '_amount'] === null) {
                $errors[] = sr_t('community::action.admin.asset_amount_invalid', ['label' => $assetLabel]);
                $assetSettings[$assetPrefix . '_amount'] = 0;
            }

            if (!empty($assetSettings[$assetPrefix . '_enabled']) && (int) $assetSettings[$assetPrefix . '_amount'] > 0) {
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
        if ($assetSettings['paid_attachment_download_publisher_reward_rate'] === null) {
            $errors[] = '첨부 다운로드 게시자 리워드 지급률이 올바르지 않습니다.';
            $assetSettings['paid_attachment_download_publisher_reward_rate'] = 0;
        }

        if (sr_community_board_group_keys_input_too_long($messageWriteGroupKeysInput)) {
            $errors[] = sr_t('community::action.admin.message_group_list_too_long');
        } else {
            $invalidGroupKeys = sr_community_invalid_board_group_keys_from_input_value($messageWriteGroupKeysInput);
            if ($invalidGroupKeys !== []) {
                $errors[] = sr_t('community::action.admin.message_group_keys_invalid', ['keys' => implode(', ', $invalidGroupKeys)]);
            }
        }

        $unknownGroupKeys = array_values(array_diff($messageWriteGroupKeys, $enabledMemberGroupKeys));
        if ($unknownGroupKeys !== []) {
            $errors[] = sr_t('community::action.admin.message_group_keys_inactive', ['keys' => implode(', ', $unknownGroupKeys)]);
        }

        if ($errors === []) {
            $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'community' LIMIT 1");
            $stmt->execute();
            $communityModule = $stmt->fetch();
            if (!is_array($communityModule)) {
                $errors[] = sr_t('community::action.admin.module_missing');
            }
        }

        if ($errors === [] && is_array($communityModule ?? null)) {
            $rows = [
                ['level_enabled', $levelEnabled ? '1' : '0', 'bool'],
                ['level_max_value', (string) $levelMaxValue, 'int'],
                ['level_auto_recalculate', $levelAutoRecalculate ? '1' : '0', 'bool'],
                ['level_post_score', (string) $levelPostScore, 'int'],
                ['level_comment_score', (string) $levelCommentScore, 'int'],
                ['message_write_policy', $messageWritePolicy, 'string'],
                ['message_write_group_keys', sr_community_board_group_keys_setting_value($messageWriteGroupKeys), 'json'],
                ['message_write_min_level', (string) $messageWriteMinLevel, 'int'],
                ['theme_key', 'basic', 'string'],
                ['layout_key', $layoutKey, 'string'],
                ['layout_primary_menu_key', $layoutPrimaryMenuKey, 'string'],
                ['layout_secondary_menu_key', $layoutSecondaryMenuKey, 'string'],
                ['layout_tertiary_menu_key', $layoutTertiaryMenuKey, 'string'],
                ['layout_quaternary_menu_key', $layoutQuaternaryMenuKey, 'string'],
                ['layout_quinary_menu_key', $layoutQuinaryMenuKey, 'string'],
                ['post_editor', $postEditor, 'string'],
                ['plain_text_auto_link_urls', $plainTextAutoLinkUrls ? '1' : '0', 'bool'],
                ['secret_posts_enabled', $secretPostsEnabled ? '1' : '0', 'bool'],
                ['secret_comments_enabled', $secretCommentsEnabled ? '1' : '0', 'bool'],
                ['post_reward_enabled', $assetSettings['post_reward_enabled'] ? '1' : '0', 'bool'],
                ['post_reward_asset_module', (string) $assetSettings['post_reward_asset_module'], 'string'],
                ['post_reward_amount', (string) $assetSettings['post_reward_amount'], 'int'],
                ['post_reward_group_policies_json', (string) $assetSettings['post_reward_group_policies_json'], 'json'],
                ['post_reward_policy_set_id', (string) $assetSettings['post_reward_policy_set_id'], 'int'],
                ['post_reward_reversal_enabled', $assetSettings['post_reward_reversal_enabled'] ? '1' : '0', 'bool'],
                ['comment_reward_enabled', $assetSettings['comment_reward_enabled'] ? '1' : '0', 'bool'],
                ['comment_reward_asset_module', (string) $assetSettings['comment_reward_asset_module'], 'string'],
                ['comment_reward_amount', (string) $assetSettings['comment_reward_amount'], 'int'],
                ['comment_reward_group_policies_json', (string) $assetSettings['comment_reward_group_policies_json'], 'json'],
                ['comment_reward_policy_set_id', (string) $assetSettings['comment_reward_policy_set_id'], 'int'],
                ['comment_reward_reversal_enabled', $assetSettings['comment_reward_reversal_enabled'] ? '1' : '0', 'bool'],
                ['write_charge_enabled', $assetSettings['write_charge_enabled'] ? '1' : '0', 'bool'],
                ['write_charge_asset_module', (string) $assetSettings['write_charge_asset_module'], 'string'],
                ['write_charge_amount', (string) $assetSettings['write_charge_amount'], 'int'],
                ['write_charge_amounts_json', (string) $assetSettings['write_charge_amounts_json'], 'json'],
                ['write_charge_group_policies_json', (string) $assetSettings['write_charge_group_policies_json'], 'json'],
                ['write_charge_policy_set_id', (string) $assetSettings['write_charge_policy_set_id'], 'int'],
                ['comment_charge_enabled', $assetSettings['comment_charge_enabled'] ? '1' : '0', 'bool'],
                ['comment_charge_asset_module', (string) $assetSettings['comment_charge_asset_module'], 'string'],
                ['comment_charge_amount', (string) $assetSettings['comment_charge_amount'], 'int'],
                ['comment_charge_amounts_json', (string) $assetSettings['comment_charge_amounts_json'], 'json'],
                ['comment_charge_group_policies_json', (string) $assetSettings['comment_charge_group_policies_json'], 'json'],
                ['comment_charge_policy_set_id', (string) $assetSettings['comment_charge_policy_set_id'], 'int'],
                ['paid_read_enabled', $assetSettings['paid_read_enabled'] ? '1' : '0', 'bool'],
                ['paid_read_asset_module', (string) $assetSettings['paid_read_asset_module'], 'string'],
                ['paid_read_amount', (string) $assetSettings['paid_read_amount'], 'int'],
                ['paid_read_amounts_json', (string) $assetSettings['paid_read_amounts_json'], 'json'],
                ['paid_read_group_policies_json', (string) $assetSettings['paid_read_group_policies_json'], 'json'],
                ['paid_read_policy_set_id', (string) $assetSettings['paid_read_policy_set_id'], 'int'],
                ['paid_read_charge_policy', (string) $assetSettings['paid_read_charge_policy'], 'string'],
                ['once_history_policy', $onceHistoryPolicy, 'string'],
                ['paid_attachment_download_enabled', $assetSettings['paid_attachment_download_enabled'] ? '1' : '0', 'bool'],
                ['paid_attachment_download_asset_module', (string) $assetSettings['paid_attachment_download_asset_module'], 'string'],
                ['paid_attachment_download_amount', (string) $assetSettings['paid_attachment_download_amount'], 'int'],
                ['paid_attachment_download_amounts_json', (string) $assetSettings['paid_attachment_download_amounts_json'], 'json'],
                ['paid_attachment_download_group_policies_json', (string) $assetSettings['paid_attachment_download_group_policies_json'], 'json'],
                ['paid_attachment_download_policy_set_id', (string) $assetSettings['paid_attachment_download_policy_set_id'], 'int'],
                ['paid_attachment_download_charge_policy', (string) $assetSettings['paid_attachment_download_charge_policy'], 'string'],
                ['paid_attachment_download_publisher_reward_enabled', $assetSettings['paid_attachment_download_publisher_reward_enabled'] ? '1' : '0', 'bool'],
                ['paid_attachment_download_publisher_reward_rate', (string) $assetSettings['paid_attachment_download_publisher_reward_rate'], 'int'],
            ];
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO sr_module_settings
                        (module_id, setting_key, setting_value, value_type, created_at, updated_at)
                     VALUES
                        (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
                     ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value),
                        value_type = VALUES(value_type),
                        updated_at = VALUES(updated_at)'
                );
                $now = sr_now();
                foreach ($rows as $row) {
                    $stmt->execute([
                        'module_id' => (int) $communityModule['id'],
                        'setting_key' => $row[0],
                        'setting_value' => $row[1],
                        'value_type' => $row[2],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $createdLevelCount = sr_community_ensure_level_definitions($pdo, (int) $levelMaxValue);
                $stmt = $pdo->prepare('DELETE FROM sr_module_settings WHERE module_id = :module_id AND setting_key = :setting_key');
                $stmt->execute([
                    'module_id' => (int) $communityModule['id'],
                    'setting_key' => 'access_condition_priority',
                ]);
                $pdo->commit();
                sr_clear_module_settings_cache('community');
                $settings = sr_community_settings($pdo);

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.settings.updated',
                    'target_type' => 'module',
                    'target_id' => 'community',
                    'result' => 'success',
                    'message' => 'Community settings updated.',
                    'metadata' => [
                        'level_enabled' => $levelEnabled,
                        'level_max_value' => $levelMaxValue,
                        'created_level_count' => $createdLevelCount,
                        'level_auto_recalculate' => $levelAutoRecalculate,
                        'message_write_policy' => $messageWritePolicy,
                        'message_write_min_level' => $messageWriteMinLevel,
                        'layout_key' => $layoutKey,
                        'layout_primary_menu_key' => $layoutPrimaryMenuKey,
                        'layout_secondary_menu_key' => $layoutSecondaryMenuKey,
                        'layout_tertiary_menu_key' => $layoutTertiaryMenuKey,
                        'layout_quaternary_menu_key' => $layoutQuaternaryMenuKey,
                        'layout_quinary_menu_key' => $layoutQuinaryMenuKey,
                        'post_editor' => $postEditor,
                        'plain_text_auto_link_urls' => $plainTextAutoLinkUrls,
                        'secret_posts_enabled' => $secretPostsEnabled,
                        'secret_comments_enabled' => $secretCommentsEnabled,
                        'once_history_policy' => $onceHistoryPolicy,
                        'asset_settings' => $assetSettings,
                    ],
                ]);
                sr_admin_audit_asset_settings_update($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.settings.asset_settings.updated',
                    'target_type' => 'module',
                    'target_id' => 'community',
                    'asset_settings_scope' => 'community.settings',
                    'before_asset_settings' => $beforeAssetSettings,
                    'after_asset_settings' => sr_community_asset_settings_for_audit($assetSettings, true),
                    'message' => 'Community asset settings updated.',
                ]);

                $notice = $createdLevelCount > 0
                    ? sr_t('community::action.admin.settings_saved_levels_created', ['count' => (string) $createdLevelCount])
                    : sr_t('community::action.admin.settings_saved');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                sr_log_exception($exception, 'community_settings_save_failed');
                $errors[] = sr_t('community::action.admin.settings_save_failed');
            }
        }
    } elseif ($intent === 'save_level_settings') {
        $levelEnabled = ($_POST['level_enabled'] ?? '') === '1';
        $levelAutoRecalculate = ($_POST['level_auto_recalculate'] ?? '') === '1';
        $levelPostScore = sr_admin_post_int_in_range('level_post_score', 0, 10000);
        $levelCommentScore = sr_admin_post_int_in_range('level_comment_score', 0, 10000);

        if ($levelPostScore === null) {
            $errors[] = sr_t('community::action.admin.post_score_invalid');
            $levelPostScore = (int) $settings['level_post_score'];
        }
        if ($levelCommentScore === null) {
            $errors[] = sr_t('community::action.admin.comment_score_invalid');
            $levelCommentScore = (int) $settings['level_comment_score'];
        }
        if ($errors === []) {
            $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'community' LIMIT 1");
            $stmt->execute();
            $communityModule = $stmt->fetch();
            if (!is_array($communityModule)) {
                $errors[] = sr_t('community::action.admin.module_missing');
            }
        }
        if ($errors === [] && is_array($communityModule ?? null)) {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_module_settings
                    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
                 VALUES
                    (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    value_type = VALUES(value_type),
                    updated_at = VALUES(updated_at)'
            );
            $now = sr_now();
            foreach ([
                ['level_enabled', $levelEnabled ? '1' : '0', 'bool'],
                ['level_auto_recalculate', $levelAutoRecalculate ? '1' : '0', 'bool'],
                ['level_post_score', (string) $levelPostScore, 'int'],
                ['level_comment_score', (string) $levelCommentScore, 'int'],
            ] as $row) {
                $stmt->execute([
                    'module_id' => (int) $communityModule['id'],
                    'setting_key' => $row[0],
                    'setting_value' => $row[1],
                    'value_type' => $row[2],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            sr_clear_module_settings_cache('community');
            $settings = sr_community_settings($pdo);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.level_settings.updated',
                'target_type' => 'module',
                'target_id' => 'community',
                'result' => 'success',
                'message' => 'Community level settings updated.',
                'metadata' => [
                    'level_enabled' => $levelEnabled,
                    'level_auto_recalculate' => $levelAutoRecalculate,
                    'level_post_score' => $levelPostScore,
                    'level_comment_score' => $levelCommentScore,
                ],
            ]);
            $notice = sr_t('community::action.admin.level_settings_saved');
        }
    } elseif ($intent === 'save_level_definitions') {
        $levelSettingsSubmitted = array_key_exists('level_post_score', $_POST) || array_key_exists('level_comment_score', $_POST);
        $levelEnabled = !empty($settings['level_enabled']);
        $levelAutoRecalculate = !empty($settings['level_auto_recalculate']);
        $levelPostScore = (int) $settings['level_post_score'];
        $levelCommentScore = (int) $settings['level_comment_score'];
        if ($levelSettingsSubmitted) {
            $levelEnabled = ($_POST['level_enabled'] ?? '') === '1';
            $levelAutoRecalculate = ($_POST['level_auto_recalculate'] ?? '') === '1';
            $postedLevelPostScore = sr_admin_post_int_in_range('level_post_score', 0, 10000);
            $postedLevelCommentScore = sr_admin_post_int_in_range('level_comment_score', 0, 10000);
            if ($postedLevelPostScore === null) {
                $errors[] = sr_t('community::action.admin.post_score_invalid');
            } else {
                $levelPostScore = $postedLevelPostScore;
            }
            if ($postedLevelCommentScore === null) {
                $errors[] = sr_t('community::action.admin.comment_score_invalid');
            } else {
                $levelCommentScore = $postedLevelCommentScore;
            }
        }
        $levelMaxValue = sr_admin_post_int_in_range('level_max_value', 1, 100);
        if ($levelMaxValue === null) {
            $errors[] = sr_t('community::action.admin.level_max_value_invalid');
            $levelMaxValue = (int) $settings['level_max_value'];
        }
        $levelMaxChanged = $levelMaxValue !== (int) $settings['level_max_value'];
        if ($levelMaxChanged && (
            sr_post_string('level_max_change_confirmed', 1) !== '1'
            || sr_post_string('level_max_change_confirm_text', 40) !== sr_t('community::ui.level_max_change_confirmation_text')
        )) {
            $errors[] = sr_t('community::action.admin.level_max_change_confirmation_required');
        }
        if ($errors === []) {
            $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'community' LIMIT 1");
            $stmt->execute();
            $communityModule = $stmt->fetch();
            if (!is_array($communityModule)) {
                $errors[] = sr_t('community::action.admin.module_missing');
            }
        }
        if ($errors === [] && is_array($communityModule ?? null)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO sr_module_settings
                        (module_id, setting_key, setting_value, value_type, created_at, updated_at)
                     VALUES
                        (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
                     ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value),
                        value_type = VALUES(value_type),
                        updated_at = VALUES(updated_at)'
                );
                $now = sr_now();
                $stmt->execute([
                    'module_id' => (int) $communityModule['id'],
                    'setting_key' => 'level_max_value',
                    'setting_value' => (string) $levelMaxValue,
                    'value_type' => 'int',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                if ($levelSettingsSubmitted) {
                    foreach ([
                        ['level_enabled', $levelEnabled ? '1' : '0', 'bool'],
                        ['level_auto_recalculate', $levelAutoRecalculate ? '1' : '0', 'bool'],
                        ['level_post_score', (string) $levelPostScore, 'int'],
                        ['level_comment_score', (string) $levelCommentScore, 'int'],
                    ] as $row) {
                        $stmt->execute([
                            'module_id' => (int) $communityModule['id'],
                            'setting_key' => $row[0],
                            'setting_value' => $row[1],
                            'value_type' => $row[2],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
                $createdLevelCount = sr_community_ensure_level_definitions($pdo, (int) $levelMaxValue);
                $pdo->commit();
                sr_clear_module_settings_cache('community');
                $settings = sr_community_settings($pdo);
                $levels = sr_community_levels($pdo, $settings);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                sr_log_exception($exception, 'community_level_max_save_failed');
                $errors[] = sr_t('community::action.admin.settings_save_failed');
            }
        }

        $rawMinScores = $_POST['level_min_score'] ?? [];
        if (!is_array($rawMinScores)) {
            $errors[] = sr_t('community::action.admin.level_min_score_input_invalid');
        }

        $minScoresById = [];
        foreach ($levels as $level) {
            $levelId = (int) ($level['id'] ?? 0);
            if ($levelId < 1) {
                continue;
            }

            $rawValue = is_array($rawMinScores) && array_key_exists((string) $levelId, $rawMinScores)
                ? $rawMinScores[(string) $levelId]
                : (string) ($level['min_score'] ?? '0');
            if (is_array($rawValue)) {
                $errors[] = sr_t('community::action.admin.level_min_score_input_invalid');
                continue;
            }

            $value = trim((string) $rawValue);
            if ($value === '' || strlen($value) > 10 || preg_match('/\A\d+\z/', $value) !== 1) {
                $errors[] = sr_t('community::action.admin.level_min_score_invalid', ['level' => (string) $level['level_value']]);
                continue;
            }

            $minScore = (int) $value;
            if ($minScore < 0 || $minScore > 1000000000) {
                $errors[] = sr_t('community::action.admin.level_min_score_invalid', ['level' => (string) $level['level_value']]);
                continue;
            }

            $minScoresById[$levelId] = $minScore;
        }

        if ($errors === []) {
            try {
                $updatedCount = sr_community_update_level_min_scores($pdo, $minScoresById, $settings);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.level_definitions.updated',
                    'target_type' => 'module',
                    'target_id' => 'community',
                    'result' => 'success',
                    'message' => 'Community level definitions updated.',
                    'metadata' => [
                        'updated_count' => $updatedCount,
                        'created_level_count' => (int) ($createdLevelCount ?? 0),
                        'level_max_value' => (int) $settings['level_max_value'],
                        'level_settings_submitted' => $levelSettingsSubmitted,
                        'level_enabled' => $levelEnabled,
                        'level_auto_recalculate' => $levelAutoRecalculate,
                        'level_post_score' => $levelPostScore,
                        'level_comment_score' => $levelCommentScore,
                    ],
                ]);
                if (($createdLevelCount ?? 0) > 0) {
                    $notice = sr_t('community::action.admin.level_definitions_saved_levels_created', ['count' => (string) $createdLevelCount]);
                } else {
                    $notice = ($updatedCount > 0 || $levelSettingsSubmitted) ? sr_t('community::action.admin.level_definitions_saved') : sr_t('community::action.admin.level_definitions_no_changes');
                }
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }
    } elseif ($intent === 'recalculate_levels') {
        if (empty($settings['level_enabled'])) {
            $errors[] = sr_t('community::action.admin.level_recalculate_disabled');
        } elseif (sr_post_string('recalculate_confirmed', 1) !== '1' || sr_post_string('recalculate_confirm_text', 40) !== sr_t('community::ui.level_recalculate_confirmation_text')) {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.levels.recalculate_confirmation_failed',
                'target_type' => 'module',
                'target_id' => 'community',
                'result' => 'failure',
                'message' => 'Community level recalculation confirmation failed.',
                'metadata' => [
                    'confirmation_checked' => false,
                    'load_grade' => sr_admin_high_load_assessment([
                        'target_records' => sr_community_recalculate_target_account_count($pdo),
                        'table_count' => 4,
                        'batch_available' => true,
                    ])['grade'],
                ],
            ]);
            $errors[] = sr_t('community::action.admin.level_recalculate_confirmation_required');
        } else {
            $summary = sr_community_recalculate_recent_account_levels($pdo, 200);
            $total = sr_community_recalculate_target_account_count($pdo);
            $loadAssessment = sr_admin_high_load_assessment([
                'target_records' => $total,
                'table_count' => 4,
                'batch_available' => true,
            ]);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.levels.recalculated',
                'target_type' => 'module',
                'target_id' => 'community',
                'result' => 'success',
                'message' => 'Community levels recalculated.',
                'metadata' => array_merge($summary, [
                    'total' => $total,
                    'failed_count' => 0,
                    'batch' => false,
                    'load_grade' => (string) $loadAssessment['grade'],
                    'confirmation_checked' => true,
                ]),
            ]);
            $notice = sr_t('community::action.admin.levels_recalculated', ['accounts' => (string) ($summary['accounts'] ?? 0)]);
        }
    } else {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }

    $levels = sr_community_levels($pdo, $settings);
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), $communitySettingsPage === 'levels' ? '/admin/community/levels' : '/admin/community/settings');
}

$settings['layout_key'] = sr_community_layout_key($settings, $site ?? null, $pdo);

include SR_ROOT . '/modules/community/views/admin-settings.php';
