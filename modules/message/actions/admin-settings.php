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
        sr_message_save_settings($pdo, array_merge($settings, [
            'message_enabled' => $messageEnabled,
            'send_policy' => $sendPolicy,
            'receive_policy' => $receivePolicy,
            'send_group_keys' => $sendGroupKeys,
            'receive_group_keys' => $receiveGroupKeys,
            'member_receive_opt_enabled' => $memberReceiveOptEnabled,
            'default_member_receive_enabled' => $defaultMemberReceiveEnabled,
            'message_create_window_seconds' => $windowSeconds,
            'message_create_limit' => $limit,
        ]));
        $_SESSION['sr_message_admin_notice'] = '쪽지 환경설정을 저장했습니다.';
        sr_redirect('/admin/message/settings');
    }

    $_SESSION['sr_message_admin_errors'] = $errors;
    sr_redirect('/admin/message/settings');
}

$memberGroups = function_exists('sr_member_groups') ? sr_member_groups($pdo) : [];

include SR_ROOT . '/modules/message/views/admin-settings.php';
