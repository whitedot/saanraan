<?php

declare(strict_types=1);

function sr_content_asset_access_required(array $page): bool
{
    return (int) ($page['asset_access_enabled'] ?? 0) === 1
        && (int) ($page['asset_access_amount'] ?? 0) > 0;
}

function sr_content_asset_amounts_with_group_policy(PDO $pdo, int $accountId, array $assetModules, array $amounts, int $fallbackAmount, mixed $policyValue, int $policySetId = 0, string $operation = 'use'): array
{
    sr_content_require_asset_group_policy_helpers();
    $operation = in_array($operation, ['grant', 'use', 'neutral'], true) ? $operation : 'use';
    $policySetIds = sr_content_asset_policy_set_ids_with_legacy($policyValue, $policySetId);
    $policySetTitles = [];
    $policies = [];
    if ($policySetIds !== []) {
        $policyIndex = 1;
        foreach ($policySetIds as $selectedSetId) {
            $policySet = sr_content_asset_policy_set_by_id($pdo, (int) $selectedSetId);
            if (!is_array($policySet) || (string) ($policySet['status'] ?? '') !== 'enabled') {
                continue;
            }
            $policySetTitles[] = (string) ($policySet['title'] ?? $policySet['set_key'] ?? $selectedSetId);
            foreach (sr_content_asset_group_policies_from_value((string) ($policySet['policies_json'] ?? '')) as $policy) {
                $policy['policy_id'] = $policyIndex;
                $policyIndex += 1;
                $policies[] = $policy;
            }
        }
    } else {
        $policies = sr_content_asset_group_policies_from_value($policyValue);
    }
    $adjustedAmounts = [];
    $snapshots = [];
    $sourceAmounts = $amounts;
    if ($sourceAmounts === [] && $assetModules !== []) {
        $sourceAmounts[(string) $assetModules[0]] = $fallbackAmount;
    }

    foreach ($sourceAmounts as $assetModule => $baseAmount) {
        $baseAmount = max(0, (int) $baseAmount);
        $snapshot = sr_admin_asset_group_policy_apply($pdo, $accountId, $baseAmount, $policies, (string) $assetModule, $operation);
        $finalAmount = max(0, (int) ($snapshot['final_amount'] ?? $baseAmount));
        $snapshot['asset_module'] = (string) $assetModule;
        $snapshot['policy_set_id'] = (int) ($policySetIds[0] ?? 0);
        $snapshot['policy_set_ids'] = $policySetIds;
        $snapshot['policy_set_key'] = '';
        $snapshot['policy_set_title'] = implode(', ', $policySetTitles);
        $snapshot['final_amount'] = $finalAmount;
        $snapshots[(string) $assetModule] = $snapshot;
        if ($finalAmount > 0) {
            $adjustedAmounts[(string) $assetModule] = $finalAmount;
        }
    }

    return [
        'amounts' => $adjustedAmounts,
        'amount' => sr_content_asset_amount_total($adjustedAmounts),
        'snapshots' => $snapshots,
        'policies_applied' => $policies !== [],
    ];
}

function sr_content_asset_group_policy_snapshot_json(array $snapshots): string
{
    if ($snapshots === []) {
        return '';
    }

    $json = json_encode(array_values($snapshots), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '';
}

function sr_content_asset_access_reference_id(int $pageId): string
{
    return (string) $pageId;
}

function sr_content_asset_access_dedupe_key(string $assetModule, int $accountId, int $subjectId, string $accessKind = 'view'): string
{
    return 'content.' . $accessKind . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId;
}

function sr_content_asset_access_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_content_asset_access_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_has_paid_access(PDO $pdo, string $assetModule, int $accountId, int $subjectId, string $accessKind = 'view'): bool
{
    $dedupeKey = sr_content_asset_access_dedupe_key($assetModule, $accountId, $subjectId, $accessKind);
    $log = sr_content_asset_access_log($pdo, $dedupeKey);

    return is_array($log) && ((int) ($log['transaction_id'] ?? 0) > 0 || (int) ($log['amount'] ?? -1) === 0);
}

function sr_content_has_paid_access_for_modules(PDO $pdo, array $assetModules, int $accountId, int $subjectId, string $accessKind = 'view'): bool
{
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        if (sr_content_has_paid_access($pdo, $assetModule, $accountId, $subjectId, $accessKind)) {
            return true;
        }
    }

    return false;
}

function sr_content_has_asset_access_history(PDO $pdo, array $assetModules, int $accountId, int $subjectId, string $accessKind, string $policy): bool
{
    $policy = sr_content_once_history_policy($policy);
    if ($policy === 'current_asset_once') {
        return sr_content_has_paid_access_for_modules($pdo, $assetModules, $accountId, $subjectId, $accessKind);
    }

    $params = [
        'account_id' => $accountId,
        'reference_id' => (string) $subjectId,
        'access_kind' => $accessKind,
    ];
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_content_asset_access_logs
         WHERE account_id = :account_id
           AND reference_id = :reference_id
           AND access_kind = :access_kind
           AND (transaction_id > 0 OR amount = 0)'
        . ' LIMIT 1'
    );
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_content_has_coupon_access_history(PDO $pdo, int $pageId, int $accountId): bool
{
    if ($pageId <= 0 || $accountId <= 0 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return false;
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_has_redemption')) {
        return false;
    }

    return sr_coupon_has_redemption($pdo, $accountId, 'content.view:coupon:' . (string) $accountId . ':' . (string) $pageId);
}

function sr_content_access_entitlements_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_content_access_entitlements LIMIT 1');
        $exists = $stmt !== false;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_content_access_entitlement_subject_type(string $accessKind): string
{
    return $accessKind === 'download' ? 'content_file' : 'content';
}

function sr_content_grant_access_entitlement(PDO $pdo, int $accountId, int $contentId, string $subjectType, int $subjectId, string $accessKind, string $sourceKind, string $sourceAssetModule = '', string $sourceChargePolicy = 'once', string $sourceReference = ''): void
{
    if ($accountId <= 0 || $contentId <= 0 || $subjectId <= 0 || $subjectType === '' || $accessKind === '' || !sr_content_access_entitlements_table_exists($pdo)) {
        return;
    }

    $insertVerb = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_content_access_entitlements
            (account_id, content_id, subject_type, subject_id, access_kind, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at)
         VALUES
            (:account_id, :content_id, :subject_type, :subject_id, :access_kind, :source_kind, :source_asset_module, :source_charge_policy, :source_reference, :granted_at, :created_at)'
    );
    $now = sr_now();
    $stmt->execute([
        'account_id' => $accountId,
        'content_id' => $contentId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'access_kind' => $accessKind,
        'source_kind' => $sourceKind,
        'source_asset_module' => $sourceAssetModule,
        'source_charge_policy' => $sourceChargePolicy,
        'source_reference' => $sourceReference,
        'granted_at' => $now,
        'created_at' => $now,
    ]);
}

function sr_content_revoke_coupon_access_entitlements(PDO $pdo, int $accountId, string $sourceReference): int
{
    if ($accountId <= 0 || $sourceReference === '' || !sr_content_access_entitlements_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "DELETE FROM sr_content_access_entitlements
         WHERE account_id = :account_id
           AND source_kind = 'coupon'
           AND source_reference = :source_reference"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'source_reference' => $sourceReference,
    ]);

    return $stmt->rowCount();
}

function sr_content_revoke_file_download_access_entitlement(PDO $pdo, int $accountId, int $contentId, int $fileId): int
{
    if ($accountId <= 0 || $contentId <= 0 || $fileId <= 0 || !sr_content_access_entitlements_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "DELETE FROM sr_content_access_entitlements
         WHERE account_id = :account_id
           AND content_id = :content_id
           AND subject_type = 'content_file'
           AND subject_id = :subject_id
           AND access_kind = 'download'"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'content_id' => $contentId,
        'subject_id' => $fileId,
    ]);

    return $stmt->rowCount();
}

function sr_content_revoke_access_entitlement_by_source(PDO $pdo, int $accountId, int $contentId, string $subjectType, int $subjectId, string $accessKind, string $sourceKind, string $sourceReference): int
{
    if (
        $accountId <= 0
        || $contentId <= 0
        || $subjectType === ''
        || $subjectId <= 0
        || $accessKind === ''
        || $sourceKind === ''
        || $sourceReference === ''
        || !sr_content_access_entitlements_table_exists($pdo)
    ) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM sr_content_access_entitlements
         WHERE account_id = :account_id
           AND content_id = :content_id
           AND subject_type = :subject_type
           AND subject_id = :subject_id
           AND access_kind = :access_kind
           AND source_kind = :source_kind
           AND source_reference = :source_reference'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'content_id' => $contentId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'access_kind' => $accessKind,
        'source_kind' => $sourceKind,
        'source_reference' => $sourceReference,
    ]);

    return $stmt->rowCount();
}

function sr_content_anonymize_access_entitlements(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0 || !sr_content_access_entitlements_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_content_access_entitlements
         SET account_id = NULL,
             source_reference = \'\',
             anonymized_at = :anonymized_at
         WHERE account_id = :account_id'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'anonymized_at' => sr_now(),
    ]);

    return $stmt->rowCount();
}

function sr_content_has_access_entitlement(PDO $pdo, array $assetModules, int $accountId, int $subjectId, string $accessKind, string $policy): bool
{
    $policy = sr_content_once_history_policy($policy);
    if (!sr_content_access_entitlements_table_exists($pdo)) {
        if (sr_content_has_asset_access_history($pdo, $assetModules, $accountId, $subjectId, $accessKind, $policy)) {
            return true;
        }

        return $policy === 'all_access'
            && $accessKind === 'view'
            && sr_content_has_coupon_access_history($pdo, $subjectId, $accountId);
    }

    $conditions = [
        'account_id = :account_id',
        'subject_type = :subject_type',
        'subject_id = :subject_id',
        'access_kind = :access_kind',
        'anonymized_at IS NULL',
    ];
    $params = [
        'account_id' => $accountId,
        'subject_type' => sr_content_access_entitlement_subject_type($accessKind),
        'subject_id' => $subjectId,
        'access_kind' => $accessKind,
    ];

    if ($policy === 'asset_any') {
        $conditions[] = 'source_kind = \'asset\'';
    } elseif ($policy === 'current_asset_once') {
        $moduleKeys = sr_content_asset_module_keys_from_value($assetModules);
        if ($moduleKeys === []) {
            return false;
        }
        $placeholders = [];
        foreach ($moduleKeys as $index => $assetModule) {
            $key = 'asset_module_' . (string) $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $assetModule;
        }
        $conditions[] = 'source_kind = \'asset\'';
        $conditions[] = 'source_charge_policy = \'once\'';
        $conditions[] = 'source_asset_module IN (' . implode(', ', $placeholders) . ')';
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_content_access_entitlements
         WHERE ' . implode(' AND ', $conditions) . '
         LIMIT 1'
    );
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_content_once_access_already_granted(PDO $pdo, array $assetModules, int $accountId, int $subjectId, string $accessKind = 'view'): bool
{
    $settings = sr_content_settings($pdo);
    $policy = sr_content_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));

    return sr_content_has_access_entitlement($pdo, $assetModules, $accountId, $subjectId, $accessKind, $policy);
}

function sr_content_asset_balance(PDO $pdo, string $assetModule, int $accountId): int
{
    if (!sr_content_asset_module_is_available($pdo, $assetModule)) {
        return 0;
    }

    $option = sr_content_asset_modules($pdo)[$assetModule];
    $balanceFunction = (string) $option['balance_function'];

    return (int) $balanceFunction($pdo, $accountId);
}

