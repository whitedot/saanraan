<?php

declare(strict_types=1);

function sr_quiz_group_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_quiz_group_key_is_valid(string $groupKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $groupKey) === 1;
}

function sr_quiz_groups_table_exists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_quiz_groups LIMIT 1');
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function sr_quiz_setting_sources_table_exists(PDO $pdo): bool
{
    static $results = [];
    $objectId = spl_object_id($pdo);
    if (array_key_exists($objectId, $results)) {
        return $results[$objectId];
    }
    try {
        $pdo->query('SELECT 1 FROM sr_quiz_setting_sources LIMIT 1');
        $results[$objectId] = true;
    } catch (Throwable $exception) {
        $results[$objectId] = false;
    }
    return $results[$objectId];
}

function sr_quiz_groups(PDO $pdo, bool $enabledOnly = false): array
{
    if (!sr_quiz_groups_table_exists($pdo)) {
        return [];
    }
    $where = $enabledOnly ? "WHERE g.status = 'enabled'" : '';
    return $pdo->query(
        'SELECT g.*, COUNT(q.id) AS item_count
         FROM sr_quiz_groups g
         LEFT JOIN sr_quiz_sets q ON q.quiz_group_id = g.id AND q.deleted_at IS NULL
         ' . $where . '
         GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at
         ORDER BY g.sort_order ASC, g.id ASC'
    )->fetchAll();
}

