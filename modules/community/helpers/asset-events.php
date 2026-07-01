<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/asset_ledger/helpers.php';

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

function sr_community_record_payment_ledger_if_available(PDO $pdo, array $record, array $items): int
{
    if (!function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'payment_ledger')) {
        return 0;
    }
    if (!is_file(SR_ROOT . '/modules/payment_ledger/helpers.php')) {
        return 0;
    }

    require_once SR_ROOT . '/modules/payment_ledger/helpers.php';
    if (!function_exists('sr_payment_ledger_record_payment') || !sr_payment_ledger_tables_available($pdo)) {
        return 0;
    }

    return sr_payment_ledger_record_payment($pdo, $record, $items);
}

function sr_community_payment_subject_type(string $eventKey, string $subjectType): string
{
    if ($eventKey === 'post_read' && $subjectType === 'community.post') {
        return 'community.post.read';
    }
    if ($eventKey === 'attachment_download' && $subjectType === 'community.attachment') {
        return 'community.attachment.download';
    }

    return 'community.' . $eventKey;
}

function sr_community_payment_description(string $eventKey): string
{
    return match ($eventKey) {
        'post_read' => '커뮤니티 게시글 열람 결제',
        'attachment_download' => '커뮤니티 첨부 다운로드 결제',
        'post_write_charge' => '커뮤니티 글쓰기 결제',
        'comment_write_charge' => '커뮤니티 댓글 작성 결제',
        'message_send_charge' => '커뮤니티 쪽지 발송 결제',
        default => '커뮤니티 자산 결제',
    };
}

function sr_community_payment_coupon_item(array $couponResult, string $settlementCurrency): array
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

function sr_community_payment_access_item(int $accountId, string $subjectType, int $subjectId, string $eventKey, string $sourceKind, string $sourceReference = ''): array
{
    return [
        'item_kind' => 'access_entitlement',
        'owner_module' => 'community',
        'reference_type' => 'community.access_entitlement',
        'reference_id' => $subjectType . ':' . (string) $subjectId . ':' . $eventKey . ':account:' . (string) $accountId,
        'amount' => 0,
        'currency_code' => '',
        'reversible' => true,
        'snapshot' => [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'event_key' => $eventKey,
            'source_kind' => $sourceKind,
            'source_reference' => $sourceReference,
        ],
    ];
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

function sr_community_available_paid_read_coupon_issues(PDO $pdo, int $accountId, array $post, int $limit = 20): array
{
    $postId = (int) ($post['id'] ?? 0);
    $boardId = (int) ($post['board_id'] ?? 0);
    if ($accountId <= 0 || $postId <= 0 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return [];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_active_account_target_issues')) {
        return [];
    }

    $issues = sr_coupon_active_account_target_issues($pdo, $accountId, 'community_post', (string) $postId, $limit);
    if ($boardId > 0 && count($issues) < $limit) {
        $seen = [];
        foreach ($issues as $issue) {
            $seen[(int) ($issue['id'] ?? 0)] = true;
        }
        foreach (sr_coupon_active_account_target_issues($pdo, $accountId, 'community_board', (string) $boardId, $limit) as $issue) {
            $issueId = (int) ($issue['id'] ?? 0);
            if ($issueId <= 0 || isset($seen[$issueId])) {
                continue;
            }
            $issues[] = $issue;
            $seen[$issueId] = true;
            if (count($issues) >= $limit) {
                break;
            }
        }
    }

    return $issues;
}

function sr_community_available_attachment_download_coupon_issues(PDO $pdo, int $accountId, int $attachmentId, int $limit = 20): array
{
    if ($accountId <= 0 || $attachmentId <= 0 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return [];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_active_account_target_issues')) {
        return [];
    }

    return sr_coupon_active_account_target_issues($pdo, $accountId, 'community_attachment', (string) $attachmentId, $limit);
}

// This helper owns its transaction boundary so fallback coupon targets cannot
// commit partial redemption work from a previous target attempt.
function sr_community_try_paid_read_coupon_access(PDO $pdo, int $accountId, array $post, array $paidReadConfig, string $couponDedupeKey, int $couponIssueId = 0, array $pricingContext = []): array
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
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, $paidReadConfig);
    $confirmationFingerprint = sr_community_asset_confirmation_fingerprint(
        'post_read',
        'community.post',
        (string) ($paidReadConfig['charge_policy'] ?? 'once'),
        $assetModuleValue,
        (int) $policyAmounts['amount'],
        is_array($policyAmounts['amounts'] ?? null) ? $policyAmounts['amounts'] : [],
        $policySnapshotJson,
        $settlementCurrency
    );

    $couponContext = [
        'dedupe_key' => $couponDedupeKey,
        'reference_module' => 'community',
        'reference_type' => 'community.post',
        'reference_id' => (string) $postId,
        'price_amount' => (int) $policyAmounts['amount'],
        'currency_code' => $settlementCurrency,
        'policy_summary' => '게시글 열람 ' . number_format((int) $policyAmounts['amount']) . $settlementCurrency,
    ];
    if ($couponIssueId > 0) {
        $couponContext['coupon_issue_id'] = $couponIssueId;
    }
    $couponContext = array_merge($couponContext, $pricingContext);

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
                $remainingAmount = max(0, (int) ($couponResult['remaining_amount'] ?? 0));
                if ($remainingAmount > 0 && $startedTransaction) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    return ['allowed' => false, 'processed' => false, 'message' => '할인 쿠폰은 자산 결제와 함께 처리해야 합니다.'];
                }
                if ($remainingAmount <= 0 && empty($couponResult['already_entitled'])) {
                    sr_community_grant_access_entitlement($pdo, $accountId, 'community.post', $postId, 'post_read', 'coupon', '', (string) ($paidReadConfig['charge_policy'] ?? 'once'), $couponDedupeKey);
                }
                if ($remainingAmount <= 0 && !empty($couponResult['processed'])) {
                    $paymentItems = array_values(array_filter([
                        sr_community_payment_coupon_item($couponResult, $settlementCurrency),
                        sr_community_payment_access_item($accountId, 'community.post', $postId, 'post_read', 'coupon', $couponDedupeKey),
                    ]));
                    sr_community_record_payment_ledger_if_available($pdo, [
                        'dedupe_key' => 'community.post.read:payment:coupon:' . (string) ($couponResult['coupon_redemption_id'] ?? ''),
                        'account_id' => $accountId,
                        'subject_module' => 'community',
                        'subject_type' => 'community.post.read',
                        'subject_id' => (string) $postId,
                        'payment_kind' => 'purchase',
                        'payable_amount' => (int) $policyAmounts['amount'],
                        'settlement_amount' => 0,
                        'settlement_currency' => $settlementCurrency,
                        'description' => '커뮤니티 게시글 열람 쿠폰 결제',
                        'snapshot' => [
                            'charge_policy' => (string) ($paidReadConfig['charge_policy'] ?? 'once'),
                            'coupon_covered_amount' => (int) $policyAmounts['amount'],
                        ],
                    ], $paymentItems);
                }
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