function sr_content_create_asset_transaction(PDO $pdo, string $assetModule, array $data): int
{
    if (!sr_content_asset_module_is_available($pdo, $assetModule)) {
        throw new RuntimeException('Page asset module is not available.');
    }

    $option = sr_content_asset_modules($pdo)[$assetModule];
    $transactionFunction = (string) $option['transaction_function'];

    return (int) $transactionFunction($pdo, $data);
}

function sr_content_insert_asset_access_placeholder(PDO $pdo, int $pageId, int $accountId, string $assetModule, int $amount, string $chargePolicy, string $dedupeKey, string $referenceType = 'content.view', ?string $referenceId = null, string $accessKind = 'view', string $groupPolicySnapshotJson = '', int $settlementAmount = 0, string $settlementCurrency = 'KRW', string $purchasePowerSnapshotJson = ''): bool
{
    $settlementAmount = max(0, $settlementAmount);
    $settlementKind = sr_content_asset_settlement_kind_for_use($amount, $settlementAmount, $purchasePowerSnapshotJson);
    $insertVerb = 'INSERT IGNORE';
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $insertVerb = 'INSERT OR IGNORE';
        }
    } catch (Throwable $exception) {
        $insertVerb = 'INSERT IGNORE';
    }
    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_content_asset_access_logs
            (content_id, account_id, asset_module, transaction_id, reference_type, reference_id, access_kind, charge_policy, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, settlement_kind, snapshot_schema_version, rounding_policy_version, log_status, group_policy_snapshot_json, dedupe_key, created_at)
         VALUES
            (:content_id, :account_id, :asset_module, 0, :reference_type, :reference_id, :access_kind, :charge_policy, :amount, :settlement_amount, :settlement_currency, :purchase_power_snapshot_json, :settlement_kind, :snapshot_schema_version, :rounding_policy_version, :log_status, :group_policy_snapshot_json, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'content_id' => $pageId,
        'account_id' => $accountId,
        'asset_module' => $assetModule,
        'reference_type' => $referenceType,
        'reference_id' => $referenceId ?? sr_content_asset_access_reference_id($pageId),
        'access_kind' => $accessKind,
        'charge_policy' => $chargePolicy,
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

function sr_content_update_asset_access_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_content_asset_access_logs
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

function sr_content_complete_zero_asset_access_log(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_content_asset_access_logs
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

function sr_content_delete_asset_access_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_content_asset_access_logs
         WHERE dedupe_key = :dedupe_key
           AND log_status = :log_status'
    );
    $stmt->execute([
        'dedupe_key' => $dedupeKey,
        'log_status' => sr_content_asset_log_status_pending(),
    ]);
}

function sr_content_asset_access_result(PDO $pdo, bool $allowed, bool $charged, string $assetModuleValue, int $amount, string $message = '', array $extra = []): array
{
    return array_merge([
        'allowed' => $allowed,
        'charged' => $charged,
        'asset_module' => $assetModuleValue,
        'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
        'amount' => $amount,
        'message' => $message,
    ], $extra);
}

function sr_content_record_payment_ledger_if_available(PDO $pdo, array $record, array $items): int
{
    if (!function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'payment_ledger')) {
        return 0;
    }
    if (!is_file(SR_ROOT . '/modules/payment_ledger/helpers.php')) {
        throw new RuntimeException('결제 기록 기반 모듈 helper를 찾을 수 없습니다.');
    }

    require_once SR_ROOT . '/modules/payment_ledger/helpers.php';
    if (!function_exists('sr_payment_ledger_record_payment') || !sr_payment_ledger_tables_available($pdo)) {
        throw new RuntimeException('결제 기록 기반 테이블이 준비되지 않았습니다.');
    }

    return sr_payment_ledger_record_payment($pdo, $record, $items);
}

function sr_content_payment_coupon_item(array $couponResult, string $settlementCurrency): array
{
    $redemptionId = (int) ($couponResult['coupon_redemption_id'] ?? 0);
    if ($redemptionId <= 0 || empty($couponResult['processed'])) {
        return [];
    }

    return [
        'item_kind' => 'coupon_redemption',
        'owner_module' => 'coupon',
        'reference_type' => 'coupon_redemption',
        'reference_id' => (string) $redemptionId,
        'amount' => -max(0, (int) ($couponResult['discount_amount'] ?? 0)),
        'currency_code' => $settlementCurrency,
        'reversible' => true,
        'snapshot' => [
            'coupon_issue_id' => (int) ($couponResult['coupon_issue_id'] ?? 0),
            'coupon_definition_id' => (int) ($couponResult['coupon_definition_id'] ?? 0),
            'coupon_type' => (string) ($couponResult['coupon_type'] ?? ''),
            'dedupe_key' => (string) ($couponResult['dedupe_key'] ?? ''),
        ],
    ];
}

function sr_content_payment_access_item(string $subjectType, int $subjectId, string $accessKind, string $sourceKind, string $sourceReference = ''): array
{
    return [
        'item_kind' => 'access_entitlement',
        'owner_module' => 'content',
        'reference_type' => 'content.access_entitlement',
        'reference_id' => $subjectType . ':' . (string) $subjectId . ':' . $accessKind,
        'amount' => 0,
        'currency_code' => '',
        'reversible' => true,
        'snapshot' => [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'access_kind' => $accessKind,
            'source_kind' => $sourceKind,
            'source_reference' => $sourceReference,
        ],
    ];
}

function sr_content_view_payment_refund_policy_version(): string
{
    return 'content_view_refund_v1';
}

function sr_content_view_payment_type(array $couponResult, bool $hasAssetSettlement): string
{
    if (!empty($couponResult['processed'])) {
        if ($hasAssetSettlement) {
            return 'coupon_partial_discount_asset';
        }

        return (string) ($couponResult['coupon_type'] ?? '') === 'access'
            ? 'coupon_access'
            : 'coupon_full_discount';
    }

    return $hasAssetSettlement ? 'asset_only' : 'settled_zero';
}

function sr_content_record_view_payment_log(PDO $pdo, array $row): void
{
    $contentId = max(0, (int) ($row['content_id'] ?? 0));
    $accountId = max(0, (int) ($row['account_id'] ?? 0));
    $paymentDedupeKey = sr_content_clean_text((string) ($row['payment_dedupe_key'] ?? ''), 190);
    if ($contentId <= 0 || $accountId <= 0 || $paymentDedupeKey === '') {
        throw new InvalidArgumentException('콘텐츠 열람 결제 단위 로그의 필수 값이 없습니다.');
    }

    $assetLogIds = [];
    foreach ((array) ($row['asset_access_log_ids'] ?? []) as $assetLogId) {
        $assetLogId = (int) $assetLogId;
        if ($assetLogId > 0) {
            $assetLogIds[$assetLogId] = $assetLogId;
        }
    }
    $assetLogIdsJson = json_encode(array_values($assetLogIds), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $paymentType = sr_content_clean_key((string) ($row['payment_type'] ?? 'asset_only'));
    $allowedPaymentTypes = ['asset_only', 'coupon_access', 'coupon_full_discount', 'coupon_partial_discount_asset', 'settled_zero'];
    if (!in_array($paymentType, $allowedPaymentTypes, true)) {
        $paymentType = 'asset_only';
    }

    $settlementKind = sr_content_clean_key((string) ($row['settlement_kind'] ?? ''));
    $allowedSettlementKinds = ['paid', 'free', 'paid_settled_zero', 'preview_test_zero'];
    if (!in_array($settlementKind, $allowedSettlementKinds, true)) {
        $settlementKind = max(0, (int) ($row['settlement_amount'] ?? 0)) > 0 ? 'paid' : 'paid_settled_zero';
    }

    $insertVerb = 'INSERT IGNORE';
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $insertVerb = 'INSERT OR IGNORE';
        }
    } catch (Throwable $exception) {
        $insertVerb = 'INSERT IGNORE';
    }

    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_content_view_payment_logs
            (content_id, content_title_snapshot, content_slug_snapshot, account_id, payment_type, settlement_kind, charge_policy, asset_module, payable_amount, settlement_amount, settlement_currency, asset_access_log_ids_json, coupon_redemption_id, coupon_dedupe_key, payment_dedupe_key, refund_status, refund_transaction_ids_json, refund_note, refund_policy_version, created_at)
         VALUES
            (:content_id, :content_title_snapshot, :content_slug_snapshot, :account_id, :payment_type, :settlement_kind, :charge_policy, :asset_module, :payable_amount, :settlement_amount, :settlement_currency, :asset_access_log_ids_json, :coupon_redemption_id, :coupon_dedupe_key, :payment_dedupe_key, \'\', \'[]\', \'\', :refund_policy_version, :created_at)'
    );
    $stmt->execute([
        'content_id' => $contentId,
        'content_title_snapshot' => sr_content_clean_text((string) ($row['content_title_snapshot'] ?? ''), 160),
        'content_slug_snapshot' => sr_content_clean_text((string) ($row['content_slug_snapshot'] ?? ''), 160),
        'account_id' => $accountId,
        'payment_type' => $paymentType,
        'settlement_kind' => $settlementKind,
        'charge_policy' => sr_content_clean_key((string) ($row['charge_policy'] ?? 'once')),
        'asset_module' => sr_content_clean_text((string) ($row['asset_module'] ?? ''), 60),
        'payable_amount' => max(0, (int) ($row['payable_amount'] ?? 0)),
        'settlement_amount' => max(0, (int) ($row['settlement_amount'] ?? 0)),
        'settlement_currency' => sr_content_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($row['settlement_currency'] ?? 'KRW')]),
        'asset_access_log_ids_json' => is_string($assetLogIdsJson) ? $assetLogIdsJson : '[]',
        'coupon_redemption_id' => (int) ($row['coupon_redemption_id'] ?? 0) > 0 ? (int) $row['coupon_redemption_id'] : null,
        'coupon_dedupe_key' => sr_content_clean_text((string) ($row['coupon_dedupe_key'] ?? ''), 160),
        'payment_dedupe_key' => $paymentDedupeKey,
        'refund_policy_version' => sr_content_view_payment_refund_policy_version(),
        'created_at' => sr_now(),
    ]);
}