function sr_quiz_admin_group_count(PDO $pdo): int
{
    if (!sr_quiz_groups_table_exists($pdo)) {
        return 0;
    }

    $row = $pdo->query('SELECT COUNT(*) AS count_value FROM sr_quiz_groups')->fetch();
    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_quiz_admin_group_sort_options(): array
{
    return [
        'group_key' => ['columns' => ['g.group_key', 'g.id']],
        'title' => ['columns' => ['g.title', 'g.id']],
        'status' => ['columns' => ['g.status', 'g.id']],
        'item_count' => ['columns' => ['item_count', 'g.id']],
        'sort_order' => ['columns' => ['g.sort_order', 'g.id']],
    ];
}

function sr_quiz_admin_group_default_sort(): array
{
    return sr_admin_sort_default('sort_order', 'asc');
}

function sr_quiz_admin_groups(PDO $pdo, int $limit, int $offset, array $sort): array
{
    if (!sr_quiz_groups_table_exists($pdo)) {
        return [];
    }

    $sql = 'SELECT g.*, COUNT(q.id) AS item_count
            FROM sr_quiz_groups g
            LEFT JOIN sr_quiz_sets q ON q.quiz_group_id = g.id AND q.deleted_at IS NULL
            GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at'
        . sr_admin_sort_order_sql(sr_quiz_admin_group_sort_options(), $sort, sr_quiz_admin_group_default_sort())
        . ' LIMIT :limit_value OFFSET :offset_value';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('limit_value', max(1, min(1000, $limit)), PDO::PARAM_INT);
    $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_quiz_group_by_id(PDO $pdo, int $groupId): ?array
{
    if ($groupId < 1) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_quiz_groups WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $groupId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function sr_quiz_group_key_exists(PDO $pdo, string $groupKey, int $exceptId = 0): bool
{
    $stmt = $pdo->prepare('SELECT id FROM sr_quiz_groups WHERE group_key = :group_key AND id <> :except_id LIMIT 1');
    $stmt->execute(['group_key' => $groupKey, 'except_id' => $exceptId]);
    return is_array($stmt->fetch());
}

function sr_quiz_save_group(PDO $pdo, array $values, int $groupId = 0): int
{
    $now = sr_now();
    if ($groupId > 0) {
        $stmt = $pdo->prepare('UPDATE sr_quiz_groups SET title = :title, description = :description, status = :status, sort_order = :sort_order, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'title' => (string) $values['title'],
            'description' => (string) ($values['description'] ?? ''),
            'status' => (string) $values['status'],
            'sort_order' => (int) $values['sort_order'],
            'updated_at' => $now,
            'id' => $groupId,
        ]);
        return $groupId;
    }
    $stmt = $pdo->prepare('INSERT INTO sr_quiz_groups (group_key, title, description, status, sort_order, created_at, updated_at) VALUES (:group_key, :title, :description, :status, :sort_order, :created_at, :updated_at)');
    $stmt->execute([
        'group_key' => (string) $values['group_key'],
        'title' => (string) $values['title'],
        'description' => (string) ($values['description'] ?? ''),
        'status' => (string) $values['status'],
        'sort_order' => (int) $values['sort_order'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    return (int) $pdo->lastInsertId();
}

function sr_quiz_delete_group(PDO $pdo, int $groupId): bool
{
    if (!is_array(sr_quiz_group_by_id($pdo, $groupId))) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $now = sr_now();
        if (sr_quiz_setting_sources_table_exists($pdo)) {
            $pdo->prepare(
                "UPDATE sr_quiz_setting_sources
                 SET source = 'item', updated_at = :updated_at
                 WHERE source = 'group'
                   AND quiz_id IN (SELECT id FROM sr_quiz_sets WHERE quiz_group_id = :group_id)"
            )->execute(['updated_at' => $now, 'group_id' => $groupId]);
        }
        $pdo->prepare('UPDATE sr_quiz_sets SET quiz_group_id = NULL, updated_at = :updated_at WHERE quiz_group_id = :group_id')->execute(['updated_at' => $now, 'group_id' => $groupId]);
        $pdo->prepare('DELETE FROM sr_quiz_groups WHERE id = :id')->execute(['id' => $groupId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    return true;
}

function sr_quiz_setting_scope(string $scope): string
{
    return in_array($scope, ['item', 'group', 'all'], true) ? $scope : 'item';
}

function sr_quiz_group_setting_bundles(): array
{
    return [
        'skin_key' => ['skin_key'],
        'status' => ['status'],
        'quiz_mode' => ['quiz_mode'],
        'scoring_model' => ['scoring_model'],
        'pass_score' => ['pass_score'],
        'starts_at' => ['starts_at'],
        'ends_at' => ['ends_at'],
        'attempt_limit' => ['attempt_limit_policy', 'attempt_limit_period_seconds'],
        'member_group_keys' => ['member_group_keys_json'],
        'comments_enabled' => ['comments_enabled'],
        'secret_comments_enabled' => ['secret_comments_enabled'],
        'reaction_preset_key' => ['reaction_preset_key'],
        'reaction_comment_preset_key' => ['reaction_comment_preset_key'],
        'comment_editor_key' => ['comment_editor_key'],
        'comment_extra_fields_json' => ['comment_extra_fields_json'],
        'reward' => ['reward_enabled'],
    ];
}

function sr_quiz_setting_sources(PDO $pdo, int $quizId): array
{
    if ($quizId < 1 || !sr_quiz_setting_sources_table_exists($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT setting_key, source FROM sr_quiz_setting_sources WHERE quiz_id = :quiz_id');
    $stmt->execute(['quiz_id' => $quizId]);
    $sources = [];
    foreach ($stmt->fetchAll() as $row) {
        $sources[(string) ($row['setting_key'] ?? '')] = sr_quiz_setting_scope((string) ($row['source'] ?? 'item'));
    }
    return $sources;
}

function sr_quiz_setting_source(PDO $pdo, int $quizId, string $settingKey): string
{
    return sr_quiz_setting_scope((string) (sr_quiz_setting_sources($pdo, $quizId)[$settingKey] ?? 'item'));
}

function sr_quiz_set_setting_source(PDO $pdo, int $quizId, string $settingKey, string $source): void
{
    $now = sr_now();
    $params = ['quiz_id' => $quizId, 'setting_key' => $settingKey, 'source' => sr_quiz_setting_scope($source), 'updated_at' => $now];
    $stmt = $pdo->prepare('UPDATE sr_quiz_setting_sources SET source = :source, updated_at = :updated_at WHERE quiz_id = :quiz_id AND setting_key = :setting_key');
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        return;
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO sr_quiz_setting_sources (quiz_id, setting_key, source, created_at, updated_at) VALUES (:quiz_id, :setting_key, :source, :created_at, :updated_at)');
        $stmt->execute(array_merge($params, ['created_at' => $now]));
    } catch (PDOException $exception) {
        $stmt = $pdo->prepare('UPDATE sr_quiz_setting_sources SET source = :source, updated_at = :updated_at WHERE quiz_id = :quiz_id AND setting_key = :setting_key');
        $stmt->execute($params);
    }
}

function sr_quiz_apply_group_setting_scopes(PDO $pdo, int $quizId, int $groupId, array $values, int $accountId, string $now): void
{
    foreach (sr_quiz_group_setting_bundles() as $settingKey => $columns) {
        $scope = sr_quiz_setting_scope((string) ($values['source_' . $settingKey] ?? 'item'));
        if ($scope === 'group' && $groupId < 1) {
            continue;
        }
        $where = 'id = :item_id';
        $params = ['item_id' => $quizId];
        if ($scope === 'group') {
            $where = 'quiz_group_id = :group_id AND deleted_at IS NULL';
            $params = ['group_id' => $groupId];
        } elseif ($scope === 'all') {
            $where = 'deleted_at IS NULL';
            $params = [];
        }
        $targetStmt = $pdo->prepare('SELECT id FROM sr_quiz_sets WHERE ' . $where);
        $targetStmt->execute($params);
        $targetIds = array_map('intval', array_column($targetStmt->fetchAll(), 'id'));
        if ($targetIds === []) {
            continue;
        }
        $assignments = [];
        $updateValues = [];
        foreach ($columns as $column) {
            $assignments[] = $column . ' = ?';
            if ($column === 'member_group_keys_json') {
                $encoded = json_encode(sr_quiz_member_group_keys_from_value($values['member_group_keys'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $updateValues[] = is_string($encoded) ? $encoded : '[]';
            } elseif ($column === 'comment_extra_fields_json') {
                $updateValues[] = sr_comment_extra_field_definitions_json($values[$column] ?? '[]');
            } elseif ($column === 'starts_at' || $column === 'ends_at') {
                $updateValues[] = sr_quiz_clean_admin_datetime((string) ($values[$column] ?? ''));
            } elseif ($column === 'pass_score') {
                $updateValues[] = (string) ($values[$column] ?? '') === '' ? null : (int) $values[$column];
            } elseif ($column === 'attempt_limit_period_seconds') {
                $updateValues[] = (string) ($values['attempt_limit_policy'] ?? '') === 'per_period' ? max(1, (int) ($values[$column] ?? 0)) : null;
            } else {
                $updateValues[] = $values[$column] ?? '';
            }
        }
        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        $stmt = $pdo->prepare('UPDATE sr_quiz_sets SET ' . implode(', ', $assignments) . ', updated_by_account_id = ?, updated_at = ? WHERE id IN (' . $placeholders . ')');
        $stmt->execute(array_merge($updateValues, [$accountId, $now], $targetIds));
        if ($settingKey === 'reward') {
            foreach ($targetIds as $targetId) {
                if ($targetId === $quizId) {
                    continue;
                }
                $pdo->prepare('DELETE FROM sr_quiz_reward_policies WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $targetId]);
                $pdo->prepare('INSERT INTO sr_quiz_reward_policies (quiz_id, status, trigger_type, result_id, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, sort_order, settings_json, created_at, updated_at) SELECT :target_id, status, trigger_type, NULL, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, sort_order, settings_json, :created_at, :updated_at FROM sr_quiz_reward_policies WHERE quiz_id = :source_id')->execute(['target_id' => $targetId, 'created_at' => $now, 'updated_at' => $now, 'source_id' => $quizId]);
            }
        }
        foreach ($targetIds as $targetId) {
            sr_quiz_set_setting_source($pdo, $targetId, $settingKey, 'item');
        }
        sr_quiz_set_setting_source($pdo, $quizId, $settingKey, $scope);
    }
}
