<?php

declare(strict_types=1);

function sr_community_paid_read_session_key(int $accountId, int $postId): string
{
    return (string) $accountId . ':' . (string) $postId;
}

function sr_community_has_paid_read_session(int $accountId, int $postId): bool
{
    $key = sr_community_paid_read_session_key($accountId, $postId);
    $sessions = is_array($_SESSION['sr_community_paid_read_posts'] ?? null) ? $_SESSION['sr_community_paid_read_posts'] : [];
    $createdAt = isset($sessions[$key]) ? (int) $sessions[$key] : 0;

    if ($createdAt > 0 && $createdAt >= time() - 300) {
        return true;
    }

    unset($_SESSION['sr_community_paid_read_posts'][$key]);
    return false;
}

function sr_community_mark_paid_read_session(int $accountId, int $postId): void
{
    if ($accountId < 1 || $postId < 1) {
        return;
    }

    if (!isset($_SESSION['sr_community_paid_read_posts']) || !is_array($_SESSION['sr_community_paid_read_posts'])) {
        $_SESSION['sr_community_paid_read_posts'] = [];
    }

    $_SESSION['sr_community_paid_read_posts'][sr_community_paid_read_session_key($accountId, $postId)] = time();
}

function sr_community_attachment_paid_read_bridge_key(int $accountId, int $attachmentId): string
{
    return (string) $accountId . ':' . (string) $attachmentId;
}

function sr_community_mark_attachment_paid_read_bridge(int $accountId, int $attachmentId, string $fingerprint, int $createdAt = 0): void
{
    if ($accountId < 1 || $attachmentId < 1 || $fingerprint === '') {
        return;
    }

    if (!isset($_SESSION['sr_community_attachment_paid_read_bridges']) || !is_array($_SESSION['sr_community_attachment_paid_read_bridges'])) {
        $_SESSION['sr_community_attachment_paid_read_bridges'] = [];
    }

    $_SESSION['sr_community_attachment_paid_read_bridges'][sr_community_attachment_paid_read_bridge_key($accountId, $attachmentId)] = [
        'created_at' => $createdAt > 0 ? $createdAt : time(),
        'fingerprint' => $fingerprint,
    ];
}

function sr_community_consume_attachment_paid_read_bridge_created_at(int $accountId, int $attachmentId, string $fingerprint): int
{
    $key = sr_community_attachment_paid_read_bridge_key($accountId, $attachmentId);
    $sessions = is_array($_SESSION['sr_community_attachment_paid_read_bridges'] ?? null) ? $_SESSION['sr_community_attachment_paid_read_bridges'] : [];
    $session = isset($sessions[$key]) && is_array($sessions[$key]) ? $sessions[$key] : [];
    $createdAt = (int) ($session['created_at'] ?? 0);
    $sessionFingerprint = (string) ($session['fingerprint'] ?? '');
    unset($_SESSION['sr_community_attachment_paid_read_bridges'][$key]);

    if ($createdAt > 0
        && $createdAt >= time() - 300
        && $fingerprint !== ''
        && hash_equals($sessionFingerprint, $fingerprint)
    ) {
        return $createdAt;
    }

    return 0;
}

function sr_community_consume_attachment_paid_read_bridge(int $accountId, int $attachmentId, string $fingerprint): bool
{
    return sr_community_consume_attachment_paid_read_bridge_created_at($accountId, $attachmentId, $fingerprint) > 0;
}

function sr_community_asset_dedupe_key(string $assetModule, int $accountId, string $eventKey, int $subjectId): string
{
    return 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId;
}

function sr_community_asset_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_asset_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_has_asset_event(PDO $pdo, string $assetModule, int $accountId, string $eventKey, int $subjectId): bool
{
    $log = sr_community_asset_log($pdo, sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId));

    return is_array($log)
        && (string) ($log['log_status'] ?? sr_community_asset_log_status_completed()) === sr_community_asset_log_status_completed()
        && ((int) ($log['transaction_id'] ?? 0) > 0 || (int) ($log['amount'] ?? -1) === 0);
}

