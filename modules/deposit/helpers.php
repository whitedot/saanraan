<?php

declare(strict_types=1);

function sr_deposit_balance(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT balance FROM sr_deposit_balances WHERE account_id = :account_id LIMIT 1');
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) $row['balance'] : 0;
}

function sr_deposit_admin_adjustment_once_limit(): int
{
    return 10000000;
}

function sr_deposit_admin_adjustment_daily_limit(): int
{
    return 10000000;
}

function sr_deposit_admin_adjustment_approval_threshold(): int
{
    return 1000000;
}

function sr_deposit_refund_min_amount(): int
{
    return 1000;
}

function sr_deposit_refund_max_amount(): int
{
    return 10000000;
}

function sr_deposit_refund_all_members_key(): string
{
    return '__all__';
}

function sr_deposit_default_settings(): array
{
    return [
        'refund_requests_enabled' => false,
        'refund_allowed_group_keys_json' => '[]',
    ];
}

function sr_deposit_settings(PDO $pdo): array
{
    $settings = array_merge(sr_deposit_default_settings(), sr_module_settings($pdo, 'deposit'));
    $settings['refund_requests_enabled'] = sr_deposit_truthy($settings['refund_requests_enabled'] ?? false);
    $settings['refund_allowed_group_keys'] = sr_deposit_normalize_group_keys(
        sr_deposit_json_array((string) ($settings['refund_allowed_group_keys_json'] ?? '[]'))
    );

    return $settings;
}