function sr_community_try_attachment_download_coupon_access(PDO $pdo, int $accountId, int|array $attachment, array $downloadConfig, string $couponDedupeKey, int $couponIssueId = 0, array $pricingContext = []): array
{
    $attachmentId = is_array($attachment) ? (int) ($attachment['id'] ?? 0) : (int) $attachment;
    if ($accountId <= 0 || $attachmentId <= 0 || $couponDedupeKey === '') {
        return ['allowed' => false, 'processed' => false];
    }

    if ((string) ($downloadConfig['charge_policy'] ?? 'once') === 'once'
        && sr_community_once_access_already_granted($pdo, $downloadConfig, $accountId, 'attachment_download', $attachmentId, $couponDedupeKey)
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

    $assetModules = sr_community_asset_module_keys_from_value($downloadConfig['asset_module'] ?? '', true);
    $assetModuleValue = sr_community_asset_module_value_from_keys($assetModules, true);
    $amounts = is_array($downloadConfig['amounts'] ?? null) ? $downloadConfig['amounts'] : [];
    $policyAmounts = sr_community_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($downloadConfig['amount'] ?? 0), $downloadConfig['group_policies_json'] ?? '', (int) ($downloadConfig['policy_set_id'] ?? 0), 'use');
    $policySnapshotJson = sr_community_asset_group_policy_snapshot_json($policyAmounts['snapshots']);
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, $downloadConfig);
    $confirmationFingerprint = sr_community_asset_confirmation_fingerprint(
        'attachment_download',
        'community.attachment',
        (string) ($downloadConfig['charge_policy'] ?? 'once'),
        $assetModuleValue,
        (int) $policyAmounts['amount'],
        is_array($policyAmounts['amounts'] ?? null) ? $policyAmounts['amounts'] : [],
        $policySnapshotJson,
        $settlementCurrency
    );

    $couponContext = [
        'dedupe_key' => $couponDedupeKey,
        'reference_module' => 'community',
        'reference_type' => 'community.attachment.download',
        'reference_id' => (string) $attachmentId,
        'price_amount' => (int) $policyAmounts['amount'],
        'currency_code' => $settlementCurrency,
        'policy_summary' => '첨부 다운로드 ' . number_format((int) $policyAmounts['amount']) . $settlementCurrency,
    ];
    if ($couponIssueId > 0) {
        $couponContext['coupon_issue_id'] = $couponIssueId;
    }
    $couponContext = array_merge($couponContext, $pricingContext);

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $couponResult = sr_coupon_redeem_for_target($pdo, $accountId, 'community_attachment', (string) $attachmentId, $couponContext);
        if (!empty($couponResult['allowed'])) {
            $remainingAmount = max(0, (int) ($couponResult['remaining_amount'] ?? 0));
            if ($remainingAmount > 0 && $startedTransaction) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                return ['allowed' => false, 'processed' => false, 'message' => '할인 쿠폰은 자산 결제와 함께 처리해야 합니다.'];
            }
            if ($remainingAmount <= 0 && empty($couponResult['already_entitled'])) {
                sr_community_grant_access_entitlement($pdo, $accountId, 'community.attachment', $attachmentId, 'attachment_download', 'coupon', '', (string) ($downloadConfig['charge_policy'] ?? 'once'), $couponDedupeKey);
            }
            if ($remainingAmount <= 0 && !empty($couponResult['processed'])) {
                $paymentItems = array_values(array_filter([
                    sr_community_payment_coupon_item($couponResult, $settlementCurrency),
                    sr_community_payment_access_item($accountId, 'community.attachment', $attachmentId, 'attachment_download', 'coupon', $couponDedupeKey),
                ]));
                sr_community_record_payment_ledger_if_available($pdo, [
                    'dedupe_key' => 'community.attachment.download:payment:coupon:' . (string) ($couponResult['coupon_redemption_id'] ?? ''),
                    'account_id' => $accountId,
                    'subject_module' => 'community',
                    'subject_type' => 'community.attachment.download',
                    'subject_id' => (string) $attachmentId,
                    'payment_kind' => 'purchase',
                    'payable_amount' => (int) $policyAmounts['amount'],
                    'settlement_amount' => 0,
                    'settlement_currency' => $settlementCurrency,
                    'description' => '커뮤니티 첨부 다운로드 쿠폰 결제',
                    'snapshot' => [
                        'charge_policy' => (string) ($downloadConfig['charge_policy'] ?? 'once'),
                        'coupon_covered_amount' => (int) $policyAmounts['amount'],
                    ],
                ], $paymentItems);
            }
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
            sr_log_exception($exception, 'community_attachment_coupon_entitlement_failed');
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

function sr_community_run_asset_event(PDO $pdo, array $config, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $direction, string $reason, bool $process = true, string $requestToken = '', bool $consumeConfirmationSession = true, bool $confirmedPost = false, bool $assetExchangeConfirmed = false, ?int $settlementAmountOverride = null, array $paymentContext = []): array
{
    return sr_community_asset_retry_operation($pdo, static function () use ($pdo, $config, $accountId, $eventKey, $subjectType, $subjectId, $direction, $reason, $process, $requestToken, $consumeConfirmationSession, $confirmedPost, $assetExchangeConfirmed, $settlementAmountOverride, $paymentContext): array {
        return sr_community_run_asset_event_once($pdo, $config, $accountId, $eventKey, $subjectType, $subjectId, $direction, $reason, $process, $requestToken, $consumeConfirmationSession, $confirmedPost, $assetExchangeConfirmed, $settlementAmountOverride, $paymentContext);
    });
}

