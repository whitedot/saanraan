<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_coupon_definition_by_id(PDO $pdo, int $definitionId): ?array
{
    if ($definitionId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_coupon_definitions WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $definitionId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_issue_by_id(PDO $pdo, int $issueId): ?array
{
    if ($issueId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT i.*, d.coupon_key, d.title, d.description, d.coupon_type, d.target_type, d.target_id, d.refundable_policy, d.max_uses_per_issue
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $issueId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_definition_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_coupon_definition_reference_rows($pdo, $target, $context));
}

function sr_coupon_definition_reference_rows(PDO $pdo, array $target, array $context): array
{
    $definitionId = (int) ($target['target_id'] ?? 0);
    if ($definitionId <= 0) {
        return [];
    }

    $definition = is_array($context['definition'] ?? null) ? $context['definition'] : sr_coupon_definition_by_id($pdo, $definitionId);
    $targetKey = (string) ($target['target_key'] ?? '');
    $domainTarget = [
        'target_type' => (string) ($definition['target_type'] ?? ''),
        'target_id' => (string) ($definition['target_id'] ?? ''),
    ];

    $rows = [];
    if (sr_coupon_table_available($pdo, 'sr_coupon_issues')) {
        try {
            $stmt = $pdo->prepare(
                'SELECT status, COUNT(*) AS reference_count, MAX(updated_at) AS updated_at
                 FROM sr_coupon_issues
                 WHERE coupon_definition_id = :definition_id
                 GROUP BY status
                 ORDER BY status ASC'
            );
            $stmt->execute(['definition_id' => $definitionId]);
        } catch (Throwable) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT status, COUNT(*) AS reference_count, \'\' AS updated_at
                     FROM sr_coupon_issues
                     WHERE coupon_definition_id = :definition_id
                     GROUP BY status
                     ORDER BY status ASC'
                );
                $stmt->execute(['definition_id' => $definitionId]);
            } catch (Throwable) {
                $stmt = null;
            }
        }
        if ($stmt === null) {
            return $rows;
        }
        foreach ($stmt->fetchAll() as $row) {
            $status = (string) ($row['status'] ?? '');
            $rows[] = [
                'consumer_module_key' => 'coupon',
                'reference_type' => 'coupon_history',
                'reference_id' => 'definition:' . (string) $definitionId . ':issue_status:' . $status,
                'title' => '지급 쿠폰 ' . (string) (int) ($row['reference_count'] ?? 0) . '건',
                'target_type' => 'coupon_definition',
                'target_id' => (string) $definitionId,
                'target_key' => $targetKey,
                'policy_status' => $status,
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'metadata' => ['history_kind' => 'coupon_issue', 'domain_target' => $domainTarget],
            ];
        }
    }

    if (sr_coupon_table_available($pdo, 'sr_coupon_redemptions')) {
        try {
            $stmt = $pdo->prepare(
                'SELECT status, COUNT(*) AS reference_count, MAX(COALESCE(refunded_at, redeemed_at)) AS updated_at
                 FROM sr_coupon_redemptions
                 WHERE coupon_definition_id = :definition_id
                 GROUP BY status
                 ORDER BY status ASC'
            );
            $stmt->execute(['definition_id' => $definitionId]);
        } catch (Throwable) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT status, COUNT(*) AS reference_count, \'\' AS updated_at
                     FROM sr_coupon_redemptions
                     WHERE coupon_definition_id = :definition_id
                     GROUP BY status
                     ORDER BY status ASC'
                );
                $stmt->execute(['definition_id' => $definitionId]);
            } catch (Throwable) {
                $stmt = null;
            }
        }
        if ($stmt === null) {
            return $rows;
        }
        foreach ($stmt->fetchAll() as $row) {
            $status = (string) ($row['status'] ?? '');
            $rows[] = [
                'consumer_module_key' => 'coupon',
                'reference_type' => 'coupon_history',
                'reference_id' => 'definition:' . (string) $definitionId . ':redemption_status:' . $status,
                'title' => '쿠폰 사용 이력 ' . (string) (int) ($row['reference_count'] ?? 0) . '건',
                'target_type' => 'coupon_definition',
                'target_id' => (string) $definitionId,
                'target_key' => $targetKey,
                'policy_status' => $status,
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'metadata' => ['history_kind' => 'coupon_redemption', 'domain_target' => $domainTarget],
            ];
        }
    }

    return $rows;
}