function sr_deposit_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'deposit' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('예치금 모듈이 등록되어 있지 않습니다.');
    }

    $allowedGroupKeys = sr_deposit_normalize_group_keys($settings['refund_allowed_group_keys'] ?? []);
    $refundRequestsEnabled = !empty($settings['refund_requests_enabled']);
    foreach ($allowedGroupKeys as $groupKey) {
        if ($groupKey === sr_deposit_refund_all_members_key()) {
            continue;
        }
        if (!sr_member_group_exists($pdo, $groupKey)) {
            throw new InvalidArgumentException('Deposit refund group does not exist.');
        }
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => 'refund_allowed_group_keys_json',
        'setting_value' => json_encode(array_values($allowedGroupKeys), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'value_type' => 'string',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => 'refund_requests_enabled',
        'setting_value' => $refundRequestsEnabled ? '1' : '0',
        'value_type' => 'bool',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    sr_clear_module_settings_cache('deposit');
}

function sr_deposit_truthy(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
}

function sr_deposit_json_array(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function sr_deposit_normalize_group_keys(mixed $groupKeys): array
{
    if (!is_array($groupKeys)) {
        return [];
    }

    $normalized = [];
    foreach ($groupKeys as $groupKey) {
        $groupKey = (string) $groupKey;
        if ($groupKey === sr_deposit_refund_all_members_key()) {
            return [sr_deposit_refund_all_members_key()];
        }
        if (sr_member_group_key_is_valid($groupKey)) {
            $normalized[$groupKey] = true;
        }
    }

    return array_keys($normalized);
}

function sr_deposit_refund_allowed_group_keys(PDO $pdo): array
{
    $settings = sr_deposit_settings($pdo);
    return isset($settings['refund_allowed_group_keys']) && is_array($settings['refund_allowed_group_keys'])
        ? $settings['refund_allowed_group_keys']
        : [];
}

function sr_deposit_refund_requests_enabled(PDO $pdo): bool
{
    $settings = sr_deposit_settings($pdo);
    return !empty($settings['refund_requests_enabled']);
}

function sr_deposit_account_can_request_refund(PDO $pdo, int $accountId): bool
{
    if ($accountId <= 0) {
        return false;
    }
    if (!sr_deposit_refund_requests_enabled($pdo)) {
        return false;
    }

    $allowedGroupKeys = sr_deposit_refund_allowed_group_keys($pdo);
    if ($allowedGroupKeys === []) {
        return false;
    }

    if (in_array(sr_deposit_refund_all_members_key(), $allowedGroupKeys, true)) {
        return true;
    }

    return sr_member_account_in_any_group($pdo, $accountId, $allowedGroupKeys);
}

function sr_deposit_validate_admin_adjustment_limit(PDO $pdo, array $runtimeConfig, int $adminAccountId, string $permissionPath, int $amount, string $approvalIdentifier = '', string $approvalNote = ''): array
{
    $absoluteAmount = abs($amount);
    if ($absoluteAmount > sr_deposit_admin_adjustment_once_limit()) {
        return ['error' => '예치금 관리자 조정 금액이 1회 상한을 초과했습니다.', 'approval_account_id' => 0];
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(ABS(amount)), 0) AS total_amount
         FROM sr_deposit_transactions
         WHERE created_by_account_id = :admin_account_id
           AND created_at >= :started_at
           AND transaction_type IN ('adjustment', 'deposit', 'use', 'refund', 'withdraw')"
    );
    $stmt->execute([
        'admin_account_id' => $adminAccountId,
        'started_at' => date('Y-m-d 00:00:00'),
    ]);
    $row = $stmt->fetch();
    $usedAmount = is_array($row) ? (int) ($row['total_amount'] ?? 0) : 0;

    if ($usedAmount + $absoluteAmount > sr_deposit_admin_adjustment_daily_limit()) {
        return ['error' => '예치금 관리자 조정 금액이 일일 상한을 초과했습니다.', 'approval_account_id' => 0];
    }

    if ($absoluteAmount <= sr_deposit_admin_adjustment_approval_threshold()) {
        return ['error' => null, 'approval_account_id' => 0];
    }

    $approvalAccountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $approvalIdentifier);
    if ($approvalAccountId <= 0) {
        return ['error' => '대액 예치금 조정은 승인자 식별자가 필요합니다.', 'approval_account_id' => 0];
    }
    if ($approvalAccountId === $adminAccountId) {
        return ['error' => '대액 예치금 조정은 처리자와 다른 승인자가 필요합니다.', 'approval_account_id' => 0];
    }
    $stmt = $pdo->prepare("SELECT status FROM sr_member_accounts WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $approvalAccountId]);
    $approvalAccount = $stmt->fetch();
    if (!is_array($approvalAccount) || (string) ($approvalAccount['status'] ?? '') !== 'active') {
        return ['error' => '대액 예치금 조정 승인자 계정이 활성 상태가 아닙니다.', 'approval_account_id' => 0];
    }
    if (!sr_admin_has_permission($pdo, $approvalAccountId, $permissionPath, 'edit')) {
        return ['error' => '대액 예치금 조정 승인자에게 해당 관리자 편집 권한이 없습니다.', 'approval_account_id' => 0];
    }
    if (sr_deposit_clean_text($approvalNote, 255) === '') {
        return ['error' => '대액 예치금 조정은 승인 사유가 필요합니다.', 'approval_account_id' => 0];
    }

    return ['error' => null, 'approval_account_id' => $approvalAccountId];
}

function sr_deposit_create_transaction(PDO $pdo, array $data): int
{
    $accountId = (int) ($data['account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);
    $transactionType = sr_deposit_clean_key((string) ($data['transaction_type'] ?? 'adjustment'), 40);
    $reason = sr_deposit_clean_text((string) ($data['reason'] ?? ''), 255);
    $referenceType = sr_deposit_clean_key((string) ($data['reference_type'] ?? ''), 60);
    $referenceId = sr_deposit_clean_reference_id((string) ($data['reference_id'] ?? ''), 120);
    $createdByAccountId = isset($data['created_by_account_id']) ? (int) $data['created_by_account_id'] : null;

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Account id is required.');
    }

    if ($amount === 0) {
        throw new InvalidArgumentException('Amount must not be zero.');
    }

    if (!sr_deposit_transaction_type_allows_amount($transactionType, $amount)) {
        throw new InvalidArgumentException('Deposit transaction amount sign is invalid for type.');
    }
    if ($transactionType === 'refund' && ($referenceType !== 'refund' || preg_match('/\Adeposit_transaction:([0-9]+)\z/', $referenceId) !== 1)) {
        throw new InvalidArgumentException('Deposit refund reference is required.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $transactionId = sr_ledger_create_transaction($pdo, [
            'balance_table' => 'sr_deposit_balances',
            'transaction_table' => 'sr_deposit_transactions',
            'balance_row_error' => 'Deposit balance row was not created.',
            'negative_balance_error' => 'Deposit balance cannot be negative.',
        ], [
            'account_id' => $accountId,
            'amount' => $amount,
            'transaction_type' => $transactionType,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by_account_id' => $createdByAccountId,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
            sr_deposit_notify_transaction_created($pdo, $transactionId);
        } else {
            sr_deposit_defer_transaction_notification($pdo, $transactionId);
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $transactionId;
}

function sr_deposit_defer_transaction_notification(PDO $pdo, int $transactionId): void
{
    if ($transactionId <= 0) {
        return;
    }

    register_shutdown_function(static function () use ($pdo, $transactionId): void {
        if ($pdo->inTransaction()) {
            return;
        }
        sr_deposit_notify_transaction_created($pdo, $transactionId);
    });
}

function sr_deposit_transaction_type_allows_amount(string $transactionType, int $amount): bool
{
    if ($amount === 0) {
        return false;
    }

    if (in_array($transactionType, ['deposit', 'refund', 'exchange_in'], true)) {
        return $amount > 0;
    }

    if (in_array($transactionType, ['use', 'withdraw', 'exchange_out', 'exchange_fee'], true)) {
        return $amount < 0;
    }

    return $transactionType === 'adjustment';
}

function sr_deposit_clean_key(string $value, int $maxLength): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_.-]/', '', strtolower($value));
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_deposit_clean_reference_id(string $value, int $maxLength): string
{
    $value = trim($value);
    $value = preg_replace('/[^a-zA-Z0-9_.:-]/', '', $value);
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_deposit_clean_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_deposit_pending_refund_amount(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS pending_amount
         FROM sr_deposit_refund_requests
         WHERE account_id = :account_id
           AND status = 'pending'"
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['pending_amount'] ?? 0) : 0;
}

function sr_deposit_refund_available_amount(PDO $pdo, int $accountId): int
{
    return max(0, sr_deposit_balance($pdo, $accountId) - sr_deposit_pending_refund_amount($pdo, $accountId));
}

function sr_deposit_request_status_label(string $status): string
{
    $labels = [
        'pending' => '대기',
        'completed' => '완료',
        'rejected' => '거부',
        'canceled' => '취소',
    ];

    return $labels[$status] ?? $status;
}

function sr_deposit_create_refund_request(PDO $pdo, int $accountId, array $data): int
{
    $amount = (int) ($data['amount'] ?? 0);
    $bankName = sr_deposit_clean_text((string) ($data['bank_name'] ?? ''), 80);
    $bankAccountNumber = sr_deposit_clean_text((string) ($data['bank_account_number'] ?? ''), 80);
    $bankAccountHolder = sr_deposit_clean_text((string) ($data['bank_account_holder'] ?? ''), 80);
    $requesterNote = sr_deposit_clean_text((string) ($data['requester_note'] ?? ''), 255);

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Account id is required.');
    }
    if ($amount < sr_deposit_refund_min_amount()) {
        throw new InvalidArgumentException('Deposit refund amount is below minimum.');
    }
    if ($amount > sr_deposit_refund_max_amount()) {
        throw new InvalidArgumentException('Deposit refund amount is above maximum.');
    }
    if ($bankName === '' || $bankAccountNumber === '' || $bankAccountHolder === '') {
        throw new InvalidArgumentException('Deposit refund bank fields are required.');
    }
    if (!sr_deposit_refund_requests_enabled($pdo)) {
        throw new RuntimeException('Deposit refund requests are disabled.');
    }
    if (!sr_deposit_account_can_request_refund($pdo, $accountId)) {
        throw new RuntimeException('Deposit refund account is not in an allowed group.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT balance FROM sr_deposit_balances WHERE account_id = :account_id LIMIT 1 FOR UPDATE');
        $stmt->execute(['account_id' => $accountId]);
        $row = $stmt->fetch();
        $balance = is_array($row) ? (int) ($row['balance'] ?? 0) : 0;
        $availableAmount = max(0, $balance - sr_deposit_pending_refund_amount($pdo, $accountId));
        if ($amount > $availableAmount) {
            throw new RuntimeException('Deposit refund amount exceeds available balance.');
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "INSERT INTO sr_deposit_refund_requests
             (account_id, amount, bank_name, bank_account_number, bank_account_holder, requester_note, status, requested_at, updated_at)
             VALUES
             (:account_id, :amount, :bank_name, :bank_account_number, :bank_account_holder, :requester_note, 'pending', :requested_at, :updated_at)"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'amount' => $amount,
            'bank_name' => $bankName,
            'bank_account_number' => $bankAccountNumber,
            'bank_account_holder' => $bankAccountHolder,
            'requester_note' => $requesterNote,
            'requested_at' => $now,
            'updated_at' => $now,
        ]);
        $requestId = (int) $pdo->lastInsertId();

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $requestId;
}

function sr_deposit_refund_requests_for_account(PDO $pdo, int $accountId, int $limit = 20): array
{
    if ($accountId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        'SELECT id, amount, bank_name, bank_account_number, bank_account_holder, requester_note, status, admin_note, transaction_id, requested_at, processed_at, updated_at
         FROM sr_deposit_refund_requests
         WHERE account_id = :account_id
         ORDER BY id DESC
         LIMIT ' . $limit
    );
    $stmt->execute(['account_id' => $accountId]);

    return $stmt->fetchAll();
}

function sr_deposit_admin_refund_request_count(PDO $pdo, $status, string $field = 'all', string $keyword = ''): int
{
    $filter = sr_deposit_admin_refund_request_filter_sql($status, $field, $keyword);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_deposit_refund_requests r
         LEFT JOIN sr_member_accounts a ON a.id = r.account_id
         {$filter['where']}"
    );
    $stmt->execute($filter['params']);

    return (int) $stmt->fetchColumn();
}

function sr_deposit_admin_refund_request_pending_ids(PDO $pdo, string $field = 'all', string $keyword = '', int $limit = 101): array
{
    $limit = max(1, min(500, $limit));
    $filter = sr_deposit_admin_refund_request_filter_sql('pending', $field, $keyword);
    $params = $filter['params'];

    $stmt = $pdo->prepare(
        "SELECT r.id
         FROM sr_deposit_refund_requests r
         LEFT JOIN sr_member_accounts a ON a.id = r.account_id
         {$filter['where']}
         ORDER BY r.id ASC
         LIMIT :limit"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function sr_deposit_admin_refund_request_rows(PDO $pdo, array $runtimeConfig, $status, array $pagination, string $field = 'all', string $keyword = ''): array
{
    $filter = sr_deposit_admin_refund_request_filter_sql($status, $field, $keyword);
    $params = $filter['params'];

    $stmt = $pdo->prepare(
        "SELECT r.id, r.account_id, r.amount, r.bank_name, r.bank_account_number, r.bank_account_holder,
                r.requester_note, r.status, r.admin_note, r.transaction_id, r.processed_by_account_id,
                r.requested_at, r.processed_at, r.updated_at,
                a.email, a.display_name, a.status AS account_status
         FROM sr_deposit_refund_requests r
         LEFT JOIN sr_member_accounts a ON a.id = r.account_id
         {$filter['where']}
         ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, r.id DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', max(1, (int) ($pagination['per_page'] ?? 20)), PDO::PARAM_INT);
    $stmt->bindValue(':offset', sr_admin_pagination_offset($pagination), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            continue;
        }
        $rows[$index]['account_public_hash'] = sr_admin_member_public_hash($runtimeConfig, (int) ($row['account_id'] ?? 0));
    }

    return $rows;
}

function sr_deposit_admin_refund_request_filter_sql($status, string $field, string $keyword): array
{
    $where = [];
    $params = [];
    $statuses = is_array($status) ? $status : ($status === '' ? [] : [(string) $status]);
    $statuses = array_values(array_intersect($statuses, ['pending', 'completed', 'rejected', 'canceled']));
    if ($statuses !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.status', 'status', $statuses);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $field = in_array($field, ['all', 'member', 'bank', 'note', 'request'], true) ? $field : 'all';
    $keyword = sr_deposit_clean_text($keyword, 120);
    if ($keyword !== '') {
        $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
        if ($field === 'member') {
            $where[] = "(CAST(r.account_id AS CHAR) LIKE :keyword_like ESCAPE '\\\\' OR a.email LIKE :keyword_like ESCAPE '\\\\' OR a.display_name LIKE :keyword_like ESCAPE '\\\\')";
            $params['keyword_like'] = $keywordLike;
        } elseif ($field === 'bank') {
            $where[] = "(r.bank_name LIKE :keyword_like ESCAPE '\\\\' OR r.bank_account_number LIKE :keyword_like ESCAPE '\\\\' OR r.bank_account_holder LIKE :keyword_like ESCAPE '\\\\')";
            $params['keyword_like'] = $keywordLike;
        } elseif ($field === 'note') {
            $where[] = "(r.requester_note LIKE :keyword_like ESCAPE '\\\\' OR r.admin_note LIKE :keyword_like ESCAPE '\\\\')";
            $params['keyword_like'] = $keywordLike;
        } elseif ($field === 'request') {
            $where[] = "(CAST(r.id AS CHAR) LIKE :keyword_like ESCAPE '\\\\' OR CAST(r.transaction_id AS CHAR) LIKE :keyword_like ESCAPE '\\\\')";
            $params['keyword_like'] = $keywordLike;
        } else {
            $where[] = "(CAST(r.id AS CHAR) LIKE :request_keyword_like ESCAPE '\\\\'
                OR CAST(r.transaction_id AS CHAR) LIKE :transaction_keyword_like ESCAPE '\\\\'
                OR CAST(r.account_id AS CHAR) LIKE :account_keyword_like ESCAPE '\\\\'
                OR a.email LIKE :email_keyword_like ESCAPE '\\\\'
                OR a.display_name LIKE :name_keyword_like ESCAPE '\\\\'
                OR r.bank_name LIKE :bank_keyword_like ESCAPE '\\\\'
                OR r.bank_account_number LIKE :bank_number_keyword_like ESCAPE '\\\\'
                OR r.bank_account_holder LIKE :bank_holder_keyword_like ESCAPE '\\\\'
                OR r.requester_note LIKE :requester_note_keyword_like ESCAPE '\\\\'
                OR r.admin_note LIKE :admin_note_keyword_like ESCAPE '\\\\')";
            $params['request_keyword_like'] = $keywordLike;
            $params['transaction_keyword_like'] = $keywordLike;
            $params['account_keyword_like'] = $keywordLike;
            $params['email_keyword_like'] = $keywordLike;
            $params['name_keyword_like'] = $keywordLike;
            $params['bank_keyword_like'] = $keywordLike;
            $params['bank_number_keyword_like'] = $keywordLike;
            $params['bank_holder_keyword_like'] = $keywordLike;
            $params['requester_note_keyword_like'] = $keywordLike;
            $params['admin_note_keyword_like'] = $keywordLike;
        }
    }

    return [
        'where' => $where === [] ? '' : 'WHERE ' . implode(' AND ', $where),
        'params' => $params,
    ];
}

function sr_deposit_complete_refund_request(PDO $pdo, int $requestId, int $adminAccountId, string $adminNote): int
{
    $adminNote = sr_deposit_clean_text($adminNote, 255);
    if ($requestId <= 0 || $adminAccountId <= 0) {
        throw new InvalidArgumentException('Deposit refund request and admin account are required.');
    }
    if ($adminNote === '') {
        throw new InvalidArgumentException('Deposit refund admin note is required.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM sr_deposit_refund_requests WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();
        if (!is_array($request) || (string) ($request['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Deposit refund request is not pending.');
        }

        $transactionId = sr_deposit_create_transaction($pdo, [
            'account_id' => (int) $request['account_id'],
            'amount' => -abs((int) $request['amount']),
            'transaction_type' => 'withdraw',
            'reason' => '예치금 환불 신청 #' . (string) $requestId . ' 완료',
            'reference_type' => 'deposit_refund',
            'reference_id' => 'deposit_refund:' . (string) $requestId,
            'created_by_account_id' => $adminAccountId,
        ]);

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE sr_deposit_refund_requests
             SET status = 'completed',
                 admin_note = :admin_note,
                 transaction_id = :transaction_id,
                 processed_by_account_id = :processed_by_account_id,
                 processed_at = :processed_at,
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute([
            'admin_note' => $adminNote,
            'transaction_id' => $transactionId,
            'processed_by_account_id' => $adminAccountId,
            'processed_at' => $now,
            'updated_at' => $now,
            'id' => $requestId,
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $transactionId;
}

function sr_deposit_reject_refund_request(PDO $pdo, int $requestId, int $adminAccountId, string $adminNote): void
{
    $adminNote = sr_deposit_clean_text($adminNote, 255);
    if ($requestId <= 0 || $adminAccountId <= 0) {
        throw new InvalidArgumentException('Deposit refund request and admin account are required.');
    }
    if ($adminNote === '') {
        throw new InvalidArgumentException('Deposit refund rejection note is required.');
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        "UPDATE sr_deposit_refund_requests
         SET status = 'rejected',
             admin_note = :admin_note,
             processed_by_account_id = :processed_by_account_id,
             processed_at = :processed_at,
             updated_at = :updated_at
         WHERE id = :id
           AND status = 'pending'"
    );
    $stmt->execute([
        'admin_note' => $adminNote,
        'processed_by_account_id' => $adminAccountId,
        'processed_at' => $now,
        'updated_at' => $now,
        'id' => $requestId,
    ]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Deposit refund request is not pending.');
    }
}

function sr_deposit_cancel_refund_request(PDO $pdo, int $requestId, int $accountId): void
{
    if ($requestId <= 0 || $accountId <= 0) {
        throw new InvalidArgumentException('Deposit refund request and account are required.');
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_deposit_refund_requests
         SET status = 'canceled',
             updated_at = :updated_at
         WHERE id = :id
           AND account_id = :account_id
           AND status = 'pending'"
    );
    $stmt->execute([
        'updated_at' => date('Y-m-d H:i:s'),
        'id' => $requestId,
        'account_id' => $accountId,
    ]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Deposit refund request could not be canceled.');
    }
}

function sr_deposit_transaction_by_id(PDO $pdo, int $transactionId): ?array
{
    if ($transactionId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, created_at
         FROM sr_deposit_transactions
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $transactionId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_deposit_notify_transaction_created(PDO $pdo, int $transactionId): ?int
{
    $createAccountEventFunction = sr_deposit_notification_event_function($pdo);
    if ($createAccountEventFunction === '') {
        return null;
    }

    $transaction = sr_deposit_transaction_by_id($pdo, $transactionId);
    if (!is_array($transaction)) {
        return null;
    }

    try {
        $amount = (int) $transaction['amount'];
        $transactionType = (string) $transaction['transaction_type'];
        $eventKey = $transactionType === 'adjustment'
            ? 'transaction.adjustment.' . ($amount > 0 ? 'increase' : 'decrease')
            : 'transaction.' . $transactionType;

        return $createAccountEventFunction($pdo, [
            'account_id' => (int) $transaction['account_id'],
            'module_key' => 'deposit',
            'event_key' => $eventKey,
            'created_by_account_id' => (int) ($transaction['created_by_account_id'] ?? 0),
            'metadata' => sr_deposit_transaction_notification_metadata($transaction),
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'deposit_transaction_notification');
        return null;
    }
}

function sr_deposit_notification_event_function(PDO $pdo): string
{
    if (!sr_module_enabled($pdo, 'notification') || !sr_module_contract_is_loadable('notification')) {
        return '';
    }

    $contractFile = SR_ROOT . '/modules/notification/notification-events.php';
    $contract = sr_load_module_contract_file('notification', $contractFile);
    if (!is_array($contract)) {
        return '';
    }

    $helpers = (string) ($contract['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $helperPath = SR_ROOT . '/modules/notification/' . $helpers;
    if (!is_file($helperPath)) {
        return '';
    }

    require_once $helperPath;

    $function = (string) ($contract['create_account_event_function'] ?? '');
    return $function !== '' && function_exists($function) ? $function : '';
}

function sr_deposit_transaction_notification_metadata(array $transaction): array
{
    $amount = (int) ($transaction['amount'] ?? 0);

    return [
        'transaction_id' => (int) ($transaction['id'] ?? 0),
        'asset_label' => '예치금',
        'amount' => number_format($amount),
        'amount_abs' => number_format(abs($amount)),
        'amount_signed' => ($amount > 0 ? '+' : '') . number_format($amount),
        'balance_after' => number_format((int) ($transaction['balance_after'] ?? 0)),
        'transaction_type' => (string) ($transaction['transaction_type'] ?? ''),
        'reason' => (string) ($transaction['reason'] ?? ''),
        'reference_type' => (string) ($transaction['reference_type'] ?? ''),
        'reference_id' => (string) ($transaction['reference_id'] ?? ''),
        'created_at' => (string) ($transaction['created_at'] ?? ''),
    ];
}
