<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/message/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/message/settings', sr_request_method() === 'POST' ? 'edit' : 'view');

$settings = sr_message_settings($pdo);
$errors = [];
$notice = '';
if (isset($_SESSION['sr_message_admin_notice']) && is_string($_SESSION['sr_message_admin_notice'])) {
    $notice = $_SESSION['sr_message_admin_notice'];
}
if (isset($_SESSION['sr_message_admin_errors']) && is_array($_SESSION['sr_message_admin_errors'])) {
    $errors = array_values(array_filter(array_map('strval', $_SESSION['sr_message_admin_errors'])));
}
unset($_SESSION['sr_message_admin_notice'], $_SESSION['sr_message_admin_errors']);

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $messageEnabled = sr_post_string('message_enabled', 10) === '1';
    $sendPolicy = sr_message_policy(sr_post_string('send_policy', 20), 'all');
    if ($sendPolicy === 'opt_in') {
        $sendPolicy = 'all';
    }
    $receivePolicy = sr_message_policy(sr_post_string('receive_policy', 20), 'all');
    $sendGroupKeys = sr_message_group_keys_from_setting($_POST['send_group_keys'] ?? []);
    $receiveGroupKeys = sr_message_group_keys_from_setting($_POST['receive_group_keys'] ?? []);
    $memberReceiveOptEnabled = sr_post_string('member_receive_opt_enabled', 10) === '1';
    $defaultMemberReceiveEnabled = sr_post_string('default_member_receive_enabled', 10) === '1';
    $windowSeconds = sr_admin_post_int_in_range('message_create_window_seconds', 60, 86400);
    $limit = sr_admin_post_int_in_range('message_create_limit', 1, 200);

    if ($sendPolicy === 'group' && $sendGroupKeys === []) {
        $errors[] = '발신 정책이 회원 그룹이면 발신 가능 그룹을 하나 이상 선택해 주세요.';
    }
    if ($receivePolicy === 'group' && $receiveGroupKeys === []) {
        $errors[] = '수신 정책이 회원 그룹이면 수신 가능 그룹을 하나 이상 선택해 주세요.';
    }
    if ($windowSeconds === null) {
        $errors[] = '발송 제한 시간은 60초 이상 86400초 이하로 입력해 주세요.';
        $windowSeconds = (int) ($settings['message_create_window_seconds'] ?? 300);
    }
    if ($limit === null) {
        $errors[] = '발송 제한 건수는 1건 이상 200건 이하로 입력해 주세요.';
        $limit = (int) ($settings['message_create_limit'] ?? 20);
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id FROM sr_modules WHERE module_key = :module_key LIMIT 1');
        $stmt->execute(['module_key' => 'message']);
        $moduleId = (int) $stmt->fetchColumn();
        if ($moduleId < 1) {
            sr_render_error(500, 'message 모듈 정보를 찾을 수 없습니다.');
        }

        $rows = [
            ['message_enabled', $messageEnabled ? '1' : '0', 'bool'],
            ['send_policy', $sendPolicy, 'string'],
            ['receive_policy', $receivePolicy, 'string'],
            ['send_group_keys', json_encode($sendGroupKeys, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', 'json'],
            ['receive_group_keys', json_encode($receiveGroupKeys, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', 'json'],
            ['member_receive_opt_enabled', $memberReceiveOptEnabled ? '1' : '0', 'bool'],
            ['default_member_receive_enabled', $defaultMemberReceiveEnabled ? '1' : '0', 'bool'],
            ['message_create_window_seconds', (string) $windowSeconds, 'int'],
            ['message_create_limit', (string) $limit, 'int'],
        ];
        $now = sr_now();
        $saveStmt = $pdo->prepare(
            'INSERT INTO sr_module_settings
                (module_id, setting_key, setting_value, value_type, created_at, updated_at)
             VALUES
                (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at)'
        );
        foreach ($rows as $row) {
            $saveStmt->execute([
                'module_id' => $moduleId,
                'setting_key' => $row[0],
                'setting_value' => $row[1],
                'value_type' => $row[2],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        sr_clear_module_settings_cache('message');
        $_SESSION['sr_message_admin_notice'] = '쪽지 환경설정을 저장했습니다.';
        sr_redirect('/admin/message/settings');
    }

    $_SESSION['sr_message_admin_errors'] = $errors;
    sr_redirect('/admin/message/settings');
}

$memberGroups = function_exists('sr_member_groups') ? sr_member_groups($pdo) : [];

include SR_ROOT . '/modules/message/views/admin-settings.php';
