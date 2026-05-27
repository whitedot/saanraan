<?php

declare(strict_types=1);

function sr_asset_exchange_assets(PDO $pdo): array
{
    $assets = [];
    foreach (sr_enabled_module_contract_files($pdo, 'asset-exchange.php') as $moduleKey => $contractFile) {
        $contract = sr_load_module_contract_file($moduleKey, $contractFile);
        if (!is_array($contract)) {
            continue;
        }

        $helpers = (string) ($contract['helpers'] ?? '');
        if ($helpers !== '' && preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) === 1) {
            $helperPath = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
            if (is_file($helperPath)) {
                require_once $helperPath;
            }
        }

        $balanceFunction = (string) ($contract['balance_function'] ?? '');
        $transactionFunction = (string) ($contract['transaction_function'] ?? '');
        if (!function_exists($balanceFunction) || !function_exists($transactionFunction)) {
            continue;
        }

        $labelFunction = (string) ($contract['label_function'] ?? '');
        $unitFunction = (string) ($contract['unit_function'] ?? '');
        $assets[$moduleKey] = [
            'module_key' => $moduleKey,
            'label' => function_exists($labelFunction) ? (string) $labelFunction($pdo) : (string) ($contract['label'] ?? $moduleKey),
            'unit_label' => function_exists($unitFunction) ? (string) $unitFunction($pdo) : (string) ($contract['unit_label'] ?? ''),
            'cash_like' => !empty($contract['cash_like']),
            'balance_function' => $balanceFunction,
            'transaction_function' => $transactionFunction,
            'exchange_out_type' => (string) ($contract['exchange_out_type'] ?? 'exchange_out'),
            'exchange_in_type' => (string) ($contract['exchange_in_type'] ?? 'exchange_in'),
            'exchange_fee_type' => (string) ($contract['exchange_fee_type'] ?? 'exchange_fee'),
        ];
    }

    uasort($assets, static function (array $left, array $right): int {
        return strcmp((string) $left['label'], (string) $right['label']);
    });

    return $assets;
}

