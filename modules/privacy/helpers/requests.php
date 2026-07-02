<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';
require_once dirname(__DIR__, 3) . '/core/helpers/ops.php';

function sr_admin_privacy_request_statuses(): array
{
    return ['requested', 'reviewing', 'completed', 'rejected', 'cancelled'];
}

function sr_privacy_request_types(): array
{
    return ['access', 'rectification', 'erasure', 'restriction', 'portability', 'objection', 'withdrawal'];
}

function sr_privacy_time_html(string $value): string
{
    return sr_relative_time_html($value);
}

function sr_admin_privacy_request_terminal_statuses(): array
{
    return ['completed', 'rejected', 'cancelled'];
}

function sr_admin_privacy_request_list_preview(?string $value, int $maxLength = 120): string
{
    $maxLength = max(1, $maxLength);
    $preview = sr_log_line_value((string) $value, $maxLength + 1);
    $length = function_exists('mb_strlen') ? mb_strlen($preview) : strlen($preview);
    if ($length <= $maxLength) {
        return $preview;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($preview, 0, $maxLength) . '...';
    }

    return substr($preview, 0, $maxLength) . '...';
}

function sr_admin_privacy_request_admin_note_sanitize(?string $value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }

    $text = sr_log_sensitive_text_sanitize($text);
    $text = preg_replace('/\b\d{6}[- ]?[1-4]\d{6}\b/u', '[redacted-rrn]', $text) ?? $text;
    $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[redacted-email]', $text) ?? $text;
    $text = preg_replace('/(?<!\d)(?:\+?82[-\s]?)?0?1[016789][-\s]?\d{3,4}[-\s]?\d{4}(?!\d)/u', '[redacted-phone]', $text) ?? $text;

    return $text;
}

function sr_admin_privacy_request_requester_display(array $request): string
{
    $snapshot = (string) ($request['requester_snapshot'] ?? '');
    if (filter_var($snapshot, FILTER_VALIDATE_EMAIL)) {
        [$localPart, $domain] = explode('@', $snapshot, 2);
        $prefix = function_exists('mb_substr') ? mb_substr($localPart, 0, 2) : substr($localPart, 0, 2);

        return $prefix . '***@' . $domain;
    }

    return sr_admin_privacy_request_list_preview($snapshot, 80);
}

function sr_admin_handle_privacy_request_create_post(PDO $pdo, array $account, array $allowedTypes): array
{
    $errors = [];
    $notice = '';
    $accountId = sr_admin_post_positive_int('account_id');
    $requestType = sr_post_string_without_truncation('request_type', 40);
    if ($requestType === null) {
        $requestType = '';
    }
    $requesterSnapshot = sr_post_string_without_truncation('requester_snapshot', 255);
    if ($requesterSnapshot === null) {
        $errors[] = '요청자는 255자 이하로 입력하세요.';
        $requesterSnapshot = '';
    }
    $requestMessage = sr_post_string_without_truncation('request_message', 2000);
    if ($requestMessage === null) {
        $errors[] = '요청 내용은 2000자 이하로 입력하세요.';
        $requestMessage = '';
    }
    $adminNote = sr_post_string_without_truncation('admin_note', 2000);
    if ($adminNote === null) {
        $errors[] = '관리자 메모는 2000자 이하로 입력하세요.';
        $adminNote = '';
    }

    $requesterSnapshot = trim($requesterSnapshot);
    $requestMessage = trim($requestMessage);
    $adminNote = sr_admin_privacy_request_admin_note_sanitize($adminNote);
    $linkedAccount = null;

    if (!in_array((string) $requestType, $allowedTypes, true)) {
        $errors[] = '요청 유형 값이 올바르지 않습니다.';
    }

    if ($requestMessage === '') {
        $errors[] = '요청 내용을 입력하세요.';
    }

    if ($accountId < 1 && $requesterSnapshot === '') {
        $errors[] = '계정 ID 또는 요청자 중 하나를 입력하세요.';
    }

    if ($accountId > 0 && $errors === []) {
        $stmt = $pdo->prepare('SELECT id, email, display_name FROM sr_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $accountId]);
        $memberAccount = $stmt->fetch();
        if (!is_array($memberAccount)) {
            $errors[] = '연결할 회원 계정을 찾을 수 없습니다.';
        } else {
            $linkedAccount = $memberAccount;
            if ($requesterSnapshot === '') {
                $requesterSnapshot = (string) ($memberAccount['email'] ?? '');
            }
        }
    }

    $requesterEmailHash = '';
    if ($errors === [] && filter_var($requesterSnapshot, FILTER_VALIDATE_EMAIL)) {
        $requesterEmailHash = sr_hmac_hash(sr_normalize_identifier($requesterSnapshot), sr_runtime_config());
    }

    if ($errors === []) {
        $now = sr_now();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_privacy_requests
                (account_id, request_type, status, requester_email_hash, requester_snapshot, request_message, admin_note, handled_by_account_id, handled_at, created_at, updated_at)
             VALUES
                (:account_id, :request_type, :status, :requester_email_hash, :requester_snapshot, :request_message, :admin_note, NULL, NULL, :created_at, :updated_at)'
        );
        $stmt->execute([
            'account_id' => $accountId > 0 ? $accountId : null,
            'request_type' => (string) $requestType,
            'status' => 'requested',
            'requester_email_hash' => $requesterEmailHash,
            'requester_snapshot' => $requesterSnapshot,
            'request_message' => $requestMessage,
            'admin_note' => $adminNote !== '' ? $adminNote : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $requestId = (int) $pdo->lastInsertId();

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'privacy.request.created',
            'target_type' => 'privacy_request',
            'target_id' => (string) $requestId,
            'result' => 'success',
            'message' => 'Privacy request created.',
            'metadata' => [
                'source' => 'admin_manual',
                'request_type' => (string) $requestType,
                'linked_account_id' => $linkedAccount !== null ? (int) $linkedAccount['id'] : null,
            ],
        ]);

        $notice = '개인정보 요청 대응 기록을 추가했습니다.';
    }

    return sr_admin_action_result($errors, $notice);
}

