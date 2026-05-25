<?php

declare(strict_types=1);

function sr_content_allowed_statuses(): array
{
    return ['draft', 'published', 'hidden'];
}

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
    return ['status', 'layout_key'];
}

function sr_content_group_asset_access_setting_keys(): array
{
    return [
        'asset_access_enabled',
        'asset_module',
        'asset_access_amount',
        'asset_charge_policy',
    ];
}

function sr_content_group_asset_action_setting_keys(): array
{
    return [
        'asset_action_enabled',
        'asset_action_module',
        'asset_action_amount',
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

function sr_content_setting_source_values(): array
{
    return ['content', 'group', 'all'];
}

function sr_content_normalize_setting_source(string $source): string
{
    if ($source === 'here_only') {
        return 'content';
    }

    return in_array($source, sr_content_setting_source_values(), true) ? $source : 'content';
}

function sr_content_asset_modules(): array
{
    return [
        'point' => [
            'label' => sr_t('content::asset.point'),
            'module_key' => 'point',
            'helper' => SR_ROOT . '/modules/point/helpers.php',
            'balance_function' => 'sr_point_balance',
            'transaction_function' => 'sr_point_create_transaction',
            'transaction_table' => 'sr_point_transactions',
            'use_type' => 'use',
            'credit_type' => 'grant',
            'refund_type' => 'refund',
        ],
        'reward' => [
            'label' => sr_t('content::asset.reward'),
            'module_key' => 'reward',
            'helper' => SR_ROOT . '/modules/reward/helpers.php',
            'balance_function' => 'sr_reward_balance',
            'transaction_function' => 'sr_reward_create_transaction',
            'transaction_table' => 'sr_reward_transactions',
            'use_type' => 'use',
            'credit_type' => 'grant',
            'refund_type' => 'refund',
        ],
        'deposit' => [
            'label' => sr_t('content::asset.deposit'),
            'module_key' => 'deposit',
            'helper' => SR_ROOT . '/modules/deposit/helpers.php',
            'balance_function' => 'sr_deposit_balance',
            'transaction_function' => 'sr_deposit_create_transaction',
            'transaction_table' => 'sr_deposit_transactions',
            'use_type' => 'use',
            'credit_type' => 'deposit',
            'refund_type' => 'refund',
        ],
    ];
}

function sr_content_asset_charge_policies(): array
{
    return sr_content_asset_view_charge_policies() + sr_content_asset_download_charge_policies();
}

function sr_content_asset_view_charge_policies(): array
{
    return [
        'once' => '최초 1회',
        'every_view' => '매 열람',
    ];
}

function sr_content_asset_download_charge_policies(): array
{
    return [
        'once' => '최초 1회',
        'every_download' => '매 다운로드',
    ];
}

function sr_content_asset_action_directions(): array
{
    return [
        'grant' => '지급',
        'use' => '차감',
    ];
}

function sr_content_asset_module_is_available(PDO $pdo, string $assetModule): bool
{
    $options = sr_content_asset_modules();
    if (!isset($options[$assetModule])) {
        return false;
    }

    $option = $options[$assetModule];
    $moduleKey = (string) ($option['module_key'] ?? '');
    $helper = (string) ($option['helper'] ?? '');
    if (!sr_module_enabled($pdo, $moduleKey) || !is_file($helper)) {
        return false;
    }

    require_once $helper;

    return function_exists((string) ($option['balance_function'] ?? ''))
        && function_exists((string) ($option['transaction_function'] ?? ''));
}

function sr_content_asset_module_options(PDO $pdo): array
{
    $available = [];
    foreach (sr_content_asset_modules() as $assetModule => $option) {
        if (sr_content_asset_module_is_available($pdo, (string) $assetModule)) {
            $available[$assetModule] = $option;
        }
    }

    return $available;
}

function sr_content_asset_module_label(string $assetModule): string
{
    $options = sr_content_asset_modules();
    return isset($options[$assetModule]) ? (string) $options[$assetModule]['label'] : '회원 자산';
}

function sr_content_asset_deduction_order(): array
{
    return ['point', 'reward', 'deposit'];
}

function sr_content_asset_module_keys_from_value(mixed $value): array
{
    $rawValues = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value);
    $selected = [];
    foreach (is_array($rawValues) ? $rawValues : [] as $rawValue) {
        $assetModule = sr_content_clean_slug((string) $rawValue);
        if (isset(sr_content_asset_modules()[$assetModule])) {
            $selected[$assetModule] = true;
        }
    }

    $ordered = [];
    foreach (sr_content_asset_deduction_order() as $assetModule) {
        if (isset($selected[$assetModule])) {
            $ordered[] = $assetModule;
        }
    }

    return $ordered;
}

function sr_content_asset_module_value_from_keys(array $assetModules): string
{
    return implode(',', sr_content_asset_module_keys_from_value($assetModules));
}

function sr_content_asset_module_labels(string $assetModuleValue): string
{
    $labels = [];
    foreach (sr_content_asset_module_keys_from_value($assetModuleValue) as $assetModule) {
        $labels[] = sr_content_asset_module_label($assetModule);
    }

    return $labels !== [] ? implode(', ', $labels) : '회원 자산';
}

function sr_content_asset_modules_available(PDO $pdo, array $assetModules): bool
{
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        if (!sr_content_asset_module_is_available($pdo, $assetModule)) {
            return false;
        }
    }

    return true;
}

function sr_content_asset_combined_balance(PDO $pdo, array $assetModules, int $accountId): int
{
    $balance = 0;
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        $balance += sr_content_asset_balance($pdo, $assetModule, $accountId);
    }

    return $balance;
}

function sr_content_reserved_slugs(): array
{
    return ['account', 'action', 'admin', 'api', 'assets', 'community', 'content', 'download', 'group', 'login', 'logout', 'modules', 'pages', 'register'];
}

function sr_content_clean_single_line(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $value)) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_content_clean_text(string $value, int $maxLength): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    if (function_exists('mb_substr')) {
        return trim(mb_substr($value, 0, $maxLength));
    }

    return trim(substr($value, 0, $maxLength));
}

function sr_content_clean_slug(string $value): string
{
    return strtolower(trim($value));
}

function sr_content_slug_is_valid(string $slug): bool
{
    return preg_match('/\A[a-z0-9][a-z0-9-]{1,118}[a-z0-9]\z/', $slug) === 1
        && !in_array($slug, sr_content_reserved_slugs(), true);
}

function sr_content_path(string $slug): string
{
    return '/content/' . rawurlencode($slug);
}

function sr_content_slug_from_request_path(): string
{
    $path = sr_request_path();
    $prefix = '/content/';
    if (!str_starts_with($path, $prefix)) {
        return '';
    }

    $slug = substr($path, strlen($prefix));
    if (!is_string($slug) || $slug === '' || strpos($slug, '/') !== false) {
        return '';
    }

    return sr_content_clean_slug($slug);
}