function sr_community_has_asset_event_for_modules(PDO $pdo, array $assetModules, int $accountId, string $eventKey, int $subjectId): bool
{
    foreach (sr_community_asset_module_keys_from_value($assetModules, true) as $assetModule) {
        if (sr_community_has_asset_event($pdo, $assetModule, $accountId, $eventKey, $subjectId)) {
            return true;
        }
    }

    return false;
}

function sr_community_has_asset_event_history(PDO $pdo, array $assetModules, int $accountId, string $eventKey, int $subjectId, string $policy): bool
{
    $policy = sr_community_once_history_policy($policy);
    if ($policy === 'current_asset_once') {
        return sr_community_has_asset_event_for_modules($pdo, $assetModules, $accountId, $eventKey, $subjectId);
    }

    $params = [
        'account_id' => $accountId,
        'event_key' => $eventKey,
        'subject_id' => $subjectId,
    ];
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_community_asset_logs
         WHERE account_id = :account_id
           AND event_key = :event_key
           AND subject_id = :subject_id
           AND direction = \'use\'
           AND log_status = \'completed\'
           AND (transaction_id > 0 OR amount = 0)'
        . ' LIMIT 1'
    );
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_community_has_coupon_access_history(PDO $pdo, int $accountId, string $dedupeKey): bool
{
    if ($accountId <= 0 || $dedupeKey === '' || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return false;
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_has_redemption')) {
        return false;
    }

    return sr_coupon_has_redemption($pdo, $accountId, $dedupeKey);
}

