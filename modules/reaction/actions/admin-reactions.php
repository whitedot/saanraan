<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/reaction/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/reactions', 'view');

$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/reactions', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent === 'save_definition') {
        $result = sr_reaction_save_definition($pdo, [
            'id' => (int) sr_post_string('id', 20),
            'reaction_key' => sr_post_string('reaction_key', 80),
            'label' => sr_post_string('label', 80),
            'icon_type' => sr_post_string('icon_type', 20),
            'icon_value' => sr_post_string('icon_value', 40),
            'color_hex' => sr_post_string('color_hex', 20),
            'color_swatch' => sr_post_string('color_swatch', 40),
            'description' => sr_post_string('description', 255),
            'status' => sr_post_string('status', 20),
            'sort_order' => (int) sr_post_string('sort_order', 20),
        ], (int) $account['id']);
        if (!empty($result['ok'])) {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'reaction.definition.' . (string) ($result['operation'] ?? 'saved'),
                'target_type' => 'reaction_definition',
                'target_id' => sr_post_string('reaction_key', 80),
                'result' => 'success',
                'message' => 'Reaction definition saved.',
            ]);
            $notice = '리액션 정의를 저장했습니다.';
        } else {
            $errors = array_merge($errors, (array) ($result['errors'] ?? []));
        }
    } elseif ($intent === 'save_preset') {
        $result = sr_reaction_save_preset($pdo, [
            'id' => (int) sr_post_string('id', 20),
            'preset_key' => sr_post_string('preset_key', 80),
            'label' => sr_post_string('label', 80),
            'description' => sr_post_string('description', 255),
            'status' => sr_post_string('status', 20),
            'visible_key_limit' => (int) sr_post_string('visible_key_limit', 20),
            'sort_order' => (int) sr_post_string('sort_order', 20),
            'reaction_keys' => $_POST['reaction_keys'] ?? [],
        ], (int) $account['id']);
        if (!empty($result['ok'])) {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'reaction.preset.' . (string) ($result['operation'] ?? 'saved'),
                'target_type' => 'reaction_preset',
                'target_id' => sr_post_string('preset_key', 80),
                'result' => 'success',
                'message' => 'Reaction preset saved.',
            ]);
            $notice = '리액션 preset을 저장했습니다.';
        } else {
            $errors = array_merge($errors, (array) ($result['errors'] ?? []));
        }
    } elseif ($intent === 'cleanup_records') {
        $policy = sr_post_string('cleanup_policy', 40);
        if (in_array($policy, ['delete', 'merge'], true)) {
            sr_admin_require_permission($pdo, (int) $account['id'], '/admin/reactions', 'delete');
        }
        $reactionKey = sr_post_string('reaction_key', 80);
        $result = sr_reaction_cleanup_disabled_records(
            $pdo,
            $reactionKey,
            $policy,
            sr_post_string('merge_target_key', 80),
            sr_post_string('confirmation_key', 80),
            (int) $account['id']
        );
        if (!empty($result['ok'])) {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'reaction.records.cleanup',
                'target_type' => 'reaction_definition',
                'target_id' => $reactionKey,
                'result' => 'success',
                'message' => 'Reaction records cleanup policy applied.',
                'metadata' => [
                    'policy' => (string) ($result['policy'] ?? ''),
                    'impact' => $result['impact'] ?? [],
                    'deleted_count' => (int) ($result['deleted_count'] ?? 0),
                    'merged_count' => (int) ($result['merged_count'] ?? 0),
                    'conflict_deleted_count' => (int) ($result['conflict_deleted_count'] ?? 0),
                ],
            ]);
            $notice = '기존 레코드 처리 방식을 적용했습니다.';
        } else {
            $errors = array_merge($errors, (array) ($result['errors'] ?? []));
        }
    } else {
        $errors[] = '알 수 없는 요청입니다.';
    }
}

$reactionDefinitions = sr_reaction_admin_definitions($pdo);
$reactionPresets = sr_reaction_admin_presets($pdo);
$reactionPresetItems = sr_reaction_admin_preset_items($pdo);

include SR_ROOT . '/modules/reaction/views/admin-reactions.php';
