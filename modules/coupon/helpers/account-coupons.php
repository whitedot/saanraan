<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_coupon_update_issue_status(PDO $pdo, int $issueId, string $status, ?int $updatedByAccountId = null): void
{
    if ($issueId <= 0 || !in_array($status, sr_coupon_issue_statuses(), true)) {
        throw new InvalidArgumentException('Coupon issue status is invalid.');
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_coupon_issues
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $issueId,
    ]);

    sr_coupon_notify_issue_event($pdo, $issueId, 'issue.status_updated', $updatedByAccountId, [
        'status_label' => sr_coupon_issue_status_label($status),
    ]);
}

function sr_coupon_issue_status_label(string $status): string
{
    $labels = [
        'active' => '사용 가능',
        'used' => '사용 완료',
        'expired' => '만료',
        'revoked' => '지급 취소',
        'withdrawn_expired' => '탈퇴 만료',
        'refund_requested' => '환급 요청',
        'refunded' => '환급 완료',
    ];

    return $labels[$status] ?? $status;
}

function sr_coupon_redemption_status_label(string $status): string
{
    $labels = [
        'redeemed' => '사용 완료',
        'refunded' => '환불 완료',
    ];

    return $labels[$status] ?? $status;
}

function sr_coupon_active_account_issues(PDO $pdo, int $accountId, int $limit = 100, int $offset = 0): array
{
    if ($accountId <= 0 || !sr_coupon_usage_enabled($pdo)) {
        return [];
    }

    sr_coupon_expire_active_issues($pdo, $accountId);

    $limit = max(1, min(300, $limit));
    $offset = max(0, $offset);
    $discountColumns = sr_coupon_definition_discount_columns_available($pdo)
        ? 'd.discount_amount, d.discount_percent, d.discount_currency_code'
        : '0 AS discount_amount, 0 AS discount_percent, \'\' AS discount_currency_code';
    $stmt = $pdo->prepare(
        "SELECT i.*, d.coupon_key, d.title, d.description, d.coupon_type, " . $discountColumns . ", d.target_type, d.target_id, d.refundable_policy, d.max_uses_per_issue
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'
           AND d.status IN ('active', 'issue_stopped')
           AND (i.expires_at IS NULL OR i.expires_at >= :now_value)
         ORDER BY i.id DESC
         LIMIT " . $limit . " OFFSET " . $offset
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now_value' => sr_now(),
    ]);

    return $stmt->fetchAll();
}

function sr_coupon_active_account_target_issues(PDO $pdo, int $accountId, string $targetType, string $targetId, int $limit = 20): array
{
    if ($accountId <= 0 || $targetType === '' || $targetId === '' || !sr_coupon_usage_enabled($pdo) || !sr_coupon_tables_available($pdo)) {
        return [];
    }

    sr_coupon_expire_active_issues($pdo, $accountId);

    $limit = max(1, min(100, $limit));
    $discountColumns = sr_coupon_definition_discount_columns_available($pdo)
        ? 'd.discount_amount, d.discount_percent, d.discount_currency_code'
        : '0 AS discount_amount, 0 AS discount_percent, \'\' AS discount_currency_code';
    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT i.*, d.coupon_key, d.title, d.description, d.coupon_type, " . $discountColumns . ", d.target_type, d.target_id, d.refundable_policy, d.max_uses_per_issue
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'
           AND d.status IN ('active', 'issue_stopped')
           AND (i.starts_at IS NULL OR i.starts_at <= :starts_now_value)
           AND (i.expires_at IS NULL OR i.expires_at >= :expires_now_value)
           AND (
                d.target_type = 'all'
                OR (d.target_type = :target_type AND (d.target_id = '' OR d.target_id = :target_id))
           )
         ORDER BY i.id DESC
         LIMIT " . $limit
    );
    $stmt->execute([
        'account_id' => $accountId,
        'starts_now_value' => $now,
        'expires_now_value' => $now,
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);

    return $stmt->fetchAll();
}

function sr_coupon_active_account_issue_count(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0 || !sr_coupon_usage_enabled($pdo) || !sr_coupon_tables_available($pdo)) {
        return 0;
    }

    sr_coupon_expire_active_issues($pdo, $accountId);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'
           AND d.status IN ('active', 'issue_stopped')
           AND (i.expires_at IS NULL OR i.expires_at >= :now_value)"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now_value' => sr_now(),
    ]);

    return (int) $stmt->fetchColumn();
}