function sr_community_access_entitlements_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_community_access_entitlements LIMIT 1');
        $exists = $stmt !== false;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_asset_log_settlement_metadata_columns_exist(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $prefix = $pdo instanceof SrPrefixedPDO ? $pdo->srTablePrefix() : 'sr_';
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS column_count
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME IN (\'settlement_kind\', \'snapshot_schema_version\', \'rounding_policy_version\')'
        );
        $stmt->execute(['table_name' => $prefix . 'community_asset_logs']);
        $row = $stmt->fetch();
        $exists = is_array($row) && (int) ($row['column_count'] ?? 0) === 3;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_grant_access_entitlement(PDO $pdo, int $accountId, string $subjectType, int $subjectId, string $eventKey, string $sourceKind, string $sourceAssetModule = '', string $sourceChargePolicy = 'once', string $sourceReference = ''): void
{
    if ($accountId <= 0 || $subjectType === '' || $subjectId <= 0 || $eventKey === '' || !sr_community_access_entitlements_table_exists($pdo)) {
        return;
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
        $insertVerb . ' INTO sr_community_access_entitlements
            (account_id, subject_type, subject_id, event_key, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at)
         VALUES
            (:account_id, :subject_type, :subject_id, :event_key, :source_kind, :source_asset_module, :source_charge_policy, :source_reference, :granted_at, :created_at)'
    );
    $now = sr_now();
    $stmt->execute([
        'account_id' => $accountId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'event_key' => $eventKey,
        'source_kind' => $sourceKind,
        'source_asset_module' => $sourceAssetModule,
        'source_charge_policy' => $sourceChargePolicy,
        'source_reference' => $sourceReference,
        'granted_at' => $now,
        'created_at' => $now,
    ]);
}

function sr_community_revoke_coupon_access_entitlements(PDO $pdo, int $accountId, string $sourceReference): int
{
    if ($accountId <= 0 || $sourceReference === '' || !sr_community_access_entitlements_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "DELETE FROM sr_community_access_entitlements
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

function sr_community_anonymize_access_entitlements(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0 || !sr_community_access_entitlements_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_access_entitlements
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

function sr_community_has_access_entitlement(PDO $pdo, array $assetModules, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $couponDedupeKey, string $policy): bool
{
    $policy = sr_community_once_history_policy($policy);
    if (!sr_community_access_entitlements_table_exists($pdo)) {
        if (sr_community_has_asset_event_history($pdo, $assetModules, $accountId, $eventKey, $subjectId, $policy)) {
            return true;
        }

        return $policy === 'all_access'
            && $couponDedupeKey !== ''
            && sr_community_has_coupon_access_history($pdo, $accountId, $couponDedupeKey);
    }

    $conditions = [
        'account_id = :account_id',
        'subject_type = :subject_type',
        'subject_id = :subject_id',
        'event_key = :event_key',
        'anonymized_at IS NULL',
    ];
    $params = [
        'account_id' => $accountId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'event_key' => $eventKey,
    ];

    if ($policy === 'asset_any') {
        $conditions[] = 'source_kind = \'asset\'';
    } elseif ($policy === 'current_asset_once') {
        $moduleKeys = sr_community_asset_module_keys_from_value($assetModules, true);
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
         FROM sr_community_access_entitlements
         WHERE ' . implode(' AND ', $conditions) . '
         LIMIT 1'
    );
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_community_once_access_already_granted(PDO $pdo, array $config, int $accountId, string $eventKey, int $subjectId, string $couponDedupeKey = ''): bool
{
    $settings = sr_community_settings($pdo);
    $policy = sr_community_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '', true);
    $subjectType = $eventKey === 'attachment_download' ? 'community.attachment' : 'community.post';

    return sr_community_has_access_entitlement($pdo, $assetModules, $accountId, $eventKey, $subjectType, $subjectId, $couponDedupeKey, $policy);
}

// This helper owns its transaction boundary so fallback coupon targets cannot
// commit partial redemption work from a previous target attempt.
function sr_community_try_paid_read_coupon_access(PDO $pdo, int $accountId, array $post, array $paidReadConfig, string $couponDedupeKey): array
{
    $postId = (int) ($post['id'] ?? 0);
    $boardId = (int) ($post['board_id'] ?? 0);
    if ($accountId <= 0 || $postId <= 0 || $couponDedupeKey === '') {
        return ['allowed' => false, 'processed' => false];
    }

    if ((string) ($paidReadConfig['charge_policy'] ?? 'once') === 'once'
        && sr_community_once_access_already_granted($pdo, $paidReadConfig, $accountId, 'post_read', $postId, $couponDedupeKey)
    ) {
        return ['allowed' => true, 'processed' => false, 'already_redeemed' => true];
    }

    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return ['allowed' => false, 'processed' => false];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_redeem_for_target')) {
        return ['allowed' => false, 'processed' => false];
    }

    $assetModules = sr_community_asset_module_keys_from_value($paidReadConfig['asset_module'] ?? '', true);
    $assetModuleValue = sr_community_asset_module_value_from_keys($assetModules, true);
    $amounts = is_array($paidReadConfig['amounts'] ?? null) ? $paidReadConfig['amounts'] : [];
    $policyAmounts = sr_community_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($paidReadConfig['amount'] ?? 0), $paidReadConfig['group_policies_json'] ?? '', (int) ($paidReadConfig['policy_set_id'] ?? 0), 'use');
    $policySnapshotJson = sr_community_asset_group_policy_snapshot_json($policyAmounts['snapshots']);
    $confirmationFingerprint = sr_community_asset_confirmation_fingerprint(
        'post_read',
        'community.post',
        (string) ($paidReadConfig['charge_policy'] ?? 'once'),
        $assetModuleValue,
        (int) $policyAmounts['amount'],
        is_array($policyAmounts['amounts'] ?? null) ? $policyAmounts['amounts'] : [],
        $policySnapshotJson
    );

    $couponContext = [
        'dedupe_key' => $couponDedupeKey,
        'reference_module' => 'community',
        'reference_type' => 'community.post',
        'reference_id' => (string) $postId,
    ];

    foreach ([['community_post', (string) $postId], ['community_board', (string) $boardId]] as $target) {
        if ((string) $target[1] === '0') {
            continue;
        }

        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $couponResult = sr_coupon_redeem_for_target($pdo, $accountId, (string) $target[0], (string) $target[1], $couponContext);
            if (!empty($couponResult['allowed'])) {
                sr_community_grant_access_entitlement($pdo, $accountId, 'community.post', $postId, 'post_read', 'coupon', '', (string) ($paidReadConfig['charge_policy'] ?? 'once'), $couponDedupeKey);
                if ($startedTransaction) {
                    $pdo->commit();
                }

                $couponResult['confirmation_fingerprint'] = $confirmationFingerprint;
                return $couponResult;
            }

            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_coupon_entitlement_failed');
            }
        }
    }

    return ['allowed' => false, 'processed' => false];
}

