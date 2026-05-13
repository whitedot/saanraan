<?php

declare(strict_types=1);

function toy_admin_grant_role(PDO $pdo, int $accountId, string $roleKey): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO toy_admin_account_roles (account_id, role_key, created_at)
         VALUES (:account_id, :role_key, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'role_key' => $roleKey,
        'created_at' => toy_now(),
    ]);
}

function toy_admin_revoke_role(PDO $pdo, int $accountId, string $roleKey): void
{
    $stmt = $pdo->prepare('DELETE FROM toy_admin_account_roles WHERE account_id = :account_id AND role_key = :role_key');
    $stmt->execute([
        'account_id' => $accountId,
        'role_key' => $roleKey,
    ]);
}

function toy_admin_current_roles(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare('SELECT role_key FROM toy_admin_account_roles WHERE account_id = :account_id ORDER BY role_key ASC');
    $stmt->execute(['account_id' => $accountId]);

    $roles = [];
    foreach ($stmt->fetchAll() as $row) {
        $roles[] = (string) $row['role_key'];
    }

    return $roles;
}

function toy_admin_has_role(PDO $pdo, int $accountId, array $allowedRoles): bool
{
    $roles = toy_admin_current_roles($pdo, $accountId);
    return array_intersect($roles, $allowedRoles) !== [];
}

function toy_admin_require_role(PDO $pdo, int $accountId, array $allowedRoles): void
{
    toy_request_contract_mark('role_checked');

    if (!toy_admin_has_role($pdo, $accountId, $allowedRoles)) {
        toy_request_contract_guard_blocked('role');
        toy_render_error(403, '관리자 권한이 필요합니다.');
    }
}

function toy_admin_owner_count(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COUNT(*) AS count_value FROM toy_admin_account_roles WHERE role_key = 'owner'");
    $row = $stmt->fetch();
    return is_array($row) ? (int) $row['count_value'] : 0;
}

function toy_admin_active_owner_count(PDO $pdo): int
{
    $stmt = $pdo->query(
        "SELECT COUNT(DISTINCT r.account_id) AS count_value
         FROM toy_admin_account_roles r
         INNER JOIN toy_member_accounts a ON a.id = r.account_id
         WHERE r.role_key = 'owner'
           AND a.status = 'active'"
    );
    $row = $stmt->fetch();
    return is_array($row) ? (int) $row['count_value'] : 0;
}

function toy_admin_allowed_roles(): array
{
    return ['owner', 'admin', 'manager'];
}

function toy_admin_role_actions(): array
{
    return ['grant', 'revoke'];
}

function toy_admin_handle_roles_post(PDO $pdo, array $account, array $allowedRoles, array $allowedActions): array
{
    $errors = [];
    $notice = '';
    $targetAccountId = toy_admin_post_positive_int('account_id');
    $roleKey = toy_post_string('role_key', 40);
    $roleAction = toy_post_string('role_action', 20);

    if ($targetAccountId <= 0) {
        $errors[] = '계정을 선택하세요.';
    }

    if (!in_array($roleKey, $allowedRoles, true)) {
        $errors[] = '역할 값이 올바르지 않습니다.';
    }

    if (!in_array($roleAction, $allowedActions, true)) {
        $errors[] = '역할 작업 값이 올바르지 않습니다.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id, email, status FROM toy_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetAccountId]);
        $targetAccount = $stmt->fetch();

        if (!is_array($targetAccount)) {
            $errors[] = '계정을 찾을 수 없습니다.';
        }
    }

    if ($errors === [] && $roleAction === 'revoke' && $roleKey === 'owner') {
        $targetRoles = toy_admin_current_roles($pdo, $targetAccountId);
        if (in_array('owner', $targetRoles, true) && toy_admin_owner_count($pdo) <= 1) {
            $errors[] = '마지막 소유자 권한은 회수할 수 없습니다.';
        }

        if (
            in_array('owner', $targetRoles, true)
            && (string) $targetAccount['status'] === 'active'
            && toy_admin_active_owner_count($pdo) <= 1
        ) {
            $errors[] = '마지막 활성 소유자 권한은 회수할 수 없습니다.';
        }
    }

    if ($errors === []) {
        if ($roleAction === 'grant') {
            toy_admin_grant_role($pdo, $targetAccountId, $roleKey);
            $eventType = 'admin.role.granted';
            $notice = '관리자 역할을 부여했습니다.';
        } else {
            toy_admin_revoke_role($pdo, $targetAccountId, $roleKey);
            $eventType = 'admin.role.revoked';
            $notice = '관리자 역할을 회수했습니다.';
        }

        toy_audit_log($pdo, [
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

    return toy_admin_action_result($errors, $notice);
}

function toy_admin_role_accounts(PDO $pdo): array
{
    $accounts = [];
    $stmt = $pdo->query(
        'SELECT a.id, a.email, a.display_name, a.status, GROUP_CONCAT(r.role_key ORDER BY r.role_key SEPARATOR ",") AS role_keys
         FROM toy_member_accounts a
         LEFT JOIN toy_admin_account_roles r ON r.account_id = a.id
         GROUP BY a.id, a.email, a.display_name, a.status
         ORDER BY a.id DESC
         LIMIT 100'
    );

    foreach ($stmt->fetchAll() as $row) {
        $roleKeys = (string) ($row['role_keys'] ?? '');
        $row['roles'] = $roleKeys === '' ? [] : explode(',', $roleKeys);
        $accounts[] = $row;
    }

    return $accounts;
}
