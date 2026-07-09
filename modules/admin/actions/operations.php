<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/operations', 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $errors = [];
    $notice = '';
    $intent = sr_post_string('intent', 40);
    if ($intent === 'acknowledge') {
        $result = sr_admin_operational_status_acknowledge_current(
            $pdo,
            sr_post_string('label', 160),
            (int) $account['id']
        );
        $errors = $result['errors'];
        $notice = (string) $result['notice'];
    } elseif ($intent === 'treat_as_ok') {
        $result = sr_admin_operational_status_treat_acknowledged_as_ok_current(
            $pdo,
            sr_post_string('label', 160),
            (int) $account['id']
        );
        $errors = $result['errors'];
        $notice = (string) $result['notice'];
    } else {
        $errors[] = '지원하지 않는 작업입니다.';
    }
    sr_admin_flash_result(sr_admin_action_result($errors, $notice));
    sr_redirect(sr_admin_post_return_url('/admin/operations'));
}

$operationStatusRows = sr_admin_operational_status_rows($pdo, true);
$operationStatusSummary = sr_admin_operational_status_summary($operationStatusRows);
$operationStatusCheckedAt = sr_now();

include SR_ROOT . '/modules/admin/views/operations.php';