function sr_coupon_definition_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    $definitionId = (int) ($target['target_id'] ?? 0);
    $definition = $definitionId > 0 ? sr_coupon_definition_by_id($pdo, $definitionId) : null;
    if (!is_array($definition)) {
        return ['status' => 'missing_target', 'message' => '쿠폰 정의를 찾을 수 없습니다.'];
    }

    if (!sr_coupon_definition_allows_redeem((string) ($definition['status'] ?? ''))) {
        return ['status' => 'disabled_target', 'message' => '쿠폰 정의가 사용 중지 상태입니다.'];
    }

    return ['status' => 'ok'];
}

function sr_coupon_definition_reference_admin_url(array $row, array $context): string
{
    return '/admin/coupons?coupon_q=' . rawurlencode((string) ($context['coupon_key'] ?? ''));
}

function sr_coupon_definitions(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(300, $limit));
    $stmt = $pdo->query(
        'SELECT *
         FROM sr_coupon_definitions
         ORDER BY id DESC
         LIMIT ' . $limit
    );

    return $stmt->fetchAll();
}

function sr_coupon_admin_definition_filters(PDO $pdo): array
{
    return [
        'status' => sr_admin_get_allowed_single_array('status', sr_coupon_statuses(), 30),
        'target_type' => sr_admin_get_allowed_single_array('target_type', array_keys(sr_coupon_target_types($pdo)), 60),
        'q' => sr_coupon_clean_text(sr_get_string('q', 120), 120),
    ];
}

function sr_coupon_admin_definition_sort_options(): array
{
    return [
        'coupon_key' => ['columns' => ['coupon_key', 'id']],
        'title' => ['columns' => ['title', 'id']],
        'target_type' => ['columns' => ['target_type', 'target_id', 'id']],
        'status' => ['columns' => ['status', 'id']],
        'created_at' => ['columns' => ['created_at', 'id']],
    ];
}

