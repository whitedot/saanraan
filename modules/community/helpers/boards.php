<?php

declare(strict_types=1);

function sr_community_board_key_is_valid(string $boardKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $boardKey) === 1;
}

function sr_community_board_group_key_is_valid(string $groupKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $groupKey) === 1;
}

function sr_community_board_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_community_board_group_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_community_policy_values(string $policy): array
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

function sr_community_board_group_setting_keys(): array
{
    return [
        'status',
        'skin_key',
        'post_editor',
        'read_policy',
        'write_policy',
        'comment_policy',
        'read_group_keys',
        'write_group_keys',
        'comment_group_keys',
        'read_min_level',
        'write_min_level',
        'comment_min_level',
        'category_required',
        'level_post_score',
        'level_comment_score',
        'image_uploads_enabled',
        'attachment_max_bytes',
        'attachment_max_count',
        'file_uploads_enabled',
        'file_attachment_max_bytes',
        'file_attachment_max_count',
        'file_allowed_extensions',
        'banner_before_list_id',
        'banner_after_list_id',
        'banner_before_view_id',
        'banner_after_view_id',
        'banner_before_form_id',
        'banner_after_form_id',
        'popup_layer_list_id',
        'popup_layer_view_id',
        'popup_layer_form_id',
    ];
}

function sr_community_asset_setting_prefixes(): array
{
    return ['post_reward', 'comment_reward', 'write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'];
}

function sr_community_board_group_asset_setting_keys(): array
{
    $keys = [];
    foreach (sr_community_asset_setting_prefixes() as $prefix) {
        $keys[] = $prefix . '_enabled';
        $keys[] = $prefix . '_asset_module';
        $keys[] = $prefix . '_amount';
        $keys[] = $prefix . '_group_policies_json';
        $keys[] = $prefix . '_policy_set_id';
        if (in_array($prefix, ['write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'], true)) {
            $keys[] = $prefix . '_amounts_json';
        }
    }
    $keys[] = 'paid_read_charge_policy';
    $keys[] = 'paid_attachment_download_charge_policy';

    return $keys;
}

function sr_community_board_group_all_setting_keys(): array
{
    return array_values(array_unique(array_merge(
        sr_community_board_group_setting_keys(),
        sr_community_board_group_asset_setting_keys()
    )));
}

function sr_community_board_group_copy_setting_keys_for_new_board(): array
{
    return array_values(array_diff(sr_community_board_group_all_setting_keys(), ['status', 'skin_key']));
}

function sr_community_board_group_default_settings(array $settings): array
{
    $fileAllowedExtensions = $settings['file_allowed_extensions'] ?? ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp'];
    if (is_array($fileAllowedExtensions)) {
        $fileAllowedExtensions = implode(',', array_map('strval', $fileAllowedExtensions));
    }

    $defaults = [
        'post_editor' => sr_community_post_editor_key((string) ($settings['post_editor'] ?? 'textarea')),
        'read_policy' => 'public',
        'write_policy' => 'member',
        'comment_policy' => 'member',
        'read_group_keys' => '[]',
        'write_group_keys' => '[]',
        'comment_group_keys' => '[]',
        'read_min_level' => '0',
        'write_min_level' => '0',
        'comment_min_level' => '0',
        'category_required' => !empty($settings['category_required']) ? '1' : '0',
        'level_post_score' => (string) min(10000, max(0, (int) ($settings['level_post_score'] ?? 10))),
        'level_comment_score' => (string) min(10000, max(0, (int) ($settings['level_comment_score'] ?? 2))),
        'image_uploads_enabled' => !empty($settings['image_uploads_enabled']) ? '1' : '0',
        'attachment_max_bytes' => (string) min(10485760, max(1024, (int) ($settings['attachment_max_bytes'] ?? 2097152))),
        'attachment_max_count' => (string) min(10, max(0, (int) ($settings['attachment_max_count'] ?? 1))),
        'file_uploads_enabled' => !empty($settings['file_uploads_enabled']) ? '1' : '0',
        'file_attachment_max_bytes' => (string) min(20971520, max(1024, (int) ($settings['file_attachment_max_bytes'] ?? 5242880))),
        'file_attachment_max_count' => (string) min(5, max(0, (int) ($settings['file_attachment_max_count'] ?? 3))),
        'file_allowed_extensions' => (string) $fileAllowedExtensions,
    ];

    foreach (sr_community_public_display_setting_labels() as $settingKey => $settingLabel) {
        $defaults[(string) $settingKey] = '0';
    }

    foreach (sr_community_board_group_asset_setting_keys() as $settingKey) {
        $value = $settings[(string) $settingKey] ?? (str_ends_with((string) $settingKey, '_asset_module') ? '' : '0');
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_array($value)) {
            $value = implode(',', array_map('strval', $value));
        }

        $defaults[(string) $settingKey] = (string) $value;
    }

    return $defaults;
}

function sr_community_board_default_settings(array $settings, array $groupSettings = []): array
{
    $defaults = sr_community_board_group_default_settings($settings);
    $defaults['status'] = (string) ($settings['board_status'] ?? 'enabled');
    $defaults['skin_key'] = 'basic';

    foreach (sr_community_board_group_copy_setting_keys_for_new_board() as $settingKey) {
        if (array_key_exists((string) $settingKey, $groupSettings)) {
            $defaults[(string) $settingKey] = (string) $groupSettings[(string) $settingKey];
        }
    }

    $arrayKeys = ['read_group_keys', 'write_group_keys', 'comment_group_keys'];
    foreach ($arrayKeys as $settingKey) {
        $value = (string) ($defaults[$settingKey] ?? '');
        $decoded = json_decode($value, true);
        $defaults[$settingKey] = sr_community_normalize_board_group_keys(is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value));
    }

    $fileAllowedExtensions = (string) ($defaults['file_allowed_extensions'] ?? '');
    $defaults['file_allowed_extensions'] = sr_community_file_extensions_from_input($fileAllowedExtensions);

    foreach (sr_community_board_group_setting_keys() as $settingKey) {
        $defaults['source_' . (string) $settingKey] = 'board';
    }
    foreach (sr_community_asset_setting_keys() as $settingKey) {
        $defaults['source_' . (string) $settingKey] = 'board';
    }

    return $defaults;
}

