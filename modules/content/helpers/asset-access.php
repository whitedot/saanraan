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

function sr_content_asset_log_settlement_metadata_columns_exist(PDO $pdo, string $tableName): bool
{
    static $existsByTable = [];
    $tableName = trim($tableName);
    if ($tableName === '') {
        return false;
    }
    if (array_key_exists($tableName, $existsByTable)) {
        return $existsByTable[$tableName];
    }

    $prefix = $pdo instanceof SrPrefixedPDO ? $pdo->srTablePrefix() : 'sr_';
    $expectedTables = [
        $prefix . 'content_asset_access_logs' => true,
        $prefix . 'content_asset_action_logs' => true,
    ];
    if (!isset($expectedTables[$tableName])) {
        $existsByTable[$tableName] = false;
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS column_count
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME IN (\'settlement_kind\', \'snapshot_schema_version\', \'rounding_policy_version\')'
        );
        $stmt->execute(['table_name' => $tableName]);
        $row = $stmt->fetch();
        $existsByTable[$tableName] = is_array($row) && (int) ($row['column_count'] ?? 0) === 3;
    } catch (Throwable $exception) {
        $existsByTable[$tableName] = false;
    }

    return $existsByTable[$tableName];
}

function sr_content_asset_access_log_settlement_metadata_columns_exist(PDO $pdo): bool
{
    $prefix = $pdo instanceof SrPrefixedPDO ? $pdo->srTablePrefix() : 'sr_';

    return sr_content_asset_log_settlement_metadata_columns_exist($pdo, $prefix . 'content_asset_access_logs');
}

function sr_content_asset_action_log_settlement_metadata_columns_exist(PDO $pdo): bool
{
    $prefix = $pdo instanceof SrPrefixedPDO ? $pdo->srTablePrefix() : 'sr_';

    return sr_content_asset_log_settlement_metadata_columns_exist($pdo, $prefix . 'content_asset_action_logs');
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

function sr_content_allocate_asset_use(PDO $pdo, array $assetModules, int $accountId, int $amount): array
{
    $remaining = max(0, $amount);
    $allocations = [];
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        if ($remaining <= 0) {
            break;
        }

        $balance = sr_content_asset_balance($pdo, $assetModule, $accountId);
        if ($balance <= 0) {
            continue;
        }

        $useAmount = min($balance, $remaining);
        if ($useAmount > 0) {
            $allocations[] = [
                'asset_module' => $assetModule,
                'amount' => $useAmount,
            ];
            $remaining -= $useAmount;
        }
    }

    return $remaining === 0 ? $allocations : [];
}