function sr_community_insert_asset_log_placeholder(PDO $pdo, array $row): bool
{
    $settlementAmount = max(0, (int) ($row['settlement_amount'] ?? 0));
    $purchasePowerSnapshotJson = (string) ($row['purchase_power_snapshot_json'] ?? '');
    $settlementKind = sr_community_asset_settlement_kind(
        (string) $row['direction'],
        (int) $row['amount'],
        $settlementAmount,
        $purchasePowerSnapshotJson
    );
    $insertVerb = 'INSERT IGNORE';
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $insertVerb = 'INSERT OR IGNORE';
        }
    } catch (Throwable $exception) {
        $insertVerb = 'INSERT IGNORE';
    }
    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_community_asset_logs
            (account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, direction, charge_policy, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, settlement_kind, snapshot_schema_version, rounding_policy_version, log_status, group_policy_snapshot_json, dedupe_key, created_at)
         VALUES
            (:account_id, :asset_module, 0, :reference_type, :reference_id, :subject_type, :subject_id, :event_key, :direction, :charge_policy, :amount, :settlement_amount, :settlement_currency, :purchase_power_snapshot_json, :settlement_kind, :snapshot_schema_version, :rounding_policy_version, :log_status, :group_policy_snapshot_json, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'account_id' => (int) $row['account_id'],
        'asset_module' => (string) $row['asset_module'],
        'reference_type' => (string) $row['reference_type'],
        'reference_id' => (string) $row['reference_id'],
        'subject_type' => (string) $row['subject_type'],
        'subject_id' => (int) $row['subject_id'],
        'event_key' => (string) $row['event_key'],
        'direction' => (string) $row['direction'],
        'charge_policy' => (string) $row['charge_policy'],
        'amount' => (int) $row['amount'],
        'settlement_amount' => $settlementAmount,
        'settlement_currency' => sr_community_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($row['settlement_currency'] ?? 'KRW')]),
        'purchase_power_snapshot_json' => $purchasePowerSnapshotJson,
        'settlement_kind' => $settlementKind,
        'snapshot_schema_version' => sr_community_asset_snapshot_schema_version(),
        'rounding_policy_version' => sr_community_asset_rounding_policy_version(),
        'log_status' => sr_community_asset_log_status_pending(),
        'group_policy_snapshot_json' => (string) ($row['group_policy_snapshot_json'] ?? ''),
        'dedupe_key' => (string) $row['dedupe_key'],
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_community_update_asset_log_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_asset_logs
         SET transaction_id = :transaction_id,
             log_status = :log_status
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'log_status' => sr_community_asset_log_status_completed(),
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_community_complete_zero_asset_log(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_asset_logs
         SET log_status = :log_status
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0
           AND amount = 0'
    );
    $stmt->execute([
        'log_status' => sr_community_asset_log_status_completed(),
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_community_delete_asset_log_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_community_asset_logs
         WHERE dedupe_key = :dedupe_key
           AND log_status = :log_status'
    );
    $stmt->execute([
        'dedupe_key' => $dedupeKey,
        'log_status' => sr_community_asset_log_status_pending(),
    ]);
}

function sr_community_run_asset_event(PDO $pdo, array $config, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $direction, string $reason, bool $process = true, string $requestToken = ''): array
{
    return sr_community_asset_retry_operation($pdo, static function () use ($pdo, $config, $accountId, $eventKey, $subjectType, $subjectId, $direction, $reason, $process, $requestToken): array {
        return sr_community_run_asset_event_once($pdo, $config, $accountId, $eventKey, $subjectType, $subjectId, $direction, $reason, $process, $requestToken);
    });
}