function sr_community_run_asset_event_once(PDO $pdo, array $config, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $direction, string $reason, bool $process = true, string $requestToken = '', bool $consumeConfirmationSession = true, bool $confirmedPost = false, bool $assetExchangeConfirmed = false, ?int $settlementAmountOverride = null, array $paymentContext = []): array
{
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '', true);
    $assetModuleValue = sr_community_asset_module_value_from_keys($assetModules, true);
    $amounts = is_array($config['amounts'] ?? null) ? $config['amounts'] : [];
    $amount = $amounts !== [] ? sr_community_asset_amount_total($amounts) : (int) ($config['amount'] ?? 0);
    $chargePolicy = (string) ($config['charge_policy'] ?? 'once');

    if ($accountId <= 0 || $subjectId <= 0 || $assetModules === []) {
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
    $paymentPayableAmount = array_key_exists('payable_amount', $paymentContext)
        ? max(0, (int) $paymentContext['payable_amount'])
        : $amount;
    if ($direction === 'use' && $settlementAmountOverride !== null) {
        $amount = max(0, $settlementAmountOverride);
    }
    $policySnapshotJson = sr_community_asset_group_policy_snapshot_json($policyAmounts['snapshots']);
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, $config);
    $confirmationFingerprint = sr_community_asset_confirmation_fingerprint($eventKey, $subjectType, $chargePolicy, $assetModuleValue, $amount, $amounts, $policySnapshotJson, $settlementCurrency);
    $confirmationRequired = $direction === 'use'
        && in_array($eventKey, ['post_read', 'attachment_download'], true)
        && sr_community_asset_policy_requires_confirmation($chargePolicy);
    if ($confirmationRequired && !$process) {
        if ($consumeConfirmationSession && sr_community_consume_asset_confirmation_session($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint)) {
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

        $extra = sr_community_asset_settlement_exchange_confirmation_extra($pdo, $assetModules, $accountId, $amount, $settlementCurrency);

        return array_merge([
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_confirmation_required',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'confirmation_fingerprint' => $confirmationFingerprint,
            'confirmation_request_token' => sr_community_asset_confirmation_request_token($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint),
            'message' => (string) ($extra['message'] ?? sr_community_asset_confirmation_required_message()),
        ], $extra);
    } elseif ($confirmationRequired && $process && !$confirmedPost) {
        $extra = sr_community_asset_settlement_exchange_confirmation_extra($pdo, $assetModules, $accountId, $amount, $settlementCurrency);

        return array_merge([
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_confirmation_required',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'confirmation_fingerprint' => $confirmationFingerprint,
            'confirmation_request_token' => sr_community_asset_confirmation_request_token($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint),
            'message' => (string) ($extra['message'] ?? sr_community_asset_confirmation_required_message()),
        ], $extra);
    }

    if ($amount <= 0) {
        if ($direction === 'use') {
            $zeroAmountSuffix = match ($eventKey) {
                'post_read' => '게시글을 열람할 수 없습니다.',
                'attachment_download' => '첨부 파일을 다운로드할 수 없습니다.',
                'post_write_charge' => '글을 작성할 수 없습니다.',
                'comment_write_charge' => '댓글을 작성할 수 없습니다.',
                'message_send_charge' => '쪽지를 보낼 수 없습니다.',
                default => '처리할 수 없습니다.',
            };
            $configErrorMessage = sr_community_asset_settlement_config_error_message($pdo, $assetModules, $accountId, 0, $settlementCurrency, $zeroAmountSuffix);
            if ($configErrorMessage !== '') {
                return [
                    'allowed' => false,
                    'processed' => false,
                    'error_key' => 'asset_settlement_config_error',
                    'asset_module' => $assetModuleValue,
                    'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                    'amount' => $amount,
                    'message' => $configErrorMessage,
                ];
            }
        }

        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $stableRequestToken = preg_match('/\A[a-f0-9]{32}(?:[a-f0-9]{32})?\z/', $requestToken) === 1 ? $requestToken : bin2hex(random_bytes(16));
        $dedupeKey = $once
            ? sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId)
            : 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':0:' . $settlementCurrency . ':' . $stableRequestToken;
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

    $assetExchangeSuggestion = [];
    $allocations = $direction === 'use'
        ? sr_community_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency)
        : [['asset_module' => $assetModules[0], 'amount' => $amount]];
    if ($direction === 'use' && $allocations === []) {
        $assetExchangeSuggestion = sr_community_asset_settlement_exchange_suggestion($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
        if ($assetExchangeSuggestion !== [] && $confirmationRequired) {
            if (!$assetExchangeConfirmed) {
                return [
                    'allowed' => false,
                    'processed' => false,
                    'error_key' => 'asset_confirmation_required',
                    'asset_module' => $assetModuleValue,
                    'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                    'amount' => $amount,
                    'confirmation_fingerprint' => $confirmationFingerprint,
                    'confirmation_request_token' => sr_community_asset_confirmation_request_token($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint),
                    'asset_exchange_suggestion' => $assetExchangeSuggestion,
                    'asset_exchange_confirmation_required' => true,
                    'message' => sr_member_asset_settlement_exchange_message($pdo, sr_community_asset_modules($pdo), $assetExchangeSuggestion, sr_community_asset_confirmation_required_message()),
                ];
            }
        } else {
            $assetExchangeSuggestion = [];
        }
        $balanceLowSuffix = match ($eventKey) {
            'post_read' => '게시글을 열람할 수 없습니다.',
            'attachment_download' => '첨부 파일을 다운로드할 수 없습니다.',
            'post_write_charge' => '글을 작성할 수 없습니다.',
            'comment_write_charge' => '댓글을 작성할 수 없습니다.',
            'message_send_charge' => '쪽지를 보낼 수 없습니다.',
            default => '처리할 수 없습니다.',
        };
        if ($assetExchangeSuggestion === []) {
            return [
                'allowed' => false,
                'processed' => false,
                'error_key' => 'asset_balance_low',
                'asset_module' => $assetModuleValue,
                'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                'amount' => $amount,
                'confirmation_fingerprint' => $confirmationFingerprint,
                'message' => sr_community_asset_balance_shortage_message($pdo, $assetModules, $accountId, $amount, $settlementCurrency, $balanceLowSuffix, sr_t('community::action.error.asset_balance_low')),
            ];
        }
    }

    $processed = false;
    $processedLogs = [];
    $dedupeKey = '';
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $assetExchangeLogId = 0;
        if ($direction === 'use' && $assetExchangeSuggestion !== [] && $assetExchangeConfirmed) {
            $assetExchangeLogId = sr_member_asset_settlement_execute_exchange_suggestion($pdo, $assetExchangeSuggestion, $accountId);
            $allocations = sr_community_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
            if ($allocations === []) {
                throw new RuntimeException('Automatic asset exchange did not create a payable community settlement plan.');
            }
        }

        $pendingAssetEvents = [];
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) ($allocation['asset_amount'] ?? $allocation['amount']);
            $allocatedSettlementAmount = $direction === 'use' ? (int) ($allocation['settlement_amount'] ?? 0) : 0;
            $allocationSettlementCurrency = $direction === 'use' ? (string) ($allocation['settlement_currency'] ?? $settlementCurrency) : $settlementCurrency;
            $purchasePowerSnapshotJson = $direction === 'use' ? sr_community_asset_purchase_power_snapshot_json(is_array($allocation['purchase_power_snapshot'] ?? null) ? $allocation['purchase_power_snapshot'] : []) : '';
            $stableRequestToken = preg_match('/\A[a-f0-9]{32}(?:[a-f0-9]{32})?\z/', $requestToken) === 1 ? $requestToken : bin2hex(random_bytes(16));
            $dedupeKey = $once
                ? sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId)
                : 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':' . (string) max(0, $amount) . ':' . $settlementCurrency . ':' . $stableRequestToken;
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
                $existingLog = sr_community_asset_log($pdo, $dedupeKey);
                if (is_array($existingLog) && (string) ($existingLog['log_status'] ?? '') === sr_community_asset_log_status_completed()) {
                    $transactionId = (int) ($existingLog['transaction_id'] ?? 0);
                    if ($direction === 'use' && $transactionId > 0 && in_array($eventKey, ['post_read', 'attachment_download'], true)) {
                        sr_community_grant_access_entitlement($pdo, $accountId, $subjectType, $subjectId, $eventKey, 'asset', $assetModule, $chargePolicy, $assetModule . ':' . (string) $transactionId);
                    }
                    $processedLogs[] = [
                        'dedupe_key' => $dedupeKey,
                        'asset_module' => $assetModule,
                        'transaction_id' => $transactionId,
                        'amount' => (int) ($existingLog['amount'] ?? $allocatedAmount),
                        'settlement_amount' => (int) ($existingLog['settlement_amount'] ?? $allocatedSettlementAmount),
                        'settlement_currency' => (string) ($existingLog['settlement_currency'] ?? $allocationSettlementCurrency),
                    ];
                    continue;
                }
                throw new RuntimeException('Community asset event is still processing.');
            }

            $pendingAssetEvents[] = [
                'asset_module' => $assetModule,
                'amount' => $allocatedAmount,
                'dedupe_key' => $dedupeKey,
            ];
        }

        foreach ($pendingAssetEvents as $pendingAssetEvent) {
            $assetModule = (string) $pendingAssetEvent['asset_module'];
            $allocatedAmount = (int) $pendingAssetEvent['amount'];
            $dedupeKey = (string) $pendingAssetEvent['dedupe_key'];
            $module = sr_community_asset_modules($pdo)[$assetModule];
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
            $assetLog = sr_community_asset_log($pdo, $dedupeKey);
            $processedLogs[] = [
                'dedupe_key' => $dedupeKey,
                'asset_module' => $assetModule,
                'transaction_id' => $transactionId,
                'amount' => $allocatedAmount,
                'settlement_amount' => is_array($assetLog) ? (int) ($assetLog['settlement_amount'] ?? 0) : 0,
                'settlement_currency' => is_array($assetLog) ? (string) ($assetLog['settlement_currency'] ?? $settlementCurrency) : $settlementCurrency,
            ];
            $processed = true;
        }

        if ($direction === 'use' && $processed && $processedLogs !== []) {
            $paymentItems = [];
            $paymentDedupeParts = [];
            $couponResult = is_array($paymentContext['coupon_result'] ?? null) ? $paymentContext['coupon_result'] : [];
            $couponPaymentItem = sr_community_payment_coupon_item($couponResult, $settlementCurrency);
            if ($couponPaymentItem !== []) {
                $paymentItems[] = $couponPaymentItem;
                $paymentDedupeParts[] = 'coupon:' . (string) ($couponResult['coupon_redemption_id'] ?? '');
            }

            foreach ($processedLogs as $processedLog) {
                $logDedupeKey = (string) ($processedLog['dedupe_key'] ?? '');
                $assetModule = (string) ($processedLog['asset_module'] ?? '');
                $transactionId = (int) ($processedLog['transaction_id'] ?? 0);
                $allocatedAmount = max(0, (int) ($processedLog['amount'] ?? 0));
                $logSettlementAmount = max(0, (int) ($processedLog['settlement_amount'] ?? 0));
                $logSettlementCurrency = (string) ($processedLog['settlement_currency'] ?? $settlementCurrency);
                if ($logDedupeKey === '' || $assetModule === '' || $transactionId <= 0 || $allocatedAmount <= 0) {
                    continue;
                }

                $paymentDedupeParts[] = $logDedupeKey;
                $paymentItems[] = [
                    'item_kind' => 'asset_transaction',
                    'owner_module' => $assetModule,
                    'reference_type' => $assetModule . '_transaction',
                    'reference_id' => (string) $transactionId,
                    'amount' => -$allocatedAmount,
                    'currency_code' => $logSettlementCurrency,
                    'reversible' => true,
                    'snapshot' => [
                        'settlement_amount' => $logSettlementAmount,
                        'community_asset_dedupe_key' => $logDedupeKey,
                    ],
                ];

                $assetLog = sr_community_asset_log($pdo, $logDedupeKey);
                if (is_array($assetLog) && (int) ($assetLog['id'] ?? 0) > 0) {
                    $paymentItems[] = [
                        'item_kind' => 'asset_access_log',
                        'owner_module' => 'community',
                        'reference_type' => 'community_asset_log',
                        'reference_id' => (string) ((int) ($assetLog['id'] ?? 0)),
                        'amount' => $logSettlementAmount,
                        'currency_code' => $logSettlementCurrency,
                        'reversible' => true,
                        'snapshot' => [
                            'asset_module' => $assetModule,
                            'transaction_id' => $transactionId,
                            'dedupe_key' => $logDedupeKey,
                            'event_key' => $eventKey,
                        ],
                    ];
                }
            }

            if ($paymentDedupeParts !== []) {
                if (in_array($eventKey, ['post_read', 'attachment_download'], true)) {
                    $sourceReference = implode(',', $paymentDedupeParts);
                    $sourceKind = $couponPaymentItem !== [] ? 'mixed' : 'asset';
                    $paymentItems[] = sr_community_payment_access_item($accountId, $subjectType, $subjectId, $eventKey, $sourceKind, $sourceReference);
                }

                sr_community_record_payment_ledger_if_available($pdo, [
                    'dedupe_key' => sr_community_payment_subject_type($eventKey, $subjectType) . ':payment:' . sha1(implode('|', $paymentDedupeParts)),
                    'account_id' => $accountId,
                    'subject_module' => 'community',
                    'subject_type' => sr_community_payment_subject_type($eventKey, $subjectType),
                    'subject_id' => (string) $subjectId,
                    'payment_kind' => 'purchase',
                    'payable_amount' => $paymentPayableAmount,
                    'settlement_amount' => $amount,
                    'settlement_currency' => $settlementCurrency,
                    'description' => sr_community_payment_description($eventKey),
                    'snapshot' => [
                        'charge_policy' => $chargePolicy,
                        'coupon_discount_amount' => (int) ($couponResult['discount_amount'] ?? 0),
                        'remaining_amount' => $amount,
                        'asset_exchange_log_id' => $assetExchangeLogId ?? 0,
                    ],
                ], $paymentItems);
            }
        }

        if ($pendingAssetEvents === [] && $processedLogs !== []) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return [
                'allowed' => true,
                'processed' => false,
                'already_processed' => true,
                'asset_module' => $assetModuleValue,
                'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                'amount' => $amount,
                'direction' => $direction,
                'logs' => $processedLogs,
                'message' => '',
            ];
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
        'asset_exchange_log_id' => $assetExchangeLogId ?? 0,
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