function sr_content_insert_asset_access_placeholder(PDO $pdo, int $pageId, int $accountId, string $assetModule, int $amount, string $chargePolicy, string $dedupeKey, string $referenceType = 'content.view', ?string $referenceId = null, string $accessKind = 'view', string $groupPolicySnapshotJson = '', int $settlementAmount = 0, string $settlementCurrency = 'KRW', string $purchasePowerSnapshotJson = ''): bool
{
    $settlementAmount = max(0, $settlementAmount);
    $settlementKind = sr_content_asset_settlement_kind_for_use($amount, $settlementAmount, $purchasePowerSnapshotJson);
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_content_asset_access_logs
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

function sr_content_asset_access_dedupe_key_for_policy(string $chargePolicy, string $referenceType, string $assetModule, int $accountId, int $subjectId, string $accessKind = 'view', string $requestToken = ''): string
{
    if ($chargePolicy === 'once') {
        return sr_content_asset_access_dedupe_key($assetModule, $accountId, $subjectId, $accessKind);
    }

    $requestToken = preg_match('/\A[a-f0-9]{32}(?:[a-f0-9]{32})?\z/', $requestToken) === 1 ? $requestToken : bin2hex(random_bytes(16));
    return $referenceType . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':' . $requestToken;
}

function sr_content_charge_view_access(PDO $pdo, array $page, int $accountId, bool $process = true, string $requestToken = '', int $couponIssueId = 0, bool $consumeConfirmationSession = true, bool $confirmedPost = false): array
{
    return sr_content_asset_retry_operation($pdo, static function () use ($pdo, $page, $accountId, $process, $requestToken, $couponIssueId, $consumeConfirmationSession, $confirmedPost): array {
        return sr_content_charge_view_access_once($pdo, $page, $accountId, $process, $requestToken, $couponIssueId, $consumeConfirmationSession, $confirmedPost);
    });
}

function sr_content_charge_view_access_once(PDO $pdo, array $page, int $accountId, bool $process = true, string $requestToken = '', int $couponIssueId = 0, bool $consumeConfirmationSession = true, bool $confirmedPost = false): array
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
    $confirmationFingerprint = sr_content_asset_confirmation_fingerprint('view', $chargePolicy, $assetModuleValue, $amount, $amounts, $policySnapshotJson);
    if ($chargePolicy === 'once' && sr_content_once_access_already_granted($pdo, $assetModules, $accountId, $pageId)) {
        return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['already_paid' => true]);
    }

    if (sr_content_asset_policy_requires_confirmation($chargePolicy) && !$process) {
        if ($consumeConfirmationSession && sr_content_consume_asset_confirmation_session('view', $accountId, $pageId, $confirmationFingerprint)) {
            return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['confirmed_access' => true]);
        }

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, sr_content_asset_confirmation_required_message(), [
            'error_key' => 'asset_confirmation_required',
            'confirmation_request_token' => sr_content_asset_confirmation_request_token('view', $accountId, $pageId, $confirmationFingerprint),
            'confirmation_fingerprint' => $confirmationFingerprint,
            'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content', $pageId),
        ]);
    }

    if ($couponIssueId <= 0 && sr_content_asset_policy_requires_confirmation($chargePolicy) && $process && (!$confirmedPost || !sr_content_asset_confirmation_request_token_valid('view', $accountId, $pageId, $confirmationFingerprint, $requestToken))) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, sr_content_asset_confirmation_required_message(), [
            'error_key' => 'asset_confirmation_required',
            'confirmation_request_token' => sr_content_asset_confirmation_request_token('view', $accountId, $pageId, $confirmationFingerprint),
            'confirmation_fingerprint' => $confirmationFingerprint,
            'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content', $pageId),
        ]);
    }

    if ($amount <= 0) {
        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.view', $assetModule, $accountId, $pageId, 'view', $requestToken);
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, 0, $chargePolicy, $dedupeKey, 'content.view', null, 'view', $policySnapshotJson);
            sr_content_complete_zero_asset_access_log($pdo, $dedupeKey);
            sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content', $pageId, 'view', 'asset_group_policy', $assetModule, $chargePolicy, $dedupeKey);
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

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }
        try {
            $couponResult = $couponIssueId > 0 ? sr_content_try_coupon_access($pdo, $pageId, $accountId, $chargePolicy, $couponIssueId) : ['allowed' => false, 'processed' => false];
            if (!empty($couponResult['allowed'])) {
                if (empty($couponResult['already_entitled'])) {
                    sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content', $pageId, 'view', 'coupon', '', $chargePolicy, (string) ($couponResult['dedupe_key'] ?? ''));
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
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($couponIssueId > 0) {
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

    $allocations = sr_content_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
    if ($allocations === []) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, sr_content_asset_balance_shortage_message($pdo, $assetModules, $accountId, $amount, $settlementCurrency, '콘텐츠를 열람할 수 없습니다.', '선택한 항목의 잔액이 부족해 콘텐츠를 열람할 수 없습니다.'));
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
            $allocatedSettlementAmount = (int) ($allocation['settlement_amount'] ?? 0);
            $allocationSettlementCurrency = (string) ($allocation['settlement_currency'] ?? $settlementCurrency);
            $purchasePowerSnapshotJson = sr_content_asset_purchase_power_snapshot_json(is_array($allocation['purchase_power_snapshot'] ?? null) ? $allocation['purchase_power_snapshot'] : []);
            $assetOption = sr_content_asset_modules($pdo)[$assetModule];
            $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.view', $assetModule, $accountId, $pageId, 'view', $requestToken);
            $inserted = sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, $allocatedAmount, $chargePolicy, $dedupeKey, 'content.view', null, 'view', sr_content_asset_group_policy_snapshot_json(isset($policyAmounts['snapshots'][$assetModule]) ? [$policyAmounts['snapshots'][$assetModule]] : []), $allocatedSettlementAmount, $allocationSettlementCurrency, $purchasePowerSnapshotJson);
            if (!$inserted) {
                if ($chargePolicy === 'once') {
                    throw new RuntimeException('Incomplete or duplicate content asset access.');
                }
                continue;
            }

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
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif ($dedupeKey !== '') {
            sr_content_delete_asset_access_placeholder($pdo, $dedupeKey);
        }
        if ($startedTransaction && sr_content_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_asset_access_charge_failed');
        }

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '포인트/금액 차감에 실패해 콘텐츠를 열람할 수 없습니다.');
    }

    return sr_content_asset_access_result($pdo, true, true, $assetModuleValue, $amount, '', ['confirmation_fingerprint' => $confirmationFingerprint]);
}