function sr_content_view_payment_log_by_id_for_update(PDO $pdo, int $paymentLogId): ?array
{
    if ($paymentLogId <= 0) {
        return null;
    }

    $lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    $stmt = $pdo->prepare('SELECT * FROM sr_content_view_payment_logs WHERE id = :id LIMIT 1' . $lockClause);
    $stmt->execute(['id' => $paymentLogId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_view_payment_log_access_log_ids(array $paymentLog): array
{
    $decoded = json_decode((string) ($paymentLog['asset_access_log_ids_json'] ?? '[]'), true);
    if (!is_array($decoded)) {
        return [];
    }

    $ids = [];
    foreach ($decoded as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function sr_content_view_payment_access_logs_for_refund(PDO $pdo, array $paymentLog): array
{
    $ids = sr_content_view_payment_log_access_log_ids($paymentLog);
    if ($ids === []) {
        return [];
    }

    $params = [
        'content_id' => (int) ($paymentLog['content_id'] ?? 0),
        'reference_id' => (string) (int) ($paymentLog['content_id'] ?? 0),
        'account_id' => (int) ($paymentLog['account_id'] ?? 0),
    ];
    $placeholders = [];
    foreach ($ids as $index => $id) {
        $key = 'id_' . (string) $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_content_asset_access_logs
         WHERE id IN (' . implode(', ', $placeholders) . ')
           AND content_id = :content_id
           AND reference_type = \'content.view\'
           AND reference_id = :reference_id
           AND access_kind = \'view\'
           AND account_id = :account_id
         ORDER BY id ASC'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_content_view_payment_access_revoke_sources(array $paymentLog, array $accessLogs): array
{
    $sources = [];
    $couponDedupeKey = sr_content_clean_text((string) ($paymentLog['coupon_dedupe_key'] ?? ''), 160);
    if ($couponDedupeKey !== '') {
        $sources['coupon:' . $couponDedupeKey] = [
            'source_kind' => 'coupon',
            'source_reference' => $couponDedupeKey,
        ];
    }

    foreach ($accessLogs as $accessLog) {
        $assetModule = sr_content_clean_key((string) ($accessLog['asset_module'] ?? ''));
        $transactionId = (int) ($accessLog['transaction_id'] ?? 0);
        if ($assetModule !== '' && $transactionId > 0) {
            $sourceReference = $assetModule . ':' . (string) $transactionId;
            $sources['asset:' . $sourceReference] = [
                'source_kind' => 'asset',
                'source_reference' => $sourceReference,
            ];
            continue;
        }

        $dedupeKey = sr_content_clean_text((string) ($accessLog['dedupe_key'] ?? ''), 160);
        if ($dedupeKey !== '') {
            $sources['asset_group_policy:' . $dedupeKey] = [
                'source_kind' => 'asset_group_policy',
                'source_reference' => $dedupeKey,
            ];
        }
    }

    return array_values($sources);
}

function sr_content_mark_view_payment_ledger_items_reversed_if_available(PDO $pdo, int $accountId, array $references, string $reason): array
{
    if ($references === [] || !function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'payment_ledger')) {
        return ['payment_record_ids' => [], 'reversed_item_count' => 0, 'refunded_record_ids' => []];
    }
    if (!is_file(SR_ROOT . '/modules/payment_ledger/helpers.php')) {
        throw new RuntimeException('결제 기록 기반 모듈 helper를 찾을 수 없습니다.');
    }

    require_once SR_ROOT . '/modules/payment_ledger/helpers.php';
    if (!function_exists('sr_payment_ledger_mark_item_references_reversed') || !sr_payment_ledger_tables_available($pdo)) {
        throw new RuntimeException('결제 기록 기반 테이블이 준비되지 않았습니다.');
    }

    return sr_payment_ledger_mark_item_references_reversed($pdo, $accountId, $references, $reason, false);
}

function sr_content_refund_view_payment(PDO $pdo, int $paymentLogId, int $adminAccountId, string $refundNote, string $refundExpirationPolicy = 'original'): array
{
    $refundNote = sr_content_clean_text($refundNote, 255);
    $refundExpirationPolicy = in_array($refundExpirationPolicy, ['original', 'reset'], true) ? $refundExpirationPolicy : 'original';
    if ($paymentLogId <= 0) {
        return ['ok' => false, 'message' => '환불할 콘텐츠 열람 결제 내역을 선택하세요.'];
    }
    if ($adminAccountId <= 0) {
        return ['ok' => false, 'message' => '처리 관리자 정보를 확인할 수 없습니다.'];
    }
    if ($refundNote === '') {
        return ['ok' => false, 'message' => '환불 사유를 입력하세요.'];
    }

    $couponNotification = [];
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $paymentLog = sr_content_view_payment_log_by_id_for_update($pdo, $paymentLogId);
        if (!is_array($paymentLog)) {
            throw new RuntimeException('환불할 콘텐츠 열람 결제 내역을 찾을 수 없습니다.');
        }
        if ((string) ($paymentLog['refund_status'] ?? '') !== '') {
            throw new RuntimeException('이미 환불 또는 접근권 회수 처리된 결제입니다.');
        }

        $accountId = (int) ($paymentLog['account_id'] ?? 0);
        $contentId = (int) ($paymentLog['content_id'] ?? 0);
        if ($accountId <= 0 || $contentId <= 0) {
            throw new RuntimeException('환불 대상 회원 또는 콘텐츠 정보를 확인할 수 없습니다.');
        }

        $accessLogIds = sr_content_view_payment_log_access_log_ids($paymentLog);
        $accessLogs = $accessLogIds !== [] ? sr_content_view_payment_access_logs_for_refund($pdo, $paymentLog) : [];
        if ($accessLogIds !== [] && $accessLogs === []) {
            throw new RuntimeException('연결된 차감 또는 접근권 로그를 찾을 수 없습니다.');
        }

        $refundTransactionIds = [];
        $paymentLedgerReferences = [];
        foreach ($accessLogs as $accessLog) {
            $amount = (int) ($accessLog['amount'] ?? 0);
            $transactionId = (int) ($accessLog['transaction_id'] ?? 0);
            if ($amount <= 0 || $transactionId <= 0) {
                continue;
            }

            $assetModule = (string) ($accessLog['asset_module'] ?? '');
            if (!sr_content_asset_module_is_available($pdo, $assetModule)) {
                throw new RuntimeException('환불할 차감 항목을 사용할 수 없습니다: ' . $assetModule);
            }

            $assetOption = sr_content_asset_modules($pdo)[$assetModule];
            $transactionData = [
                'account_id' => $accountId,
                'amount' => $amount,
                'transaction_type' => (string) ($assetOption['refund_type'] ?? 'refund'),
                'reason' => '콘텐츠 열람 환불: ' . $refundNote,
                'reference_type' => 'refund',
                'reference_id' => $assetModule . '_transaction:' . (string) $transactionId,
                'created_by_account_id' => $adminAccountId,
            ];
            if ($assetModule === 'point') {
                $transactionData['refund_expiration_policy'] = $refundExpirationPolicy;
            }
            $paymentLedgerReferences[] = [
                'item_kind' => 'asset_transaction',
                'owner_module' => $assetModule,
                'reference_type' => $assetModule . '_transaction',
                'reference_id' => (string) $transactionId,
            ];
            $paymentLedgerReferences[] = [
                'item_kind' => 'asset_access_log',
                'owner_module' => 'content',
                'reference_type' => 'content_asset_access_log',
                'reference_id' => (string) (int) ($accessLog['id'] ?? 0),
            ];

            if ($assetModule === 'point' && function_exists('sr_point_create_refund_transactions')) {
                foreach (sr_point_create_refund_transactions($pdo, $transactionData) as $refundTransactionId) {
                    $refundTransactionIds[] = $assetModule . ':' . (string) $refundTransactionId;
                }
                continue;
            }

            $refundTransactionId = sr_content_create_asset_transaction($pdo, $assetModule, $transactionData);
            $refundTransactionIds[] = $assetModule . ':' . (string) $refundTransactionId;
        }

        $couponRefund = [];
        $couponRedemptionId = (int) ($paymentLog['coupon_redemption_id'] ?? 0);
        if ($couponRedemptionId > 0) {
            if (!is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
                throw new RuntimeException('쿠폰 환불 helper를 찾을 수 없습니다.');
            }
            require_once SR_ROOT . '/modules/coupon/helpers.php';
            if (!function_exists('sr_coupon_refund_redemption_state_only')) {
                throw new RuntimeException('쿠폰 상태 환불 계약을 찾을 수 없습니다.');
            }
            $couponRefund = sr_coupon_refund_redemption_state_only($pdo, $couponRedemptionId, $adminAccountId, $refundNote, [
                'allowed_coupon_types' => ['access', 'fixed_discount', 'percent_discount'],
                'require_refundable_policy' => false,
            ]);
            $paymentLedgerReferences[] = [
                'item_kind' => 'coupon_redemption',
                'owner_module' => 'coupon',
                'reference_type' => 'coupon_redemption',
                'reference_id' => (string) $couponRedemptionId,
            ];
            $couponNotification = [
                'coupon_issue_id' => (int) ($couponRefund['coupon_issue_id'] ?? 0),
                'event_key' => (string) ($couponRefund['notification_event_key'] ?? 'redemption.refunded'),
                'payload' => is_array($couponRefund['notification_payload'] ?? null) ? $couponRefund['notification_payload'] : [],
            ];
        }

        $accessRevoked = false;
        $shouldRevokeAccess = (string) ($paymentLog['charge_policy'] ?? '') === 'once' || (int) ($paymentLog['settlement_amount'] ?? 0) <= 0;
        if ($shouldRevokeAccess) {
            foreach (sr_content_view_payment_access_revoke_sources($paymentLog, $accessLogs) as $source) {
                $accessRevoked = sr_content_revoke_access_entitlement_by_source(
                    $pdo,
                    $accountId,
                    $contentId,
                    'content',
                    $contentId,
                    'view',
                    (string) ($source['source_kind'] ?? ''),
                    (string) ($source['source_reference'] ?? '')
                ) > 0 || $accessRevoked;
            }
        }
        if ($shouldRevokeAccess && ($refundTransactionIds !== [] || $couponRefund !== []) && !$accessRevoked) {
            throw new RuntimeException('결제 단위와 일치하는 콘텐츠 열람 접근권을 회수할 수 없습니다.');
        }
        if ($accessRevoked) {
            $paymentLedgerReferences[] = [
                'item_kind' => 'access_entitlement',
                'owner_module' => 'content',
                'reference_type' => 'content.access_entitlement',
                'reference_id' => 'content:' . (string) $contentId . ':view',
            ];
        }

        if ($refundTransactionIds === [] && !$accessRevoked && $couponRefund === []) {
            throw new RuntimeException('환불할 원장 거래나 회수할 접근권을 찾을 수 없습니다.');
        }

        $refundTransactionIdsJson = json_encode($refundTransactionIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = sr_now();
        $refundStatus = $refundTransactionIds !== [] || $couponRefund !== [] ? 'refunded' : 'access_revoked';
        $stmt = $pdo->prepare(
            'UPDATE sr_content_view_payment_logs
             SET refund_status = :refund_status,
                 refund_transaction_ids_json = :refund_transaction_ids_json,
                 refund_note = :refund_note,
                 refunded_by_account_id = :refunded_by_account_id,
                 refunded_at = :refunded_at,
                 access_revoked_at = :access_revoked_at
             WHERE id = :id
               AND refund_status = \'\''
        );
        $stmt->execute([
            'refund_status' => $refundStatus,
            'refund_transaction_ids_json' => is_string($refundTransactionIdsJson) ? $refundTransactionIdsJson : '[]',
            'refund_note' => $refundNote,
            'refunded_by_account_id' => $adminAccountId,
            'refunded_at' => $now,
            'access_revoked_at' => $accessRevoked ? $now : null,
            'id' => $paymentLogId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('이미 처리된 콘텐츠 열람 결제입니다.');
        }
        sr_content_mark_view_payment_ledger_items_reversed_if_available($pdo, $accountId, $paymentLedgerReferences, '콘텐츠 열람 환불: ' . $refundNote);

        if ($startedTransaction) {
            $pdo->commit();
        }

        if ($startedTransaction && $couponNotification !== [] && function_exists('sr_coupon_notify_issue_event')) {
            $payload = is_array($couponNotification['payload'] ?? null) ? $couponNotification['payload'] : [];
            $payload['revoked_access_count'] = $accessRevoked ? 1 : 0;
            sr_coupon_notify_issue_event($pdo, (int) ($couponNotification['coupon_issue_id'] ?? 0), (string) ($couponNotification['event_key'] ?? 'redemption.refunded'), $adminAccountId, $payload);
        }

        try {
            sr_audit_log($pdo, [
                'actor_account_id' => $adminAccountId,
                'actor_type' => 'admin',
                'event_type' => 'content_view_payment.refunded',
                'target_type' => 'content_view_payment',
                'target_id' => (string) $paymentLogId,
                'result' => 'success',
                'message' => 'Content view payment refunded.',
                'metadata' => [
                    'content_id' => $contentId,
                    'account_id' => $accountId,
                    'coupon_redemption_id' => $couponRedemptionId,
                    'refund_status' => $refundStatus,
                    'refund_expiration_policy' => $refundExpirationPolicy,
                    'refund_transaction_ids' => $refundTransactionIds,
                    'access_revoked' => $accessRevoked,
                ],
            ]);
        } catch (Throwable $auditException) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($auditException, 'content_view_payment_refund_audit_failed');
            }
        }

        return [
            'ok' => true,
            'message' => $refundStatus === 'refunded' ? '콘텐츠 열람 결제를 환불 처리했습니다.' : '콘텐츠 열람 접근권을 회수했습니다.',
            'coupon_notification' => $startedTransaction ? [] : $couponNotification,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_view_payment_refund_failed');
        }

        return ['ok' => false, 'message' => $exception->getMessage()];
    }
}

function sr_content_available_coupon_issues(PDO $pdo, int $accountId, string $targetType, int $targetId, int $limit = 20): array
{
    if ($accountId <= 0 || $targetId <= 0 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return [];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_active_account_target_issues')) {
        return [];
    }

    return sr_coupon_active_account_target_issues($pdo, $accountId, $targetType, (string) $targetId, $limit);
}

function sr_content_asset_access_dedupe_key_for_policy(string $chargePolicy, string $referenceType, string $assetModule, int $accountId, int $subjectId, string $accessKind = 'view', string $requestToken = '', int $settlementAmount = 0, string $settlementCurrency = ''): string
{
    if ($chargePolicy === 'once') {
        return sr_content_asset_access_dedupe_key($assetModule, $accountId, $subjectId, $accessKind);
    }

    $requestToken = preg_match('/\A[a-f0-9]{32}(?:[a-f0-9]{32})?\z/', $requestToken) === 1 ? $requestToken : bin2hex(random_bytes(16));
    $settlementCurrency = function_exists('sr_normalize_currency_code') ? sr_normalize_currency_code($settlementCurrency) : strtoupper(trim($settlementCurrency));

    return $referenceType . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':' . (string) max(0, $settlementAmount) . ':' . $settlementCurrency . ':' . $requestToken;
}

function sr_content_charge_view_access(PDO $pdo, array $page, int $accountId, bool $process = true, string $requestToken = '', int $couponIssueId = 0, bool $consumeConfirmationSession = true, bool $confirmedPost = false, bool $assetExchangeConfirmed = false): array
{
    return sr_content_asset_retry_operation($pdo, static function () use ($pdo, $page, $accountId, $process, $requestToken, $couponIssueId, $consumeConfirmationSession, $confirmedPost, $assetExchangeConfirmed): array {
        return sr_content_charge_view_access_once($pdo, $page, $accountId, $process, $requestToken, $couponIssueId, $consumeConfirmationSession, $confirmedPost, $assetExchangeConfirmed);
    });
}

function sr_content_charge_view_access_once(PDO $pdo, array $page, int $accountId, bool $process = true, string $requestToken = '', int $couponIssueId = 0, bool $consumeConfirmationSession = true, bool $confirmedPost = false, bool $assetExchangeConfirmed = false): array
{
    $pageId = (int) ($page['id'] ?? 0);
    $assetModules = sr_content_asset_module_keys_from_value($page['asset_module'] ?? '');
    $assetModuleValue = sr_content_asset_module_value_from_keys($assetModules);
    $chargePolicy = (string) ($page['asset_charge_policy'] ?? 'once');
    $amounts = sr_content_asset_amounts_from_value($page['asset_access_amounts_json'] ?? '', $assetModules, (int) ($page['asset_access_amount'] ?? 0));
    $amount = $amounts !== [] ? sr_content_asset_amount_total($amounts) : (int) ($page['asset_access_amount'] ?? 0);

    if ($pageId <= 0 || $accountId <= 0 || !sr_content_asset_access_required($page)) {
        return ['allowed' => true, 'charged' => false, 'message' => ''];
    }

    if ($assetModules === [] || !isset(sr_content_asset_view_charge_policies()[$chargePolicy])) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '콘텐츠 유료 열람 설정이 올바르지 않아 열람할 수 없습니다.');
    }

    if (!sr_content_asset_modules_available($pdo, $assetModules)) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '선택한 포인트/금액 항목을 모두 사용할 수 없어 콘텐츠를 열람할 수 없습니다.');
    }

    $policyAmounts = sr_content_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($page['asset_access_amount'] ?? 0), $page['asset_access_group_policies_json'] ?? '', (int) ($page['asset_access_policy_set_id'] ?? 0));
    $amounts = $policyAmounts['amounts'];
    $amount = (int) $policyAmounts['amount'];
    $policySnapshotJson = sr_content_asset_group_policy_snapshot_json($policyAmounts['snapshots']);
    $settlementCurrency = sr_content_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($page['asset_access_settlement_currency'] ?? '')]);
    $paymentPayableAmount = $amount;
    $confirmationFingerprint = sr_content_asset_confirmation_fingerprint('view', $chargePolicy, $assetModuleValue, $amount, $amounts, $policySnapshotJson, $settlementCurrency);
    if ($chargePolicy === 'once' && sr_content_once_access_already_granted($pdo, $assetModules, $accountId, $pageId)) {
        return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['already_paid' => true]);
    }

    if (sr_content_asset_policy_requires_confirmation($chargePolicy) && !$process) {
        if ($consumeConfirmationSession && sr_content_consume_asset_confirmation_session('view', $accountId, $pageId, $confirmationFingerprint)) {
            return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['confirmed_access' => true]);
        }

        $extra = [
            'error_key' => 'asset_confirmation_required',
            'confirmation_request_token' => sr_content_asset_confirmation_request_token('view', $accountId, $pageId, $confirmationFingerprint),
            'confirmation_fingerprint' => $confirmationFingerprint,
            'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content', $pageId),
        ];
        $extra = array_merge($extra, sr_content_asset_settlement_exchange_confirmation_extra($pdo, $assetModules, $accountId, $amount, $settlementCurrency));

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, (string) ($extra['message'] ?? sr_content_asset_confirmation_required_message()), $extra);
    }

    if ($couponIssueId <= 0 && sr_content_asset_policy_requires_confirmation($chargePolicy) && $process && !$confirmedPost) {
        $extra = [
            'error_key' => 'asset_confirmation_required',
            'confirmation_request_token' => sr_content_asset_confirmation_request_token('view', $accountId, $pageId, $confirmationFingerprint),
            'confirmation_fingerprint' => $confirmationFingerprint,
            'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content', $pageId),
        ];
        $extra = array_merge($extra, sr_content_asset_settlement_exchange_confirmation_extra($pdo, $assetModules, $accountId, $amount, $settlementCurrency));

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, (string) ($extra['message'] ?? sr_content_asset_confirmation_required_message()), $extra);
    }

    if ($amount <= 0) {
        $configErrorMessage = sr_content_asset_settlement_config_error_message($pdo, $assetModules, $accountId, 0, $settlementCurrency, '콘텐츠를 열람할 수 없습니다.');
        if ($configErrorMessage !== '') {
            return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, $configErrorMessage);
        }

        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.view', $assetModule, $accountId, $pageId, 'view', $requestToken, 0, $settlementCurrency);
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, 0, $chargePolicy, $dedupeKey, 'content.view', null, 'view', $policySnapshotJson);
            sr_content_complete_zero_asset_access_log($pdo, $dedupeKey);
            sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content', $pageId, 'view', 'asset_group_policy', $assetModule, $chargePolicy, $dedupeKey);
            $zeroAccessLog = sr_content_asset_access_log($pdo, $dedupeKey);
            sr_content_record_view_payment_log($pdo, [
                'content_id' => $pageId,
                'content_title_snapshot' => (string) ($page['title'] ?? ''),
                'content_slug_snapshot' => (string) ($page['slug'] ?? ''),
                'account_id' => $accountId,
                'payment_type' => 'settled_zero',
                'settlement_kind' => 'paid_settled_zero',
                'charge_policy' => $chargePolicy,
                'asset_module' => $assetModule,
                'payable_amount' => $paymentPayableAmount,
                'settlement_amount' => 0,
                'settlement_currency' => $settlementCurrency,
                'asset_access_log_ids' => is_array($zeroAccessLog) && (int) ($zeroAccessLog['id'] ?? 0) > 0 ? [(int) $zeroAccessLog['id']] : [],
                'payment_dedupe_key' => 'content.view:payment-unit:' . sha1($dedupeKey),
            ]);
            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($startedTransaction && sr_content_asset_is_retryable_transaction_exception($exception)) {
                throw $exception;
            }
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'content_asset_group_access_failed');
            }

            return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '포인트/금액 접근권 처리에 실패했습니다.');
        }
        return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, 0, '', ['group_policy_applied' => true]);
    }

    $mixedCouponResult = [];
    $mixedCouponTransactionOpen = false;
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $couponResult = $couponIssueId > 0 ? sr_content_try_coupon_access($pdo, $pageId, $accountId, $chargePolicy, $couponIssueId, [
            'price_amount' => $amount,
            'currency_code' => $settlementCurrency,
            'policy_summary' => '콘텐츠 열람 ' . number_format($amount) . $settlementCurrency,
        ]) : ['allowed' => false, 'processed' => false];
        if (!empty($couponResult['allowed'])) {
            $remainingAmount = max(0, (int) ($couponResult['remaining_amount'] ?? 0));
            if ($remainingAmount > 0) {
                $mixedCouponResult = $couponResult;
                $mixedCouponTransactionOpen = $startedTransaction && $pdo->inTransaction();
                $amount = $remainingAmount;
            } else {
                if (empty($couponResult['already_entitled'])) {
                    sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content', $pageId, 'view', 'coupon', '', $chargePolicy, (string) ($couponResult['dedupe_key'] ?? ''));
                }
                if (!empty($couponResult['processed'])) {
                    $paymentItems = array_values(array_filter([
                        sr_content_payment_coupon_item($couponResult, $settlementCurrency),
                        sr_content_payment_access_item('content', $pageId, 'view', 'coupon', (string) ($couponResult['dedupe_key'] ?? '')),
                    ]));
                    sr_content_record_payment_ledger_if_available($pdo, [
                        'dedupe_key' => 'content.view:payment:coupon:' . (string) ($couponResult['coupon_redemption_id'] ?? ''),
                        'account_id' => $accountId,
                        'subject_module' => 'content',
                        'subject_type' => 'content.view',
                        'subject_id' => (string) $pageId,
                        'payment_kind' => 'purchase',
                        'payable_amount' => $paymentPayableAmount,
                        'settlement_amount' => 0,
                        'settlement_currency' => $settlementCurrency,
                        'description' => '콘텐츠 열람 쿠폰 결제',
                        'snapshot' => [
                            'charge_policy' => $chargePolicy,
                            'coupon_covered_amount' => $paymentPayableAmount,
                        ],
                    ], $paymentItems);
                    sr_content_record_view_payment_log($pdo, [
                        'content_id' => $pageId,
                        'content_title_snapshot' => (string) ($page['title'] ?? ''),
                        'content_slug_snapshot' => (string) ($page['slug'] ?? ''),
                        'account_id' => $accountId,
                        'payment_type' => sr_content_view_payment_type($couponResult, false),
                        'settlement_kind' => 'paid_settled_zero',
                        'charge_policy' => $chargePolicy,
                        'asset_module' => '',
                        'payable_amount' => $paymentPayableAmount,
                        'settlement_amount' => 0,
                        'settlement_currency' => $settlementCurrency,
                        'asset_access_log_ids' => [],
                        'coupon_redemption_id' => (int) ($couponResult['coupon_redemption_id'] ?? 0),
                        'coupon_dedupe_key' => (string) ($couponResult['dedupe_key'] ?? ''),
                        'payment_dedupe_key' => 'content.view:payment-unit:coupon:' . (string) ($couponResult['coupon_redemption_id'] ?? ''),
                    ]);
                }
                if ($startedTransaction) {
                    $pdo->commit();
                }

                return [
                    'allowed' => true,
                    'charged' => false,
                    'coupon_used' => !empty($couponResult['processed']),
                    'already_paid' => !empty($couponResult['already_redeemed']) || !empty($couponResult['already_entitled']),
                    'coupon_title' => (string) ($couponResult['coupon_title'] ?? ''),
                    'asset_module' => $assetModuleValue,
                    'asset_label' => '쿠폰',
                    'amount' => 0,
                    'message' => '',
                    'confirmation_fingerprint' => $confirmationFingerprint,
                ];
            }
        }
        if ($mixedCouponResult === [] && $startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($mixedCouponResult === [] && $couponIssueId > 0) {
            return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '선택한 쿠폰을 사용할 수 없습니다.', [
                'error_key' => 'asset_confirmation_required',
                'confirmation_request_token' => sr_content_asset_confirmation_request_token('view', $accountId, $pageId, $confirmationFingerprint),
                'confirmation_fingerprint' => $confirmationFingerprint,
                'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content', $pageId),
            ]);
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($startedTransaction && sr_content_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_coupon_entitlement_failed');
        }

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '쿠폰 접근권 처리에 실패했습니다.');
    }

    $assetExchangeSuggestion = [];
    $allocations = sr_content_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
    if ($allocations === []) {
        $assetExchangeSuggestion = sr_content_asset_settlement_exchange_suggestion($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
        if ($assetExchangeSuggestion !== []) {
            if (!$assetExchangeConfirmed) {
                if ($mixedCouponTransactionOpen && $pdo->inTransaction()) {
                    $pdo->rollBack();
                    $mixedCouponTransactionOpen = false;
                }
                return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, sr_member_asset_settlement_exchange_message($pdo, sr_content_asset_modules($pdo), $assetExchangeSuggestion, sr_content_asset_confirmation_required_message()), [
                    'error_key' => 'asset_confirmation_required',
                    'confirmation_request_token' => sr_content_asset_confirmation_request_token('view', $accountId, $pageId, $confirmationFingerprint),
                    'confirmation_fingerprint' => $confirmationFingerprint,
                    'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content', $pageId),
                    'asset_exchange_suggestion' => $assetExchangeSuggestion,
                    'asset_exchange_confirmation_required' => true,
                ]);
            }
        } else {
            if ($mixedCouponTransactionOpen && $pdo->inTransaction()) {
                $pdo->rollBack();
                $mixedCouponTransactionOpen = false;
            }
            return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, sr_content_asset_balance_shortage_message($pdo, $assetModules, $accountId, $amount, $settlementCurrency, '콘텐츠를 열람할 수 없습니다.', '선택한 항목의 잔액이 부족해 콘텐츠를 열람할 수 없습니다.'));
        }
    }

    $dedupeKey = '';
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $assetExchangeLogId = 0;
        if ($assetExchangeSuggestion !== [] && $assetExchangeConfirmed) {
            $assetExchangeLogId = sr_member_asset_settlement_execute_exchange_suggestion($pdo, $assetExchangeSuggestion, $accountId);
            $allocations = sr_content_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
            if ($allocations === []) {
                throw new RuntimeException('Automatic asset exchange did not create a payable settlement plan.');
            }
        }

        $pendingAccessCharges = [];
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) ($allocation['asset_amount'] ?? $allocation['amount']);
            $allocatedSettlementAmount = (int) ($allocation['settlement_amount'] ?? 0);
            $allocationSettlementCurrency = (string) ($allocation['settlement_currency'] ?? $settlementCurrency);
            $purchasePowerSnapshotJson = sr_content_asset_purchase_power_snapshot_json(is_array($allocation['purchase_power_snapshot'] ?? null) ? $allocation['purchase_power_snapshot'] : []);
            $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.view', $assetModule, $accountId, $pageId, 'view', $requestToken, $amount, $settlementCurrency);
            $inserted = sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, $allocatedAmount, $chargePolicy, $dedupeKey, 'content.view', null, 'view', sr_content_asset_group_policy_snapshot_json(isset($policyAmounts['snapshots'][$assetModule]) ? [$policyAmounts['snapshots'][$assetModule]] : []), $allocatedSettlementAmount, $allocationSettlementCurrency, $purchasePowerSnapshotJson);
            if (!$inserted) {
                if ($chargePolicy === 'once') {
                    throw new RuntimeException('Incomplete or duplicate content asset access.');
                }
                $existingLog = sr_content_asset_access_log($pdo, $dedupeKey);
                if (is_array($existingLog) && (string) ($existingLog['log_status'] ?? '') === sr_content_asset_log_status_completed()) {
                    $transactionId = (int) ($existingLog['transaction_id'] ?? 0);
                    if ($transactionId > 0) {
                        sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content', $pageId, 'view', 'asset', $assetModule, $chargePolicy, $assetModule . ':' . (string) $transactionId);
                    }
                    continue;
                }
                throw new RuntimeException('Content asset access is still processing.');
            }

            $pendingAccessCharges[] = [
                'asset_module' => $assetModule,
                'amount' => $allocatedAmount,
                'settlement_amount' => $allocatedSettlementAmount,
                'settlement_currency' => $allocationSettlementCurrency,
                'dedupe_key' => $dedupeKey,
            ];
        }

        $paymentItems = [];
        $paymentDedupeParts = [];
        $assetAccessLogIds = [];
        foreach ($pendingAccessCharges as $pendingAccessCharge) {
            $assetModule = (string) $pendingAccessCharge['asset_module'];
            $allocatedAmount = (int) $pendingAccessCharge['amount'];
            $allocatedSettlementAmount = (int) ($pendingAccessCharge['settlement_amount'] ?? 0);
            $allocationSettlementCurrency = (string) ($pendingAccessCharge['settlement_currency'] ?? $settlementCurrency);
            $dedupeKey = (string) $pendingAccessCharge['dedupe_key'];
            $assetOption = sr_content_asset_modules($pdo)[$assetModule];
            $transactionId = sr_content_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => -$allocatedAmount,
                'transaction_type' => (string) ($assetOption['use_type'] ?? 'use'),
                'reason' => '콘텐츠 열람',
                'reference_type' => 'content.view',
                'reference_id' => sr_content_asset_access_reference_id($pageId),
                'created_by_account_id' => null,
            ]);
            sr_content_update_asset_access_transaction($pdo, $dedupeKey, $transactionId);
            sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content', $pageId, 'view', 'asset', $assetModule, $chargePolicy, $assetModule . ':' . (string) $transactionId);
            $accessLog = sr_content_asset_access_log($pdo, $dedupeKey);
            $paymentDedupeParts[] = $dedupeKey;
            $paymentItems[] = [
                'item_kind' => 'asset_transaction',
                'owner_module' => $assetModule,
                'reference_type' => $assetModule . '_transaction',
                'reference_id' => (string) $transactionId,
                'amount' => -$allocatedAmount,
                'currency_code' => $allocationSettlementCurrency,
                'reversible' => true,
                'snapshot' => [
                    'settlement_amount' => $allocatedSettlementAmount,
                    'asset_access_dedupe_key' => $dedupeKey,
                ],
            ];
            if (is_array($accessLog) && (int) ($accessLog['id'] ?? 0) > 0) {
                $assetAccessLogIds[] = (int) $accessLog['id'];
                $paymentItems[] = [
                    'item_kind' => 'asset_access_log',
                    'owner_module' => 'content',
                    'reference_type' => 'content_asset_access_log',
                    'reference_id' => (string) ((int) ($accessLog['id'] ?? 0)),
                    'amount' => $allocatedSettlementAmount,
                    'currency_code' => $allocationSettlementCurrency,
                    'reversible' => true,
                    'snapshot' => [
                        'asset_module' => $assetModule,
                        'transaction_id' => $transactionId,
                        'dedupe_key' => $dedupeKey,
                    ],
                ];
            }
        }

        if ($pendingAccessCharges === []) {
            if ($startedTransaction || $mixedCouponTransactionOpen) {
                $pdo->commit();
            }
            return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['already_processed' => true, 'confirmation_fingerprint' => $confirmationFingerprint]);
        }

        $couponPaymentItem = sr_content_payment_coupon_item($mixedCouponResult, $settlementCurrency);
        if ($couponPaymentItem !== []) {
            array_unshift($paymentItems, $couponPaymentItem);
            $paymentDedupeParts[] = 'coupon:' . (string) ($mixedCouponResult['coupon_redemption_id'] ?? '');
        }
        $paymentItems[] = sr_content_payment_access_item('content', $pageId, 'view', 'asset', implode(',', $paymentDedupeParts));
        sr_content_record_payment_ledger_if_available($pdo, [
            'dedupe_key' => 'content.view:payment:' . sha1(implode('|', $paymentDedupeParts)),
            'account_id' => $accountId,
            'subject_module' => 'content',
            'subject_type' => 'content.view',
            'subject_id' => (string) $pageId,
            'payment_kind' => 'purchase',
            'payable_amount' => $paymentPayableAmount,
            'settlement_amount' => $amount,
            'settlement_currency' => $settlementCurrency,
            'description' => '콘텐츠 열람 결제',
            'snapshot' => [
                'charge_policy' => $chargePolicy,
                'coupon_discount_amount' => (int) ($mixedCouponResult['discount_amount'] ?? 0),
                'remaining_amount' => $amount,
                'asset_exchange_log_id' => $assetExchangeLogId ?? 0,
            ],
        ], $paymentItems);
        sr_content_record_view_payment_log($pdo, [
            'content_id' => $pageId,
            'content_title_snapshot' => (string) ($page['title'] ?? ''),
            'content_slug_snapshot' => (string) ($page['slug'] ?? ''),
            'account_id' => $accountId,
            'payment_type' => sr_content_view_payment_type($mixedCouponResult, true),
            'settlement_kind' => 'paid',
            'charge_policy' => $chargePolicy,
            'asset_module' => $assetModuleValue,
            'payable_amount' => $paymentPayableAmount,
            'settlement_amount' => $amount,
            'settlement_currency' => $settlementCurrency,
            'asset_access_log_ids' => $assetAccessLogIds,
            'coupon_redemption_id' => (int) ($mixedCouponResult['coupon_redemption_id'] ?? 0),
            'coupon_dedupe_key' => (string) ($mixedCouponResult['dedupe_key'] ?? ''),
            'payment_dedupe_key' => 'content.view:payment-unit:' . sha1(implode('|', $paymentDedupeParts)),
        ]);

        if ($startedTransaction || $mixedCouponTransactionOpen) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if (($startedTransaction || $mixedCouponTransactionOpen) && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif ($dedupeKey !== '') {
            sr_content_delete_asset_access_placeholder($pdo, $dedupeKey);
        }
        if (($startedTransaction || $mixedCouponTransactionOpen) && sr_content_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_asset_access_charge_failed');
        }

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '포인트/금액 차감에 실패해 콘텐츠를 열람할 수 없습니다.');
    }

    return sr_content_asset_access_result($pdo, true, true, $assetModuleValue, $amount, '', [
        'confirmation_fingerprint' => $confirmationFingerprint,
        'asset_exchange_log_id' => $assetExchangeLogId ?? 0,
        'coupon_used' => !empty($mixedCouponResult['processed']),
        'coupon_title' => (string) ($mixedCouponResult['coupon_title'] ?? ''),
        'coupon_discount_amount' => (int) ($mixedCouponResult['discount_amount'] ?? 0),
    ]);
}

