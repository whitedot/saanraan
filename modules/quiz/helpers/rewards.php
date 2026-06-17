<?php

declare(strict_types=1);

function sr_quiz_reward_providers(): array
{
    return ['ledger_asset', 'coupon'];
}

function sr_quiz_default_reward_providers(): array
{
    return ['none', ...sr_quiz_reward_providers()];
}

function sr_quiz_reward_provider_label(string $provider): string
{
    return [
        'none' => '지급안함',
        'ledger_asset' => '포인트/금액',
        'coupon' => '쿠폰 발급',
    ][$provider] ?? $provider;
}

function sr_quiz_reward_dedupe_scopes(): array
{
    return ['per_quiz', 'per_source', 'per_attempt'];
}

function sr_quiz_reward_dedupe_scope_label(string $scope): string
{
    return [
        'per_quiz' => '퀴즈당 1회',
        'per_source' => '출처당 1회',
        'per_attempt' => '응시마다',
    ][$scope] ?? $scope;
}

function sr_quiz_reward_grant_status_label(string $status): string
{
    return [
        'pending' => '대기',
        'granted' => '지급',
        'failed' => '실패',
        'duplicate' => '중복',
        'cancelled' => '취소',
    ][$status] ?? $status;
}

function sr_quiz_active_reward_policy(PDO $pdo, int $quizId): ?array
{
    if ($quizId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_reward_policies
         WHERE quiz_id = :quiz_id
           AND status = \'active\'
         ORDER BY sort_order ASC, id ASC
         LIMIT 1'
    );
    $stmt->execute(['quiz_id' => $quizId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_quiz_issue_reward_grant(PDO $pdo, array $quiz, int $attemptId, int $accountId, array $policy, array $assetOptions, string $now, array $sourceContext = []): array
{
    $quizId = (int) ($quiz['id'] ?? 0);
    $policyId = (int) ($policy['id'] ?? 0);
    $rewardProvider = (string) ($policy['reward_provider'] ?? 'ledger_asset');
    $rewardModule = (string) ($policy['reward_module'] ?? '');
    $rewardAmount = (int) ($policy['reward_amount'] ?? 0);
    $dedupeScope = (string) ($policy['dedupe_scope'] ?? 'per_quiz');
    if (!in_array($dedupeScope, sr_quiz_reward_dedupe_scopes(), true)) {
        $dedupeScope = 'per_quiz';
    }
    $dedupeKeyParts = [
        'quiz.reward',
        'account',
        (string) $accountId,
        'quiz',
        (string) $quizId,
        'policy',
        (string) $policyId,
        'provider',
        $rewardProvider,
        'module',
        $rewardModule,
    ];
    if ($dedupeScope === 'per_source') {
        $dedupeKeyParts[] = 'source';
        $dedupeKeyParts[] = (string) ($sourceContext['source_module'] ?? '');
        $dedupeKeyParts[] = (string) ($sourceContext['source_type'] ?? '');
        $dedupeKeyParts[] = (string) ($sourceContext['source_id'] ?? '');
    } elseif ($dedupeScope === 'per_attempt') {
        $dedupeKeyParts[] = 'attempt';
        $dedupeKeyParts[] = (string) $attemptId;
    }
    $dedupeKey = implode(':', $dedupeKeyParts);

    $insertVerb = 'INSERT IGNORE';
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $insertVerb = 'INSERT OR IGNORE';
        }
    } catch (Throwable $exception) {
        $insertVerb = 'INSERT IGNORE';
    }
    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_quiz_reward_grants
            (quiz_id, attempt_id, reward_policy_id, account_id, reward_provider, reward_module, reward_code, reward_amount,
             source_module, source_type, source_id, dedupe_scope, dedupe_key, status, request_snapshot_json, created_at, updated_at)
         VALUES
            (:quiz_id, :attempt_id, :reward_policy_id, :account_id, :reward_provider, :reward_module, :reward_code, :reward_amount,
             :source_module, :source_type, :source_id, :dedupe_scope, :dedupe_key, \'pending\', :request_snapshot_json, :created_at, :updated_at)'
    );
    $snapshotJson = json_encode([
        'quiz_key' => (string) ($quiz['quiz_key'] ?? ''),
        'reward_provider' => $rewardProvider,
        'reward_module' => $rewardModule,
        'reward_amount' => $rewardAmount,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->execute([
        'quiz_id' => $quizId,
        'attempt_id' => $attemptId,
        'reward_policy_id' => $policyId,
        'account_id' => $accountId,
        'reward_provider' => $rewardProvider,
        'reward_module' => $rewardModule,
        'reward_code' => (string) ($policy['reward_code'] ?? 'quiz_reward'),
        'reward_amount' => $rewardAmount,
        'source_module' => $sourceContext['source_module'] ?? null,
        'source_type' => $sourceContext['source_type'] ?? null,
        'source_id' => $sourceContext['source_id'] ?? null,
        'dedupe_scope' => $dedupeScope,
        'dedupe_key' => $dedupeKey,
        'request_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $lockClause = '';
    try {
        $lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    } catch (Throwable $exception) {
        $lockClause = ' FOR UPDATE';
    }
    $grantStmt = $pdo->prepare('SELECT * FROM sr_quiz_reward_grants WHERE dedupe_key = :dedupe_key LIMIT 1' . $lockClause);
    $grantStmt->execute(['dedupe_key' => $dedupeKey]);
    $grant = $grantStmt->fetch();
    if (!is_array($grant)) {
        return [];
    }
    $existingAttemptId = (int) ($grant['attempt_id'] ?? 0);
    if ($existingAttemptId > 0 && $existingAttemptId !== $attemptId && $dedupeScope !== 'per_attempt') {
        $grant['status'] = 'duplicate';
        $grant['error_message'] = '';
        return $grant;
    }
    if ((string) ($grant['status'] ?? '') === 'granted') {
        return $grant;
    }

    $grantId = (int) ($grant['id'] ?? 0);
    sr_quiz_refresh_reward_grant_for_retry($pdo, $grantId, [
        'attempt_id' => $attemptId,
        'reward_policy_id' => $policyId,
        'reward_provider' => $rewardProvider,
        'reward_module' => $rewardModule,
        'reward_code' => (string) ($policy['reward_code'] ?? 'quiz_reward'),
        'reward_amount' => $rewardAmount,
        'source_module' => $sourceContext['source_module'] ?? null,
        'source_type' => $sourceContext['source_type'] ?? null,
        'source_id' => $sourceContext['source_id'] ?? null,
        'dedupe_scope' => $dedupeScope,
        'request_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
        'updated_at' => $now,
    ]);
    if ($rewardProvider === 'coupon') {
        return sr_quiz_issue_coupon_reward_grant($pdo, $quiz, $grantId, $attemptId, $accountId, $policy, $now);
    }
    if ($rewardProvider !== 'ledger_asset') {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '지원하지 않는 보상 공급자입니다.', $now);
    }
    if (!isset($assetOptions[$rewardModule])) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '보상 자산 계약을 찾을 수 없습니다.', $now);
    }

    $transactionFunction = (string) ($assetOptions[$rewardModule]['transaction_function'] ?? '');
    if (!function_exists($transactionFunction)) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '보상 자산 거래 함수를 찾을 수 없습니다.', $now);
    }

    try {
        $transactionId = (int) $transactionFunction($pdo, [
            'account_id' => $accountId,
            'amount' => $rewardAmount,
            'transaction_type' => (string) ($assetOptions[$rewardModule]['credit_type'] ?? 'grant'),
            'reason' => '퀴즈 보상: ' . (string) ($quiz['title'] ?? ''),
            'reference_type' => 'quiz_reward',
            'reference_id' => (string) $grantId,
            'created_by_account_id' => null,
        ]);
        $resultJson = json_encode(['transaction_id' => $transactionId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->prepare(
            'UPDATE sr_quiz_reward_grants
             SET status = \'granted\',
                 provider_reference_type = :provider_reference_type,
                 provider_reference_id = :provider_reference_id,
                 result_snapshot_json = :result_snapshot_json,
                 granted_at = :granted_at,
                 updated_at = :updated_at
             WHERE id = :id'
        )->execute([
            'provider_reference_type' => 'quiz_reward',
            'provider_reference_id' => (string) $grantId,
            'result_snapshot_json' => is_string($resultJson) ? $resultJson : '{}',
            'granted_at' => $now,
            'updated_at' => $now,
            'id' => $grantId,
        ]);
        $pdo->prepare('UPDATE sr_quiz_attempts SET rewarded_at = :rewarded_at, updated_at = :updated_at WHERE id = :id')->execute([
            'rewarded_at' => $now,
            'updated_at' => $now,
            'id' => $attemptId,
        ]);
    } catch (Throwable $exception) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)), $now);
    }

    $grantStmt->execute(['dedupe_key' => $dedupeKey]);
    $grant = $grantStmt->fetch();

    return is_array($grant) ? $grant : [];
}