function sr_community_run_asset_event_once(PDO $pdo, array $config, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $direction, string $reason, bool $process = true, string $requestToken = ''): array
{
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '', true);
    $assetModuleValue = sr_community_asset_module_value_from_keys($assetModules, true);
    $amounts = is_array($config['amounts'] ?? null) ? $config['amounts'] : [];
    $amount = $amounts !== [] ? sr_community_asset_amount_total($amounts) : (int) ($config['amount'] ?? 0);
    $chargePolicy = (string) ($config['charge_policy'] ?? 'once');

    if ($accountId <= 0 || $subjectId <= 0 || $amount <= 0 || $assetModules === []) {
        return ['allowed' => true, 'processed' => false, 'message' => ''];
    }

    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_modules_unavailable',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => sr_t('community::action.error.asset_modules_unavailable'),
        ];
    }

    $once = in_array($chargePolicy, ['once'], true) || in_array($direction, ['grant', 'refund'], true);
    $alreadyProcessed = false;
    if ($once && $direction === 'use') {
        $settings = sr_community_settings($pdo);
        $onceHistoryPolicy = sr_community_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));
        $alreadyProcessed = sr_community_has_access_entitlement($pdo, $assetModules, $accountId, $eventKey, $subjectType, $subjectId, '', $onceHistoryPolicy);
    } elseif ($once) {
        $alreadyProcessed = sr_community_has_asset_event_for_modules($pdo, $assetModules, $accountId, $eventKey, $subjectId);
    }
    if ($alreadyProcessed) {
        return [
            'allowed' => true,
            'processed' => false,
            'already_processed' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => '',
        ];
    }

    $policyAmounts = sr_community_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($config['amount'] ?? 0), $config['group_policies_json'] ?? '', (int) ($config['policy_set_id'] ?? 0), $direction === 'use' ? 'use' : 'grant');
    $amounts = $amounts !== [] ? $policyAmounts['amounts'] : [];
    $amount = (int) $policyAmounts['amount'];
    $policySnapshotJson = sr_community_asset_group_policy_snapshot_json($policyAmounts['snapshots']);
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, $config);
    $confirmationFingerprint = sr_community_asset_confirmation_fingerprint($eventKey, $subjectType, $chargePolicy, $assetModuleValue, $amount, $amounts, $policySnapshotJson);
    if ($direction === 'use' && sr_community_asset_policy_requires_confirmation($chargePolicy) && !$process) {
        if (sr_community_consume_asset_confirmation_session($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint)) {
            return [
                'allowed' => true,
                'processed' => false,
                'confirmed_access' => true,
                'asset_module' => $assetModuleValue,
                'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                'amount' => $amount,
                'confirmation_fingerprint' => $confirmationFingerprint,
                'message' => '',
            ];
        }

        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_confirmation_required',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'confirmation_fingerprint' => $confirmationFingerprint,
            'confirmation_request_token' => sr_community_asset_confirmation_request_token($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint),
            'message' => sr_community_asset_confirmation_required_message(),
        ];
    } elseif ($direction === 'use' && sr_community_asset_policy_requires_confirmation($chargePolicy) && $process && !sr_community_asset_confirmation_request_token_valid($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint, $requestToken)) {
        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_confirmation_required',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'confirmation_fingerprint' => $confirmationFingerprint,
            'confirmation_request_token' => sr_community_asset_confirmation_request_token($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint),
            'message' => sr_community_asset_confirmation_required_message(),
        ];
    }

    if ($amount <= 0) {
        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $stableRequestToken = preg_match('/\A[a-f0-9]{32}\z/', $requestToken) === 1 ? $requestToken : bin2hex(random_bytes(16));
        $dedupeKey = $once
            ? sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId)
            : 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':' . $stableRequestToken;
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            sr_community_insert_asset_log_placeholder($pdo, [
                'account_id' => $accountId,
                'asset_module' => $assetModule,
                'reference_type' => $subjectType,
                'reference_id' => (string) $subjectId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'event_key' => $eventKey,
                'direction' => $direction,
                'charge_policy' => $chargePolicy,
                'amount' => 0,
                'group_policy_snapshot_json' => $policySnapshotJson,
                'dedupe_key' => $dedupeKey,
            ]);
            sr_community_complete_zero_asset_log($pdo, $dedupeKey);
            if ($direction === 'use' && in_array($eventKey, ['post_read', 'attachment_download'], true)) {
                sr_community_grant_access_entitlement($pdo, $accountId, $subjectType, $subjectId, $eventKey, 'asset_group_policy', $assetModule, $chargePolicy, $dedupeKey);
            }
            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($startedTransaction && sr_community_asset_is_retryable_transaction_exception($exception)) {
                throw $exception;
            }
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_asset_group_event_failed');
            }

            return [
                'allowed' => false,
                'processed' => false,
                'error_key' => 'asset_processing_failed',
                'asset_module' => $assetModuleValue,
                'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                'amount' => 0,
                'message' => sr_t('community::action.error.asset_processing_failed'),
            ];
        }

        return [
            'allowed' => true,
            'processed' => true,
            'group_policy_applied' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => 0,
            'direction' => $direction,
            'message' => '',
        ];
    }

    $allocations = $direction === 'use'
        ? sr_community_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency)
        : [['asset_module' => $assetModules[0], 'amount' => $amount]];
    if ($direction === 'use' && $allocations === []) {
        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_balance_low',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'confirmation_fingerprint' => $confirmationFingerprint,
            'message' => sr_t('community::action.error.asset_balance_low'),
        ];
    }

    $processed = false;
    $processedLogs = [];
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
            $purchasePowerSnapshotJson = $direction === 'use' ? sr_community_asset_purchase_power_snapshot_json(is_array($allocation['purchase_power_snapshot'] ?? null) ? $allocation['purchase_power_snapshot'] : []) : '';
            $module = sr_community_asset_modules($pdo)[$assetModule];
            $stableRequestToken = preg_match('/\A[a-f0-9]{32}\z/', $requestToken) === 1 ? $requestToken : bin2hex(random_bytes(16));
            $dedupeKey = $once
                ? sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId)
                : 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':' . $stableRequestToken;
            $inserted = sr_community_insert_asset_log_placeholder($pdo, [
                'account_id' => $accountId,
                'asset_module' => $assetModule,
                'reference_type' => $subjectType,
                'reference_id' => (string) $subjectId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'event_key' => $eventKey,
                'direction' => $direction,
                'charge_policy' => $chargePolicy,
                'amount' => $allocatedAmount,
                'settlement_amount' => $allocatedSettlementAmount,
                'settlement_currency' => $allocationSettlementCurrency,
                'purchase_power_snapshot_json' => $purchasePowerSnapshotJson,
                'group_policy_snapshot_json' => sr_community_asset_group_policy_snapshot_json(isset($policyAmounts['snapshots'][$assetModule]) ? [$policyAmounts['snapshots'][$assetModule]] : []),
                'dedupe_key' => $dedupeKey,
            ]);
            if (!$inserted) {
                if ($once) {
                    throw new RuntimeException('Incomplete or duplicate community asset event.');
                }
                continue;
            }

            $signedAmount = $direction === 'use' ? -$allocatedAmount : $allocatedAmount;
            $transactionType = $direction === 'use'
                ? (string) ($module['use_type'] ?? 'use')
                : ($direction === 'refund' ? (string) ($module['refund_type'] ?? 'refund') : (string) ($module['credit_type'] ?? 'grant'));
            $transactionId = sr_community_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => $signedAmount,
                'transaction_type' => $transactionType,
                'reason' => $reason,
                'reference_type' => $subjectType,
                'reference_id' => (string) $subjectId,
                'created_by_account_id' => null,
            ]);
            sr_community_update_asset_log_transaction($pdo, $dedupeKey, $transactionId);
            if ($direction === 'use' && in_array($eventKey, ['post_read', 'attachment_download'], true)) {
                sr_community_grant_access_entitlement($pdo, $accountId, $subjectType, $subjectId, $eventKey, 'asset', $assetModule, $chargePolicy, $assetModule . ':' . (string) $transactionId);
            }
            $processedLogs[] = [
                'dedupe_key' => $dedupeKey,
                'asset_module' => $assetModule,
                'transaction_id' => $transactionId,
                'amount' => $allocatedAmount,
                'settlement_amount' => $allocatedSettlementAmount,
                'settlement_currency' => $allocationSettlementCurrency,
            ];
            $processed = true;
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif ($dedupeKey !== '') {
            sr_community_delete_asset_log_placeholder($pdo, $dedupeKey);
        }
        if ($startedTransaction && sr_community_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'community_asset_event_failed');
        }

        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_processing_failed',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => sr_t('community::action.error.asset_processing_failed'),
        ];
    }

    return [
        'allowed' => true,
        'processed' => $processed,
        'asset_module' => $assetModuleValue,
        'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
        'amount' => $amount,
        'direction' => $direction,
        'processed_logs' => $processedLogs,
        'confirmation_fingerprint' => $confirmationFingerprint,
        'message' => '',
    ];
}