function sr_content_try_coupon_access(PDO $pdo, int $pageId, int $accountId, string $chargePolicy = 'once', int $couponIssueId = 0, array $pricingContext = []): array
{
    if ($pageId <= 0 || $accountId <= 0 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return ['allowed' => false, 'processed' => false];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_redeem_for_target')) {
        return ['allowed' => false, 'processed' => false];
    }

    $dedupeKey = 'content.view:coupon:' . (string) $accountId . ':' . (string) $pageId;
    if ($chargePolicy !== 'once') {
        $dedupeKey .= ':' . bin2hex(random_bytes(8));
    }

    $context = [
        'dedupe_key' => $dedupeKey,
        'reference_module' => 'content',
        'reference_type' => 'content.view',
        'reference_id' => (string) $pageId,
    ];
    if ($couponIssueId > 0) {
        $context['coupon_issue_id'] = $couponIssueId;
    }
    $context = array_merge($context, $pricingContext);

    $result = sr_coupon_redeem_for_target($pdo, $accountId, 'content', (string) $pageId, $context);
    $result['dedupe_key'] = $dedupeKey;

    return $result;
}

function sr_content_try_coupon_download_access(PDO $pdo, int $fileId, int $accountId, string $chargePolicy = 'once', int $couponIssueId = 0, array $pricingContext = []): array
{
    if ($fileId <= 0 || $accountId <= 0 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return ['allowed' => false, 'processed' => false];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_redeem_for_target')) {
        return ['allowed' => false, 'processed' => false];
    }

    $dedupeKey = 'content.download:coupon:' . (string) $accountId . ':' . (string) $fileId;
    if ($chargePolicy !== 'once') {
        $dedupeKey .= ':' . bin2hex(random_bytes(8));
    }

    $context = [
        'dedupe_key' => $dedupeKey,
        'reference_module' => 'content',
        'reference_type' => 'content.download',
        'reference_id' => (string) $fileId,
    ];
    if ($couponIssueId > 0) {
        $context['coupon_issue_id'] = $couponIssueId;
    }
    $context = array_merge($context, $pricingContext);

    $result = sr_coupon_redeem_for_target($pdo, $accountId, 'content_file', (string) $fileId, $context);
    $result['dedupe_key'] = $dedupeKey;

    return $result;
}