function sr_asset_exchange_policy(PDO $pdo, int $policyId): ?array
{
    if ($policyId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_asset_exchange_policies WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $policyId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_asset_exchange_policies(PDO $pdo, bool $enabledOnly = false): array
{
    $sql = 'SELECT * FROM sr_asset_exchange_policies';
    if ($enabledOnly) {
        $sql .= " WHERE status = 'enabled'";
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';

    return $pdo->query($sql)->fetchAll();
}

function sr_asset_exchange_save_policy(PDO $pdo, array $data): int
{
    $policyId = (int) ($data['id'] ?? 0);
    $fromModuleKey = sr_asset_exchange_clean_module_key((string) ($data['from_module_key'] ?? ''));
    $toModuleKey = sr_asset_exchange_clean_module_key((string) ($data['to_module_key'] ?? ''));
    $status = (string) ($data['status'] ?? 'disabled');
    $roundingMode = (string) ($data['rounding_mode'] ?? 'floor');
    $feeTrigger = (string) ($data['fee_trigger'] ?? 'none');
    $feeBasis = (string) ($data['fee_basis'] ?? 'to_amount');

    if ($fromModuleKey === '' || $toModuleKey === '' || $fromModuleKey === $toModuleKey) {
        throw new InvalidArgumentException('출금 자산과 입금 자산을 서로 다르게 선택하세요.');
    }
    $assets = sr_asset_exchange_assets($pdo);
    if (!isset($assets[$fromModuleKey], $assets[$toModuleKey])) {
        throw new InvalidArgumentException('설치되어 있고 활성화된 자산 모듈만 환전 정책에 사용할 수 있습니다.');
    }
    if (!in_array($status, ['enabled', 'disabled'], true)) {
        throw new InvalidArgumentException('정책 상태가 올바르지 않습니다.');
    }
    if (!in_array($roundingMode, ['floor', 'round', 'ceil'], true)) {
        throw new InvalidArgumentException('반올림 방식이 올바르지 않습니다.');
    }
    if (!in_array($feeTrigger, ['none', 'always', 'reexchange'], true)) {
        throw new InvalidArgumentException('수수료 적용 조건이 올바르지 않습니다.');
    }
    if (!in_array($feeBasis, ['from_amount', 'to_amount'], true)) {
        throw new InvalidArgumentException('수수료 계산 기준이 올바르지 않습니다.');
    }

    $rateNumerator = sr_asset_exchange_positive_int($data['rate_numerator'] ?? 0, '비율 분자는 1 이상이어야 합니다.');
    $rateDenominator = sr_asset_exchange_positive_int($data['rate_denominator'] ?? 0, '비율 분모는 1 이상이어야 합니다.');
    $minAmount = sr_asset_exchange_positive_int($data['min_amount'] ?? 0, '최소 환전량은 1 이상이어야 합니다.');
    $maxAmount = sr_asset_exchange_nullable_int($data['max_amount'] ?? null);
    if ($maxAmount !== null && $maxAmount < $minAmount) {
        throw new InvalidArgumentException('최대 환전량은 최소 환전량 이상이어야 합니다.');
    }

    $feeRateNumerator = max(0, sr_asset_exchange_int_string($data['fee_rate_numerator'] ?? 0));
    $feeRateDenominator = max(1, sr_asset_exchange_int_string($data['fee_rate_denominator'] ?? 1));
    $feeFixedAmount = max(0, sr_asset_exchange_int_string($data['fee_fixed_amount'] ?? 0));
    $feeMinAmount = sr_asset_exchange_nullable_int($data['fee_min_amount'] ?? null);
    $feeMaxAmount = sr_asset_exchange_nullable_int($data['fee_max_amount'] ?? null);
    if ($feeMaxAmount !== null && $feeMinAmount !== null && $feeMaxAmount < $feeMinAmount) {
        throw new InvalidArgumentException('최대 수수료는 최소 수수료 이상이어야 합니다.');
    }
    if ($feeTrigger !== 'none' && $feeRateNumerator === 0 && $feeFixedAmount === 0 && ($feeMinAmount ?? 0) === 0) {
        throw new InvalidArgumentException('수수료를 적용하려면 정률, 정액, 최소 수수료 중 하나를 입력하세요.');
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM sr_asset_exchange_policies
         WHERE from_module_key = :from_module_key
           AND to_module_key = :to_module_key
           AND id <> :id
         LIMIT 1'
    );
    $stmt->execute([
        'from_module_key' => $fromModuleKey,
        'to_module_key' => $toModuleKey,
        'id' => $policyId,
    ]);
    if (is_array($stmt->fetch())) {
        throw new InvalidArgumentException('이미 같은 자산 조합의 환전 정책이 있습니다.');
    }

    $now = sr_now();
    $params = [
        'from_module_key' => $fromModuleKey,
        'to_module_key' => $toModuleKey,
        'status' => $status,
        'rate_numerator' => $rateNumerator,
        'rate_denominator' => $rateDenominator,
        'min_amount' => $minAmount,
        'max_amount' => $maxAmount,
        'rounding_mode' => $roundingMode,
        'fee_trigger' => $feeTrigger,
        'fee_basis' => $feeBasis,
        'fee_rate_numerator' => $feeRateNumerator,
        'fee_rate_denominator' => $feeRateDenominator,
        'fee_fixed_amount' => $feeFixedAmount,
        'fee_min_amount' => $feeMinAmount,
        'fee_max_amount' => $feeMaxAmount,
        'sort_order' => sr_asset_exchange_int_string($data['sort_order'] ?? 0),
        'updated_at' => $now,
    ];

    if ($policyId > 0) {
        $params['id'] = $policyId;
        $stmt = $pdo->prepare(
            'UPDATE sr_asset_exchange_policies
             SET from_module_key = :from_module_key, to_module_key = :to_module_key, status = :status,
                 rate_numerator = :rate_numerator, rate_denominator = :rate_denominator,
                 min_amount = :min_amount, max_amount = :max_amount, rounding_mode = :rounding_mode,
                 fee_trigger = :fee_trigger, fee_basis = :fee_basis,
                 fee_rate_numerator = :fee_rate_numerator, fee_rate_denominator = :fee_rate_denominator,
                 fee_fixed_amount = :fee_fixed_amount, fee_min_amount = :fee_min_amount, fee_max_amount = :fee_max_amount,
                 sort_order = :sort_order, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute($params);
        return $policyId;
    }

    $params['created_at'] = $now;
    $stmt = $pdo->prepare(
        'INSERT INTO sr_asset_exchange_policies
            (from_module_key, to_module_key, status, rate_numerator, rate_denominator, min_amount, max_amount, rounding_mode,
             fee_trigger, fee_basis, fee_rate_numerator, fee_rate_denominator, fee_fixed_amount, fee_min_amount, fee_max_amount,
             sort_order, created_at, updated_at)
         VALUES
            (:from_module_key, :to_module_key, :status, :rate_numerator, :rate_denominator, :min_amount, :max_amount, :rounding_mode,
             :fee_trigger, :fee_basis, :fee_rate_numerator, :fee_rate_denominator, :fee_fixed_amount, :fee_min_amount, :fee_max_amount,
             :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute($params);

    return (int) $pdo->lastInsertId();
}

function sr_asset_exchange_quote(PDO $pdo, array $policy, int $accountId, int $amount): array
{
    if ($amount <= 0) {
        throw new InvalidArgumentException('환전 금액은 1 이상이어야 합니다.');
    }
    if ((string) ($policy['status'] ?? '') !== 'enabled') {
        throw new InvalidArgumentException('비활성 정책은 실행할 수 없습니다.');
    }
    $assets = sr_asset_exchange_assets($pdo);
    $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
    $toModuleKey = (string) ($policy['to_module_key'] ?? '');
    if (!isset($assets[$fromModuleKey], $assets[$toModuleKey])) {
        throw new InvalidArgumentException('환전 대상 자산 모듈이 활성 상태가 아닙니다.');
    }
    $balanceFunction = (string) $assets[$fromModuleKey]['balance_function'];
    if ($accountId > 0 && $balanceFunction($pdo, $accountId) < $amount) {
        throw new InvalidArgumentException('출금 자산 잔액이 부족합니다.');
    }
    if ($amount < (int) ($policy['min_amount'] ?? 1)) {
        throw new InvalidArgumentException('최소 환전량보다 작습니다.');
    }
    $maxAmount = isset($policy['max_amount']) && $policy['max_amount'] !== null ? (int) $policy['max_amount'] : 0;
    if ($maxAmount > 0 && $amount > $maxAmount) {
        throw new InvalidArgumentException('최대 환전량보다 큽니다.');
    }

    $depositBeforeFee = sr_asset_exchange_apply_ratio(
        $amount,
        (int) ($policy['rate_numerator'] ?? 1),
        (int) ($policy['rate_denominator'] ?? 1),
        (string) ($policy['rounding_mode'] ?? 'floor')
    );
    if ($depositBeforeFee <= 0) {
        throw new InvalidArgumentException('환전 결과 금액이 0입니다.');
    }

    $feeApplies = sr_asset_exchange_fee_applies($pdo, $policy, $accountId);
    $feeAmount = $feeApplies ? sr_asset_exchange_fee_amount($policy, $amount, $depositBeforeFee) : 0;
    $depositAmount = $depositBeforeFee - $feeAmount;
    if ($depositAmount <= 0) {
        throw new InvalidArgumentException('수수료 차감 후 입금액이 0 이하입니다.');
    }

    return [
        'request_amount' => $amount,
        'deposit_before_fee' => $depositBeforeFee,
        'deposit_amount' => $depositAmount,
        'fee_amount' => $feeAmount,
        'fee_applies' => $feeApplies,
    ];
}

function sr_asset_exchange_execute(PDO $pdo, array $policy, int $accountId, int $amount, ?int $createdByAccountId = null): int
{
    $assets = sr_asset_exchange_assets($pdo);
    $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
    $toModuleKey = (string) ($policy['to_module_key'] ?? '');
    if (!isset($assets[$fromModuleKey], $assets[$toModuleKey])) {
        throw new RuntimeException('환전 대상 자산 모듈이 활성 상태가 아닙니다.');
    }

    $quote = sr_asset_exchange_quote($pdo, $policy, $accountId, $amount);
    $groupId = sr_asset_exchange_group_id();
    $now = sr_now();
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $fromTransactionFunction = (string) $assets[$fromModuleKey]['transaction_function'];
        $toTransactionFunction = (string) $assets[$toModuleKey]['transaction_function'];
        $fromTransactionId = $fromTransactionFunction($pdo, [
            'account_id' => $accountId,
            'amount' => -$amount,
            'transaction_type' => (string) $assets[$fromModuleKey]['exchange_out_type'],
            'reason' => '자산 환전 출금',
            'reference_type' => 'asset_exchange',
            'reference_id' => $groupId,
            'created_by_account_id' => $createdByAccountId,
        ]);
        $toTransactionId = $toTransactionFunction($pdo, [
            'account_id' => $accountId,
            'amount' => (int) $quote['deposit_before_fee'],
            'transaction_type' => (string) $assets[$toModuleKey]['exchange_in_type'],
            'reason' => '자산 환전 입금',
            'reference_type' => 'asset_exchange',
            'reference_id' => $groupId,
            'created_by_account_id' => $createdByAccountId,
        ]);
        $feeTransactionId = null;
        if ((int) $quote['fee_amount'] > 0) {
            $feeTransactionId = $toTransactionFunction($pdo, [
                'account_id' => $accountId,
                'amount' => -(int) $quote['fee_amount'],
                'transaction_type' => (string) $assets[$toModuleKey]['exchange_fee_type'],
                'reason' => '자산 환전 업무 수수료',
                'reference_type' => 'asset_exchange',
                'reference_id' => $groupId,
                'created_by_account_id' => $createdByAccountId,
            ]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sr_asset_exchange_logs
                (exchange_group_id, policy_id, account_id, from_module_key, to_module_key, request_amount,
                 rate_numerator, rate_denominator, rounding_mode, deposit_amount, fee_amount, fee_trigger, fee_basis,
                 status, failure_reason, from_transaction_id, to_transaction_id, fee_transaction_id, created_by_account_id, created_at)
             VALUES
                (:exchange_group_id, :policy_id, :account_id, :from_module_key, :to_module_key, :request_amount,
                 :rate_numerator, :rate_denominator, :rounding_mode, :deposit_amount, :fee_amount, :fee_trigger, :fee_basis,
                 :status, :failure_reason, :from_transaction_id, :to_transaction_id, :fee_transaction_id, :created_by_account_id, :created_at)'
        );
        $stmt->execute([
            'exchange_group_id' => $groupId,
            'policy_id' => (int) $policy['id'],
            'account_id' => $accountId,
            'from_module_key' => $fromModuleKey,
            'to_module_key' => $toModuleKey,
            'request_amount' => $amount,
            'rate_numerator' => (int) $policy['rate_numerator'],
            'rate_denominator' => (int) $policy['rate_denominator'],
            'rounding_mode' => (string) $policy['rounding_mode'],
            'deposit_amount' => (int) $quote['deposit_before_fee'],
            'fee_amount' => (int) $quote['fee_amount'],
            'fee_trigger' => (string) $policy['fee_trigger'],
            'fee_basis' => (string) $policy['fee_basis'],
            'status' => 'completed',
            'failure_reason' => '',
            'from_transaction_id' => $fromTransactionId,
            'to_transaction_id' => $toTransactionId,
            'fee_transaction_id' => $feeTransactionId,
            'created_by_account_id' => $createdByAccountId,
            'created_at' => $now,
        ]);
        $logId = (int) $pdo->lastInsertId();

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return $logId;
}

function sr_asset_exchange_apply_ratio(int $amount, int $numerator, int $denominator, string $roundingMode): int
{
    if ($amount <= 0 || $numerator <= 0 || $denominator <= 0) {
        return 0;
    }

    $product = $amount * $numerator;
    if ($roundingMode === 'ceil') {
        return intdiv($product + $denominator - 1, $denominator);
    }
    if ($roundingMode === 'round') {
        return intdiv(($product * 2) + $denominator, $denominator * 2);
    }

    return intdiv($product, $denominator);
}

function sr_asset_exchange_fee_applies(PDO $pdo, array $policy, int $accountId): bool
{
    $trigger = (string) ($policy['fee_trigger'] ?? 'none');
    if ($trigger === 'none') {
        return false;
    }
    if ($trigger === 'always') {
        return true;
    }

    $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
    $assets = sr_asset_exchange_assets($pdo);
    if (empty($assets[$fromModuleKey]['cash_like'])) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM sr_asset_exchange_logs
         WHERE account_id = :account_id
           AND to_module_key = :from_module_key
           AND status = 'completed'
         LIMIT 1"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'from_module_key' => $fromModuleKey,
    ]);

    return is_array($stmt->fetch());
}

function sr_asset_exchange_fee_amount(array $policy, int $fromAmount, int $toAmount): int
{
    $basis = (string) ($policy['fee_basis'] ?? 'to_amount') === 'from_amount' ? $fromAmount : $toAmount;
    $fee = (int) ($policy['fee_fixed_amount'] ?? 0);
    $rateNumerator = (int) ($policy['fee_rate_numerator'] ?? 0);
    $rateDenominator = max(1, (int) ($policy['fee_rate_denominator'] ?? 1));
    if ($rateNumerator > 0) {
        $fee += intdiv($basis * $rateNumerator, $rateDenominator);
    }
    if (isset($policy['fee_min_amount']) && $policy['fee_min_amount'] !== null) {
        $fee = max($fee, (int) $policy['fee_min_amount']);
    }
    if (isset($policy['fee_max_amount']) && $policy['fee_max_amount'] !== null) {
        $fee = min($fee, (int) $policy['fee_max_amount']);
    }

    return max(0, $fee);
}

function sr_asset_exchange_clean_module_key(string $value): string
{
    $value = strtolower(trim($value));
    return sr_is_safe_module_key($value) ? $value : '';
}

function sr_asset_exchange_positive_int(mixed $value, string $message): int
{
    $intValue = sr_asset_exchange_int_string($value);
    if ($intValue <= 0) {
        throw new InvalidArgumentException($message);
    }

    return $intValue;
}

function sr_asset_exchange_nullable_int(mixed $value): ?int
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    return max(0, sr_asset_exchange_int_string($value));
}

function sr_asset_exchange_int_string(mixed $value): int
{
    $string = trim((string) $value);
    if (preg_match('/\A-?\d+\z/', $string) !== 1) {
        return 0;
    }

    return (int) $string;
}

function sr_asset_exchange_group_id(): string
{
    return 'ae_' . date('YmdHis') . '_' . bin2hex(random_bytes(8));
}

function sr_asset_exchange_asset_label(array $assets, string $moduleKey): string
{
    return (string) ($assets[$moduleKey]['label'] ?? $moduleKey);
}