function sr_community_asset_grant_log_for_reversal(PDO $pdo, int $accountId, string $grantEventKey, int $subjectId): ?array
{
    foreach (array_keys(sr_community_asset_modules($pdo)) as $assetModule) {
        $original = sr_community_asset_log($pdo, sr_community_asset_dedupe_key((string) $assetModule, $accountId, $grantEventKey, $subjectId));
        if (!is_array($original)
            || (int) ($original['transaction_id'] ?? 0) < 1
            || (string) ($original['direction'] ?? '') !== 'grant'
            || (string) ($original['log_status'] ?? sr_community_asset_log_status_completed()) !== sr_community_asset_log_status_completed()
        ) {
            continue;
        }

        return $original;
    }

    return null;
}

function sr_community_asset_recovery_failure_by_original(PDO $pdo, int $originalAssetLogId, string $reversalEventKey): ?array
{
    if (!sr_community_asset_recovery_failures_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_asset_recovery_failures
         WHERE original_asset_log_id = :original_asset_log_id
           AND reversal_event_key = :reversal_event_key
         LIMIT 1'
    );
    $stmt->execute([
        'original_asset_log_id' => $originalAssetLogId,
        'reversal_event_key' => $reversalEventKey,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_asset_recovery_failures_table_exists(PDO $pdo): bool
{
    static $existsByConnection = [];

    $connectionKey = (string) spl_object_id($pdo);
    if (array_key_exists($connectionKey, $existsByConnection)) {
        return $existsByConnection[$connectionKey];
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_community_asset_recovery_failures LIMIT 1');
        $existsByConnection[$connectionKey] = $stmt !== false;
    } catch (Throwable $exception) {
        $existsByConnection[$connectionKey] = false;
    }

    return $existsByConnection[$connectionKey];
}

function sr_community_asset_completed_reversal_amount(PDO $pdo, array $originalLog, string $reversalEventKey): int
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS amount_value
         FROM sr_community_asset_logs
         WHERE account_id = :account_id
           AND asset_module = :asset_module
           AND subject_type = :subject_type
           AND subject_id = :subject_id
           AND event_key = :event_key
           AND direction = \'use\'
           AND log_status = \'completed\'
           AND transaction_id > 0'
    );
    $stmt->execute([
        'account_id' => (int) ($originalLog['account_id'] ?? 0),
        'asset_module' => (string) ($originalLog['asset_module'] ?? ''),
        'subject_type' => (string) ($originalLog['subject_type'] ?? ''),
        'subject_id' => (int) ($originalLog['subject_id'] ?? 0),
        'event_key' => $reversalEventKey,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? max(0, (int) ($row['amount_value'] ?? 0)) : 0;
}

function sr_community_asset_recovery_context_json(array $operationContext): string
{
    $allowedKeys = [
        'operation_event_key',
        'before_status',
        'after_status',
        'actor_type',
        'route_context',
        'batch_operation_key',
    ];
    $context = [];
    foreach ($allowedKeys as $key) {
        if (!array_key_exists($key, $operationContext)) {
            continue;
        }
        $value = $operationContext[$key];
        if (is_scalar($value) || $value === null) {
            $context[$key] = mb_substr((string) $value, 0, 120);
        }
    }
    $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return is_string($encoded) ? $encoded : '{}';
}

function sr_community_asset_recovery_canonical_event_key(string $eventKey, string $subjectType, string $purpose): string
{
    $subjectDomain = match ($subjectType) {
        'community.comment' => 'comment',
        default => 'post',
    };
    $purposeName = match ($purpose) {
        'reversal' => 'reward_reversal',
        default => 'reward_grant',
    };
    $canonical = 'community.' . $subjectDomain . '.' . $purposeName;

    return function_exists('sr_asset_recovery_event_key_valid') && sr_asset_recovery_event_key_valid($eventKey) ? $eventKey : $canonical;
}

function sr_community_asset_recovery_legacy_event_key(string $eventKey, string $subjectType, string $purpose): string
{
    if ($eventKey === 'community.post.reward_grant') {
        return 'post_reward';
    }
    if ($eventKey === 'community.comment.reward_grant') {
        return 'comment_reward';
    }
    if ($eventKey === 'community.post.reward_reversal') {
        return 'post_reward_reversal';
    }
    if ($eventKey === 'community.comment.reward_reversal') {
        return 'comment_reward_reversal';
    }

    if (!str_contains($eventKey, '.')) {
        return $eventKey;
    }

    $subjectDomain = $subjectType === 'community.comment' ? 'comment' : 'post';
    if ($purpose === 'reversal') {
        return $subjectDomain . '_reward_reversal';
    }

    return $subjectDomain . '_reward';
}

function sr_community_asset_recovery_savepoint_name(): string
{
    static $counter = 0;
    $counter++;

    return 'sr_community_recovery_' . (string) $counter;
}

function sr_community_asset_recovery_upsert(PDO $pdo, array $originalLog, string $subjectType, int $subjectId, string $grantEventKey, string $reversalEventKey, int $recoveredAmount, string $failureReason, array $operationContext = []): int
{
    if (!sr_community_asset_recovery_failures_table_exists($pdo)) {
        throw new RuntimeException('Community asset recovery failure table is not available.');
    }

    $attemptedAmount = max(0, (int) ($originalLog['amount'] ?? 0));
    $recoveredAmount = max(0, min($attemptedAmount, $recoveredAmount));
    $unrecoveredAmount = max(0, $attemptedAmount - $recoveredAmount);
    $status = $unrecoveredAmount > 0 ? 'open' : 'recovered';
    $now = sr_now();
    $canonicalGrantEventKey = sr_community_asset_recovery_canonical_event_key($grantEventKey, $subjectType, 'grant');
    $canonicalReversalEventKey = sr_community_asset_recovery_canonical_event_key($reversalEventKey, $subjectType, 'reversal');
    $operationEventKey = mb_substr((string) ($operationContext['operation_event_key'] ?? ''), 0, 80);
    $actorAccountIdValue = isset($operationContext['actor_account_id']) ? (int) $operationContext['actor_account_id'] : 0;
    $actorAccountId = $actorAccountIdValue > 0 ? $actorAccountIdValue : null;
    $actorType = mb_substr((string) ($operationContext['actor_type'] ?? ''), 0, 30);
    $contextJson = sr_community_asset_recovery_context_json($operationContext);

    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable $exception) {
        $driver = '';
    }

    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_community_asset_recovery_failures
                (account_id, asset_module, original_asset_log_id, original_transaction_id, subject_type, subject_id,
                 grant_event_key, reversal_event_key, operation_event_key, attempted_amount, recovered_amount,
                 unrecovered_amount, failure_reason, status, actor_account_id, actor_type, operation_context_json,
                 attempt_count, created_at, updated_at, last_attempted_at, resolved_at)
             VALUES
                (:account_id, :asset_module, :original_asset_log_id, :original_transaction_id, :subject_type, :subject_id,
                 :grant_event_key, :reversal_event_key, :operation_event_key, :attempted_amount, :recovered_amount,
                 :unrecovered_amount, :failure_reason, :status, :actor_account_id, :actor_type, :operation_context_json,
                 1, :created_at, :updated_at, :last_attempted_at, :resolved_at)
             ON CONFLICT(original_asset_log_id, reversal_event_key) DO UPDATE SET
                 recovered_amount = CASE WHEN status = \'open\' THEN MAX(recovered_amount, excluded.recovered_amount) ELSE recovered_amount END,
                 unrecovered_amount = CASE WHEN status = \'open\' THEN excluded.attempted_amount - MAX(recovered_amount, excluded.recovered_amount) ELSE unrecovered_amount END,
                 failure_reason = CASE WHEN status = \'open\' THEN excluded.failure_reason ELSE failure_reason END,
                 actor_account_id = CASE WHEN status = \'open\' THEN excluded.actor_account_id ELSE actor_account_id END,
                 actor_type = CASE WHEN status = \'open\' THEN excluded.actor_type ELSE actor_type END,
                 operation_context_json = CASE WHEN status = \'open\' THEN excluded.operation_context_json ELSE operation_context_json END,
                 attempt_count = CASE WHEN status = \'open\' THEN attempt_count + 1 ELSE attempt_count END,
                 updated_at = CASE WHEN status = \'open\' THEN excluded.updated_at ELSE updated_at END,
                 last_attempted_at = CASE WHEN status = \'open\' THEN excluded.last_attempted_at ELSE last_attempted_at END,
                 resolved_at = CASE WHEN status = \'open\' AND excluded.attempted_amount - MAX(recovered_amount, excluded.recovered_amount) <= 0 THEN excluded.updated_at ELSE resolved_at END,
                 status = CASE WHEN status = \'open\' AND excluded.attempted_amount - MAX(recovered_amount, excluded.recovered_amount) <= 0 THEN \'recovered\' ELSE status END'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_community_asset_recovery_failures
                (account_id, asset_module, original_asset_log_id, original_transaction_id, subject_type, subject_id,
                 grant_event_key, reversal_event_key, operation_event_key, attempted_amount, recovered_amount,
                 unrecovered_amount, failure_reason, status, actor_account_id, actor_type, operation_context_json,
                 attempt_count, created_at, updated_at, last_attempted_at, resolved_at)
             VALUES
                (:account_id, :asset_module, :original_asset_log_id, :original_transaction_id, :subject_type, :subject_id,
                 :grant_event_key, :reversal_event_key, :operation_event_key, :attempted_amount, :recovered_amount,
                 :unrecovered_amount, :failure_reason, :status, :actor_account_id, :actor_type, :operation_context_json,
                 1, :created_at, :updated_at, :last_attempted_at, :resolved_at)
             ON DUPLICATE KEY UPDATE
                 recovered_amount = IF(status = \'open\', GREATEST(recovered_amount, VALUES(recovered_amount)), recovered_amount),
                 unrecovered_amount = IF(status = \'open\', GREATEST(0, attempted_amount - GREATEST(recovered_amount, VALUES(recovered_amount))), unrecovered_amount),
                 failure_reason = IF(status = \'open\', VALUES(failure_reason), failure_reason),
                 actor_account_id = IF(status = \'open\', VALUES(actor_account_id), actor_account_id),
                 actor_type = IF(status = \'open\', VALUES(actor_type), actor_type),
                 operation_context_json = IF(status = \'open\', VALUES(operation_context_json), operation_context_json),
                 attempt_count = IF(status = \'open\', attempt_count + 1, attempt_count),
                 updated_at = IF(status = \'open\', VALUES(updated_at), updated_at),
                 last_attempted_at = IF(status = \'open\', VALUES(last_attempted_at), last_attempted_at),
                 resolved_at = IF(status = \'open\' AND attempted_amount - GREATEST(recovered_amount, VALUES(recovered_amount)) <= 0, VALUES(updated_at), resolved_at),
                 status = IF(status = \'open\' AND attempted_amount - GREATEST(recovered_amount, VALUES(recovered_amount)) <= 0, \'recovered\', status)'
        );
    }

    $stmt->execute([
        'account_id' => (int) ($originalLog['account_id'] ?? 0),
        'asset_module' => (string) ($originalLog['asset_module'] ?? ''),
        'original_asset_log_id' => (int) ($originalLog['id'] ?? 0),
        'original_transaction_id' => (int) ($originalLog['transaction_id'] ?? 0),
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'grant_event_key' => $grantEventKey,
        'reversal_event_key' => $reversalEventKey,
        'operation_event_key' => $operationEventKey,
        'attempted_amount' => $attemptedAmount,
        'recovered_amount' => $recoveredAmount,
        'unrecovered_amount' => $unrecoveredAmount,
        'failure_reason' => $failureReason,
        'status' => $status,
        'actor_account_id' => $actorAccountId,
        'actor_type' => $actorType,
        'operation_context_json' => $contextJson,
        'created_at' => $now,
        'updated_at' => $now,
        'last_attempted_at' => $now,
        'resolved_at' => $status === 'recovered' ? $now : null,
    ]);

    if (function_exists('sr_asset_recovery_record_failure') && sr_asset_recovery_failures_table_exists($pdo)) {
        sr_asset_recovery_record_failure($pdo, [
            'source_module' => 'community',
            'source_log_id' => (int) ($originalLog['id'] ?? 0),
            'asset_module' => (string) ($originalLog['asset_module'] ?? ''),
            'account_id' => (int) ($originalLog['account_id'] ?? 0),
            'original_transaction_id' => (int) ($originalLog['transaction_id'] ?? 0),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'grant_event_key' => $canonicalGrantEventKey,
            'reversal_event_key' => $canonicalReversalEventKey,
            'attempted_amount' => $attemptedAmount,
            'recovered_amount' => $recoveredAmount,
            'failure_reason' => $failureReason === 'resolved' ? 'recovered' : $failureReason,
            'operation_context' => $operationContext,
        ]);
    }

    $row = sr_community_asset_recovery_failure_by_original($pdo, (int) ($originalLog['id'] ?? 0), $reversalEventKey);
    return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
}

function sr_community_reverse_asset_grant_for_operation(PDO $pdo, int $accountId, string $grantEventKey, string $subjectType, int $subjectId, string $reversalEventKey, string $canonicalReversalEventKey, string $reason, array $operationContext = []): array
{
    $legacyGrantEventKey = sr_community_asset_recovery_legacy_event_key($grantEventKey, $subjectType, 'grant');
    $legacyReversalEventKey = sr_community_asset_recovery_legacy_event_key($reversalEventKey, $subjectType, 'reversal');
    $canonicalReversalEventKey = sr_community_asset_recovery_canonical_event_key($canonicalReversalEventKey, $subjectType, 'reversal');

    $original = sr_community_asset_grant_log_for_reversal($pdo, $accountId, $legacyGrantEventKey, $subjectId);
    if (!is_array($original)) {
        return ['operation_allowed' => true, 'recovery_status' => 'not_needed', 'recovery_failure_id' => 0, 'processed' => false, 'message' => ''];
    }

    $attemptedAmount = max(0, (int) ($original['amount'] ?? 0));
    $existingFailure = sr_community_asset_recovery_failure_by_original($pdo, (int) ($original['id'] ?? 0), $legacyReversalEventKey);
    if (is_array($existingFailure) && in_array((string) ($existingFailure['status'] ?? ''), ['recovered', 'resolved', 'manually_resolved', 'cancelled'], true)) {
        return ['operation_allowed' => true, 'recovery_status' => 'not_needed', 'recovery_failure_id' => (int) ($existingFailure['id'] ?? 0), 'processed' => false, 'message' => ''];
    }

    $alreadyRecoveredAmount = sr_community_asset_completed_reversal_amount($pdo, $original, $canonicalReversalEventKey);
    if ($attemptedAmount <= 0 || $alreadyRecoveredAmount >= $attemptedAmount) {
        if (is_array($existingFailure) && (string) ($existingFailure['status'] ?? '') === 'open') {
            $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $attemptedAmount, 'recovered', $operationContext);
            return ['operation_allowed' => true, 'recovery_status' => 'completed', 'recovery_failure_id' => $failureId, 'processed' => false, 'message' => ''];
        }

        return ['operation_allowed' => true, 'recovery_status' => 'not_needed', 'recovery_failure_id' => 0, 'processed' => false, 'message' => ''];
    }

    $isManualRetry = (string) ($operationContext['operation_event_key'] ?? '') === 'manual_retry';
    if (!$isManualRetry && is_array($existingFailure) && (string) ($existingFailure['status'] ?? '') === 'open') {
        $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $alreadyRecoveredAmount, 'balance_low', $operationContext);
        return ['operation_allowed' => true, 'recovery_status' => 'unrecovered', 'recovery_failure_id' => $failureId, 'processed' => false, 'message' => ''];
    }

    $remainingAmount = max(0, $attemptedAmount - $alreadyRecoveredAmount);
    $availableAmount = sr_community_asset_balance($pdo, (string) ($original['asset_module'] ?? ''), $accountId);
    $recoverableAmount = min($remainingAmount, max(0, $availableAmount));
    if ($recoverableAmount <= 0) {
        $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $alreadyRecoveredAmount, 'balance_low', $operationContext);
        return ['operation_allowed' => true, 'recovery_status' => 'unrecovered', 'recovery_failure_id' => $failureId, 'processed' => false, 'error_key' => 'asset_balance_low', 'message' => ''];
    }

    $savepoint = sr_community_asset_recovery_savepoint_name();
    $pdo->exec('SAVEPOINT ' . $savepoint);
    try {
        $config = sr_community_asset_reversal_config($original);
        $config['amount'] = $recoverableAmount;
        $config['charge_policy'] = 'every_action';
        $result = sr_community_run_asset_event($pdo, $config, $accountId, $canonicalReversalEventKey, $subjectType, $subjectId, 'use', $reason, true, bin2hex(random_bytes(16)));
        if (!empty($result['allowed'])) {
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            $totalRecovered = $alreadyRecoveredAmount + $recoverableAmount;
            if ($totalRecovered >= $attemptedAmount) {
                $failureId = 0;
                if (is_array($existingFailure) && (string) ($existingFailure['status'] ?? '') === 'open') {
                    $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $totalRecovered, 'recovered', $operationContext);
                }
                if ($failureId > 0 && function_exists('sr_asset_recovery_record_reversal_link')) {
                    $commonFailure = sr_asset_recovery_failure_by_dedupe_key($pdo, sr_asset_recovery_dedupe_key('community', (int) ($original['id'] ?? 0), sr_community_asset_recovery_canonical_event_key($canonicalReversalEventKey, $subjectType, 'reversal')));
                    $commonFailureId = is_array($commonFailure) ? (int) ($commonFailure['id'] ?? 0) : 0;
                    foreach ((array) ($result['processed_logs'] ?? []) as $processedLog) {
                        sr_asset_recovery_record_reversal_link($pdo, $commonFailureId, (string) ($processedLog['asset_module'] ?? ''), (int) ($processedLog['transaction_id'] ?? 0), (int) ($processedLog['amount'] ?? 0));
                    }
                }
                return ['operation_allowed' => true, 'recovery_status' => 'completed', 'recovery_failure_id' => $failureId, 'processed' => true, 'message' => ''];
            }

            $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $totalRecovered, 'balance_low', $operationContext);
            if ($failureId > 0 && function_exists('sr_asset_recovery_record_reversal_link')) {
                $commonFailure = sr_asset_recovery_failure_by_dedupe_key($pdo, sr_asset_recovery_dedupe_key('community', (int) ($original['id'] ?? 0), sr_community_asset_recovery_canonical_event_key($canonicalReversalEventKey, $subjectType, 'reversal')));
                $commonFailureId = is_array($commonFailure) ? (int) ($commonFailure['id'] ?? 0) : 0;
                foreach ((array) ($result['processed_logs'] ?? []) as $processedLog) {
                    sr_asset_recovery_record_reversal_link($pdo, $commonFailureId, (string) ($processedLog['asset_module'] ?? ''), (int) ($processedLog['transaction_id'] ?? 0), (int) ($processedLog['amount'] ?? 0));
                }
            }
            return ['operation_allowed' => true, 'recovery_status' => 'unrecovered', 'recovery_failure_id' => $failureId, 'processed' => true, 'message' => ''];
        }

        $errorKey = (string) ($result['error_key'] ?? '');
        if ($errorKey === 'asset_balance_low') {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $alreadyRecoveredAmount, 'balance_low', $operationContext);
            return ['operation_allowed' => true, 'recovery_status' => 'unrecovered', 'recovery_failure_id' => $failureId, 'processed' => false, 'error_key' => 'asset_balance_low', 'message' => ''];
        }

        $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        return ['operation_allowed' => false, 'recovery_status' => 'failed', 'recovery_failure_id' => 0, 'processed' => false, 'error_key' => $errorKey !== '' ? $errorKey : 'asset_processing_failed', 'message' => (string) ($result['message'] ?? '')];
    } catch (Throwable $exception) {
        if (sr_community_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        return ['operation_allowed' => false, 'recovery_status' => 'failed', 'recovery_failure_id' => 0, 'processed' => false, 'error_key' => 'asset_processing_failed', 'message' => $exception->getMessage()];
    }
}

function sr_community_reverse_asset_grant(PDO $pdo, int $accountId, string $grantEventKey, string $subjectType, int $subjectId, string $reversalEventKey, string $reason): array
{
    $original = sr_community_asset_grant_log_for_reversal($pdo, $accountId, $grantEventKey, $subjectId);
    if (is_array($original)) {
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

function sr_community_asset_recovery_failure_by_id_for_update(PDO $pdo, int $failureId): ?array
{
    if (!sr_community_asset_recovery_failures_table_exists($pdo)) {
        return null;
    }

    $lockSql = '';
    try {
        $lockSql = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    } catch (Throwable $exception) {
        $lockSql = '';
    }

    $stmt = $pdo->prepare(
        'SELECT f.*
         FROM sr_community_asset_recovery_failures f
         WHERE f.id = :id
         LIMIT 1' . $lockSql
    );
    $stmt->execute(['id' => $failureId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_asset_recovery_failure_where(array $filters, array &$params): string
{
    $conditions = ['1 = 1'];
    if ((string) ($filters['status'] ?? '') !== '') {
        $conditions[] = 'f.status = :status';
        $params['status'] = (string) $filters['status'];
    }
    if ((string) ($filters['asset_module'] ?? '') !== '') {
        $conditions[] = 'f.asset_module = :asset_module';
        $params['asset_module'] = (string) $filters['asset_module'];
    }
    if ((string) ($filters['subject_type'] ?? '') !== '') {
        $conditions[] = 'f.subject_type = :subject_type';
        $params['subject_type'] = (string) $filters['subject_type'];
    }
    if ((int) ($filters['subject_id'] ?? 0) > 0) {
        $conditions[] = 'f.subject_id = :subject_id';
        $params['subject_id'] = (int) $filters['subject_id'];
    }
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $conditions[] = "(f.account_id = :keyword_id OR ma.email LIKE :keyword_like ESCAPE '\\\\' OR ma.display_name LIKE :keyword_like ESCAPE '\\\\')";
        $params['keyword_id'] = preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0;
        $params['keyword_like'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    }
    foreach (['created_from' => '>=', 'created_to' => '<='] as $key => $operator) {
        $value = (string) ($filters[$key] ?? '');
        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) === 1) {
            $conditions[] = 'f.created_at ' . $operator . ' :' . $key;
            $params[$key] = $key === 'created_to' ? $value . ' 23:59:59' : $value . ' 00:00:00';
        }
    }

    return implode(' AND ', $conditions);
}

function sr_community_asset_recovery_failures(PDO $pdo, array $filters, int $limit, int $offset): array
{
    if (!sr_community_asset_recovery_failures_table_exists($pdo)) {
        return [];
    }

    $params = [];
    $where = sr_community_asset_recovery_failure_where($filters, $params);
    $stmt = $pdo->prepare(
        'SELECT f.*, ma.email AS account_email, ma.display_name AS account_display_name
         FROM sr_community_asset_recovery_failures f
         LEFT JOIN sr_member_accounts ma ON ma.id = f.account_id
         WHERE ' . $where . '
         ORDER BY f.updated_at DESC, f.id DESC
         LIMIT ' . max(1, min(200, $limit)) . ' OFFSET ' . max(0, $offset)
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}