function sr_content_file_download_required(array $file): bool
{
    return (int) ($file['asset_download_enabled'] ?? 0) === 1
        && (int) ($file['asset_download_amount'] ?? 0) > 0;
}

function sr_content_charge_file_download(PDO $pdo, array $file, int $accountId, bool $process = true, string $requestToken = '', int $couponIssueId = 0, bool $consumeConfirmationSession = true, bool $confirmedPost = false, bool $assetExchangeConfirmed = false): array
{
    return sr_content_asset_retry_operation($pdo, static function () use ($pdo, $file, $accountId, $process, $requestToken, $couponIssueId, $consumeConfirmationSession, $confirmedPost, $assetExchangeConfirmed): array {
        return sr_content_charge_file_download_once($pdo, $file, $accountId, $process, $requestToken, $couponIssueId, $consumeConfirmationSession, $confirmedPost, $assetExchangeConfirmed);
    });
}

function sr_content_charge_file_download_once(PDO $pdo, array $file, int $accountId, bool $process = true, string $requestToken = '', int $couponIssueId = 0, bool $consumeConfirmationSession = true, bool $confirmedPost = false, bool $assetExchangeConfirmed = false): array
{
    $pageId = (int) ($file['content_id'] ?? 0);
    $fileId = (int) ($file['id'] ?? 0);
    $assetModules = sr_content_asset_module_keys_from_value($file['asset_module'] ?? '');
    $assetModuleValue = sr_content_asset_module_value_from_keys($assetModules);
    $chargePolicy = (string) ($file['asset_charge_policy'] ?? 'once');
    $amounts = sr_content_asset_amounts_from_value($file['asset_download_amounts_json'] ?? '', $assetModules, (int) ($file['asset_download_amount'] ?? 0));
    $amount = $amounts !== [] ? sr_content_asset_amount_total($amounts) : (int) ($file['asset_download_amount'] ?? 0);

    if ($pageId <= 0 || $fileId <= 0 || $accountId <= 0 || !sr_content_file_download_required($file)) {
        return ['allowed' => true, 'charged' => false, 'message' => ''];
    }

    if ($assetModules === [] || !isset(sr_content_asset_download_charge_policies()[$chargePolicy])) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '콘텐츠 파일 다운로드 설정이 올바르지 않아 다운로드할 수 없습니다.');
    }

    if (!sr_content_asset_modules_available($pdo, $assetModules)) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '선택한 포인트/금액 항목을 모두 사용할 수 없어 파일을 다운로드할 수 없습니다.');
    }

    $policyAmounts = sr_content_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($file['asset_download_amount'] ?? 0), $file['asset_download_group_policies_json'] ?? '', (int) ($file['asset_download_policy_set_id'] ?? 0));
    $amounts = $policyAmounts['amounts'];
    $amount = (int) $policyAmounts['amount'];
    $policySnapshotJson = sr_content_asset_group_policy_snapshot_json($policyAmounts['snapshots']);
    $settlementCurrency = sr_content_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($file['asset_download_settlement_currency'] ?? '')]);
    $paymentPayableAmount = $amount;
    $confirmationFingerprint = sr_content_asset_confirmation_fingerprint('download', $chargePolicy, $assetModuleValue, $amount, $amounts, $policySnapshotJson, $settlementCurrency);
    if ($chargePolicy === 'once' && sr_content_once_access_already_granted($pdo, $assetModules, $accountId, $fileId, 'download')) {
        return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['already_paid' => true]);
    }

    if (sr_content_asset_policy_requires_confirmation($chargePolicy) && !$process) {
        if ($consumeConfirmationSession && sr_content_consume_asset_confirmation_session('download', $accountId, $fileId, $confirmationFingerprint)) {
            return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['confirmed_access' => true]);
        }

        $extra = [
            'error_key' => 'asset_confirmation_required',
            'confirmation_request_token' => sr_content_asset_confirmation_request_token('download', $accountId, $fileId, $confirmationFingerprint),
            'confirmation_fingerprint' => $confirmationFingerprint,
            'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content_file', $fileId),
        ];
        $extra = array_merge($extra, sr_content_asset_settlement_exchange_confirmation_extra($pdo, $assetModules, $accountId, $amount, $settlementCurrency));

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, (string) ($extra['message'] ?? sr_content_asset_confirmation_required_message()), $extra);
    }

    if ($couponIssueId <= 0 && sr_content_asset_policy_requires_confirmation($chargePolicy) && $process && !$confirmedPost) {
        $extra = [
            'error_key' => 'asset_confirmation_required',
            'confirmation_request_token' => sr_content_asset_confirmation_request_token('download', $accountId, $fileId, $confirmationFingerprint),
            'confirmation_fingerprint' => $confirmationFingerprint,
            'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content_file', $fileId),
        ];
        $extra = array_merge($extra, sr_content_asset_settlement_exchange_confirmation_extra($pdo, $assetModules, $accountId, $amount, $settlementCurrency));

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, (string) ($extra['message'] ?? sr_content_asset_confirmation_required_message()), $extra);
    }

    if ($amount <= 0) {
        $configErrorMessage = sr_content_asset_settlement_config_error_message($pdo, $assetModules, $accountId, 0, $settlementCurrency, '파일을 다운로드할 수 없습니다.');
        if ($configErrorMessage !== '') {
            return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, $configErrorMessage);
        }

        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.download', $assetModule, $accountId, $fileId, 'download', $requestToken, 0, $settlementCurrency);
        $accessLogIds = [];
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, 0, $chargePolicy, $dedupeKey, 'content.download', (string) $fileId, 'download', $policySnapshotJson);
            sr_content_complete_zero_asset_access_log($pdo, $dedupeKey);
            sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content_file', $fileId, 'download', 'asset_group_policy', $assetModule, $chargePolicy, $dedupeKey);
            $accessLog = sr_content_asset_access_log($pdo, $dedupeKey);
            if (is_array($accessLog)) {
                $accessLogIds[] = (int) ($accessLog['id'] ?? 0);
            }
            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($startedTransaction && sr_content_asset_is_retryable_transaction_exception($exception)) {
                throw $exception;
            }
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'content_file_group_access_failed');
            }

            return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '포인트/금액 접근권 처리에 실패했습니다.');
        }
        return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, 0, '', ['group_policy_applied' => true, 'access_log_ids' => array_values(array_filter($accessLogIds))]);
    }

    $mixedCouponResult = [];
    $mixedCouponTransactionOpen = false;
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $couponResult = $couponIssueId > 0 ? sr_content_try_coupon_download_access($pdo, $fileId, $accountId, $chargePolicy, $couponIssueId, [
            'price_amount' => $amount,
            'currency_code' => $settlementCurrency,
            'policy_summary' => '콘텐츠 다운로드 ' . number_format($amount) . $settlementCurrency,
        ]) : ['allowed' => false, 'processed' => false];
        if (!empty($couponResult['allowed'])) {
            $remainingAmount = max(0, (int) ($couponResult['remaining_amount'] ?? 0));
            if ($remainingAmount > 0) {
                $mixedCouponResult = $couponResult;
                $mixedCouponTransactionOpen = $startedTransaction && $pdo->inTransaction();
                $amount = $remainingAmount;
            } else {
                if (empty($couponResult['already_entitled'])) {
                    sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content_file', $fileId, 'download', 'coupon', '', $chargePolicy, (string) ($couponResult['dedupe_key'] ?? ''));
                }
                if (!empty($couponResult['processed'])) {
                    $paymentItems = array_values(array_filter([
                        sr_content_payment_coupon_item($couponResult, $settlementCurrency),
                        sr_content_payment_access_item('content_file', $fileId, 'download', 'coupon', (string) ($couponResult['dedupe_key'] ?? '')),
                    ]));
                    sr_content_record_payment_ledger_if_available($pdo, [
                        'dedupe_key' => 'content.download:payment:coupon:' . (string) ($couponResult['coupon_redemption_id'] ?? ''),
                        'account_id' => $accountId,
                        'subject_module' => 'content',
                        'subject_type' => 'content.download',
                        'subject_id' => (string) $fileId,
                        'payment_kind' => 'purchase',
                        'payable_amount' => $paymentPayableAmount,
                        'settlement_amount' => 0,
                        'settlement_currency' => $settlementCurrency,
                        'description' => '콘텐츠 다운로드 쿠폰 결제',
                        'snapshot' => [
                            'charge_policy' => $chargePolicy,
                            'content_id' => $pageId,
                            'coupon_covered_amount' => $paymentPayableAmount,
                        ],
                    ], $paymentItems);
                }
                if ($startedTransaction) {
                    $pdo->commit();
                }

                return [
                    'allowed' => true,
                    'charged' => false,
                    'coupon_used' => !empty($couponResult['processed']),
                    'already_paid' => !empty($couponResult['already_redeemed']) || !empty($couponResult['already_entitled']),
                    'coupon_title' => (string) ($couponResult['coupon_title'] ?? ''),
                    'asset_module' => $assetModuleValue,
                    'asset_label' => '쿠폰',
                    'amount' => 0,
                    'message' => '',
                    'confirmation_fingerprint' => $confirmationFingerprint,
                    'access_log_ids' => [],
                    'coupon_redemption_id' => (int) ($couponResult['coupon_redemption_id'] ?? 0),
                    'coupon_dedupe_key' => (string) ($couponResult['dedupe_key'] ?? ''),
                ];
            }
        }
        if ($mixedCouponResult === [] && $startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($mixedCouponResult === [] && $couponIssueId > 0) {
            return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '선택한 쿠폰을 사용할 수 없습니다.', [
                'error_key' => 'asset_confirmation_required',
                'confirmation_request_token' => sr_content_asset_confirmation_request_token('download', $accountId, $fileId, $confirmationFingerprint),
                'confirmation_fingerprint' => $confirmationFingerprint,
                'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content_file', $fileId),
            ]);
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($startedTransaction && sr_content_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_file_coupon_entitlement_failed');
        }

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '쿠폰 접근권 처리에 실패했습니다.');
    }

    $assetExchangeSuggestion = [];
    $allocations = sr_content_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
    if ($allocations === []) {
        $assetExchangeSuggestion = sr_content_asset_settlement_exchange_suggestion($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
        if ($assetExchangeSuggestion !== []) {
            if (!$assetExchangeConfirmed) {
                if ($mixedCouponTransactionOpen && $pdo->inTransaction()) {
                    $pdo->rollBack();
                    $mixedCouponTransactionOpen = false;
                }
                return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, sr_member_asset_settlement_exchange_message($pdo, sr_content_asset_modules($pdo), $assetExchangeSuggestion, sr_content_asset_confirmation_required_message()), [
                    'error_key' => 'asset_confirmation_required',
                    'confirmation_request_token' => sr_content_asset_confirmation_request_token('download', $accountId, $fileId, $confirmationFingerprint),
                    'confirmation_fingerprint' => $confirmationFingerprint,
                    'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content_file', $fileId),
                    'asset_exchange_suggestion' => $assetExchangeSuggestion,
                    'asset_exchange_confirmation_required' => true,
                ]);
            }
        } else {
            if ($mixedCouponTransactionOpen && $pdo->inTransaction()) {
                $pdo->rollBack();
                $mixedCouponTransactionOpen = false;
            }
            return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, sr_content_asset_balance_shortage_message($pdo, $assetModules, $accountId, $amount, $settlementCurrency, '파일을 다운로드할 수 없습니다.', '선택한 항목의 잔액이 부족해 파일을 다운로드할 수 없습니다.'));
        }
    }

    $dedupeKey = '';
    $accessLogIds = [];
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $assetExchangeLogId = 0;
        if ($assetExchangeSuggestion !== [] && $assetExchangeConfirmed) {
            $assetExchangeLogId = sr_member_asset_settlement_execute_exchange_suggestion($pdo, $assetExchangeSuggestion, $accountId);
            $allocations = sr_content_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
            if ($allocations === []) {
                throw new RuntimeException('Automatic asset exchange did not create a payable file settlement plan.');
            }
        }

        $pendingDownloadCharges = [];
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) ($allocation['asset_amount'] ?? $allocation['amount']);
            $allocatedSettlementAmount = (int) ($allocation['settlement_amount'] ?? 0);
            $allocationSettlementCurrency = (string) ($allocation['settlement_currency'] ?? $settlementCurrency);
            $purchasePowerSnapshotJson = sr_content_asset_purchase_power_snapshot_json(is_array($allocation['purchase_power_snapshot'] ?? null) ? $allocation['purchase_power_snapshot'] : []);
            $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.download', $assetModule, $accountId, $fileId, 'download', $requestToken, $amount, $settlementCurrency);
            $inserted = sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, $allocatedAmount, $chargePolicy, $dedupeKey, 'content.download', (string) $fileId, 'download', sr_content_asset_group_policy_snapshot_json(isset($policyAmounts['snapshots'][$assetModule]) ? [$policyAmounts['snapshots'][$assetModule]] : []), $allocatedSettlementAmount, $allocationSettlementCurrency, $purchasePowerSnapshotJson);
            if (!$inserted) {
                if ($chargePolicy === 'once') {
                    throw new RuntimeException('Incomplete or duplicate content file asset access.');
                }
                $existingLog = sr_content_asset_access_log($pdo, $dedupeKey);
                if (is_array($existingLog) && (string) ($existingLog['log_status'] ?? '') === sr_content_asset_log_status_completed()) {
                    $accessLogIds[] = (int) ($existingLog['id'] ?? 0);
                    $transactionId = (int) ($existingLog['transaction_id'] ?? 0);
                    if ($transactionId > 0) {
                        sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content_file', $fileId, 'download', 'asset', $assetModule, $chargePolicy, $assetModule . ':' . (string) $transactionId);
                    }
                    continue;
                }
                throw new RuntimeException('Content file asset access is still processing.');
            }

            $pendingDownloadCharges[] = [
                'asset_module' => $assetModule,
                'amount' => $allocatedAmount,
                'settlement_amount' => $allocatedSettlementAmount,
                'settlement_currency' => $allocationSettlementCurrency,
                'dedupe_key' => $dedupeKey,
            ];
        }

        $paymentItems = [];
        $paymentDedupeParts = [];
        foreach ($pendingDownloadCharges as $pendingDownloadCharge) {
            $assetModule = (string) $pendingDownloadCharge['asset_module'];
            $allocatedAmount = (int) $pendingDownloadCharge['amount'];
            $allocatedSettlementAmount = (int) ($pendingDownloadCharge['settlement_amount'] ?? 0);
            $allocationSettlementCurrency = (string) ($pendingDownloadCharge['settlement_currency'] ?? $settlementCurrency);
            $dedupeKey = (string) $pendingDownloadCharge['dedupe_key'];
            $assetOption = sr_content_asset_modules($pdo)[$assetModule];
            $transactionId = sr_content_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => -$allocatedAmount,
                'transaction_type' => (string) ($assetOption['use_type'] ?? 'use'),
                'reason' => '콘텐츠 파일 다운로드',
                'reference_type' => 'content.download',
                'reference_id' => (string) $fileId,
                'created_by_account_id' => null,
            ]);
            sr_content_update_asset_access_transaction($pdo, $dedupeKey, $transactionId);
            sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content_file', $fileId, 'download', 'asset', $assetModule, $chargePolicy, $assetModule . ':' . (string) $transactionId);
            $accessLog = sr_content_asset_access_log($pdo, $dedupeKey);
            if (is_array($accessLog)) {
                $accessLogIds[] = (int) ($accessLog['id'] ?? 0);
            }
            $paymentDedupeParts[] = $dedupeKey;
            $paymentItems[] = [
                'item_kind' => 'asset_transaction',
                'owner_module' => $assetModule,
                'reference_type' => $assetModule . '_transaction',
                'reference_id' => (string) $transactionId,
                'amount' => -$allocatedAmount,
                'currency_code' => $allocationSettlementCurrency,
                'reversible' => true,
                'snapshot' => [
                    'settlement_amount' => $allocatedSettlementAmount,
                    'asset_access_dedupe_key' => $dedupeKey,
                ],
            ];
            if (is_array($accessLog) && (int) ($accessLog['id'] ?? 0) > 0) {
                $paymentItems[] = [
                    'item_kind' => 'asset_access_log',
                    'owner_module' => 'content',
                    'reference_type' => 'content_asset_access_log',
                    'reference_id' => (string) ((int) ($accessLog['id'] ?? 0)),
                    'amount' => $allocatedSettlementAmount,
                    'currency_code' => $allocationSettlementCurrency,
                    'reversible' => true,
                    'snapshot' => [
                        'asset_module' => $assetModule,
                        'transaction_id' => $transactionId,
                        'dedupe_key' => $dedupeKey,
                    ],
                ];
            }
        }

        if ($pendingDownloadCharges === []) {
            if ($startedTransaction || $mixedCouponTransactionOpen) {
                $pdo->commit();
            }
            return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['already_processed' => true, 'confirmation_fingerprint' => $confirmationFingerprint, 'access_log_ids' => array_values(array_filter($accessLogIds))]);
        }

        $couponPaymentItem = sr_content_payment_coupon_item($mixedCouponResult, $settlementCurrency);
        if ($couponPaymentItem !== []) {
            array_unshift($paymentItems, $couponPaymentItem);
            $paymentDedupeParts[] = 'coupon:' . (string) ($mixedCouponResult['coupon_redemption_id'] ?? '');
        }
        $paymentItems[] = sr_content_payment_access_item('content_file', $fileId, 'download', 'asset', implode(',', $paymentDedupeParts));
        sr_content_record_payment_ledger_if_available($pdo, [
            'dedupe_key' => 'content.download:payment:' . sha1(implode('|', $paymentDedupeParts)),
            'account_id' => $accountId,
            'subject_module' => 'content',
            'subject_type' => 'content.download',
            'subject_id' => (string) $fileId,
            'payment_kind' => 'purchase',
            'payable_amount' => $paymentPayableAmount,
            'settlement_amount' => $amount,
            'settlement_currency' => $settlementCurrency,
            'description' => '콘텐츠 다운로드 결제',
            'snapshot' => [
                'charge_policy' => $chargePolicy,
                'content_id' => $pageId,
                'coupon_discount_amount' => (int) ($mixedCouponResult['discount_amount'] ?? 0),
                'remaining_amount' => $amount,
                'asset_exchange_log_id' => $assetExchangeLogId ?? 0,
            ],
        ], $paymentItems);

        if ($startedTransaction || $mixedCouponTransactionOpen) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if (($startedTransaction || $mixedCouponTransactionOpen) && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif ($dedupeKey !== '') {
            sr_content_delete_asset_access_placeholder($pdo, $dedupeKey);
        }
        if (($startedTransaction || $mixedCouponTransactionOpen) && sr_content_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_file_download_charge_failed');
        }

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '포인트/금액 차감에 실패해 파일을 다운로드할 수 없습니다.');
    }

    return sr_content_asset_access_result($pdo, true, true, $assetModuleValue, $amount, '', [
        'confirmation_fingerprint' => $confirmationFingerprint,
        'access_log_ids' => array_values(array_filter($accessLogIds)),
        'asset_exchange_log_id' => $assetExchangeLogId ?? 0,
        'coupon_used' => !empty($mixedCouponResult['processed']),
        'coupon_title' => (string) ($mixedCouponResult['coupon_title'] ?? ''),
        'coupon_discount_amount' => (int) ($mixedCouponResult['discount_amount'] ?? 0),
        'coupon_redemption_id' => (int) ($mixedCouponResult['coupon_redemption_id'] ?? 0),
        'coupon_dedupe_key' => (string) ($mixedCouponResult['dedupe_key'] ?? ''),
    ]);
}