function sr_content_by_id(PDO $pdo, int $pageId): ?array
{
    if ($pageId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_content_items WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $pageId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
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

function sr_content_published_by_slug(PDO $pdo, string $slug): ?array
{
    if (!sr_content_slug_is_valid($slug)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_content_items
         WHERE slug = :slug
         LIMIT 1"
    );
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();

    if (!is_array($row)) {
        return null;
    }

    $page = sr_content_with_effective_settings($pdo, $row);
    return (string) ($page['status'] ?? '') === 'published' ? $page : null;
}

function sr_content_slug_exists(PDO $pdo, string $slug, int $exceptPageId = 0): bool
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_content_items
         WHERE slug = :slug
           AND id <> :except_id
         LIMIT 1'
    );
    $stmt->execute([
        'slug' => $slug,
        'except_id' => $exceptPageId,
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
    $status = sr_get_string('status', 30);
    if ($status !== '' && !in_array($status, sr_content_group_statuses(), true)) {
        $status = '';
    }

    $field = sr_get_string('field', 20);
    if (!in_array($field, ['all', 'key', 'title'], true)) {
        $field = 'all';
    }

    return [
        'status' => $status,
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

function sr_content_admin_group_list(PDO $pdo, array $filters): array
{
    if (!sr_content_groups_table_exists($pdo)) {
        return [];
    }

    $where = [];
    $params = [];
    if ((string) ($filters['status'] ?? '') !== '') {
        $where[] = 'g.status = :status';
        $params['status'] = (string) $filters['status'];
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

    $sql = 'SELECT g.*,
                   COUNT(p.id) AS content_count
            FROM sr_content_groups g
            LEFT JOIN sr_content_items p ON p.content_group_id = g.id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at
              ORDER BY g.sort_order ASC, g.id ASC
              LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_content_admin_filters(): array
{
    $status = sr_get_string('status', 30);
    if ($status !== '' && !in_array($status, sr_content_allowed_statuses(), true)) {
        $status = '';
    }

    $field = sr_get_string('field', 20);
    if (!in_array($field, ['all', 'title', 'slug'], true)) {
        $field = 'all';
    }

    return [
        'status' => $status,
        'content_group_id' => (int) sr_get_string('content_group_id', 20),
        'field' => $field,
        'q' => sr_content_clean_single_line(sr_get_string('q', 120), 120),
    ];
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
    if ($pageId < 1) {
        return [];
    }

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

    return [$pageId];
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
        $sql = "UPDATE sr_content_items
                SET status = ?, published_at = CASE WHEN ? = 'published' THEN COALESCE(published_at, ?) ELSE NULL END, updated_by = ?, updated_at = ?
                WHERE id IN (" . $placeholders . ')';
        $params = [$status, $status, $now, $accountId, $now];
    } elseif ($settingKey === 'layout_key') {
        $sql = 'UPDATE sr_content_items SET layout_key = ?, updated_by = ?, updated_at = ? WHERE id IN (' . $placeholders . ')';
        $params = [(string) ($values['layout_key'] ?? ''), $accountId, $now];
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

function sr_content_admin_status_counts(PDO $pdo): array
{
    $counts = [
        'total' => 0,
        'draft' => 0,
        'published' => 0,
        'hidden' => 0,
    ];

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_content_items GROUP BY status');
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

function sr_content_admin_list(PDO $pdo, array $filters): array
{
    $where = [];
    $params = [];
    if ((string) ($filters['status'] ?? '') !== '') {
        $where[] = 'p.status = :status';
        $params['status'] = (string) $filters['status'];
    }

    if ((int) ($filters['content_group_id'] ?? 0) > 0) {
        $where[] = 'p.content_group_id = :content_group_id';
        $params['content_group_id'] = (int) $filters['content_group_id'];
    }

    if ((string) ($filters['q'] ?? '') !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'title') {
            $where[] = 'p.title LIKE :keyword';
            $params['keyword'] = '%' . (string) $filters['q'] . '%';
        } elseif ($field === 'slug') {
            $where[] = 'p.slug LIKE :keyword';
            $params['keyword'] = '%' . (string) $filters['q'] . '%';
        } else {
            $where[] = '(p.title LIKE :title_keyword OR p.slug LIKE :slug_keyword)';
            $params['title_keyword'] = '%' . (string) $filters['q'] . '%';
            $params['slug_keyword'] = '%' . (string) $filters['q'] . '%';
        }
    }

    $sql = 'SELECT p.*, g.group_key AS content_group_key, g.title AS content_group_title,
                   creator.display_name AS created_by_name, updater.display_name AS updated_by_name
            FROM sr_content_items p
            LEFT JOIN sr_content_groups g ON g.id = p.content_group_id
            LEFT JOIN sr_member_accounts creator ON creator.id = p.created_by
            LEFT JOIN sr_member_accounts updater ON updater.id = p.updated_by';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.updated_at DESC, p.id DESC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_content_published_contents_for_group(PDO $pdo, int $groupId): array
{
    if ($groupId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT id, slug, title, summary, updated_at, published_at
         FROM sr_content_items
         WHERE content_group_id = :group_id
           AND status = 'published'
         ORDER BY published_at DESC, updated_at DESC, id DESC"
    );
    $stmt->execute(['group_id' => $groupId]);

    return $stmt->fetchAll();
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

function sr_content_group_setting_value(PDO $pdo, int $groupId, string $settingKey): ?string
{
    if ($groupId < 1 || !in_array($settingKey, sr_content_group_setting_keys(), true) || !sr_content_group_settings_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT setting_value
         FROM sr_content_group_settings
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

function sr_content_setting_source(PDO $pdo, int $pageId, string $settingKey): string
{
    if ($pageId < 1 || !in_array($settingKey, sr_content_group_setting_keys(), true) || !sr_content_setting_sources_table_exists($pdo)) {
        return 'content';
    }

    $stmt = $pdo->prepare(
        'SELECT source
         FROM sr_content_setting_sources
         WHERE content_id = :content_id
           AND setting_key = :setting_key
         LIMIT 1'
    );
    $stmt->execute([
        'content_id' => $pageId,
        'setting_key' => $settingKey,
    ]);
    $source = $stmt->fetchColumn();

    return sr_content_normalize_setting_source(is_string($source) ? $source : 'content');
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

    $pageId = (int) ($page['id'] ?? 0);
    $groupId = (int) ($page['content_group_id'] ?? 0);
    if ($pageId > 0 && $groupId > 0 && sr_content_setting_source($pdo, $pageId, $settingKey) === 'group') {
        $groupValue = sr_content_group_setting_value($pdo, $groupId, $settingKey);
        if (is_string($groupValue) && $groupValue !== '') {
            return $groupValue;
        }
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

function sr_content_homepage_candidates(PDO $pdo): array
{
    $candidates = [];

    foreach (sr_content_enabled_groups($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_content_group_key_is_valid($groupKey)) {
            continue;
        }

        $path = sr_content_group_path($groupKey);
        $candidates[] = [
            'module_key' => 'content',
            'label' => sr_t('content::homepage.group_candidate_label', ['title' => (string) ($group['title'] ?? $groupKey)]),
            'path' => $path,
            'detail' => $path,
            'available' => true,
        ];
    }

    $stmt = $pdo->query(
        "SELECT id, slug, title, updated_at
         FROM sr_content_items
         WHERE status = 'published'
         ORDER BY updated_at DESC, id DESC
         LIMIT 200"
    );

    foreach ($stmt->fetchAll() as $page) {
        $slug = (string) ($page['slug'] ?? '');
        if (!sr_content_slug_is_valid($slug)) {
            continue;
        }

        $path = sr_content_path($slug);
        $candidates[] = [
            'module_key' => 'content',
            'label' => sr_t('content::homepage.candidate_label', ['title' => (string) ($page['title'] ?? $slug)]),
            'path' => $path,
            'detail' => $path,
            'available' => true,
        ];
    }

    return $candidates;
}

function sr_content_public_banner_setting_labels(): array
{
    return [
        'banner_before_content_id' => '본문 상단 배너',
        'banner_after_content_id' => '본문 하단 배너',
    ];
}

function sr_content_public_popup_layer_setting_labels(): array
{
    return [
        'popup_layer_id' => '콘텐츠 팝업레이어',
    ];
}

function sr_content_public_display_setting_labels(): array
{
    return sr_content_public_banner_setting_labels() + sr_content_public_popup_layer_setting_labels();
}

function sr_content_normalize_asset_values(array $values, bool $coerceInvalid = true): array
{
    $assetModule = sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($values['asset_module'] ?? ''));

    $chargePolicy = (string) ($values['asset_charge_policy'] ?? 'once');
    if ($coerceInvalid && !isset(sr_content_asset_charge_policies()[$chargePolicy])) {
        $chargePolicy = 'once';
    }

    $values['asset_access_enabled'] = (int) ($values['asset_access_enabled'] ?? 0) === 1 ? 1 : 0;
    $values['asset_module'] = $assetModule;
    $values['asset_access_amount'] = max(0, (int) ($values['asset_access_amount'] ?? 0));
    $values['asset_charge_policy'] = $chargePolicy;

    $actionModule = sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($values['asset_action_module'] ?? ''));

    $actionDirection = (string) ($values['asset_action_direction'] ?? 'grant');
    if ($coerceInvalid && !isset(sr_content_asset_action_directions()[$actionDirection])) {
        $actionDirection = 'grant';
    }

    $values['asset_action_enabled'] = (int) ($values['asset_action_enabled'] ?? 0) === 1 ? 1 : 0;
    $values['asset_action_module'] = $actionModule;
    $values['asset_action_amount'] = max(0, (int) ($values['asset_action_amount'] ?? 0));
    $values['asset_action_direction'] = $actionDirection;
    $values['asset_action_label'] = sr_content_clean_single_line((string) ($values['asset_action_label'] ?? '완료'), 80);
    if ((string) $values['asset_action_label'] === '') {
        $values['asset_action_label'] = '완료';
    }

    return $values;
}

function sr_content_asset_settings_for_audit(array $values): array
{
    $values = sr_content_normalize_asset_values($values);
    $settings = [];
    foreach (sr_content_group_asset_setting_keys() as $settingKey) {
        $settings[$settingKey] = $values[$settingKey] ?? '';
        $settings['source_' . $settingKey] = sr_content_normalize_setting_source((string) ($values['source_' . $settingKey] ?? 'content'));
    }
    foreach (sr_content_group_file_asset_setting_keys() as $settingKey) {
        $settings['source_' . $settingKey] = sr_content_normalize_setting_source((string) ($values['source_' . $settingKey] ?? 'content'));
    }

    return $settings;
}

function sr_content_file_asset_settings_for_audit(array $file): array
{
    $values = sr_content_normalize_file_asset_values([
        'asset_download_enabled' => (int) ($file['asset_download_enabled'] ?? 0),
        'asset_module' => (string) ($file['asset_module'] ?? ''),
        'asset_download_amount' => (int) ($file['asset_download_amount'] ?? 0),
        'asset_charge_policy' => (string) ($file['asset_charge_policy'] ?? 'once'),
    ]);

    return [
        'asset_download_enabled' => (int) $values['asset_download_enabled'],
        'asset_module' => (string) $values['asset_module'],
        'asset_download_amount' => (int) $values['asset_download_amount'],
        'asset_charge_policy' => (string) $values['asset_charge_policy'],
    ];
}

function sr_content_files_asset_settings_for_audit(PDO $pdo, int $pageId): array
{
    $settings = [];
    foreach (sr_content_files_for_content($pdo, $pageId) as $file) {
        $settings[(string) (int) $file['id']] = sr_content_file_asset_settings_for_audit($file);
    }

    return $settings;
}

function sr_content_asset_settings_from_storage_for_audit(PDO $pdo, int $pageId): array
{
    $page = sr_content_by_id($pdo, $pageId);
    if (!is_array($page)) {
        return [];
    }

    $sources = sr_content_setting_sources($pdo, $pageId);
    foreach (sr_content_group_asset_setting_keys() as $settingKey) {
        $page['source_' . $settingKey] = $sources[$settingKey] ?? 'content';
    }
    foreach (sr_content_group_file_asset_setting_keys() as $settingKey) {
        $page['source_' . $settingKey] = $sources[$settingKey] ?? 'content';
    }

    return [
        'content' => sr_content_asset_settings_for_audit($page),
        'files' => sr_content_files_asset_settings_for_audit($pdo, $pageId),
    ];
}

function sr_content_group_asset_settings_for_audit(array $settings): array
{
    $assetSettings = sr_content_normalize_asset_values($settings);
    $fileAssetSettings = sr_content_normalize_file_asset_values([
        'asset_download_enabled' => $settings['file_asset_download_enabled'] ?? 0,
        'asset_module' => $settings['file_asset_module'] ?? '',
        'asset_download_amount' => $settings['file_asset_download_amount'] ?? 0,
        'asset_charge_policy' => $settings['file_asset_charge_policy'] ?? 'once',
    ]);

    $auditSettings = [];
    foreach (sr_content_group_asset_setting_keys() as $settingKey) {
        $auditSettings[$settingKey] = $assetSettings[$settingKey] ?? '';
    }
    $auditSettings['file_asset_download_enabled'] = (int) $fileAssetSettings['asset_download_enabled'];
    $auditSettings['file_asset_module'] = (string) $fileAssetSettings['asset_module'];
    $auditSettings['file_asset_download_amount'] = (int) $fileAssetSettings['asset_download_amount'];
    $auditSettings['file_asset_charge_policy'] = (string) $fileAssetSettings['asset_charge_policy'];

    return $auditSettings;
}

function sr_content_group_asset_settings_from_storage_for_audit(PDO $pdo, int $groupId): array
{
    return sr_content_group_asset_settings_for_audit(sr_content_group_settings($pdo, $groupId));
}

function sr_content_input_values(): array
{
    $pageGroupScope = sr_content_group_apply_scope(sr_post_string('content_group_scope', 20));
    $pageGroupId = (int) sr_post_string('content_group_id', 20);

    $values = [
        'content_group_scope' => $pageGroupScope,
        'content_group_id' => $pageGroupId,
        'source_status' => sr_content_normalize_setting_source(sr_post_string('source_status', 20)),
        'source_layout_key' => sr_content_normalize_setting_source(sr_post_string('source_layout_key', 20)),
        'title' => sr_content_clean_single_line(sr_post_string('title', 160), 160),
        'slug' => sr_content_clean_slug(sr_post_string('slug', 120)),
        'summary' => sr_content_clean_text(sr_post_string('summary', 1000), 1000),
        'body_text' => sr_content_clean_text(sr_post_string('body_text', 100000), 100000),
        'body_format' => 'plain',
        'status' => sr_post_string('status', 30),
        'layout_key' => sr_public_layout_normalize_key(sr_post_string('layout_key', 80)),
        'asset_access_enabled' => sr_post_string('asset_access_enabled', 1) === '1' ? 1 : 0,
        'asset_module' => sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($_POST['asset_module'] ?? '')),
        'asset_access_amount' => (int) sr_post_string('asset_access_amount', 20),
        'asset_charge_policy' => sr_content_clean_slug(sr_post_string('asset_charge_policy', 20)),
        'asset_action_enabled' => sr_post_string('asset_action_enabled', 1) === '1' ? 1 : 0,
        'asset_action_module' => sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($_POST['asset_action_module'] ?? '')),
        'asset_action_amount' => (int) sr_post_string('asset_action_amount', 20),
        'asset_action_direction' => sr_content_clean_slug(sr_post_string('asset_action_direction', 20)),
        'asset_action_label' => sr_content_clean_single_line(sr_post_string('asset_action_label', 80), 80),
        'seo_title' => sr_content_clean_single_line(sr_post_string('seo_title', 160), 160),
        'seo_description' => sr_content_clean_single_line(sr_post_string('seo_description', 255), 255),
    ];

    foreach (sr_content_public_display_setting_labels() as $settingKey => $settingLabel) {
        $rawValue = sr_post_string($settingKey, 20);
        $values[$settingKey] = preg_match('/\A[0-9]{1,9}\z/', $rawValue) === 1 ? (int) $rawValue : -1;
        $values['source_' . $settingKey] = sr_content_normalize_setting_source(sr_post_string('source_' . $settingKey, 20));
    }

    $legacyAssetSource = sr_content_normalize_setting_source(sr_post_string('asset_policy_source', 20));
    $legacyAccessSource = sr_content_normalize_setting_source(sr_post_string('asset_access_policy_source', 20));
    if (sr_post_string('asset_access_policy_source', 20) === '') {
        $legacyAccessSource = $legacyAssetSource;
    }
    $legacyActionSource = sr_content_normalize_setting_source(sr_post_string('asset_action_policy_source', 20));
    if (sr_post_string('asset_action_policy_source', 20) === '') {
        $legacyActionSource = $legacyAssetSource;
    }
    $legacyFileSource = sr_content_normalize_setting_source(sr_post_string('file_asset_policy_source', 20));
    foreach (sr_content_group_asset_access_setting_keys() as $settingKey) {
        $postedSource = sr_post_string('source_' . $settingKey, 20);
        $values['source_' . $settingKey] = $postedSource !== ''
            ? sr_content_normalize_setting_source($postedSource)
            : $legacyAccessSource;
    }
    foreach (sr_content_group_asset_action_setting_keys() as $settingKey) {
        $postedSource = sr_post_string('source_' . $settingKey, 20);
        $values['source_' . $settingKey] = $postedSource !== ''
            ? sr_content_normalize_setting_source($postedSource)
            : $legacyActionSource;
    }
    foreach (sr_content_group_file_asset_setting_keys() as $settingKey) {
        $postedSource = sr_post_string('source_' . $settingKey, 20);
        $values['source_' . $settingKey] = $postedSource !== ''
            ? sr_content_normalize_setting_source($postedSource)
            : $legacyFileSource;
    }

    return sr_content_normalize_asset_values($values, false);
}

function sr_content_validate_input(PDO $pdo, array $values, int $pageId = 0, array $publicBannerIds = [], array $publicPopupLayerIds = []): array
{
    $errors = [];
    if ((string) ($values['title'] ?? '') === '') {
        $errors[] = '제목을 입력하세요.';
    }

    $pageGroupId = (int) ($values['content_group_id'] ?? 0);
    if ($pageGroupId < 0 || ($pageGroupId > 0 && !is_array(sr_content_group_by_id($pdo, $pageGroupId)))) {
        $errors[] = '콘텐츠 그룹 값이 올바르지 않습니다.';
    }
    if (sr_content_group_apply_scope((string) ($values['content_group_scope'] ?? 'here_only')) === 'group' && $pageGroupId < 1) {
        $errors[] = '그룹적용을 선택하려면 콘텐츠 그룹을 선택하세요.';
    }

    $sourceLabels = [
        'source_status' => '상태',
        'source_layout_key' => '콘텐츠 레이아웃',
    ];
    foreach ([
        'asset_access_enabled' => '유료 열람 사용',
        'asset_module' => '차감 자산',
        'asset_access_amount' => '차감 금액',
        'asset_charge_policy' => '과금 방식',
        'asset_action_enabled' => '완료 버튼 사용',
        'asset_action_module' => '완료 버튼 대상 자산',
        'asset_action_amount' => '완료 버튼 금액',
        'asset_action_direction' => '완료 버튼 처리 방향',
        'asset_action_label' => '완료 버튼 문구',
    ] as $settingKey => $sourceLabel) {
        $sourceLabels['source_' . $settingKey] = $sourceLabel;
    }
    foreach ($sourceLabels as $sourceKey => $sourceLabel) {
        if (sr_content_normalize_setting_source((string) ($values[$sourceKey] ?? 'content')) === 'group' && $pageGroupId < 1) {
            $errors[] = $sourceLabel . ' 설정은 콘텐츠 그룹이 있어야 그룹 적용할 수 있습니다.';
        }
    }

    $slug = (string) ($values['slug'] ?? '');
    if (!sr_content_slug_is_valid($slug)) {
        $errors[] = 'slug는 3-120자의 소문자 영문, 숫자, 하이픈만 사용할 수 있으며 예약어는 사용할 수 없습니다.';
    } elseif (sr_content_slug_exists($pdo, $slug, $pageId)) {
        $errors[] = '이미 사용 중인 slug입니다.';
    }

    if (!in_array((string) ($values['status'] ?? ''), sr_content_allowed_statuses(), true)) {
        $errors[] = '상태 값이 올바르지 않습니다.';
    }

    $layoutKey = (string) ($values['layout_key'] ?? '');
    if ($layoutKey !== '' && !isset(sr_public_layout_options($pdo)[$layoutKey])) {
        $errors[] = '콘텐츠 레이아웃 값이 올바르지 않습니다.';
    }

    if ((string) ($values['body_format'] ?? 'plain') !== 'plain') {
        $errors[] = '본문 형식이 올바르지 않습니다.';
    }

    if ((int) ($values['asset_access_enabled'] ?? 0) === 1) {
        $assetModules = sr_content_asset_module_keys_from_value($values['asset_module'] ?? '');
        if ($assetModules === []) {
            $errors[] = '유료 열람 자산이 올바르지 않습니다.';
        } elseif (!sr_content_asset_modules_available($pdo, $assetModules)) {
            $errors[] = '선택한 자산 모듈이 모두 활성 상태일 때만 유료 열람 자산으로 사용할 수 있습니다.';
        }

        $amount = (int) ($values['asset_access_amount'] ?? 0);
        if ($amount < 1 || $amount > 999999999) {
            $errors[] = '유료 열람 금액은 1부터 999999999 사이로 입력하세요.';
        }

        if (!isset(sr_content_asset_view_charge_policies()[(string) ($values['asset_charge_policy'] ?? '')])) {
            $errors[] = '유료 열람 과금 방식이 올바르지 않습니다.';
        }
    }

    if ((int) ($values['asset_action_enabled'] ?? 0) === 1) {
        $assetModules = sr_content_asset_module_keys_from_value($values['asset_action_module'] ?? '');
        if ($assetModules === []) {
            $errors[] = '완료 버튼 대상 자산이 올바르지 않습니다.';
        } elseif (!sr_content_asset_modules_available($pdo, $assetModules)) {
            $errors[] = '선택한 자산 모듈이 모두 활성 상태일 때만 완료 버튼 대상 자산으로 사용할 수 있습니다.';
        }

        $amount = (int) ($values['asset_action_amount'] ?? 0);
        if ($amount < 1 || $amount > 999999999) {
            $errors[] = '완료 버튼 금액은 1부터 999999999 사이로 입력하세요.';
        }

        if (!isset(sr_content_asset_action_directions()[(string) ($values['asset_action_direction'] ?? '')])) {
            $errors[] = '완료 버튼 지급/차감 방향이 올바르지 않습니다.';
        }

        if ((string) ($values['asset_action_label'] ?? '') === '') {
            $errors[] = '완료 버튼 문구를 입력하세요.';
        }
    }

    foreach (sr_content_public_display_setting_labels() as $settingKey => $settingLabel) {
        $displayId = (int) ($values[$settingKey] ?? 0);
        if (sr_content_normalize_setting_source((string) ($values['source_' . $settingKey] ?? 'content')) === 'group' && $pageGroupId < 1) {
            $errors[] = $settingLabel . ' 설정은 콘텐츠 그룹이 있어야 그룹 적용할 수 있습니다.';
        }

        if ($displayId < 0) {
            $errors[] = $settingLabel . ' 값이 올바르지 않습니다.';
            continue;
        }

        if (isset(sr_content_public_banner_setting_labels()[$settingKey]) && $displayId > 0 && !isset($publicBannerIds[$displayId])) {
            $errors[] = $settingLabel . '는 공용 배너 중에서 선택하세요.';
        }

        if (isset(sr_content_public_popup_layer_setting_labels()[$settingKey]) && $displayId > 0 && !isset($publicPopupLayerIds[$displayId])) {
            $errors[] = $settingLabel . '는 공용 팝업레이어 중에서 선택하세요.';
        }
    }

    return $errors;
}

function sr_content_save(PDO $pdo, array $values, int $accountId, int $pageId = 0): int
{
    $values = sr_content_normalize_asset_values($values);
    $now = sr_now();
    $pdo->beginTransaction();

    try {
        $existing = $pageId > 0 ? sr_content_by_id($pdo, $pageId) : null;
        $publishedAt = null;
        if ((string) $values['status'] === 'published') {
            $publishedAt = is_array($existing) && !empty($existing['published_at']) ? (string) $existing['published_at'] : $now;
        }

        if (is_array($existing)) {
            $stmt = $pdo->prepare(
                'UPDATE sr_content_items
                 SET content_group_id = :content_group_id,
                     slug = :slug, title = :title, summary = :summary, body_text = :body_text,
                     body_format = :body_format, status = :status,
                     layout_key = :layout_key,
                     asset_access_enabled = :asset_access_enabled,
                     asset_module = :asset_module,
                     asset_access_amount = :asset_access_amount,
                     asset_charge_policy = :asset_charge_policy,
                     asset_action_enabled = :asset_action_enabled,
                     asset_action_module = :asset_action_module,
                     asset_action_amount = :asset_action_amount,
                     asset_action_direction = :asset_action_direction,
                     asset_action_label = :asset_action_label,
                     banner_before_content_id = :banner_before_content_id,
                     banner_after_content_id = :banner_after_content_id,
                     popup_layer_id = :popup_layer_id,
                     seo_title = :seo_title,
                     seo_description = :seo_description, updated_by = :updated_by,
                     published_at = :published_at, updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'content_group_id' => (int) ($values['content_group_id'] ?? 0) > 0 ? (int) $values['content_group_id'] : null,
                'slug' => (string) $values['slug'],
                'title' => (string) $values['title'],
                'summary' => (string) $values['summary'],
                'body_text' => (string) $values['body_text'],
                'body_format' => 'plain',
                'status' => (string) $values['status'],
                'layout_key' => (string) ($values['layout_key'] ?? ''),
                'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
                'asset_module' => (string) ($values['asset_module'] ?? ''),
                'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
                'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
                'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
                'asset_action_module' => (string) ($values['asset_action_module'] ?? ''),
                'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
                'asset_action_direction' => (string) ($values['asset_action_direction'] ?? 'grant'),
                'asset_action_label' => (string) ($values['asset_action_label'] ?? '완료'),
                'banner_before_content_id' => (int) ($values['banner_before_content_id'] ?? 0),
                'banner_after_content_id' => (int) ($values['banner_after_content_id'] ?? 0),
                'popup_layer_id' => (int) ($values['popup_layer_id'] ?? 0),
                'seo_title' => (string) $values['seo_title'],
                'seo_description' => (string) $values['seo_description'],
                'updated_by' => $accountId,
                'published_at' => $publishedAt,
                'updated_at' => $now,
                'id' => $pageId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_content_items
                    (content_group_id, slug, title, summary, body_text, body_format, status, layout_key, asset_access_enabled, asset_module, asset_access_amount, asset_charge_policy, asset_action_enabled, asset_action_module, asset_action_amount, asset_action_direction, asset_action_label, banner_before_content_id, banner_after_content_id, popup_layer_id, seo_title, seo_description, created_by, updated_by, published_at, created_at, updated_at)
                 VALUES
                    (:content_group_id, :slug, :title, :summary, :body_text, :body_format, :status, :layout_key, :asset_access_enabled, :asset_module, :asset_access_amount, :asset_charge_policy, :asset_action_enabled, :asset_action_module, :asset_action_amount, :asset_action_direction, :asset_action_label, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :seo_title, :seo_description, :created_by, :updated_by, :published_at, :created_at, :updated_at)'
            );
            $stmt->execute([
                'content_group_id' => (int) ($values['content_group_id'] ?? 0) > 0 ? (int) $values['content_group_id'] : null,
                'slug' => (string) $values['slug'],
                'title' => (string) $values['title'],
                'summary' => (string) $values['summary'],
                'body_text' => (string) $values['body_text'],
                'body_format' => 'plain',
                'status' => (string) $values['status'],
                'layout_key' => (string) ($values['layout_key'] ?? ''),
                'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
                'asset_module' => (string) ($values['asset_module'] ?? ''),
                'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
                'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
                'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
                'asset_action_module' => (string) ($values['asset_action_module'] ?? ''),
                'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
                'asset_action_direction' => (string) ($values['asset_action_direction'] ?? 'grant'),
                'asset_action_label' => (string) ($values['asset_action_label'] ?? '완료'),
                'banner_before_content_id' => (int) ($values['banner_before_content_id'] ?? 0),
                'banner_after_content_id' => (int) ($values['banner_after_content_id'] ?? 0),
                'popup_layer_id' => (int) ($values['popup_layer_id'] ?? 0),
                'seo_title' => (string) $values['seo_title'],
                'seo_description' => (string) $values['seo_description'],
                'created_by' => $accountId,
                'updated_by' => $accountId,
                'published_at' => $publishedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $pageId = (int) $pdo->lastInsertId();
        }

        foreach (sr_content_public_display_setting_labels() as $settingKey => $settingLabel) {
            sr_content_apply_setting_scope($pdo, $pageId, (int) ($values['content_group_id'] ?? 0), (string) $settingKey, (string) ($values['source_' . $settingKey] ?? 'content'), $values, $accountId, $now);
        }
        foreach (sr_content_group_basic_setting_keys() as $settingKey) {
            sr_content_apply_setting_scope($pdo, $pageId, (int) ($values['content_group_id'] ?? 0), (string) $settingKey, (string) ($values['source_' . $settingKey] ?? 'content'), $values, $accountId, $now);
        }
        foreach (sr_content_group_asset_access_setting_keys() as $settingKey) {
            sr_content_apply_setting_scope($pdo, $pageId, (int) ($values['content_group_id'] ?? 0), (string) $settingKey, (string) ($values['source_' . $settingKey] ?? 'content'), $values, $accountId, $now);
        }
        foreach (sr_content_group_asset_action_setting_keys() as $settingKey) {
            sr_content_apply_setting_scope($pdo, $pageId, (int) ($values['content_group_id'] ?? 0), (string) $settingKey, (string) ($values['source_' . $settingKey] ?? 'content'), $values, $accountId, $now);
        }
        foreach (sr_content_group_file_asset_setting_keys() as $settingKey) {
            sr_content_set_setting_source($pdo, $pageId, (string) $settingKey, (string) ($values['source_' . $settingKey] ?? 'content'));
        }

        sr_content_record_revision($pdo, $pageId, $values, $accountId, $now);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $pageId;
}

function sr_content_record_revision(PDO $pdo, int $pageId, array $values, int $accountId, string $now): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_revisions
            (content_id, content_group_id, title, summary, body_text, body_format, status, layout_key, asset_access_enabled, asset_module, asset_access_amount, asset_charge_policy, asset_action_enabled, asset_action_module, asset_action_amount, asset_action_direction, asset_action_label, banner_before_content_id, banner_after_content_id, popup_layer_id, created_by, created_at)
         VALUES
            (:content_id, :content_group_id, :title, :summary, :body_text, :body_format, :status, :layout_key, :asset_access_enabled, :asset_module, :asset_access_amount, :asset_charge_policy, :asset_action_enabled, :asset_action_module, :asset_action_amount, :asset_action_direction, :asset_action_label, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :created_by, :created_at)'
    );
    $stmt->execute([
        'content_id' => $pageId,
        'content_group_id' => (int) ($values['content_group_id'] ?? 0) > 0 ? (int) $values['content_group_id'] : null,
        'title' => (string) $values['title'],
        'summary' => (string) $values['summary'],
        'body_text' => (string) $values['body_text'],
        'body_format' => 'plain',
        'status' => (string) $values['status'],
        'layout_key' => (string) ($values['layout_key'] ?? ''),
        'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
        'asset_module' => (string) ($values['asset_module'] ?? ''),
        'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
        'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
        'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
        'asset_action_module' => (string) ($values['asset_action_module'] ?? ''),
        'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
        'asset_action_direction' => (string) ($values['asset_action_direction'] ?? 'grant'),
        'asset_action_label' => (string) ($values['asset_action_label'] ?? '완료'),
        'banner_before_content_id' => (int) ($values['banner_before_content_id'] ?? 0),
        'banner_after_content_id' => (int) ($values['banner_after_content_id'] ?? 0),
        'popup_layer_id' => (int) ($values['popup_layer_id'] ?? 0),
        'created_by' => $accountId,
        'created_at' => $now,
    ]);
}

function sr_content_file_extension_mime_map(): array
{
    return [
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'hwp' => ['application/x-hwp', 'application/haansofthwp'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];
}

function sr_content_file_allowed_extensions(): array
{
    return array_keys(sr_content_file_extension_mime_map());
}

function sr_content_file_mime_types_for_extensions(array $extensions): array
{
    $map = sr_content_file_extension_mime_map();
    $mimeTypes = [];
    foreach (sr_upload_normalize_extensions($extensions) as $extension) {
        foreach ($map[$extension] ?? [] as $mimeType) {
            $mimeTypes[$mimeType] = true;
        }
    }

    return array_keys($mimeTypes);
}

function sr_content_file_upload_max_bytes(): int
{
    return 20971520;
}

function sr_content_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format(max(0, $bytes)) . ' bytes';
}

function sr_content_file_upload_was_provided(mixed $file): bool
{
    return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function sr_content_file_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), sr_content_file_mime_types_for_extensions(sr_content_file_allowed_extensions()), true);
}

function sr_content_file_storage_driver(array $file): string
{
    $driver = strtolower((string) ($file['storage_driver'] ?? 'local'));
    return in_array($driver, ['local', 's3'], true) ? $driver : 'local';
}

function sr_content_file_storage_key(array $file): string
{
    $key = (string) ($file['storage_key'] ?? '');
    if ($key !== '' && sr_storage_key_is_safe($key)) {
        return $key;
    }

    $storagePath = (string) ($file['storage_path'] ?? '');
    if (str_starts_with($storagePath, SR_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)) {
        $storagePath = substr($storagePath, strlen(SR_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR));
    } elseif (str_starts_with($storagePath, 'storage/')) {
        $storagePath = substr($storagePath, strlen('storage/'));
    }

    $storagePath = str_replace('\\', '/', ltrim($storagePath, '/'));
    return sr_storage_key_is_safe($storagePath) ? $storagePath : '';
}

function sr_content_file_path(array $file): ?string
{
    $driver = sr_content_file_storage_driver($file);
    $key = sr_content_file_storage_key($file);
    if ($driver === 'local' && $key !== '') {
        return sr_storage_local_path($key);
    }

    return null;
}

function sr_content_files_for_content(PDO $pdo, int $pageId): array
{
    if ($pageId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_content_files
         WHERE content_id = :content_id
           AND status = 'active'
         ORDER BY id ASC
         LIMIT 50"
    );
    $stmt->execute(['content_id' => $pageId]);

    return $stmt->fetchAll();
}

function sr_content_file_by_id(PDO $pdo, int $fileId): ?array
{
    if ($fileId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT f.*, p.slug, p.title AS content_title, p.status AS content_status
         FROM sr_content_files f
         INNER JOIN sr_content_items p ON p.id = f.content_id
         WHERE f.id = :id
           AND f.status = 'active'
         LIMIT 1"
    );
    $stmt->execute(['id' => $fileId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_published_file_by_id(PDO $pdo, int $fileId): ?array
{
    $file = sr_content_file_by_id($pdo, $fileId);
    if (!is_array($file) || (string) ($file['content_status'] ?? '') !== 'published') {
        return null;
    }

    return $file;
}

function sr_content_normalize_file_asset_values(array $values, bool $coerceInvalid = true): array
{
    $assetModule = sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($values['asset_module'] ?? ''));

    $chargePolicy = (string) ($values['asset_charge_policy'] ?? 'once');
    if ($coerceInvalid && !isset(sr_content_asset_download_charge_policies()[$chargePolicy])) {
        $chargePolicy = 'once';
    }

    $values['asset_download_enabled'] = (int) ($values['asset_download_enabled'] ?? 0) === 1 ? 1 : 0;
    $values['asset_module'] = $assetModule;
    $values['asset_download_amount'] = max(0, (int) ($values['asset_download_amount'] ?? 0));
    $values['asset_charge_policy'] = $chargePolicy;

    return $values;
}

function sr_content_file_asset_validation_errors(PDO $pdo, array $values, string $labelPrefix = '파일 다운로드'): array
{
    $errors = [];
    if ((int) ($values['asset_download_enabled'] ?? 0) !== 1) {
        return [];
    }

    $assetModules = sr_content_asset_module_keys_from_value($values['asset_module'] ?? '');
    if ($assetModules === []) {
        $errors[] = $labelPrefix . ' 자산이 올바르지 않습니다.';
    } elseif (!sr_content_asset_modules_available($pdo, $assetModules)) {
        $errors[] = '선택한 자산 모듈이 모두 활성 상태일 때만 ' . $labelPrefix . ' 자산으로 사용할 수 있습니다.';
    }

    $amount = (int) ($values['asset_download_amount'] ?? 0);
    if ($amount < 1 || $amount > 999999999) {
        $errors[] = $labelPrefix . ' 금액은 1부터 999999999 사이로 입력하세요.';
    }

    if (!isset(sr_content_asset_download_charge_policies()[(string) ($values['asset_charge_policy'] ?? '')])) {
        $errors[] = $labelPrefix . ' 과금 방식이 올바르지 않습니다.';
    }

    return $errors;
}

function sr_content_validate_file_request(PDO $pdo, int $pageId, array $pageValues = []): array
{
    $errors = [];
    $existingIds = $_POST['content_file_ids'] ?? [];
    if (is_array($existingIds)) {
        foreach ($existingIds as $rawFileId) {
            $fileId = (int) $rawFileId;
            if ($fileId < 1) {
                continue;
            }

            $file = sr_content_file_by_id($pdo, $fileId);
            if (!is_array($file) || (int) $file['content_id'] !== $pageId) {
                $errors[] = '수정할 콘텐츠 파일을 확인할 수 없습니다.';
                continue;
            }

            $values = sr_content_file_values_from_post($fileId);
            $errors = array_merge($errors, sr_content_file_asset_validation_errors($pdo, $values));
        }
    }

    $upload = $_FILES['content_file_upload'] ?? null;
    if (sr_content_file_upload_was_provided($upload)) {
        try {
            sr_upload_validate_file($upload, [
                'max_bytes' => sr_content_file_upload_max_bytes(),
                'allowed_extensions' => sr_content_file_allowed_extensions(),
                'allowed_mime_types' => sr_content_file_mime_types_for_extensions(sr_content_file_allowed_extensions()),
            ]);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        $values = sr_content_new_file_values_from_post($pdo, $pageValues);
        $errors = array_merge($errors, sr_content_file_asset_validation_errors($pdo, $values, '새 파일 다운로드'));
    }

    return $errors;
}

function sr_content_file_values_from_post(int $fileId): array
{
    $titleValues = is_array($_POST['content_file_title'] ?? null) ? $_POST['content_file_title'] : [];
    $enabledValues = is_array($_POST['content_file_asset_download_enabled'] ?? null) ? $_POST['content_file_asset_download_enabled'] : [];
    $moduleValues = is_array($_POST['content_file_asset_module'] ?? null) ? $_POST['content_file_asset_module'] : [];
    $amountValues = is_array($_POST['content_file_asset_download_amount'] ?? null) ? $_POST['content_file_asset_download_amount'] : [];
    $policyValues = is_array($_POST['content_file_asset_charge_policy'] ?? null) ? $_POST['content_file_asset_charge_policy'] : [];

    return sr_content_normalize_file_asset_values([
        'title' => sr_content_clean_single_line((string) ($titleValues[$fileId] ?? ''), 160),
        'asset_download_enabled' => (string) ($enabledValues[$fileId] ?? '') === '1' ? 1 : 0,
        'asset_module' => sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($moduleValues[$fileId] ?? '')),
        'asset_download_amount' => (int) ($amountValues[$fileId] ?? 0),
        'asset_charge_policy' => sr_content_clean_slug((string) ($policyValues[$fileId] ?? '')),
    ], false);
}

function sr_content_file_asset_values_from_group(PDO $pdo, int $groupId): array
{
    return sr_content_normalize_file_asset_values([
        'asset_download_enabled' => (int) (sr_content_group_setting_value($pdo, $groupId, 'file_asset_download_enabled') ?? 0),
        'asset_module' => (string) (sr_content_group_setting_value($pdo, $groupId, 'file_asset_module') ?? ''),
        'asset_download_amount' => (int) (sr_content_group_setting_value($pdo, $groupId, 'file_asset_download_amount') ?? 0),
        'asset_charge_policy' => (string) (sr_content_group_setting_value($pdo, $groupId, 'file_asset_charge_policy') ?? 'once'),
    ]);
}

function sr_content_new_file_values_from_post(?PDO $pdo = null, array $pageValues = []): array
{
    $values = sr_content_normalize_file_asset_values([
        'title' => sr_content_clean_single_line(sr_post_string('new_content_file_title', 160), 160),
        'asset_download_enabled' => sr_post_string('new_content_file_asset_download_enabled', 1) === '1' ? 1 : 0,
        'asset_module' => sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($_POST['new_content_file_asset_module'] ?? '')),
        'asset_download_amount' => (int) sr_post_string('new_content_file_asset_download_amount', 20),
        'asset_charge_policy' => sr_content_clean_slug(sr_post_string('new_content_file_asset_charge_policy', 20)),
    ], false);

    return $values;
}

function sr_content_save_files_from_request(PDO $pdo, int $pageId, int $accountId, array $pageValues = []): void
{
    if ($pageId < 1) {
        return;
    }

    $deleteValues = is_array($_POST['content_file_delete'] ?? null) ? $_POST['content_file_delete'] : [];
    $existingIds = is_array($_POST['content_file_ids'] ?? null) ? $_POST['content_file_ids'] : [];
    foreach ($existingIds as $rawFileId) {
        $fileId = (int) $rawFileId;
        if ($fileId < 1) {
            continue;
        }

        $file = sr_content_file_by_id($pdo, $fileId);
        if (!is_array($file) || (int) $file['content_id'] !== $pageId) {
            continue;
        }

        if ((string) ($deleteValues[$fileId] ?? '') === '1') {
            sr_content_hide_file($pdo, $fileId);
            continue;
        }

        sr_content_update_file($pdo, $fileId, sr_content_file_values_from_post($fileId));
    }

    $upload = $_FILES['content_file_upload'] ?? null;
    if (sr_content_file_upload_was_provided($upload)) {
        sr_content_upload_file($pdo, $pageId, $accountId, $upload, sr_content_new_file_values_from_post($pdo, $pageValues));
    }
}

function sr_content_update_file(PDO $pdo, int $fileId, array $values): void
{
    $values = sr_content_normalize_file_asset_values($values);
    $title = (string) ($values['title'] ?? '');
    if ($title === '') {
        $title = '첨부 파일';
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_content_files
         SET title = :title,
             asset_download_enabled = :asset_download_enabled,
             asset_module = :asset_module,
             asset_download_amount = :asset_download_amount,
             asset_charge_policy = :asset_charge_policy,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'title' => $title,
        'asset_download_enabled' => (int) $values['asset_download_enabled'],
        'asset_module' => (string) $values['asset_module'],
        'asset_download_amount' => (int) $values['asset_download_amount'],
        'asset_charge_policy' => (string) $values['asset_charge_policy'],
        'updated_at' => sr_now(),
        'id' => $fileId,
    ]);
}

function sr_content_hide_file(PDO $pdo, int $fileId): void
{
    $stmt = $pdo->prepare(
        "UPDATE sr_content_files
         SET status = 'hidden', updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'updated_at' => sr_now(),
        'id' => $fileId,
    ]);
}

function sr_content_upload_file(PDO $pdo, int $pageId, int $accountId, array $file, array $values): int
{
    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_content_file_upload_max_bytes(),
        'allowed_extensions' => sr_content_file_allowed_extensions(),
        'allowed_mime_types' => sr_content_file_mime_types_for_extensions(sr_content_file_allowed_extensions()),
    ]);
    $values = sr_content_normalize_file_asset_values($values);

    $storedName = sr_upload_random_filename((string) $validated['extension']);
    $storedMimeType = sr_upload_detect_mime((string) $validated['tmp_name']);
    $sizeBytes = filesize((string) $validated['tmp_name']);
    if (!sr_content_file_mime_is_allowed($storedMimeType) || !is_int($sizeBytes)) {
        throw new RuntimeException('저장된 콘텐츠 파일 metadata를 확인할 수 없습니다.');
    }

    $storageKey = 'content/files/' . date('Y/m') . '/' . $storedName;
    $stored = sr_storage_put_file((string) $validated['tmp_name'], $storageKey, [
        'content_type' => $storedMimeType,
    ]);

    try {
        $title = (string) ($values['title'] ?? '');
        if ($title === '') {
            $title = (string) $validated['original_name'];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO sr_content_files
                (content_id, title, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, status, asset_download_enabled, asset_module, asset_download_amount, asset_charge_policy, created_by, created_at, updated_at)
             VALUES
                (:content_id, :title, :original_name, :stored_name, :storage_path, :storage_driver, :storage_key, :mime_type, :size_bytes, :checksum_sha256, 'active', :asset_download_enabled, :asset_module, :asset_download_amount, :asset_charge_policy, :created_by, :created_at, :updated_at)"
        );
        $now = sr_now();
        $stmt->execute([
            'content_id' => $pageId,
            'title' => $title,
            'original_name' => (string) $validated['original_name'],
            'stored_name' => $storedName,
            'storage_path' => (string) ($stored['path'] ?? ''),
            'storage_driver' => (string) $stored['driver'],
            'storage_key' => $storageKey,
            'mime_type' => $storedMimeType,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => (string) $validated['checksum'],
            'asset_download_enabled' => (int) $values['asset_download_enabled'],
            'asset_module' => (string) $values['asset_module'],
            'asset_download_amount' => (int) $values['asset_download_amount'],
            'asset_charge_policy' => (string) $values['asset_charge_policy'],
            'created_by' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $pdo->lastInsertId();
    } catch (Throwable $exception) {
        sr_storage_delete((string) $stored['driver'], $storageKey);
        throw $exception;
    }
}

function sr_content_asset_access_required(array $page): bool
{
    return (int) ($page['asset_access_enabled'] ?? 0) === 1
        && (int) ($page['asset_access_amount'] ?? 0) > 0;
}

function sr_content_asset_access_reference_id(int $pageId): string
{
    return (string) $pageId;
}

function sr_content_asset_access_dedupe_key(string $assetModule, int $accountId, int $subjectId, string $accessKind = 'view'): string
{
    return 'content.' . $accessKind . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId;
}

function sr_content_asset_access_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_content_asset_access_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_has_paid_access(PDO $pdo, string $assetModule, int $accountId, int $subjectId, string $accessKind = 'view'): bool
{
    $dedupeKey = sr_content_asset_access_dedupe_key($assetModule, $accountId, $subjectId, $accessKind);
    $log = sr_content_asset_access_log($pdo, $dedupeKey);

    return is_array($log) && (int) ($log['transaction_id'] ?? 0) > 0;
}

function sr_content_has_paid_access_for_modules(PDO $pdo, array $assetModules, int $accountId, int $subjectId, string $accessKind = 'view'): bool
{
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        if (sr_content_has_paid_access($pdo, $assetModule, $accountId, $subjectId, $accessKind)) {
            return true;
        }
    }

    return false;
}

function sr_content_asset_balance(PDO $pdo, string $assetModule, int $accountId): int
{
    if (!sr_content_asset_module_is_available($pdo, $assetModule)) {
        return 0;
    }

    $option = sr_content_asset_modules()[$assetModule];
    $balanceFunction = (string) $option['balance_function'];

    return (int) $balanceFunction($pdo, $accountId);
}

function sr_content_create_asset_transaction(PDO $pdo, string $assetModule, array $data): int
{
    if (!sr_content_asset_module_is_available($pdo, $assetModule)) {
        throw new RuntimeException('Page asset module is not available.');
    }

    $option = sr_content_asset_modules()[$assetModule];
    $transactionFunction = (string) $option['transaction_function'];

    return (int) $transactionFunction($pdo, $data);
}

function sr_content_allocate_asset_use(PDO $pdo, array $assetModules, int $accountId, int $amount): array
{
    $remaining = max(0, $amount);
    $allocations = [];
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        if ($remaining <= 0) {
            break;
        }

        $balance = sr_content_asset_balance($pdo, $assetModule, $accountId);
        if ($balance <= 0) {
            continue;
        }

        $useAmount = min($balance, $remaining);
        if ($useAmount > 0) {
            $allocations[] = [
                'asset_module' => $assetModule,
                'amount' => $useAmount,
            ];
            $remaining -= $useAmount;
        }
    }

    return $remaining === 0 ? $allocations : [];
}

function sr_content_insert_asset_access_placeholder(PDO $pdo, int $pageId, int $accountId, string $assetModule, int $amount, string $chargePolicy, string $dedupeKey, string $referenceType = 'content.view', ?string $referenceId = null, string $accessKind = 'view'): bool
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_content_asset_access_logs
            (content_id, account_id, asset_module, transaction_id, reference_type, reference_id, access_kind, charge_policy, amount, dedupe_key, created_at)
         VALUES
            (:content_id, :account_id, :asset_module, 0, :reference_type, :reference_id, :access_kind, :charge_policy, :amount, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'content_id' => $pageId,
        'account_id' => $accountId,
        'asset_module' => $assetModule,
        'reference_type' => $referenceType,
        'reference_id' => $referenceId ?? sr_content_asset_access_reference_id($pageId),
        'access_kind' => $accessKind,
        'charge_policy' => $chargePolicy,
        'amount' => $amount,
        'dedupe_key' => $dedupeKey,
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_content_update_asset_access_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_content_asset_access_logs
         SET transaction_id = :transaction_id
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_content_delete_asset_access_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_content_asset_access_logs
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
}

function sr_content_charge_view_access(PDO $pdo, array $page, int $accountId): array
{
    $pageId = (int) ($page['id'] ?? 0);
    $assetModules = sr_content_asset_module_keys_from_value($page['asset_module'] ?? '');
    $assetModuleValue = sr_content_asset_module_value_from_keys($assetModules);
    $chargePolicy = (string) ($page['asset_charge_policy'] ?? 'once');
    $amount = (int) ($page['asset_access_amount'] ?? 0);

    if ($pageId <= 0 || $accountId <= 0 || !sr_content_asset_access_required($page)) {
        return ['allowed' => true, 'charged' => false, 'message' => ''];
    }

    if ($assetModules === [] || !isset(sr_content_asset_view_charge_policies()[$chargePolicy])) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '콘텐츠 유료 열람 설정이 올바르지 않아 열람할 수 없습니다.',
        ];
    }

    if (!sr_content_asset_modules_available($pdo, $assetModules)) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '선택한 자산 모듈을 모두 사용할 수 없어 콘텐츠를 열람할 수 없습니다.',
        ];
    }

    if ($chargePolicy === 'once' && sr_content_has_paid_access_for_modules($pdo, $assetModules, $accountId, $pageId)) {
        return [
            'allowed' => true,
            'charged' => false,
            'already_paid' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '',
        ];
    }

    $allocations = sr_content_allocate_asset_use($pdo, $assetModules, $accountId, $amount);
    if ($allocations === []) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '선택한 자산의 합산 잔액이 부족해 콘텐츠를 열람할 수 없습니다.',
        ];
    }

    $dedupeKey = '';
    try {
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) $allocation['amount'];
            $assetOption = sr_content_asset_modules()[$assetModule];
            $dedupeKey = $chargePolicy === 'once'
                ? sr_content_asset_access_dedupe_key($assetModule, $accountId, $pageId)
                : 'content.view:' . $assetModule . ':' . (string) $accountId . ':' . (string) $pageId . ':' . bin2hex(random_bytes(8));
            $inserted = sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, $allocatedAmount, $chargePolicy, $dedupeKey);
            if (!$inserted) {
                continue;
            }

            $transactionId = sr_content_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => -$allocatedAmount,
                'transaction_type' => (string) ($assetOption['use_type'] ?? 'use'),
                'reason' => '콘텐츠 열람',
                'reference_type' => 'content.view',
                'reference_id' => sr_content_asset_access_reference_id($pageId),
                'created_by_account_id' => null,
            ]);
            sr_content_update_asset_access_transaction($pdo, $dedupeKey, $transactionId);
        }
    } catch (Throwable $exception) {
        if ($dedupeKey !== '') {
            sr_content_delete_asset_access_placeholder($pdo, $dedupeKey);
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_asset_access_charge_failed');
        }

        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '회원 자산 차감에 실패해 콘텐츠를 열람할 수 없습니다.',
        ];
    }

    return [
        'allowed' => true,
        'charged' => true,
        'asset_module' => $assetModuleValue,
        'asset_label' => sr_content_asset_module_labels($assetModuleValue),
        'amount' => $amount,
        'message' => '',
    ];
}

