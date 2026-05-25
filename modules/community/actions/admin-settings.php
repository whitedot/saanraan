<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);

$errors = [];
$notice = '';
$settings = sr_community_settings($pdo);
$communitySettingsPage = isset($communitySettingsPage) ? (string) $communitySettingsPage : 'settings';
$communitySettingsPermissionPath = $communitySettingsPage === 'levels' ? '/admin/community/levels' : '/admin/community/settings';
sr_admin_require_permission($pdo, (int) $account['id'], $communitySettingsPermissionPath, 'view');
$communityLayoutOptions = sr_public_layout_options($pdo);
$assetModuleOptions = sr_community_asset_module_options($pdo);
$levels = sr_community_levels($pdo);
$maxLevel = sr_community_max_level_value();
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
        $levelAutoRecalculate = ($_POST['level_auto_recalculate'] ?? '') === '1';
        $levelPostScore = sr_admin_post_int_in_range('level_post_score', 0, 10000);
        $levelCommentScore = sr_admin_post_int_in_range('level_comment_score', 0, 10000);
        $messageWritePolicy = sr_community_message_write_policy(sr_post_string('message_write_policy', 40));
        $messageWriteMinLevel = sr_admin_post_int_in_range('message_write_min_level', 0, $maxLevel);
        $messageWriteGroupKeysInput = $_POST['message_write_group_keys'] ?? [];
        $messageWriteGroupKeys = sr_community_board_group_keys_from_input_value($messageWriteGroupKeysInput);
        $layoutKey = sr_public_layout_normalize_key(sr_post_string('layout_key', 80));
        $assetSettings = [];
        foreach (['post_reward', 'comment_reward', 'write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'] as $assetPrefix) {
            $assetSettings[$assetPrefix . '_enabled'] = ($_POST[$assetPrefix . '_enabled'] ?? '') === '1';
            $assetSettings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
                ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($_POST[$assetPrefix . '_asset_module'] ?? ''))
                : sr_community_asset_module_key(sr_post_string($assetPrefix . '_asset_module', 20));
            $assetSettings[$assetPrefix . '_amount'] = sr_admin_post_int_in_range($assetPrefix . '_amount', 0, 999999999);
        }
        $assetSettings['post_reward_reversal_enabled'] = ($_POST['post_reward_reversal_enabled'] ?? '') === '1';
        $assetSettings['comment_reward_reversal_enabled'] = ($_POST['comment_reward_reversal_enabled'] ?? '') === '1';
        $assetSettings['paid_read_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_read_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_attachment_download_charge_policy', 20), 'once');
        $beforeAssetSettings = sr_community_asset_settings_for_audit($settings, true);

        if ($levelPostScore === null) {
            $errors[] = sr_t('community::action.admin.post_score_invalid');
            $levelPostScore = (int) $settings['level_post_score'];
        }

        if ($levelCommentScore === null) {
            $errors[] = sr_t('community::action.admin.comment_score_invalid');
            $levelCommentScore = (int) $settings['level_comment_score'];
        }

        if ($messageWriteMinLevel === null) {
            $errors[] = sr_t('community::action.admin.message_min_level_invalid', ['max' => (string) $maxLevel]);
            $messageWriteMinLevel = (int) $settings['message_write_min_level'];
        }

        if (!isset($communityLayoutOptions[$layoutKey])) {
            $errors[] = sr_t('community::action.admin.layout_invalid');
            $layoutKey = sr_community_layout_key($settings, $site ?? null, $pdo);
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
                ['level_auto_recalculate', $levelAutoRecalculate ? '1' : '0', 'bool'],
                ['level_post_score', (string) $levelPostScore, 'int'],
                ['level_comment_score', (string) $levelCommentScore, 'int'],
                ['message_write_policy', $messageWritePolicy, 'string'],
                ['message_write_group_keys', sr_community_board_group_keys_setting_value($messageWriteGroupKeys), 'json'],
                ['message_write_min_level', (string) $messageWriteMinLevel, 'int'],
                ['theme_key', 'basic', 'string'],
                ['layout_key', $layoutKey, 'string'],
                ['post_reward_enabled', $assetSettings['post_reward_enabled'] ? '1' : '0', 'bool'],
                ['post_reward_asset_module', (string) $assetSettings['post_reward_asset_module'], 'string'],
                ['post_reward_amount', (string) $assetSettings['post_reward_amount'], 'int'],
                ['post_reward_reversal_enabled', $assetSettings['post_reward_reversal_enabled'] ? '1' : '0', 'bool'],
                ['comment_reward_enabled', $assetSettings['comment_reward_enabled'] ? '1' : '0', 'bool'],
                ['comment_reward_asset_module', (string) $assetSettings['comment_reward_asset_module'], 'string'],
                ['comment_reward_amount', (string) $assetSettings['comment_reward_amount'], 'int'],
                ['comment_reward_reversal_enabled', $assetSettings['comment_reward_reversal_enabled'] ? '1' : '0', 'bool'],
                ['write_charge_enabled', $assetSettings['write_charge_enabled'] ? '1' : '0', 'bool'],
                ['write_charge_asset_module', (string) $assetSettings['write_charge_asset_module'], 'string'],
                ['write_charge_amount', (string) $assetSettings['write_charge_amount'], 'int'],
                ['comment_charge_enabled', $assetSettings['comment_charge_enabled'] ? '1' : '0', 'bool'],
                ['comment_charge_asset_module', (string) $assetSettings['comment_charge_asset_module'], 'string'],
                ['comment_charge_amount', (string) $assetSettings['comment_charge_amount'], 'int'],
                ['paid_read_enabled', $assetSettings['paid_read_enabled'] ? '1' : '0', 'bool'],
                ['paid_read_asset_module', (string) $assetSettings['paid_read_asset_module'], 'string'],
                ['paid_read_amount', (string) $assetSettings['paid_read_amount'], 'int'],
                ['paid_read_charge_policy', (string) $assetSettings['paid_read_charge_policy'], 'string'],
                ['paid_attachment_download_enabled', $assetSettings['paid_attachment_download_enabled'] ? '1' : '0', 'bool'],
                ['paid_attachment_download_asset_module', (string) $assetSettings['paid_attachment_download_asset_module'], 'string'],
                ['paid_attachment_download_amount', (string) $assetSettings['paid_attachment_download_amount'], 'int'],
                ['paid_attachment_download_charge_policy', (string) $assetSettings['paid_attachment_download_charge_policy'], 'string'],
            ];
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
            foreach ($rows as $row) {
                $stmt->execute([
                    'module_id' => (int) $communityModule['id'],
                    'setting_key' => $row[0],
                    'setting_value' => $row[1],
                    'value_type' => $row[2],
                    'created_at' => sr_now(),
                    'updated_at' => sr_now(),
                ]);
            }
            $stmt = $pdo->prepare('DELETE FROM sr_module_settings WHERE module_id = :module_id AND setting_key = :setting_key');
            $stmt->execute([
                'module_id' => (int) $communityModule['id'],
                'setting_key' => 'access_condition_priority',
            ]);
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
                    'level_auto_recalculate' => $levelAutoRecalculate,
                    'message_write_policy' => $messageWritePolicy,
                    'message_write_min_level' => $messageWriteMinLevel,
                    'layout_key' => $layoutKey,
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

            $notice = sr_t('community::action.admin.settings_saved');
        }
    } elseif ($intent === 'save_level_definitions') {
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

            $rawValue = is_array($rawMinScores) ? ($rawMinScores[(string) $levelId] ?? '') : '';
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
                $updatedCount = sr_community_update_level_min_scores($pdo, $minScoresById);
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
                    ],
                ]);
                $notice = $updatedCount > 0 ? sr_t('community::action.admin.level_definitions_saved') : sr_t('community::action.admin.level_definitions_no_changes');
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }
    } elseif ($intent === 'recalculate_levels') {
        if (empty($settings['level_enabled'])) {
            $errors[] = sr_t('community::action.admin.level_recalculate_disabled');
        } else {
            $summary = sr_community_recalculate_recent_account_levels($pdo, 200);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.levels.recalculated',
                'target_type' => 'module',
                'target_id' => 'community',
                'result' => 'success',
                'message' => 'Community levels recalculated.',
                'metadata' => $summary,
            ]);
            $notice = sr_t('community::action.admin.levels_recalculated', ['accounts' => (string) ($summary['accounts'] ?? 0)]);
        }
    } else {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }

    $levels = sr_community_levels($pdo);
}

$settings['layout_key'] = sr_community_layout_key($settings, $site ?? null, $pdo);

include SR_ROOT . '/modules/community/views/admin-settings.php';
