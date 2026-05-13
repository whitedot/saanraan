<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/privacy/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$allowedStatuses = toy_admin_privacy_request_statuses();
$errors = [];
$notice = '';

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    $postResult = toy_admin_handle_privacy_request_post($pdo, $account, $allowedStatuses);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
}

$statusFilter = toy_admin_privacy_request_status_filter($allowedStatuses);
$requests = toy_admin_privacy_requests($pdo, $statusFilter);

if (toy_request_method() === 'GET') {
    toy_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'privacy.request.list.viewed',
        'target_type' => 'privacy_request',
        'target_id' => '',
        'result' => 'success',
        'message' => 'Privacy request list viewed.',
        'metadata' => [
            'status_filter' => $statusFilter,
            'result_count' => count($requests),
        ],
    ]);
}

include TOY_ROOT . '/modules/privacy/views/admin-privacy-requests.php';
