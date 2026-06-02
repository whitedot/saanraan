<?php

declare(strict_types=1);

function sr_admin_grant_role(PDO $pdo, int $accountId, string $roleKey): void
{
    if ($roleKey !== 'owner') {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_admin_account_roles (account_id, role_key, created_at)
         VALUES (:account_id, :role_key, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'role_key' => 'owner',
        'created_at' => sr_now(),
    ]);
}

function sr_admin_revoke_role(PDO $pdo, int $accountId, string $roleKey): void
{
    if ($roleKey !== 'owner') {
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM sr_admin_account_roles WHERE account_id = :account_id AND role_key = :role_key');
    $stmt->execute([
        'account_id' => $accountId,
        'role_key' => 'owner',
    ]);
}

function sr_admin_locked_owner_rows(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT r.account_id, a.status
         FROM sr_admin_account_roles r
         INNER JOIN sr_member_accounts a ON a.id = r.account_id
         WHERE r.role_key = 'owner'
         FOR UPDATE"
    );

    return $stmt->fetchAll();
}

function sr_admin_current_roles(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare("SELECT role_key FROM sr_admin_account_roles WHERE account_id = :account_id AND role_key = 'owner' ORDER BY role_key ASC");
    $stmt->execute(['account_id' => $accountId]);

    $roles = [];
    foreach ($stmt->fetchAll() as $row) {
        $roles[] = (string) $row['role_key'];
    }

    return $roles;
}

function sr_admin_is_owner(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT 1 FROM sr_admin_account_roles WHERE account_id = :account_id AND role_key = 'owner' LIMIT 1");
    $stmt->execute(['account_id' => $accountId]);

    return (bool) $stmt->fetchColumn();
}

function sr_admin_has_role(PDO $pdo, int $accountId, array $allowedRoles): bool
{
    return in_array('owner', $allowedRoles, true) && sr_admin_is_owner($pdo, $accountId);
}

function sr_admin_has_admin_access(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1) {
        return false;
    }

    if (sr_admin_is_owner($pdo, $accountId)) {
        return true;
    }

    if (!sr_admin_permissions_table_exists($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM sr_admin_account_permissions
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);

    return (bool) $stmt->fetchColumn();
}

function sr_admin_require_role(PDO $pdo, int $accountId, array $allowedRoles): void
{
    sr_admin_require_owner($pdo, $accountId);
}

function sr_admin_require_owner(PDO $pdo, int $accountId): void
{
    sr_request_contract_mark('role_checked');

    if (!sr_admin_is_owner($pdo, $accountId)) {
        sr_request_contract_guard_blocked('role');
        sr_render_error(403, sr_t('admin::auth.role_required'));
    }
}

function sr_admin_permission_actions(): array
{
    return ['view', 'edit', 'delete'];
}

function sr_admin_permission_action_requires_view(string $actionKey): bool
{
    return in_array($actionKey, ['edit', 'delete'], true);
}

function sr_admin_normalize_permission_action(string $actionKey): string
{
    return in_array($actionKey, sr_admin_permission_actions(), true) ? $actionKey : '';
}

function sr_admin_permissions_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_admin_account_permissions LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_admin_normalize_permission_path(string $menuPath): string
{
    $menuPath = trim($menuPath);
    if (preg_match('/\A\/admin(?:\/[a-z0-9][a-z0-9_-]*)*\z/', $menuPath) !== 1) {
        return '';
    }

    return $menuPath;
}

function sr_admin_permission_token(string $menuPath, string $actionKey): string
{
    $menuPath = sr_admin_normalize_permission_path($menuPath);
    $actionKey = sr_admin_normalize_permission_action($actionKey);
    if ($menuPath === '' || $actionKey === '') {
        return '';
    }

    return $menuPath . '|' . $actionKey;
}

function sr_admin_parse_permission_token(string $token): array
{
    $parts = explode('|', trim($token), 2);
    if (count($parts) !== 2) {
        return ['', ''];
    }

    $menuPath = sr_admin_normalize_permission_path($parts[0]);
    $actionKey = sr_admin_normalize_permission_action($parts[1]);
    if ($menuPath === '' || $actionKey === '') {
        return ['', ''];
    }

    return [$menuPath, $actionKey];
}

