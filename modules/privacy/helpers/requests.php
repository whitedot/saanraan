<?php

declare(strict_types=1);

function toy_admin_privacy_request_statuses(): array
{
    return ['requested', 'reviewing', 'completed', 'rejected', 'cancelled'];
}

function toy_admin_privacy_request_terminal_statuses(): array
{
    return ['completed', 'rejected', 'cancelled'];
}

function toy_admin_privacy_request_list_preview(?string $value, int $maxLength = 120): string
{
    $maxLength = max(1, $maxLength);
    $preview = toy_log_line_value((string) $value, $maxLength + 1);
    $length = function_exists('mb_strlen') ? mb_strlen($preview) : strlen($preview);
    if ($length <= $maxLength) {
        return $preview;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($preview, 0, $maxLength) . '...';
    }

    return substr($preview, 0, $maxLength) . '...';
}

function toy_admin_privacy_request_requester_display(array $request): string
{
    $snapshot = (string) ($request['requester_snapshot'] ?? '');
    if (filter_var($snapshot, FILTER_VALIDATE_EMAIL)) {
        [$localPart, $domain] = explode('@', $snapshot, 2);
        $prefix = function_exists('mb_substr') ? mb_substr($localPart, 0, 2) : substr($localPart, 0, 2);

        return $prefix . '***@' . $domain;
    }

    return toy_admin_privacy_request_list_preview($snapshot, 80);
}

function toy_admin_handle_privacy_request_post(PDO $pdo, array $account, array $allowedStatuses): array
{
    $errors = [];
    $notice = '';
    $requestId = toy_admin_post_positive_int('request_id');
    $status = toy_post_string_without_truncation('status', 30);
    if ($status === null) {
        $status = '';
    }
    $adminNote = toy_post_string_without_truncation('admin_note', 2000);
    if ($adminNote === null) {
        $errors[] = '관리자 메모는 2000자 이하로 입력하세요.';
        $adminNote = '';
    }
    $identityConfirmed = ($_POST['identity_confirmed'] ?? '') === '1';
    $exportConfirmed = ($_POST['export_confirmed'] ?? '') === '1';
    $actionConfirmed = ($_POST['action_confirmed'] ?? '') === '1';

    if ($requestId <= 0) {
        $errors[] = '요청을 선택하세요.';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = '처리 상태 값이 올바르지 않습니다.';
    }

    if ($status === 'completed' && (!$identityConfirmed || !$exportConfirmed || !$actionConfirmed)) {
        $errors[] = '완료 처리 전 요청자 확인, 처리 자료 또는 처리 결과 확인, 처리 내용 확인이 필요합니다.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id, status, admin_note FROM toy_privacy_requests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $requestId]);
        $privacyRequest = $stmt->fetch();

        if (!is_array($privacyRequest)) {
            $errors[] = '개인정보 처리 요청을 찾을 수 없습니다.';
        }
    }

    if ($errors === []) {
        $storedAdminNote = (string) ($privacyRequest['admin_note'] ?? '');
        $nextAdminNote = $adminNote !== '' ? $adminNote : $storedAdminNote;

        if (in_array($status, toy_admin_privacy_request_terminal_statuses(), true) && $nextAdminNote === '') {
            $errors[] = '종결 상태로 변경할 때는 관리자 메모를 남기세요.';
        }
    }

    if (
        $errors === []
        && in_array((string) $privacyRequest['status'], toy_admin_privacy_request_terminal_statuses(), true)
        && $status !== (string) $privacyRequest['status']
    ) {
        $errors[] = '종결된 개인정보 처리 요청 상태는 다시 변경할 수 없습니다.';
    }

    if ($errors === []) {
        $handledAt = in_array($status, toy_admin_privacy_request_terminal_statuses(), true) ? toy_now() : null;
        $stmt = $pdo->prepare(
            'UPDATE toy_privacy_requests
             SET status = :status,
                 admin_note = :admin_note,
                 handled_by_account_id = :handled_by_account_id,
                 handled_at = :handled_at,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'admin_note' => $nextAdminNote,
            'handled_by_account_id' => (int) $account['id'],
            'handled_at' => $handledAt,
            'updated_at' => toy_now(),
            'id' => $requestId,
        ]);

        toy_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'privacy.request.updated',
            'target_type' => 'privacy_request',
            'target_id' => (string) $requestId,
            'result' => 'success',
            'message' => 'Privacy request updated.',
            'metadata' => [
                'before_status' => (string) $privacyRequest['status'],
                'after_status' => $status,
                'checklist' => [
                    'identity_confirmed' => $identityConfirmed,
                    'export_confirmed' => $exportConfirmed,
                    'action_confirmed' => $actionConfirmed,
                ],
            ],
        ]);

        $notice = '개인정보 처리 요청 상태를 저장했습니다.';
    }

    return toy_admin_action_result($errors, $notice);
}

