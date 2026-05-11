<?php

declare(strict_types=1);

function toy_community_board_key_is_valid(string $boardKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $boardKey) === 1;
}

function toy_community_board_group_key_is_valid(string $groupKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $groupKey) === 1;
}

function toy_community_board_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function toy_community_board_group_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function toy_community_policy_values(string $policy): array
{
    if ($policy === 'read') {
        return ['public', 'member', 'group'];
    }

    if ($policy === 'write') {
        return ['member', 'group', 'admin'];
    }

    if ($policy === 'comment') {
        return ['member', 'group', 'disabled'];
    }

    return [];
}

function toy_community_board_group_setting_keys(): array
{
    return [
        'read_policy',
        'write_policy',
        'comment_policy',
        'read_group_keys',
        'write_group_keys',
        'comment_group_keys',
        'image_uploads_enabled',
        'attachment_max_bytes',
        'attachment_max_count',
    ];
}

function toy_community_board_group_column_setting_keys(): array
{
    return ['read_policy', 'write_policy', 'comment_policy', 'image_uploads_enabled'];
}

function toy_community_board_setting_source_values(): array
{
    return ['board', 'group'];
}

function toy_community_normalize_board_setting_source(string $source): string
{
    return in_array($source, toy_community_board_setting_source_values(), true) ? $source : 'board';
}

function toy_community_board_select_columns(string $alias = 'b'): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';

    return $prefix . 'id, ' . $prefix . 'board_group_id, ' . $prefix . 'board_key, ' . $prefix . 'title, '
        . $prefix . 'description, ' . $prefix . 'status, ' . $prefix . 'read_policy, ' . $prefix . 'write_policy, '
        . $prefix . 'comment_policy, ' . $prefix . 'image_uploads_enabled, ' . $prefix . 'sort_order, '
        . $prefix . 'created_at, ' . $prefix . 'updated_at';
}

function toy_community_boards(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT ' . toy_community_board_select_columns('b') . ',
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM toy_community_boards b
         LEFT JOIN toy_community_board_groups g ON g.id = b.board_group_id
         ORDER BY COALESCE(g.sort_order, 1000000) ASC, g.id ASC, b.sort_order ASC, b.id ASC'
    );

    $boards = [];
    foreach ($stmt->fetchAll() as $board) {
        $boards[] = toy_community_board_with_effective_settings($pdo, $board);
    }

    return $boards;
}

function toy_community_enabled_boards(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT " . toy_community_board_select_columns('b') . ",
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM toy_community_boards b
         LEFT JOIN toy_community_board_groups g ON g.id = b.board_group_id
         WHERE b.status = 'enabled'
         ORDER BY COALESCE(g.sort_order, 1000000) ASC, g.id ASC, b.sort_order ASC, b.id ASC"
    );

    $boards = [];
    foreach ($stmt->fetchAll() as $board) {
        $boards[] = toy_community_board_with_effective_settings($pdo, $board);
    }

    return $boards;
}

function toy_community_board_by_key(PDO $pdo, string $boardKey): ?array
{
    if (!toy_community_board_key_is_valid($boardKey)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ' . toy_community_board_select_columns('b') . ',
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM toy_community_boards b
         LEFT JOIN toy_community_board_groups g ON g.id = b.board_group_id
         WHERE b.board_key = :board_key
         LIMIT 1'
    );
    $stmt->execute(['board_key' => $boardKey]);
    $board = $stmt->fetch();

    return is_array($board) ? toy_community_board_with_effective_settings($pdo, $board) : null;
}

function toy_community_board_by_id(PDO $pdo, int $boardId): ?array
{
    if ($boardId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ' . toy_community_board_select_columns('b') . ',
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM toy_community_boards b
         LEFT JOIN toy_community_board_groups g ON g.id = b.board_group_id
         WHERE b.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $boardId]);
    $board = $stmt->fetch();

    return is_array($board) ? toy_community_board_with_effective_settings($pdo, $board) : null;
}

function toy_community_board_with_effective_settings(PDO $pdo, array $board): array
{
    $board['effective_read_policy'] = toy_community_effective_board_policy($pdo, $board, 'read_policy');
    $board['effective_write_policy'] = toy_community_effective_board_policy($pdo, $board, 'write_policy');
    $board['effective_comment_policy'] = toy_community_effective_board_policy($pdo, $board, 'comment_policy');
    $board['effective_image_uploads_enabled'] = toy_community_effective_board_image_uploads_enabled($pdo, $board) ? 1 : 0;

    return $board;
}

