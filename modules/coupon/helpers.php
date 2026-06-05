<?php

declare(strict_types=1);

function sr_coupon_clean_key(string $value, int $maxLength = 60): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]/', '', $value);
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_coupon_key_is_valid(string $couponKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $couponKey) === 1;
}

function sr_coupon_clean_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function sr_coupon_like_keyword(string $keyword): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
}

function sr_coupon_statuses(): array
{
    return ['active', 'disabled'];
}

function sr_coupon_issue_statuses(): array
{
    return ['active', 'used', 'expired', 'revoked', 'withdrawn_expired', 'refund_requested', 'refunded'];
}

function sr_coupon_expire_active_issues(PDO $pdo, ?int $accountId = null): int
{
    if (!sr_coupon_tables_available($pdo)) {
        return 0;
    }

    $now = sr_now();
    $where = "status = 'active' AND expires_at IS NOT NULL AND expires_at < :expires_before";
    $params = [
        'expires_before' => $now,
        'updated_at' => $now,
    ];
    if ($accountId !== null && $accountId > 0) {
        $where .= ' AND account_id = :account_id';
        $params['account_id'] = $accountId;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_coupon_issues
         SET status = \'expired\',
             updated_at = :updated_at
         WHERE ' . $where
    );
    $stmt->execute($params);

    return $stmt->rowCount();
}

function sr_coupon_target_types(?PDO $pdo = null): array
{
    $targetTypes = [
        'all' => '전체',
    ];

    if ($pdo === null) {
        return $targetTypes;
    }

    foreach (sr_coupon_target_contracts($pdo) as $targetType => $target) {
        $targetTypes[(string) $targetType] = (string) ($target['label'] ?? $targetType);
    }

    return $targetTypes;
}

function sr_coupon_refundable_policies(): array
{
    return [
        'none' => '환급 없음',
        'refundable' => '환급 가능',
    ];
}

function sr_coupon_target_contract_helper_path(string $moduleKey, array $target): string
{
    $helpers = (string) ($target['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
    return is_file($path) ? $path : '';
}

function sr_coupon_target_contracts(PDO $pdo): array
{
    $contracts = [];
    foreach (sr_enabled_module_contract_files($pdo, 'coupon-targets.php', ['coupon']) as $moduleKey => $file) {
        $contractTargets = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($contractTargets)) {
            continue;
        }

        foreach ($contractTargets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $targetType = (string) ($target['target_type'] ?? '');
            $label = sr_coupon_clean_text((string) ($target['label'] ?? ''), 80);
            if ($targetType === '' || $label === '' || preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $targetType) !== 1) {
                continue;
            }

            $helperPath = sr_coupon_target_contract_helper_path($moduleKey, $target);
            if ($helperPath !== '') {
                require_once $helperPath;
            }

            $target['module_key'] = $moduleKey;
            $target['label'] = $label;
            $contracts[$targetType] = $target;
        }
    }

    return $contracts;
}

function sr_coupon_issue_member_groups(PDO $pdo): array
{
    if (!function_exists('sr_member_groups') || !function_exists('sr_member_groups_table_exists') || !sr_member_groups_table_exists($pdo)) {
        return [];
    }

    return array_values(array_filter(sr_member_groups($pdo), static function (array $group): bool {
        return (string) ($group['status'] ?? '') === 'enabled';
    }));
}

