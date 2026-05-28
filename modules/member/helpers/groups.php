<?php

declare(strict_types=1);

function sr_member_group_key_is_valid(string $groupKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $groupKey) === 1;
}

function sr_member_group_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_member_groups_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_member_groups LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_member_group_memberships LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_member_group_rules LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_member_group_membership_logs LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_member_group_by_key(PDO $pdo, string $groupKey): ?array
{
    if (!sr_member_groups_table_exists($pdo)) {
        return null;
    }

    if (!sr_member_group_key_is_valid($groupKey)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_member_groups WHERE group_key = :group_key LIMIT 1');
    $stmt->execute(['group_key' => $groupKey]);
    $group = $stmt->fetch();

    return is_array($group) ? $group : null;
}

function sr_member_group_exists(PDO $pdo, string $groupKey): bool
{
    $group = sr_member_group_by_key($pdo, $groupKey);
    return is_array($group) && (string) $group['status'] === 'enabled';
}

function sr_member_account_group_keys(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !sr_member_groups_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT g.group_key
         FROM sr_member_group_memberships m
         INNER JOIN sr_member_groups g ON g.id = m.group_id
         WHERE m.account_id = :account_id
           AND m.status = 'active'
           AND g.status = 'enabled'
           AND (m.expires_at IS NULL OR m.expires_at >= :now)
         ORDER BY g.group_key ASC"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now' => sr_now(),
    ]);

    $groupKeys = [];
    foreach ($stmt->fetchAll() as $row) {
        $groupKeys[] = (string) $row['group_key'];
    }

    return $groupKeys;
}

function sr_member_account_in_group(PDO $pdo, int $accountId, string $groupKey): bool
{
    return in_array($groupKey, sr_member_account_group_keys($pdo, $accountId), true);
}

function sr_member_account_in_any_group(PDO $pdo, int $accountId, array $groupKeys): bool
{
    $normalizedKeys = [];
    foreach ($groupKeys as $groupKey) {
        $groupKey = (string) $groupKey;
        if (sr_member_group_key_is_valid($groupKey)) {
            $normalizedKeys[] = $groupKey;
        }
    }

    if ($normalizedKeys === []) {
        return false;
    }

    return array_intersect(sr_member_account_group_keys($pdo, $accountId), $normalizedKeys) !== [];
}

function sr_member_groups(PDO $pdo): array
{
    if (!sr_member_groups_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT g.*,
                COUNT(DISTINCT CASE WHEN m.status = 'active' THEN m.account_id END) AS active_member_count
         FROM sr_member_groups g
         LEFT JOIN sr_member_group_memberships m ON m.group_id = g.id
         GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.is_system, g.sort_order, g.created_at, g.updated_at
         ORDER BY g.sort_order ASC, g.id ASC"
    );

    return $stmt->fetchAll();
}

function sr_admin_member_group_list_filter(array $allowedStatuses): array
{
    $status = sr_get_string('status', 30);
    if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
        $status = '';
    }

    $field = sr_get_string('field', 30);
    if (!in_array($field, ['all', 'key', 'title', 'description'], true)) {
        $field = 'all';
    }

    return [
        'status' => $status,
        'field' => $field,
        'keyword' => trim(sr_get_string('q', 120)),
    ];
}