function toy_community_create_board(PDO $pdo, array $data): int
{
    $now = toy_now();
    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_boards
            (board_group_id, board_key, title, description, status, read_policy, write_policy, comment_policy, image_uploads_enabled, sort_order, created_at, updated_at)
         VALUES
            (:board_group_id, :board_key, :title, :description, :status, :read_policy, :write_policy, :comment_policy, :image_uploads_enabled, :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute([
        'board_group_id' => (int) ($data['board_group_id'] ?? 0) > 0 ? (int) $data['board_group_id'] : null,
        'board_key' => (string) $data['board_key'],
        'title' => (string) $data['title'],
        'description' => (string) $data['description'],
        'status' => (string) $data['status'],
        'read_policy' => (string) $data['read_policy'],
        'write_policy' => (string) $data['write_policy'],
        'comment_policy' => (string) $data['comment_policy'],
        'image_uploads_enabled' => !empty($data['image_uploads_enabled']) ? 1 : 0,
        'sort_order' => (int) $data['sort_order'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function toy_community_update_board(PDO $pdo, int $boardId, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE toy_community_boards
         SET board_group_id = :board_group_id,
             title = :title,
             description = :description,
             status = :status,
             read_policy = :read_policy,
             write_policy = :write_policy,
             comment_policy = :comment_policy,
             image_uploads_enabled = :image_uploads_enabled,
             sort_order = :sort_order,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'board_group_id' => (int) ($data['board_group_id'] ?? 0) > 0 ? (int) $data['board_group_id'] : null,
        'title' => (string) $data['title'],
        'description' => (string) $data['description'],
        'status' => (string) $data['status'],
        'read_policy' => (string) $data['read_policy'],
        'write_policy' => (string) $data['write_policy'],
        'comment_policy' => (string) $data['comment_policy'],
        'image_uploads_enabled' => !empty($data['image_uploads_enabled']) ? 1 : 0,
        'sort_order' => (int) $data['sort_order'],
        'updated_at' => toy_now(),
        'id' => $boardId,
    ]);
}

function toy_community_board_groups(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT g.*,
                COUNT(b.id) AS board_count
         FROM toy_community_board_groups g
         LEFT JOIN toy_community_boards b ON b.board_group_id = g.id
         GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at
         ORDER BY g.sort_order ASC, g.id ASC'
    );

    return $stmt->fetchAll();
}

function toy_community_enabled_board_groups(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT *
         FROM toy_community_board_groups
         WHERE status = 'enabled'
         ORDER BY sort_order ASC, id ASC"
    );

    return $stmt->fetchAll();
}

function toy_community_board_group_by_id(PDO $pdo, int $groupId): ?array
{
    if ($groupId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM toy_community_board_groups WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $groupId]);
    $group = $stmt->fetch();

    return is_array($group) ? $group : null;
}

function toy_community_board_group_by_key(PDO $pdo, string $groupKey): ?array
{
    if (!toy_community_board_group_key_is_valid($groupKey)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM toy_community_board_groups WHERE group_key = :group_key LIMIT 1');
    $stmt->execute(['group_key' => $groupKey]);
    $group = $stmt->fetch();

    return is_array($group) ? $group : null;
}

function toy_community_create_board_group(PDO $pdo, array $data): int
{
    $now = toy_now();
    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_board_groups
            (group_key, title, description, status, sort_order, created_at, updated_at)
         VALUES
            (:group_key, :title, :description, :status, :sort_order, :created_at, :updated_at)'
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

function toy_community_update_board_group(PDO $pdo, int $groupId, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE toy_community_board_groups
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
        'updated_at' => toy_now(),
        'id' => $groupId,
    ]);
}

function toy_community_board_setting_value(PDO $pdo, int $boardId, string $settingKey): ?string
{
    if ($boardId < 1 || $settingKey === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT setting_value
         FROM toy_community_board_settings
         WHERE board_id = :board_id
           AND setting_key = :setting_key
         LIMIT 1'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'setting_key' => $settingKey,
    ]);
    $value = $stmt->fetchColumn();

    return is_string($value) ? $value : null;
}

