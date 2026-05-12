<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$errors = [];
$notice = '';
$settings = toy_community_settings($pdo);
$communityThemeOptions = toy_community_theme_options();
$levels = toy_community_levels($pdo);
$maxLevel = toy_community_max_level_value();
$memberGroups = toy_member_groups($pdo);
$enabledMemberGroups = [];
$enabledMemberGroupKeys = [];
foreach ($memberGroups as $memberGroup) {
    if ((string) ($memberGroup['status'] ?? '') !== 'enabled') {
        continue;
    }

    $enabledMemberGroups[] = $memberGroup;
    $enabledMemberGroupKeys[] = (string) $memberGroup['group_key'];
}

if (toy_request_method() === 'POST') {
    toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
    toy_require_csrf();

    $intent = toy_post_string('intent', 40);

    if ($intent === 'save_settings') {
        $levelEnabled = ($_POST['level_enabled'] ?? '') === '1';
        $levelAutoRecalculate = ($_POST['level_auto_recalculate'] ?? '') === '1';
        $levelPostScore = toy_admin_post_int_in_range('level_post_score', 0, 10000);
        $levelCommentScore = toy_admin_post_int_in_range('level_comment_score', 0, 10000);
        $accessConditionPriority = toy_community_access_condition_priority(toy_post_string('access_condition_priority', 40));
        $messageWritePolicy = toy_community_message_write_policy(toy_post_string('message_write_policy', 40));
        $messageWriteMinLevel = toy_admin_post_int_in_range('message_write_min_level', 0, $maxLevel);
        $messageWriteGroupKeysInput = toy_post_string_without_truncation('message_write_group_keys', 1000);
        $messageWriteGroupKeys = is_string($messageWriteGroupKeysInput) ? toy_community_board_group_keys_from_input($messageWriteGroupKeysInput) : [];
        $themeKey = toy_post_string('theme_key', 40);

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

        if (!is_string($messageWriteGroupKeysInput)) {
            $errors[] = '쪽지 그룹 key 목록은 1000자 이하로 입력하세요.';
        } else {
            $invalidGroupKeys = toy_community_invalid_board_group_keys_from_input($messageWriteGroupKeysInput);
            if ($invalidGroupKeys !== []) {
                $errors[] = '쪽지 그룹 key 형식이 올바르지 않습니다: ' . implode(', ', $invalidGroupKeys);
            }
        }

        $unknownGroupKeys = array_values(array_diff($messageWriteGroupKeys, $enabledMemberGroupKeys));
        if ($unknownGroupKeys !== []) {
            $errors[] = '쪽지 그룹 key는 활성 회원 그룹이어야 합니다: ' . implode(', ', $unknownGroupKeys);
        }

        if ($messageWritePolicy === 'group' && $messageWriteGroupKeys === []) {
            $errors[] = '쪽지 발송 정책을 group으로 선택하려면 그룹 key를 하나 이상 입력하세요.';
        }

        if ($errors === []) {
            $stmt = $pdo->prepare("SELECT id FROM toy_modules WHERE module_key = 'community' LIMIT 1");
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
                ['message_write_group_keys', toy_community_board_group_keys_setting_value($messageWriteGroupKeys), 'json'],
                ['message_write_min_level', (string) $messageWriteMinLevel, 'int'],
                ['theme_key', $themeKey, 'string'],
            ];
            $stmt = $pdo->prepare(
                'INSERT INTO toy_module_settings
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
                    'created_at' => toy_now(),
                    'updated_at' => toy_now(),
                ]);
            }
            toy_clear_module_settings_cache('community');
            $settings = toy_community_settings($pdo);

            toy_audit_log($pdo, [
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
                $updatedCount = toy_community_update_level_min_scores($pdo, $minScoresById);
                toy_audit_log($pdo, [
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
        $summary = toy_community_recalculate_recent_account_levels($pdo, 200);
        toy_audit_log($pdo, [
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

    $levels = toy_community_levels($pdo);
}

include TOY_ROOT . '/modules/community/views/admin-settings.php';
