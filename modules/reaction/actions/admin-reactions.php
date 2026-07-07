<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/reaction/helpers.php';

$reactionAdminPage = 'definitions';
$requestPath = sr_request_path();
if ($requestPath === '/admin/reactions/presets') {
    $reactionAdminPage = 'presets';
} elseif ($requestPath === '/admin/reactions/records') {
    $reactionAdminPage = 'records';
}
$reactionPermissionPath = $requestPath;
$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], $reactionPermissionPath, 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $reactionPermissionPath, 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent === 'save_definition' && $reactionAdminPage === 'definitions') {
        $definitionIconType = sr_post_string('icon_type', 20);
        $definitionIconValue = sr_post_string('icon_value', 180);
        $uploadedReactionIcon = null;
        if ($definitionIconType === 'image' && sr_reaction_icon_upload_was_provided($_FILES['icon_image'] ?? null)) {
            try {
                $uploadedReactionIcon = sr_reaction_upload_icon_image((array) $_FILES['icon_image']);
                $definitionIconValue = (string) ($uploadedReactionIcon['storage_reference'] ?? '');
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        $result = ['ok' => false, 'errors' => []];
        if ($errors === []) {
            $result = sr_reaction_save_definition($pdo, [
                'id' => (int) sr_post_string('id', 20),
                'reaction_key' => sr_post_string('reaction_key', 80),
                'label' => sr_post_string('label', 80),
                'icon_type' => $definitionIconType,
                'icon_value' => $definitionIconValue,
                'color_hex' => sr_post_string('color_hex', 20),
                'color_swatch' => sr_post_string('color_swatch', 40),
                'description' => sr_post_string('description', 255),
                'status' => sr_post_string('status', 20),
                'sort_order' => (int) sr_post_string('sort_order', 20),
            ], (int) $account['id']);
        }
        if ($errors !== []) {
            if (is_array($uploadedReactionIcon ?? null)) {
                sr_storage_delete((string) ($uploadedReactionIcon['driver'] ?? ''), (string) ($uploadedReactionIcon['storage_key'] ?? ''));
            }
        } elseif (!empty($result['ok'])) {
            $definitionValues = is_array($result['values'] ?? null) ? $result['values'] : [];
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'reaction.definition.' . (string) ($result['operation'] ?? 'saved'),
                'target_type' => 'reaction_definition',
                'target_id' => (string) ($result['reaction_key'] ?? sr_post_string('reaction_key', 80)),
                'result' => 'success',
                'message' => 'Reaction definition saved.',
                'metadata' => [
                    'reaction_key' => (string) ($result['reaction_key'] ?? ''),
                    'operation' => (string) ($result['operation'] ?? ''),
                    'label' => (string) ($definitionValues['label'] ?? ''),
                    'icon_type' => (string) ($definitionValues['icon_type'] ?? ''),
                    'icon_value' => (string) ($definitionValues['icon_value'] ?? ''),
                    'color_hex' => (string) ($definitionValues['color_hex'] ?? ''),
                    'color_swatch' => (string) ($definitionValues['color_swatch'] ?? ''),
                    'status' => (string) ($definitionValues['status'] ?? ''),
                    'sort_order' => (int) ($definitionValues['sort_order'] ?? 0),
                ],
            ]);
            $notice = '리액션 정의를 저장했습니다.';
        } else {
            if (is_array($uploadedReactionIcon ?? null)) {
                sr_storage_delete((string) ($uploadedReactionIcon['driver'] ?? ''), (string) ($uploadedReactionIcon['storage_key'] ?? ''));
            }
            $errors = array_merge($errors, (array) ($result['errors'] ?? []));
        }
    } elseif ($intent === 'save_preset' && $reactionAdminPage === 'presets') {
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
            $presetValues = is_array($result['values'] ?? null) ? $result['values'] : [];
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'reaction.preset.' . (string) ($result['operation'] ?? 'saved'),
                'target_type' => 'reaction_preset',
                'target_id' => (string) ($result['preset_key'] ?? sr_post_string('preset_key', 80)),
                'result' => 'success',
                'message' => 'Reaction preset saved.',
                'metadata' => [
                    'preset_key' => (string) ($result['preset_key'] ?? ''),
                    'operation' => (string) ($result['operation'] ?? ''),
                    'label' => (string) ($presetValues['label'] ?? ''),
                    'status' => (string) ($presetValues['status'] ?? ''),
                    'visible_key_limit' => (int) ($presetValues['visible_key_limit'] ?? 0),
                    'sort_order' => (int) ($presetValues['sort_order'] ?? 0),
                    'reaction_keys' => array_values(array_map('strval', (array) ($presetValues['reaction_keys'] ?? []))),
                ],
            ]);
            $notice = '리액션 묶음을 저장했습니다.';
        } else {
            $errors = array_merge($errors, (array) ($result['errors'] ?? []));
        }
    } elseif ($intent === 'cleanup_records' && $reactionAdminPage === 'definitions') {
        $policy = sr_post_string('cleanup_policy', 40);
        if (in_array($policy, ['delete', 'merge'], true)) {
            sr_admin_require_permission($pdo, (int) $account['id'], $reactionPermissionPath, 'delete');
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
            $notice = '기존 사용 기록 처리 방식을 적용했습니다.';
        } else {
            $errors = array_merge($errors, (array) ($result['errors'] ?? []));
        }
    } else {
        $errors[] = '알 수 없는 요청입니다.';
    }

    sr_admin_flash_result(sr_admin_action_result($errors, $notice));
    sr_redirect($reactionPermissionPath);
}

$reactionDefinitions = sr_reaction_admin_definitions($pdo);
$reactionPresets = sr_reaction_admin_presets($pdo);
$reactionPresetItems = sr_reaction_admin_preset_items($pdo);
$reactionRecordFilters = sr_reaction_admin_record_filters([]);
$reactionRecords = [];
$reactionRecordTargets = [];
$reactionRecordTargetGroups = [];
if ($reactionAdminPage === 'records') {
    $reactionRecordFilters = sr_reaction_admin_record_filters([
        'account_id' => sr_admin_member_account_id_from_identifier($pdo, sr_runtime_config(), sr_get_string('account_id', 80)),
        'target_module' => sr_get_string('target_module', 60),
        'target_type' => sr_get_string('target_type', 60),
        'target_id' => sr_get_string('target_id', 60),
        'reaction_key' => sr_get_string('reaction_key', 80),
    ]);
    $reactionRecords = sr_reaction_admin_records($pdo, $reactionRecordFilters, 100);
    foreach ($reactionRecords as $reactionRecord) {
        $groupKey = (string) ($reactionRecord['target_module'] ?? '') . '/' . (string) ($reactionRecord['target_type'] ?? '');
        $targetId = sr_reaction_target_id((string) ($reactionRecord['target_id'] ?? ''));
        if ($groupKey !== '/' && $targetId !== '') {
            $reactionRecordTargetGroups[$groupKey][] = $targetId;
        }
    }
    foreach ($reactionRecordTargetGroups as $groupKey => $targetIds) {
        [$targetModule, $targetType] = array_pad(explode('/', $groupKey, 2), 2, '');
        $resolvedTargets = sr_reaction_resolve_targets($pdo, $targetModule, $targetType, $targetIds, (int) ($account['id'] ?? 0), ['context' => 'admin']);
        foreach ($resolvedTargets as $targetId => $target) {
            $reactionRecordTargets[$groupKey . '/' . (string) $targetId] = $target;
        }
    }
}

include SR_ROOT . '/modules/reaction/views/admin-reactions.php';
