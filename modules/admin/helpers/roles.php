<?php

declare(strict_types=1);

function sr_admin_grant_role(PDO $pdo, int $accountId, string $roleKey): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_admin_account_roles (account_id, role_key, created_at)
         VALUES (:account_id, :role_key, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'role_key' => $roleKey,
        'created_at' => sr_now(),
    ]);
}

function sr_admin_revoke_role(PDO $pdo, int $accountId, string $roleKey): void
{
    $stmt = $pdo->prepare('DELETE FROM sr_admin_account_roles WHERE account_id = :account_id AND role_key = :role_key');
    $stmt->execute([
        'account_id' => $accountId,
        'role_key' => $roleKey,
    ]);
}

function sr_admin_current_roles(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare('SELECT role_key FROM sr_admin_account_roles WHERE account_id = :account_id ORDER BY role_key ASC');
    $stmt->execute(['account_id' => $accountId]);

    $roles = [];
    foreach ($stmt->fetchAll() as $row) {
        $roles[] = (string) $row['role_key'];
    }

    return $roles;
}

function sr_admin_has_role(PDO $pdo, int $accountId, array $allowedRoles): bool
{
    $roles = sr_admin_current_roles($pdo, $accountId);
    return array_intersect($roles, $allowedRoles) !== [];
}

function sr_admin_require_role(PDO $pdo, int $accountId, array $allowedRoles): void
{
    sr_request_contract_mark('role_checked');

    if (!sr_admin_has_role($pdo, $accountId, $allowedRoles)) {
        sr_request_contract_guard_blocked('role');
        sr_render_error(403, sr_t('admin::auth.role_required'));
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

function sr_admin_allowed_roles(): array
{
    return ['owner', 'admin', 'manager'];
}

function sr_admin_role_actions(): array
{
    return ['grant', 'revoke'];
}

function sr_admin_post_role_keys(array $allowedRoles): array
{
    $roleKeys = $_POST['role_keys'] ?? [];
    if (!is_array($roleKeys)) {
        return [];
    }

    $selectedMap = [];
    foreach ($roleKeys as $roleKey) {
        $roleKey = is_string($roleKey) ? trim($roleKey) : '';
        if (in_array($roleKey, $allowedRoles, true)) {
            $selectedMap[$roleKey] = true;
        }
    }

    $selectedRoles = [];
    foreach ($allowedRoles as $allowedRole) {
        if (isset($selectedMap[$allowedRole])) {
            $selectedRoles[] = $allowedRole;
        }
    }

    return array_values($selectedRoles);
}

function sr_admin_post_role_keys_valid(array $allowedRoles): bool
{
    $roleKeys = $_POST['role_keys'] ?? [];
    if ($roleKeys === []) {
        return true;
    }

    if (!is_array($roleKeys)) {
        return false;
    }

    foreach ($roleKeys as $roleKey) {
        if (!is_string($roleKey) || !in_array(trim($roleKey), $allowedRoles, true)) {
            return false;
        }
    }

    return true;
}

function sr_admin_role_filter(array $allowedRoles): string
{
    $roleFilter = sr_get_string('role', 40);
    if ($roleFilter === '') {
        return '';
    }

    if (in_array($roleFilter, array_merge(['any', 'none'], $allowedRoles), true)) {
        return $roleFilter;
    }

    return '';
}

function sr_admin_role_filter_has_conditions(string $statusFilter, string $roleFilter, array $searchFilter): bool
{
    return $statusFilter !== ''
        || $roleFilter !== ''
        || trim((string) ($searchFilter['keyword'] ?? '')) !== '';
}

function sr_admin_role_filter_url(string $statusFilter, string $roleFilter, array $searchFilter): string
{
    $query = [];
    if ($statusFilter !== '') {
        $query['status'] = $statusFilter;
    }

    if ($roleFilter !== '') {
        $query['role'] = $roleFilter;
    }

    if ((string) ($searchFilter['field'] ?? 'all') !== 'all') {
        $query['field'] = (string) $searchFilter['field'];
    }

    if (trim((string) ($searchFilter['keyword'] ?? '')) !== '') {
        $query['q'] = (string) $searchFilter['keyword'];
    }

    return '/admin/roles' . ($query === [] ? '' : '?' . http_build_query($query));
}

function sr_admin_handle_roles_post(PDO $pdo, array $account, array $allowedRoles, array $allowedActions): array
{
    $errors = [];
    $notice = '';
    $targetAccountId = sr_admin_post_positive_int('account_id');
    $roleKey = sr_post_string('role_key', 40);
    $roleAction = sr_post_string('role_action', 20);
    $intent = sr_post_string('intent', 40);
    $selectedRoles = sr_admin_post_role_keys($allowedRoles);

    if ($targetAccountId <= 0) {
        $errors[] = '계정을 선택하세요.';
    }

    if ($intent === 'sync_roles') {
        if (!sr_admin_post_role_keys_valid($allowedRoles)) {
            $errors[] = '역할 값이 올바르지 않습니다.';
        }
    } else {
        if (!in_array($roleKey, $allowedRoles, true)) {
            $errors[] = '역할 값이 올바르지 않습니다.';
        }

        if (!in_array($roleAction, $allowedActions, true)) {
            $errors[] = '역할 작업 값이 올바르지 않습니다.';
        }
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id, email, status FROM sr_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetAccountId]);
        $targetAccount = $stmt->fetch();

        if (!is_array($targetAccount)) {
            $errors[] = '계정을 찾을 수 없습니다.';
        }
    }

    if ($errors === []) {
        $targetRoles = array_values(array_intersect($allowedRoles, sr_admin_current_roles($pdo, $targetAccountId)));
    }

    if ($errors === [] && $intent === 'sync_roles' && in_array('owner', $targetRoles, true) && !in_array('owner', $selectedRoles, true)) {
        if (sr_admin_owner_count($pdo) <= 1) {
            $errors[] = '마지막 소유자 권한은 회수할 수 없습니다.';
        } elseif ((string) $targetAccount['status'] === 'active' && sr_admin_active_owner_count($pdo) <= 1) {
            $errors[] = '마지막 활성 소유자 권한은 회수할 수 없습니다.';
        }
    }

    if ($errors === [] && $intent !== 'sync_roles' && $roleAction === 'revoke' && $roleKey === 'owner') {
        if (in_array('owner', $targetRoles, true) && sr_admin_owner_count($pdo) <= 1) {
            $errors[] = '마지막 소유자 권한은 회수할 수 없습니다.';
        } elseif (
            in_array('owner', $targetRoles, true)
            && (string) $targetAccount['status'] === 'active'
            && sr_admin_active_owner_count($pdo) <= 1
        ) {
            $errors[] = '마지막 활성 소유자 권한은 회수할 수 없습니다.';
        }
    }

    if ($errors === [] && $intent === 'sync_roles') {
        $beforeRoles = $targetRoles;
        $grantedRoles = array_values(array_diff($selectedRoles, $beforeRoles));
        $revokedRoles = array_values(array_diff($beforeRoles, $selectedRoles));

        foreach ($grantedRoles as $grantRole) {
            sr_admin_grant_role($pdo, $targetAccountId, $grantRole);
        }

        foreach ($revokedRoles as $revokeRole) {
            sr_admin_revoke_role($pdo, $targetAccountId, $revokeRole);
        }

        if ($grantedRoles === [] && $revokedRoles === []) {
            $notice = '관리자 역할 변경 사항이 없습니다.';
        } else {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'admin.role.changed',
                'target_type' => 'member_account',
                'target_id' => (string) $targetAccountId,
                'result' => 'success',
                'message' => 'Admin roles changed.',
                'metadata' => [
                    'before_roles' => $beforeRoles,
                    'after_roles' => $selectedRoles,
                    'granted_roles' => $grantedRoles,
                    'revoked_roles' => $revokedRoles,
                ],
            ]);

            $notice = '관리자 역할을 저장했습니다.';
        }
    } elseif ($errors === []) {
        if ($roleAction === 'grant') {
            sr_admin_grant_role($pdo, $targetAccountId, $roleKey);
            $eventType = 'admin.role.granted';
            $notice = '관리자 역할을 부여했습니다.';
        } else {
            sr_admin_revoke_role($pdo, $targetAccountId, $roleKey);
            $eventType = 'admin.role.revoked';
            $notice = '관리자 역할을 회수했습니다.';
        }

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => $eventType,
            'target_type' => 'member_account',
            'target_id' => (string) $targetAccountId,
            'result' => 'success',
            'message' => 'Admin role changed.',
            'metadata' => [
                'role_key' => $roleKey,
                'action' => $roleAction,
            ],
        ]);
    }

    return sr_admin_action_result($errors, $notice);
}