function sr_quiz_refresh_reward_grant_for_retry(PDO $pdo, int $grantId, array $values): void
{
    if ($grantId < 1) {
        return;
    }

    $pdo->prepare(
        'UPDATE sr_quiz_reward_grants
         SET attempt_id = :attempt_id,
             reward_policy_id = :reward_policy_id,
             reward_provider = :reward_provider,
             reward_module = :reward_module,
             reward_code = :reward_code,
             reward_amount = :reward_amount,
             source_module = :source_module,
             source_type = :source_type,
             source_id = :source_id,
             dedupe_scope = :dedupe_scope,
             request_snapshot_json = :request_snapshot_json,
             status = \'pending\',
             error_message = NULL,
             failed_at = NULL,
             updated_at = :updated_at
         WHERE id = :id
           AND status <> \'granted\''
    )->execute([
        'attempt_id' => (int) ($values['attempt_id'] ?? 0),
        'reward_policy_id' => (int) ($values['reward_policy_id'] ?? 0),
        'reward_provider' => (string) ($values['reward_provider'] ?? ''),
        'reward_module' => (string) ($values['reward_module'] ?? ''),
        'reward_code' => (string) ($values['reward_code'] ?? ''),
        'reward_amount' => $values['reward_amount'] ?? null,
        'source_module' => $values['source_module'] ?? null,
        'source_type' => $values['source_type'] ?? null,
        'source_id' => $values['source_id'] ?? null,
        'dedupe_scope' => (string) ($values['dedupe_scope'] ?? 'per_quiz'),
        'request_snapshot_json' => (string) ($values['request_snapshot_json'] ?? '{}'),
        'updated_at' => (string) ($values['updated_at'] ?? sr_now()),
        'id' => $grantId,
    ]);
}