function sr_content_admin_payment_history_sort_options(): array
{
    return [
        'created_at' => ['label' => '결제 시각', 'columns' => ['p.created_at', 'p.source_id']],
        'target' => ['label' => '대상', 'columns' => ['p.target_title', 'p.source_id']],
        'account_id' => ['label' => '회원', 'columns' => ['p.account_id', 'p.source_id']],
        'payment_type' => ['label' => '결제 유형', 'columns' => ['p.payment_type', 'p.source_id']],
        'settlement_kind' => ['label' => '정산', 'columns' => ['p.settlement_kind', 'p.source_id']],
        'settlement_amount' => ['label' => '금액', 'columns' => ['p.settlement_amount', 'p.source_id']],
    ];
}

function sr_content_admin_payment_history_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_content_admin_payment_history_filters_from_request(PDO $pdo): array
{
    $legacySettlementKind = sr_content_asset_settlement_kind_for_use(1, 0, '');
    $filters = [
        'kind' => sr_content_admin_single_filter_values('kind', ['content_view', 'content_file_download']),
        'payment_type' => sr_content_admin_single_filter_values('payment_type', ['asset_only', 'coupon_access', 'coupon_partial_discount_asset']),
        'settlement_kind' => sr_content_admin_single_filter_values('settlement_kind', ['paid', 'paid_settled_zero', $legacySettlementKind]),
        'refund_status' => sr_content_admin_multi_filter_values('refund_status', ['none', 'refunded', 'access_revoked']),
        'coupon_used' => sr_content_admin_single_filter_values('coupon_used', ['yes', 'no']),
        'target_id' => (int) sr_get_string('target_id', 20),
        'account_id' => sr_admin_member_account_id_from_identifier($pdo, sr_runtime_config(), sr_get_string('account_id', 80)),
        'date_from' => sr_get_string('date_from', 10),
        'date_to' => sr_get_string('date_to', 10),
        'q' => sr_get_string('q', 120),
    ];
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) $filters['date_from']) !== 1) {
        $filters['date_from'] = '';
    }
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) $filters['date_to']) !== 1) {
        $filters['date_to'] = '';
    }

    return $filters;
}

