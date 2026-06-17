<?php

declare(strict_types=1);

function sr_content_asset_action_required(array $page): bool
{
    return (int) ($page['asset_action_enabled'] ?? 0) === 1
        && (int) ($page['asset_action_amount'] ?? 0) > 0;
}

function sr_content_asset_action_dedupe_key(string $assetModule, int $accountId, int $pageId): string
{
    return 'content.action:' . $assetModule . ':' . (string) $accountId . ':' . (string) $pageId . ':complete';
}

function sr_content_asset_action_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_content_asset_action_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_has_completed_asset_action(PDO $pdo, string $assetModule, int $accountId, int $pageId): bool
{
    $log = sr_content_asset_action_log($pdo, sr_content_asset_action_dedupe_key($assetModule, $accountId, $pageId));

    return is_array($log)
        && (string) ($log['log_status'] ?? sr_content_asset_log_status_completed()) === sr_content_asset_log_status_completed()
        && ((int) ($log['transaction_id'] ?? 0) > 0 || (int) ($log['amount'] ?? -1) === 0);
}

function sr_content_has_completed_asset_action_for_modules(PDO $pdo, array $assetModules, int $accountId, int $pageId): bool
{
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        if (sr_content_has_completed_asset_action($pdo, $assetModule, $accountId, $pageId)) {
            return true;
        }
    }

    return false;
}