function sr_content_file_download_required(array $file): bool
{
    return (int) ($file['asset_download_enabled'] ?? 0) === 1
        && (int) ($file['asset_download_amount'] ?? 0) > 0;
}

function sr_content_charge_file_download(PDO $pdo, array $file, int $accountId): array
{
    $pageId = (int) ($file['content_id'] ?? 0);
    $fileId = (int) ($file['id'] ?? 0);
    $assetModules = sr_content_asset_module_keys_from_value($file['asset_module'] ?? '');
    $assetModuleValue = sr_content_asset_module_value_from_keys($assetModules);
    $chargePolicy = (string) ($file['asset_charge_policy'] ?? 'once');
    $amount = (int) ($file['asset_download_amount'] ?? 0);

    if ($pageId <= 0 || $fileId <= 0 || $accountId <= 0 || !sr_content_file_download_required($file)) {
        return ['allowed' => true, 'charged' => false, 'message' => ''];
    }

    if ($assetModules === [] || !isset(sr_content_asset_download_charge_policies()[$chargePolicy])) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '콘텐츠 파일 다운로드 설정이 올바르지 않아 다운로드할 수 없습니다.',
        ];
    }

    if (!sr_content_asset_modules_available($pdo, $assetModules)) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '선택한 자산 모듈을 모두 사용할 수 없어 파일을 다운로드할 수 없습니다.',
        ];
    }

    if ($chargePolicy === 'once' && sr_content_has_paid_access_for_modules($pdo, $assetModules, $accountId, $fileId, 'download')) {
        return [
            'allowed' => true,
            'charged' => false,
            'already_paid' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '',
        ];
    }

    $allocations = sr_content_allocate_asset_use($pdo, $assetModules, $accountId, $amount);
    if ($allocations === []) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '선택한 자산의 합산 잔액이 부족해 파일을 다운로드할 수 없습니다.',
        ];
    }

    $dedupeKey = '';
    try {
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) $allocation['amount'];
            $assetOption = sr_content_asset_modules()[$assetModule];
            $dedupeKey = $chargePolicy === 'once'
                ? sr_content_asset_access_dedupe_key($assetModule, $accountId, $fileId, 'download')
                : 'content.download:' . $assetModule . ':' . (string) $accountId . ':' . (string) $fileId . ':' . bin2hex(random_bytes(8));
            $inserted = sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, $allocatedAmount, $chargePolicy, $dedupeKey, 'content.download', (string) $fileId, 'download');
            if (!$inserted) {
                continue;
            }

            $transactionId = sr_content_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => -$allocatedAmount,
                'transaction_type' => (string) ($assetOption['use_type'] ?? 'use'),
                'reason' => '콘텐츠 파일 다운로드',
                'reference_type' => 'content.download',
                'reference_id' => (string) $fileId,
                'created_by_account_id' => null,
            ]);
            sr_content_update_asset_access_transaction($pdo, $dedupeKey, $transactionId);
        }
    } catch (Throwable $exception) {
        if ($dedupeKey !== '') {
            sr_content_delete_asset_access_placeholder($pdo, $dedupeKey);
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_file_download_charge_failed');
        }

        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '회원 자산 차감에 실패해 파일을 다운로드할 수 없습니다.',
        ];
    }

    return [
        'allowed' => true,
        'charged' => true,
        'asset_module' => $assetModuleValue,
        'asset_label' => sr_content_asset_module_labels($assetModuleValue),
        'amount' => $amount,
        'message' => '',
    ];
}

