<?php

declare(strict_types=1);

function sr_reward_balance(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT balance FROM sr_reward_balances WHERE account_id = :account_id LIMIT 1');
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) $row['balance'] : 0;
}

function sr_reward_admin_adjustment_once_limit(): int
{
    return 10000000;
}

function sr_reward_admin_adjustment_daily_limit(): int
{
    return 10000000;
}

function sr_reward_admin_adjustment_approval_threshold(): int
{
    return 1000000;
}

function sr_reward_withdrawal_min_amount(): int
{
    return 1000;
}

function sr_reward_withdrawal_max_amount(): int
{
    return 10000000;
}

function sr_reward_default_settings(): array
{
    return [
        'withdrawal_allowed_group_keys_json' => '[]',
    ];
}

function sr_reward_settings(PDO $pdo): array
{
    $settings = array_merge(sr_reward_default_settings(), sr_module_settings($pdo, 'reward'));
    $settings['withdrawal_allowed_group_keys'] = sr_reward_normalize_group_keys(
        sr_reward_json_array((string) ($settings['withdrawal_allowed_group_keys_json'] ?? '[]'))
    );

    return $settings;
}

function sr_reward_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'reward' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('적립금 모듈이 등록되어 있지 않습니다.');
    }

    $allowedGroupKeys = sr_reward_normalize_group_keys($settings['withdrawal_allowed_group_keys'] ?? []);
    foreach ($allowedGroupKeys as $groupKey) {
        if (!sr_member_group_exists($pdo, $groupKey)) {
            throw new InvalidArgumentException('Reward withdrawal group does not exist.');
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
        'setting_key' => 'withdrawal_allowed_group_keys_json',
        'setting_value' => json_encode(array_values($allowedGroupKeys), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'value_type' => 'string',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    sr_clear_module_settings_cache('reward');
}

function sr_reward_json_array(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function sr_reward_normalize_group_keys(mixed $groupKeys): array
{
    if (!is_array($groupKeys)) {
        return [];
    }

    $normalized = [];
    foreach ($groupKeys as $groupKey) {
        $groupKey = (string) $groupKey;
        if (sr_member_group_key_is_valid($groupKey)) {
            $normalized[$groupKey] = true;
        }
    }

    return array_keys($normalized);
}

function sr_reward_withdrawal_allowed_group_keys(PDO $pdo): array
{
    $settings = sr_reward_settings($pdo);
    return isset($settings['withdrawal_allowed_group_keys']) && is_array($settings['withdrawal_allowed_group_keys'])
        ? $settings['withdrawal_allowed_group_keys']
        : [];
}

function sr_reward_account_can_request_withdrawal(PDO $pdo, int $accountId): bool
{
    $allowedGroupKeys = sr_reward_withdrawal_allowed_group_keys($pdo);
    if ($allowedGroupKeys === []) {
        return true;
    }

    return sr_member_account_in_any_group($pdo, $accountId, $allowedGroupKeys);
}

function sr_reward_validate_admin_adjustment_limit(PDO $pdo, array $runtimeConfig, int $adminAccountId, string $permissionPath, int $amount, string $approvalIdentifier = '', string $approvalNote = ''): array
{
    $absoluteAmount = abs($amount);
    if ($absoluteAmount > sr_reward_admin_adjustment_once_limit()) {
        return ['error' => '적립금 관리자 조정 금액이 1회 상한을 초과했습니다.', 'approval_account_id' => 0];
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(ABS(amount)), 0) AS total_amount
         FROM sr_reward_transactions
         WHERE created_by_account_id = :admin_account_id
           AND created_at >= :started_at
           AND transaction_type IN ('adjustment', 'grant', 'use', 'refund', 'expire')"
    );
    $stmt->execute([
        'admin_account_id' => $adminAccountId,
        'started_at' => date('Y-m-d 00:00:00'),
    ]);
    $row = $stmt->fetch();
    $usedAmount = is_array($row) ? (int) ($row['total_amount'] ?? 0) : 0;

    if ($usedAmount + $absoluteAmount > sr_reward_admin_adjustment_daily_limit()) {
        return ['error' => '적립금 관리자 조정 금액이 일일 상한을 초과했습니다.', 'approval_account_id' => 0];
    }

    if ($absoluteAmount <= sr_reward_admin_adjustment_approval_threshold()) {
        return ['error' => null, 'approval_account_id' => 0];
    }

    $approvalAccountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $approvalIdentifier);
    if ($approvalAccountId <= 0) {
        return ['error' => '대액 적립금 조정은 승인자 식별자가 필요합니다.', 'approval_account_id' => 0];
    }
    if ($approvalAccountId === $adminAccountId) {
        return ['error' => '대액 적립금 조정은 처리자와 다른 승인자가 필요합니다.', 'approval_account_id' => 0];
    }
    $stmt = $pdo->prepare("SELECT status FROM sr_member_accounts WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $approvalAccountId]);
    $approvalAccount = $stmt->fetch();
    if (!is_array($approvalAccount) || (string) ($approvalAccount['status'] ?? '') !== 'active') {
        return ['error' => '대액 적립금 조정 승인자 계정이 활성 상태가 아닙니다.', 'approval_account_id' => 0];
    }
    if (!sr_admin_has_permission($pdo, $approvalAccountId, $permissionPath, 'edit')) {
        return ['error' => '대액 적립금 조정 승인자에게 해당 관리자 편집 권한이 없습니다.', 'approval_account_id' => 0];
    }
    if (sr_reward_clean_text($approvalNote, 255) === '') {
        return ['error' => '대액 적립금 조정은 승인 사유가 필요합니다.', 'approval_account_id' => 0];
    }

    return ['error' => null, 'approval_account_id' => $approvalAccountId];
}

function sr_reward_create_transaction(PDO $pdo, array $data): int
{
    $accountId = (int) ($data['account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);
    $transactionType = sr_reward_clean_key((string) ($data['transaction_type'] ?? 'adjustment'), 40);
    $reason = sr_reward_clean_text((string) ($data['reason'] ?? ''), 255);
    $referenceType = sr_reward_clean_key((string) ($data['reference_type'] ?? ''), 60);
    $referenceId = sr_reward_clean_reference_id((string) ($data['reference_id'] ?? ''), 120);
    $createdByAccountId = isset($data['created_by_account_id']) ? (int) $data['created_by_account_id'] : null;

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Account id is required.');
    }

    if ($amount === 0) {
        throw new InvalidArgumentException('Amount must not be zero.');
    }

    if (!sr_reward_transaction_type_allows_amount($transactionType, $amount)) {
        throw new InvalidArgumentException('Reward transaction amount sign is invalid for type.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $transactionId = sr_ledger_create_transaction($pdo, [
            'balance_table' => 'sr_reward_balances',
            'transaction_table' => 'sr_reward_transactions',
            'balance_row_error' => 'Reward balance row was not created.',
            'negative_balance_error' => 'Reward balance cannot be negative.',
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
            sr_reward_notify_transaction_created($pdo, $transactionId);
        } else {
            sr_reward_defer_transaction_notification($pdo, $transactionId);
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $transactionId;
}

function sr_reward_defer_transaction_notification(PDO $pdo, int $transactionId): void
{
    if ($transactionId <= 0) {
        return;
    }

    register_shutdown_function(static function () use ($pdo, $transactionId): void {
        if ($pdo->inTransaction()) {
            return;
        }
        sr_reward_notify_transaction_created($pdo, $transactionId);
    });
}

function sr_reward_transaction_type_allows_amount(string $transactionType, int $amount): bool
{
    if ($amount === 0) {
        return false;
    }

    if (in_array($transactionType, ['grant', 'refund', 'exchange_in'], true)) {
        return $amount > 0;
    }

    if (in_array($transactionType, ['use', 'expire', 'withdraw', 'exchange_out', 'exchange_fee'], true)) {
        return $amount < 0;
    }

    return $transactionType === 'adjustment';
}

function sr_reward_clean_key(string $value, int $maxLength): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_.-]/', '', strtolower($value));
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_reward_clean_reference_id(string $value, int $maxLength): string
{
    $value = trim($value);
    $value = preg_replace('/[^a-zA-Z0-9_.:-]/', '', $value);
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_reward_clean_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_reward_pending_withdrawal_amount(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS pending_amount
         FROM sr_reward_withdrawal_requests
         WHERE account_id = :account_id
           AND status = 'pending'"
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['pending_amount'] ?? 0) : 0;
}

function sr_reward_withdrawal_available_amount(PDO $pdo, int $accountId): int
{
    return max(0, sr_reward_balance($pdo, $accountId) - sr_reward_pending_withdrawal_amount($pdo, $accountId));
}

function sr_reward_request_status_label(string $status): string
{
    $labels = [
        'pending' => '대기',
        'completed' => '완료',
        'rejected' => '반려',
        'canceled' => '취소',
    ];

    return $labels[$status] ?? $status;
}

function sr_reward_create_withdrawal_request(PDO $pdo, int $accountId, array $data): int
{
    $amount = (int) ($data['amount'] ?? 0);
    $bankName = sr_reward_clean_text((string) ($data['bank_name'] ?? ''), 80);
    $bankAccountNumber = sr_reward_clean_text((string) ($data['bank_account_number'] ?? ''), 80);
    $bankAccountHolder = sr_reward_clean_text((string) ($data['bank_account_holder'] ?? ''), 80);
    $requesterNote = sr_reward_clean_text((string) ($data['requester_note'] ?? ''), 255);

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Account id is required.');
    }
    if ($amount < sr_reward_withdrawal_min_amount()) {
        throw new InvalidArgumentException('Reward withdrawal amount is below minimum.');
    }
    if ($amount > sr_reward_withdrawal_max_amount()) {
        throw new InvalidArgumentException('Reward withdrawal amount is above maximum.');
    }
    if ($bankName === '' || $bankAccountNumber === '' || $bankAccountHolder === '') {
        throw new InvalidArgumentException('Reward withdrawal bank fields are required.');
    }
    if (!sr_reward_account_can_request_withdrawal($pdo, $accountId)) {
        throw new RuntimeException('Reward withdrawal account is not in an allowed group.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT balance FROM sr_reward_balances WHERE account_id = :account_id LIMIT 1 FOR UPDATE');
        $stmt->execute(['account_id' => $accountId]);
        $row = $stmt->fetch();
        $balance = is_array($row) ? (int) ($row['balance'] ?? 0) : 0;
        $availableAmount = max(0, $balance - sr_reward_pending_withdrawal_amount($pdo, $accountId));
        if ($amount > $availableAmount) {
            throw new RuntimeException('Reward withdrawal amount exceeds available balance.');
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "INSERT INTO sr_reward_withdrawal_requests
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

function sr_reward_withdrawal_requests_for_account(PDO $pdo, int $accountId, int $limit = 20): array
{
    if ($accountId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        'SELECT id, amount, bank_name, bank_account_number, bank_account_holder, requester_note, status, admin_note, transaction_id, requested_at, processed_at, updated_at
         FROM sr_reward_withdrawal_requests
         WHERE account_id = :account_id
         ORDER BY id DESC
         LIMIT ' . $limit
    );
    $stmt->execute(['account_id' => $accountId]);

    return $stmt->fetchAll();
}

function sr_reward_admin_withdrawal_request_count(PDO $pdo, string $status): int
{
    if ($status !== '' && in_array($status, ['pending', 'completed', 'rejected', 'canceled'], true)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_reward_withdrawal_requests WHERE status = :status');
        $stmt->execute(['status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    return (int) $pdo->query('SELECT COUNT(*) FROM sr_reward_withdrawal_requests')->fetchColumn();
}

function sr_reward_admin_withdrawal_request_rows(PDO $pdo, array $runtimeConfig, string $status, array $pagination): array
{
    $where = '';
    $params = [];
    if ($status !== '' && in_array($status, ['pending', 'completed', 'rejected', 'canceled'], true)) {
        $where = 'WHERE r.status = :status';
        $params['status'] = $status;
    }

    $stmt = $pdo->prepare(
        "SELECT r.id, r.account_id, r.amount, r.bank_name, r.bank_account_number, r.bank_account_holder,
                r.requester_note, r.status, r.admin_note, r.transaction_id, r.processed_by_account_id,
                r.requested_at, r.processed_at, r.updated_at,
                a.email, a.display_name, a.login_id, a.status AS account_status
         FROM sr_reward_withdrawal_requests r
         LEFT JOIN sr_member_accounts a ON a.id = r.account_id
         {$where}
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

function sr_reward_complete_withdrawal_request(PDO $pdo, int $requestId, int $adminAccountId, string $adminNote): int
{
    $adminNote = sr_reward_clean_text($adminNote, 255);
    if ($requestId <= 0 || $adminAccountId <= 0) {
        throw new InvalidArgumentException('Reward withdrawal request and admin account are required.');
    }
    if ($adminNote === '') {
        throw new InvalidArgumentException('Reward withdrawal admin note is required.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM sr_reward_withdrawal_requests WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();
        if (!is_array($request) || (string) ($request['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Reward withdrawal request is not pending.');
        }

        $transactionId = sr_reward_create_transaction($pdo, [
            'account_id' => (int) $request['account_id'],
            'amount' => -abs((int) $request['amount']),
            'transaction_type' => 'withdraw',
            'reason' => '적립금 출금 신청 #' . (string) $requestId . ' 완료',
            'reference_type' => 'reward_withdrawal',
            'reference_id' => 'reward_withdrawal:' . (string) $requestId,
            'created_by_account_id' => $adminAccountId,
        ]);

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE sr_reward_withdrawal_requests
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

function sr_reward_reject_withdrawal_request(PDO $pdo, int $requestId, int $adminAccountId, string $adminNote): void
{
    $adminNote = sr_reward_clean_text($adminNote, 255);
    if ($requestId <= 0 || $adminAccountId <= 0) {
        throw new InvalidArgumentException('Reward withdrawal request and admin account are required.');
    }
    if ($adminNote === '') {
        throw new InvalidArgumentException('Reward withdrawal rejection note is required.');
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        "UPDATE sr_reward_withdrawal_requests
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
        throw new RuntimeException('Reward withdrawal request is not pending.');
    }
}

function sr_reward_cancel_withdrawal_request(PDO $pdo, int $requestId, int $accountId): void
{
    if ($requestId <= 0 || $accountId <= 0) {
        throw new InvalidArgumentException('Reward withdrawal request and account are required.');
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_reward_withdrawal_requests
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
        throw new RuntimeException('Reward withdrawal request could not be canceled.');
    }
}

function sr_reward_transaction_by_id(PDO $pdo, int $transactionId): ?array
{
    if ($transactionId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, created_at
         FROM sr_reward_transactions
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $transactionId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_reward_notify_transaction_created(PDO $pdo, int $transactionId): ?int
{
    if (!sr_module_enabled($pdo, 'notification') || !is_file(SR_ROOT . '/modules/notification/helpers.php')) {
        return null;
    }

    $transaction = sr_reward_transaction_by_id($pdo, $transactionId);
    if (!is_array($transaction)) {
        return null;
    }

    try {
        require_once SR_ROOT . '/modules/notification/helpers.php';
        if (!function_exists('sr_notification_create_account_event')) {
            return null;
        }

        $amount = (int) $transaction['amount'];
        $transactionType = (string) $transaction['transaction_type'];
        $eventKey = $transactionType === 'adjustment'
            ? 'transaction.adjustment.' . ($amount > 0 ? 'increase' : 'decrease')
            : 'transaction.' . $transactionType;

        return sr_notification_create_account_event($pdo, [
            'account_id' => (int) $transaction['account_id'],
            'module_key' => 'reward',
            'event_key' => $eventKey,
            'created_by_account_id' => (int) ($transaction['created_by_account_id'] ?? 0),
            'metadata' => sr_reward_transaction_notification_metadata($transaction),
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'reward_transaction_notification');
        return null;
    }
}

function sr_reward_transaction_notification_metadata(array $transaction): array
{
    $amount = (int) ($transaction['amount'] ?? 0);

    return [
        'transaction_id' => (int) ($transaction['id'] ?? 0),
        'asset_label' => '적립금',
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