function sr_coupon_issue_target_account_ids(PDO $pdo, array $runtimeConfig, string $targetMode, string $accountIdentifier, string $groupKey): array
{
    if (!in_array($targetMode, ['member', 'all', 'group'], true)) {
        throw new InvalidArgumentException('쿠폰 지급 대상을 선택해 주세요.');
    }

    if ($targetMode === 'member') {
        $accountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $accountIdentifier);
        if ($accountId <= 0) {
            throw new InvalidArgumentException('쿠폰을 지급할 회원을 선택해 주세요.');
        }

        return [$accountId];
    }

    if ($targetMode === 'all') {
        $stmt = $pdo->query("SELECT id FROM sr_member_accounts WHERE status = 'active' ORDER BY id ASC");
        $accountIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        if ($accountIds === []) {
            throw new InvalidArgumentException('쿠폰을 지급할 활성 회원이 없습니다.');
        }

        return $accountIds;
    }

    if (
        !function_exists('sr_member_group_by_key')
        || !function_exists('sr_member_group_key_is_valid')
        || !sr_member_group_key_is_valid($groupKey)
    ) {
        throw new InvalidArgumentException('쿠폰을 지급할 회원 그룹을 선택해 주세요.');
    }

    $group = sr_member_group_by_key($pdo, $groupKey);
    if (!is_array($group) || (string) ($group['status'] ?? '') !== 'enabled') {
        throw new InvalidArgumentException('사용 가능한 회원 그룹을 선택해 주세요.');
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT m.account_id
         FROM sr_member_group_memberships m
         INNER JOIN sr_member_accounts a ON a.id = m.account_id
         WHERE m.group_id = :group_id
           AND m.status = 'active'
           AND a.status = 'active'
           AND (m.expires_at IS NULL OR m.expires_at >= :now)
         ORDER BY m.account_id ASC"
    );
    $stmt->execute([
        'group_id' => (int) $group['id'],
        'now' => sr_now(),
    ]);
    $accountIds = array_map('intval', array_column($stmt->fetchAll(), 'account_id'));
    if ($accountIds === []) {
        throw new InvalidArgumentException('선택한 회원 그룹에 지급 가능한 활성 회원이 없습니다.');
    }

    return $accountIds;
}