function sr_admin_handle_privacy_request_post(PDO $pdo, array $account, array $allowedStatuses): array
{
    $errors = [];
    $notice = '';
    $requestId = sr_admin_post_positive_int('request_id');
    $status = sr_post_string_without_truncation('status', 30);
    if ($status === null) {
        $status = '';
    }
    $adminNote = sr_post_string_without_truncation('admin_note', 2000);
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
        $errors[] = '대응 상태 값이 올바르지 않습니다.';
    }

    if ($status === 'completed' && (!$identityConfirmed || !$exportConfirmed || !$actionConfirmed)) {
        $errors[] = '완료 전 요청자 확인, 사본 자료 또는 실제 조치 결과 확인, 대응 내용 확인이 필요합니다.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id, status, admin_note, handled_by_account_id, handled_at FROM sr_privacy_requests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $requestId]);
        $privacyRequest = $stmt->fetch();

        if (!is_array($privacyRequest)) {
            $errors[] = '개인정보 요청 대응 기록을 찾을 수 없습니다.';
        }
    }

    if ($errors === []) {
        $storedAdminNote = (string) ($privacyRequest['admin_note'] ?? '');
        $nextAdminNote = $adminNote !== '' ? $adminNote : $storedAdminNote;
        $nextAdminNote = sr_admin_privacy_request_admin_note_sanitize($nextAdminNote);

        if (in_array($status, sr_admin_privacy_request_terminal_statuses(), true) && $nextAdminNote === '') {
            $errors[] = '종결 상태로 변경할 때는 관리자 메모를 남기세요.';
        }
    }

    if (
        $errors === []
        && in_array((string) $privacyRequest['status'], sr_admin_privacy_request_terminal_statuses(), true)
        && $status !== (string) $privacyRequest['status']
    ) {
        $errors[] = '종결된 개인정보 요청 대응 기록 상태는 다시 변경할 수 없습니다.';
    }

    if ($errors === []) {
        $statusChanged = $status !== (string) $privacyRequest['status'];
        $isTerminalStatus = in_array($status, sr_admin_privacy_request_terminal_statuses(), true);
        $preserveTerminalHandler = !$statusChanged && $isTerminalStatus;
        $handledAt = $isTerminalStatus
            ? ($preserveTerminalHandler ? ($privacyRequest['handled_at'] ?? null) : sr_now())
            : null;
        $handledByAccountId = $preserveTerminalHandler
            ? ($privacyRequest['handled_by_account_id'] ?? null)
            : (int) $account['id'];
        $stmt = $pdo->prepare(
            'UPDATE sr_privacy_requests
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
            'handled_by_account_id' => $handledByAccountId,
            'handled_at' => $handledAt,
            'updated_at' => sr_now(),
            'id' => $requestId,
        ]);

        sr_audit_log($pdo, [
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

        $notice = '개인정보 요청 대응 기록 상태를 저장했습니다.';
    }

    return sr_admin_action_result($errors, $notice);
}

