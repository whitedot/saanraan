<?php

declare(strict_types=1);

function sr_community_board_manager_permission_options(): array
{
    return [
        'view_manage' => '관리권한 목록 조회',
        'delete_post' => '게시글 삭제',
        'remove_post_og_image' => '게시글 OG 이미지 제거',
    ];
}

function sr_community_board_manager_permission_is_valid(string $permissionKey): bool
{
    return array_key_exists($permissionKey, sr_community_board_manager_permission_options());
}

function sr_community_board_managers(PDO $pdo, int $boardId): array
{
    if ($boardId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT m.id, m.board_id, m.account_id, m.permission_key, m.status, m.created_by, m.updated_by, m.created_at, m.updated_at,
                a.display_name, a.status AS account_status,
                n.nickname
         FROM sr_community_board_managers m
         INNER JOIN sr_member_accounts a ON a.id = m.account_id
         LEFT JOIN sr_member_nicknames n ON n.account_id = a.id
         WHERE m.board_id = :board_id
           AND m.status = 'active'
         ORDER BY m.account_id ASC, m.permission_key ASC"
    );
    $stmt->execute(['board_id' => $boardId]);

    return $stmt->fetchAll();
}

function sr_community_account_has_board_management_permission(PDO $pdo, int $boardId, int $accountId, string $permissionKey): bool
{
    if ($boardId < 1 || $accountId < 1 || !sr_community_board_manager_permission_is_valid($permissionKey)) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT 1
         FROM sr_community_board_managers
         WHERE board_id = :board_id
           AND account_id = :account_id
           AND permission_key = :permission_key
           AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute([
        'board_id' => $boardId,
        'account_id' => $accountId,
        'permission_key' => $permissionKey,
    ]);

    return (bool) $stmt->fetchColumn();
}

function sr_community_grant_board_management_permissions(PDO $pdo, int $boardId, int $accountId, array $permissionKeys, int $actorAccountId): array
{
    $granted = [];
    if ($boardId < 1 || $accountId < 1) {
        return $granted;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        "INSERT INTO sr_community_board_managers
            (board_id, account_id, permission_key, status, created_by, updated_by, created_at, updated_at)
         VALUES
            (:board_id, :account_id, :permission_key, 'active', :created_by, :updated_by, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            status = 'active',
            updated_by = VALUES(updated_by),
            updated_at = VALUES(updated_at)"
    );
    foreach ($permissionKeys as $permissionKey) {
        $permissionKey = (string) $permissionKey;
        if (!sr_community_board_manager_permission_is_valid($permissionKey)) {
            continue;
        }

        $stmt->execute([
            'board_id' => $boardId,
            'account_id' => $accountId,
            'permission_key' => $permissionKey,
            'created_by' => $actorAccountId > 0 ? $actorAccountId : null,
            'updated_by' => $actorAccountId > 0 ? $actorAccountId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $granted[] = $permissionKey;
    }

    return array_values(array_unique($granted));
}

function sr_community_revoke_board_management_permission(PDO $pdo, int $managerId, int $boardId, int $actorAccountId): ?array
{
    if ($managerId < 1 || $boardId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, board_id, account_id, permission_key, status
         FROM sr_community_board_managers
         WHERE id = :id
           AND board_id = :board_id
         LIMIT 1"
    );
    $stmt->execute(['id' => $managerId, 'board_id' => $boardId]);
    $manager = $stmt->fetch();
    if (!is_array($manager)) {
        return null;
    }

    $update = $pdo->prepare(
        "UPDATE sr_community_board_managers
         SET status = 'revoked',
             updated_by = :updated_by,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $update->execute([
        'updated_by' => $actorAccountId > 0 ? $actorAccountId : null,
        'updated_at' => sr_now(),
        'id' => $managerId,
    ]);

    return $manager;
}