function sr_coupon_admin_definition_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_coupon_admin_definition_count(PDO $pdo, array $filters): int
{
    $where = [];
    $params = [];

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = sr_coupon_clean_text((string) ($filters['q'] ?? ''), 120);
    if ($keyword !== '') {
        $where[] = "(coupon_key LIKE :keyword_like ESCAPE '\\\\' OR title LIKE :keyword_like ESCAPE '\\\\' OR description LIKE :keyword_like ESCAPE '\\\\' OR target_id LIKE :keyword_like ESCAPE '\\\\')";
        $params['keyword_like'] = sr_coupon_like_keyword($keyword);
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_coupon_definitions'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_coupon_admin_definitions(PDO $pdo, array $filters, int $limit = 100, array $sort = [], int $offset = 0): array
{
    $limit = max(1, min(300, $limit));
    $offset = max(0, $offset);
    $where = [];
    $params = [];
    $sortOptions = sr_coupon_admin_definition_sort_options();
    $defaultSort = sr_coupon_admin_definition_default_sort();
    $orderSql = sr_admin_sort_order_sql($sortOptions, $sort, $defaultSort);
    if ($orderSql === '') {
        $orderSql = ' ORDER BY id DESC';
    }

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = sr_coupon_clean_text((string) ($filters['q'] ?? ''), 120);
    if ($keyword !== '') {
        $where[] = "(coupon_key LIKE :keyword_like ESCAPE '\\\\' OR title LIKE :keyword_like ESCAPE '\\\\' OR description LIKE :keyword_like ESCAPE '\\\\' OR target_id LIKE :keyword_like ESCAPE '\\\\')";
        $params['keyword_like'] = sr_coupon_like_keyword($keyword);
    }

    $sql = 'SELECT *
            FROM sr_coupon_definitions'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . $orderSql
        . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_coupon_admin_issue_filters(PDO $pdo, array $runtimeConfig): array
{
    return [
        'status' => sr_admin_get_allowed_array('status', sr_coupon_issue_statuses(), 30),
        'target_type' => sr_admin_get_allowed_single_array('target_type', array_keys(sr_coupon_target_types($pdo)), 60),
        'coupon_q' => sr_coupon_clean_text(sr_get_string('coupon_q', 120), 120),
        'account' => sr_admin_member_account_lookup_filter($pdo, $runtimeConfig),
    ];
}

function sr_coupon_admin_issue_sort_options(): array
{
    return [
        'member' => ['columns' => ["COALESCE(a.display_name, '')", 'a.email', 'i.account_id', 'i.id']],
        'coupon' => ['columns' => ['d.title', 'd.coupon_key', 'i.id']],
        'target_type' => ['columns' => ['d.target_type', 'd.target_id', 'i.id']],
        'status' => ['columns' => ['i.status', 'i.id']],
        'used_count' => ['columns' => ['i.used_count', 'i.id']],
        'issued_at' => ['columns' => ['i.issued_at', 'i.id']],
    ];
}

function sr_coupon_admin_issue_default_sort(): array
{
    return sr_admin_sort_default('issued_at', 'desc');
}

function sr_coupon_admin_issue_count(PDO $pdo, array $runtimeConfig, array $filters): int
{
    sr_coupon_expire_active_issues($pdo);

    $where = [];
    $params = [];

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('i.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('d.target_type', 'target_type', $filters['target_type']);
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
        $where[] = 'i.account_id = :account_id';
        $params['account_id'] = $accountId;
    } elseif (trim((string) ($accountFilter['keyword'] ?? '')) !== '') {
        $where[] = '1 = 0';
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         LEFT JOIN sr_member_accounts a ON a.id = i.account_id'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_coupon_admin_issues(PDO $pdo, array $runtimeConfig, array $filters, int $limit = 100, array $sort = [], int $offset = 0): array
{
    sr_coupon_expire_active_issues($pdo);

    $limit = max(1, min(300, $limit));
    $offset = max(0, $offset);
    $where = [];
    $params = [];
    $sortOptions = sr_coupon_admin_issue_sort_options();
    $defaultSort = sr_coupon_admin_issue_default_sort();
    $orderSql = sr_admin_sort_order_sql($sortOptions, $sort, $defaultSort);
    if ($orderSql === '') {
        $orderSql = ' ORDER BY i.id DESC';
    }

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('i.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('d.target_type', 'target_type', $filters['target_type']);
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
        $where[] = 'i.account_id = :account_id';
        $params['account_id'] = $accountId;
    } elseif (trim((string) ($accountFilter['keyword'] ?? '')) !== '') {
        $where[] = '1 = 0';
    }

    $sql = 'SELECT i.id, i.account_id, i.status, i.used_count, i.issued_at, i.expires_at,
                   i.claim_type, i.nominal_price_amount, i.nominal_price_currency_code,
                   d.title, d.coupon_key, d.target_type, d.target_id,
                   a.display_name, a.email, a.status AS account_status
            FROM sr_coupon_issues i
            INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
            LEFT JOIN sr_member_accounts a ON a.id = i.account_id'
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

function sr_coupon_admin_redemption_filters(PDO $pdo, array $runtimeConfig): array
{
    return [
        'status' => sr_admin_get_allowed_single_array('status', ['redeemed', 'refunded'], 30),
        'target_type' => sr_admin_get_allowed_single_array('target_type', array_keys(sr_coupon_target_types($pdo)), 60),
        'refundable_policy' => sr_admin_get_allowed_single_array('refundable_policy', array_keys(sr_coupon_refundable_policies()), 30),
        'coupon_q' => sr_coupon_clean_text(sr_get_string('coupon_q', 120), 120),
        'account' => sr_admin_member_account_lookup_filter($pdo, $runtimeConfig),
    ];
}

function sr_coupon_admin_redemption_sort_options(): array
{
    return [
        'member' => ['columns' => ["COALESCE(a.display_name, '')", 'a.email', 'r.account_id', 'r.id']],
        'coupon' => ['columns' => ['d.title', 'd.coupon_key', 'r.id']],
        'target_type' => ['columns' => ['r.target_type', 'r.target_id', 'r.id']],
        'status' => ['columns' => ['r.status', 'r.id']],
        'redeemed_at' => ['columns' => ['r.redeemed_at', 'r.id']],
        'refunded_at' => ['columns' => ['refunded_at', 'r.id']],
    ];
}

function sr_coupon_admin_redemption_default_sort(): array
{
    return sr_admin_sort_default('redeemed_at', 'desc');
}

function sr_coupon_admin_claim_campaign_filters(): array
{
    return [
        'status' => sr_admin_get_allowed_single_array('status', sr_coupon_claim_campaign_statuses(), 30),
        'claim_type' => sr_admin_get_allowed_single_array('claim_type', sr_coupon_claim_types(), 20),
        'visibility' => sr_admin_get_allowed_single_array('visibility', ['hidden', 'public'], 20),
        'q' => sr_coupon_clean_text(sr_get_string('q', 120), 120),
    ];
}

function sr_coupon_admin_claim_campaign_query_parts(array $filters = []): array
{
    $where = [];
    $params = [];

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('c.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['claim_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('c.claim_type', 'claim_type', $filters['claim_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['visibility'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('c.visibility', 'visibility', $filters['visibility']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = sr_coupon_clean_text((string) ($filters['q'] ?? ''), 120);
    if ($keyword !== '') {
        $where[] = "(c.campaign_key LIKE :campaign_keyword_like ESCAPE '\\\\' OR c.title LIKE :campaign_keyword_like ESCAPE '\\\\' OR d.coupon_key LIKE :campaign_keyword_like ESCAPE '\\\\' OR d.title LIKE :campaign_keyword_like ESCAPE '\\\\')";
        $params['campaign_keyword_like'] = sr_coupon_like_keyword($keyword);
    }

    return [
        'where' => $where === [] ? '' : ' WHERE ' . implode(' AND ', $where),
        'params' => $params,
    ];
}

function sr_coupon_admin_claim_campaign_count(PDO $pdo, array $filters = []): int
{
    if (!sr_coupon_claim_tables_available($pdo)) {
        return 0;
    }

    $query = sr_coupon_admin_claim_campaign_query_parts($filters);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_coupon_claim_campaigns c
         INNER JOIN sr_coupon_definitions d ON d.id = c.coupon_definition_id'
        . $query['where']
    );
    $stmt->execute($query['params']);

    return max(0, (int) $stmt->fetchColumn());
}

function sr_coupon_admin_claim_campaigns(PDO $pdo, int $limit = 100, array $filters = [], int $offset = 0): array
{
    if (!sr_coupon_claim_tables_available($pdo)) {
        return [];
    }

    $limit = max(1, min(300, $limit));
    $offset = max(0, $offset);
    $query = sr_coupon_admin_claim_campaign_query_parts($filters);

    $sql = 'SELECT c.id, c.campaign_key, c.title, c.status, c.claim_type, c.price_amount, c.price_currency_code,
                   c.allowed_asset_modules_json, c.total_claim_limit, c.per_account_limit, c.visibility,
                   d.coupon_key, d.title AS coupon_title
            FROM sr_coupon_claim_campaigns c
            INNER JOIN sr_coupon_definitions d ON d.id = c.coupon_definition_id'
        . $query['where']
        . ' ORDER BY c.id DESC
            LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($query['params']);

    return $stmt->fetchAll();
}

function sr_coupon_admin_claim_campaign_definition_options(PDO $pdo, int $limit = 300, int $includeDefinitionId = 0): array
{
    $limit = max(1, min(1000, $limit));
    $stmt = $pdo->query(
        'SELECT id, coupon_key, title, status
         FROM sr_coupon_definitions
         ORDER BY id DESC
         LIMIT ' . $limit
    );

    $options = $stmt->fetchAll();
    if ($includeDefinitionId < 1) {
        return $options;
    }
    foreach ($options as $option) {
        if ((int) ($option['id'] ?? 0) === $includeDefinitionId) {
            return $options;
        }
    }

    $currentStmt = $pdo->prepare('SELECT id, coupon_key, title, status FROM sr_coupon_definitions WHERE id = :id LIMIT 1');
    $currentStmt->execute(['id' => $includeDefinitionId]);
    $current = $currentStmt->fetch();
    if (is_array($current)) {
        $options[] = $current;
    }

    return $options;
}

function sr_coupon_admin_claim_log_filters(PDO $pdo, array $runtimeConfig): array
{
    return [
        'status' => sr_admin_get_allowed_array('status', array_merge(sr_coupon_claim_log_statuses(), ['expired_unmaterialized']), 30),
        'claim_source' => sr_admin_get_allowed_single_array('claim_source', array_merge(sr_coupon_claim_surfaces(), ['admin']), 40),
        'campaign_q' => sr_coupon_clean_text(sr_get_string('campaign_q', 120), 120),
        'account' => sr_admin_member_account_lookup_filter($pdo, $runtimeConfig),
    ];
}

function sr_coupon_admin_claim_log_query_parts(array $filters, string $now): array
{
    $where = [];
    $params = [];

    $statusFilters = is_array($filters['status'] ?? null) ? array_values(array_map('strval', $filters['status'])) : [];
    if ($statusFilters !== []) {
        $statusConditions = [];
        $materializedStatuses = array_values(array_filter($statusFilters, static fn (string $status): bool => $status !== 'expired_unmaterialized'));
        if ($materializedStatuses !== []) {
            [$condition, $conditionParams] = sr_admin_sql_in_condition('l.status', 'log_status', $materializedStatuses);
            $statusConditions[] = $condition;
            $params = array_merge($params, $conditionParams);
        }
        if (in_array('expired_unmaterialized', $statusFilters, true)) {
            $statusConditions[] = "(l.status IN ('reserved', 'pending_payment') AND l.reserved_until IS NOT NULL AND l.reserved_until <> '' AND l.reserved_until < :claim_log_now)";
            $params['claim_log_now'] = $now;
        }
        if ($statusConditions !== []) {
            $where[] = '(' . implode(' OR ', $statusConditions) . ')';
        }
    }

    if (($filters['claim_source'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('l.claim_source', 'claim_source', $filters['claim_source']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = sr_coupon_clean_text((string) ($filters['campaign_q'] ?? ''), 120);
    if ($keyword !== '') {
        $where[] = "(c.campaign_key LIKE :claim_log_keyword_like ESCAPE '\\\\' OR c.title LIKE :claim_log_keyword_like ESCAPE '\\\\' OR d.coupon_key LIKE :claim_log_keyword_like ESCAPE '\\\\' OR d.title LIKE :claim_log_keyword_like ESCAPE '\\\\')";
        $params['claim_log_keyword_like'] = sr_coupon_like_keyword($keyword);
    }

    $accountFilter = is_array($filters['account'] ?? null) ? $filters['account'] : [];
    $accountId = (int) ($accountFilter['account_id'] ?? 0);
    if ($accountId > 0) {
        $where[] = 'l.account_id = :claim_log_account_id';
        $params['claim_log_account_id'] = $accountId;
    } elseif (trim((string) ($accountFilter['keyword'] ?? '')) !== '') {
        $where[] = '1 = 0';
    }

    return [
        'where' => $where === [] ? '' : ' WHERE ' . implode(' AND ', $where),
        'params' => $params,
    ];
}

function sr_coupon_admin_claim_log_count(PDO $pdo, array $filters = []): int
{
    if (!sr_coupon_claim_tables_available($pdo)) {
        return 0;
    }

    $query = sr_coupon_admin_claim_log_query_parts($filters, sr_now());
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_coupon_claim_logs l
         INNER JOIN sr_coupon_claim_campaigns c ON c.id = l.campaign_id
         INNER JOIN sr_coupon_definitions d ON d.id = l.coupon_definition_id
         LEFT JOIN sr_member_accounts a ON a.id = l.account_id'
        . $query['where']
    );
    $stmt->execute($query['params']);

    return max(0, (int) $stmt->fetchColumn());
}

function sr_coupon_admin_claim_logs(PDO $pdo, int $limit = 100, array $filters = [], int $offset = 0): array
{
    if (!sr_coupon_claim_tables_available($pdo)) {
        return [];
    }

    $limit = max(1, min(300, $limit));
    $offset = max(0, $offset);
    $now = sr_now();
    $query = sr_coupon_admin_claim_log_query_parts($filters, $now);

    $sql = 'SELECT l.id, l.campaign_id, l.coupon_definition_id, l.account_id, l.coupon_issue_id, l.dedupe_key,
                   l.claim_source, l.status, l.reserved_until, l.failure_code, l.failure_message, l.created_at,
                   c.campaign_key, c.title AS campaign_title, d.coupon_key, d.title AS coupon_title,
                   a.email AS account_email, a.display_name AS account_display_name
            FROM sr_coupon_claim_logs l
            INNER JOIN sr_coupon_claim_campaigns c ON c.id = l.campaign_id
            INNER JOIN sr_coupon_definitions d ON d.id = l.coupon_definition_id
            LEFT JOIN sr_member_accounts a ON a.id = l.account_id'
        . $query['where']
        . ' ORDER BY l.id DESC
            LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($query['params']);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $reservedUntil = (string) ($row['reserved_until'] ?? '');
        $row['display_status'] = $status;
        $row['is_lazy_expired'] = false;
        if (
            in_array($status, ['reserved', 'pending_payment'], true)
            && $reservedUntil !== ''
            && strcmp($reservedUntil, $now) < 0
        ) {
            $row['display_status'] = 'expired_unmaterialized';
            $row['is_lazy_expired'] = true;
        }
        $rows[] = $row;
    }

    return $rows;
}