function toy_community_set_board_setting(PDO $pdo, int $boardId, string $settingKey, string $settingValue, string $valueType = 'string'): void
{
    if ($boardId < 1 || $settingKey === '') {
        return;
    }

    $now = toy_now();
    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_board_settings
            (board_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:board_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'setting_key' => $settingKey,
        'setting_value' => $settingValue,
        'value_type' => $valueType,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function toy_community_board_group_setting_value(PDO $pdo, int $groupId, string $settingKey): ?string
{
    if ($groupId < 1 || !in_array($settingKey, toy_community_board_group_setting_keys(), true)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT setting_value
         FROM toy_community_board_group_settings
         WHERE group_id = :group_id
           AND setting_key = :setting_key
         LIMIT 1'
    );
    $stmt->execute([
        'group_id' => $groupId,
        'setting_key' => $settingKey,
    ]);
    $value = $stmt->fetchColumn();

    return is_string($value) ? $value : null;
}

function toy_community_set_board_group_setting(PDO $pdo, int $groupId, string $settingKey, string $settingValue, string $valueType = 'string'): void
{
    if ($groupId < 1 || !in_array($settingKey, toy_community_board_group_setting_keys(), true)) {
        return;
    }

    $now = toy_now();
    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_board_group_settings
            (group_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:group_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'group_id' => $groupId,
        'setting_key' => $settingKey,
        'setting_value' => $settingValue,
        'value_type' => $valueType,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function toy_community_board_group_settings(PDO $pdo, int $groupId): array
{
    if ($groupId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT setting_key, setting_value
         FROM toy_community_board_group_settings
         WHERE group_id = :group_id'
    );
    $stmt->execute(['group_id' => $groupId]);

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }

    return $settings;
}

function toy_community_board_setting_source(PDO $pdo, int $boardId, string $settingKey): string
{
    if ($boardId < 1 || !in_array($settingKey, toy_community_board_group_setting_keys(), true)) {
        return 'board';
    }

    $stmt = $pdo->prepare(
        'SELECT source
         FROM toy_community_board_setting_sources
         WHERE board_id = :board_id
           AND setting_key = :setting_key
         LIMIT 1'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'setting_key' => $settingKey,
    ]);
    $source = $stmt->fetchColumn();

    return toy_community_normalize_board_setting_source(is_string($source) ? $source : 'board');
}

function toy_community_board_setting_sources(PDO $pdo, int $boardId): array
{
    if ($boardId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT setting_key, source
         FROM toy_community_board_setting_sources
         WHERE board_id = :board_id'
    );
    $stmt->execute(['board_id' => $boardId]);

    $sources = [];
    foreach ($stmt->fetchAll() as $row) {
        $settingKey = (string) ($row['setting_key'] ?? '');
        if (in_array($settingKey, toy_community_board_group_setting_keys(), true)) {
            $sources[$settingKey] = toy_community_normalize_board_setting_source((string) ($row['source'] ?? 'board'));
        }
    }

    return $sources;
}

function toy_community_set_board_setting_source(PDO $pdo, int $boardId, string $settingKey, string $source): void
{
    if ($boardId < 1 || !in_array($settingKey, toy_community_board_group_setting_keys(), true)) {
        return;
    }

    $source = toy_community_normalize_board_setting_source($source);
    $now = toy_now();
    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_board_setting_sources
            (board_id, setting_key, source, created_at, updated_at)
         VALUES
            (:board_id, :setting_key, :source, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            source = VALUES(source),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'setting_key' => $settingKey,
        'source' => $source,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function toy_community_effective_board_setting(PDO $pdo, array $board, string $settingKey, mixed $default = ''): string
{
    $boardId = (int) ($board['id'] ?? 0);
    if ($boardId < 1 || !in_array($settingKey, toy_community_board_group_setting_keys(), true)) {
        return (string) $default;
    }

    $boardGroupId = (int) ($board['board_group_id'] ?? 0);
    if ($boardGroupId > 0 && toy_community_board_setting_source($pdo, $boardId, $settingKey) === 'group') {
        $groupValue = toy_community_board_group_setting_value($pdo, $boardGroupId, $settingKey);
        if (is_string($groupValue) && $groupValue !== '') {
            return $groupValue;
        }
    }

    if (in_array($settingKey, toy_community_board_group_column_setting_keys(), true)) {
        return (string) ($board[$settingKey] ?? $default);
    }

    $boardValue = toy_community_board_setting_value($pdo, $boardId, $settingKey);
    return is_string($boardValue) && $boardValue !== '' ? $boardValue : (string) $default;
}

function toy_community_effective_board_policy(PDO $pdo, array $board, string $settingKey): string
{
    $policyType = str_replace('_policy', '', $settingKey);
    $fallback = (string) ($board[$settingKey] ?? '');
    $policy = toy_community_effective_board_setting($pdo, $board, $settingKey, $fallback);

    return in_array($policy, toy_community_policy_values($policyType), true) ? $policy : $fallback;
}

function toy_community_effective_board_image_uploads_enabled(PDO $pdo, array $board): bool
{
    return in_array(toy_community_effective_board_setting($pdo, $board, 'image_uploads_enabled', (string) (int) ($board['image_uploads_enabled'] ?? 1)), ['1', 'true', 'yes', 'on'], true);
}

function toy_community_board_attachment_max_bytes(PDO $pdo, int $boardId, array $settings = []): int
{
    $default = min(10485760, max(1024, (int) ($settings['attachment_max_bytes'] ?? 2097152)));
    $board = toy_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? toy_community_effective_board_setting($pdo, $board, 'attachment_max_bytes', (string) $default)
        : toy_community_board_setting_value($pdo, $boardId, 'attachment_max_bytes');

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return min(10485760, max(1024, (int) $value));
}

function toy_community_board_attachment_max_count(PDO $pdo, int $boardId, array $settings = []): int
{
    $default = min(10, max(0, (int) ($settings['attachment_max_count'] ?? 1)));
    $board = toy_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? toy_community_effective_board_setting($pdo, $board, 'attachment_max_count', (string) $default)
        : toy_community_board_setting_value($pdo, $boardId, 'attachment_max_count');

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return min(10, max(0, (int) $value));
}

function toy_community_board_own_attachment_max_bytes(PDO $pdo, int $boardId, array $settings = []): int
{
    $default = min(10485760, max(1024, (int) ($settings['attachment_max_bytes'] ?? 2097152)));
    $value = toy_community_board_setting_value($pdo, $boardId, 'attachment_max_bytes');
    return is_string($value) && $value !== '' ? min(10485760, max(1024, (int) $value)) : $default;
}

function toy_community_board_own_attachment_max_count(PDO $pdo, int $boardId, array $settings = []): int
{
    $default = min(10, max(0, (int) ($settings['attachment_max_count'] ?? 1)));
    $value = toy_community_board_setting_value($pdo, $boardId, 'attachment_max_count');
    return is_string($value) && $value !== '' ? min(10, max(0, (int) $value)) : $default;
}

function toy_community_apply_board_group_settings_to_boards(PDO $pdo, int $groupId, array $settingKeys): int
{
    $group = toy_community_board_group_by_id($pdo, $groupId);
    if (!is_array($group)) {
        return 0;
    }

    $settingKeys = array_values(array_intersect(toy_community_board_group_setting_keys(), $settingKeys));
    if ($settingKeys === []) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT ' . toy_community_board_select_columns('') . ' FROM toy_community_boards WHERE board_group_id = :group_id');
    $stmt->execute(['group_id' => $groupId]);
    $boards = $stmt->fetchAll();
    foreach ($boards as $board) {
        $boardId = (int) ($board['id'] ?? 0);
        if ($boardId < 1) {
            continue;
        }

        $updates = [];
        $params = ['id' => $boardId, 'updated_at' => toy_now()];
        foreach (array_intersect($settingKeys, toy_community_board_group_column_setting_keys()) as $settingKey) {
            $value = toy_community_board_group_setting_value($pdo, $groupId, $settingKey);
            if (!is_string($value) || $value === '') {
                continue;
            }

            $updates[] = $settingKey . ' = :' . $settingKey;
            $params[$settingKey] = $settingKey === 'image_uploads_enabled' ? (int) in_array($value, ['1', 'true', 'yes', 'on'], true) : $value;
        }

        if ($updates !== []) {
            $stmt = $pdo->prepare(
                'UPDATE toy_community_boards
                 SET ' . implode(', ', $updates) . ',
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute($params);
        }

        foreach (array_diff($settingKeys, toy_community_board_group_column_setting_keys()) as $settingKey) {
            $value = toy_community_board_group_setting_value($pdo, $groupId, $settingKey);
            if (is_string($value)) {
                $valueType = in_array($settingKey, ['attachment_max_bytes', 'attachment_max_count'], true) ? 'int' : 'json';
                toy_community_set_board_setting($pdo, $boardId, $settingKey, $value, $valueType);
            }
        }

        foreach ($settingKeys as $settingKey) {
            toy_community_set_board_setting_source($pdo, $boardId, $settingKey, 'board');
        }
    }

    return count($boards);
}