function sr_content_asset_action_required(array $page): bool
{
    return (int) ($page['asset_action_enabled'] ?? 0) === 1
        && (int) ($page['asset_action_amount'] ?? 0) > 0;
}

function sr_content_asset_action_dedupe_key(string $assetModule, int $accountId, int $pageId): string
{
    return 'content.action:' . $assetModule . ':' . (string) $accountId . ':' . (string) $pageId . ':complete';
}

function sr_content_asset_action_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_content_asset_action_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_has_completed_asset_action(PDO $pdo, string $assetModule, int $accountId, int $pageId): bool
{
    $log = sr_content_asset_action_log($pdo, sr_content_asset_action_dedupe_key($assetModule, $accountId, $pageId));

    return is_array($log) && (int) ($log['transaction_id'] ?? 0) > 0;
}

function sr_content_has_completed_asset_action_for_modules(PDO $pdo, array $assetModules, int $accountId, int $pageId): bool
{
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        if (sr_content_has_completed_asset_action($pdo, $assetModule, $accountId, $pageId)) {
            return true;
        }
    }

    return false;
}

function sr_content_insert_asset_action_placeholder(PDO $pdo, int $pageId, int $accountId, string $assetModule, string $direction, int $amount, string $dedupeKey): bool
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_content_asset_action_logs
            (content_id, account_id, asset_module, transaction_id, reference_type, reference_id, action_key, direction, amount, dedupe_key, created_at)
         VALUES
            (:content_id, :account_id, :asset_module, 0, :reference_type, :reference_id, :action_key, :direction, :amount, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'content_id' => $pageId,
        'account_id' => $accountId,
        'asset_module' => $assetModule,
        'reference_type' => 'content.action',
        'reference_id' => (string) $pageId,
        'action_key' => 'complete',
        'direction' => $direction,
        'amount' => $amount,
        'dedupe_key' => $dedupeKey,
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_content_update_asset_action_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_content_asset_action_logs
         SET transaction_id = :transaction_id
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_content_delete_asset_action_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_content_asset_action_logs
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
}