function sr_quiz_reward_grant_ledger_transaction(PDO $pdo, array $grant, array $assetOptions): ?array
{
    if ((string) ($grant['reward_provider'] ?? '') !== 'ledger_asset') {
        return null;
    }

    $moduleKey = (string) ($grant['reward_module'] ?? '');
    $lookupFunction = (string) ($assetOptions[$moduleKey]['transaction_lookup_function'] ?? '');
    if ($lookupFunction === '' || !function_exists($lookupFunction)) {
        return null;
    }

    $referenceType = (string) ($grant['provider_reference_type'] ?? '');
    $referenceId = (string) ($grant['provider_reference_id'] ?? '');
    if ($referenceType === '' || $referenceId === '') {
        $grantId = (int) ($grant['id'] ?? 0);
        if ($grantId < 1) {
            return null;
        }
        $referenceType = 'quiz_reward';
        $referenceId = (string) $grantId;
    }

    $row = $lookupFunction($pdo, $referenceType, $referenceId);

    return is_array($row) ? $row : null;
}

function sr_quiz_reward_grant_by_id(PDO $pdo, int $grantId): ?array
{
    if ($grantId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_quiz_reward_grants WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $grantId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_quiz_reward_grant_reclaim_status(PDO $pdo, array $grant, array $assetOptions): array
{
    $status = [
        'available' => false,
        'reason' => 'not_reclaimable',
        'module_key' => (string) ($grant['reward_module'] ?? ''),
        'transaction_id' => 0,
        'account_id' => (int) ($grant['account_id'] ?? 0),
        'amount' => 0,
        'remaining_amount' => 0,
        'transaction_type' => '',
        'reference_type' => '',
        'reference_id' => '',
    ];

    $transaction = sr_quiz_reward_grant_ledger_transaction($pdo, $grant, $assetOptions);
    if (!is_array($transaction)) {
        $status['reason'] = 'ledger_transaction_not_found';

        return $status;
    }

    $transactionId = (int) ($transaction['id'] ?? 0);
    $accountId = (int) ($transaction['account_id'] ?? 0);
    $amount = (int) ($transaction['amount'] ?? 0);
    $moduleKey = (string) ($grant['reward_module'] ?? '');
    $status['module_key'] = $moduleKey;
    $status['transaction_id'] = $transactionId;
    $status['account_id'] = $accountId;
    $status['amount'] = $amount;
    $status['transaction_type'] = (string) ($transaction['transaction_type'] ?? '');

    if ($transactionId < 1 || $accountId < 1 || $amount <= 0) {
        $status['reason'] = 'ledger_transaction_not_reclaimable';

        return $status;
    }

    if ($moduleKey !== 'reward') {
        $status['reason'] = 'unsupported_asset_reclaim';

        return $status;
    }

    if (!function_exists('sr_reward_reclaim_remaining_amounts_for_transactions') || !function_exists('sr_reward_reclaim_reference_id')) {
        $status['reason'] = 'reclaim_contract_missing';

        return $status;
    }

    $remainingAmounts = sr_reward_reclaim_remaining_amounts_for_transactions($pdo, [$transaction]);
    $remainingAmount = (int) ($remainingAmounts[$transactionId] ?? ($remainingAmounts[$accountId . ':' . $transactionId] ?? 0));
    $status['remaining_amount'] = max(0, $remainingAmount);
    $status['reference_type'] = 'reclaim';
    $status['reference_id'] = sr_reward_reclaim_reference_id($transactionId);

    if ($status['remaining_amount'] <= 0) {
        $status['reason'] = 'nothing_remaining';

        return $status;
    }

    $status['available'] = true;
    $status['reason'] = '';

    return $status;
}

function sr_quiz_reclaim_reward_grant(PDO $pdo, array $grant, array $assetOptions, int $amount, int $adminAccountId, string $reason = ''): array
{
    if ($amount <= 0) {
        return [
            'ok' => false,
            'errors' => ['회수 금액은 1 이상이어야 합니다.'],
            'transaction_id' => 0,
        ];
    }

    $status = sr_quiz_reward_grant_reclaim_status($pdo, $grant, $assetOptions);
    if (empty($status['available'])) {
        return [
            'ok' => false,
            'errors' => ['회수할 수 있는 퀴즈 보상 원장 거래를 찾을 수 없습니다.'],
            'transaction_id' => 0,
            'reclaim_status' => $status,
        ];
    }

    $remainingAmount = (int) ($status['remaining_amount'] ?? 0);
    if ($amount > $remainingAmount) {
        return [
            'ok' => false,
            'errors' => ['회수 금액이 남은 회수 가능액을 초과했습니다.'],
            'transaction_id' => 0,
            'reclaim_status' => $status,
        ];
    }

    if (!function_exists('sr_reward_validate_reclaim_transaction') || !function_exists('sr_reward_create_transaction')) {
        return [
            'ok' => false,
            'errors' => ['적립금 회수 계약을 찾을 수 없습니다.'],
            'transaction_id' => 0,
            'reclaim_status' => $status,
        ];
    }

    $accountId = (int) ($status['account_id'] ?? 0);
    $referenceType = (string) ($status['reference_type'] ?? '');
    $referenceId = (string) ($status['reference_id'] ?? '');
    $reclaimReason = sr_quiz_clean_text($reason, 255);
    if ($reclaimReason === '') {
        $reclaimReason = '퀴즈 보상 회수: grant #' . (string) (int) ($grant['id'] ?? 0);
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $validationError = sr_reward_validate_reclaim_transaction($pdo, $accountId, -$amount, $referenceType, $referenceId, true);
        if ($validationError !== null) {
            throw new RuntimeException($validationError);
        }

        $transactionId = sr_reward_create_transaction($pdo, [
            'account_id' => $accountId,
            'amount' => -$amount,
            'transaction_type' => 'reclaim',
            'reason' => $reclaimReason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by_account_id' => $adminAccountId > 0 ? $adminAccountId : null,
        ]);

        sr_audit_log($pdo, [
            'actor_account_id' => $adminAccountId,
            'actor_type' => 'admin',
            'event_type' => 'quiz.reward.reclaimed',
            'target_type' => 'quiz_reward_grant',
            'target_id' => (string) (int) ($grant['id'] ?? 0),
            'result' => 'success',
            'message' => 'Quiz reward grant reclaimed.',
            'metadata' => [
                'account_id' => $accountId,
                'amount' => $amount,
                'reward_transaction_id' => (int) ($status['transaction_id'] ?? 0),
                'reclaim_transaction_id' => $transactionId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ],
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'ok' => true,
            'errors' => [],
            'transaction_id' => $transactionId,
            'reclaim_status' => $status,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'ok' => false,
            'errors' => [sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500))],
            'transaction_id' => 0,
            'reclaim_status' => $status,
        ];
    }
}

function sr_quiz_issue_coupon_reward_grant(PDO $pdo, array $quiz, int $grantId, int $attemptId, int $accountId, array $policy, string $now): array
{
    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '쿠폰 모듈이 활성화되어 있지 않습니다.', $now);
    }
    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_issue_to_account')) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '쿠폰 발급 함수를 찾을 수 없습니다.', $now);
    }

    $definitionId = (int) ($policy['reward_code'] ?? 0);
    if ($definitionId < 1) {
        $definitionId = (int) ($policy['reward_module'] ?? 0);
    }
    if ($definitionId < 1) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '쿠폰 정의 ID를 확인해야 합니다.', $now);
    }
    if (!sr_quiz_reward_coupon_definition_is_available($pdo, $definitionId)) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '사용 가능한 보상 쿠폰이 아닙니다.', $now);
    }

    try {
        $issueId = sr_coupon_issue_to_account($pdo, $definitionId, $accountId, '퀴즈 보상: ' . (string) ($quiz['title'] ?? ''), null, null);
        $resultJson = json_encode(['coupon_issue_id' => $issueId, 'coupon_definition_id' => $definitionId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->prepare(
            'UPDATE sr_quiz_reward_grants
             SET status = \'granted\',
                 provider_reference_type = :provider_reference_type,
                 provider_reference_id = :provider_reference_id,
                 result_snapshot_json = :result_snapshot_json,
                 granted_at = :granted_at,
                 updated_at = :updated_at
             WHERE id = :id'
        )->execute([
            'provider_reference_type' => 'coupon_issue',
            'provider_reference_id' => (string) $issueId,
            'result_snapshot_json' => is_string($resultJson) ? $resultJson : '{}',
            'granted_at' => $now,
            'updated_at' => $now,
            'id' => $grantId,
        ]);
        $pdo->prepare('UPDATE sr_quiz_attempts SET rewarded_at = :rewarded_at, updated_at = :updated_at WHERE id = :id')->execute([
            'rewarded_at' => $now,
            'updated_at' => $now,
            'id' => $attemptId,
        ]);
    } catch (Throwable $exception) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)), $now);
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_quiz_reward_grants WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $grantId]);
    $grant = $stmt->fetch();

    return is_array($grant) ? $grant : [];
}