function toy_admin_privacy_request_status_filter(array $allowedStatuses): string
{
    $statusFilter = toy_get_string('status', 30);
    if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
        return '';
    }

    return $statusFilter;
}

function toy_admin_privacy_requests(PDO $pdo, string $statusFilter): array
{
    $requests = [];
    if ($statusFilter !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, account_id, request_type, status, requester_snapshot, request_message, admin_note, handled_by_account_id, handled_at, created_at, updated_at
             FROM toy_privacy_requests
             WHERE status = :status
             ORDER BY id DESC
             LIMIT 100'
        );
        $stmt->execute(['status' => $statusFilter]);
    } else {
        $stmt = $pdo->query(
            'SELECT id, account_id, request_type, status, requester_snapshot, request_message, admin_note, handled_by_account_id, handled_at, created_at, updated_at
             FROM toy_privacy_requests
             ORDER BY id DESC
             LIMIT 100'
        );
    }

    foreach ($stmt->fetchAll() as $row) {
        $requests[] = $row;
    }

    return $requests;
}

function toy_admin_privacy_request(PDO $pdo, int $requestId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, account_id, request_type, status, requester_snapshot, request_message, admin_note, handled_by_account_id, handled_at, created_at, updated_at
         FROM toy_privacy_requests
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $requestId]);
    $privacyRequest = $stmt->fetch();

    if (!is_array($privacyRequest)) {
        return null;
    }

    return $privacyRequest;
}

function toy_admin_privacy_request_export_data(PDO $pdo, array $privacyRequest): array
{
    $export = [
        'exported_at' => toy_now(),
        'privacy_request' => [
            'id' => (int) $privacyRequest['id'],
            'account_id' => $privacyRequest['account_id'] !== null ? (int) $privacyRequest['account_id'] : null,
            'request_type' => (string) $privacyRequest['request_type'],
            'status' => (string) $privacyRequest['status'],
            'requester_snapshot' => (string) $privacyRequest['requester_snapshot'],
            'request_message' => $privacyRequest['request_message'],
            'admin_note' => $privacyRequest['admin_note'],
            'handled_by_account_id' => $privacyRequest['handled_by_account_id'] !== null ? (int) $privacyRequest['handled_by_account_id'] : null,
            'handled_at' => $privacyRequest['handled_at'],
            'created_at' => (string) $privacyRequest['created_at'],
            'updated_at' => (string) $privacyRequest['updated_at'],
        ],
    ];

    if (!empty($privacyRequest['account_id'])) {
        try {
            $export['account_data'] = toy_privacy_export_data($pdo, (int) $privacyRequest['account_id']);
        } catch (Throwable $exception) {
            toy_log_exception($exception, 'privacy_request_export_account_' . (int) $privacyRequest['id']);
            $export['account_data_unavailable'] = true;
        }
    }

    return $export;
}