function sr_content_view_payment_logs_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_content_view_payment_logs LIMIT 1');
        $exists = true;
    } catch (Throwable) {
        $exists = false;
    }

    return $exists;
}

function sr_content_admin_payment_history_sources(PDO $pdo): array
{
    $sources = [];
    if (sr_content_view_payment_logs_table_exists($pdo)) {
        $sources[] = "SELECT 'content_view' AS source_kind,
                             v.id AS source_id,
                             v.content_id AS content_id,
                             0 AS file_id,
                             v.content_id AS target_id,
                             v.content_title_snapshot AS target_title,
                             v.content_slug_snapshot AS target_meta,
                             v.account_id AS account_id,
                             v.payment_type AS payment_type,
                             v.settlement_kind AS settlement_kind,
                             v.charge_policy AS charge_policy,
                             v.asset_module AS asset_module,
                             v.payable_amount AS payable_amount,
                             v.settlement_amount AS settlement_amount,
                             v.settlement_currency AS settlement_currency,
                             v.asset_access_log_ids_json AS asset_access_log_ids_json,
                             v.coupon_redemption_id AS coupon_redemption_id,
                             v.coupon_dedupe_key AS coupon_dedupe_key,
                             v.payment_dedupe_key AS payment_dedupe_key,
                             v.refund_status AS refund_status,
                             v.refund_transaction_ids_json AS refund_transaction_ids_json,
                             v.refund_note AS refund_note,
                             v.refunded_by_account_id AS refunded_by_account_id,
                             v.refunded_at AS refunded_at,
                             v.access_revoked_at AS access_revoked_at,
                             v.refund_policy_version AS refund_policy_version,
                             v.created_at AS created_at
                      FROM sr_content_view_payment_logs v";
    }
    if (sr_content_file_download_logs_table_exists($pdo)) {
        $sources[] = "SELECT 'content_file_download' AS source_kind,
                             d.id AS source_id,
                             d.content_id AS content_id,
                             d.file_id AS file_id,
                             d.file_id AS target_id,
                             COALESCE(NULLIF(d.file_title_snapshot, ''), NULLIF(f.title, ''), d.file_original_name_snapshot) AS target_title,
                             COALESCE(NULLIF(d.content_title_snapshot, ''), NULLIF(p.title, ''), d.file_original_name_snapshot) AS target_meta,
                             d.account_id AS account_id,
                             CASE
                                 WHEN COALESCE(d.coupon_redemption_id, 0) > 0 AND COALESCE(d.amount, 0) <= 0 THEN 'coupon_access'
                                 WHEN COALESCE(d.coupon_redemption_id, 0) > 0 THEN 'coupon_partial_discount_asset'
                                 ELSE 'asset_only'
                             END AS payment_type,
                             CASE
                                 WHEN COALESCE(d.amount, 0) > 0 THEN 'paid'
                                 WHEN COALESCE(d.coupon_redemption_id, 0) > 0 THEN 'paid_settled_zero'
                                 ELSE ''
                             END AS settlement_kind,
                             d.charge_policy AS charge_policy,
                             d.asset_module AS asset_module,
                             d.amount AS payable_amount,
                             d.amount AS settlement_amount,
                             'KRW' AS settlement_currency,
                             d.asset_access_log_ids_json AS asset_access_log_ids_json,
                             d.coupon_redemption_id AS coupon_redemption_id,
                             d.coupon_dedupe_key AS coupon_dedupe_key,
                             '' AS payment_dedupe_key,
                             d.refund_status AS refund_status,
                             d.refund_transaction_ids_json AS refund_transaction_ids_json,
                             d.refund_note AS refund_note,
                             d.refunded_by_account_id AS refunded_by_account_id,
                             d.refunded_at AS refunded_at,
                             d.access_revoked_at AS access_revoked_at,
                             d.refund_policy_version AS refund_policy_version,
                             d.created_at AS created_at
                      FROM sr_content_file_download_logs d
                      LEFT JOIN sr_content_items p ON p.id = d.content_id
                      LEFT JOIN sr_content_files f ON f.id = d.file_id
                      WHERE d.download_type = 'paid'";
    }

    return $sources;
}