function sr_admin_current_permission_keys(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !sr_admin_permissions_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT menu_path, action_key FROM sr_admin_account_permissions WHERE account_id = :account_id ORDER BY menu_path ASC, action_key ASC');
    $stmt->execute(['account_id' => $accountId]);

    $tokens = [];
    foreach ($stmt->fetchAll() as $row) {
        $token = sr_admin_permission_token((string) $row['menu_path'], (string) $row['action_key']);
        if ($token !== '') {
            $tokens[] = $token;
        }
    }

    return $tokens;
}

function sr_admin_current_permission_map(PDO $pdo, int $accountId): array
{
    $map = [];
    foreach (sr_admin_current_permission_keys($pdo, $accountId) as $token) {
        [$menuPath, $actionKey] = sr_admin_parse_permission_token($token);
        if ($menuPath !== '' && $actionKey !== '') {
            $map[$menuPath][$actionKey] = true;
        }
    }

    return $map;
}

function sr_admin_has_permission(PDO $pdo, int $accountId, string $menuPath, string $actionKey = 'view'): bool
{
    $menuPath = sr_admin_normalize_permission_path($menuPath);
    $actionKey = sr_admin_normalize_permission_action($actionKey);
    if ($menuPath === '' || $actionKey === '' || $accountId < 1) {
        return false;
    }

    if ($menuPath === '/admin' && $actionKey === 'view') {
        return sr_admin_has_admin_access($pdo, $accountId);
    }

    if (sr_admin_is_owner($pdo, $accountId)) {
        return true;
    }

    $ownerOnlyPaths = sr_admin_owner_only_permission_keys();
    if (isset($ownerOnlyPaths[$menuPath])) {
        return false;
    }

    if (!sr_admin_permissions_table_exists($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM sr_admin_account_permissions
         WHERE account_id = :account_id
           AND menu_path = :menu_path
           AND action_key = :action_key
         LIMIT 1'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'menu_path' => $menuPath,
        'action_key' => $actionKey,
    ]);

    return (bool) $stmt->fetchColumn();
}

function sr_admin_require_permission(PDO $pdo, int $accountId, string $menuPath, string $actionKey = 'view'): void
{
    sr_request_contract_mark('role_checked');

    if (!sr_admin_has_permission($pdo, $accountId, $menuPath, $actionKey)) {
        sr_request_contract_guard_blocked('permission');
        sr_render_error(403, sr_t('admin::auth.role_required'));
    }
}

