<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/embed_manager/helpers.php';

$account = sr_member_require_login($pdo);
$requestMethod = sr_request_method();
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/embed-manager/settings', $requestMethod === 'POST' ? 'edit' : 'view');

if ($requestMethod === 'POST') {
    sr_require_csrf();
    $scope = sr_post_string('embed_scope', 60);
    if (!in_array($scope, ['standalone_url_only', 'all_supported_links'], true)) {
        $scope = 'standalone_url_only';
    }

    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'embed_manager' LIMIT 1");
    $stmt->execute();
    $moduleId = (int) $stmt->fetchColumn();
    $errors = [];
    if ($moduleId < 1) {
        $errors[] = '임베드 매니저 모듈 정보를 찾을 수 없습니다.';
    } else {
        $now = sr_now();
        $save = $pdo->prepare(
            'INSERT INTO sr_module_settings
                (module_id, setting_key, setting_value, value_type, created_at, updated_at)
             VALUES
                (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = VALUES(value_type),
                updated_at = VALUES(updated_at)'
        );
        foreach ([
            ['url_embed_enabled', isset($_POST['url_embed_enabled']) ? '1' : '0', 'bool'],
            ['internal_url_embed_enabled', isset($_POST['internal_url_embed_enabled']) ? '1' : '0', 'bool'],
            ['external_url_embed_enabled', isset($_POST['external_url_embed_enabled']) ? '1' : '0', 'bool'],
            ['embed_scope', $scope, 'string'],
        ] as $row) {
            $save->execute([
                'module_id' => $moduleId,
                'setting_key' => $row[0],
                'setting_value' => $row[1],
                'value_type' => $row[2],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        sr_clear_module_settings_cache('embed_manager');
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $errors === [] ? '임베드 매니저 환경설정을 저장했습니다.' : ''), '/admin/embed-manager/settings');
}

$flashResult = sr_admin_pop_flash_result();
$notice = (string) ($flashResult['notice'] ?? '');
$errors = isset($flashResult['errors']) && is_array($flashResult['errors']) ? $flashResult['errors'] : [];
$settings = sr_embed_manager_settings($pdo);
$urlContractTargetLabels = [];
foreach (sr_embed_manager_url_contract_targets($pdo) as $targetModule => $targetTypes) {
    foreach ($targetTypes as $targetType => $definition) {
        $label = trim((string) ($definition['label'] ?? ''));
        if ($label === '') {
            $label = sr_embed_manager_target_type_label((string) $targetModule, (string) $targetType);
        }
        if ($label !== '') {
            $urlContractTargetLabels[$label] = $label;
        }
    }
}
$urlContractTargetLabels = array_values($urlContractTargetLabels);

include SR_ROOT . '/modules/embed_manager/views/admin-settings.php';
