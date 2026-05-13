<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/privacy/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$allowedStatuses = sr_admin_privacy_request_statuses();
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_privacy_request_post($pdo, $account, $allowedStatuses);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
}

$statusFilter = sr_admin_privacy_request_status_filter($allowedStatuses);
$requests = sr_admin_privacy_requests($pdo, $statusFilter);

if (sr_request_method() === 'GET') {
    sr_audit_log($pdo, [
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

include SR_ROOT . '/modules/privacy/views/admin-privacy-requests.php';