function sr_admin_sync_account_permissions(PDO $pdo, int $accountId, array $permissionTokens): void
{
    if ($accountId < 1 || !sr_admin_permissions_table_exists($pdo)) {
        return;
    }

    $allowedMap = sr_admin_permission_option_map($pdo);
    $selectedMap = [];
    foreach ($permissionTokens as $permissionToken) {
        [$menuPath, $actionKey] = sr_admin_parse_permission_token(is_string($permissionToken) ? $permissionToken : '');
        if ($menuPath !== '' && $actionKey !== '' && isset($allowedMap[$menuPath])) {
            $selectedMap[$menuPath . '|' . $actionKey] = [$menuPath, $actionKey];
            if (sr_admin_permission_action_requires_view($actionKey)) {
                $selectedMap[$menuPath . '|view'] = [$menuPath, 'view'];
            }
        }
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $delete = $pdo->prepare('DELETE FROM sr_admin_account_permissions WHERE account_id = :account_id');
        $delete->execute(['account_id' => $accountId]);

        $insert = $pdo->prepare(
            'INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
             VALUES (:account_id, :menu_path, :action_key, :created_at)'
        );
        foreach ($selectedMap as $permission) {
            $insert->execute([
                'account_id' => $accountId,
                'menu_path' => $permission[0],
                'action_key' => $permission[1],
                'created_at' => sr_now(),
            ]);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_admin_owner_count(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COUNT(*) AS count_value FROM sr_admin_account_roles WHERE role_key = 'owner'");
    $row = $stmt->fetch();
    return is_array($row) ? (int) $row['count_value'] : 0;
}

function sr_admin_active_owner_count(PDO $pdo): int
{
    $stmt = $pdo->query(
        "SELECT COUNT(DISTINCT r.account_id) AS count_value
         FROM sr_admin_account_roles r
         INNER JOIN sr_member_accounts a ON a.id = r.account_id
         WHERE r.role_key = 'owner'
           AND a.status = 'active'"
    );
    $row = $stmt->fetch();
    return is_array($row) ? (int) $row['count_value'] : 0;
}

function sr_admin_permission_options(PDO $pdo): array
{
    if (!function_exists('sr_admin_navigation_source_groups')) {
        return [];
    }

    $ownerOnlyPaths = sr_admin_owner_only_permission_keys();
    $groups = [];
    foreach (sr_admin_navigation_source_groups($pdo) as $group) {
        if (!is_array($group)) {
            continue;
        }

        $categoryLabel = trim((string) ($group['label'] ?? ''));
        foreach ((array) ($group['module_groups'] ?? []) as $moduleGroup) {
            if (!is_array($moduleGroup)) {
                continue;
            }

            $moduleLabel = trim((string) ($moduleGroup['label'] ?? ''));
            if ($moduleLabel === '') {
                $moduleLabel = (string) ($moduleGroup['module_key'] ?? '');
            }

            $items = [];
            foreach ((array) ($moduleGroup['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $path = sr_admin_normalize_permission_path((string) ($item['path'] ?? ''));
                $label = trim((string) ($item['label'] ?? ''));
                if ($path === '' || $label === '' || isset($ownerOnlyPaths[$path])) {
                    continue;
                }

                $items[$path] = [
                    'key' => $path,
                    'label' => $label,
                    'path' => $path,
                    'actions' => sr_admin_permission_actions(),
                ];
            }

            if ($items === []) {
                continue;
            }

            $groups[] = [
                'category_label' => $categoryLabel,
                'module_key' => (string) ($moduleGroup['module_key'] ?? ''),
                'label' => $moduleLabel,
                'items' => array_values($items),
            ];
        }
    }

    return $groups;
}

function sr_admin_permission_option_map(PDO $pdo): array
{
    $map = [];
    foreach (sr_admin_permission_options($pdo) as $group) {
        foreach ((array) ($group['items'] ?? []) as $item) {
            $key = (string) ($item['key'] ?? '');
            if ($key !== '') {
                $map[$key] = $item;
            }
        }
    }

    return $map;
}

function sr_admin_owner_only_permission_keys(): array
{
    return [
        '/admin/menu' => true,
        '/admin/modules' => true,
        '/admin/roles' => true,
        '/admin/updates' => true,
        '/admin/retention' => true,
    ];
}

function sr_admin_post_permission_keys(PDO $pdo): array
{
    $permissionKeys = $_POST['permission_keys'] ?? [];
    if (!is_array($permissionKeys)) {
        return [];
    }

    $allowedMap = sr_admin_permission_option_map($pdo);
    $selectedMap = [];
    foreach ($permissionKeys as $permissionKey) {
        [$menuPath, $actionKey] = sr_admin_parse_permission_token(is_string($permissionKey) ? $permissionKey : '');
        if ($menuPath !== '' && $actionKey !== '' && isset($allowedMap[$menuPath])) {
            $selectedMap[$menuPath . '|' . $actionKey] = true;
            if (sr_admin_permission_action_requires_view($actionKey)) {
                $selectedMap[$menuPath . '|view'] = true;
            }
        }
    }

    $selectedKeys = array_keys($selectedMap);
    sort($selectedKeys);
    return $selectedKeys;
}

function sr_admin_post_permission_keys_valid(PDO $pdo): bool
{
    $permissionKeys = $_POST['permission_keys'] ?? [];
    if ($permissionKeys === []) {
        return true;
    }

    if (!is_array($permissionKeys)) {
        return false;
    }

    $allowedMap = sr_admin_permission_option_map($pdo);
    foreach ($permissionKeys as $permissionKey) {
        [$menuPath, $actionKey] = sr_admin_parse_permission_token(is_string($permissionKey) ? $permissionKey : '');
        if ($menuPath === '' || $actionKey === '' || !isset($allowedMap[$menuPath])) {
            return false;
        }
    }

    return true;
}

function sr_admin_permission_filter(PDO $pdo): string
{
    $permissionFilter = sr_get_string('permission', 230);
    if ($permissionFilter === '') {
        return '';
    }

    if (in_array($permissionFilter, ['any', 'none', 'owner'], true)) {
        return $permissionFilter;
    }

    [$menuPath, $actionKey] = sr_admin_parse_permission_token($permissionFilter);
    $allowedMap = sr_admin_permission_option_map($pdo);

    return $menuPath !== '' && $actionKey !== '' && isset($allowedMap[$menuPath]) ? $menuPath . '|' . $actionKey : '';
}

function sr_admin_permission_filter_has_conditions(string $statusFilter, string $permissionFilter, array $searchFilter): bool
{
    return $statusFilter !== ''
        || $permissionFilter !== ''
        || trim((string) ($searchFilter['keyword'] ?? '')) !== '';
}

function sr_admin_permission_filter_url(string $statusFilter, string $permissionFilter, array $searchFilter): string
{
    $query = [];
    if ($statusFilter !== '') {
        $query['status'] = $statusFilter;
    }

    if ($permissionFilter !== '') {
        $query['permission'] = $permissionFilter;
    }

    if ((string) ($searchFilter['field'] ?? 'all') !== 'all') {
        $query['field'] = (string) $searchFilter['field'];
    }

    if (trim((string) ($searchFilter['keyword'] ?? '')) !== '') {
        $query['q'] = (string) $searchFilter['keyword'];
    }

    return '/admin/roles' . ($query === [] ? '' : '?' . http_build_query($query));
}

function sr_admin_handle_permissions_post(PDO $pdo, array $account): array
{
    $errors = [];
    $notice = '';
    $intent = sr_post_string('intent', 40);
    $targetAccountId = sr_admin_post_positive_int('account_id');
    $selectedIsOwner = ($_POST['is_owner'] ?? '') === '1';
    $selectedPermissionKeys = sr_admin_post_permission_keys($pdo);
    $requestedPermissionKeys = $selectedPermissionKeys;

    if (!in_array($intent, ['', 'add_permission', 'revoke_permission'], true)) {
        $errors[] = sr_t('admin::action.roles.intent_invalid');
    }

    if ($targetAccountId <= 0) {
        $errors[] = sr_t('admin::action.roles.account_required');
    }

    if (!sr_admin_post_permission_keys_valid($pdo)) {
        $errors[] = sr_t('admin::action.roles.permission_invalid');
    }

    if ($intent === 'add_permission' && !$selectedIsOwner && $selectedPermissionKeys === []) {
        $errors[] = sr_t('admin::action.roles.permission_required');
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id, email, status FROM sr_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetAccountId]);
        $targetAccount = $stmt->fetch();

        if (!is_array($targetAccount)) {
            $errors[] = sr_t('admin::action.roles.account_not_found');
        }
    }

    if ($errors === []) {
        $beforeIsOwner = sr_admin_is_owner($pdo, $targetAccountId);
        $beforePermissionKeys = sr_admin_current_permission_keys($pdo, $targetAccountId);
    }

    if ($errors === [] && $intent === 'add_permission') {
        $selectedIsOwner = $beforeIsOwner || $selectedIsOwner;
        if ($beforeIsOwner && $requestedPermissionKeys !== []) {
            $errors[] = sr_t('admin::action.roles.owner_permission_redundant');
        } elseif ($selectedIsOwner) {
            $selectedPermissionKeys = [];
        } else {
            $selectedPermissionKeys = array_values(array_unique(array_merge($beforePermissionKeys, $selectedPermissionKeys)));
            sort($selectedPermissionKeys);
        }
    }

    if ($errors === [] && $intent === 'revoke_permission') {
        $selectedIsOwner = false;
        $selectedPermissionKeys = [];
    }

    if ($errors === [] && (string) $targetAccount['status'] !== 'active') {
        $addsOwnerRole = $selectedIsOwner && !$beforeIsOwner;
        $addsPermissionKeys = array_values(array_diff($selectedPermissionKeys, $beforePermissionKeys));
        if ($addsOwnerRole || $addsPermissionKeys !== []) {
            $errors[] = sr_t('admin::action.roles.inactive_account_grant_disallowed');
        }
    }

    if ($errors === [] && $intent !== 'add_permission' && $selectedIsOwner) {
        $selectedPermissionKeys = [];
    }

    if ($errors === [] && $beforeIsOwner && !$selectedIsOwner) {
        if (sr_admin_owner_count($pdo) <= 1) {
            $errors[] = sr_t('admin::action.roles.last_owner_revoke_disallowed');
        } elseif ((string) $targetAccount['status'] === 'active' && sr_admin_active_owner_count($pdo) <= 1) {
            $errors[] = sr_t('admin::action.roles.last_active_owner_revoke_disallowed');
        }
    }

    if ($errors === []) {
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            if ($beforeIsOwner && !$selectedIsOwner) {
                $lockedOwnerRows = sr_admin_locked_owner_rows($pdo);
                $lockedOwnerCount = count($lockedOwnerRows);
                $lockedActiveOwnerCount = 0;
                foreach ($lockedOwnerRows as $lockedOwnerRow) {
                    if ((string) ($lockedOwnerRow['status'] ?? '') === 'active') {
                        $lockedActiveOwnerCount++;
                    }
                }

                if ($lockedOwnerCount <= 1) {
                    $errors[] = sr_t('admin::action.roles.last_owner_revoke_disallowed');
                } elseif ((string) $targetAccount['status'] === 'active' && $lockedActiveOwnerCount <= 1) {
                    $errors[] = sr_t('admin::action.roles.last_active_owner_revoke_disallowed');
                }
            }

            if ($errors === []) {
                if ($selectedIsOwner) {
                    sr_admin_grant_role($pdo, $targetAccountId, 'owner');
                } else {
                    sr_admin_revoke_role($pdo, $targetAccountId, 'owner');
                }
                sr_admin_sync_account_permissions($pdo, $targetAccountId, $selectedPermissionKeys);

                $afterIsOwner = sr_admin_is_owner($pdo, $targetAccountId);
                $afterPermissionKeys = sr_admin_current_permission_keys($pdo, $targetAccountId);
                if ($beforeIsOwner === $afterIsOwner && $beforePermissionKeys === $afterPermissionKeys) {
                    $notice = sr_t('admin::action.roles.no_changes');
                } else {
                    sr_audit_log($pdo, [
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'admin',
                        'event_type' => 'admin.permissions.changed',
                        'target_type' => 'member_account',
                        'target_id' => (string) $targetAccountId,
                        'result' => 'success',
                        'message' => 'Admin permissions changed.',
                        'metadata' => [
                            'before_owner' => $beforeIsOwner,
                            'after_owner' => $afterIsOwner,
                            'before_permissions' => $beforePermissionKeys,
                            'after_permissions' => $afterPermissionKeys,
                        ],
                    ]);

                    $notice = $intent === 'revoke_permission'
                        ? sr_t('admin::action.roles.revoked')
                        : sr_t('admin::action.roles.saved');
                }
            }

            if ($ownsTransaction) {
                if ($errors === []) {
                    $pdo->commit();
                } else {
                    $pdo->rollBack();
                }
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    return sr_admin_action_result($errors, $notice);
}

function sr_admin_permission_account_query_parts(PDO $pdo, string $statusFilter = '', array $searchFilter = [], string $permissionFilter = ''): array
{
    $where = [];
    $having = '';
    $params = [];

    if ($statusFilter !== '') {
        $where[] = 'a.status = :status';
        $params['status'] = $statusFilter;
    }

    $field = (string) ($searchFilter['field'] ?? 'all');
    $keyword = trim((string) ($searchFilter['keyword'] ?? ''));
    $accountId = (int) ($searchFilter['account_id'] ?? 0);
    if ($keyword !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
        if ($field === 'hash') {
            $where[] = $accountId > 0 ? 'a.id = :account_id' : '1 = 0';
            if ($accountId > 0) {
                $params['account_id'] = $accountId;
            }
        } elseif ($field === 'email') {
            $where[] = 'a.email LIKE :keyword_like';
            $params['keyword_like'] = $like;
        } elseif ($field === 'name') {
            $where[] = '(a.display_name LIKE :keyword_like OR n.nickname LIKE :keyword_like)';
            $params['keyword_like'] = $like;
        } else {
            $clauses = ['a.email LIKE :keyword_email_like', 'a.display_name LIKE :keyword_name_like', 'n.nickname LIKE :keyword_nickname_like'];
            $params['keyword_email_like'] = $like;
            $params['keyword_name_like'] = $like;
            $params['keyword_nickname_like'] = $like;
            if ($accountId > 0) {
                $clauses[] = 'a.id = :account_id';
                $params['account_id'] = $accountId;
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
    }

    if ($permissionFilter === 'any') {
        $having = 'HAVING owner_count > 0 OR permission_count > 0';
    } elseif ($permissionFilter === 'none') {
        $having = 'HAVING owner_count = 0 AND permission_count = 0';
    } elseif ($permissionFilter === 'owner') {
        $having = 'HAVING owner_count > 0';
    } elseif ($permissionFilter !== '') {
        [$filterPath, $filterAction] = sr_admin_parse_permission_token($permissionFilter);
        $having = 'HAVING owner_count > 0 OR SUM(CASE WHEN p.menu_path = :permission_path AND p.action_key = :permission_action THEN 1 ELSE 0 END) > 0';
        $params['permission_path'] = $filterPath;
        $params['permission_action'] = $filterAction;
    }

    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
    $permissionJoin = sr_admin_permissions_table_exists($pdo)
        ? 'LEFT JOIN sr_admin_account_permissions p ON p.account_id = a.id'
        : 'LEFT JOIN (SELECT NULL AS account_id, NULL AS menu_path, NULL AS action_key) p ON 1 = 0';

    return [
        'where_sql' => $whereSql,
        'permission_join' => $permissionJoin,
        'having' => $having,
        'params' => $params,
    ];
}

function sr_admin_permission_account_count(PDO $pdo, string $statusFilter = '', array $searchFilter = [], string $permissionFilter = ''): int
{
    $queryParts = sr_admin_permission_account_query_parts($pdo, $statusFilter, $searchFilter, $permissionFilter);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM (
             SELECT a.id,
                    COUNT(DISTINCT r.id) AS owner_count,
                    COUNT(DISTINCT CONCAT(p.menu_path, "|", p.action_key)) AS permission_count
             FROM sr_member_accounts a
             LEFT JOIN sr_member_nicknames n ON n.account_id = a.id
             LEFT JOIN sr_admin_account_roles r ON r.account_id = a.id AND r.role_key = "owner"
             ' . $queryParts['permission_join'] . '
             ' . $queryParts['where_sql'] . '
             GROUP BY a.id
             ' . $queryParts['having'] . '
         ) permission_accounts'
    );
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_admin_permission_accounts(PDO $pdo, string $statusFilter = '', array $searchFilter = [], string $permissionFilter = '', int $limit = 100, int $offset = 0, array $sort = []): array
{
    $accounts = [];
    $queryParts = sr_admin_permission_account_query_parts($pdo, $statusFilter, $searchFilter, $permissionFilter);
    $limitSql = $limit > 0 ? ' LIMIT :limit_value OFFSET :offset_value' : '';
    $stmt = $pdo->prepare(
        'SELECT a.id, a.email, a.display_name, a.status, COALESCE(n.nickname, \'\') AS nickname,
                COUNT(DISTINCT r.id) AS owner_count,
                COUNT(DISTINCT CONCAT(p.menu_path, "|", p.action_key)) AS permission_count,
                GROUP_CONCAT(DISTINCT CONCAT(p.menu_path, "|", p.action_key) ORDER BY p.menu_path, p.action_key SEPARATOR ",") AS permission_keys
         FROM sr_member_accounts a
         LEFT JOIN sr_member_nicknames n ON n.account_id = a.id
         LEFT JOIN sr_admin_account_roles r ON r.account_id = a.id AND r.role_key = "owner"
         ' . $queryParts['permission_join'] . '
         ' . $queryParts['where_sql'] . '
         GROUP BY a.id, a.email, a.display_name, a.status, n.nickname
         ' . $queryParts['having'] . '
         ' . sr_admin_sort_order_sql(sr_admin_permission_account_sort_options(), $sort, sr_admin_permission_account_default_sort())
        . $limitSql
    );
    foreach ($queryParts['params'] as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    foreach ($stmt->fetchAll() as $row) {
        $permissionKeys = (string) ($row['permission_keys'] ?? '');
        $row['is_owner'] = (int) ($row['owner_count'] ?? 0) > 0;
        $row['permission_keys'] = $permissionKeys === '' ? [] : explode(',', $permissionKeys);
        $accounts[] = $row;
    }

    return $accounts;
}