function toy_privacy_export_data(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, request_type, status, request_message, admin_note, handled_at, created_at, updated_at
         FROM toy_privacy_requests
         WHERE account_id = :account_id
         ORDER BY id ASC'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'exported_at' => toy_now(),
        'account_id' => $accountId,
        'privacy_requests' => $stmt->fetchAll(),
        'module_exports' => toy_privacy_module_exports($pdo, $accountId),
    ];
}

function toy_privacy_module_exports(PDO $pdo, int $accountId): array
{
    $exports = [];
    foreach (toy_enabled_module_contract_files($pdo, 'privacy-export.php', ['privacy']) as $moduleKey => $exportFile) {
        try {
            $moduleExport = toy_load_module_contract_file($moduleKey, $exportFile);
            if (is_callable($moduleExport)) {
                $moduleExportData = $moduleExport($pdo, $accountId);
                if (is_array($moduleExportData)) {
                    $exports[$moduleKey] = toy_privacy_export_sanitize_module_data($moduleExportData);
                }
            } elseif (is_array($moduleExport)) {
                $exports[$moduleKey] = toy_privacy_export_sanitize_module_data($moduleExport);
            }
        } catch (Throwable $exception) {
            toy_log_exception($exception, 'privacy_export_module_' . $moduleKey);
        }
    }

    return $exports;
}

function toy_privacy_export_sanitize_module_data(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    $sanitized = [];
    foreach ($value as $key => $childValue) {
        if (is_string($key) && toy_privacy_export_internal_key($key)) {
            continue;
        }

        $sanitized[$key] = toy_privacy_export_sanitize_module_data($childValue);
    }

    return $sanitized;
}

function toy_privacy_export_internal_key(string $key): bool
{
    $normalizedKey = strtolower($key);
    return $normalizedKey === 'password_hash'
        || $normalizedKey === 'account_identifier_hash'
        || preg_match(
            '/(?:^|[._-])(?:password|token|secret|credential|bearer|authorization|api[._-]?key|access[._-]?key|private[._-]?key|client[._-]?secret|app[._-]?key)(?:$|[._-])/',
            $normalizedKey
        ) === 1
        || str_ends_with($normalizedKey, '_token_hash')
        || str_ends_with($normalizedKey, '_hash');
}

function toy_admin_privacy_request_export_reauth_errors(PDO $pdo, array $account, int $requestId): array
{
    $password = toy_post_string('admin_password', 255);
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return ['관리자 재인증 계정을 확인할 수 없습니다.'];
    }

    $throttle = toy_member_reauth_throttle_status($pdo, $accountId);
    if (!empty($throttle['limited'])) {
        toy_member_log_auth($pdo, $accountId, 'reauth_blocked', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'privacy.request.export_reauth_blocked',
            'target_type' => 'privacy_request',
            'target_id' => (string) $requestId,
            'result' => 'failure',
            'message' => 'Privacy request export reauthentication blocked by throttle.',
        ]);
        return ['재인증 시도가 많습니다. 잠시 후 다시 시도하세요.'];
    }

    if ($password === '' || !password_verify($password, (string) ($account['password_hash'] ?? ''))) {
        toy_member_log_auth($pdo, $accountId, 'privacy_request_export_reauth', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'privacy.request.export_reauth_failed',
            'target_type' => 'privacy_request',
            'target_id' => (string) $requestId,
            'result' => 'failure',
            'message' => 'Privacy request export reauthentication failed.',
        ]);
        return ['개인정보 처리 자료를 내려받기 전 관리자 비밀번호를 다시 입력하세요.'];
    }

    toy_member_log_auth($pdo, $accountId, 'privacy_request_export_reauth', 'success');
    return [];
}

function toy_admin_log_privacy_request_export(PDO $pdo, array $account, int $requestId): void
{
    toy_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'privacy.request.exported',
        'target_type' => 'privacy_request',
        'target_id' => (string) $requestId,
        'result' => 'success',
        'message' => 'Privacy request export downloaded.',
    ]);
}
