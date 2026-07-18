<?php

declare(strict_types=1);

function sr_content_group_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_content_group_key_is_valid(string $groupKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $groupKey) === 1;
}

function sr_content_groups_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_content_groups LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_content_group_settings_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_content_group_settings LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_content_setting_sources_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_content_setting_sources LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_content_group_path(string $groupKey): string
{
    return '/content/group?key=' . rawurlencode($groupKey);
}

function sr_content_group_basic_setting_keys(): array
{
    return ['status', 'layout_key', 'reaction_preset_key', 'reaction_comment_preset_key', 'comment_editor_key', 'comment_extra_fields_json', 'member_submission_enabled', 'member_submission_allowed_group_keys', 'member_submission_review_required'];
}

function sr_content_group_asset_access_setting_keys(): array
{
    return [
        'asset_access_enabled',
        'asset_module',
        'asset_access_amount',
        'asset_access_settlement_currency',
        'asset_access_amounts_json',
        'asset_access_group_policies_json',
        'asset_access_policy_set_id',
        'asset_charge_policy',
    ];
}

function sr_content_group_asset_action_setting_keys(): array
{
    return [
        'asset_action_enabled',
        'asset_action_module',
        'asset_action_amount',
        'asset_action_settlement_currency',
        'asset_action_amounts_json',
        'asset_action_group_policies_json',
        'asset_action_policy_set_id',
        'asset_action_direction',
        'asset_action_label',
    ];
}

function sr_content_group_asset_setting_keys(): array
{
    return array_merge(sr_content_group_asset_access_setting_keys(), sr_content_group_asset_action_setting_keys());
}

function sr_content_group_file_asset_setting_keys(): array
{
    return [
        'file_asset_download_enabled',
        'file_asset_module',
        'file_asset_download_amount',
        'file_asset_download_amounts_json',
        'file_asset_download_group_policies_json',
        'file_asset_download_policy_set_id',
        'file_asset_charge_policy',
    ];
}

function sr_content_group_setting_keys(): array
{
    return array_values(array_unique(array_merge(
        sr_content_group_basic_setting_keys(),
        array_keys(sr_content_public_display_setting_labels()),
        sr_content_group_asset_setting_keys(),
        sr_content_group_file_asset_setting_keys()
    )));
}

function sr_content_group_default_settings(?array $site = null, ?PDO $pdo = null): array
{
    $layoutKey = $pdo instanceof PDO ? sr_content_default_layout_key($pdo, $site) : sr_public_layout_key($site, $pdo);
    $settings = [
        'status' => 'draft',
        'layout_key' => $layoutKey,
        'reaction_preset_key' => '',
        'reaction_comment_preset_key' => '',
        'comment_editor_key' => 'inherit',
        'comment_extra_fields_json' => '[]',
        'asset_access_enabled' => '0',
        'asset_module' => '',
        'asset_access_amount' => '0',
        'asset_access_settlement_currency' => $pdo instanceof PDO ? sr_site_default_currency($pdo) : 'KRW',
        'asset_access_amounts_json' => '',
        'asset_access_group_policies_json' => '',
        'asset_access_policy_set_id' => '0',
        'asset_charge_policy' => 'once',
        'asset_action_enabled' => '0',
        'asset_action_module' => '',
        'asset_action_amount' => '0',
        'asset_action_settlement_currency' => $pdo instanceof PDO ? sr_site_default_currency($pdo) : 'KRW',
        'asset_action_amounts_json' => '',
        'asset_action_group_policies_json' => '',
        'asset_action_policy_set_id' => '0',
        'asset_action_direction' => 'grant',
        'asset_action_label' => sr_t('content::ui.text.727333ab'),
        'file_asset_download_enabled' => '0',
        'file_asset_module' => '',
        'file_asset_download_amount' => '0',
        'file_asset_download_amounts_json' => '',
        'file_asset_download_group_policies_json' => '',
        'file_asset_download_policy_set_id' => '0',
        'file_asset_charge_policy' => 'once',
        'member_submission_enabled' => '0',
        'member_submission_allowed_group_keys' => '[]',
        'member_submission_review_required' => 'inherit',
    ];

    foreach (sr_content_public_display_setting_labels() as $settingKey => $settingLabel) {
        $settings[(string) $settingKey] = '0';
    }

    return $settings;
}