function sr_admin_privacy_request_filters(array $allowedStatuses, array $allowedTypes): array
{
    $filters = [
        'request_id' => sr_admin_get_positive_int('request_id'),
        'status' => sr_admin_get_allowed_array('status', $allowedStatuses, 30),
        'request_type' => sr_admin_get_allowed_single_array('request_type', $allowedTypes, 40),
        'field' => sr_get_string('field', 20),
        'q' => trim(sr_get_string('q', 120)),
    ];

    if (!in_array($filters['field'], ['all', 'id', 'account', 'requester', 'message', 'note'], true)) {
        $filters['field'] = 'all';
    }

    return $filters;
}

function sr_admin_privacy_request_status_counts(PDO $pdo, array $allowedStatuses): array
{
    $counts = ['total' => 0];
    foreach ($allowedStatuses as $status) {
        $counts[$status] = 0;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_privacy_requests GROUP BY status');
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $count = (int) ($row['count_value'] ?? 0);
        $counts['total'] += $count;
        if (in_array($status, $allowedStatuses, true)) {
            $counts[$status] = $count;
        }
    }

    return $counts;
}

function sr_admin_privacy_request_query_parts(array $filters): array
{
    $where = [];
    $params = [];

    if ((int) ($filters['request_id'] ?? 0) > 0) {
        $where[] = 'id = :request_id';
        $params['request_id'] = (int) $filters['request_id'];
    }

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['request_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('request_type', 'request_type', $filters['request_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'id') {
            $where[] = "CAST(id AS CHAR) LIKE :keyword ESCAPE '\\\\'";
            $params['keyword'] = $like;
        } elseif ($field === 'account') {
            $where[] = "CAST(account_id AS CHAR) LIKE :keyword ESCAPE '\\\\'";
            $params['keyword'] = $like;
        } elseif ($field === 'requester') {
            $where[] = "requester_snapshot LIKE :keyword ESCAPE '\\\\'";
            $params['keyword'] = $like;
        } elseif ($field === 'message') {
            $where[] = "request_message LIKE :keyword ESCAPE '\\\\'";
            $params['keyword'] = $like;
        } elseif ($field === 'note') {
            $where[] = "admin_note LIKE :keyword ESCAPE '\\\\'";
            $params['keyword'] = $like;
        } else {
            $where[] = "(CAST(id AS CHAR) LIKE :id_keyword ESCAPE '\\\\' OR CAST(account_id AS CHAR) LIKE :account_keyword ESCAPE '\\\\' OR requester_snapshot LIKE :requester_keyword ESCAPE '\\\\' OR request_message LIKE :message_keyword ESCAPE '\\\\' OR admin_note LIKE :note_keyword ESCAPE '\\\\')";
            $params['id_keyword'] = $like;
            $params['account_keyword'] = $like;
            $params['requester_keyword'] = $like;
            $params['message_keyword'] = $like;
            $params['note_keyword'] = $like;
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_admin_privacy_request_count(PDO $pdo, array $filters): int
{
    $queryParts = sr_admin_privacy_request_query_parts($filters);
    $whereSql = $queryParts['where'] === [] ? '' : 'WHERE ' . implode(' AND ', $queryParts['where']);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS count_value FROM sr_privacy_requests ' . $whereSql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_admin_privacy_request_sort_options(): array
{
    return [
        'request_type' => ['columns' => ['request_type', 'id']],
        'status' => ['columns' => ['status', 'id']],
        'handled_at' => ['columns' => ['handled_at', 'id']],
        'created_at' => ['columns' => ['created_at', 'id']],
    ];
}

function sr_admin_privacy_request_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_admin_privacy_requests(PDO $pdo, array $filters, int $limit = 0, int $offset = 0, array $sort = []): array
{
    $queryParts = sr_admin_privacy_request_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
    $sql = 'SELECT id, account_id, request_type, status, requester_snapshot, request_message, admin_note, handled_by_account_id, handled_at, created_at, updated_at
            FROM sr_privacy_requests
            ' . $whereSql
        . sr_admin_sort_order_sql(sr_admin_privacy_request_sort_options(), $sort, sr_admin_privacy_request_default_sort());
    if ($limit > 0) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    $requests = [];
    foreach ($stmt->fetchAll() as $row) {
        $requests[] = $row;
    }

    return $requests;
}

function sr_admin_privacy_request(PDO $pdo, int $requestId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, account_id, request_type, status, requester_snapshot, request_message, admin_note, handled_by_account_id, handled_at, created_at, updated_at
         FROM sr_privacy_requests
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

function sr_admin_privacy_request_export_data(PDO $pdo, array $privacyRequest): array
{
    $exportedAt = sr_now();
    $export = [
        'export_schema_version' => 'privacy_request_export_v1',
        'description' => '개인정보 요청 대응 기록과 연결 계정의 개인정보 사본입니다. 요청 기록은 실제 데이터 조치를 자동 실행했다는 뜻이 아니며, 처리 결과는 관리자 메모와 소유 모듈 기록을 함께 확인해야 합니다.',
        'sections' => [
            'privacy_request' => '개인정보 요청 대응 기록',
            'account_data' => '요청과 연결된 회원 계정의 개인정보 사본',
        ],
        'exported_at' => $exportedAt,
        'privacy_request' => [
            'id' => (int) $privacyRequest['id'],
            'account_id' => $privacyRequest['account_id'] !== null ? (int) $privacyRequest['account_id'] : null,
            'request_type' => (string) $privacyRequest['request_type'],
            'status' => (string) $privacyRequest['status'],
            'requester_snapshot' => (string) $privacyRequest['requester_snapshot'],
            'request_message' => $privacyRequest['request_message'],
            'admin_note' => sr_admin_privacy_request_admin_note_sanitize($privacyRequest['admin_note'] ?? null),
            'handled_by_account_id' => $privacyRequest['handled_by_account_id'] !== null ? (int) $privacyRequest['handled_by_account_id'] : null,
            'handled_at' => $privacyRequest['handled_at'],
            'created_at' => (string) $privacyRequest['created_at'],
            'updated_at' => (string) $privacyRequest['updated_at'],
        ],
    ];

    if (!empty($privacyRequest['account_id'])) {
        try {
            $export['account_data'] = sr_privacy_export_data($pdo, (int) $privacyRequest['account_id']);
        } catch (Throwable $exception) {
            $evidenceId = sr_privacy_export_evidence_id('privacy_request_account_data', (int) $privacyRequest['id'], $exportedAt);
            sr_log_exception($exception, 'privacy_request_export_account_' . (int) $privacyRequest['id'] . '_evidence_' . $evidenceId);
            $export['account_data_unavailable'] = true;
            $export['account_data_status'] = [
                'status' => 'failed',
                'error_code' => 'account_export_exception',
                'evidence_id' => $evidenceId,
            ];
        }
    }

    return $export;
}

function sr_privacy_export_data(PDO $pdo, int $accountId): array
{
    $exportedAt = sr_now();
    $stmt = $pdo->prepare(
        'SELECT id, request_type, status, request_message, admin_note, handled_at, created_at, updated_at
         FROM sr_privacy_requests
         WHERE account_id = :account_id
         ORDER BY id ASC'
    );
    $stmt->execute(['account_id' => $accountId]);
    $privacyRequests = $stmt->fetchAll();
    foreach ($privacyRequests as $index => $privacyRequest) {
        if (is_array($privacyRequest)) {
            $privacyRequests[$index]['admin_note'] = sr_admin_privacy_request_admin_note_sanitize($privacyRequest['admin_note'] ?? null);
        }
    }

    $moduleExportResults = sr_privacy_module_export_results($pdo, $accountId, $exportedAt);

    return [
        'export_schema_version' => 'privacy_export_v1',
        'generated_by' => 'saanraan privacy module',
        'description' => '이 파일은 회원 계정과 연결된 개인정보 사본입니다. 일부 운영 보존 기록은 법적 또는 서비스 운영 근거로 남을 수 있습니다.',
        'sections' => [
            'privacy_requests' => '개인정보 요청 대응 기록',
            'module_exports' => '활성 모듈이 privacy-export.php 계약으로 제공한 회원 관련 데이터',
            'module_export_status' => '모듈별 사본 제공 성공, 빈 결과, 실패 또는 건너뜀 증빙',
        ],
        'exported_at' => $exportedAt,
        'account_id' => $accountId,
        'privacy_requests' => $privacyRequests,
        'module_exports' => $moduleExportResults['exports'],
        'module_export_status' => $moduleExportResults['status'],
        'partial_export' => $moduleExportResults['partial_export'],
    ];
}

function sr_privacy_module_exports(PDO $pdo, int $accountId): array
{
    $results = sr_privacy_module_export_results($pdo, $accountId, sr_now());

    return $results['exports'];
}

function sr_privacy_module_export_results(PDO $pdo, int $accountId, string $exportedAt): array
{
    $exports = [];
    $statuses = [];
    $partialExport = false;
    foreach (sr_enabled_module_contract_files($pdo, 'privacy-export.php', ['privacy']) as $moduleKey => $exportFile) {
        try {
            $moduleExport = sr_load_module_contract_file($moduleKey, $exportFile);
            if (is_callable($moduleExport)) {
                $moduleExportData = $moduleExport($pdo, $accountId);
                if (is_array($moduleExportData)) {
                    $exports[$moduleKey] = sr_privacy_export_sanitize_module_data($moduleExportData);
                    $statuses[$moduleKey] = [
                        'status' => $exports[$moduleKey] === [] ? 'empty' : 'success',
                    ];
                } else {
                    $statuses[$moduleKey] = [
                        'status' => 'skipped',
                        'error_code' => 'module_export_non_array',
                    ];
                    $partialExport = true;
                }
            } elseif (is_array($moduleExport)) {
                $exports[$moduleKey] = sr_privacy_export_sanitize_module_data($moduleExport);
                $statuses[$moduleKey] = [
                    'status' => $exports[$moduleKey] === [] ? 'empty' : 'success',
                ];
            } else {
                $statuses[$moduleKey] = [
                    'status' => 'skipped',
                    'error_code' => 'module_export_invalid_contract',
                ];
                $partialExport = true;
            }
        } catch (Throwable $exception) {
            $evidenceId = sr_privacy_export_evidence_id('privacy_export_module_' . (string) $moduleKey, $accountId, $exportedAt);
            sr_log_exception($exception, 'privacy_export_module_' . $moduleKey . '_evidence_' . $evidenceId);
            $statuses[$moduleKey] = [
                'status' => 'failed',
                'error_code' => 'module_export_exception',
                'evidence_id' => $evidenceId,
            ];
            $partialExport = true;
        }
    }

    return [
        'exports' => $exports,
        'status' => $statuses,
        'partial_export' => $partialExport,
    ];
}

function sr_privacy_export_evidence_id(string $scope, int $subjectId, string $exportedAt): string
{
    $scope = strtolower($scope);
    $safeScope = preg_replace('/[^a-z0-9_]+/', '_', $scope) ?? 'privacy_export';
    $safeScope = trim($safeScope, '_');
    if ($safeScope === '') {
        $safeScope = 'privacy_export';
    }
    $safeTime = preg_replace('/[^0-9]+/', '', $exportedAt) ?? '';
    if ($safeTime === '') {
        $safeTime = date('YmdHis');
    }
    $digest = substr(sha1($safeScope . '|' . $subjectId . '|' . $exportedAt), 0, 12);

    return $safeScope . '_' . $subjectId . '_' . $safeTime . '_' . $digest;
}

function sr_privacy_export_sanitize_module_data(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    $sanitized = [];
    foreach ($value as $key => $childValue) {
        if (is_string($key) && sr_privacy_export_internal_key($key)) {
            continue;
        }

        $sanitized[$key] = sr_privacy_export_sanitize_module_data($childValue);
    }

    return $sanitized;
}

function sr_privacy_export_internal_key(string $key): bool
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

function sr_admin_privacy_request_export_reauth_errors(PDO $pdo, array $account, int $requestId): array
{
    $password = sr_post_string('admin_password', 255);
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return ['관리자 재인증 계정을 확인할 수 없습니다.'];
    }

    $throttle = sr_member_reauth_throttle_status($pdo, $accountId);
    if (!empty($throttle['limited'])) {
        sr_member_log_auth($pdo, $accountId, 'reauth_blocked', 'failure');
        sr_audit_log($pdo, [
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
        sr_member_log_auth($pdo, $accountId, 'privacy_request_export_reauth', 'failure');
        sr_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'privacy.request.export_reauth_failed',
            'target_type' => 'privacy_request',
            'target_id' => (string) $requestId,
            'result' => 'failure',
            'message' => 'Privacy request export reauthentication failed.',
        ]);
        return ['개인정보 요청 대응 자료를 내려받기 전 관리자 비밀번호를 다시 입력하세요.'];
    }

    sr_member_log_auth($pdo, $accountId, 'privacy_request_export_reauth', 'success');
    return [];
}

function sr_admin_log_privacy_request_export(PDO $pdo, array $account, int $requestId): void
{
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'privacy.request.exported',
        'target_type' => 'privacy_request',
        'target_id' => (string) $requestId,
        'result' => 'success',
        'message' => 'Privacy request export downloaded.',
    ]);
}