function sr_content_try_coupon_access(PDO $pdo, int $pageId, int $accountId, string $chargePolicy = 'once', int $couponIssueId = 0): array
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

    $result = sr_coupon_redeem_for_target($pdo, $accountId, 'content', (string) $pageId, $context);
    $result['dedupe_key'] = $dedupeKey;

    return $result;
}

function sr_content_try_coupon_download_access(PDO $pdo, int $fileId, int $accountId, string $chargePolicy = 'once', int $couponIssueId = 0): array
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

    $result = sr_coupon_redeem_for_target($pdo, $accountId, 'content_file', (string) $fileId, $context);
    $result['dedupe_key'] = $dedupeKey;

    return $result;
}

function sr_content_file_download_required(array $file): bool
{
    return (int) ($file['asset_download_enabled'] ?? 0) === 1
        && (int) ($file['asset_download_amount'] ?? 0) > 0;
}

function sr_content_charge_file_download(PDO $pdo, array $file, int $accountId, bool $process = true, string $requestToken = '', int $couponIssueId = 0, bool $consumeConfirmationSession = true, bool $confirmedPost = false): array
{
    return sr_content_asset_retry_operation($pdo, static function () use ($pdo, $file, $accountId, $process, $requestToken, $couponIssueId, $consumeConfirmationSession, $confirmedPost): array {
        return sr_content_charge_file_download_once($pdo, $file, $accountId, $process, $requestToken, $couponIssueId, $consumeConfirmationSession, $confirmedPost);
    });
}