function sr_coupon_target_search(PDO $pdo, string $targetType, string $keyword, int $limit = 20): array
{
    if (!array_key_exists($targetType, sr_coupon_target_types($pdo)) || $targetType === 'all') {
        return [];
    }

    $keyword = sr_coupon_clean_text($keyword, 120);
    $limit = max(1, min(30, $limit));
    $contracts = sr_coupon_target_contracts($pdo);
    $target = $contracts[$targetType] ?? null;
    $searchFunction = is_array($target) ? (string) ($target['search_function'] ?? '') : '';
    if ($searchFunction === '' || !function_exists($searchFunction)) {
        return [];
    }

    try {
        $results = $searchFunction($pdo, $targetType, $keyword, $limit);
        return is_array($results) ? $results : [];
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'coupon_target_search_' . $targetType);
        return [];
    }
}

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
    $definitionId = (int) ($target['target_id'] ?? 0);
    if ($definitionId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM sr_coupon_issues WHERE coupon_definition_id = :definition_id) +
            (SELECT COUNT(*) FROM sr_coupon_redemptions WHERE coupon_definition_id = :definition_id) AS reference_count'
    );
    $stmt->execute(['definition_id' => $definitionId]);

    return (int) $stmt->fetchColumn();
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
    $stmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS reference_count, MAX(updated_at) AS updated_at
         FROM sr_coupon_issues
         WHERE coupon_definition_id = :definition_id
         GROUP BY status
         ORDER BY status ASC'
    );
    $stmt->execute(['definition_id' => $definitionId]);
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $rows[] = [
            'consumer_module_key' => 'coupon',
            'reference_type' => 'coupon_issue',
            'reference_id' => 'definition:' . (string) $definitionId . ':issue_status:' . $status,
            'title' => '지급 쿠폰 ' . (string) (int) ($row['reference_count'] ?? 0) . '건',
            'target_type' => 'coupon_definition',
            'target_id' => (string) $definitionId,
            'target_key' => $targetKey,
            'policy_status' => $status,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'metadata' => ['domain_target' => $domainTarget],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS reference_count, MAX(COALESCE(refunded_at, redeemed_at)) AS updated_at
         FROM sr_coupon_redemptions
         WHERE coupon_definition_id = :definition_id
         GROUP BY status
         ORDER BY status ASC'
    );
    $stmt->execute(['definition_id' => $definitionId]);
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $rows[] = [
            'consumer_module_key' => 'coupon',
            'reference_type' => 'coupon_redemption',
            'reference_id' => 'definition:' . (string) $definitionId . ':redemption_status:' . $status,
            'title' => '쿠폰 사용 이력 ' . (string) (int) ($row['reference_count'] ?? 0) . '건',
            'target_type' => 'coupon_definition',
            'target_id' => (string) $definitionId,
            'target_key' => $targetKey,
            'policy_status' => $status,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'metadata' => ['domain_target' => $domainTarget],
        ];
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

    if ((string) ($definition['status'] ?? '') !== 'active') {
        return ['status' => 'disabled_target', 'message' => '쿠폰 정의가 비활성 상태입니다.'];
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

function sr_coupon_admin_definitions(PDO $pdo, array $filters, int $limit = 100, array $sort = []): array
{
    $limit = max(1, min(300, $limit));
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
        . ' LIMIT ' . $limit;
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

function sr_coupon_admin_issues(PDO $pdo, array $runtimeConfig, array $filters, int $limit = 100, array $sort = []): array
{
    sr_coupon_expire_active_issues($pdo);

    $limit = max(1, min(300, $limit));
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
                   d.title, d.coupon_key, d.target_type, d.target_id,
                   a.display_name, a.email, a.status AS account_status
            FROM sr_coupon_issues i
            INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
            LEFT JOIN sr_member_accounts a ON a.id = i.account_id'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . $orderSql
        . ' LIMIT ' . $limit;
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

function sr_coupon_create_definition(PDO $pdo, array $data): int
{
    $couponKey = sr_coupon_clean_key((string) ($data['coupon_key'] ?? ''));
    $title = sr_coupon_clean_text((string) ($data['title'] ?? ''), 120);
    $description = sr_coupon_clean_text((string) ($data['description'] ?? ''), 1000);
    $status = in_array((string) ($data['status'] ?? 'active'), sr_coupon_statuses(), true) ? (string) $data['status'] : 'active';
    $couponType = sr_coupon_clean_key((string) ($data['coupon_type'] ?? 'access'), 40);
    $targetType = array_key_exists((string) ($data['target_type'] ?? 'all'), sr_coupon_target_types($pdo)) ? (string) $data['target_type'] : 'all';
    $targetId = sr_coupon_clean_text((string) ($data['target_id'] ?? ''), 80);
    $refundablePolicy = array_key_exists((string) ($data['refundable_policy'] ?? 'none'), sr_coupon_refundable_policies()) ? (string) $data['refundable_policy'] : 'none';
    $maxUsesValue = $data['max_uses_per_issue'] ?? '1';
    if (is_array($maxUsesValue)) {
        throw new InvalidArgumentException('사용 가능 횟수는 1부터 1000 사이의 정수로 입력하세요.');
    }
    $maxUsesString = trim((string) $maxUsesValue);
    if ($maxUsesString === '' || preg_match('/\A[1-9][0-9]*\z/', $maxUsesString) !== 1) {
        throw new InvalidArgumentException('사용 가능 횟수는 1부터 1000 사이의 정수로 입력하세요.');
    }
    $maxUses = (int) $maxUsesString;
    if ($maxUses < 1 || $maxUses > 1000) {
        throw new InvalidArgumentException('사용 가능 횟수는 1부터 1000 사이의 정수로 입력하세요.');
    }

    if (!sr_coupon_key_is_valid($couponKey)) {
        throw new InvalidArgumentException('쿠폰 키는 영문 소문자로 시작하고 소문자, 숫자, 밑줄만 사용할 수 있습니다.');
    }

    if ($title === '') {
        throw new InvalidArgumentException('쿠폰 키와 이름을 입력하세요.');
    }

    $stmt = $pdo->prepare('SELECT id FROM sr_coupon_definitions WHERE coupon_key = :coupon_key LIMIT 1');
    $stmt->execute(['coupon_key' => $couponKey]);
    if (is_array($stmt->fetch())) {
        throw new InvalidArgumentException('이미 사용 중인 쿠폰 키입니다.');
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            (:coupon_key, :title, :description, :status, :coupon_type, :target_type, :target_id, :refundable_policy, :max_uses_per_issue, NULL, NULL, :created_at, :updated_at)'
    );
    $stmt->execute([
        'coupon_key' => $couponKey,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'coupon_type' => $couponType !== '' ? $couponType : 'access',
        'target_type' => $targetType,
        'target_id' => $targetId,
        'refundable_policy' => $refundablePolicy,
        'max_uses_per_issue' => $maxUses,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_coupon_update_definition_status(PDO $pdo, int $definitionId, string $status): void
{
    if ($definitionId <= 0 || !in_array($status, sr_coupon_statuses(), true)) {
        throw new InvalidArgumentException('쿠폰 종류의 상태가 올바르지 않습니다.');
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_coupon_definitions
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $definitionId,
    ]);
}

function sr_coupon_issue_to_account(PDO $pdo, int $definitionId, int $accountId, string $reason = '', ?int $issuedByAccountId = null, ?string $expiresAt = null): int
{
    if ($definitionId <= 0 || $accountId <= 0) {
        throw new InvalidArgumentException('쿠폰 종류와 지급할 회원을 선택해 주세요.');
    }

    $definition = sr_coupon_definition_by_id($pdo, $definitionId);
    if (!is_array($definition) || (string) $definition['status'] !== 'active') {
        throw new InvalidArgumentException('사용 중인 쿠폰 종류만 지급할 수 있습니다.');
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:coupon_definition_id, :account_id, :status, :issued_reason, :issued_by_account_id, :issued_at, :expires_at, 0, :created_at, :updated_at)'
    );
    $stmt->execute([
        'coupon_definition_id' => $definitionId,
        'account_id' => $accountId,
        'status' => 'active',
        'issued_reason' => sr_coupon_clean_text($reason, 255),
        'issued_by_account_id' => $issuedByAccountId !== null && $issuedByAccountId > 0 ? $issuedByAccountId : null,
        'issued_at' => $now,
        'expires_at' => $expiresAt,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $issueId = (int) $pdo->lastInsertId();
    sr_coupon_notify_issue_event($pdo, $issueId, 'issue.created', $issuedByAccountId);

    return $issueId;
}

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

function sr_coupon_active_account_issues(PDO $pdo, int $accountId, int $limit = 100): array
{
    if ($accountId <= 0) {
        return [];
    }

    sr_coupon_expire_active_issues($pdo, $accountId);

    $limit = max(1, min(300, $limit));
    $stmt = $pdo->prepare(
        "SELECT i.*, d.coupon_key, d.title, d.description, d.coupon_type, d.target_type, d.target_id, d.refundable_policy, d.max_uses_per_issue
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'
           AND d.status = 'active'
           AND (i.expires_at IS NULL OR i.expires_at >= :now_value)
         ORDER BY i.id DESC
         LIMIT " . $limit
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now_value' => sr_now(),
    ]);

    return $stmt->fetchAll();
}

function sr_coupon_active_account_issue_count(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0 || !sr_coupon_tables_available($pdo)) {
        return 0;
    }

    sr_coupon_expire_active_issues($pdo, $accountId);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'
           AND d.status = 'active'
           AND (i.expires_at IS NULL OR i.expires_at >= :now_value)"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now_value' => sr_now(),
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

function sr_coupon_admin_redemptions(PDO $pdo, array $runtimeConfig, int $limit = 100, array $filters = [], array $sort = []): array
{
    $limit = max(1, min(300, $limit));
    $refundColumns = sr_coupon_redemption_refund_columns_available($pdo)
        ? 'r.refunded_at, r.refunded_by_account_id, r.refund_note'
        : 'NULL AS refunded_at, NULL AS refunded_by_account_id, \'\' AS refund_note';
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
                   r.dedupe_key, r.status, r.redeemed_at, ' . $refundColumns . ',
                   d.coupon_key, d.title, d.refundable_policy, i.status AS issue_status, i.used_count,
                   a.display_name, a.email, a.status AS account_status
            FROM sr_coupon_redemptions r
            INNER JOIN sr_coupon_definitions d ON d.id = r.coupon_definition_id
            INNER JOIN sr_coupon_issues i ON i.id = r.coupon_issue_id
            LEFT JOIN sr_member_accounts a ON a.id = r.account_id'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . $orderSql
        . ' LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['account_public_hash'] = sr_admin_member_public_hash($runtimeConfig, (int) ($row['account_id'] ?? 0));
        $rows[] = $row;
    }

    return $rows;
}

function sr_coupon_refund_redemption(PDO $pdo, int $redemptionId, int $adminAccountId, string $refundNote): array
{
    $refundNote = sr_coupon_clean_text($refundNote, 255);
    if ($redemptionId <= 0) {
        throw new InvalidArgumentException('환불할 쿠폰 사용 내역을 선택하세요.');
    }
    if ($adminAccountId <= 0) {
        throw new InvalidArgumentException('관리자 계정을 확인할 수 없습니다.');
    }
    if ($refundNote === '') {
        throw new InvalidArgumentException('환불 사유를 입력하세요.');
    }
    if (!sr_coupon_redemption_refund_columns_available($pdo)) {
        throw new InvalidArgumentException('쿠폰 환불 컬럼 업데이트를 먼저 적용하세요.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT r.*, d.refundable_policy, d.max_uses_per_issue, d.title, i.status AS issue_status, i.used_count
             FROM sr_coupon_redemptions r
             INNER JOIN sr_coupon_definitions d ON d.id = r.coupon_definition_id
             INNER JOIN sr_coupon_issues i ON i.id = r.coupon_issue_id
             WHERE r.id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute(['id' => $redemptionId]);
        $redemption = $stmt->fetch();
        if (!is_array($redemption)) {
            throw new InvalidArgumentException('쿠폰 사용 내역을 찾을 수 없습니다.');
        }
        if ((string) ($redemption['status'] ?? '') !== 'redeemed') {
            throw new InvalidArgumentException('이미 환불되었거나 환불할 수 없는 사용 내역입니다.');
        }
        if ((string) ($redemption['refundable_policy'] ?? '') !== 'refundable') {
            throw new InvalidArgumentException('환급 가능 정책인 쿠폰만 수동 환불할 수 있습니다.');
        }

        $now = sr_now();
        $usedCount = max(0, (int) ($redemption['used_count'] ?? 0) - 1);
        $issueStatus = (string) ($redemption['issue_status'] ?? '');
        $nextIssueStatus = $issueStatus === 'used' ? 'active' : $issueStatus;

        $originalDedupeKey = (string) ($redemption['dedupe_key'] ?? '');
        $refundedDedupeKey = sr_coupon_refunded_dedupe_key($redemptionId, $originalDedupeKey);

        $stmt = $pdo->prepare(
            "UPDATE sr_coupon_redemptions
             SET status = 'refunded',
                 dedupe_key = :dedupe_key,
                 refunded_at = :refunded_at,
                 refunded_by_account_id = :refunded_by_account_id,
                 refund_note = :refund_note
             WHERE id = :id
               AND status = 'redeemed'"
        );
        $stmt->execute([
            'dedupe_key' => $refundedDedupeKey,
            'refunded_at' => $now,
            'refunded_by_account_id' => $adminAccountId,
            'refund_note' => $refundNote,
            'id' => $redemptionId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('이미 환불되었거나 환불할 수 없는 사용 내역입니다.');
        }

        $stmt = $pdo->prepare(
            'UPDATE sr_coupon_issues
             SET used_count = :used_count,
                 status = :status,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'used_count' => $usedCount,
            'status' => $nextIssueStatus,
            'updated_at' => $now,
            'id' => (int) $redemption['coupon_issue_id'],
        ]);

        $revokedAccess = sr_coupon_revoke_consumer_access($pdo, (int) $redemption['account_id'], $originalDedupeKey);

        if ($startedTransaction) {
            $pdo->commit();
        }

        sr_coupon_notify_issue_event($pdo, (int) $redemption['coupon_issue_id'], 'redemption.refunded', $adminAccountId, [
            'redemption_id' => $redemptionId,
            'refund_note' => $refundNote,
            'refunded_at' => $now,
            'used_count' => $usedCount,
            'revoked_access_count' => $revokedAccess,
            'original_dedupe_key' => $originalDedupeKey,
            'refunded_dedupe_key' => $refundedDedupeKey,
            'status_label' => sr_coupon_issue_status_label($nextIssueStatus),
        ]);

        return [
            'coupon_issue_id' => (int) $redemption['coupon_issue_id'],
            'coupon_definition_id' => (int) $redemption['coupon_definition_id'],
            'account_id' => (int) $redemption['account_id'],
            'coupon_title' => (string) ($redemption['title'] ?? ''),
            'used_count' => $usedCount,
            'issue_status' => $nextIssueStatus,
            'refunded_at' => $now,
            'revoked_access_count' => $revokedAccess,
            'original_dedupe_key' => $originalDedupeKey,
            'refunded_dedupe_key' => $refundedDedupeKey,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_coupon_refunded_dedupe_key(int $redemptionId, string $originalDedupeKey): string
{
    return 'refunded:' . (string) $redemptionId . ':' . substr(sha1($originalDedupeKey), 0, 24);
}

function sr_coupon_revoke_consumer_access(PDO $pdo, int $accountId, string $dedupeKey): int
{
    if ($accountId <= 0 || $dedupeKey === '') {
        return 0;
    }

    $revoked = 0;
    foreach (sr_coupon_target_contracts($pdo) as $target) {
        $revokeFunction = (string) ($target['revoke_access_function'] ?? '');
        if ($revokeFunction === '' || !function_exists($revokeFunction)) {
            continue;
        }

        try {
            $revoked += max(0, (int) $revokeFunction($pdo, $accountId, $dedupeKey));
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'coupon_revoke_consumer_access');
        }
    }

    return $revoked;
}

function sr_coupon_redeem_for_target(PDO $pdo, int $accountId, string $targetType, string $targetId, array $context = []): array
{
    $dedupeKey = sr_coupon_clean_text((string) ($context['dedupe_key'] ?? ''), 160);
    if ($accountId <= 0 || $targetType === '' || $dedupeKey === '' || !sr_coupon_tables_available($pdo)) {
        return ['allowed' => false, 'processed' => false, 'message' => ''];
    }

    sr_coupon_expire_active_issues($pdo, $accountId);

    if (sr_coupon_has_redemption($pdo, $accountId, $dedupeKey)) {
        return ['allowed' => true, 'processed' => false, 'already_redeemed' => true, 'message' => ''];
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT i.*, d.coupon_key, d.title, d.target_type, d.target_id, d.max_uses_per_issue, d.refundable_policy
             FROM sr_coupon_issues i
             INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
             WHERE i.account_id = :account_id
               AND i.status = 'active'
               AND d.status = 'active'
               AND (i.expires_at IS NULL OR i.expires_at >= :now_value)
             ORDER BY i.expires_at IS NULL ASC, i.expires_at ASC, i.id ASC
             FOR UPDATE"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'now_value' => sr_now(),
        ]);
        $selectedIssue = null;
        foreach ($stmt->fetchAll() as $issue) {
            if (sr_coupon_issue_matches_target($issue, $targetType, $targetId)) {
                $selectedIssue = $issue;
                break;
            }
        }

        if (!is_array($selectedIssue)) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return ['allowed' => false, 'processed' => false, 'message' => ''];
        }

        $now = sr_now();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_coupon_redemptions
                (coupon_issue_id, coupon_definition_id, account_id, target_type, target_id, reference_module, reference_type, reference_id, dedupe_key, status, redeemed_at, created_at)
             VALUES
                (:coupon_issue_id, :coupon_definition_id, :account_id, :target_type, :target_id, :reference_module, :reference_type, :reference_id, :dedupe_key, :status, :redeemed_at, :created_at)'
        );
        $stmt->execute([
            'coupon_issue_id' => (int) $selectedIssue['id'],
            'coupon_definition_id' => (int) $selectedIssue['coupon_definition_id'],
            'account_id' => $accountId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reference_module' => sr_coupon_clean_key((string) ($context['reference_module'] ?? ''), 60),
            'reference_type' => sr_coupon_clean_text((string) ($context['reference_type'] ?? ''), 80),
            'reference_id' => sr_coupon_clean_text((string) ($context['reference_id'] ?? $targetId), 120),
            'dedupe_key' => $dedupeKey,
            'status' => 'redeemed',
            'redeemed_at' => $now,
            'created_at' => $now,
        ]);
        $redemptionId = (int) $pdo->lastInsertId();

        $usedCount = (int) $selectedIssue['used_count'] + 1;
        $maxUses = max(1, (int) $selectedIssue['max_uses_per_issue']);
        $newStatus = $usedCount >= $maxUses ? 'used' : 'active';
        $stmt = $pdo->prepare(
            'UPDATE sr_coupon_issues
             SET used_count = :used_count,
                 status = :status,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'used_count' => $usedCount,
            'status' => $newStatus,
            'updated_at' => $now,
            'id' => (int) $selectedIssue['id'],
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        sr_coupon_notify_issue_event($pdo, (int) $selectedIssue['id'], 'redemption.redeemed', null, [
            'redemption_id' => $redemptionId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reference_module' => sr_coupon_clean_key((string) ($context['reference_module'] ?? ''), 60),
            'reference_type' => sr_coupon_clean_text((string) ($context['reference_type'] ?? ''), 80),
            'reference_id' => sr_coupon_clean_text((string) ($context['reference_id'] ?? $targetId), 120),
            'used_count' => $usedCount,
            'max_uses_per_issue' => $maxUses,
            'status_label' => sr_coupon_issue_status_label($newStatus),
            'created_at' => $now,
        ]);

        return [
            'allowed' => true,
            'processed' => true,
            'coupon_issue_id' => (int) $selectedIssue['id'],
            'coupon_definition_id' => (int) $selectedIssue['coupon_definition_id'],
            'coupon_title' => (string) $selectedIssue['title'],
            'message' => '',
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'coupon_redeem_for_target');
        }

        return ['allowed' => false, 'processed' => false, 'message' => ''];
    }
}

function sr_coupon_notify_issue_event(PDO $pdo, int $issueId, string $eventKey, ?int $createdByAccountId = null, array $metadata = []): ?int
{
    $createAccountEventFunction = sr_coupon_notification_event_function($pdo);
    if ($createAccountEventFunction === '') {
        return null;
    }

    $issue = sr_coupon_issue_by_id($pdo, $issueId);
    if (!is_array($issue)) {
        return null;
    }

    try {
        return $createAccountEventFunction($pdo, [
            'account_id' => (int) $issue['account_id'],
            'module_key' => 'coupon',
            'event_key' => $eventKey,
            'created_by_account_id' => $createdByAccountId !== null && $createdByAccountId > 0 ? $createdByAccountId : null,
            'metadata' => array_merge(sr_coupon_issue_notification_metadata($issue), $metadata),
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'coupon_issue_notification');
        return null;
    }
}

function sr_coupon_notification_event_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_coupon_issue_notification_metadata(array $issue): array
{
    return [
        'coupon_issue_id' => (int) ($issue['id'] ?? 0),
        'coupon_definition_id' => (int) ($issue['coupon_definition_id'] ?? 0),
        'coupon_key' => (string) ($issue['coupon_key'] ?? ''),
        'coupon_title' => (string) ($issue['title'] ?? ''),
        'asset_label' => '쿠폰·이용권',
        'status' => (string) ($issue['status'] ?? ''),
        'status_label' => sr_coupon_issue_status_label((string) ($issue['status'] ?? '')),
        'issued_reason' => (string) ($issue['issued_reason'] ?? ''),
        'target_type' => (string) ($issue['target_type'] ?? ''),
        'target_id' => (string) ($issue['target_id'] ?? ''),
        'used_count' => (int) ($issue['used_count'] ?? 0),
        'max_uses_per_issue' => (int) ($issue['max_uses_per_issue'] ?? 1),
        'issued_at' => (string) ($issue['issued_at'] ?? ''),
        'expires_at' => (string) ($issue['expires_at'] ?? ''),
        'created_at' => sr_now(),
    ];
}

function sr_coupon_process_account_withdrawal(PDO $pdo, int $accountId): array
{
    if ($accountId <= 0 || !sr_coupon_tables_available($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT i.id,
                CASE WHEN d.refundable_policy = 'refundable' THEN 'refund_requested' ELSE 'withdrawn_expired' END AS next_status
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'"
    );
    $stmt->execute(['account_id' => $accountId]);
    $pendingIssues = $stmt->fetchAll();

    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         SET i.status = CASE WHEN d.refundable_policy = 'refundable' THEN 'refund_requested' ELSE 'withdrawn_expired' END,
             i.updated_at = :updated_at
         WHERE i.account_id = :account_id
           AND i.status = 'active'"
    );
    $stmt->execute([
        'updated_at' => $now,
        'account_id' => $accountId,
    ]);
    $updatedCount = $stmt->rowCount();

    foreach ($pendingIssues as $pendingIssue) {
        $issueId = (int) ($pendingIssue['id'] ?? 0);
        $nextStatus = (string) ($pendingIssue['next_status'] ?? '');
        if ($issueId <= 0 || !in_array($nextStatus, ['withdrawn_expired', 'refund_requested'], true)) {
            continue;
        }

        sr_coupon_notify_issue_event($pdo, $issueId, 'issue.status_updated', null, [
            'status_label' => sr_coupon_issue_status_label($nextStatus),
        ]);
    }

    return [
        'label' => '쿠폰·이용권',
        'amount' => $updatedCount,
        'process' => '소멸/환급 검토',
    ];
}