function sr_quiz_mark_reward_grant_failed(PDO $pdo, int $grantId, string $message, string $now): array
{
    $pdo->prepare(
        'UPDATE sr_quiz_reward_grants
         SET status = \'failed\',
             error_message = :error_message,
             failed_at = :failed_at,
             updated_at = :updated_at
         WHERE id = :id'
    )->execute([
        'error_message' => $message,
        'failed_at' => $now,
        'updated_at' => $now,
        'id' => $grantId,
    ]);
    $stmt = $pdo->prepare('SELECT * FROM sr_quiz_reward_grants WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $grantId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_quiz_reward_coupon_definitions(PDO $pdo, int $limit = 200): array
{
    $limit = max(1, min(300, $limit));
    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return [];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_tables_available') || !sr_coupon_tables_available($pdo)) {
        return [];
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'SELECT id, coupon_key, title, target_type, target_id, max_uses_per_issue, valid_from, valid_until
         FROM sr_coupon_definitions
         WHERE status = \'active\'
           AND (valid_from IS NULL OR valid_from <= :now_from)
           AND (valid_until IS NULL OR valid_until >= :now_until)
         ORDER BY title ASC, coupon_key ASC, id ASC
         LIMIT ' . $limit
    );
    $stmt->execute([
        'now_from' => $now,
        'now_until' => $now,
    ]);

    return $stmt->fetchAll();
}

function sr_quiz_reward_coupon_definition_is_available(PDO $pdo, int $definitionId): bool
{
    if ($definitionId < 1 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return false;
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_tables_available') || !sr_coupon_tables_available($pdo)) {
        return false;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_coupon_definitions
         WHERE id = :id
           AND status = \'active\'
           AND (valid_from IS NULL OR valid_from <= :now_from)
           AND (valid_until IS NULL OR valid_until >= :now_until)
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $definitionId,
        'now_from' => $now,
        'now_until' => $now,
    ]);

    return is_array($stmt->fetch());
}