function sr_community_public_banner_setting_labels(): array
{
    return [
        'banner_before_list_id' => sr_t('community::display.banner_before_list'),
        'banner_after_list_id' => sr_t('community::display.banner_after_list'),
        'banner_before_view_id' => sr_t('community::display.banner_before_view'),
        'banner_after_view_id' => sr_t('community::display.banner_after_view'),
        'banner_before_form_id' => sr_t('community::display.banner_before_form'),
        'banner_after_form_id' => sr_t('community::display.banner_after_form'),
    ];
}

function sr_community_public_popup_layer_setting_labels(): array
{
    return [
        'popup_layer_list_id' => sr_t('community::display.popup_layer_list'),
        'popup_layer_view_id' => sr_t('community::display.popup_layer_view'),
        'popup_layer_form_id' => sr_t('community::display.popup_layer_form'),
    ];
}

function sr_community_asset_setting_label(string $assetPrefix): string
{
    $labels = [
        'post_reward' => sr_t('community::asset_setting.post_reward'),
        'comment_reward' => sr_t('community::asset_setting.comment_reward'),
        'write_charge' => sr_t('community::asset_setting.write_charge'),
        'comment_charge' => sr_t('community::asset_setting.comment_charge'),
        'paid_read' => sr_t('community::asset_setting.paid_read'),
        'paid_attachment_download' => sr_t('community::asset_setting.paid_attachment_download'),
    ];

    return (string) ($labels[$assetPrefix] ?? $assetPrefix);
}

function sr_community_public_display_setting_labels(): array
{
    return sr_community_public_banner_setting_labels() + sr_community_public_popup_layer_setting_labels();
}

function sr_community_board_group_column_setting_keys(): array
{
    return ['status', 'read_policy', 'write_policy', 'comment_policy', 'image_uploads_enabled'];
}

function sr_community_board_setting_source_values(): array
{
    return ['board', 'group', 'all'];
}

function sr_community_normalize_board_setting_source(string $source): string
{
    if ($source === 'here_only') {
        return 'board';
    }

    return in_array($source, sr_community_board_setting_source_values(), true) ? $source : 'board';
}

