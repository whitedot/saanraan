<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/privacy/helpers.php';

$account = toy_member_require_login($pdo);
$allowedTypes = ['access', 'rectification', 'erasure', 'restriction', 'portability', 'objection', 'withdrawal'];
$errors = [];
$notice = '';
$values = [
    'request_type' => 'access',
    'request_message' => '',
];

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    $requestType = toy_post_string_without_truncation('request_type', 40);
    if ($requestType === null) {
        $requestType = '';
    }
    $requestMessage = toy_post_string_without_truncation('request_message', 2000);
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
             FROM toy_privacy_requests
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
        $now = toy_now();
        $stmt = $pdo->prepare(
            'INSERT INTO toy_privacy_requests
                (account_id, request_type, status, requester_email_hash, requester_snapshot, request_message, created_at, updated_at)
             VALUES
                (:account_id, :request_type, :status, :requester_email_hash, :requester_snapshot, :request_message, :created_at, :updated_at)'
        );
        $stmt->execute([
            'account_id' => (int) $account['id'],
            'request_type' => $values['request_type'],
            'status' => 'requested',
            'requester_email_hash' => toy_hmac_hash(toy_normalize_identifier((string) $account['email']), $config),
            'requester_snapshot' => (string) $account['email'],
            'request_message' => $values['request_message'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $requestId = (int) $pdo->lastInsertId();
        toy_audit_log($pdo, [
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

        $notice = '개인정보 처리 요청을 접수했습니다.';
        $values = [
            'request_type' => 'access',
            'request_message' => '',
        ];
    }
}

$requests = [];
$stmt = $pdo->prepare(
    'SELECT id, request_type, status, request_message, admin_note, handled_at, created_at, updated_at
     FROM toy_privacy_requests
     WHERE account_id = :account_id
     ORDER BY id DESC
     LIMIT 50'
);
$stmt->execute(['account_id' => (int) $account['id']]);
foreach ($stmt->fetchAll() as $row) {
    $requests[] = $row;
}

include TOY_ROOT . '/modules/privacy/views/account-privacy-requests.php';