function sr_content_admin_payment_history_where_sql(array $filters): array
{
    $conditions = [];
    $params = [];

    foreach (['kind' => 'source_kind', 'payment_type' => 'payment_type', 'settlement_kind' => 'settlement_kind'] as $filterKey => $column) {
        $values = is_array($filters[$filterKey] ?? null) ? $filters[$filterKey] : [];
        if ($values === []) {
            continue;
        }
        $paramKey = $filterKey . '_0';
        $conditions[] = 'p.' . $column . ' = :' . $paramKey;
        $params[$paramKey] = (string) reset($values);
    }

    $refundStatuses = is_array($filters['refund_status'] ?? null) ? $filters['refund_status'] : [];
    if ($refundStatuses !== []) {
        $refundConditions = [];
        foreach (array_values($refundStatuses) as $index => $refundStatus) {
            if ((string) $refundStatus === 'none') {
                $refundConditions[] = "p.refund_status = ''";
                continue;
            }
            $paramKey = 'refund_status_' . (string) $index;
            $refundConditions[] = 'p.refund_status = :' . $paramKey;
            $params[$paramKey] = (string) $refundStatus;
        }
        if ($refundConditions !== []) {
            $conditions[] = '(' . implode(' OR ', $refundConditions) . ')';
        }
    }

    $couponUsed = is_array($filters['coupon_used'] ?? null) ? (string) reset($filters['coupon_used']) : '';
    if ($couponUsed === 'yes') {
        $conditions[] = 'COALESCE(p.coupon_redemption_id, 0) > 0';
    } elseif ($couponUsed === 'no') {
        $conditions[] = 'COALESCE(p.coupon_redemption_id, 0) = 0';
    }

    $targetId = (int) ($filters['target_id'] ?? 0);
    if ($targetId > 0) {
        $conditions[] = '(p.content_id = :target_id OR p.file_id = :target_id OR p.target_id = :target_id)';
        $params['target_id'] = $targetId;
    }

    $accountId = (int) ($filters['account_id'] ?? 0);
    if ($accountId > 0) {
        $conditions[] = 'p.account_id = :account_id';
        $params['account_id'] = $accountId;
    }

    $dateFrom = (string) ($filters['date_from'] ?? '');
    if ($dateFrom !== '') {
        $conditions[] = 'p.created_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }
    $dateTo = (string) ($filters['date_to'] ?? '');
    if ($dateTo !== '') {
        $conditions[] = 'p.created_at <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $conditions[] = "(p.target_title LIKE :q ESCAPE '\\\\' OR p.target_meta LIKE :q ESCAPE '\\\\' OR p.coupon_dedupe_key LIKE :q ESCAPE '\\\\' OR p.payment_dedupe_key LIKE :q ESCAPE '\\\\')";
        $params['q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    }

    return [
        'sql' => $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '',
        'params' => $params,
    ];
}

function sr_content_admin_payment_history_count(PDO $pdo, array $filters): int
{
    $sources = sr_content_admin_payment_history_sources($pdo);
    if ($sources === []) {
        return 0;
    }
    $where = sr_content_admin_payment_history_where_sql($filters);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM (' . implode(' UNION ALL ', $sources) . ') p ' . $where['sql']);
    $stmt->execute($where['params']);

    return (int) $stmt->fetchColumn();
}

function sr_content_admin_payment_history_logs(PDO $pdo, array $filters, int $limit, int $offset, array $sort = []): array
{
    $sources = sr_content_admin_payment_history_sources($pdo);
    if ($sources === []) {
        return [];
    }
    $where = sr_content_admin_payment_history_where_sql($filters);
    $stmt = $pdo->prepare(
        'SELECT p.*, a.email, a.display_name, rb.display_name AS refunded_by_display_name
         FROM (' . implode(' UNION ALL ', $sources) . ') p
         LEFT JOIN sr_member_accounts a ON a.id = p.account_id
         LEFT JOIN sr_member_accounts rb ON rb.id = p.refunded_by_account_id
         ' . $where['sql'] . '
         ' . sr_admin_sort_order_sql(sr_content_admin_payment_history_sort_options(), $sort, sr_content_admin_payment_history_default_sort()) . '
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($where['params'] as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return sr_content_admin_payment_history_logs_with_access_summaries($pdo, $stmt->fetchAll());
}

function sr_content_admin_payment_history_logs_with_access_summaries(PDO $pdo, array $paymentLogs): array
{
    if ($paymentLogs === [] || !sr_content_asset_access_logs_table_exists($pdo)) {
        return $paymentLogs;
    }

    $idsByIndex = [];
    $allIds = [];
    foreach ($paymentLogs as $index => $paymentLog) {
        $decoded = json_decode((string) ($paymentLog['asset_access_log_ids_json'] ?? '[]'), true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $idsByIndex[(int) $index][$id] = $id;
                $allIds[$id] = $id;
            }
        }
    }
    if ($allIds === []) {
        return $paymentLogs;
    }

    $placeholders = [];
    $params = [];
    foreach (array_values($allIds) as $index => $id) {
        $key = 'id_' . (string) $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $stmt = $pdo->prepare(
        'SELECT id, content_id, account_id, asset_module, transaction_id, reference_type, reference_id, access_kind, amount,
                settlement_amount, settlement_currency, settlement_kind, snapshot_schema_version, rounding_policy_version
         FROM sr_content_asset_access_logs
         WHERE id IN (' . implode(', ', $placeholders) . ')
         ORDER BY id ASC'
    );
    $stmt->execute($params);

    $accessLogsById = [];
    foreach ($stmt->fetchAll() as $accessLog) {
        $accessLogsById[(int) ($accessLog['id'] ?? 0)] = $accessLog;
    }

    foreach ($idsByIndex as $index => $ids) {
        $summaryLines = [];
        $paymentLog = $paymentLogs[$index];
        foreach ($ids as $id) {
            $accessLog = $accessLogsById[$id] ?? null;
            if (!is_array($accessLog)) {
                continue;
            }
            $sourceKind = (string) ($paymentLog['source_kind'] ?? '');
            $referenceType = $sourceKind === 'content_file_download' ? 'content.download' : 'content.view';
            $referenceId = $sourceKind === 'content_file_download' ? (string) (int) ($paymentLog['file_id'] ?? 0) : (string) (int) ($paymentLog['content_id'] ?? 0);
            $accessKind = $sourceKind === 'content_file_download' ? 'download' : 'view';
            if ((int) ($accessLog['content_id'] ?? 0) !== (int) ($paymentLog['content_id'] ?? 0)
                || (int) ($accessLog['account_id'] ?? 0) !== (int) ($paymentLog['account_id'] ?? 0)
                || (string) ($accessLog['reference_type'] ?? '') !== $referenceType
                || (string) ($accessLog['reference_id'] ?? '') !== $referenceId
                || (string) ($accessLog['access_kind'] ?? '') !== $accessKind
            ) {
                continue;
            }
            $assetModule = (string) ($accessLog['asset_module'] ?? '');
            $summaryLines[] = trim(implode(' · ', array_filter([
                sr_content_asset_module_labels($assetModule, $pdo) . ' ' . number_format((int) ($accessLog['amount'] ?? 0)),
                '기준 ' . number_format((int) ($accessLog['settlement_amount'] ?? 0)) . ' ' . (string) ($accessLog['settlement_currency'] ?? 'KRW'),
                (string) ($accessLog['settlement_kind'] ?? sr_content_asset_settlement_kind_for_use(1, 0, '')),
                'snapshot ' . (string) ($accessLog['snapshot_schema_version'] ?? 'asset_settlement_snapshot_v1'),
                'rounding ' . (string) ($accessLog['rounding_policy_version'] ?? 'asset_settlement_rounding_v1'),
            ], static fn (string $part): bool => $part !== '')));
        }
        $paymentLogs[$index]['asset_log_summary'] = implode("\n", $summaryLines);
    }

    return $paymentLogs;
}