function sr_coupon_usable_account_issue_count(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0 || !sr_coupon_usage_enabled($pdo) || !sr_coupon_tables_available($pdo)) {
        return 0;
    }

    sr_coupon_expire_active_issues($pdo, $accountId);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'
           AND d.status IN ('active', 'issue_stopped')
           AND (i.starts_at IS NULL OR i.starts_at <= :starts_now_value)
           AND (i.expires_at IS NULL OR i.expires_at >= :expires_now_value)"
    );
    $now = sr_now();
    $stmt->execute([
        'account_id' => $accountId,
        'starts_now_value' => $now,
        'expires_now_value' => $now,
    ]);

    return (int) $stmt->fetchColumn();
}

function sr_coupon_tables_available(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_coupon_issues LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_coupon_definitions LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_coupon_redemptions LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function sr_coupon_table_available(PDO $pdo, string $tableName): bool
{
    if (preg_match('/\Asr_coupon_[a-z0-9_]+\z/', $tableName) !== 1) {
        return false;
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function sr_coupon_issue_matches_target(array $issue, string $targetType, string $targetId): bool
{
    $definitionTargetType = (string) ($issue['target_type'] ?? '');
    $definitionTargetId = (string) ($issue['target_id'] ?? '');
    if ($definitionTargetType === 'all') {
        return true;
    }

    return $definitionTargetType === $targetType
        && ($definitionTargetId === '' || $definitionTargetId === $targetId);
}

function sr_coupon_has_redemption(PDO $pdo, int $accountId, string $dedupeKey): bool
{
    if ($accountId <= 0 || $dedupeKey === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM sr_coupon_redemptions
         WHERE account_id = :account_id
           AND dedupe_key = :dedupe_key
           AND status = 'redeemed'
         LIMIT 1"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'dedupe_key' => $dedupeKey,
    ]);

    return is_array($stmt->fetch());
}

function sr_coupon_redemption_refund_columns_available(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $stmt = $pdo->query('SELECT refunded_at, refunded_by_account_id, refund_note FROM sr_coupon_redemptions LIMIT 1');
        $available = $stmt !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function sr_coupon_redemption_pricing_columns_available(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $stmt = $pdo->query('SELECT amount, currency_code, asset_unit, policy_summary, priced_at, target_snapshot_json FROM sr_coupon_redemptions LIMIT 1');
        $available = $stmt !== false;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function sr_coupon_issue_claim_columns_available(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $stmt = $pdo->query('SELECT claim_type, claim_campaign_id, claim_log_id, nominal_price_amount, nominal_price_currency_code, asset_reference_module, asset_reference_type, asset_reference_id, claim_snapshot_json FROM sr_coupon_issues LIMIT 1');
        $available = $stmt !== false;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function sr_coupon_redemption_pricing_snapshot_from_result(array $pricing, string $targetType, string $targetId): array
{
    if (empty($pricing['ok'])) {
        return [
            'amount' => 0,
            'currency_code' => '',
            'asset_unit' => '',
            'policy_summary' => '',
            'priced_at' => null,
            'target_snapshot_json' => null,
        ];
    }

    $snapshot = [
        'target_type' => (string) ($pricing['target_type'] ?? $targetType),
        'target_id' => (string) ($pricing['target_id'] ?? $targetId),
        'amount' => sr_coupon_nonnegative_int_or_null($pricing['price_amount'] ?? 0) ?? 0,
        'currency_code' => sr_coupon_clean_currency_code((string) ($pricing['currency_code'] ?? '')),
        'asset_unit' => sr_coupon_clean_key((string) ($pricing['asset_unit'] ?? ''), 40),
        'is_free' => !empty($pricing['is_free']),
        'already_entitled' => !empty($pricing['already_entitled']),
        'policy_summary' => sr_coupon_clean_text((string) ($pricing['policy_summary'] ?? ''), 255),
        'priced_at' => sr_coupon_clean_text((string) ($pricing['priced_at'] ?? sr_now()), 30),
    ];
    foreach (['coupon_type', 'discount_amount', 'remaining_amount'] as $optionalKey) {
        if (array_key_exists($optionalKey, $pricing)) {
            $amount = $optionalKey === 'coupon_type' ? null : sr_coupon_nonnegative_int_or_null($pricing[$optionalKey]);
            $snapshot[$optionalKey] = $optionalKey === 'coupon_type'
                ? sr_coupon_clean_key((string) $pricing[$optionalKey], 40)
                : ($amount ?? 0);
        }
    }

    return [
        'amount' => $snapshot['amount'],
        'currency_code' => $snapshot['currency_code'],
        'asset_unit' => $snapshot['asset_unit'],
        'policy_summary' => $snapshot['policy_summary'],
        'priced_at' => $snapshot['priced_at'],
        'target_snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ];
}

function sr_coupon_admin_redemption_count(PDO $pdo, array $runtimeConfig, array $filters = []): int
{
    $where = [];
    $params = [];

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['refundable_policy'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('d.refundable_policy', 'refundable_policy', $filters['refundable_policy']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $couponKeyword = sr_coupon_clean_text((string) ($filters['coupon_q'] ?? ''), 120);
    if ($couponKeyword !== '') {
        $where[] = "(d.coupon_key LIKE :coupon_keyword_like ESCAPE '\\\\' OR d.title LIKE :coupon_keyword_like ESCAPE '\\\\')";
        $params['coupon_keyword_like'] = sr_coupon_like_keyword($couponKeyword);
    }

    $accountFilter = is_array($filters['account'] ?? null) ? $filters['account'] : [];
    $accountId = (int) ($accountFilter['account_id'] ?? 0);
    if ($accountId > 0) {
        $where[] = 'r.account_id = :account_id';
        $params['account_id'] = $accountId;
    } elseif (trim((string) ($accountFilter['keyword'] ?? '')) !== '') {
        $where[] = '1 = 0';
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_coupon_redemptions r
         INNER JOIN sr_coupon_definitions d ON d.id = r.coupon_definition_id
         INNER JOIN sr_coupon_issues i ON i.id = r.coupon_issue_id
         LEFT JOIN sr_member_accounts a ON a.id = r.account_id'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_coupon_admin_redemptions(PDO $pdo, array $runtimeConfig, int $limit = 100, array $filters = [], array $sort = [], int $offset = 0): array
{
    $limit = max(1, min(300, $limit));
    $offset = max(0, $offset);
    $refundColumns = sr_coupon_redemption_refund_columns_available($pdo)
        ? 'r.refunded_at, r.refunded_by_account_id, r.refund_note'
        : 'NULL AS refunded_at, NULL AS refunded_by_account_id, \'\' AS refund_note';
    $pricingColumns = sr_coupon_redemption_pricing_columns_available($pdo)
        ? 'r.amount, r.currency_code, r.asset_unit, r.policy_summary, r.priced_at'
        : '0 AS amount, \'\' AS currency_code, \'\' AS asset_unit, \'\' AS policy_summary, NULL AS priced_at';
    $where = [];
    $params = [];
    $sortOptions = sr_coupon_admin_redemption_sort_options();
    $defaultSort = sr_coupon_admin_redemption_default_sort();
    $orderSql = sr_admin_sort_order_sql($sortOptions, $sort, $defaultSort);
    if ($orderSql === '') {
        $orderSql = ' ORDER BY r.id DESC';
    }

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['refundable_policy'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('d.refundable_policy', 'refundable_policy', $filters['refundable_policy']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $couponKeyword = sr_coupon_clean_text((string) ($filters['coupon_q'] ?? ''), 120);
    if ($couponKeyword !== '') {
        $where[] = "(d.coupon_key LIKE :coupon_keyword_like ESCAPE '\\\\' OR d.title LIKE :coupon_keyword_like ESCAPE '\\\\')";
        $params['coupon_keyword_like'] = sr_coupon_like_keyword($couponKeyword);
    }

    $accountFilter = is_array($filters['account'] ?? null) ? $filters['account'] : [];
    $accountId = (int) ($accountFilter['account_id'] ?? 0);
    if ($accountId > 0) {
        $where[] = 'r.account_id = :account_id';
        $params['account_id'] = $accountId;
    } elseif (trim((string) ($accountFilter['keyword'] ?? '')) !== '') {
        $where[] = '1 = 0';
    }

    $sql = 'SELECT r.id, r.coupon_issue_id, r.coupon_definition_id, r.account_id,
                   r.target_type, r.target_id, r.reference_module, r.reference_type, r.reference_id,
                   r.dedupe_key, r.status, r.redeemed_at, ' . $refundColumns . ', ' . $pricingColumns . ',
                   d.coupon_key, d.title, d.coupon_type, d.refundable_policy, i.status AS issue_status, i.used_count,
                   a.display_name, a.email, a.status AS account_status
            FROM sr_coupon_redemptions r
            INNER JOIN sr_coupon_definitions d ON d.id = r.coupon_definition_id
            INNER JOIN sr_coupon_issues i ON i.id = r.coupon_issue_id
            LEFT JOIN sr_member_accounts a ON a.id = r.account_id'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . $orderSql
        . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['account_public_hash'] = sr_admin_member_public_hash($runtimeConfig, (int) ($row['account_id'] ?? 0));
        $rows[] = $row;
    }

    return $rows;
}