function sr_content_group_by_id(PDO $pdo, int $groupId): ?array
{
    if ($groupId < 1 || !sr_content_groups_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_content_groups WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $groupId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_group_by_key(PDO $pdo, string $groupKey): ?array
{
    if (!sr_content_groups_table_exists($pdo)) {
        return null;
    }

    if (!sr_content_group_key_is_valid($groupKey)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_content_groups WHERE group_key = :group_key LIMIT 1');
    $stmt->execute(['group_key' => $groupKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_enabled_group_by_key(PDO $pdo, string $groupKey): ?array
{
    $group = sr_content_group_by_key($pdo, $groupKey);
    if (!is_array($group) || (string) ($group['status'] ?? '') !== 'enabled') {
        return null;
    }

    return $group;
}

function sr_content_group_key_exists(PDO $pdo, string $groupKey, int $exceptGroupId = 0): bool
{
    if (!sr_content_groups_table_exists($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_content_groups
         WHERE group_key = :group_key
           AND id <> :except_id
         LIMIT 1'
    );
    $stmt->execute([
        'group_key' => $groupKey,
        'except_id' => $exceptGroupId,
    ]);

    return is_array($stmt->fetch());
}

function sr_content_groups(PDO $pdo): array
{
    if (!sr_content_groups_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        'SELECT g.*,
                COUNT(p.id) AS content_count
         FROM sr_content_groups g
         LEFT JOIN sr_content_items p ON p.content_group_id = g.id
         GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at
         ORDER BY g.sort_order ASC, g.id ASC'
    );

    return $stmt->fetchAll();
}

function sr_content_enabled_groups(PDO $pdo): array
{
    if (!sr_content_groups_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT *
         FROM sr_content_groups
         WHERE status = 'enabled'
         ORDER BY sort_order ASC, id ASC"
    );

    return $stmt->fetchAll();
}

function sr_content_admin_group_filters(): array
{
    $statuses = sr_content_admin_multi_filter_values('status', sr_content_group_statuses());

    $field = sr_get_string('field', 20);
    if (!in_array($field, ['all', 'key', 'title'], true)) {
        $field = 'all';
    }

    return [
        'status' => $statuses,
        'field' => $field,
        'q' => sr_content_clean_single_line(sr_get_string('q', 120), 120),
    ];
}

function sr_content_admin_group_status_counts(PDO $pdo): array
{
    $counts = [
        'total' => 0,
        'enabled' => 0,
        'disabled' => 0,
        'archived' => 0,
    ];

    if (!sr_content_groups_table_exists($pdo)) {
        return $counts;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_content_groups GROUP BY status');
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

function sr_content_admin_group_query_parts(array $filters): array
{
    $where = [];
    $params = [];
    $statuses = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    if ($statuses !== []) {
        $placeholders = [];
        foreach (array_values($statuses) as $index => $status) {
            $paramKey = 'status_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = (string) $status;
        }
        $where[] = 'g.status IN (' . implode(', ', $placeholders) . ')';
    }

    if ((string) ($filters['q'] ?? '') !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'key') {
            $where[] = 'g.group_key LIKE :keyword';
            $params['keyword'] = '%' . (string) $filters['q'] . '%';
        } elseif ($field === 'title') {
            $where[] = 'g.title LIKE :keyword';
            $params['keyword'] = '%' . (string) $filters['q'] . '%';
        } else {
            $where[] = '(g.group_key LIKE :key_keyword OR g.title LIKE :title_keyword)';
            $params['key_keyword'] = '%' . (string) $filters['q'] . '%';
            $params['title_keyword'] = '%' . (string) $filters['q'] . '%';
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_content_admin_group_count(PDO $pdo, array $filters): int
{
    if (!sr_content_groups_table_exists($pdo)) {
        return 0;
    }

    $queryParts = sr_content_admin_group_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value FROM sr_content_groups g';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_content_admin_group_sort_options(): array
{
    return [
        'title' => ['columns' => ['g.title', 'g.id']],
        'group_key' => ['columns' => ['g.group_key', 'g.id']],
        'status' => ['columns' => ['g.status', 'g.id']],
        'content_count' => ['columns' => ['content_count', 'g.id']],
        'sort_order' => ['columns' => ['g.sort_order', 'g.id']],
        'updated_at' => ['columns' => ['g.updated_at', 'g.id']],
    ];
}

function sr_content_admin_group_default_sort(): array
{
    return sr_admin_sort_default('sort_order', 'asc');
}

function sr_content_admin_group_list(PDO $pdo, array $filters, int $limit = 0, int $offset = 0, array $sort = []): array
{
    if (!sr_content_groups_table_exists($pdo)) {
        return [];
    }

    $queryParts = sr_content_admin_group_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $sql = 'SELECT g.*,
                   COUNT(p.id) AS content_count
            FROM sr_content_groups g
            LEFT JOIN sr_content_items p ON p.content_group_id = g.id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at'
        . sr_admin_sort_order_sql(sr_content_admin_group_sort_options(), $sort, sr_content_admin_group_default_sort());
    if ($limit > 0) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_content_group_apply_scope(string $scope): string
{
    if ($scope === 'board') {
        return 'here_only';
    }

    return in_array($scope, ['group', 'all', 'here_only'], true) ? $scope : 'here_only';
}

function sr_content_apply_scope_target_ids(PDO $pdo, int $pageId, int $pageGroupId, string $scope): array
{
    $scope = sr_content_group_apply_scope($scope);
    if ($scope === 'all') {
        $stmt = $pdo->query('SELECT id FROM sr_content_items ORDER BY id ASC');
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    if ($scope === 'group' && $pageGroupId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM sr_content_items WHERE content_group_id = :content_group_id ORDER BY id ASC');
        $stmt->execute(['content_group_id' => $pageGroupId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    if ($pageId < 1) {
        return [];
    }

    return [$pageId];
}

function sr_content_status_rows_for_ids(PDO $pdo, array $contentIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $contentIds), static fn (int $contentId): bool => $contentId > 0)));
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare('SELECT id, slug, status, published_at FROM sr_content_items WHERE id IN (' . $placeholders . ')');
    $stmt->execute($ids);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[(int) ($row['id'] ?? 0)] = $row;
    }

    return $rows;
}

function sr_content_audit_status_schedule_changes(PDO $pdo, array $beforeRows, array $afterRows, array $account): void
{
    foreach ($afterRows as $contentId => $afterRow) {
        $beforeRow = is_array($beforeRows[(int) $contentId] ?? null) ? $beforeRows[(int) $contentId] : null;
        $beforeStatus = is_array($beforeRow) ? (string) ($beforeRow['status'] ?? '') : '';
        $beforePublishedAt = is_array($beforeRow) ? (string) ($beforeRow['published_at'] ?? '') : '';
        $afterStatus = (string) ($afterRow['status'] ?? '');
        $afterPublishedAt = (string) ($afterRow['published_at'] ?? '');
        if ($afterStatus === 'scheduled' && ($beforeStatus !== 'scheduled' || $beforePublishedAt !== $afterPublishedAt)) {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) ($account['id'] ?? 0),
                'actor_type' => 'admin',
                'event_type' => 'content.scheduled',
                'target_type' => 'content',
                'target_id' => (string) (int) $contentId,
                'result' => 'success',
                'message' => 'Content scheduled for publishing.',
                'metadata' => [
                    'slug' => (string) ($afterRow['slug'] ?? ''),
                    'scheduled_publish_at' => $afterPublishedAt,
                    'previous_status' => $beforeStatus,
                    'previous_published_at' => $beforePublishedAt,
                ],
            ]);
        } elseif ($beforeStatus === 'scheduled' && $afterStatus !== 'scheduled') {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) ($account['id'] ?? 0),
                'actor_type' => 'admin',
                'event_type' => 'content.schedule_cleared',
                'target_type' => 'content',
                'target_id' => (string) (int) $contentId,
                'result' => 'success',
                'message' => 'Content schedule cleared.',
                'metadata' => [
                    'slug' => (string) ($afterRow['slug'] ?? ''),
                    'status' => $afterStatus,
                    'previous_published_at' => $beforePublishedAt,
                ],
            ]);
        }
    }
}

function sr_content_apply_setting_scope(PDO $pdo, int $pageId, int $pageGroupId, string $settingKey, string $scope, array $values, int $accountId, string $now): void
{
    $targets = sr_content_apply_scope_target_ids($pdo, $pageId, $pageGroupId, $scope);
    if ($targets === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($targets), '?'));
    $params = [];
    $sql = '';
    if ($settingKey === 'status') {
        $status = (string) ($values['status'] ?? 'draft');
        $scheduledPublishAt = (string) ($values['scheduled_publish_at'] ?? '');
        $sql = "UPDATE sr_content_items
                SET status = ?,
                    published_at = CASE
                        WHEN ? = 'published' THEN CASE
                            WHEN status = 'published' AND published_at IS NOT NULL THEN published_at
                            ELSE ?
                        END
                        WHEN ? = 'scheduled' THEN ?
                        ELSE NULL
                    END,
                    updated_by = ?, updated_at = ?
                WHERE id IN (" . $placeholders . ')';
        $params = [$status, $status, $now, $status, $scheduledPublishAt !== '' ? $scheduledPublishAt : null, $accountId, $now];
    } elseif ($settingKey === 'layout_key') {
        $sql = 'UPDATE sr_content_items SET layout_key = ?, updated_by = ?, updated_at = ? WHERE id IN (' . $placeholders . ')';
        $params = [(string) ($values['layout_key'] ?? ''), $accountId, $now];
    } elseif (in_array($settingKey, ['reaction_preset_key', 'reaction_comment_preset_key'], true)) {
        $sql = 'UPDATE sr_content_items SET ' . $settingKey . ' = ?, updated_by = ?, updated_at = ? WHERE id IN (' . $placeholders . ')';
        $params = [sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $values[$settingKey] ?? '') : '', $accountId, $now];
    } elseif ($settingKey === 'comment_editor_key') {
        $sql = 'UPDATE sr_content_items SET comment_editor_key = ?, updated_by = ?, updated_at = ? WHERE id IN (' . $placeholders . ')';
        $params = [sr_editor_normalize_key((string) ($values['comment_editor_key'] ?? 'inherit'), true), $accountId, $now];
    } elseif ($settingKey === 'comment_extra_fields_json') {
        $sql = 'UPDATE sr_content_items SET comment_extra_fields_json = ?, updated_by = ?, updated_at = ? WHERE id IN (' . $placeholders . ')';
        $params = [sr_comment_extra_field_definitions_json($values['comment_extra_fields_json'] ?? '[]'), $accountId, $now];
    } elseif (in_array($settingKey, ['banner_before_content_id', 'banner_after_content_id', 'popup_layer_id'], true)) {
        $sql = 'UPDATE sr_content_items SET ' . $settingKey . ' = ?, updated_by = ?, updated_at = ? WHERE id IN (' . $placeholders . ')';
        $params = [(int) ($values[$settingKey] ?? 0), $accountId, $now];
    } elseif (in_array($settingKey, sr_content_group_asset_access_setting_keys(), true) || in_array($settingKey, sr_content_group_asset_action_setting_keys(), true)) {
        $sql = 'UPDATE sr_content_items SET ' . $settingKey . ' = ?, updated_by = ?, updated_at = ? WHERE id IN (' . $placeholders . ')';
        $params = [$values[$settingKey] ?? '', $accountId, $now];
    } else {
        return;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, $targets));
    foreach ($targets as $targetPageId) {
        sr_content_set_setting_source($pdo, (int) $targetPageId, $settingKey, 'content');
    }
}

function sr_content_create_group(PDO $pdo, array $data): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_groups
            (group_key, title, description, status, sort_order, created_at, updated_at)
         VALUES
            (:group_key, :title, :description, :status, :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute([
        'group_key' => (string) $data['group_key'],
        'title' => (string) $data['title'],
        'description' => (string) ($data['description'] ?? ''),
        'status' => (string) $data['status'],
        'sort_order' => (int) ($data['sort_order'] ?? 0),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_content_update_group(PDO $pdo, int $groupId, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_content_groups
         SET title = :title,
             description = :description,
             status = :status,
             sort_order = :sort_order,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'title' => (string) $data['title'],
        'description' => (string) ($data['description'] ?? ''),
        'status' => (string) $data['status'],
        'sort_order' => (int) ($data['sort_order'] ?? 0),
        'updated_at' => sr_now(),
        'id' => $groupId,
    ]);
}

function sr_content_set_group_setting(PDO $pdo, int $groupId, string $settingKey, string $settingValue, string $valueType = 'string'): void
{
    if ($groupId < 1 || !in_array($settingKey, sr_content_group_setting_keys(), true) || !sr_content_group_settings_table_exists($pdo)) {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_group_settings
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

function sr_content_group_settings(PDO $pdo, int $groupId): array
{
    if ($groupId < 1 || !sr_content_group_settings_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT setting_key, setting_value
         FROM sr_content_group_settings
         WHERE group_id = :group_id'
    );
    $stmt->execute(['group_id' => $groupId]);

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settingKey = (string) ($row['setting_key'] ?? '');
        if (in_array($settingKey, sr_content_group_setting_keys(), true)) {
            $settings[$settingKey] = (string) ($row['setting_value'] ?? '');
        }
    }

    return $settings;
}

function sr_content_group_reference_counts(PDO $pdo, int $groupId): array
{
    return [
        'contents' => $groupId > 0 ? sr_content_count($pdo, 'sr_content_items', 'content_group_id = :group_id', ['group_id' => $groupId]) : 0,
        'revision_references' => $groupId > 0 ? sr_content_count($pdo, 'sr_content_revisions', 'content_group_id = :group_id', ['group_id' => $groupId]) : 0,
        'comments' => $groupId > 0
            ? sr_content_count($pdo, 'sr_content_comments', 'content_id IN (SELECT id FROM sr_content_items WHERE content_group_id = :group_id)', ['group_id' => $groupId])
            : 0,
        'files' => $groupId > 0
            ? sr_content_count($pdo, 'sr_content_files', 'content_id IN (SELECT id FROM sr_content_items WHERE content_group_id = :group_id)', ['group_id' => $groupId])
            : 0,
    ];
}

function sr_content_group_external_reference_counts(PDO $pdo, int $groupId): array
{
    $group = sr_content_group_by_id($pdo, $groupId);
    if (!is_array($group)) {
        return ['site_menu' => 0, 'homepage' => 0];
    }

    $groupKey = (string) ($group['group_key'] ?? '');
    $groupPath = $groupKey !== '' ? sr_content_group_path($groupKey) : '';
    $siteSettings = sr_site_settings($pdo);
    return [
        'site_menu' => $groupPath !== ''
            ? sr_content_optional_count($pdo, 'sr_site_menu_items', 'url = :url', ['url' => $groupPath])
            : 0,
        'homepage' => $groupPath !== '' && (string) ($siteSettings['site.home_path'] ?? '') === $groupPath ? 1 : 0,
    ];
}

function sr_content_can_delete_group(PDO $pdo, int $groupId): array
{
    $group = sr_content_group_by_id($pdo, $groupId);
    if (!is_array($group)) {
        return ['can_delete' => false, 'errors' => ['콘텐츠 그룹을 찾을 수 없습니다.'], 'references' => [], 'external_references' => []];
    }

    $references = sr_content_group_reference_counts($pdo, $groupId);
    $externalReferences = sr_content_group_external_reference_counts($pdo, $groupId);
    $errors = [];
    if (array_sum(array_map('intval', $externalReferences)) > 0) {
        $errors[] = '사이트 메뉴, 초기화면 등 외부 운영 참조가 있어 콘텐츠 그룹을 삭제할 수 없습니다.';
    }

    return ['can_delete' => $errors === [], 'errors' => $errors, 'references' => $references, 'external_references' => $externalReferences, 'group' => $group];
}

function sr_content_delete_group(PDO $pdo, int $groupId): array
{
    $check = sr_content_can_delete_group($pdo, $groupId);
    if (empty($check['can_delete']) || !is_array($check['group'] ?? null)) {
        return $check;
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $deletedSettings = sr_content_count($pdo, 'sr_content_group_settings', 'group_id = :group_id', ['group_id' => $groupId]);
        $detachedContents = (int) ($check['references']['contents'] ?? 0);
        $now = sr_now();
        if (sr_content_setting_sources_table_exists($pdo)) {
            $pdo->prepare(
                "UPDATE sr_content_setting_sources
                 SET source = 'content', updated_at = :updated_at
                 WHERE source = 'group'
                   AND content_id IN (SELECT id FROM sr_content_items WHERE content_group_id = :group_id)"
            )->execute(['updated_at' => $now, 'group_id' => $groupId]);
        }
        $pdo->prepare('UPDATE sr_content_items SET content_group_id = NULL, updated_at = :updated_at WHERE content_group_id = :group_id')->execute([
            'updated_at' => $now,
            'group_id' => $groupId,
        ]);
        $pdo->prepare('DELETE FROM sr_content_group_settings WHERE group_id = :group_id')->execute(['group_id' => $groupId]);
        $pdo->prepare('DELETE FROM sr_content_groups WHERE id = :id')->execute(['id' => $groupId]);
        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $check['deleted_settings'] = $deletedSettings;
    $check['detached_contents'] = $detachedContents;
    return $check;
}

function sr_content_group_file_rows_for_delete(PDO $pdo, array $contentIds): array
{
    $contentIds = array_values(array_filter(array_map('intval', $contentIds), static fn (int $contentId): bool => $contentId > 0));
    if ($contentIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($contentIds), '?'));
    $params = $contentIds;
    $linkClause = '';
    $linkClause = ' OR (
        f.content_id = 0
        AND EXISTS (SELECT 1 FROM sr_content_file_links owned_link WHERE owned_link.file_id = f.id AND owned_link.content_id IN (' . $placeholders . '))
        AND NOT EXISTS (SELECT 1 FROM sr_content_file_links outside_link WHERE outside_link.file_id = f.id AND outside_link.content_id NOT IN (' . $placeholders . '))
    )';
    $params = array_merge($params, $contentIds, $contentIds);

    $stmt = $pdo->prepare(
        'SELECT f.*
         FROM sr_content_files f
         WHERE f.content_id IN (' . $placeholders . ')' . $linkClause . '
         ORDER BY f.id ASC'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_content_setting_sources(PDO $pdo, int $pageId): array
{
    if ($pageId < 1 || !sr_content_setting_sources_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT setting_key, source
         FROM sr_content_setting_sources
         WHERE content_id = :content_id'
    );
    $stmt->execute(['content_id' => $pageId]);

    $sources = [];
    foreach ($stmt->fetchAll() as $row) {
        $settingKey = (string) ($row['setting_key'] ?? '');
        if (in_array($settingKey, sr_content_group_setting_keys(), true)) {
            $sources[$settingKey] = sr_content_normalize_setting_source((string) ($row['source'] ?? 'content'));
        }
    }

    return $sources;
}

function sr_content_set_setting_source(PDO $pdo, int $pageId, string $settingKey, string $source): void
{
    if ($pageId < 1 || !in_array($settingKey, sr_content_group_setting_keys(), true) || !sr_content_setting_sources_table_exists($pdo)) {
        return;
    }

    $source = sr_content_normalize_setting_source($source);
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_setting_sources
            (content_id, setting_key, source, created_at, updated_at)
         VALUES
            (:content_id, :setting_key, :source, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            source = VALUES(source),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'content_id' => $pageId,
        'setting_key' => $settingKey,
        'source' => $source,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function sr_content_effective_setting(PDO $pdo, array $page, string $settingKey, mixed $default = ''): string
{
    if (!in_array($settingKey, sr_content_group_setting_keys(), true)) {
        return (string) $default;
    }

    return (string) ($page[$settingKey] ?? $default);
}

function sr_content_with_effective_settings(PDO $pdo, array $page): array
{
    foreach (sr_content_group_setting_keys() as $settingKey) {
        $page[$settingKey] = sr_content_effective_setting($pdo, $page, $settingKey, (string) ($page[$settingKey] ?? ''));
    }

    return sr_content_normalize_asset_values($page);
}
