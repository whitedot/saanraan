<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers.php';

function sr_reaction_allowed_target_map(): array
{
    return [
        'content/content' => true,
        'content/comment' => true,
        'community/post' => true,
        'community/comment' => true,
        'quiz/quiz_set' => true,
        'quiz/comment' => true,
        'survey/survey_form' => true,
        'survey/comment' => true,
    ];
}

function sr_reaction_clean_key(string $value, int $maxLength = 80): string
{
    $value = strtolower(trim($value));
    if ($value === '' || preg_match('/\A[a-z][a-z0-9_]{0,' . max(0, $maxLength - 1) . '}\z/', $value) !== 1) {
        return '';
    }

    return $value;
}

function sr_reaction_target_id(string $value): string
{
    $value = trim($value);
    return preg_match('/\A[1-9][0-9]*\z/', $value) === 1 ? $value : '';
}

function sr_reaction_target_key(string $targetModule, string $targetType): string
{
    $targetModule = sr_reaction_clean_key($targetModule, 60);
    $targetType = sr_reaction_clean_key($targetType, 60);
    if ($targetModule === '' || $targetType === '') {
        return '';
    }

    $key = $targetModule . '/' . $targetType;
    return isset(sr_reaction_allowed_target_map()[$key]) ? $key : '';
}

function sr_reaction_lock_clause(PDO $pdo): string
{
    try {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    } catch (Throwable) {
        return ' FOR UPDATE';
    }
}

function sr_reaction_tables_available(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_reaction_definitions LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_reaction_presets LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_reaction_preset_items LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_reaction_records LIMIT 1');
    } catch (Throwable) {
        return false;
    }

    return true;
}

function sr_reaction_rate_limits_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_rate_limits LIMIT 1');
        $exists = true;
    } catch (Throwable) {
        $exists = false;
    }

    return $exists;
}

function sr_reaction_write_rate_limit_window_seconds(PDO $pdo): int
{
    return min(3600, max(10, (int) sr_site_setting($pdo, 'reaction_write_window_seconds', '60')));
}

function sr_reaction_write_rate_limited(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1 || !sr_reaction_rate_limits_table_exists($pdo)) {
        return false;
    }

    $limit = min(1000, max(1, (int) sr_site_setting($pdo, 'reaction_write_account_limit', '120')));
    return sr_rate_limit_count($pdo, 'reaction.write.account', (string) $accountId, sr_reaction_write_rate_limit_window_seconds($pdo)) >= $limit;
}

function sr_reaction_record_write_rate_limit(PDO $pdo, int $accountId): void
{
    if ($accountId < 1 || !sr_reaction_rate_limits_table_exists($pdo)) {
        return;
    }

    sr_rate_limit_increment($pdo, 'reaction.write.account', (string) $accountId, sr_reaction_write_rate_limit_window_seconds($pdo));
}

function sr_reaction_normalize_target(array $target, string $targetModule, string $targetType, string $targetId): array
{
    $status = (string) ($target['status'] ?? 'broken');
    if (!in_array($status, ['active', 'private', 'deleted', 'broken'], true)) {
        $status = 'broken';
    }

    $canView = array_key_exists('can_view', $target)
        ? (bool) $target['can_view']
        : ($status === 'active' && !empty($target['public_url']));
    $canWrite = array_key_exists('can_write', $target)
        ? (bool) $target['can_write']
        : ($status === 'active' && $canView);
    $ownerAccountId = (int) ($target['owner_account_id'] ?? ($target['author_account_id'] ?? 0));
    $recipientAccountId = (int) ($target['recipient_account_id'] ?? ($target['notification_account_id'] ?? $ownerAccountId));
    $reactionKeys = [];
    foreach (($target['reaction_keys'] ?? []) as $key) {
        if (is_string($key)) {
            $cleanKey = sr_reaction_clean_key($key);
            if ($cleanKey !== '') {
                $reactionKeys[] = $cleanKey;
            }
        }
    }

    return [
        'found' => $status !== 'broken',
        'target_module' => $targetModule,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'status' => $status,
        'can_view' => $canView,
        'can_write' => $canWrite,
        'owner_account_id' => $ownerAccountId,
        'recipient_account_id' => $recipientAccountId,
        'notification_enabled' => !array_key_exists('notification_enabled', $target) || (bool) $target['notification_enabled'],
        'preset_key' => sr_reaction_clean_key((string) ($target['preset_key'] ?? '')),
        'reaction_keys' => array_values(array_unique($reactionKeys)),
        'label' => (string) ($target['label'] ?? ($target['label_snapshot'] ?? '')),
        'public_url' => (string) ($target['public_url'] ?? ''),
        'admin_url' => (string) ($target['admin_url'] ?? ''),
    ];
}

