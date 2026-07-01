<?php

declare(strict_types=1);

function sr_community_account_guard_types(): array
{
    return ['publication_hold', 'confirmed_hold', 'write_cooldown', 'needs_review'];
}

function sr_community_account_guard_statuses(): array
{
    return ['active', 'released', 'expired', 'cancelled', 'needs_review'];
}

function sr_community_account_guard_active_statuses(): array
{
    return ['active'];
}

function sr_community_account_guard_active_uid(int $accountId, string $guardType): string
{
    if ($accountId < 1 || !in_array($guardType, sr_community_account_guard_types(), true)) {
        return '';
    }

    return (string) $accountId . ':' . $guardType;
}

function sr_community_account_guard_status_is_active(string $status): bool
{
    return in_array($status, sr_community_account_guard_active_statuses(), true);
}

function sr_community_account_guard_json(array $data): string
{
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '{}';
}

function sr_community_account_guard_transition(PDO $pdo, int $guardId, string $status, array $context = []): bool
{
    if ($guardId < 1 || !in_array($status, sr_community_account_guard_statuses(), true)) {
        return false;
    }

    $now = function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s');
    $setParts = [
        'status = :status',
        'updated_at = :updated_at',
    ];
    $params = [
        'id' => $guardId,
        'status' => $status,
        'updated_at' => $now,
    ];

    if (!sr_community_account_guard_status_is_active($status)) {
        $setParts[] = 'active_guard_uid = NULL';
    }
    if ($status === 'released') {
        $setParts[] = 'released_at = :released_at';
        $params['released_at'] = $now;
    }
    $reviewerAccountId = (int) ($context['reviewer_account_id'] ?? 0);
    if ($reviewerAccountId > 0) {
        $setParts[] = 'reviewer_account_id = :reviewer_account_id';
        $params['reviewer_account_id'] = $reviewerAccountId;
    }
    if (array_key_exists('snapshot', $context)) {
        $snapshot = is_array($context['snapshot']) ? $context['snapshot'] : [];
        $setParts[] = 'snapshot_json = :snapshot_json';
        $params['snapshot_json'] = sr_community_account_guard_json($snapshot);
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_account_guards
         SET ' . implode(', ', $setParts) . '
         WHERE id = :id'
    );
    $stmt->execute($params);

    return $stmt->rowCount() > 0;
}

function sr_community_account_active_guards(PDO $pdo, int $accountId, ?string $now = null): array
{
    if ($accountId < 1) {
        return [];
    }

    $now = $now !== null && $now !== '' ? $now : (function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s'));
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_account_guards
         WHERE account_id = :account_id
           AND status = \'active\'
           AND (expires_at IS NULL OR expires_at > :now_value)
         ORDER BY id ASC'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now_value' => $now,
    ]);

    return $stmt->fetchAll();
}
