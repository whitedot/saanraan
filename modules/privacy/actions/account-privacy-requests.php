<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/privacy/helpers.php';

$account = sr_member_require_login($pdo);
$allowedTypes = sr_privacy_request_types();
$errors = [];
$notice = '';
$flash = isset($_SESSION['sr_privacy_request_account_flash']) && is_array($_SESSION['sr_privacy_request_account_flash'])
    ? $_SESSION['sr_privacy_request_account_flash']
    : [];
unset($_SESSION['sr_privacy_request_account_flash']);
$errors = isset($flash['errors']) && is_array($flash['errors']) ? array_values(array_map('strval', $flash['errors'])) : [];
$notice = (string) ($flash['notice'] ?? '');
$values = [
    'request_type' => 'access',
    'request_message' => '',
];
if (isset($flash['values']) && is_array($flash['values'])) {
    $values = array_merge($values, [
        'request_type' => (string) ($flash['values']['request_type'] ?? $values['request_type']),
        'request_message' => (string) ($flash['values']['request_message'] ?? $values['request_message']),
    ]);
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $requestType = sr_post_string_without_truncation('request_type', 40);
    if ($requestType === null) {
        $requestType = '';
    }
    $requestMessage = sr_post_string_without_truncation('request_message', 2000);
    if ($requestMessage === null) {
        $errors[] = '요청 내용은 2000자 이하로 입력하세요.';
        $requestMessage = '';
    }

    $values = [
        'request_type' => $requestType,
        'request_message' => $requestMessage,
    ];

    if (!in_array($values['request_type'], $allowedTypes, true)) {
        $errors[] = '요청 유형이 올바르지 않습니다.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM sr_privacy_requests
             WHERE account_id = :account_id
               AND request_type = :request_type
               AND status IN (\'requested\', \'reviewing\')
             LIMIT 1'
        );
        $stmt->execute([
            'account_id' => (int) $account['id'],
            'request_type' => $values['request_type'],
        ]);

        if (is_array($stmt->fetch())) {
            $errors[] = '이미 처리 대기 중인 같은 유형의 개인정보 처리 요청이 있습니다.';
        }
    }

    if ($errors === []) {
        $now = sr_now();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_privacy_requests
                (account_id, request_type, status, requester_email_hash, requester_snapshot, request_message, created_at, updated_at)
             VALUES
                (:account_id, :request_type, :status, :requester_email_hash, :requester_snapshot, :request_message, :created_at, :updated_at)'
        );
        $stmt->execute([
            'account_id' => (int) $account['id'],
            'request_type' => $values['request_type'],
            'status' => 'requested',
            'requester_email_hash' => sr_hmac_hash(sr_normalize_identifier((string) $account['email']), $config),
            'requester_snapshot' => (string) $account['email'],
            'request_message' => $values['request_message'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $requestId = (int) $pdo->lastInsertId();
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'privacy.request.created',
            'target_type' => 'privacy_request',
            'target_id' => (string) $requestId,
            'result' => 'success',
            'message' => 'Privacy request created.',
            'metadata' => [
                'request_type' => $values['request_type'],
            ],
        ]);

        $createAdminNotificationFunction = sr_module_contract_function($pdo, 'notification', 'admin-notification-events.php', 'create_function');
        if ($createAdminNotificationFunction !== '') {
            try {
                $createAdminNotificationFunction($pdo, [
                    'title' => '새 개인정보 처리 요청이 접수되었습니다.',
                    'body_text' => '요청 유형: ' . sr_privacy_request_type_label($values['request_type']),
                    'severity' => 'danger',
                    'source_module_key' => 'privacy',
                    'event_key' => 'request.created',
                    'target_type' => 'privacy_request',
                    'target_id' => (string) $requestId,
                    'action_url' => '/admin/privacy-requests',
                    'permission_path' => '/admin/privacy-requests',
                    'permission_action' => 'view',
                    'dedupe_key' => 'privacy.request.' . (string) $requestId,
                    'created_by_account_id' => (int) $account['id'],
                ]);
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'privacy_admin_notification_create');
            }
        }

        $notice = '개인정보 처리 요청을 접수했습니다.';
        $values = [
            'request_type' => 'access',
            'request_message' => '',
        ];
    }

    $_SESSION['sr_privacy_request_account_flash'] = [
        'errors' => $errors,
        'notice' => $notice,
        'values' => $errors === [] ? ['request_type' => 'access', 'request_message' => ''] : $values,
    ];
    sr_redirect('/account/privacy-requests');
}

$requests = [];
$stmt = $pdo->prepare(
    'SELECT id, request_type, status, request_message, admin_note, handled_at, created_at, updated_at
     FROM sr_privacy_requests
     WHERE account_id = :account_id
     ORDER BY id DESC
     LIMIT 50'
);
$stmt->execute(['account_id' => (int) $account['id']]);
foreach ($stmt->fetchAll() as $row) {
    $requests[] = $row;
}

include SR_ROOT . '/modules/privacy/views/account-privacy-requests.php';