function sr_content_run_asset_action(PDO $pdo, array $page, int $accountId): array
{
    $pageId = (int) ($page['id'] ?? 0);
    $assetModules = sr_content_asset_module_keys_from_value($page['asset_action_module'] ?? '');
    $assetModuleValue = sr_content_asset_module_value_from_keys($assetModules);
    $direction = (string) ($page['asset_action_direction'] ?? 'grant');
    $amount = (int) ($page['asset_action_amount'] ?? 0);

    if ($pageId <= 0 || $accountId <= 0 || !sr_content_asset_action_required($page)) {
        return ['allowed' => false, 'completed' => false, 'message' => '콘텐츠 완료 버튼을 사용할 수 없습니다.'];
    }

    if ($assetModules === [] || !isset(sr_content_asset_action_directions()[$direction])) {
        return ['allowed' => false, 'completed' => false, 'message' => '콘텐츠 완료 버튼 설정이 올바르지 않습니다.'];
    }

    if (!sr_content_asset_modules_available($pdo, $assetModules)) {
        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '선택한 자산 모듈을 모두 사용할 수 없어 완료 처리할 수 없습니다.',
        ];
    }

    if (sr_content_has_completed_asset_action_for_modules($pdo, $assetModules, $accountId, $pageId)) {
        return [
            'allowed' => true,
            'completed' => false,
            'already_completed' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '이미 완료 처리되었습니다.',
        ];
    }

    $allocations = $direction === 'use'
        ? sr_content_allocate_asset_use($pdo, $assetModules, $accountId, $amount)
        : [['asset_module' => $assetModules[0], 'amount' => $amount]];
    if ($direction === 'use' && $allocations === []) {
        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '선택한 자산의 합산 잔액이 부족해 완료 처리할 수 없습니다.',
        ];
    }

    $dedupeKey = '';
    try {
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) $allocation['amount'];
            $dedupeKey = sr_content_asset_action_dedupe_key($assetModule, $accountId, $pageId);
            $inserted = sr_content_insert_asset_action_placeholder($pdo, $pageId, $accountId, $assetModule, $direction, $allocatedAmount, $dedupeKey);
            if (!$inserted) {
                continue;
            }

            $assetOption = sr_content_asset_modules()[$assetModule];
            $signedAmount = $direction === 'grant' ? $allocatedAmount : -$allocatedAmount;
            $transactionType = $direction === 'grant'
                ? (string) ($assetOption['credit_type'] ?? 'grant')
                : (string) ($assetOption['use_type'] ?? 'use');
            $transactionId = sr_content_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => $signedAmount,
                'transaction_type' => $transactionType,
                'reason' => '콘텐츠 완료 버튼 처리',
                'reference_type' => 'content.action',
                'reference_id' => (string) $pageId,
                'created_by_account_id' => null,
            ]);
            sr_content_update_asset_action_transaction($pdo, $dedupeKey, $transactionId);
        }
    } catch (Throwable $exception) {
        if ($dedupeKey !== '') {
            sr_content_delete_asset_action_placeholder($pdo, $dedupeKey);
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_asset_action_failed');
        }

        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '회원 자산 처리에 실패했습니다.',
        ];
    }

    return [
        'allowed' => true,
        'completed' => true,
        'asset_module' => $assetModuleValue,
        'asset_label' => sr_content_asset_module_labels($assetModuleValue),
        'amount' => $amount,
        'direction' => $direction,
        'message' => '',
    ];
}

function sr_content_hide(PDO $pdo, int $pageId, int $accountId): bool
{
    $page = sr_content_by_id($pdo, $pageId);
    if (!is_array($page)) {
        return false;
    }

    $now = sr_now();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE sr_content_items
             SET status = 'hidden', updated_by = :updated_by, updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute([
            'updated_by' => $accountId,
            'updated_at' => $now,
            'id' => $pageId,
        ]);

        $page['status'] = 'hidden';
        sr_content_record_revision($pdo, $pageId, $page, $accountId, $now);
        $pdo->commit();

        return $stmt->rowCount() > 0;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}