function sr_content_insert_asset_action_placeholder(PDO $pdo, int $pageId, int $accountId, string $assetModule, string $direction, int $amount, string $dedupeKey, string $groupPolicySnapshotJson = '', int $settlementAmount = 0, string $settlementCurrency = 'KRW', string $purchasePowerSnapshotJson = ''): bool
{
    $settlementAmount = max(0, $settlementAmount);
    $settlementKind = sr_content_asset_settlement_kind_for_action($direction, $amount, $settlementAmount, $purchasePowerSnapshotJson);
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_content_asset_action_logs
            (content_id, account_id, asset_module, transaction_id, reference_type, reference_id, action_key, direction, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, settlement_kind, snapshot_schema_version, rounding_policy_version, log_status, group_policy_snapshot_json, dedupe_key, created_at)
         VALUES
            (:content_id, :account_id, :asset_module, 0, :reference_type, :reference_id, :action_key, :direction, :amount, :settlement_amount, :settlement_currency, :purchase_power_snapshot_json, :settlement_kind, :snapshot_schema_version, :rounding_policy_version, :log_status, :group_policy_snapshot_json, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'content_id' => $pageId,
        'account_id' => $accountId,
        'asset_module' => $assetModule,
        'reference_type' => 'content.action',
        'reference_id' => (string) $pageId,
        'action_key' => 'complete',
        'direction' => $direction,
        'amount' => $amount,
        'settlement_amount' => $settlementAmount,
        'settlement_currency' => sr_content_asset_settlement_currency($pdo, ['asset_settlement_currency' => $settlementCurrency]),
        'purchase_power_snapshot_json' => $purchasePowerSnapshotJson,
        'settlement_kind' => $settlementKind,
        'snapshot_schema_version' => sr_content_asset_snapshot_schema_version(),
        'rounding_policy_version' => sr_content_asset_rounding_policy_version(),
        'log_status' => sr_content_asset_log_status_pending(),
        'group_policy_snapshot_json' => $groupPolicySnapshotJson,
        'dedupe_key' => $dedupeKey,
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_content_update_asset_action_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_content_asset_action_logs
         SET transaction_id = :transaction_id,
             log_status = :log_status
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'log_status' => sr_content_asset_log_status_completed(),
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_content_complete_zero_asset_action_log(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_content_asset_action_logs
         SET log_status = :log_status
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0
           AND amount = 0'
    );
    $stmt->execute([
        'log_status' => sr_content_asset_log_status_completed(),
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_content_delete_asset_action_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_content_asset_action_logs
         WHERE dedupe_key = :dedupe_key
           AND log_status = :log_status'
    );
    $stmt->execute([
        'dedupe_key' => $dedupeKey,
        'log_status' => sr_content_asset_log_status_pending(),
    ]);
}

function sr_content_run_asset_action(PDO $pdo, array $page, int $accountId): array
{
    return sr_content_asset_retry_operation($pdo, static function () use ($pdo, $page, $accountId): array {
        return sr_content_run_asset_action_once($pdo, $page, $accountId);
    });
}

function sr_content_run_asset_action_once(PDO $pdo, array $page, int $accountId): array
{
    $pageId = (int) ($page['id'] ?? 0);
    $assetModules = sr_content_asset_module_keys_from_value($page['asset_action_module'] ?? '');
    $direction = (string) ($page['asset_action_direction'] ?? 'grant');
    if ($direction === 'grant' && count($assetModules) > 1) {
        $storedAmounts = sr_content_asset_amounts_from_value($page['asset_action_amounts_json'] ?? '', $assetModules, 0);
        $assetModules = [(string) (array_key_first($storedAmounts) ?? $assetModules[0])];
    }
    $assetModuleValue = sr_content_asset_module_value_from_keys($assetModules);
    $amounts = sr_content_asset_amounts_from_value($page['asset_action_amounts_json'] ?? '', $assetModules, (int) ($page['asset_action_amount'] ?? 0));
    $amount = $amounts !== [] ? sr_content_asset_amount_total($amounts) : (int) ($page['asset_action_amount'] ?? 0);

    if ($pageId <= 0 || $accountId <= 0 || !sr_content_asset_action_required($page)) {
        return ['allowed' => false, 'completed' => false, 'message' => '콘텐츠 완료 버튼을 사용할 수 없습니다.'];
    }

    if ($assetModules === [] || !isset(sr_content_asset_action_directions()[$direction])) {
        return ['allowed' => false, 'completed' => false, 'message' => '콘텐츠 완료 버튼 설정이 올바르지 않습니다.'];
    }

    if (!sr_content_asset_modules_available($pdo, $assetModules)) {
        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => '선택한 포인트/금액 항목을 모두 사용할 수 없어 완료 처리할 수 없습니다.',
        ];
    }

    if (sr_content_has_completed_asset_action_for_modules($pdo, $assetModules, $accountId, $pageId)) {
        return [
            'allowed' => true,
            'completed' => false,
            'already_completed' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => '이미 완료 처리되었습니다.',
        ];
    }

    $baseActionAmounts = $amounts !== [] ? $amounts : [(string) $assetModules[0] => $amount];
    $policyAmounts = sr_content_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $baseActionAmounts, $amount, $page['asset_action_group_policies_json'] ?? '', (int) ($page['asset_action_policy_set_id'] ?? 0), $direction === 'use' ? 'use' : 'grant');
    $amounts = $policyAmounts['amounts'];
    $amount = (int) $policyAmounts['amount'];
    $settlementCurrency = sr_content_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($page['asset_action_settlement_currency'] ?? '')]);
    if ($amount <= 0) {
        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $dedupeKey = sr_content_asset_action_dedupe_key($assetModule, $accountId, $pageId);
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            sr_content_insert_asset_action_placeholder($pdo, $pageId, $accountId, $assetModule, $direction, 0, $dedupeKey, sr_content_asset_group_policy_snapshot_json($policyAmounts['snapshots']));
            sr_content_complete_zero_asset_action_log($pdo, $dedupeKey);
            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            } elseif ($dedupeKey !== '') {
                sr_content_delete_asset_action_placeholder($pdo, $dedupeKey);
            }
            if ($startedTransaction && sr_content_asset_is_retryable_transaction_exception($exception)) {
                throw $exception;
            }
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'content_asset_group_action_failed');
            }

            return [
                'allowed' => false,
                'completed' => false,
                'asset_module' => $assetModuleValue,
                'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
                'amount' => 0,
                'direction' => $direction,
                'message' => '포인트/금액 처리에 실패했습니다.',
            ];
        }
        return [
            'allowed' => true,
            'completed' => true,
            'group_policy_applied' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
            'amount' => 0,
            'direction' => $direction,
            'message' => '',
        ];
    }

    $allocations = $direction === 'use'
        ? sr_content_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency)
        : sr_content_asset_amount_allocations($amounts !== [] ? $amounts : [(string) $assetModules[0] => $amount]);
    if ($direction === 'use' && $allocations === []) {
        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => '선택한 항목의 잔액이 부족해 완료 처리할 수 없습니다.',
        ];
    }

    $dedupeKey = '';
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) ($allocation['asset_amount'] ?? $allocation['amount']);
            $allocatedSettlementAmount = $direction === 'use' ? (int) ($allocation['settlement_amount'] ?? 0) : 0;
            $allocationSettlementCurrency = $direction === 'use' ? (string) ($allocation['settlement_currency'] ?? $settlementCurrency) : $settlementCurrency;
            $purchasePowerSnapshotJson = $direction === 'use' ? sr_content_asset_purchase_power_snapshot_json(is_array($allocation['purchase_power_snapshot'] ?? null) ? $allocation['purchase_power_snapshot'] : []) : '';
            $dedupeKey = sr_content_asset_action_dedupe_key($assetModule, $accountId, $pageId);
            $inserted = sr_content_insert_asset_action_placeholder($pdo, $pageId, $accountId, $assetModule, $direction, $allocatedAmount, $dedupeKey, sr_content_asset_group_policy_snapshot_json(isset($policyAmounts['snapshots'][$assetModule]) ? [$policyAmounts['snapshots'][$assetModule]] : []), $allocatedSettlementAmount, $allocationSettlementCurrency, $purchasePowerSnapshotJson);
            if (!$inserted) {
                continue;
            }

            $assetOption = sr_content_asset_modules($pdo)[$assetModule];
            $signedAmount = $direction === 'grant' ? $allocatedAmount : -$allocatedAmount;
            $transactionType = $direction === 'grant'
                ? (string) ($assetOption['credit_type'] ?? 'grant')
                : (string) ($assetOption['use_type'] ?? 'use');
            $transactionId = sr_content_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => $signedAmount,
                'transaction_type' => $transactionType,
                'reason' => '콘텐츠 완료 버튼 처리',
                'reference_type' => 'content.action',
                'reference_id' => (string) $pageId,
                'created_by_account_id' => null,
            ]);
            sr_content_update_asset_action_transaction($pdo, $dedupeKey, $transactionId);
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif ($dedupeKey !== '') {
            sr_content_delete_asset_action_placeholder($pdo, $dedupeKey);
        }
        if ($startedTransaction && sr_content_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_asset_action_failed');
        }

        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => '포인트/금액 처리에 실패했습니다.',
        ];
    }

    return [
        'allowed' => true,
        'completed' => true,
        'asset_module' => $assetModuleValue,
        'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
        'amount' => $amount,
        'direction' => $direction,
        'message' => '',
    ];
}