function sr_admin_member_group_query_parts(array $filter): array
{
    $where = [];
    $params = [];
    $status = (string) ($filter['status'] ?? '');
    $field = (string) ($filter['field'] ?? 'all');
    $keyword = trim((string) ($filter['keyword'] ?? ''));

    if ($status !== '') {
        $where[] = 'g.status = :status';
        $params['status'] = $status;
    }

    if ($keyword !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
        if ($field === 'key') {
            $where[] = 'g.group_key LIKE :keyword';
            $params['keyword'] = $like;
        } elseif ($field === 'title') {
            $where[] = 'g.title LIKE :keyword';
            $params['keyword'] = $like;
        } elseif ($field === 'description') {
            $where[] = 'g.description LIKE :keyword';
            $params['keyword'] = $like;
        } else {
            $where[] = '(g.group_key LIKE :group_key_keyword OR g.title LIKE :title_keyword OR g.description LIKE :description_keyword)';
            $params['group_key_keyword'] = $like;
            $params['title_keyword'] = $like;
            $params['description_keyword'] = $like;
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_admin_member_group_count(PDO $pdo, array $filter): int
{
    if (!sr_member_groups_table_exists($pdo)) {
        return 0;
    }

    $queryParts = sr_admin_member_group_query_parts($filter);
    $sql = 'SELECT COUNT(*) AS count_value FROM sr_member_groups g';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_admin_member_group_sort_options(): array
{
    return [
        'group_key' => ['columns' => ['g.group_key', 'g.id']],
        'title' => ['columns' => ['g.title', 'g.id']],
        'status' => ['columns' => ['g.status', 'g.id']],
        'active_member_count' => ['columns' => ['active_member_count', 'g.id']],
        'sort_order' => ['columns' => ['g.sort_order', 'g.id']],
    ];
}

function sr_admin_member_group_default_sort(): array
{
    return sr_admin_sort_default('sort_order', 'asc');
}

function sr_admin_member_group_list(PDO $pdo, array $filter, int $limit = 0, int $offset = 0, array $sort = []): array
{
    if (!sr_member_groups_table_exists($pdo)) {
        return [];
    }

    $queryParts = sr_admin_member_group_query_parts($filter);
    $sql = "SELECT g.*,
                   COUNT(DISTINCT CASE WHEN m.status = 'active' THEN m.account_id END) AS active_member_count
            FROM sr_member_groups g
            LEFT JOIN sr_member_group_memberships m ON m.group_id = g.id";
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }
    $sql .= ' GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.is_system, g.sort_order, g.created_at, g.updated_at'
        . sr_admin_sort_order_sql(sr_admin_member_group_sort_options(), $sort, sr_admin_member_group_default_sort());
    if ($limit > 0) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($queryParts['params'] as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_admin_member_group_status_counts(PDO $pdo): array
{
    $counts = [
        'total' => 0,
        'enabled' => 0,
        'disabled' => 0,
        'archived' => 0,
    ];

    if (!sr_member_groups_table_exists($pdo)) {
        return $counts;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_member_groups GROUP BY status');
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $count = (int) ($row['count_value'] ?? 0);
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $count;
        }
        $counts['total'] += $count;
    }

    return $counts;
}

function sr_admin_member_group_filter_rows(array $groups, array $filter): array
{
    $status = (string) ($filter['status'] ?? '');
    $field = (string) ($filter['field'] ?? 'all');
    $keyword = trim((string) ($filter['keyword'] ?? ''));
    $keyword = function_exists('mb_strtolower') ? mb_strtolower($keyword, 'UTF-8') : strtolower($keyword);

    return array_values(array_filter($groups, static function (array $group) use ($status, $field, $keyword): bool {
        if ($status !== '' && (string) ($group['status'] ?? '') !== $status) {
            return false;
        }

        if ($keyword === '') {
            return true;
        }

        $values = [];
        if ($field === 'key' || $field === 'all') {
            $values[] = (string) ($group['group_key'] ?? '');
        }
        if ($field === 'title' || $field === 'all') {
            $values[] = (string) ($group['title'] ?? '');
        }
        if ($field === 'description' || $field === 'all') {
            $values[] = (string) ($group['description'] ?? '');
        }

        foreach ($values as $value) {
            $normalizedValue = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            if (str_contains($normalizedValue, $keyword)) {
                return true;
            }
        }

        return false;
    }));
}

function sr_member_group_by_id(PDO $pdo, int $groupId): ?array
{
    if ($groupId < 1 || !sr_member_groups_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_member_groups WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $groupId]);
    $group = $stmt->fetch();

    return is_array($group) ? $group : null;
}

function sr_member_group_save(PDO $pdo, array $data): int
{
    $groupId = (int) ($data['id'] ?? 0);
    $now = sr_now();

    if ($groupId > 0) {
        $stmt = $pdo->prepare(
            'UPDATE sr_member_groups
             SET title = :title,
                 description = :description,
                 status = :status,
                 sort_order = :sort_order,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'title' => (string) $data['title'],
            'description' => (string) $data['description'],
            'status' => (string) $data['status'],
            'sort_order' => (int) $data['sort_order'],
            'updated_at' => $now,
            'id' => $groupId,
        ]);

        return $groupId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_groups
            (group_key, title, description, status, is_system, sort_order, created_at, updated_at)
         VALUES
            (:group_key, :title, :description, :status, 0, :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute([
        'group_key' => (string) $data['group_key'],
        'title' => (string) $data['title'],
        'description' => (string) $data['description'],
        'status' => (string) $data['status'],
        'sort_order' => (int) $data['sort_order'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_member_group_grant_manual(PDO $pdo, int $accountId, int $groupId, int $actorAccountId): void
{
    sr_member_group_set_manual_membership($pdo, $accountId, $groupId, $actorAccountId, true);
}

function sr_member_group_revoke_manual(PDO $pdo, int $accountId, int $groupId, int $actorAccountId): void
{
    sr_member_group_set_manual_membership($pdo, $accountId, $groupId, $actorAccountId, false);
}

function sr_member_group_set_manual_membership(PDO $pdo, int $accountId, int $groupId, int $actorAccountId, bool $grant): void
{
    $now = sr_now();

    if ($grant) {
        $stmt = $pdo->prepare(
            "SELECT id FROM sr_member_group_memberships
             WHERE account_id = :account_id
               AND group_id = :group_id
               AND assignment_type = 'manual'
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'group_id' => $groupId,
        ]);
        $membership = $stmt->fetch();

        if (is_array($membership)) {
            $membershipId = (int) $membership['id'];
            $stmt = $pdo->prepare(
                "UPDATE sr_member_group_memberships
                 SET status = 'active',
                     granted_at = COALESCE(granted_at, :granted_at),
                     revoked_at = NULL,
                     expires_at = NULL,
                     created_by_account_id = :actor_account_id,
                     updated_at = :updated_at
                 WHERE id = :id"
            );
            $stmt->execute([
                'granted_at' => $now,
                'actor_account_id' => $actorAccountId,
                'updated_at' => $now,
                'id' => $membershipId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO sr_member_group_memberships
                    (group_id, account_id, assignment_type, source_module_key, source_rule_key, status, granted_at, expires_at, revoked_at, created_by_account_id, updated_at)
                 VALUES
                    (:group_id, :account_id, 'manual', '', '', 'active', :granted_at, NULL, NULL, :actor_account_id, :updated_at)"
            );
            $stmt->execute([
                'group_id' => $groupId,
                'account_id' => $accountId,
                'granted_at' => $now,
                'actor_account_id' => $actorAccountId,
                'updated_at' => $now,
            ]);
            $membershipId = (int) $pdo->lastInsertId();
        }

        sr_member_group_log($pdo, $groupId, $accountId, $membershipId, 'member.group.manual_granted', $actorAccountId, 'Manual group membership granted.', []);
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM sr_member_group_memberships
         WHERE account_id = :account_id
           AND group_id = :group_id
           AND assignment_type = 'manual'
           AND status = 'active'
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'group_id' => $groupId,
    ]);
    $membership = $stmt->fetch();
    if (!is_array($membership)) {
        return;
    }

    $membershipId = (int) $membership['id'];
    $stmt = $pdo->prepare(
        "UPDATE sr_member_group_memberships
         SET status = 'revoked',
             revoked_at = :revoked_at,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'revoked_at' => $now,
        'updated_at' => $now,
        'id' => $membershipId,
    ]);

    sr_member_group_log($pdo, $groupId, $accountId, $membershipId, 'member.group.manual_revoked', $actorAccountId, 'Manual group membership revoked.', []);
}

function sr_member_group_log(PDO $pdo, int $groupId, int $accountId, ?int $membershipId, string $eventType, ?int $actorAccountId, string $message, array $metadata): void
{
    $sourceModuleKey = (string) ($metadata['source_module_key'] ?? '');
    $sourceRuleKey = (string) ($metadata['source_rule_key'] ?? '');
    $metadataJson = $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($metadataJson === false) {
        $metadataJson = null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_group_membership_logs
            (group_id, account_id, membership_id, event_type, source_module_key, source_rule_key, actor_account_id, message, metadata_json, created_at)
         VALUES
            (:group_id, :account_id, :membership_id, :event_type, :source_module_key, :source_rule_key, :actor_account_id, :message, :metadata_json, :created_at)'
    );
    $stmt->execute([
        'group_id' => $groupId,
        'account_id' => $accountId,
        'membership_id' => $membershipId,
        'event_type' => $eventType,
        'source_module_key' => sr_is_safe_module_key($sourceModuleKey) ? $sourceModuleKey : '',
        'source_rule_key' => sr_member_group_rule_key_is_valid($sourceRuleKey) ? $sourceRuleKey : '',
        'actor_account_id' => $actorAccountId,
        'message' => $message,
        'metadata_json' => $metadataJson,
        'created_at' => sr_now(),
    ]);
}

function sr_member_group_rule_key_is_valid(string $ruleKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\.[a-z0-9_.]{1,119}\z/', $ruleKey) === 1;
}

function sr_member_group_evaluation_policies(): array
{
    return ['grant_only', 'sync'];
}

function sr_member_group_rule_statuses(): array
{
    return ['enabled', 'disabled'];
}

function sr_member_group_rule_definitions(PDO $pdo): array
{
    $definitions = [];

    foreach (sr_enabled_module_contract_files($pdo, 'member-group-rules.php', ['member']) as $moduleKey => $file) {
        try {
            $rules = sr_load_module_contract_file($moduleKey, $file);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'member_group_rules_' . $moduleKey);
            continue;
        }

        if (!is_array($rules)) {
            continue;
        }

        foreach ($rules as $rule) {
            $definition = sr_member_group_normalize_rule_definition($moduleKey, $rule);
            if ($definition === null) {
                continue;
            }

            $definitions[$definition['source_module_key'] . ':' . $definition['rule_key']] = $definition;
        }
    }

    ksort($definitions, SORT_STRING);
    return $definitions;
}

function sr_member_group_normalize_rule_definition(string $moduleKey, mixed $rule): ?array
{
    if (!is_array($rule)) {
        return null;
    }

    $ruleKey = (string) ($rule['rule_key'] ?? '');
    $label = trim((string) ($rule['label'] ?? ''));
    $description = trim((string) ($rule['description'] ?? ''));
    $params = is_array($rule['params'] ?? null) ? $rule['params'] : [];
    $evaluator = (string) ($rule['evaluator'] ?? '');

    if (!sr_is_safe_module_key($moduleKey) || !sr_member_group_rule_key_is_valid($ruleKey)) {
        return null;
    }

    if (!str_starts_with($ruleKey, $moduleKey . '.')) {
        return null;
    }

    if ($label === '' || $evaluator === '' || !is_callable($evaluator)) {
        return null;
    }

    return [
        'source_module_key' => $moduleKey,
        'rule_key' => $ruleKey,
        'label' => $label,
        'description' => $description,
        'params' => sr_member_group_normalize_rule_params($params),
        'evaluator' => $evaluator,
    ];
}

function sr_member_group_normalize_rule_params(array $params): array
{
    $normalized = [];
    foreach ($params as $param) {
        if (!is_array($param)) {
            continue;
        }

        $key = (string) ($param['key'] ?? '');
        $label = trim((string) ($param['label'] ?? ''));
        $type = (string) ($param['type'] ?? 'string');
        if (preg_match('/\A[a-z][a-z0-9_]{0,59}\z/', $key) !== 1 || $label === '') {
            continue;
        }

        if (!in_array($type, ['string', 'int', 'bool', 'subject'], true)) {
            $type = 'string';
        }

        $optionsCallback = (string) ($param['options_callback'] ?? '');
        if ($optionsCallback !== '' && !is_callable($optionsCallback)) {
            $optionsCallback = '';
        }

        $normalized[] = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'min' => isset($param['min']) ? (int) $param['min'] : null,
            'max' => isset($param['max']) ? (int) $param['max'] : null,
            'default' => $param['default'] ?? null,
            'options' => sr_member_group_normalize_rule_param_options($param['options'] ?? []),
            'options_callback' => $optionsCallback,
        ];
    }

    return $normalized;
}

function sr_member_group_normalize_rule_param_options(mixed $options): array
{
    if (!is_array($options)) {
        return [];
    }

    $normalized = [];
    foreach ($options as $optionKey => $option) {
        if (is_array($option)) {
            $value = (string) ($option['value'] ?? '');
            $label = trim((string) ($option['label'] ?? $value));
        } else {
            $value = is_string($optionKey) ? (string) $optionKey : (string) $option;
            $label = trim((string) $option);
        }

        if ($value === '' || $label === '') {
            continue;
        }

        $normalized[] = [
            'value' => $value,
            'label' => $label,
        ];
    }

    return $normalized;
}

function sr_member_group_rule_param_options(?PDO $pdo, array $param): array
{
    $options = sr_member_group_normalize_rule_param_options($param['options'] ?? []);
    $optionsCallback = (string) ($param['options_callback'] ?? '');
    if ($optionsCallback !== '' && $pdo instanceof PDO && is_callable($optionsCallback)) {
        try {
            $callbackOptions = $optionsCallback($pdo);
            $options = sr_member_group_normalize_rule_param_options($callbackOptions);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'member_group_rule_param_options_' . (string) ($param['key'] ?? 'unknown'));
        }
    }

    return $options;
}

function sr_member_group_rule_params_from_input(array $definition, mixed $input, ?PDO $pdo = null): array
{
    $input = is_array($input) ? $input : [];
    $values = [];
    foreach ((array) ($definition['params'] ?? []) as $param) {
        if (!is_array($param)) {
            continue;
        }

        $key = (string) ($param['key'] ?? '');
        if ($key === '') {
            continue;
        }

        $rawValue = $input[$key] ?? ($param['default'] ?? '');
        if (is_array($rawValue)) {
            $rawValue = '';
        }

        $type = (string) ($param['type'] ?? 'string');
        if ($type === 'int' || $type === 'subject') {
            $value = (int) $rawValue;
            $options = sr_member_group_rule_param_options($pdo, $param);
            if ($options !== []) {
                $allowedValues = [];
                foreach ($options as $option) {
                    $allowedValues[(string) $option['value']] = true;
                }
                if (!isset($allowedValues[(string) $value])) {
                    $value = (int) ($param['default'] ?? 0);
                }
            }
            if (isset($param['min']) && $value < (int) $param['min']) {
                $value = (int) $param['min'];
            }
            if (isset($param['max']) && $value > (int) $param['max']) {
                $value = (int) $param['max'];
            }
            $values[$key] = $value;
        } elseif ($type === 'bool') {
            $values[$key] = in_array(strtolower(trim((string) $rawValue)), ['1', 'true', 'yes', 'on'], true);
        } else {
            $values[$key] = trim((string) $rawValue);
        }
    }

    return $values;
}

function sr_member_group_rule_count(PDO $pdo): int
{
    if (!sr_member_groups_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->query(
        'SELECT COUNT(*) AS count_value
         FROM sr_member_group_rules r
         INNER JOIN sr_member_groups g ON g.id = r.group_id'
    );
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_member_group_rule_sort_options(): array
{
    return [
        'group_title' => ['columns' => ['g.title', 'g.group_key', 'r.id']],
        'source_module_key' => ['columns' => ['r.source_module_key', 'r.rule_key', 'r.id']],
        'rule_key' => ['columns' => ['r.rule_key', 'r.id']],
        'evaluation_policy' => ['columns' => ['r.evaluation_policy', 'r.id']],
        'status' => ['columns' => ['r.status', 'r.id']],
        'last_evaluated_at' => ['columns' => ['r.last_evaluated_at', 'r.id']],
        'created_at' => ['columns' => ['r.created_at', 'r.id']],
    ];
}

function sr_member_group_rule_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_member_group_rules(PDO $pdo, int $limit = 0, int $offset = 0, array $sort = []): array
{
    if (!sr_member_groups_table_exists($pdo)) {
        return [];
    }

    $sql = 'SELECT r.*, g.group_key, g.title AS group_title
            FROM sr_member_group_rules r
            INNER JOIN sr_member_groups g ON g.id = r.group_id';
    $sql .= function_exists('sr_admin_sort_order_sql')
        ? sr_admin_sort_order_sql(sr_member_group_rule_sort_options(), $sort, sr_member_group_rule_default_sort())
        : ' ORDER BY r.id DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    if ($limit > 0) {
        $stmt->bindValue('limit_value', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_member_group_rule_by_id(PDO $pdo, int $ruleId): ?array
{
    if ($ruleId < 1 || !sr_member_groups_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_member_group_rules WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $ruleId]);
    $rule = $stmt->fetch();

    return is_array($rule) ? $rule : null;
}

function sr_member_group_rule_save(PDO $pdo, array $data): int
{
    $ruleId = (int) ($data['id'] ?? 0);
    $now = sr_now();

    if ($ruleId > 0) {
        $stmt = $pdo->prepare(
            'UPDATE sr_member_group_rules
             SET group_id = :group_id,
                 source_module_key = :source_module_key,
                 rule_key = :rule_key,
                 rule_params_json = :rule_params_json,
                 evaluation_policy = :evaluation_policy,
                 status = :status,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'group_id' => (int) $data['group_id'],
            'source_module_key' => (string) $data['source_module_key'],
            'rule_key' => (string) $data['rule_key'],
            'rule_params_json' => (string) $data['rule_params_json'],
            'evaluation_policy' => (string) $data['evaluation_policy'],
            'status' => (string) $data['status'],
            'updated_at' => $now,
            'id' => $ruleId,
        ]);

        return $ruleId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_group_rules
            (group_id, source_module_key, rule_key, rule_params_json, evaluation_policy, status, last_evaluated_at, created_at, updated_at)
         VALUES
            (:group_id, :source_module_key, :rule_key, :rule_params_json, :evaluation_policy, :status, NULL, :created_at, :updated_at)'
    );
    $stmt->execute([
        'group_id' => (int) $data['group_id'],
        'source_module_key' => (string) $data['source_module_key'],
        'rule_key' => (string) $data['rule_key'],
        'rule_params_json' => (string) $data['rule_params_json'],
        'evaluation_policy' => (string) $data['evaluation_policy'],
        'status' => (string) $data['status'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_member_group_evaluate_account(PDO $pdo, int $accountId, array $filters = []): array
{
    if ($accountId < 1 || !sr_member_groups_table_exists($pdo)) {
        return ['evaluated' => 0, 'granted' => 0, 'revoked' => 0];
    }

    $stmt = $pdo->prepare("SELECT id, status FROM sr_member_accounts WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $accountId]);
    $account = $stmt->fetch();
    if (!is_array($account) || !in_array((string) $account['status'], ['active', 'pending'], true)) {
        return ['evaluated' => 0, 'granted' => 0, 'revoked' => 0];
    }

    $definitions = sr_member_group_rule_definitions($pdo);
    $rules = sr_member_group_enabled_rules($pdo, (string) ($filters['source_module_key'] ?? ''));
    $summary = ['evaluated' => 0, 'granted' => 0, 'revoked' => 0];

    foreach ($rules as $rule) {
        $definitionKey = (string) $rule['source_module_key'] . ':' . (string) $rule['rule_key'];
        if (!isset($definitions[$definitionKey])) {
            continue;
        }

        $params = json_decode((string) $rule['rule_params_json'], true);
        if (!is_array($params)) {
            $params = [];
        }

        $evaluation = sr_member_group_call_evaluator($definitions[$definitionKey], $pdo, $accountId, $params);
        $summary['evaluated']++;

        if (!empty($evaluation['matched'])) {
            if (sr_member_group_grant_auto($pdo, $accountId, $rule, $evaluation)) {
                $summary['granted']++;
            }
        } elseif ((string) $rule['evaluation_policy'] === 'sync') {
            if (sr_member_group_revoke_auto($pdo, $accountId, $rule, $evaluation)) {
                $summary['revoked']++;
            }
        }

        $stmt = $pdo->prepare('UPDATE sr_member_group_rules SET last_evaluated_at = :last_evaluated_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'last_evaluated_at' => sr_now(),
            'updated_at' => sr_now(),
            'id' => (int) $rule['id'],
        ]);
    }

    return $summary;
}

function sr_member_group_evaluate_accounts(PDO $pdo, array $filters = []): array
{
    if (!sr_member_groups_table_exists($pdo)) {
        return ['accounts' => 0, 'evaluated' => 0, 'granted' => 0, 'revoked' => 0];
    }

    $limit = max(1, min(200, (int) ($filters['limit'] ?? 50)));
    $stmt = $pdo->prepare(
        "SELECT id
         FROM sr_member_accounts
         WHERE status IN ('active', 'pending')
         ORDER BY id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $summary = ['accounts' => 0, 'evaluated' => 0, 'granted' => 0, 'revoked' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $accountSummary = sr_member_group_evaluate_account($pdo, (int) $row['id'], $filters);
        $summary['accounts']++;
        $summary['evaluated'] += (int) $accountSummary['evaluated'];
        $summary['granted'] += (int) $accountSummary['granted'];
        $summary['revoked'] += (int) $accountSummary['revoked'];
    }

    return $summary;
}

function sr_member_group_enabled_rules(PDO $pdo, string $sourceModuleKey = ''): array
{
    if ($sourceModuleKey !== '') {
        $stmt = $pdo->prepare(
            "SELECT * FROM sr_member_group_rules
             WHERE status = 'enabled'
               AND source_module_key = :source_module_key
             ORDER BY id ASC"
        );
        $stmt->execute(['source_module_key' => $sourceModuleKey]);
        return $stmt->fetchAll();
    }

    $stmt = $pdo->query("SELECT * FROM sr_member_group_rules WHERE status = 'enabled' ORDER BY id ASC");
    return $stmt->fetchAll();
}

function sr_member_group_call_evaluator(array $definition, PDO $pdo, int $accountId, array $params): array
{
    $evaluator = (string) $definition['evaluator'];
    try {
        $result = $evaluator($pdo, $accountId, $params);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'member_group_evaluator_' . (string) $definition['rule_key']);
        return ['matched' => false, 'metric' => null, 'summary' => sr_t('member::group.evaluation.failed')];
    }

    if (!is_array($result)) {
        return ['matched' => false, 'metric' => null, 'summary' => sr_t('member::group.evaluation.empty')];
    }

    return [
        'matched' => !empty($result['matched']),
        'metric' => $result['metric'] ?? null,
        'summary' => sr_log_line_value((string) ($result['summary'] ?? ''), 120),
    ];
}

function sr_member_group_grant_auto(PDO $pdo, int $accountId, array $rule, array $evaluation): bool
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT id, status FROM sr_member_group_memberships
         WHERE account_id = :account_id
           AND group_id = :group_id
           AND assignment_type = 'auto'
           AND source_module_key = :source_module_key
           AND source_rule_key = :source_rule_key
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'group_id' => (int) $rule['group_id'],
        'source_module_key' => (string) $rule['source_module_key'],
        'source_rule_key' => (string) $rule['rule_key'],
    ]);
    $membership = $stmt->fetch();

    if (is_array($membership) && (string) $membership['status'] === 'active') {
        return false;
    }

    if (is_array($membership)) {
        $membershipId = (int) $membership['id'];
        $stmt = $pdo->prepare(
            "UPDATE sr_member_group_memberships
             SET status = 'active',
                 granted_at = COALESCE(granted_at, :granted_at),
                 revoked_at = NULL,
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute([
            'granted_at' => $now,
            'updated_at' => $now,
            'id' => $membershipId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO sr_member_group_memberships
                (group_id, account_id, assignment_type, source_module_key, source_rule_key, status, granted_at, expires_at, revoked_at, created_by_account_id, updated_at)
             VALUES
                (:group_id, :account_id, 'auto', :source_module_key, :source_rule_key, 'active', :granted_at, NULL, NULL, NULL, :updated_at)"
        );
        $stmt->execute([
            'group_id' => (int) $rule['group_id'],
            'account_id' => $accountId,
            'source_module_key' => (string) $rule['source_module_key'],
            'source_rule_key' => (string) $rule['rule_key'],
            'granted_at' => $now,
            'updated_at' => $now,
        ]);
        $membershipId = (int) $pdo->lastInsertId();
    }

    sr_member_group_log($pdo, (int) $rule['group_id'], $accountId, $membershipId, 'member.group.auto_granted', null, 'Auto group membership granted.', [
        'source_module_key' => (string) $rule['source_module_key'],
        'source_rule_key' => (string) $rule['rule_key'],
        'summary' => (string) $evaluation['summary'],
    ]);

    return true;
}

function sr_member_group_revoke_auto(PDO $pdo, int $accountId, array $rule, array $evaluation): bool
{
    $stmt = $pdo->prepare(
        "SELECT id FROM sr_member_group_memberships
         WHERE account_id = :account_id
           AND group_id = :group_id
           AND assignment_type = 'auto'
           AND source_module_key = :source_module_key
           AND source_rule_key = :source_rule_key
           AND status = 'active'
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'group_id' => (int) $rule['group_id'],
        'source_module_key' => (string) $rule['source_module_key'],
        'source_rule_key' => (string) $rule['rule_key'],
    ]);
    $membership = $stmt->fetch();
    if (!is_array($membership)) {
        return false;
    }

    $membershipId = (int) $membership['id'];
    $stmt = $pdo->prepare(
        "UPDATE sr_member_group_memberships
         SET status = 'revoked',
             revoked_at = :revoked_at,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'revoked_at' => sr_now(),
        'updated_at' => sr_now(),
        'id' => $membershipId,
    ]);

    sr_member_group_log($pdo, (int) $rule['group_id'], $accountId, $membershipId, 'member.group.auto_revoked', null, 'Auto group membership revoked.', [
        'source_module_key' => (string) $rule['source_module_key'],
        'source_rule_key' => (string) $rule['rule_key'],
        'summary' => (string) $evaluation['summary'],
    ]);

    return true;
}

function sr_member_group_memberships(PDO $pdo, int $limit = 100, int $groupId = 0): array
{
    if (!sr_member_groups_table_exists($pdo)) {
        return [];
    }

    $whereSql = $groupId > 0 ? ' WHERE m.group_id = :group_id' : '';
    $stmt = $pdo->prepare(
        'SELECT m.*, g.group_key, g.title AS group_title, a.email, a.display_name, a.status AS account_status
         FROM sr_member_group_memberships m
         INNER JOIN sr_member_groups g ON g.id = m.group_id
         INNER JOIN sr_member_accounts a ON a.id = m.account_id
         ' . $whereSql . '
         ORDER BY m.id DESC
         LIMIT :limit_value'
    );
    if ($groupId > 0) {
        $stmt->bindValue('group_id', $groupId, PDO::PARAM_INT);
    }
    $stmt->bindValue('limit_value', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_member_group_logs(PDO $pdo, int $limit = 50, int $groupId = 0): array
{
    if (!sr_member_groups_table_exists($pdo)) {
        return [];
    }

    $whereSql = $groupId > 0 ? ' WHERE l.group_id = :group_id' : '';
    $stmt = $pdo->prepare(
        'SELECT l.*, g.group_key, g.title AS group_title, a.email, a.display_name
         FROM sr_member_group_membership_logs l
         INNER JOIN sr_member_groups g ON g.id = l.group_id
         INNER JOIN sr_member_accounts a ON a.id = l.account_id
         ' . $whereSql . '
         ORDER BY l.id DESC
         LIMIT :limit_value'
    );
    if ($groupId > 0) {
        $stmt->bindValue('group_id', $groupId, PDO::PARAM_INT);
    }
    $stmt->bindValue('limit_value', max(1, min(100, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_member_group_privacy_export(PDO $pdo, int $accountId): array
{
    if (!sr_member_groups_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT g.group_key, g.title, m.assignment_type, m.source_module_key, m.source_rule_key,
                m.status, m.granted_at, m.expires_at, m.revoked_at, m.updated_at
         FROM sr_member_group_memberships m
         INNER JOIN sr_member_groups g ON g.id = m.group_id
         WHERE m.account_id = :account_id
         ORDER BY m.id ASC'
    );
    $stmt->execute(['account_id' => $accountId]);

    return $stmt->fetchAll();
}