function sr_reaction_target_contract(PDO $pdo, string $targetModule, string $targetType): ?array
{
    foreach (sr_enabled_module_contract_files($pdo, 'reaction-targets.php', ['reaction']) as $moduleKey => $file) {
        $contract = sr_load_module_contract_file($moduleKey, $file);
        $targets = is_array($contract['targets'] ?? null) ? $contract['targets'] : [];
        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }

            if ((string) ($target['target_module'] ?? '') === $targetModule && (string) ($target['target_type'] ?? '') === $targetType) {
                return $target;
            }
        }
    }

    return null;
}

function sr_reaction_resolve_target(PDO $pdo, string $targetModule, string $targetType, string $targetId, int $viewerAccountId, array $context = []): ?array
{
    if (isset($context['resolved_target']) && is_array($context['resolved_target'])) {
        return sr_reaction_normalize_target($context['resolved_target'], $targetModule, $targetType, $targetId);
    }

    $contract = sr_reaction_target_contract($pdo, $targetModule, $targetType);
    if (!is_array($contract)) {
        return null;
    }

    $resolve = $contract['resolve'] ?? null;
    if (!is_callable($resolve)) {
        return null;
    }

    try {
        $target = $resolve($pdo, [
            'target_module' => $targetModule,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'viewer_account_id' => $viewerAccountId,
            'context' => (string) ($context['context'] ?? 'public'),
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'reaction_target_resolve');
        return null;
    }

    return is_array($target) ? sr_reaction_normalize_target($target, $targetModule, $targetType, $targetId) : null;
}

function sr_reaction_default_preset_key(PDO $pdo): string
{
    $key = sr_reaction_clean_key((string) sr_site_setting($pdo, 'reaction_default_preset_key', 'emotions'));
    return $key !== '' ? $key : 'emotions';
}

function sr_reaction_active_definition(PDO $pdo, string $reactionKey): ?array
{
    $reactionKey = sr_reaction_clean_key($reactionKey);
    if ($reactionKey === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_reaction_definitions
         WHERE reaction_key = :reaction_key
           AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute(['reaction_key' => $reactionKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_reaction_allowed_keys(PDO $pdo, array $target): array
{
    $explicitKeys = isset($target['reaction_keys']) && is_array($target['reaction_keys']) ? $target['reaction_keys'] : [];
    if ($explicitKeys !== []) {
        $keys = [];
        foreach ($explicitKeys as $key) {
            if (is_string($key)) {
                $cleanKey = sr_reaction_clean_key($key);
                if ($cleanKey !== '') {
                    $keys[] = $cleanKey;
                }
            }
        }
        return array_values(array_unique($keys));
    }

    $presetKey = sr_reaction_clean_key((string) ($target['preset_key'] ?? ''));
    if ($presetKey === '') {
        $presetKey = sr_reaction_default_preset_key($pdo);
    }

    $stmt = $pdo->prepare(
        "SELECT i.reaction_key
         FROM sr_reaction_preset_items i
         INNER JOIN sr_reaction_presets p ON p.preset_key = i.preset_key
         INNER JOIN sr_reaction_definitions d ON d.reaction_key = i.reaction_key
         WHERE i.preset_key = :preset_key
           AND i.is_public = 1
           AND p.status = 'active'
           AND p.selection_policy = 'single'
           AND d.status = 'active'
         ORDER BY i.sort_order ASC, i.id ASC
         LIMIT 12"
    );
    $stmt->execute(['preset_key' => $presetKey]);
    $keys = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = sr_reaction_clean_key((string) ($row['reaction_key'] ?? ''));
        if ($key !== '') {
            $keys[] = $key;
        }
    }

    return array_values(array_unique($keys));
}

function sr_reaction_my_record(PDO $pdo, int $accountId, string $targetModule, string $targetType, string $targetId, bool $lock = false): ?array
{
    if ($accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_reaction_records
         WHERE account_id = :account_id
           AND target_module = :target_module
           AND target_type = :target_type
           AND target_id = :target_id
         LIMIT 1' . ($lock ? sr_reaction_lock_clause($pdo) : '')
    );
    $stmt->execute([
        'account_id' => $accountId,
        'target_module' => $targetModule,
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_reaction_counts(PDO $pdo, string $targetModule, string $targetType, string $targetId, array $allowedKeys = []): array
{
    $params = [
        'target_module' => $targetModule,
        'target_type' => $targetType,
        'target_id' => $targetId,
    ];
    $where = '';
    if ($allowedKeys !== []) {
        $placeholders = [];
        foreach (array_values($allowedKeys) as $index => $key) {
            $param = 'reaction_key_' . (string) $index;
            $placeholders[] = ':' . $param;
            $params[$param] = $key;
        }
        $where = ' AND reaction_key IN (' . implode(', ', $placeholders) . ')';
    }

    $stmt = $pdo->prepare(
        'SELECT reaction_key, COUNT(*) AS count_value
         FROM sr_reaction_records
         WHERE target_module = :target_module
           AND target_type = :target_type
           AND target_id = :target_id' . $where . '
         GROUP BY reaction_key'
    );
    $stmt->execute($params);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = sr_reaction_clean_key((string) ($row['reaction_key'] ?? ''));
        if ($key !== '') {
            $counts[$key] = (int) ($row['count_value'] ?? 0);
        }
    }

    return $counts;
}

function sr_reaction_create_account_event(PDO $pdo, int $recipientAccountId, int $actorAccountId, array $target, string $reactionKey): bool
{
    if ($recipientAccountId < 1 || $actorAccountId < 1 || $recipientAccountId === $actorAccountId) {
        return false;
    }
    if ((string) ($target['status'] ?? '') !== 'active' || empty($target['can_write']) || empty($target['notification_enabled'])) {
        return false;
    }

    $createAccountEventFunction = sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
    if ($createAccountEventFunction === '') {
        return false;
    }

    try {
        return $createAccountEventFunction($pdo, [
            'account_id' => $recipientAccountId,
            'module_key' => 'reaction',
            'event_key' => 'target.reacted',
            'created_by_account_id' => $actorAccountId,
            'metadata' => [
                'reaction_key' => $reactionKey,
                'target_module' => (string) ($target['target_module'] ?? ''),
                'target_type' => (string) ($target['target_type'] ?? ''),
                'target_id' => (string) ($target['target_id'] ?? ''),
                'target_label' => (string) ($target['label'] ?? ''),
                'link_url' => (string) ($target['public_url'] ?? ''),
            ],
        ]) !== null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'reaction_notification_event_create');
    }

    return false;
}

function sr_reaction_write(PDO $pdo, int $accountId, string $targetModule, string $targetType, string $targetId, string $reactionKey, string $intent = 'toggle', array $context = []): array
{
    $targetModule = sr_reaction_clean_key($targetModule, 60);
    $targetType = sr_reaction_clean_key($targetType, 60);
    $targetId = sr_reaction_target_id($targetId);
    $reactionKey = sr_reaction_clean_key($reactionKey);
    $intent = in_array($intent, ['apply', 'cancel', 'toggle'], true) ? $intent : 'toggle';
    $targetKey = sr_reaction_target_key($targetModule, $targetType);

    $result = [
        'ok' => false,
        'error' => '',
        'changed' => false,
        'operation' => 'none',
        'my_reaction_key' => '',
        'counts' => [],
        'notification_created' => false,
    ];

    if ($accountId < 1) {
        $result['error'] = 'login_required';
        return $result;
    }
    if ($targetKey === '' || $targetId === '') {
        $result['error'] = 'invalid_target';
        return $result;
    }
    if ($reactionKey === '') {
        $result['error'] = 'invalid_reaction';
        return $result;
    }
    if (!sr_reaction_tables_available($pdo)) {
        $result['error'] = 'not_available';
        return $result;
    }

    $isCancelIntent = $intent === 'cancel';
    $target = sr_reaction_resolve_target($pdo, $targetModule, $targetType, $targetId, $accountId, $context);
    if (!$isCancelIntent) {
        if (!is_array($target)) {
            $result['error'] = 'target_contract_missing';
            return $result;
        }
        if ((int) ($target['owner_account_id'] ?? 0) === $accountId) {
            $result['error'] = 'self_reaction_not_allowed';
            return $result;
        }
        if ((string) ($target['status'] ?? '') !== 'active' || empty($target['can_view']) || empty($target['can_write'])) {
            $result['error'] = 'target_not_writable';
            return $result;
        }

        $allowedKeys = sr_reaction_allowed_keys($pdo, $target);
        if (!in_array($reactionKey, $allowedKeys, true) || sr_reaction_active_definition($pdo, $reactionKey) === null) {
            $result['error'] = 'reaction_not_allowed';
            return $result;
        }
    } else {
        $allowedKeys = [];
        if (is_array($target) && !empty($target['can_view'])) {
            $allowedKeys = sr_reaction_allowed_keys($pdo, $target);
        }
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $now = sr_now();
        $existing = sr_reaction_my_record($pdo, $accountId, $targetModule, $targetType, $targetId, true);
        $existingKey = is_array($existing) ? sr_reaction_clean_key((string) ($existing['reaction_key'] ?? '')) : '';
        $nextKey = $existingKey;

        if ($intent === 'cancel' || ($intent === 'toggle' && $existingKey === $reactionKey)) {
            if ($existingKey !== '') {
                $stmt = $pdo->prepare('DELETE FROM sr_reaction_records WHERE id = :id');
                $stmt->execute(['id' => (int) ($existing['id'] ?? 0)]);
                $result['changed'] = $stmt->rowCount() > 0;
                $result['operation'] = 'cancel';
            } else {
                $result['operation'] = 'noop';
            }
            $nextKey = '';
        } elseif ($existingKey === $reactionKey) {
            $result['operation'] = 'noop';
        } elseif ($existingKey !== '') {
            $stmt = $pdo->prepare(
                'UPDATE sr_reaction_records
                 SET reaction_key = :reaction_key,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'reaction_key' => $reactionKey,
                'updated_at' => $now,
                'id' => (int) ($existing['id'] ?? 0),
            ]);
            $result['changed'] = $stmt->rowCount() > 0;
            $result['operation'] = 'change';
            $nextKey = $reactionKey;
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_reaction_records
                    (account_id, target_module, target_type, target_id, reaction_key, created_at, updated_at)
                 VALUES
                    (:account_id, :target_module, :target_type, :target_id, :reaction_key, :created_at, :updated_at)'
            );
            $stmt->execute([
                'account_id' => $accountId,
                'target_module' => $targetModule,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'reaction_key' => $reactionKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $result['changed'] = true;
            $result['operation'] = 'apply';
            $nextKey = $reactionKey;
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        $result['ok'] = true;
        $result['my_reaction_key'] = $nextKey;
        $result['counts'] = sr_reaction_counts($pdo, $targetModule, $targetType, $targetId, $allowedKeys);
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_log_exception($exception, 'reaction_write');
        $result['error'] = 'write_failed';
        return $result;
    }

    if ($result['ok'] && $result['changed'] && in_array($result['operation'], ['apply', 'change'], true) && is_array($target)) {
        $result['notification_created'] = sr_reaction_create_account_event(
            $pdo,
            (int) ($target['recipient_account_id'] ?? 0),
            $accountId,
            $target,
            $reactionKey
        );
    }

    return $result;
}
