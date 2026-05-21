<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$errors = [];
$notice = '';
$settings = sr_community_settings($pdo);
$communityThemeOptions = sr_community_theme_options();
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
    sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);

    if ($intent === 'save_settings') {
        $levelEnabled = ($_POST['level_enabled'] ?? '') === '1';
        $levelAutoRecalculate = ($_POST['level_auto_recalculate'] ?? '') === '1';
        $levelPostScore = sr_admin_post_int_in_range('level_post_score', 0, 10000);
        $levelCommentScore = sr_admin_post_int_in_range('level_comment_score', 0, 10000);
        $accessConditionPriority = sr_community_access_condition_priority(sr_post_string('access_condition_priority', 40));
        $messageWritePolicy = sr_community_message_write_policy(sr_post_string('message_write_policy', 40));
        $messageWriteMinLevel = sr_admin_post_int_in_range('message_write_min_level', 0, $maxLevel);
        $messageWriteGroupKeysInput = $_POST['message_write_group_keys'] ?? [];
        $messageWriteGroupKeys = sr_community_board_group_keys_from_input_value($messageWriteGroupKeysInput);
        $themeKey = sr_post_string('theme_key', 40);
        $assetSettings = [];
        foreach (['post_reward', 'comment_reward', 'write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'] as $assetPrefix) {
            $assetSettings[$assetPrefix . '_enabled'] = ($_POST[$assetPrefix . '_enabled'] ?? '') === '1';
            $assetSettings[$assetPrefix . '_asset_module'] = sr_community_asset_module_key(sr_post_string($assetPrefix . '_asset_module', 20));
            $assetSettings[$assetPrefix . '_amount'] = sr_admin_post_int_in_range($assetPrefix . '_amount', 0, 999999999);
        }
        $assetSettings['post_reward_reversal_enabled'] = ($_POST['post_reward_reversal_enabled'] ?? '') === '1';
        $assetSettings['comment_reward_reversal_enabled'] = ($_POST['comment_reward_reversal_enabled'] ?? '') === '1';
        $assetSettings['paid_read_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_read_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_attachment_download_charge_policy', 20), 'once');

        if ($levelPostScore === null) {
            $errors[] = '게시글 점수는 0 이상 10000 이하의 정수여야 합니다.';
            $levelPostScore = (int) $settings['level_post_score'];
        }

        if ($levelCommentScore === null) {
            $errors[] = '댓글 점수는 0 이상 10000 이하의 정수여야 합니다.';
            $levelCommentScore = (int) $settings['level_comment_score'];
        }

        if ($messageWriteMinLevel === null) {
            $errors[] = '쪽지 최소 레벨은 0 이상 ' . (string) $maxLevel . ' 이하의 정수여야 합니다.';
            $messageWriteMinLevel = (int) $settings['message_write_min_level'];
        }

        if (!isset($communityThemeOptions[$themeKey])) {
            $errors[] = '커뮤니티 테마 값이 올바르지 않습니다.';
            $themeKey = 'basic';
        }

        foreach (['post_reward' => '게시글 적립', 'comment_reward' => '댓글 적립', 'write_charge' => '글쓰기 차감', 'comment_charge' => '댓글 차감', 'paid_read' => '유료 열람', 'paid_attachment_download' => '첨부 다운로드 차감'] as $assetPrefix => $assetLabel) {
            if ($assetSettings[$assetPrefix . '_amount'] === null) {
                $errors[] = $assetLabel . ' 금액은 0 이상 999999999 이하의 정수여야 합니다.';
                $assetSettings[$assetPrefix . '_amount'] = 0;
            }

            $assetModule = (string) $assetSettings[$assetPrefix . '_asset_module'];
            if (!isset($assetModuleOptions[$assetModule]) && !empty($assetSettings[$assetPrefix . '_enabled']) && (int) $assetSettings[$assetPrefix . '_amount'] > 0) {
                $errors[] = $assetLabel . '에 사용할 ' . sr_community_asset_module_label($assetModule) . ' 모듈이 활성 상태가 아닙니다.';
            }
        }

        if (sr_community_board_group_keys_input_too_long($messageWriteGroupKeysInput)) {
            $errors[] = '쪽지 회원 그룹 목록은 1000자 이하로 선택하세요.';
        } else {
            $invalidGroupKeys = sr_community_invalid_board_group_keys_from_input_value($messageWriteGroupKeysInput);
            if ($invalidGroupKeys !== []) {
                $errors[] = '쪽지 회원 그룹 값이 올바르지 않습니다: ' . implode(', ', $invalidGroupKeys);
            }
        }

        $unknownGroupKeys = array_values(array_diff($messageWriteGroupKeys, $enabledMemberGroupKeys));
        if ($unknownGroupKeys !== []) {
            $errors[] = '쪽지 회원 그룹은 활성 회원 그룹이어야 합니다: ' . implode(', ', $unknownGroupKeys);
        }

        if ($messageWritePolicy === 'group' && $messageWriteGroupKeys === []) {
            $errors[] = '쪽지 발송 정책을 group으로 선택하려면 회원 그룹을 하나 이상 선택하세요.';
        }

        if ($errors === []) {
            $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'community' LIMIT 1");
            $stmt->execute();
            $communityModule = $stmt->fetch();
            if (!is_array($communityModule)) {
                $errors[] = '커뮤니티 모듈이 등록되어 있지 않습니다.';
            }
        }

        if ($errors === [] && is_array($communityModule ?? null)) {
            $rows = [
                ['level_enabled', $levelEnabled ? '1' : '0', 'bool'],
                ['level_auto_recalculate', $levelAutoRecalculate ? '1' : '0', 'bool'],
                ['level_post_score', (string) $levelPostScore, 'int'],
                ['level_comment_score', (string) $levelCommentScore, 'int'],
                ['access_condition_priority', $accessConditionPriority, 'string'],
                ['message_write_policy', $messageWritePolicy, 'string'],
                ['message_write_group_keys', sr_community_board_group_keys_setting_value($messageWriteGroupKeys), 'json'],
                ['message_write_min_level', (string) $messageWriteMinLevel, 'int'],
                ['theme_key', $themeKey, 'string'],
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
                    'access_condition_priority' => $accessConditionPriority,
                    'message_write_policy' => $messageWritePolicy,
                    'message_write_min_level' => $messageWriteMinLevel,
                    'theme_key' => $themeKey,
                    'asset_settings' => $assetSettings,
                ],
            ]);

            $notice = '커뮤니티 설정을 저장했습니다.';
        }
    } elseif ($intent === 'save_level_definitions') {
        $rawMinScores = $_POST['level_min_score'] ?? [];
        if (!is_array($rawMinScores)) {
            $errors[] = '레벨 최소 점수 입력이 올바르지 않습니다.';
        }

        $minScoresById = [];
        foreach ($levels as $level) {
            $levelId = (int) ($level['id'] ?? 0);
            if ($levelId < 1) {
                continue;
            }

            $rawValue = is_array($rawMinScores) ? ($rawMinScores[(string) $levelId] ?? '') : '';
            if (is_array($rawValue)) {
                $errors[] = '레벨 최소 점수 입력이 올바르지 않습니다.';
                continue;
            }

            $value = trim((string) $rawValue);
            if ($value === '' || strlen($value) > 10 || preg_match('/\A\d+\z/', $value) !== 1) {
                $errors[] = '레벨 ' . (string) $level['level_value'] . ' 최소 점수는 0 이상 1000000000 이하의 정수여야 합니다.';
                continue;
            }

            $minScore = (int) $value;
            if ($minScore < 0 || $minScore > 1000000000) {
                $errors[] = '레벨 ' . (string) $level['level_value'] . ' 최소 점수는 0 이상 1000000000 이하의 정수여야 합니다.';
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
                $notice = $updatedCount > 0 ? '레벨 정의를 저장했습니다.' : '변경된 레벨 정의가 없습니다.';
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }
    } elseif ($intent === 'recalculate_levels') {
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
        $notice = '커뮤니티 레벨을 재계산했습니다. 대상 회원: ' . (string) ($summary['accounts'] ?? 0);
    } else {
        $errors[] = '작업 값이 올바르지 않습니다.';
    }

    $levels = sr_community_levels($pdo);
}

include SR_ROOT . '/modules/community/views/admin-settings.php';
