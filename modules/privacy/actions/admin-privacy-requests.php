<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/privacy/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/privacy-requests', 'view');

$allowedStatuses = sr_admin_privacy_request_statuses();
$allowedTypes = sr_privacy_request_types();
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/privacy-requests', 'edit');

    $postResult = sr_admin_handle_privacy_request_post($pdo, $account, $allowedStatuses);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
}

$privacyRequestListFilters = sr_admin_privacy_request_filters($allowedStatuses, $allowedTypes);
$privacyRequestStatusCounts = sr_admin_privacy_request_status_counts($pdo, $allowedStatuses);
$privacyRequestPagination = sr_admin_pagination_from_total($pdo, sr_admin_privacy_request_count($pdo, $privacyRequestListFilters));
$requests = sr_admin_privacy_requests($pdo, $privacyRequestListFilters, (int) $privacyRequestPagination['per_page'], sr_admin_pagination_offset($privacyRequestPagination));

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
            'filters' => $privacyRequestListFilters,
            'result_count' => (int) $privacyRequestPagination['total'],
        ],
    ]);
}

include SR_ROOT . '/modules/privacy/views/admin-privacy-requests.php';