function sr_community_board_scope_target_ids(PDO $pdo, int $boardId, int $boardGroupId, string $source): array
{
    if ($boardId < 1) {
        return [];
    }

    $source = sr_community_normalize_board_setting_source($source);
    if ($source === 'all') {
        $stmt = $pdo->query('SELECT id FROM sr_community_boards ORDER BY id ASC');
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    if ($source === 'group' && $boardGroupId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM sr_community_boards WHERE board_group_id = :board_group_id ORDER BY id ASC');
        $stmt->execute(['board_group_id' => $boardGroupId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    return [$boardId];
}

function sr_community_board_setting_value_type(string $settingKey): string
{
    if (in_array($settingKey, [
        'attachment_max_bytes',
        'attachment_max_count',
        'file_attachment_max_bytes',
        'file_attachment_max_count',
        'read_min_level',
        'write_min_level',
        'comment_min_level',
        'level_post_score',
        'level_comment_score',
        'banner_before_list_id',
        'banner_after_list_id',
        'banner_before_view_id',
        'banner_after_view_id',
        'banner_before_form_id',
        'banner_after_form_id',
        'popup_layer_list_id',
        'popup_layer_view_id',
        'popup_layer_form_id',
    ], true) || str_ends_with($settingKey, '_amount')) {
        return 'int';
    }

    if (in_array($settingKey, ['file_uploads_enabled'], true) || str_ends_with($settingKey, '_enabled')) {
        return 'bool';
    }

    if (in_array($settingKey, ['read_group_keys', 'write_group_keys', 'comment_group_keys'], true) || str_ends_with($settingKey, '_amounts_json')) {
        return 'json';
    }

    return 'string';
}

function sr_community_post_editor_key(string $value, bool $allowInherit = false): string
{
    return sr_editor_normalize_key($value, $allowInherit);
}

function sr_community_effective_post_editor(PDO $pdo, array $board, ?array $settings = null): string
{
    $boardId = (int) ($board['id'] ?? 0);

    if ($boardId > 0) {
        $boardEditor = sr_community_post_editor_key((string) (sr_community_board_setting_value($pdo, $boardId, 'post_editor') ?? 'textarea'));
        if ($boardEditor !== '') {
            return sr_editor_effective_key($pdo, $boardEditor);
        }
    }

    return sr_editor_effective_key($pdo, 'textarea');
}

function sr_community_apply_board_setting_scope(PDO $pdo, int $boardId, int $boardGroupId, string $settingKey, string $source, mixed $value): void
{
    $targets = sr_community_board_scope_target_ids($pdo, $boardId, $boardGroupId, $source);
    if ($targets === []) {
        return;
    }

    if (in_array($settingKey, sr_community_board_group_column_setting_keys(), true)) {
        $placeholders = implode(',', array_fill(0, count($targets), '?'));
        $stmt = $pdo->prepare('UPDATE sr_community_boards SET ' . $settingKey . ' = ?, updated_at = ? WHERE id IN (' . $placeholders . ')');
        $stmt->execute(array_merge([(string) $value, sr_now()], $targets));
    } else {
        $valueType = sr_community_board_setting_value_type($settingKey);
        foreach ($targets as $targetBoardId) {
            sr_community_set_board_setting($pdo, (int) $targetBoardId, $settingKey, (string) $value, $valueType);
        }
    }

    foreach ($targets as $targetBoardId) {
        sr_community_set_board_setting_source($pdo, (int) $targetBoardId, $settingKey, 'board');
    }
}

function sr_community_board_select_columns(string $alias = 'b'): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';

    return $prefix . 'id, ' . $prefix . 'board_group_id, ' . $prefix . 'board_key, ' . $prefix . 'title, '
        . $prefix . 'description, ' . $prefix . 'status, ' . $prefix . 'read_policy, ' . $prefix . 'write_policy, '
        . $prefix . 'comment_policy, ' . $prefix . 'image_uploads_enabled, ' . $prefix . 'sort_order, '
        . $prefix . 'created_at, ' . $prefix . 'updated_at';
}

function sr_community_boards(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT ' . sr_community_board_select_columns('b') . ',
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM sr_community_boards b
         LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
         ORDER BY COALESCE(g.sort_order, 1000000) ASC, g.id ASC, b.sort_order ASC, b.id ASC'
    );

    $boards = [];
    foreach ($stmt->fetchAll() as $board) {
        $boards[] = sr_community_board_with_effective_settings($pdo, $board);
    }

    return $boards;
}

function sr_community_admin_board_query_parts(array $filters): array
{
    $where = [];
    $params = [];
    $status = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    $groupId = (int) ($filters['group_id'] ?? 0);
    $field = (string) ($filters['field'] ?? 'all');
    $keyword = trim((string) ($filters['q'] ?? ''));

    if ($status !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('b.status', 'status', $status);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if ($groupId > 0) {
        $where[] = 'b.board_group_id = :board_group_id';
        $params['board_group_id'] = $groupId;
    }

    if ($keyword !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
        if ($field === 'key') {
            $where[] = 'b.board_key LIKE :keyword';
            $params['keyword'] = $like;
        } elseif ($field === 'title') {
            $where[] = 'b.title LIKE :keyword';
            $params['keyword'] = $like;
        } elseif ($field === 'group') {
            $where[] = '(g.title LIKE :group_title_keyword OR g.group_key LIKE :group_key_keyword)';
            $params['group_title_keyword'] = $like;
            $params['group_key_keyword'] = $like;
        } else {
            $where[] = '(b.board_key LIKE :board_key_keyword OR b.title LIKE :title_keyword OR b.description LIKE :description_keyword OR g.title LIKE :group_title_keyword OR g.group_key LIKE :group_key_keyword)';
            $params['board_key_keyword'] = $like;
            $params['title_keyword'] = $like;
            $params['description_keyword'] = $like;
            $params['group_title_keyword'] = $like;
            $params['group_key_keyword'] = $like;
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_community_admin_board_count(PDO $pdo, array $filters): int
{
    $queryParts = sr_community_admin_board_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value
            FROM sr_community_boards b
            LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_admin_board_sort_options(): array
{
    return [
        'board_key' => ['columns' => ['b.board_key', 'b.id']],
        'title' => ['columns' => ['b.title', 'b.id']],
        'board_group' => ['columns' => ['g.title', 'b.id']],
        'status' => ['columns' => ['b.status', 'b.id']],
        'sort_order' => ['columns' => ['COALESCE(g.sort_order, 1000000)', 'g.id', 'b.sort_order', 'b.id']],
    ];
}

function sr_community_admin_board_default_sort(): array
{
    return sr_admin_sort_default('sort_order', 'asc');
}

function sr_community_admin_boards(PDO $pdo, array $filters, int $limit = 0, int $offset = 0, array $sort = []): array
{
    $queryParts = sr_community_admin_board_query_parts($filters);
    $sql = 'SELECT ' . sr_community_board_select_columns('b') . ',
                   g.group_key AS board_group_key,
                   g.title AS board_group_title,
                   g.status AS board_group_status,
                   g.sort_order AS board_group_sort_order
            FROM sr_community_boards b
            LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }
    $sql .= sr_admin_sort_order_sql(sr_community_admin_board_sort_options(), $sort, sr_community_admin_board_default_sort());
    if ($limit > 0) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($queryParts['params'] as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    $boards = [];
    foreach ($stmt->fetchAll() as $board) {
        $boards[] = sr_community_board_with_effective_settings($pdo, $board);
    }

    return $boards;
}

function sr_community_admin_board_status_counts(PDO $pdo, array $allowedStatuses): array
{
    $counts = ['total' => 0];
    foreach ($allowedStatuses as $status) {
        $counts[(string) $status] = 0;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_community_boards GROUP BY status');
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

function sr_community_enabled_boards(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT " . sr_community_board_select_columns('b') . ",
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM sr_community_boards b
         LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
         WHERE b.status = 'enabled'
         ORDER BY COALESCE(g.sort_order, 1000000) ASC, g.id ASC, b.sort_order ASC, b.id ASC"
    );

    $boards = [];
    foreach ($stmt->fetchAll() as $board) {
        $boards[] = sr_community_board_with_effective_settings($pdo, $board);
    }

    return $boards;
}

function sr_community_board_by_key(PDO $pdo, string $boardKey): ?array
{
    if (!sr_community_board_key_is_valid($boardKey)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ' . sr_community_board_select_columns('b') . ',
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM sr_community_boards b
         LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
         WHERE b.board_key = :board_key
         LIMIT 1'
    );
    $stmt->execute(['board_key' => $boardKey]);
    $board = $stmt->fetch();

    return is_array($board) ? sr_community_board_with_effective_settings($pdo, $board) : null;
}

function sr_community_board_by_id(PDO $pdo, int $boardId): ?array
{
    if ($boardId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ' . sr_community_board_select_columns('b') . ',
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM sr_community_boards b
         LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
         WHERE b.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $boardId]);
    $board = $stmt->fetch();

    return is_array($board) ? sr_community_board_with_effective_settings($pdo, $board) : null;
}

function sr_community_board_with_effective_settings(PDO $pdo, array $board): array
{
    $board['effective_read_policy'] = sr_community_effective_board_policy($pdo, $board, 'read_policy');
    $board['effective_write_policy'] = sr_community_effective_board_policy($pdo, $board, 'write_policy');
    $board['effective_comment_policy'] = sr_community_effective_board_policy($pdo, $board, 'comment_policy');
    $board['effective_image_uploads_enabled'] = sr_community_effective_board_image_uploads_enabled($pdo, $board) ? 1 : 0;
    foreach (sr_community_public_display_setting_labels() as $settingKey => $settingLabel) {
        $board[$settingKey] = (int) sr_community_effective_board_setting($pdo, $board, (string) $settingKey, '0');
    }
    $board['effective_file_uploads_enabled'] = sr_community_effective_board_file_uploads_enabled($pdo, $board) ? 1 : 0;

    return $board;
}

function sr_community_create_board(PDO $pdo, array $data): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_boards
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

function sr_community_update_board(PDO $pdo, int $boardId, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_boards
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
        'updated_at' => sr_now(),
        'id' => $boardId,
    ]);
}

function sr_community_board_groups(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT g.*,
                COUNT(b.id) AS board_count
         FROM sr_community_board_groups g
         LEFT JOIN sr_community_boards b ON b.board_group_id = g.id
         GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at
         ORDER BY g.sort_order ASC, g.id ASC'
    );

    return $stmt->fetchAll();
}

function sr_community_admin_board_group_query_parts(array $filters): array
{
    $where = [];
    $params = [];
    $status = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    $field = (string) ($filters['field'] ?? 'all');
    $keyword = trim((string) ($filters['q'] ?? ''));

    if ($status !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('g.status', 'status', $status);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if ($keyword !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
        if ($field === 'key') {
            $where[] = 'g.group_key LIKE :keyword';
            $params['keyword'] = $like;
        } elseif ($field === 'title') {
            $where[] = 'g.title LIKE :keyword';
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

function sr_community_admin_board_group_count(PDO $pdo, array $filters): int
{
    $queryParts = sr_community_admin_board_group_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value FROM sr_community_board_groups g';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_admin_board_group_sort_options(): array
{
    return [
        'group_key' => ['columns' => ['g.group_key', 'g.id']],
        'title' => ['columns' => ['g.title', 'g.id']],
        'status' => ['columns' => ['g.status', 'g.id']],
        'board_count' => ['columns' => ['board_count', 'g.id']],
        'sort_order' => ['columns' => ['g.sort_order', 'g.id']],
    ];
}

function sr_community_admin_board_group_default_sort(): array
{
    return sr_admin_sort_default('sort_order', 'asc');
}

function sr_community_admin_board_groups(PDO $pdo, array $filters, int $limit = 0, int $offset = 0, array $sort = []): array
{
    $queryParts = sr_community_admin_board_group_query_parts($filters);
    $sql = 'SELECT g.*,
                   COUNT(b.id) AS board_count
            FROM sr_community_board_groups g
            LEFT JOIN sr_community_boards b ON b.board_group_id = g.id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }
    $sql .= ' GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at'
        . sr_admin_sort_order_sql(sr_community_admin_board_group_sort_options(), $sort, sr_community_admin_board_group_default_sort());
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

function sr_community_admin_board_group_status_counts(PDO $pdo, array $allowedStatuses): array
{
    $counts = ['total' => 0];
    foreach ($allowedStatuses as $status) {
        $counts[(string) $status] = 0;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_community_board_groups GROUP BY status');
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

function sr_community_enabled_board_groups(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT *
         FROM sr_community_board_groups
         WHERE status = 'enabled'
         ORDER BY sort_order ASC, id ASC"
    );

    return $stmt->fetchAll();
}

function sr_community_board_group_by_id(PDO $pdo, int $groupId): ?array
{
    if ($groupId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_board_groups WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $groupId]);
    $group = $stmt->fetch();

    return is_array($group) ? $group : null;
}

function sr_community_board_group_by_key(PDO $pdo, string $groupKey): ?array
{
    if (!sr_community_board_group_key_is_valid($groupKey)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_board_groups WHERE group_key = :group_key LIMIT 1');
    $stmt->execute(['group_key' => $groupKey]);
    $group = $stmt->fetch();

    return is_array($group) ? $group : null;
}

function sr_community_create_board_group(PDO $pdo, array $data): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_groups
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

function sr_community_update_board_group(PDO $pdo, int $groupId, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_board_groups
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
        'updated_at' => sr_now(),
        'id' => $groupId,
    ]);
}

function sr_community_board_setting_value(PDO $pdo, int $boardId, string $settingKey): ?string
{
    if ($boardId < 1 || $settingKey === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT setting_value
         FROM sr_community_board_settings
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

function sr_community_set_board_setting(PDO $pdo, int $boardId, string $settingKey, string $settingValue, string $valueType = 'string'): void
{
    if ($boardId < 1 || $settingKey === '') {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_settings
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

function sr_community_board_group_setting_value(PDO $pdo, int $groupId, string $settingKey): ?string
{
    if ($groupId < 1 || !in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT setting_value
         FROM sr_community_board_group_settings
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

function sr_community_set_board_group_setting(PDO $pdo, int $groupId, string $settingKey, string $settingValue, string $valueType = 'string'): void
{
    if ($groupId < 1 || !in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_group_settings
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

function sr_community_board_group_settings(PDO $pdo, int $groupId): array
{
    if ($groupId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT setting_key, setting_value
         FROM sr_community_board_group_settings
         WHERE group_id = :group_id'
    );
    $stmt->execute(['group_id' => $groupId]);

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }

    return $settings;
}

function sr_community_board_setting_source(PDO $pdo, int $boardId, string $settingKey): string
{
    if ($boardId < 1 || !in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
        return 'board';
    }

    $stmt = $pdo->prepare(
        'SELECT source
         FROM sr_community_board_setting_sources
         WHERE board_id = :board_id
           AND setting_key = :setting_key
         LIMIT 1'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'setting_key' => $settingKey,
    ]);
    $source = $stmt->fetchColumn();

    return sr_community_normalize_board_setting_source(is_string($source) ? $source : 'board');
}

function sr_community_board_setting_sources(PDO $pdo, int $boardId): array
{
    if ($boardId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT setting_key, source
         FROM sr_community_board_setting_sources
         WHERE board_id = :board_id'
    );
    $stmt->execute(['board_id' => $boardId]);

    $sources = [];
    foreach ($stmt->fetchAll() as $row) {
        $settingKey = (string) ($row['setting_key'] ?? '');
        if (in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
            $sources[$settingKey] = sr_community_normalize_board_setting_source((string) ($row['source'] ?? 'board'));
        }
    }

    return $sources;
}

function sr_community_set_board_setting_source(PDO $pdo, int $boardId, string $settingKey, string $source): void
{
    if ($boardId < 1 || !in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
        return;
    }

    $source = sr_community_normalize_board_setting_source($source);
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_setting_sources
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

function sr_community_effective_board_setting(PDO $pdo, array $board, string $settingKey, mixed $default = ''): string
{
    $boardId = (int) ($board['id'] ?? 0);
    if ($boardId < 1 || !in_array($settingKey, sr_community_board_group_setting_keys(), true)) {
        return (string) $default;
    }

    if (in_array($settingKey, sr_community_board_group_column_setting_keys(), true)) {
        return (string) ($board[$settingKey] ?? $default);
    }

    $boardValue = sr_community_board_setting_value($pdo, $boardId, $settingKey);
    return is_string($boardValue) && $boardValue !== '' ? $boardValue : (string) $default;
}

function sr_community_effective_board_policy(PDO $pdo, array $board, string $settingKey): string
{
    $policyType = str_replace('_policy', '', $settingKey);
    $fallback = (string) ($board[$settingKey] ?? '');
    $policy = sr_community_effective_board_setting($pdo, $board, $settingKey, $fallback);

    return in_array($policy, sr_community_policy_values($policyType), true) ? $policy : $fallback;
}

function sr_community_effective_board_image_uploads_enabled(PDO $pdo, array $board): bool
{
    return in_array(sr_community_effective_board_setting($pdo, $board, 'image_uploads_enabled', (string) (int) ($board['image_uploads_enabled'] ?? 1)), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_effective_board_file_uploads_enabled(PDO $pdo, array $board): bool
{
    return in_array(sr_community_effective_board_setting($pdo, $board, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_board_min_level(PDO $pdo, int $boardId, string $settingKey): int
{
    if ($boardId < 1 || !in_array($settingKey, ['read_min_level', 'write_min_level', 'comment_min_level'], true)) {
        return 0;
    }

    $board = sr_community_board_by_id($pdo, $boardId);
    if (!is_array($board)) {
        return 0;
    }

    return sr_community_normalize_level_value(sr_community_effective_board_setting($pdo, $board, $settingKey, '0'), sr_community_settings($pdo));
}

function sr_community_board_own_min_level(PDO $pdo, int $boardId, string $settingKey): int
{
    if ($boardId < 1 || !in_array($settingKey, ['read_min_level', 'write_min_level', 'comment_min_level'], true)) {
        return 0;
    }

    $value = sr_community_board_setting_value($pdo, $boardId, $settingKey);
    return is_string($value) && $value !== '' ? sr_community_normalize_level_value($value, sr_community_settings($pdo)) : 0;
}

function sr_community_board_level_score(PDO $pdo, int $boardId, string $settingKey, array $settings = []): int
{
    if (!in_array($settingKey, ['level_post_score', 'level_comment_score'], true)) {
        return 0;
    }

    $defaultSettings = sr_community_default_settings();
    $default = min(10000, max(0, (int) ($defaultSettings[$settingKey] ?? ($settingKey === 'level_post_score' ? 10 : 2))));
    if ($boardId < 1) {
        return $default;
    }

    $value = sr_community_board_setting_value($pdo, $boardId, $settingKey);
    if (is_string($value) && $value !== '') {
        return min(10000, max(0, (int) $value));
    }

    return $default;
}

function sr_community_board_own_level_score(PDO $pdo, int $boardId, string $settingKey, array $settings = []): int
{
    if (!in_array($settingKey, ['level_post_score', 'level_comment_score'], true)) {
        return 0;
    }

    $defaultSettings = sr_community_default_settings();
    $default = min(10000, max(0, (int) ($defaultSettings[$settingKey] ?? ($settingKey === 'level_post_score' ? 10 : 2))));
    if ($boardId < 1) {
        return $default;
    }

    $value = sr_community_board_setting_value($pdo, $boardId, $settingKey);

    return is_string($value) && $value !== '' ? min(10000, max(0, (int) $value)) : $default;
}

function sr_community_board_attachment_max_bytes(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(10485760, max(1024, (int) ($defaultSettings['attachment_max_bytes'] ?? 2097152)));
    $board = sr_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? sr_community_effective_board_setting($pdo, $board, 'attachment_max_bytes', (string) $default)
        : sr_community_board_setting_value($pdo, $boardId, 'attachment_max_bytes');

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return min(10485760, max(1024, (int) $value));
}

function sr_community_board_attachment_max_count(PDO $pdo, int $boardId, array $settings = []): int
{
    $default = 1;
    $board = sr_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? sr_community_effective_board_setting($pdo, $board, 'attachment_max_count', (string) $default)
        : sr_community_board_setting_value($pdo, $boardId, 'attachment_max_count');

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return min(10, max(0, (int) $value));
}

function sr_community_board_own_attachment_max_bytes(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(10485760, max(1024, (int) ($defaultSettings['attachment_max_bytes'] ?? 2097152)));
    $value = sr_community_board_setting_value($pdo, $boardId, 'attachment_max_bytes');
    return is_string($value) && $value !== '' ? min(10485760, max(1024, (int) $value)) : $default;
}

function sr_community_board_own_attachment_max_count(PDO $pdo, int $boardId, array $settings = []): int
{
    $default = 1;
    $value = sr_community_board_setting_value($pdo, $boardId, 'attachment_max_count');
    return is_string($value) && $value !== '' ? min(10, max(0, (int) $value)) : $default;
}

function sr_community_board_file_attachment_max_bytes(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(20971520, max(1024, (int) ($defaultSettings['file_attachment_max_bytes'] ?? 5242880)));
    $board = sr_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? sr_community_effective_board_setting($pdo, $board, 'file_attachment_max_bytes', (string) $default)
        : sr_community_board_setting_value($pdo, $boardId, 'file_attachment_max_bytes');

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return min(20971520, max(1024, (int) $value));
}

function sr_community_board_file_attachment_max_count(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(5, max(0, (int) ($defaultSettings['file_attachment_max_count'] ?? 3)));
    $board = sr_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? sr_community_effective_board_setting($pdo, $board, 'file_attachment_max_count', (string) $default)
        : sr_community_board_setting_value($pdo, $boardId, 'file_attachment_max_count');

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return min(5, max(0, (int) $value));
}

function sr_community_board_own_file_attachment_max_bytes(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(20971520, max(1024, (int) ($defaultSettings['file_attachment_max_bytes'] ?? 5242880)));
    $value = sr_community_board_setting_value($pdo, $boardId, 'file_attachment_max_bytes');
    return is_string($value) && $value !== '' ? min(20971520, max(1024, (int) $value)) : $default;
}

function sr_community_board_own_file_attachment_max_count(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(5, max(0, (int) ($defaultSettings['file_attachment_max_count'] ?? 3)));
    $value = sr_community_board_setting_value($pdo, $boardId, 'file_attachment_max_count');
    return is_string($value) && $value !== '' ? min(5, max(0, (int) $value)) : $default;
}

function sr_community_board_file_allowed_extensions(PDO $pdo, int $boardId, array $settings = []): array
{
    $defaultSettings = sr_community_default_settings();
    $default = sr_community_normalize_file_extensions(is_array($defaultSettings['file_allowed_extensions'] ?? null) ? $defaultSettings['file_allowed_extensions'] : ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp']);
    $board = sr_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? sr_community_effective_board_setting($pdo, $board, 'file_allowed_extensions', implode(',', $default))
        : sr_community_board_setting_value($pdo, $boardId, 'file_allowed_extensions');

    if (!is_string($value) || trim($value) === '') {
        return $default;
    }

    return sr_community_normalize_file_extensions(preg_split('/[\s,]+/', $value) ?: []);
}

function sr_community_board_own_file_allowed_extensions(PDO $pdo, int $boardId, array $settings = []): array
{
    $defaultSettings = sr_community_default_settings();
    $default = sr_community_normalize_file_extensions(is_array($defaultSettings['file_allowed_extensions'] ?? null) ? $defaultSettings['file_allowed_extensions'] : ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp']);
    $value = sr_community_board_setting_value($pdo, $boardId, 'file_allowed_extensions');
    if (!is_string($value) || trim($value) === '') {
        return $default;
    }

    return sr_community_normalize_file_extensions(preg_split('/[\s,]+/', $value) ?: []);
}

function sr_community_normalize_file_extensions(array $extensions): array
{
    $allowed = array_fill_keys(array_keys(sr_community_file_extension_mime_map()), true);
    $normalized = [];
    foreach ($extensions as $extension) {
        $extension = strtolower(ltrim(trim((string) $extension), '.'));
        if (isset($allowed[$extension])) {
            $normalized[$extension] = true;
        }
    }

    return array_keys($normalized);
}

function sr_community_file_extensions_from_input(string $value): array
{
    if (trim($value) === '') {
        return [];
    }

    $rawExtensions = preg_split('/[\s,]+/', $value);
    return sr_community_normalize_file_extensions(is_array($rawExtensions) ? $rawExtensions : []);
}

function sr_community_invalid_file_extensions_from_input(string $value): array
{
    if (trim($value) === '') {
        return [];
    }

    $allowed = array_fill_keys(array_keys(sr_community_file_extension_mime_map()), true);
    $invalid = [];
    $rawExtensions = preg_split('/[\s,]+/', $value);
    foreach (is_array($rawExtensions) ? $rawExtensions : [] as $rawExtension) {
        $extension = strtolower(ltrim(trim((string) $rawExtension), '.'));
        if ($extension !== '' && !isset($allowed[$extension])) {
            $invalid[] = $extension;
        }
    }

    return array_values(array_unique($invalid));
}

function sr_community_optional_table_exists(PDO $pdo, string $tableName): bool
{
    static $cache = [];
    if (!preg_match('/\Asr_[a-z0-9_]+\z/', $tableName)) {
        return false;
    }
    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
        $cache[$tableName] = true;
    } catch (Throwable) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

function sr_community_optional_count(PDO $pdo, string $tableName, string $whereSql, array $params = []): int
{
    if (!sr_community_optional_table_exists($pdo, $tableName)) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $tableName . ' WHERE ' . $whereSql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_community_board_reference_counts(PDO $pdo, int $boardId): array
{
    if ($boardId < 1) {
        return ['posts' => 0, 'series' => 0, 'attachments' => 0, 'comments' => 0];
    }

    return [
        'posts' => sr_community_optional_count($pdo, 'sr_community_posts', 'board_id = :board_id', ['board_id' => $boardId]),
        'series' => sr_community_optional_count($pdo, 'sr_community_series', 'board_id = :board_id', ['board_id' => $boardId]),
        'attachments' => sr_community_optional_table_exists($pdo, 'sr_community_attachments')
            ? sr_community_optional_count($pdo, 'sr_community_attachments', 'post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)', ['board_id' => $boardId])
            : 0,
        'comments' => sr_community_optional_table_exists($pdo, 'sr_community_comments')
            ? sr_community_optional_count($pdo, 'sr_community_comments', 'post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)', ['board_id' => $boardId])
            : 0,
    ];
}

function sr_community_board_external_reference_counts(PDO $pdo, int $boardId): array
{
    $board = sr_community_board_by_id($pdo, $boardId);
    if (!is_array($board)) {
        return ['site_menu' => 0, 'banner_targets' => 0, 'popup_layer_targets' => 0, 'coupon_targets' => 0];
    }

    $boardKey = (string) ($board['board_key'] ?? '');
    return [
        'site_menu' => $boardKey !== ''
            ? sr_community_optional_count($pdo, 'sr_site_menu_items', 'url = :url', ['url' => '/community/board?key=' . $boardKey])
            : 0,
        'banner_targets' => sr_community_optional_count(
            $pdo,
            'sr_banner_targets',
            "module_key = 'community' AND point_key IN ('community.board.list', 'community.post.form') AND match_type = 'exact' AND subject_id = :subject_id",
            ['subject_id' => $boardId]
        ),
        'popup_layer_targets' => sr_community_optional_count(
            $pdo,
            'sr_popup_layer_targets',
            "module_key = 'community' AND point_key IN ('community.board.list', 'community.post.form') AND match_type = 'exact' AND subject_id = :subject_id",
            ['subject_id' => $boardId]
        ),
        'coupon_targets' => sr_community_optional_count(
            $pdo,
            'sr_coupon_definitions',
            "target_type = 'community_board' AND target_id = :target_id",
            ['target_id' => (string) $boardId]
        ),
    ];
}

function sr_community_board_delete_block_messages(array $references, array $externalReferences): array
{
    $messages = [];
    if (array_sum(array_map('intval', $externalReferences)) > 0) {
        $messages[] = '사이트 메뉴, 배너/팝업, 쿠폰 등 외부 운영 참조가 있어 삭제할 수 없습니다.';
    }

    return $messages;
}

function sr_community_can_delete_board(PDO $pdo, int $boardId): array
{
    $board = sr_community_board_by_id($pdo, $boardId);
    if (!is_array($board)) {
        return ['can_delete' => false, 'errors' => ['게시판을 찾을 수 없습니다.'], 'references' => [], 'external_references' => []];
    }

    $references = sr_community_board_reference_counts($pdo, $boardId);
    $externalReferences = sr_community_board_external_reference_counts($pdo, $boardId);
    $errors = sr_community_board_delete_block_messages($references, $externalReferences);

    return [
        'can_delete' => $errors === [],
        'errors' => $errors,
        'references' => $references,
        'external_references' => $externalReferences,
        'board' => $board,
    ];
}

function sr_community_delete_board(PDO $pdo, int $boardId): array
{
    $check = sr_community_can_delete_board($pdo, $boardId);
    if (empty($check['can_delete']) || !is_array($check['board'] ?? null)) {
        return $check;
    }

    $attachmentFiles = sr_community_board_attachment_storage_refs($pdo, $boardId);
    $stmt = $pdo->prepare('SELECT id FROM sr_community_posts WHERE board_id = :board_id');
    $stmt->execute(['board_id' => $boardId]);
    $bodyFilePostIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $pdo->beginTransaction();
    try {
        $deletedSettingSources = sr_community_optional_count($pdo, 'sr_community_board_setting_sources', 'board_id = :board_id', ['board_id' => $boardId]);
        $deletedSettings = sr_community_optional_count($pdo, 'sr_community_board_settings', 'board_id = :board_id', ['board_id' => $boardId]);
        $deletedCategories = sr_community_optional_count($pdo, 'sr_community_categories', 'board_id = :board_id', ['board_id' => $boardId]);
        $deletedPosts = (int) ($check['references']['posts'] ?? 0);
        $deletedComments = (int) ($check['references']['comments'] ?? 0);
        $deletedAttachments = (int) ($check['references']['attachments'] ?? 0);
        $deletedSeries = (int) ($check['references']['series'] ?? 0);

        if (sr_community_optional_table_exists($pdo, 'sr_community_series_scraps')) {
            $pdo->prepare('DELETE FROM sr_community_series_scraps WHERE series_id IN (SELECT id FROM sr_community_series WHERE board_id = :board_id)')->execute(['board_id' => $boardId]);
        }
        if (sr_community_optional_table_exists($pdo, 'sr_community_series_items')) {
            $pdo->prepare('DELETE FROM sr_community_series_items WHERE series_id IN (SELECT id FROM sr_community_series WHERE board_id = :board_id)')->execute(['board_id' => $boardId]);
        }
        if (sr_community_optional_table_exists($pdo, 'sr_community_access_entitlements')) {
            $pdo->prepare("DELETE FROM sr_community_access_entitlements WHERE subject_type IN ('community_post', 'community.post') AND subject_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)")->execute(['board_id' => $boardId]);
            if (sr_community_optional_table_exists($pdo, 'sr_community_attachments')) {
                $pdo->prepare("DELETE FROM sr_community_access_entitlements WHERE subject_type IN ('community.attachment', 'community_attachment') AND subject_id IN (SELECT a.id FROM sr_community_attachments a INNER JOIN sr_community_posts p ON p.id = a.post_id WHERE p.board_id = :board_id)")->execute(['board_id' => $boardId]);
            }
        }
        if (sr_community_optional_table_exists($pdo, 'sr_community_reports')) {
            $pdo->prepare("DELETE FROM sr_community_reports WHERE target_type = 'post' AND target_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)")->execute(['board_id' => $boardId]);
            if (sr_community_optional_table_exists($pdo, 'sr_community_series')) {
                $pdo->prepare("DELETE FROM sr_community_reports WHERE target_type = 'series' AND target_id IN (SELECT id FROM sr_community_series WHERE board_id = :board_id)")->execute(['board_id' => $boardId]);
            }
            if (sr_community_optional_table_exists($pdo, 'sr_community_comments')) {
                $pdo->prepare("DELETE FROM sr_community_reports WHERE target_type = 'comment' AND target_id IN (SELECT c.id FROM sr_community_comments c INNER JOIN sr_community_posts p ON p.id = c.post_id WHERE p.board_id = :board_id)")->execute(['board_id' => $boardId]);
            }
        }
        if (sr_community_optional_table_exists($pdo, 'sr_community_series')) {
            $pdo->prepare('DELETE FROM sr_community_series WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
        }
        if (sr_community_optional_table_exists($pdo, 'sr_community_scraps')) {
            $pdo->prepare('DELETE FROM sr_community_scraps WHERE post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)')->execute(['board_id' => $boardId]);
        }
        if (sr_community_optional_table_exists($pdo, 'sr_community_link_refs')) {
            $pdo->prepare('DELETE FROM sr_community_link_refs WHERE post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)')->execute(['board_id' => $boardId]);
        }
        if (sr_community_optional_table_exists($pdo, 'sr_community_attachments')) {
            $pdo->prepare('DELETE FROM sr_community_attachments WHERE post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)')->execute(['board_id' => $boardId]);
        }
        if (sr_community_optional_table_exists($pdo, 'sr_community_comments')) {
            $pdo->prepare('DELETE FROM sr_community_comments WHERE post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)')->execute(['board_id' => $boardId]);
        }
        $pdo->prepare('DELETE FROM sr_community_posts WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_board_setting_sources WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_board_settings WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
        if (sr_community_optional_table_exists($pdo, 'sr_community_categories')) {
            $pdo->prepare('DELETE FROM sr_community_categories WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
        }
        $pdo->prepare('DELETE FROM sr_community_boards WHERE id = :id')->execute(['id' => $boardId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $failedAttachmentFiles = 0;
    $failedAttachmentFileRefs = [];
    foreach ($attachmentFiles as $attachmentFile) {
        $driver = (string) $attachmentFile['driver'];
        $key = (string) $attachmentFile['key'];
        if (!sr_storage_delete($driver, $key)) {
            $failedAttachmentFiles++;
            $failedAttachmentFileRefs[] = $driver . ':' . $key;
            sr_community_record_storage_cleanup_failure($pdo, 'board_delete_attachment', $boardId, $driver, $key, '게시판 삭제 후 첨부 파일 저장소 정리에 실패했습니다.');
        }
    }
    $deletedBodyFiles = sr_community_cleanup_body_files_for_deleted_posts($pdo, $bodyFilePostIds);

    $check['deleted_settings'] = $deletedSettings;
    $check['deleted_setting_sources'] = $deletedSettingSources;
    $check['deleted_categories'] = $deletedCategories;
    $check['deleted_posts'] = $deletedPosts;
    $check['deleted_comments'] = $deletedComments;
    $check['deleted_attachments'] = $deletedAttachments;
    $check['deleted_attachment_files'] = count($attachmentFiles) - $failedAttachmentFiles;
    $check['deleted_body_files'] = $deletedBodyFiles;
    $check['failed_attachment_files'] = $failedAttachmentFiles;
    $check['failed_attachment_file_refs'] = $failedAttachmentFileRefs;
    $check['deleted_series'] = $deletedSeries;
    return $check;
}

function sr_community_record_storage_cleanup_failure(PDO $pdo, string $sourceType, int $sourceId, string $driver, string $key, string $errorMessage): void
{
    if (!sr_community_optional_table_exists($pdo, 'sr_community_storage_cleanup_failures') || !sr_storage_key_is_safe($key)) {
        return;
    }

    $driver = in_array($driver, ['local', 's3'], true) ? $driver : 'local';
    $now = sr_now();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_community_storage_cleanup_failures
                (source_type, source_id, storage_driver, storage_key, status, attempt_count, last_error, created_at, updated_at)
             VALUES
                (:source_type, :source_id, :storage_driver, :storage_key, \'pending\', 1, :last_error, :created_at, :updated_at)'
        );
        $stmt->execute([
            'source_type' => sr_community_clean_key($sourceType),
            'source_id' => $sourceId,
            'storage_driver' => $driver,
            'storage_key' => $key,
            'last_error' => sr_community_clean_cleanup_error($errorMessage),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'community_storage_cleanup_failure_record_failed');
    }
}

function sr_community_storage_cleanup_failures(PDO $pdo, int $limit = 50): array
{
    if (!sr_community_optional_table_exists($pdo, 'sr_community_storage_cleanup_failures')) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_community_storage_cleanup_failures
         WHERE status = 'pending'
         ORDER BY updated_at DESC, id DESC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_retry_storage_cleanup_failure(PDO $pdo, int $failureId): array
{
    if ($failureId < 1 || !sr_community_optional_table_exists($pdo, 'sr_community_storage_cleanup_failures')) {
        return ['ok' => false, 'message' => '저장소 정리 실패 기록을 찾을 수 없습니다.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM sr_community_storage_cleanup_failures WHERE id = :id AND status = 'pending' LIMIT 1");
    $stmt->execute(['id' => $failureId]);
    $failure = $stmt->fetch();
    if (!is_array($failure)) {
        return ['ok' => false, 'message' => '재시도할 저장소 정리 실패 기록을 찾을 수 없습니다.'];
    }

    $driver = (string) ($failure['storage_driver'] ?? 'local');
    $key = (string) ($failure['storage_key'] ?? '');
    $now = sr_now();
    if ($key !== '' && sr_storage_delete($driver, $key)) {
        $stmt = $pdo->prepare(
            "UPDATE sr_community_storage_cleanup_failures
             SET status = 'cleaned',
                 attempt_count = attempt_count + 1,
                 last_error = '',
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute(['updated_at' => $now, 'id' => $failureId]);

        return ['ok' => true, 'message' => '저장소 파일 정리를 완료했습니다.'];
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_community_storage_cleanup_failures
         SET attempt_count = attempt_count + 1,
             last_error = :last_error,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'last_error' => '저장소 파일 정리 재시도에 실패했습니다.',
        'updated_at' => $now,
        'id' => $failureId,
    ]);

    return ['ok' => false, 'message' => '저장소 파일 정리 재시도에 실패했습니다. 저장소 권한 또는 S3 설정을 확인해 주세요.'];
}

function sr_community_clean_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
    $value = trim($value, '_');

    return $value !== '' ? substr($value, 0, 60) : 'unknown';
}

function sr_community_clean_cleanup_error(string $value): string
{
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 1000);
    }

    return substr($value, 0, 1000);
}

function sr_community_board_attachment_storage_refs(PDO $pdo, int $boardId): array
{
    if ($boardId < 1 || !sr_community_optional_table_exists($pdo, 'sr_community_attachments')) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT a.*
         FROM sr_community_attachments a
         INNER JOIN sr_community_posts p ON p.id = a.post_id
         WHERE p.board_id = :board_id'
    );
    $stmt->execute(['board_id' => $boardId]);
    $refs = [];
    foreach ($stmt->fetchAll() as $attachment) {
        $driver = strtolower((string) ($attachment['storage_driver'] ?? 'local'));
        $driver = in_array($driver, ['local', 's3'], true) ? $driver : 'local';
        $key = function_exists('sr_community_attachment_storage_key')
            ? sr_community_attachment_storage_key($attachment)
            : (string) ($attachment['storage_key'] ?? '');
        if ($key !== '' && sr_storage_key_is_safe($key)) {
            $refs[$driver . ':' . $key] = ['driver' => $driver, 'key' => $key];
        }
    }

    return array_values($refs);
}

function sr_community_board_group_reference_counts(PDO $pdo, int $groupId): array
{
    return [
        'boards' => $groupId > 0 ? sr_community_optional_count($pdo, 'sr_community_boards', 'board_group_id = :group_id', ['group_id' => $groupId]) : 0,
    ];
}

function sr_community_board_group_external_reference_counts(PDO $pdo, int $groupId): array
{
    $group = sr_community_board_group_by_id($pdo, $groupId);
    if (!is_array($group)) {
        return ['site_menu' => 0];
    }

    $groupKey = (string) ($group['group_key'] ?? '');
    return [
        'site_menu' => $groupKey !== ''
            ? sr_community_optional_count($pdo, 'sr_site_menu_items', 'url = :url', ['url' => '/community#group-' . $groupKey])
            : 0,
    ];
}

function sr_community_can_delete_board_group(PDO $pdo, int $groupId): array
{
    $group = sr_community_board_group_by_id($pdo, $groupId);
    if (!is_array($group)) {
        return ['can_delete' => false, 'errors' => ['게시판 그룹을 찾을 수 없습니다.'], 'references' => [], 'external_references' => []];
    }

    $references = sr_community_board_group_reference_counts($pdo, $groupId);
    $externalReferences = sr_community_board_group_external_reference_counts($pdo, $groupId);
    $errors = [];
    if ((int) ($references['boards'] ?? 0) > 0) {
        $errors[] = '게시판이 연결된 그룹은 삭제할 수 없습니다. 게시판을 이동하거나 그룹을 비활성/보관 상태로 전환해 주세요.';
    }
    if (array_sum(array_map('intval', $externalReferences)) > 0) {
        $errors[] = '외부 운영 참조가 있어 게시판 그룹을 삭제할 수 없습니다.';
    }

    return ['can_delete' => $errors === [], 'errors' => $errors, 'references' => $references, 'external_references' => $externalReferences, 'group' => $group];
}

function sr_community_delete_board_group(PDO $pdo, int $groupId): array
{
    $check = sr_community_can_delete_board_group($pdo, $groupId);
    if (empty($check['can_delete']) || !is_array($check['group'] ?? null)) {
        return $check;
    }

    $pdo->beginTransaction();
    try {
        $deletedSettings = sr_community_optional_count($pdo, 'sr_community_board_group_settings', 'group_id = :group_id', ['group_id' => $groupId]);
        if (sr_community_optional_table_exists($pdo, 'sr_community_board_group_settings')) {
            $pdo->prepare('DELETE FROM sr_community_board_group_settings WHERE group_id = :group_id')->execute(['group_id' => $groupId]);
        }
        $pdo->prepare('DELETE FROM sr_community_board_groups WHERE id = :id')->execute(['id' => $groupId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $check['deleted_settings'] = $deletedSettings;
    return $check;
}
