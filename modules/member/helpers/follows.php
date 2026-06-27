<?php

declare(strict_types=1);

function sr_member_follows_table_exists(PDO $pdo): bool
{
    static $cache = [];

    $cacheKey = (string) spl_object_id($pdo);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_member_follows LIMIT 1');
        $cache[$cacheKey] = true;
    } catch (Throwable) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function sr_member_follow_status(PDO $pdo, int $followerAccountId, int $followingAccountId): string
{
    if ($followerAccountId < 1 || $followingAccountId < 1 || !sr_member_follows_table_exists($pdo)) {
        return '';
    }

    $stmt = $pdo->prepare(
        'SELECT status
         FROM sr_member_follows
         WHERE follower_account_id = :follower_account_id
           AND following_account_id = :following_account_id
         LIMIT 1'
    );
    $stmt->execute([
        'follower_account_id' => $followerAccountId,
        'following_account_id' => $followingAccountId,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? (string) ($row['status'] ?? '') : '';
}

function sr_member_is_following(PDO $pdo, int $followerAccountId, int $followingAccountId): bool
{
    return sr_member_follow_status($pdo, $followerAccountId, $followingAccountId) === 'active';
}

function sr_member_followers(PDO $pdo, int $followingAccountId): array
{
    if ($followingAccountId < 1 || !sr_member_follows_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT follower_account_id
         FROM sr_member_follows
         WHERE following_account_id = :following_account_id
           AND status = 'active'
         ORDER BY id ASC"
    );
    $stmt->execute(['following_account_id' => $followingAccountId]);

    $accountIds = [];
    foreach ($stmt->fetchAll() as $row) {
        if (is_array($row) && (int) ($row['follower_account_id'] ?? 0) > 0) {
            $accountIds[] = (int) $row['follower_account_id'];
        }
    }

    return $accountIds;
}

function sr_member_follow_account(PDO $pdo, int $followerAccountId, int $followingAccountId): bool
{
    if ($followerAccountId < 1 || $followingAccountId < 1 || $followerAccountId === $followingAccountId || !sr_member_follows_table_exists($pdo)) {
        return false;
    }

    $now = sr_now();
    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = '';
    }

    $upsertClause = "ON DUPLICATE KEY UPDATE status = 'active', updated_at = VALUES(updated_at)";
    if ($driver === 'sqlite') {
        $upsertClause = "ON CONFLICT(follower_account_id, following_account_id) DO UPDATE SET status = 'active', updated_at = excluded.updated_at";
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_follows
            (follower_account_id, following_account_id, status, created_at, updated_at)
         VALUES
            (:follower_account_id, :following_account_id, :status, :created_at, :updated_at)
         ' . $upsertClause
    );
    $stmt->execute([
        'follower_account_id' => $followerAccountId,
        'following_account_id' => $followingAccountId,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return true;
}

function sr_member_unfollow_account(PDO $pdo, int $followerAccountId, int $followingAccountId): bool
{
    if ($followerAccountId < 1 || $followingAccountId < 1 || !sr_member_follows_table_exists($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_member_follows
         SET status = 'inactive', updated_at = :updated_at
         WHERE follower_account_id = :follower_account_id
           AND following_account_id = :following_account_id"
    );
    $stmt->execute([
        'updated_at' => sr_now(),
        'follower_account_id' => $followerAccountId,
        'following_account_id' => $followingAccountId,
    ]);

    return $stmt->rowCount() > 0;
}

function sr_member_follow_target_from_hash(PDO $pdo, array $config, string $publicHash): ?array
{
    $target = sr_member_public_account_summary_by_hash($pdo, $config, strtolower(trim($publicHash)));
    if (!is_array($target) || (int) ($target['id'] ?? 0) < 1) {
        return null;
    }
    if (in_array((string) ($target['status'] ?? ''), ['withdrawn', 'anonymized'], true)) {
        return null;
    }

    return $target;
}