function sr_admin_role_accounts(PDO $pdo, string $statusFilter = '', array $searchFilter = [], string $roleFilter = ''): array
{
    $accounts = [];
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
            $where[] = 'a.display_name LIKE :keyword_like';
            $params['keyword_like'] = $like;
        } else {
            $clauses = ['a.email LIKE :keyword_email_like', 'a.display_name LIKE :keyword_name_like'];
            $params['keyword_email_like'] = $like;
            $params['keyword_name_like'] = $like;
            if ($accountId > 0) {
                $clauses[] = 'a.id = :account_id';
                $params['account_id'] = $accountId;
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
    }

    if ($roleFilter === 'any') {
        $having = 'HAVING COUNT(r.role_key) > 0';
    } elseif ($roleFilter === 'none') {
        $having = 'HAVING COUNT(r.role_key) = 0';
    } elseif ($roleFilter !== '') {
        $having = 'HAVING SUM(CASE WHEN r.role_key = :role_filter THEN 1 ELSE 0 END) > 0';
        $params['role_filter'] = $roleFilter;
    }

    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
    $stmt = $pdo->prepare(
        'SELECT a.id, a.email, a.display_name, a.status, GROUP_CONCAT(r.role_key ORDER BY r.role_key SEPARATOR ",") AS role_keys
         FROM sr_member_accounts a
         LEFT JOIN sr_admin_account_roles r ON r.account_id = a.id
         ' . $whereSql . '
         GROUP BY a.id, a.email, a.display_name, a.status
         ' . $having . '
         ORDER BY a.id DESC
         LIMIT 100'
    );
    $stmt->execute($params);

    foreach ($stmt->fetchAll() as $row) {
        $roleKeys = (string) ($row['role_keys'] ?? '');
        $row['roles'] = $roleKeys === '' ? [] : explode(',', $roleKeys);
        $accounts[] = $row;
    }

    return $accounts;
}