function sr_content_charge_file_download_once(PDO $pdo, array $file, int $accountId, bool $process = true, string $requestToken = '', int $couponIssueId = 0, bool $consumeConfirmationSession = true, bool $confirmedPost = false): array
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
    $confirmationFingerprint = sr_content_asset_confirmation_fingerprint('download', $chargePolicy, $assetModuleValue, $amount, $amounts, $policySnapshotJson);
    if ($chargePolicy === 'once' && sr_content_once_access_already_granted($pdo, $assetModules, $accountId, $fileId, 'download')) {
        return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['already_paid' => true]);
    }

    if (sr_content_asset_policy_requires_confirmation($chargePolicy) && !$process) {
        if ($consumeConfirmationSession && sr_content_consume_asset_confirmation_session('download', $accountId, $fileId, $confirmationFingerprint)) {
            return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['confirmed_access' => true]);
        }

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, sr_content_asset_confirmation_required_message(), [
            'error_key' => 'asset_confirmation_required',
            'confirmation_request_token' => sr_content_asset_confirmation_request_token('download', $accountId, $fileId, $confirmationFingerprint),
            'confirmation_fingerprint' => $confirmationFingerprint,
            'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content_file', $fileId),
        ]);
    }

    if ($couponIssueId <= 0 && sr_content_asset_policy_requires_confirmation($chargePolicy) && $process && (!$confirmedPost || !sr_content_asset_confirmation_request_token_valid('download', $accountId, $fileId, $confirmationFingerprint, $requestToken))) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, sr_content_asset_confirmation_required_message(), [
            'error_key' => 'asset_confirmation_required',
            'confirmation_request_token' => sr_content_asset_confirmation_request_token('download', $accountId, $fileId, $confirmationFingerprint),
            'confirmation_fingerprint' => $confirmationFingerprint,
            'coupon_issues' => sr_content_available_coupon_issues($pdo, $accountId, 'content_file', $fileId),
        ]);
    }

    if ($amount <= 0) {
        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.download', $assetModule, $accountId, $fileId, 'download', $requestToken);
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

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }
        try {
            $couponResult = $couponIssueId > 0 ? sr_content_try_coupon_download_access($pdo, $fileId, $accountId, $chargePolicy, $couponIssueId) : ['allowed' => false, 'processed' => false];
            if (!empty($couponResult['allowed'])) {
                if (empty($couponResult['already_entitled'])) {
                    sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content_file', $fileId, 'download', 'coupon', '', $chargePolicy, (string) ($couponResult['dedupe_key'] ?? ''));
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
            ];
        }
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($couponIssueId > 0) {
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

    $allocations = sr_content_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
    if ($allocations === []) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, sr_content_asset_balance_shortage_message($pdo, $assetModules, $accountId, $amount, $settlementCurrency, '파일을 다운로드할 수 없습니다.', '선택한 항목의 잔액이 부족해 파일을 다운로드할 수 없습니다.'));
    }

    $dedupeKey = '';
    $accessLogIds = [];
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) ($allocation['asset_amount'] ?? $allocation['amount']);
            $allocatedSettlementAmount = (int) ($allocation['settlement_amount'] ?? 0);
            $allocationSettlementCurrency = (string) ($allocation['settlement_currency'] ?? $settlementCurrency);
            $purchasePowerSnapshotJson = sr_content_asset_purchase_power_snapshot_json(is_array($allocation['purchase_power_snapshot'] ?? null) ? $allocation['purchase_power_snapshot'] : []);
            $assetOption = sr_content_asset_modules($pdo)[$assetModule];
            $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.download', $assetModule, $accountId, $fileId, 'download', $requestToken);
            $inserted = sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, $allocatedAmount, $chargePolicy, $dedupeKey, 'content.download', (string) $fileId, 'download', sr_content_asset_group_policy_snapshot_json(isset($policyAmounts['snapshots'][$assetModule]) ? [$policyAmounts['snapshots'][$assetModule]] : []), $allocatedSettlementAmount, $allocationSettlementCurrency, $purchasePowerSnapshotJson);
            if (!$inserted) {
                if ($chargePolicy === 'once') {
                    throw new RuntimeException('Incomplete or duplicate content file asset access.');
                }
                continue;
            }

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
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif ($dedupeKey !== '') {
            sr_content_delete_asset_access_placeholder($pdo, $dedupeKey);
        }
        if ($startedTransaction && sr_content_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_file_download_charge_failed');
        }

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '포인트/금액 차감에 실패해 파일을 다운로드할 수 없습니다.');
    }

    return sr_content_asset_access_result($pdo, true, true, $assetModuleValue, $amount, '', ['confirmation_fingerprint' => $confirmationFingerprint, 'access_log_ids' => array_values(array_filter($accessLogIds))]);
}