function sr_quiz_coupon_definition_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_quiz_coupon_definition_reference_rows($pdo, $target, $context));
}

function sr_quiz_coupon_definition_reference_rows(PDO $pdo, array $target, array $context): array
{
    $definitionId = (int) ($target['target_id'] ?? 0);
    if ($definitionId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT rp.id AS reward_policy_id, rp.status AS reward_policy_status, q.id AS quiz_id, q.quiz_key, q.title, q.status AS quiz_status, q.updated_at
         FROM sr_quiz_reward_policies rp
         INNER JOIN sr_quiz_sets q ON q.id = rp.quiz_id
         WHERE rp.reward_provider = \'coupon\'
           AND rp.reward_code = :definition_id
           AND q.deleted_at IS NULL
         ORDER BY q.updated_at DESC, q.id DESC'
    );
    $stmt->execute(['definition_id' => (string) $definitionId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'consumer_module_key' => 'quiz',
            'reference_type' => 'quiz_reward_coupon',
            'reference_id' => (string) (int) ($row['reward_policy_id'] ?? 0),
            'quiz_id' => (int) ($row['quiz_id'] ?? 0),
            'target_type' => 'coupon_definition',
            'target_id' => (string) $definitionId,
            'title' => (string) ($row['title'] ?? ''),
            'status' => (string) ($row['quiz_status'] ?? ''),
            'summary' => '퀴즈 보상: ' . (string) ($row['quiz_key'] ?? ''),
            'admin_url' => '/admin/quiz?mode=edit&id=' . rawurlencode((string) (int) ($row['quiz_id'] ?? 0)),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $rows;
}

function sr_quiz_coupon_definition_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    $quizId = (int) ($row['quiz_id'] ?? $row['reference_id'] ?? 0);
    if ($quizId < 1) {
        return ['status' => 'unknown', 'message' => '퀴즈 참조를 확인할 수 없습니다.'];
    }

    return ['status' => 'ok'];
}

function sr_quiz_coupon_definition_reference_admin_url(array $row, array $context): string
{
    $quizId = (int) ($row['quiz_id'] ?? 0);
    if ($quizId < 1 && preg_match('/\A[1-9][0-9]*\z/', (string) ($row['reference_id'] ?? '')) === 1) {
        $quizId = (int) $row['reference_id'];
    }

    return $quizId > 0 ? '/admin/quiz?mode=edit&id=' . rawurlencode((string) $quizId) : '/admin/quiz';
}