function sr_community_asset_log_by_dedupe_key(PDO $pdo, string $dedupeKey): ?array
{
    return sr_community_asset_log($pdo, $dedupeKey);
}

function sr_community_publisher_reward_log_by_dedupe_key(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_publisher_reward_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_create_publisher_reward_notification(PDO $pdo, int $publisherAccountId, array $rewardLog): void
{
    if ($publisherAccountId < 1 || !is_file(SR_ROOT . '/modules/community/helpers/notifications.php')) {
        return;
    }

    require_once SR_ROOT . '/modules/community/helpers/notifications.php';
    if (!function_exists('sr_community_create_account_event_notification')) {
        return;
    }

    sr_community_create_account_event_notification($pdo, $publisherAccountId, 'attachment.publisher_reward.granted', [
        'amount' => number_format((int) ($rewardLog['reward_amount'] ?? 0)),
        'asset' => sr_community_asset_module_label((string) ($rewardLog['asset_module'] ?? ''), $pdo),
        'link_url' => sr_url('/community/post?id=' . rawurlencode((string) (int) ($rewardLog['post_id'] ?? 0))),
    ], null);
}

function sr_community_grant_attachment_publisher_reward(PDO $pdo, array $board, array $settings, array $post, array $attachment, int $downloaderAccountId, array $downloadResult): void
{
    $config = sr_community_publisher_reward_config($pdo, $board, $settings);
    if (empty($config['enabled']) || (int) ($config['rate'] ?? 0) <= 0 || empty($downloadResult['processed'])) {
        return;
    }

    $publisherAccountId = (int) ($post['author_account_id'] ?? 0);
    $attachmentId = (int) ($attachment['id'] ?? 0);
    $postId = (int) ($post['id'] ?? 0);
    if ($publisherAccountId < 1 || $downloaderAccountId < 1 || $attachmentId < 1 || $postId < 1 || $publisherAccountId === $downloaderAccountId) {
        return;
    }

    $processedLogs = is_array($downloadResult['processed_logs'] ?? null) ? $downloadResult['processed_logs'] : [];
    foreach ($processedLogs as $processedLog) {
        $dedupeKey = (string) ($processedLog['dedupe_key'] ?? '');
        if ($dedupeKey === '') {
            continue;
        }

        $chargeLog = sr_community_asset_log_by_dedupe_key($pdo, $dedupeKey);
        if (!is_array($chargeLog)
            || (string) ($chargeLog['event_key'] ?? '') !== 'attachment_download'
            || (string) ($chargeLog['direction'] ?? '') !== 'use'
            || (int) ($chargeLog['transaction_id'] ?? 0) < 1
            || (int) ($chargeLog['amount'] ?? 0) <= 0
        ) {
            continue;
        }

        $chargeAmount = (int) $chargeLog['amount'];
        $rewardRate = (int) $config['rate'];
        $rewardAmount = intdiv($chargeAmount * $rewardRate, 100);
        if ($rewardAmount <= 0) {
            continue;
        }

        $rewardDedupeKey = 'community.attachment_download.publisher_reward:' . (string) (int) $chargeLog['id'];
        if (sr_community_publisher_reward_log_by_dedupe_key($pdo, $rewardDedupeKey) !== null) {
            continue;
        }

        $now = sr_now();
        $insert = $pdo->prepare(
            'INSERT INTO sr_community_publisher_reward_logs
                (charge_asset_log_id, charge_transaction_id, reward_transaction_id, reversal_transaction_id,
                 post_id, attachment_id, downloader_account_id, publisher_account_id, asset_module,
                 charge_amount, reward_rate, reward_amount, status, dedupe_key, failure_message, created_at, updated_at)
             VALUES
                (:charge_asset_log_id, :charge_transaction_id, 0, 0,
                 :post_id, :attachment_id, :downloader_account_id, :publisher_account_id, :asset_module,
                 :charge_amount, :reward_rate, :reward_amount, \'pending\', :dedupe_key, NULL, :created_at, :updated_at)'
        );
        try {
            $insert->execute([
                'charge_asset_log_id' => (int) $chargeLog['id'],
                'charge_transaction_id' => (int) $chargeLog['transaction_id'],
                'post_id' => $postId,
                'attachment_id' => $attachmentId,
                'downloader_account_id' => $downloaderAccountId,
                'publisher_account_id' => $publisherAccountId,
                'asset_module' => (string) $chargeLog['asset_module'],
                'charge_amount' => $chargeAmount,
                'reward_rate' => $rewardRate,
                'reward_amount' => $rewardAmount,
                'dedupe_key' => $rewardDedupeKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_publisher_reward_log_insert_failed');
            }
            continue;
        }

        try {
            $transactionId = sr_community_create_asset_transaction($pdo, (string) $chargeLog['asset_module'], [
                'account_id' => $publisherAccountId,
                'amount' => $rewardAmount,
                'transaction_type' => (string) ((sr_community_asset_modules($pdo)[(string) $chargeLog['asset_module']]['credit_type'] ?? 'grant')),
                'reason' => '첨부 다운로드 리워드',
                'reference_type' => 'community.attachment.publisher_reward',
                'reference_id' => (string) (int) $chargeLog['id'],
                'created_by_account_id' => null,
            ]);
            $stmt = $pdo->prepare(
                "UPDATE sr_community_publisher_reward_logs
                 SET reward_transaction_id = :transaction_id,
                     status = 'granted',
                     updated_at = :updated_at
                 WHERE dedupe_key = :dedupe_key"
            );
            $stmt->execute([
                'transaction_id' => $transactionId,
                'updated_at' => sr_now(),
                'dedupe_key' => $rewardDedupeKey,
            ]);
            $rewardLog = sr_community_publisher_reward_log_by_dedupe_key($pdo, $rewardDedupeKey);
            if (is_array($rewardLog)) {
                sr_community_create_publisher_reward_notification($pdo, $publisherAccountId, $rewardLog);
            }
        } catch (Throwable $exception) {
            $stmt = $pdo->prepare(
                "UPDATE sr_community_publisher_reward_logs
                 SET status = 'failed',
                     failure_message = :failure_message,
                     updated_at = :updated_at
                 WHERE dedupe_key = :dedupe_key"
            );
            $stmt->execute([
                'failure_message' => mb_substr($exception->getMessage(), 0, 1000),
                'updated_at' => sr_now(),
                'dedupe_key' => $rewardDedupeKey,
            ]);
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_publisher_reward_grant_failed');
            }
        }
    }
}

function sr_community_asset_reversal_config(array $originalLog): array
{
    return [
        'enabled' => true,
        'asset_module' => (string) ($originalLog['asset_module'] ?? 'point'),
        'amount' => (int) ($originalLog['amount'] ?? 0),
        'charge_policy' => 'once',
    ];
}

function sr_community_reverse_asset_grant(PDO $pdo, int $accountId, string $grantEventKey, string $subjectType, int $subjectId, string $reversalEventKey, string $reason): array
{
    foreach (array_keys(sr_community_asset_modules()) as $assetModule) {
        $original = sr_community_asset_log($pdo, sr_community_asset_dedupe_key((string) $assetModule, $accountId, $grantEventKey, $subjectId));
        if (!is_array($original) || (int) ($original['transaction_id'] ?? 0) < 1 || (string) ($original['direction'] ?? '') !== 'grant') {
            continue;
        }

        return sr_community_run_asset_event($pdo, sr_community_asset_reversal_config($original), $accountId, $reversalEventKey, $subjectType, $subjectId, 'use', $reason);
    }

    return ['allowed' => true, 'processed' => false, 'message' => ''];
}

function sr_community_asset_reversal_error_message(array $result, string $balanceLowKey, string $fallbackKey): string
{
    if ((string) ($result['error_key'] ?? '') === 'asset_balance_low') {
        return sr_t($balanceLowKey);
    }

    $message = trim((string) ($result['message'] ?? ''));
    return $message !== '' ? $message : sr_t($fallbackKey);
}
