<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers/assets.php';
require_once SR_ROOT . '/modules/content/helpers/body-files.php';
require_once SR_ROOT . '/modules/content/helpers/files.php';
require_once SR_ROOT . '/modules/content/helpers/series.php';
require_once SR_ROOT . '/modules/content/helpers/comments.php';
require_once SR_ROOT . '/modules/member/helpers.php';

function sr_content_allowed_statuses(): array
{
    return ['draft', 'scheduled', 'published', 'hidden'];
}

function sr_content_datetime_local_value(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
}

function sr_content_scheduled_publish_at_from_post(): string
{
    $value = trim(sr_post_string('scheduled_publish_at', 30));
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime(str_replace('T', ' ', $value));
    return $timestamp === false ? '' : date('Y-m-d H:i:s', $timestamp);
}

function sr_content_publish_due_scheduled(PDO $pdo): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT id, slug, published_at
         FROM sr_content_items
         WHERE status = 'scheduled'
           AND published_at IS NOT NULL
           AND published_at <= :now_value
         ORDER BY published_at ASC, id ASC"
    );
    $stmt->execute(['now_value' => $now]);

    $publishedCount = 0;
    $updateStmt = $pdo->prepare(
        "UPDATE sr_content_items
         SET status = 'published',
             updated_at = :updated_at
         WHERE id = :id
           AND status = 'scheduled'"
    );
    foreach ($stmt->fetchAll() as $row) {
        $contentId = (int) ($row['id'] ?? 0);
        if ($contentId < 1) {
            continue;
        }

        $updateStmt->execute([
            'updated_at' => $now,
            'id' => $contentId,
        ]);
        if ($updateStmt->rowCount() < 1) {
            continue;
        }

        $publishedCount += 1;
        sr_audit_log($pdo, [
            'actor_type' => 'system',
            'event_type' => 'content.scheduled_published',
            'target_type' => 'content',
            'target_id' => (string) $contentId,
            'result' => 'success',
            'message' => 'Scheduled content published.',
            'metadata' => [
                'slug' => (string) ($row['slug'] ?? ''),
                'scheduled_publish_at' => (string) ($row['published_at'] ?? ''),
                'published_at' => $now,
            ],
        ]);
    }

    return $publishedCount;
}

function sr_content_group_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_content_default_settings(): array
{
    return [
        'editor' => 'textarea',
        'once_history_policy' => 'all_access',
        'layout_key' => 'content.basic',
        'layout_primary_menu_key' => 'header',
        'layout_secondary_menu_key' => '',
        'layout_tertiary_menu_key' => '',
    ];
}

function sr_content_clean_layout_menu_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function sr_content_settings(PDO $pdo): array
{
    $settings = array_merge(sr_content_default_settings(), sr_module_settings($pdo, 'content'));
    $settings['editor'] = sr_editor_normalize_key((string) ($settings['editor'] ?? 'textarea'));
    $settings['once_history_policy'] = sr_content_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));
    $settings['layout_key'] = sr_public_layout_normalize_key((string) ($settings['layout_key'] ?? 'content.basic'));
    if (!isset(sr_public_layout_options($pdo)[$settings['layout_key']])) {
        $settings['layout_key'] = sr_public_layout_key(null, $pdo);
    }
    $settings['layout_primary_menu_key'] = sr_content_clean_layout_menu_key((string) ($settings['layout_primary_menu_key'] ?? 'header'));
    $settings['layout_secondary_menu_key'] = sr_content_clean_layout_menu_key((string) ($settings['layout_secondary_menu_key'] ?? ''));
    $settings['layout_tertiary_menu_key'] = sr_content_clean_layout_menu_key((string) ($settings['layout_tertiary_menu_key'] ?? ''));

    return $settings;
}

function sr_content_default_layout_key(PDO $pdo, ?array $site = null): string
{
    $layoutKey = sr_public_layout_normalize_key((string) (sr_content_settings($pdo)['layout_key'] ?? ''));
    if ($layoutKey !== '' && isset(sr_public_layout_options($pdo)[$layoutKey])) {
        return $layoutKey;
    }

    return sr_public_layout_key($site, $pdo);
}

function sr_content_public_layout_context(array $settings, array $context = []): array
{
    $siteMenus = [
        'primary' => sr_content_clean_layout_menu_key((string) ($settings['layout_primary_menu_key'] ?? 'header')),
        'secondary' => sr_content_clean_layout_menu_key((string) ($settings['layout_secondary_menu_key'] ?? '')),
        'tertiary' => sr_content_clean_layout_menu_key((string) ($settings['layout_tertiary_menu_key'] ?? '')),
    ];

    $context['site_menus'] = array_merge(is_array($context['site_menus'] ?? null) ? $context['site_menus'] : [], $siteMenus);

    return $context;
}

function sr_content_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'content' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('콘텐츠 모듈이 등록되어 있지 않습니다.');
    }

    $rows = [
        ['editor', sr_editor_normalize_key((string) ($settings['editor'] ?? 'textarea')), 'string'],
        ['once_history_policy', sr_content_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access')), 'string'],
        ['layout_key', sr_public_layout_normalize_key((string) ($settings['layout_key'] ?? 'content.basic')), 'string'],
        ['layout_primary_menu_key', sr_content_clean_layout_menu_key((string) ($settings['layout_primary_menu_key'] ?? 'header')), 'string'],
        ['layout_secondary_menu_key', sr_content_clean_layout_menu_key((string) ($settings['layout_secondary_menu_key'] ?? '')), 'string'],
        ['layout_tertiary_menu_key', sr_content_clean_layout_menu_key((string) ($settings['layout_tertiary_menu_key'] ?? '')), 'string'],
    ];
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    foreach ($rows as $row) {
        $stmt->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => (string) $row[0],
            'setting_value' => (string) $row[1],
            'value_type' => (string) $row[2],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    sr_clear_module_settings_cache('content');
}

function sr_content_once_history_policy_values(): array
{
    return [
        'all_access' => '결제/쿠폰 이력이 있으면 재결제 없음',
        'asset_any' => '결제 이력만 재결제 없음 (쿠폰 제외)',
        'current_asset_once' => '현재 결제수단 이력만 재결제 없음',
    ];
}

function sr_content_once_history_policy(string $policy): string
{
    return array_key_exists($policy, sr_content_once_history_policy_values()) ? $policy : 'all_access';
}

function sr_content_editor_key(PDO $pdo): string
{
    $settings = sr_content_settings($pdo);
    return sr_editor_effective_key($pdo, (string) $settings['editor']);
}

function sr_content_html_body_enabled(PDO $pdo): bool
{
    return sr_content_editor_key($pdo) === 'ckeditor';
}

function sr_content_body_html(array $page): string
{
    return sr_body_text_html($page);
}

function sr_content_link_card_resolve_many(PDO $pdo, array $types): array
{
    $ids = [];
    foreach ($types['content'] ?? [] as $id) {
        if (preg_match('/\A[1-9][0-9]*\z/', (string) $id) === 1) {
            $ids[(int) $id] = true;
        }
    }
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        'SELECT id, slug, title, summary, status, published_at
         FROM sr_content_items
         WHERE id IN (' . $placeholders . ')'
    );
    $stmt->execute(array_keys($ids));

    $resolved = [];
    foreach ($stmt->fetchAll() as $row) {
        $contentId = (string) (int) ($row['id'] ?? 0);
        $status = (string) ($row['status'] ?? '');
        $isPublished = $status === 'published';
        $resolved[sr_link_card_ref_key('content', 'content', $contentId)] = [
            'module' => 'content',
            'entity_type' => 'content',
            'entity_id' => $contentId,
            'title' => $isPublished ? (string) ($row['title'] ?? '') : '연결할 수 없는 콘텐츠',
            'summary' => $isPublished ? (string) ($row['summary'] ?? '') : '',
            'url' => $isPublished ? sr_content_path((string) ($row['slug'] ?? '')) : '',
            'status' => $status,
            'broken' => !$isPublished,
        ];
    }

    foreach (array_keys($ids) as $id) {
        $key = sr_link_card_ref_key('content', 'content', (string) $id);
        if (!isset($resolved[$key])) {
            $resolved[$key] = sr_link_card_broken_result('content', 'content', (string) $id);
        }
    }

    return $resolved;
}

function sr_content_admin_link_refs(PDO $pdo, bool $brokenOnly = false): array
{
    return [];
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
        'asset_access_enabled' => '0',
        'asset_module' => '',
        'asset_access_amount' => '0',
        'asset_access_amounts_json' => '',
        'asset_access_group_policies_json' => '',
        'asset_access_policy_set_id' => '0',
        'asset_charge_policy' => 'once',
        'asset_action_enabled' => '0',
        'asset_action_module' => '',
        'asset_action_amount' => '0',
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
    ];

    foreach (sr_content_public_display_setting_labels() as $settingKey => $settingLabel) {
        $settings[(string) $settingKey] = '0';
    }

    return $settings;
}

function sr_content_default_values(?PDO $pdo = null, ?array $site = null, array $groupSettings = []): array
{
    $defaults = sr_content_group_default_settings($site, $pdo);
    foreach (sr_content_group_setting_keys() as $settingKey) {
        if (array_key_exists((string) $settingKey, $groupSettings)) {
            $defaults[(string) $settingKey] = (string) $groupSettings[(string) $settingKey];
        }
    }

    $values = [
        'title' => '',
        'content_group_scope' => 'here_only',
        'content_group_id' => 0,
        'slug' => '',
        'summary' => '',
        'body_text' => '',
        'status' => (string) ($defaults['status'] ?? 'draft'),
        'layout_key' => (string) ($defaults['layout_key'] ?? ($pdo instanceof PDO ? sr_content_default_layout_key($pdo, $site) : '')),
        'asset_access_enabled' => (int) ($defaults['asset_access_enabled'] ?? 0),
        'asset_module' => (string) ($defaults['asset_module'] ?? ''),
        'asset_access_amount' => (int) ($defaults['asset_access_amount'] ?? 0),
        'asset_access_amounts_json' => (string) ($defaults['asset_access_amounts_json'] ?? ''),
        'asset_access_group_policies_json' => (string) ($defaults['asset_access_group_policies_json'] ?? ''),
        'asset_access_policy_set_id' => (int) ($defaults['asset_access_policy_set_id'] ?? 0),
        'asset_charge_policy' => (string) ($defaults['asset_charge_policy'] ?? 'once'),
        'asset_action_enabled' => (int) ($defaults['asset_action_enabled'] ?? 0),
        'asset_action_module' => (string) ($defaults['asset_action_module'] ?? ''),
        'asset_action_amount' => (int) ($defaults['asset_action_amount'] ?? 0),
        'asset_action_amounts_json' => (string) ($defaults['asset_action_amounts_json'] ?? ''),
        'asset_action_group_policies_json' => (string) ($defaults['asset_action_group_policies_json'] ?? ''),
        'asset_action_policy_set_id' => (int) ($defaults['asset_action_policy_set_id'] ?? 0),
        'asset_action_direction' => (string) ($defaults['asset_action_direction'] ?? 'grant'),
        'asset_action_label' => (string) ($defaults['asset_action_label'] ?? sr_t('content::ui.text.727333ab')),
        'banner_before_content_id' => (int) ($defaults['banner_before_content_id'] ?? 0),
        'banner_after_content_id' => (int) ($defaults['banner_after_content_id'] ?? 0),
        'popup_layer_id' => (int) ($defaults['popup_layer_id'] ?? 0),
        'seo_title' => '',
        'seo_description' => '',
    ];

    foreach (sr_content_group_setting_keys() as $settingKey) {
        $values['source_' . (string) $settingKey] = 'content';
    }

    return sr_content_normalize_asset_values($values);
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

function sr_content_by_slug(PDO $pdo, string $slug): ?array
{
    if (!sr_content_slug_is_valid($slug)) {
        return null;
    }

    sr_content_publish_due_scheduled($pdo);

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

    return sr_content_with_effective_settings($pdo, $row);
}

function sr_content_published_by_slug(PDO $pdo, string $slug): ?array
{
    $page = sr_content_by_slug($pdo, $slug);

    return is_array($page) && (string) ($page['status'] ?? '') === 'published' ? $page : null;
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

function sr_content_admin_multi_filter_values(string $key, array $allowedValues): array
{
    $rawValues = $_GET[$key] ?? [];
    if (!is_array($rawValues)) {
        $rawValues = [$rawValues];
    }

    $allowedMap = array_fill_keys(array_map('strval', $allowedValues), true);
    $selected = [];
    foreach ($rawValues as $rawValue) {
        $value = is_scalar($rawValue) ? trim((string) $rawValue) : '';
        if ($value === '' || !isset($allowedMap[$value])) {
            continue;
        }
        $selected[$value] = $value;
    }

    return array_values($selected);
}

function sr_content_admin_single_filter_values(string $key, array $allowedValues): array
{
    $values = sr_content_admin_multi_filter_values($key, $allowedValues);

    return $values === [] ? [] : [(string) $values[0]];
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

function sr_content_admin_filters(): array
{
    $statuses = sr_content_admin_multi_filter_values('status', sr_content_allowed_statuses());

    $field = sr_get_string('field', 20);
    if (!in_array($field, ['all', 'title', 'slug'], true)) {
        $field = 'all';
    }

    return [
        'status' => $statuses,
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
    sr_content_publish_due_scheduled($pdo);
    $counts = [
        'total' => 0,
        'draft' => 0,
        'scheduled' => 0,
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

function sr_content_admin_query_parts(array $filters): array
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
        $where[] = 'p.status IN (' . implode(', ', $placeholders) . ')';
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

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_content_admin_sort_options(): array
{
    return [
        'title' => [
            'label' => '제목',
            'columns' => ['p.title', 'p.id'],
        ],
        'content_group' => [
            'label' => '콘텐츠 그룹',
            'columns' => ['g.title', 'p.id'],
        ],
        'slug' => [
            'label' => 'Slug',
            'columns' => ['p.slug', 'p.id'],
        ],
        'status' => [
            'label' => '상태',
            'columns' => ['p.status', 'p.id'],
        ],
        'asset_access' => [
            'label' => '유료 열람',
            'columns' => ['p.asset_access_enabled', 'p.asset_module', 'p.asset_access_amount', 'p.id'],
        ],
        'created_by' => [
            'label' => '작성자',
            'columns' => ["COALESCE(creator.display_name, '')", 'p.id'],
        ],
        'updated_at' => [
            'label' => '수정일',
            'columns' => ['p.updated_at', 'p.id'],
        ],
        'published_at' => [
            'label' => '공개일',
            'columns' => ['p.published_at', 'p.id'],
        ],
    ];
}

function sr_content_admin_default_sort(): array
{
    return [
        'key' => 'updated_at',
        'dir' => 'desc',
    ];
}

function sr_content_admin_sort_from_request(): array
{
    $defaultSort = sr_content_admin_default_sort();
    $sortKey = sr_get_string('sort', 40);
    $sortDir = strtolower(sr_get_string('dir', 10));
    $sortOptions = sr_content_admin_sort_options();

    if (!isset($sortOptions[$sortKey])) {
        $sortKey = (string) $defaultSort['key'];
        $sortDir = (string) $defaultSort['dir'];
    }
    if (!in_array($sortDir, ['asc', 'desc'], true)) {
        $sortDir = (string) $defaultSort['dir'];
    }

    return [
        'key' => $sortKey,
        'dir' => $sortDir,
        'is_default' => $sortKey === (string) $defaultSort['key'] && $sortDir === (string) $defaultSort['dir'],
    ];
}

function sr_content_admin_sort_order_sql(array $sort): string
{
    $sortOptions = sr_content_admin_sort_options();
    $defaultSort = sr_content_admin_default_sort();
    $sortKey = (string) ($sort['key'] ?? $defaultSort['key']);
    $sortDir = strtolower((string) ($sort['dir'] ?? $defaultSort['dir']));
    if (!isset($sortOptions[$sortKey])) {
        $sortKey = (string) $defaultSort['key'];
        $sortDir = (string) $defaultSort['dir'];
    }
    if (!in_array($sortDir, ['asc', 'desc'], true)) {
        $sortDir = (string) $defaultSort['dir'];
    }

    $orderParts = [];
    foreach ($sortOptions[$sortKey]['columns'] as $column) {
        $orderParts[] = $column . ' ' . strtoupper($sortDir);
    }

    return ' ORDER BY ' . implode(', ', $orderParts);
}

function sr_content_admin_sort_url(string $sortKey = '', string $sortDir = ''): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
    $contextPath = function_exists('sr_request_path') ? sr_request_path() : $path;
    $queryString = (string) (parse_url($uri, PHP_URL_QUERY) ?: '');
    $params = [];
    if ($queryString !== '') {
        parse_str($queryString, $params);
        if (!is_array($params)) {
            $params = [];
        }
        if (function_exists('sr_admin_normalize_query_params')) {
            $params = sr_admin_normalize_query_params($params, $contextPath);
        }
    }

    unset($params['page'], $params['sort'], $params['dir']);

    $sortOptions = sr_content_admin_sort_options();
    $defaultSort = sr_content_admin_default_sort();
    $sortDir = strtolower($sortDir);
    if (isset($sortOptions[$sortKey]) && in_array($sortDir, ['asc', 'desc'], true)) {
        if ($sortKey !== (string) $defaultSort['key'] || $sortDir !== (string) $defaultSort['dir']) {
            $params['sort'] = $sortKey;
            $params['dir'] = $sortDir;
        }
    }

    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    return sr_url($path . ($query !== '' ? '?' . $query : ''));
}

function sr_content_admin_sort_header_html(string $label, string $sortKey, array $currentSort): string
{
    $currentKey = (string) ($currentSort['key'] ?? '');
    $currentDir = (string) ($currentSort['dir'] ?? 'desc');
    $isCurrent = $currentKey === $sortKey;
    $ascActive = $isCurrent && $currentDir === 'asc';
    $descActive = $isCurrent && $currentDir === 'desc';

    $ascClass = 'btn btn-sm admin-sort-button btn-group-start ' . ($ascActive ? 'btn-solid-primary' : 'btn-solid-light');
    $descClass = 'btn btn-sm admin-sort-button btn-group-end ' . ($descActive ? 'btn-solid-primary' : 'btn-solid-light');
    $ascLabel = $label . ' 오름차순 정렬';
    $descLabel = $label . ' 내림차순 정렬';

    return '<div class="admin-sort-header">'
        . '<span class="admin-sort-label">' . sr_e($label) . '</span>'
        . '<span class="admin-sort-button-group" role="group" aria-label="' . sr_e($label . ' 정렬 방향') . '">'
        . '<a href="' . sr_e(sr_content_admin_sort_url($sortKey, 'asc')) . '" class="' . sr_e($ascClass) . '" aria-label="' . sr_e($ascLabel) . '" title="' . sr_e($ascLabel) . '"' . ($ascActive ? ' aria-current="true"' : '') . '>' . sr_material_icon_html('arrow_upward') . '</a>'
        . '<a href="' . sr_e(sr_content_admin_sort_url($sortKey, 'desc')) . '" class="' . sr_e($descClass) . '" aria-label="' . sr_e($descLabel) . '" title="' . sr_e($descLabel) . '"' . ($descActive ? ' aria-current="true"' : '') . '>' . sr_material_icon_html('arrow_downward') . '</a>'
        . '</span>'
        . ($isCurrent ? '<span class="sr-only">현재 ' . sr_e($currentDir === 'asc' ? '오름차순' : '내림차순') . '</span>' : '')
        . '</div>';
}

function sr_content_admin_sort_aria(string $sortKey, array $currentSort): string
{
    if ((string) ($currentSort['key'] ?? '') !== $sortKey) {
        return '';
    }

    return (string) ($currentSort['dir'] ?? 'desc') === 'asc' ? ' aria-sort="ascending"' : ' aria-sort="descending"';
}

function sr_content_admin_count(PDO $pdo, array $filters): int
{
    sr_content_publish_due_scheduled($pdo);
    $queryParts = sr_content_admin_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value
            FROM sr_content_items p
            LEFT JOIN sr_content_groups g ON g.id = p.content_group_id
            LEFT JOIN sr_member_accounts creator ON creator.id = p.created_by
            LEFT JOIN sr_member_accounts updater ON updater.id = p.updated_by';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_content_admin_list(PDO $pdo, array $filters, int $limit = 0, int $offset = 0, array $sort = []): array
{
    sr_content_publish_due_scheduled($pdo);
    $queryParts = sr_content_admin_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $seriesSupported = sr_content_series_supported($pdo);
    $seriesSelectSql = $seriesSupported
        ? 'cs.series_key AS content_series_key, cs.title AS content_series_title,
                   csi.episode_label AS content_series_episode_label, csi.sort_order AS content_series_sort_order,'
        : "'' AS content_series_key, '' AS content_series_title,
                   '' AS content_series_episode_label, 0 AS content_series_sort_order,";
    $seriesJoinSql = $seriesSupported
        ? 'LEFT JOIN sr_content_series_items csi ON csi.active_content_id = p.id
            LEFT JOIN sr_content_series cs ON cs.id = csi.series_id'
        : '';
    $sql = 'SELECT p.*, g.group_key AS content_group_key, g.title AS content_group_title,
                   ' . $seriesSelectSql . '
                   creator.display_name AS created_by_name, updater.display_name AS updated_by_name
            FROM sr_content_items p
            LEFT JOIN sr_content_groups g ON g.id = p.content_group_id
            ' . $seriesJoinSql . '
            LEFT JOIN sr_member_accounts creator ON creator.id = p.created_by
            LEFT JOIN sr_member_accounts updater ON updater.id = p.updated_by';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= sr_content_admin_sort_order_sql($sort);
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

function sr_content_published_contents_for_group(PDO $pdo, int $groupId): array
{
    if ($groupId < 1) {
        return [];
    }

    sr_content_publish_due_scheduled($pdo);

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

function sr_content_optional_table_exists(PDO $pdo, string $tableName): bool
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

function sr_content_optional_count(PDO $pdo, string $tableName, string $whereSql, array $params = []): int
{
    if (!sr_content_optional_table_exists($pdo, $tableName)) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $tableName . ' WHERE ' . $whereSql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_content_group_reference_counts(PDO $pdo, int $groupId): array
{
    return [
        'contents' => $groupId > 0 ? sr_content_optional_count($pdo, 'sr_content_items', 'content_group_id = :group_id', ['group_id' => $groupId]) : 0,
        'revision_references' => $groupId > 0 ? sr_content_optional_count($pdo, 'sr_content_revisions', 'content_group_id = :group_id', ['group_id' => $groupId]) : 0,
        'comments' => $groupId > 0 && sr_content_optional_table_exists($pdo, 'sr_content_comments')
            ? sr_content_optional_count($pdo, 'sr_content_comments', 'content_id IN (SELECT id FROM sr_content_items WHERE content_group_id = :group_id)', ['group_id' => $groupId])
            : 0,
        'files' => $groupId > 0 && sr_content_optional_table_exists($pdo, 'sr_content_files')
            ? sr_content_optional_count($pdo, 'sr_content_files', 'content_id IN (SELECT id FROM sr_content_items WHERE content_group_id = :group_id)', ['group_id' => $groupId])
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

    $contentIds = sr_content_group_content_ids($pdo, $groupId);
    $files = sr_content_group_file_rows_for_delete($pdo, $contentIds);
    $pdo->beginTransaction();
    try {
        $deletedSettings = sr_content_optional_count($pdo, 'sr_content_group_settings', 'group_id = :group_id', ['group_id' => $groupId]);
        $deletedContents = count($contentIds);
        $deletedFiles = count($files);
        $deletedComments = (int) ($check['references']['comments'] ?? 0);
        $deletedRevisions = (int) ($check['references']['revision_references'] ?? 0);
        if ($contentIds !== []) {
            $placeholders = implode(',', array_fill(0, count($contentIds), '?'));

            if (sr_content_optional_table_exists($pdo, 'sr_content_comments')) {
                $pdo->prepare('DELETE FROM sr_content_comments WHERE content_id IN (' . $placeholders . ')')->execute($contentIds);
            }
            if (sr_content_optional_table_exists($pdo, 'sr_content_link_refs')) {
                $pdo->prepare('DELETE FROM sr_content_link_refs WHERE content_id IN (' . $placeholders . ')')->execute($contentIds);
            }
            if (sr_content_optional_table_exists($pdo, 'sr_content_series_items')) {
                $pdo->prepare('DELETE FROM sr_content_series_items WHERE content_id IN (' . $placeholders . ') OR active_content_id IN (' . $placeholders . ')')->execute(array_merge($contentIds, $contentIds));
            }
            if (sr_content_optional_table_exists($pdo, 'sr_content_access_entitlements')) {
                $pdo->prepare('DELETE FROM sr_content_access_entitlements WHERE content_id IN (' . $placeholders . ')')->execute($contentIds);
            }
            if (sr_content_optional_table_exists($pdo, 'sr_content_setting_sources')) {
                $pdo->prepare('DELETE FROM sr_content_setting_sources WHERE content_id IN (' . $placeholders . ')')->execute($contentIds);
            }
            if (sr_content_optional_table_exists($pdo, 'sr_content_revisions')) {
                $pdo->prepare('DELETE FROM sr_content_revisions WHERE content_id IN (' . $placeholders . ')')->execute($contentIds);
            }
            if (sr_content_optional_table_exists($pdo, 'sr_content_file_links')) {
                $pdo->prepare('DELETE FROM sr_content_file_links WHERE content_id IN (' . $placeholders . ')')->execute($contentIds);
            }
            if ($files !== []) {
                $fileIds = array_values(array_map(static fn (array $file): int => (int) ($file['id'] ?? 0), $files));
                $fileIds = array_values(array_filter($fileIds, static fn (int $fileId): bool => $fileId > 0));
                if ($fileIds !== []) {
                    $filePlaceholders = implode(',', array_fill(0, count($fileIds), '?'));
                    if (sr_content_optional_table_exists($pdo, 'sr_content_file_links')) {
                        $pdo->prepare('DELETE FROM sr_content_file_links WHERE file_id IN (' . $filePlaceholders . ')')->execute($fileIds);
                    }
                    $pdo->prepare('DELETE FROM sr_content_files WHERE id IN (' . $filePlaceholders . ')')->execute($fileIds);
                }
            }
            $pdo->prepare('DELETE FROM sr_content_items WHERE id IN (' . $placeholders . ')')->execute($contentIds);
        }
        if (sr_content_optional_table_exists($pdo, 'sr_content_group_settings')) {
            $pdo->prepare('DELETE FROM sr_content_group_settings WHERE group_id = :group_id')->execute(['group_id' => $groupId]);
        }
        $pdo->prepare('DELETE FROM sr_content_groups WHERE id = :id')->execute(['id' => $groupId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $failedFiles = 0;
    $failedFileRefs = [];
    foreach ($files as $file) {
        $driver = function_exists('sr_content_file_storage_driver') ? sr_content_file_storage_driver($file) : (string) ($file['storage_driver'] ?? 'local');
        $key = function_exists('sr_content_file_storage_key') ? sr_content_file_storage_key($file) : (string) ($file['storage_key'] ?? '');
        if ($key !== '' && !sr_storage_delete($driver, $key)) {
            $failedFiles++;
            $failedFileRefs[] = $driver . ':' . $key;
            sr_content_record_storage_cleanup_failure($pdo, 'group_delete_file', $groupId, $driver, $key, '콘텐츠 그룹 삭제 후 파일 저장소 정리에 실패했습니다.');
        }
    }
    $deletedBodyFiles = sr_content_cleanup_body_files_for_deleted_content($pdo, $contentIds);

    $check['deleted_settings'] = $deletedSettings;
    $check['deleted_contents'] = $deletedContents;
    $check['deleted_comments'] = $deletedComments;
    $check['deleted_revisions'] = $deletedRevisions;
    $check['deleted_files'] = $deletedFiles - $failedFiles;
    $check['deleted_body_files'] = $deletedBodyFiles;
    $check['failed_files'] = $failedFiles;
    $check['failed_file_refs'] = $failedFileRefs;
    return $check;
}

function sr_content_record_storage_cleanup_failure(PDO $pdo, string $sourceType, int $sourceId, string $driver, string $key, string $errorMessage): void
{
    if (!sr_content_optional_table_exists($pdo, 'sr_content_storage_cleanup_failures') || !sr_storage_key_is_safe($key)) {
        return;
    }

    $driver = in_array($driver, ['local', 's3'], true) ? $driver : 'local';
    $now = sr_now();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_content_storage_cleanup_failures
                (source_type, source_id, storage_driver, storage_key, status, attempt_count, last_error, created_at, updated_at)
             VALUES
                (:source_type, :source_id, :storage_driver, :storage_key, \'pending\', 1, :last_error, :created_at, :updated_at)'
        );
        $stmt->execute([
            'source_type' => sr_content_clean_key($sourceType),
            'source_id' => $sourceId,
            'storage_driver' => $driver,
            'storage_key' => $key,
            'last_error' => sr_content_clean_text($errorMessage, 1000),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'content_storage_cleanup_failure_record_failed');
    }
}

function sr_content_storage_cleanup_failures(PDO $pdo, int $limit = 50): array
{
    if (!sr_content_optional_table_exists($pdo, 'sr_content_storage_cleanup_failures')) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_content_storage_cleanup_failures
         WHERE status = 'pending'
         ORDER BY updated_at DESC, id DESC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_content_retry_storage_cleanup_failure(PDO $pdo, int $failureId): array
{
    if ($failureId < 1 || !sr_content_optional_table_exists($pdo, 'sr_content_storage_cleanup_failures')) {
        return ['ok' => false, 'message' => '저장소 정리 실패 기록을 찾을 수 없습니다.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM sr_content_storage_cleanup_failures WHERE id = :id AND status = 'pending' LIMIT 1");
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
            "UPDATE sr_content_storage_cleanup_failures
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
        "UPDATE sr_content_storage_cleanup_failures
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

function sr_content_clean_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
    $value = trim($value, '_');

    return $value !== '' ? substr($value, 0, 60) : 'unknown';
}

function sr_content_group_content_ids(PDO $pdo, int $groupId): array
{
    if ($groupId < 1 || !sr_content_optional_table_exists($pdo, 'sr_content_items')) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT id FROM sr_content_items WHERE content_group_id = :group_id ORDER BY id ASC');
    $stmt->execute(['group_id' => $groupId]);

    return array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
}

function sr_content_group_file_rows_for_delete(PDO $pdo, array $contentIds): array
{
    $contentIds = array_values(array_filter(array_map('intval', $contentIds), static fn (int $contentId): bool => $contentId > 0));
    if ($contentIds === [] || !sr_content_optional_table_exists($pdo, 'sr_content_files')) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($contentIds), '?'));
    $params = $contentIds;
    $linkClause = '';
    if (sr_content_optional_table_exists($pdo, 'sr_content_file_links')) {
        $linkClause = ' OR (
            f.content_id = 0
            AND EXISTS (SELECT 1 FROM sr_content_file_links owned_link WHERE owned_link.file_id = f.id AND owned_link.content_id IN (' . $placeholders . '))
            AND NOT EXISTS (SELECT 1 FROM sr_content_file_links outside_link WHERE outside_link.file_id = f.id AND outside_link.content_id NOT IN (' . $placeholders . '))
        )';
        $params = array_merge($params, $contentIds, $contentIds);
    }

    $stmt = $pdo->prepare(
        'SELECT f.*
         FROM sr_content_files f
         WHERE f.content_id IN (' . $placeholders . ')' . $linkClause . '
         ORDER BY f.id ASC'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
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

function sr_content_homepage_path_is_available(PDO $pdo, string $homePath): ?bool
{
    if (str_starts_with($homePath, '/content/group?key=')) {
        $query = parse_url($homePath, PHP_URL_QUERY);
        if (!is_string($query)) {
            return false;
        }

        parse_str($query, $params);
        $groupKey = is_string($params['key'] ?? null) ? strtolower(trim((string) $params['key'])) : '';
        return $groupKey !== '' && sr_content_enabled_group_by_key($pdo, $groupKey) !== null;
    }

    $prefix = '/content/';
    if (!str_starts_with($homePath, $prefix)) {
        return null;
    }

    $slug = rawurldecode(substr($homePath, strlen($prefix)));
    if (!is_string($slug) || $slug === '' || strpos($slug, '/') !== false) {
        return false;
    }

    return sr_content_published_by_slug($pdo, sr_content_clean_slug($slug)) !== null;
}

function sr_content_coupon_target_search(PDO $pdo, string $targetType, string $keyword, int $limit = 20): array
{
    if ($targetType !== 'content') {
        return [];
    }

    $keyword = sr_content_clean_text($keyword, 120);
    $limit = max(1, min(30, $limit));
    $where = $keyword === '' ? '1 = 1' : "(id = :id OR title LIKE :keyword_title ESCAPE '\\\\' OR slug LIKE :keyword_slug ESCAPE '\\\\')";
    $params = [];
    if ($keyword !== '') {
        $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
        $params = [
            'id' => preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0,
            'keyword_title' => $keywordLike,
            'keyword_slug' => $keywordLike,
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id, title, slug, status, updated_at
         FROM sr_content_items
         WHERE ' . $where . '
         ORDER BY id DESC
         LIMIT ' . $limit
    );
    $stmt->execute($params);

    return array_map(static function (array $row): array {
        return [
            'reference_type' => 'content',
            'reference_id' => (string) (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'reason' => '콘텐츠 #' . (string) (int) ($row['id'] ?? 0),
            'member_name' => 'slug: ' . (string) ($row['slug'] ?? ''),
            'member_email' => '상태: ' . (string) ($row['status'] ?? ''),
            'created_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }, $stmt->fetchAll());
}

function sr_content_link_card_search_content_targets(PDO $pdo, string $keyword, int $limit = 10): array
{
    $keyword = sr_content_clean_text($keyword, 120);
    $limit = max(1, min(20, $limit));
    $where = $keyword === '' ? '1 = 1' : "(id = :id OR title LIKE :keyword_title ESCAPE '\\\\' OR slug LIKE :keyword_slug ESCAPE '\\\\')";
    $params = [];
    if ($keyword !== '') {
        $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
        $params = [
            'id' => preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0,
            'keyword_title' => $keywordLike,
            'keyword_slug' => $keywordLike,
        ];
    }

    sr_content_publish_due_scheduled($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, title, slug, summary, status, updated_at
         FROM sr_content_items
         WHERE status = \'published\'
           AND ' . $where . '
         ORDER BY published_at DESC, updated_at DESC, id DESC
         LIMIT ' . $limit
    );
    $stmt->execute($params);

    return array_map(static function (array $row): array {
        $contentId = (string) (int) ($row['id'] ?? 0);
        return [
            'module' => 'content',
            'entity_type' => 'content',
            'entity_id' => $contentId,
            'title' => (string) ($row['title'] ?? ''),
            'summary' => (string) ($row['summary'] ?? ''),
            'url' => sr_content_path((string) ($row['slug'] ?? '')),
            'status' => (string) ($row['status'] ?? ''),
            'meta' => '콘텐츠 #' . $contentId . ' / slug: ' . (string) ($row['slug'] ?? ''),
        ];
    }, $stmt->fetchAll());
}

function sr_content_coupon_revoke_access(PDO $pdo, int $accountId, string $dedupeKey): int
{
    require_once SR_ROOT . '/modules/content/helpers/assets.php';
    return sr_content_revoke_coupon_access_entitlements($pdo, $accountId, $dedupeKey);
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

function sr_content_input_values(?PDO $pdo = null): array
{
    $pageGroupScope = sr_content_group_apply_scope(sr_post_string('content_group_scope', 20));
    $pageGroupId = (int) sr_post_string('content_group_id', 20);
    $bodyFormat = 'plain';
    if ($pdo instanceof PDO && sr_post_string('body_format', 20) === 'html' && sr_content_html_body_enabled($pdo)) {
        $bodyFormat = 'html';
    }
    $bodyText = sr_post_string_without_truncation('body_text', 100000);
    if (!is_string($bodyText)) {
        $bodyText = '';
    }
    $bodyText = $bodyFormat === 'html'
        ? sr_sanitize_rich_text_html($bodyText)
        : sr_content_clean_text($bodyText, 100000);

    $assetAccessPolicySetIds = sr_content_asset_policy_set_ids_from_value($_POST['asset_access_policy_set_ids'] ?? []);
    $assetActionPolicySetIds = sr_content_asset_policy_set_ids_from_value($_POST['asset_action_policy_set_ids'] ?? []);
    $values = [
        'content_group_scope' => $pageGroupScope,
        'content_group_id' => $pageGroupId,
        'source_status' => sr_content_normalize_setting_source(sr_post_string('source_status', 20)),
        'source_layout_key' => sr_content_normalize_setting_source(sr_post_string('source_layout_key', 20)),
        'title' => sr_content_clean_single_line(sr_post_string('title', 160), 160),
        'slug' => sr_content_clean_slug(sr_post_string('slug', 120)),
        'summary' => sr_content_clean_text(sr_post_string('summary', 1000), 1000),
        'body_text' => $bodyText,
        'body_format' => $bodyFormat,
        'status' => sr_post_string('status', 30),
        'layout_key' => sr_public_layout_normalize_key(sr_post_string('layout_key', 80)),
        'asset_access_enabled' => sr_post_string('asset_access_enabled', 1) === '1' ? 1 : 0,
        'asset_module' => sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($_POST['asset_module'] ?? '')),
        'asset_access_amount' => sr_admin_post_int_in_range('asset_access_amount', 0, 999999999) ?? 0,
        'asset_access_amounts_json' => sr_content_asset_amounts_json_from_map(sr_content_asset_amounts_from_post('asset_access_amounts', sr_content_asset_module_keys_from_value($_POST['asset_module'] ?? ''), sr_admin_post_int_in_range('asset_access_amount', 0, 999999999) ?? 0)),
        'asset_access_group_policies_json' => sr_content_asset_policy_set_selection_json_from_ids($assetAccessPolicySetIds),
        'asset_access_policy_set_id' => sr_content_asset_policy_set_first_id($assetAccessPolicySetIds),
        'asset_charge_policy' => sr_content_clean_slug(sr_post_string('asset_charge_policy', 20)),
        'asset_action_enabled' => sr_post_string('asset_action_enabled', 1) === '1' ? 1 : 0,
        'asset_action_module' => sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($_POST['asset_action_module'] ?? '')),
        'asset_action_amount' => sr_admin_post_int_in_range('asset_action_amount', 0, 999999999) ?? 0,
        'asset_action_amounts_json' => sr_content_asset_amounts_json_from_map(sr_content_asset_amounts_from_post('asset_action_amounts', sr_content_asset_module_keys_from_value($_POST['asset_action_module'] ?? ''), sr_admin_post_int_in_range('asset_action_amount', 0, 999999999) ?? 0)),
        'asset_action_group_policies_json' => sr_content_asset_policy_set_selection_json_from_ids($assetActionPolicySetIds),
        'asset_action_policy_set_id' => sr_content_asset_policy_set_first_id($assetActionPolicySetIds),
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
    $values['source_asset_access_group_policies_json'] = $values['source_asset_access_policy_set_id'] ?? $legacyAccessSource;
    $values['source_asset_action_group_policies_json'] = $values['source_asset_action_policy_set_id'] ?? $legacyActionSource;
    $values['source_file_asset_download_group_policies_json'] = $values['source_file_asset_download_policy_set_id'] ?? $legacyFileSource;
    $values['source_asset_access_amounts_json'] = $values['source_asset_access_amount'] ?? $legacyAccessSource;
    $values['source_asset_action_amounts_json'] = $values['source_asset_action_amount'] ?? $legacyActionSource;
    $values['source_file_asset_download_amounts_json'] = $values['source_file_asset_download_amount'] ?? $legacyFileSource;

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
        $errors[] = '그룹 적용을 선택하려면 콘텐츠 그룹을 선택하세요.';
    }

    $sourceLabels = [
        'source_status' => '상태',
        'source_layout_key' => '콘텐츠 레이아웃',
    ];
    foreach (sr_content_group_asset_access_setting_keys() as $settingKey) {
        $sourceLabels['source_' . $settingKey] = '유료 열람';
    }
    foreach (sr_content_group_asset_action_setting_keys() as $settingKey) {
        $sourceLabels['source_' . $settingKey] = '완료 버튼';
    }
    foreach ([
        'file_asset_download_enabled' => '파일 다운로드 사용',
        'file_asset_module' => '파일 다운로드 항목',
        'file_asset_download_amount' => '파일 다운로드 금액',
        'file_asset_download_amounts_json' => '파일 다운로드 항목별 금액',
        'file_asset_charge_policy' => '파일 다운로드 과금 방식',
        'asset_action_label' => '완료 버튼 문구',
    ] as $settingKey => $sourceLabel) {
        $sourceLabels['source_' . $settingKey] = $sourceLabel;
    }
    foreach ($sourceLabels as $sourceKey => $sourceLabel) {
        if (sr_content_normalize_setting_source((string) ($values[$sourceKey] ?? 'content')) === 'group' && $pageGroupId < 1) {
            $errors[] = $sourceLabel . ' 설정은 콘텐츠 그룹이 있어야 그룹 적용을 할 수 있습니다.';
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

    if ((string) ($values['status'] ?? '') === 'scheduled') {
        $scheduledPublishAt = (string) ($values['scheduled_publish_at'] ?? '');
        if ($scheduledPublishAt === '') {
            $errors[] = '예약 발행 시각을 입력하세요.';
        } elseif (strtotime($scheduledPublishAt) === false) {
            $errors[] = '예약 발행 시각 형식이 올바르지 않습니다.';
        } elseif (strtotime($scheduledPublishAt) <= time()) {
            $errors[] = '예약 발행 시각은 현재보다 미래여야 합니다.';
        }
    }

    $layoutKey = (string) ($values['layout_key'] ?? '');
    if ($layoutKey !== '' && !isset(sr_public_layout_options($pdo)[$layoutKey])) {
        $errors[] = '콘텐츠 레이아웃 값이 올바르지 않습니다.';
    }

    if (!in_array((string) ($values['body_format'] ?? 'plain'), ['plain', 'html'], true)) {
        $errors[] = '본문 형식이 올바르지 않습니다.';
    }
    $errors = array_merge($errors, sr_link_card_token_rejection_errors((string) ($values['body_text'] ?? '')));

    if ((int) ($values['asset_access_enabled'] ?? 0) === 1) {
        $assetModules = sr_content_asset_module_keys_from_value($values['asset_module'] ?? '');
        if ($assetModules === []) {
            $errors[] = '유료 열람 항목이 올바르지 않습니다.';
        } elseif (!sr_content_asset_modules_available($pdo, $assetModules)) {
            $errors[] = '선택한 포인트/금액 항목이 모두 활성 상태일 때만 유료 열람 항목으로 사용할 수 있습니다.';
        }

        $amount = (int) ($values['asset_access_amount'] ?? 0);
        if ($amount < 1 || $amount > 999999999) {
            $errors[] = '유료 열람 금액은 1부터 999999999 사이로 입력하세요.';
        }
        $amounts = sr_content_asset_amounts_from_value($values['asset_access_amounts_json'] ?? '', $assetModules);
        if (count($amounts) < count($assetModules)) {
            $errors[] = '유료 열람 항목별 금액은 선택한 항목마다 1 이상으로 입력하세요.';
        }

        if (!isset(sr_content_asset_view_charge_policies()[(string) ($values['asset_charge_policy'] ?? '')])) {
            $errors[] = '유료 열람 과금 방식이 올바르지 않습니다.';
        }
        $assetAccessPolicySetIds = sr_content_asset_policy_set_ids_with_legacy($values['asset_access_group_policies_json'] ?? '', (int) ($values['asset_access_policy_set_id'] ?? 0));
        $errors = array_merge($errors, sr_content_asset_policy_set_ids_validation_errors($pdo, $assetAccessPolicySetIds, '유료 열람'));
        $errors = array_merge($errors, sr_content_asset_policy_set_asset_match_errors($pdo, $assetAccessPolicySetIds, $assetModules, '유료 열람'));
        $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, sr_content_asset_group_policies_from_value($values['asset_access_group_policies_json'] ?? ''), '유료 열람'));
    }

    if ((int) ($values['asset_action_enabled'] ?? 0) === 1) {
        $assetModules = sr_content_asset_module_keys_from_value($values['asset_action_module'] ?? '');
        $actionDirection = (string) ($values['asset_action_direction'] ?? '');
        if ($assetModules === []) {
            $errors[] = '완료 버튼 처리 항목이 올바르지 않습니다.';
        } elseif (!sr_content_asset_modules_available($pdo, $assetModules)) {
            $errors[] = '선택한 금액 모듈이 모두 활성 상태일 때만 완료 버튼 처리 항목으로 사용할 수 있습니다.';
        } elseif ($actionDirection === 'grant' && count($assetModules) > 1) {
            $errors[] = '완료 버튼 지급은 처리 항목을 하나만 선택하세요.';
        }

        $amount = (int) ($values['asset_action_amount'] ?? 0);
        if ($amount < 1 || $amount > 999999999) {
            $errors[] = '완료 버튼 금액은 1부터 999999999 사이로 입력하세요.';
        }
        if (!isset(sr_content_asset_action_directions()[$actionDirection])) {
            $errors[] = '완료 버튼 지급/차감 방향이 올바르지 않습니다.';
        }
        $amounts = sr_content_asset_amounts_from_value($values['asset_action_amounts_json'] ?? '', $assetModules);
        if (count($amounts) < count($assetModules)) {
            $errors[] = '완료 버튼 금액은 선택한 처리 항목마다 1 이상으로 입력하세요.';
        }

        if ((string) ($values['asset_action_label'] ?? '') === '') {
            $errors[] = '완료 버튼 문구를 입력하세요.';
        }
        $assetActionPolicySetIds = sr_content_asset_policy_set_ids_with_legacy($values['asset_action_group_policies_json'] ?? '', (int) ($values['asset_action_policy_set_id'] ?? 0));
        $errors = array_merge($errors, sr_content_asset_policy_set_ids_validation_errors($pdo, $assetActionPolicySetIds, '완료 버튼'));
        $errors = array_merge($errors, sr_content_asset_policy_set_asset_match_errors($pdo, $assetActionPolicySetIds, $assetModules, '완료 버튼'));
        $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, sr_content_asset_group_policies_from_value($values['asset_action_group_policies_json'] ?? ''), '완료 버튼'));
    }

    foreach (sr_content_public_display_setting_labels() as $settingKey => $settingLabel) {
        $displayId = (int) ($values[$settingKey] ?? 0);
        if (sr_content_normalize_setting_source((string) ($values['source_' . $settingKey] ?? 'content')) === 'group' && $pageGroupId < 1) {
            $errors[] = $settingLabel . ' 설정은 콘텐츠 그룹이 있어야 그룹 적용을 할 수 있습니다.';
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
    if (sr_link_card_token_rejection_errors((string) ($values['body_text'] ?? '')) !== []) {
        throw new InvalidArgumentException('링크 카드 토큰은 콘텐츠 본문에 저장할 수 없습니다.');
    }

    $now = sr_now();
    $pdo->beginTransaction();

    try {
        $existing = $pageId > 0 ? sr_content_by_id($pdo, $pageId) : null;
        $publishedAt = null;
        if ((string) $values['status'] === 'published') {
            $publishedAt = is_array($existing) && (string) ($existing['status'] ?? '') === 'published' && !empty($existing['published_at']) ? (string) $existing['published_at'] : $now;
        } elseif ((string) $values['status'] === 'scheduled') {
            $publishedAt = (string) ($values['scheduled_publish_at'] ?? '');
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
                     asset_access_amounts_json = :asset_access_amounts_json,
                     asset_access_group_policies_json = :asset_access_group_policies_json,
                     asset_access_policy_set_id = :asset_access_policy_set_id,
                     asset_charge_policy = :asset_charge_policy,
                     asset_action_enabled = :asset_action_enabled,
                     asset_action_module = :asset_action_module,
                     asset_action_amount = :asset_action_amount,
                     asset_action_amounts_json = :asset_action_amounts_json,
                     asset_action_group_policies_json = :asset_action_group_policies_json,
                     asset_action_policy_set_id = :asset_action_policy_set_id,
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
                'body_format' => (string) ($values['body_format'] ?? 'plain'),
                'status' => (string) $values['status'],
                'layout_key' => (string) ($values['layout_key'] ?? ''),
                'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
                'asset_module' => (string) ($values['asset_module'] ?? ''),
                'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
                'asset_access_amounts_json' => (string) ($values['asset_access_amounts_json'] ?? '{}'),
                'asset_access_group_policies_json' => (string) ($values['asset_access_group_policies_json'] ?? ''),
                'asset_access_policy_set_id' => (int) ($values['asset_access_policy_set_id'] ?? 0),
                'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
                'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
                'asset_action_module' => (string) ($values['asset_action_module'] ?? ''),
                'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
                'asset_action_amounts_json' => (string) ($values['asset_action_amounts_json'] ?? '{}'),
                'asset_action_group_policies_json' => (string) ($values['asset_action_group_policies_json'] ?? ''),
                'asset_action_policy_set_id' => (int) ($values['asset_action_policy_set_id'] ?? 0),
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
                    (content_group_id, slug, title, summary, body_text, body_format, status, layout_key, asset_access_enabled, asset_module, asset_access_amount, asset_access_amounts_json, asset_access_group_policies_json, asset_access_policy_set_id, asset_charge_policy, asset_action_enabled, asset_action_module, asset_action_amount, asset_action_amounts_json, asset_action_group_policies_json, asset_action_policy_set_id, asset_action_direction, asset_action_label, banner_before_content_id, banner_after_content_id, popup_layer_id, seo_title, seo_description, created_by, updated_by, published_at, created_at, updated_at)
                 VALUES
                    (:content_group_id, :slug, :title, :summary, :body_text, :body_format, :status, :layout_key, :asset_access_enabled, :asset_module, :asset_access_amount, :asset_access_amounts_json, :asset_access_group_policies_json, :asset_access_policy_set_id, :asset_charge_policy, :asset_action_enabled, :asset_action_module, :asset_action_amount, :asset_action_amounts_json, :asset_action_group_policies_json, :asset_action_policy_set_id, :asset_action_direction, :asset_action_label, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :seo_title, :seo_description, :created_by, :updated_by, :published_at, :created_at, :updated_at)'
            );
            $stmt->execute([
                'content_group_id' => (int) ($values['content_group_id'] ?? 0) > 0 ? (int) $values['content_group_id'] : null,
                'slug' => (string) $values['slug'],
                'title' => (string) $values['title'],
                'summary' => (string) $values['summary'],
                'body_text' => (string) $values['body_text'],
                'body_format' => (string) ($values['body_format'] ?? 'plain'),
                'status' => (string) $values['status'],
                'layout_key' => (string) ($values['layout_key'] ?? ''),
                'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
                'asset_module' => (string) ($values['asset_module'] ?? ''),
                'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
                'asset_access_amounts_json' => (string) ($values['asset_access_amounts_json'] ?? '{}'),
                'asset_access_group_policies_json' => (string) ($values['asset_access_group_policies_json'] ?? ''),
                'asset_access_policy_set_id' => (int) ($values['asset_access_policy_set_id'] ?? 0),
                'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
                'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
                'asset_action_module' => (string) ($values['asset_action_module'] ?? ''),
                'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
                'asset_action_amounts_json' => (string) ($values['asset_action_amounts_json'] ?? '{}'),
                'asset_action_group_policies_json' => (string) ($values['asset_action_group_policies_json'] ?? ''),
                'asset_action_policy_set_id' => (int) ($values['asset_action_policy_set_id'] ?? 0),
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

        if ((string) ($values['body_format'] ?? 'plain') === 'html') {
            $finalBodyText = sr_content_finalize_body_files($pdo, $pageId, (string) ($values['body_text'] ?? ''), $accountId);
            if ($finalBodyText !== (string) ($values['body_text'] ?? '')) {
                $values['body_text'] = $finalBodyText;
                $stmt = $pdo->prepare('UPDATE sr_content_items SET body_text = :body_text, updated_at = :updated_at WHERE id = :id');
                $stmt->execute([
                    'body_text' => $finalBodyText,
                    'updated_at' => $now,
                    'id' => $pageId,
                ]);
            }
        } else {
            sr_content_cleanup_unreferenced_body_files($pdo, $pageId, '');
        }
        sr_link_card_reconcile_table($pdo, 'sr_content_link_refs', 'content_id', $pageId, [], $accountId);
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
            (content_id, content_group_id, title, summary, body_text, body_format, status, layout_key, asset_access_enabled, asset_module, asset_access_amount, asset_access_amounts_json, asset_access_group_policies_json, asset_access_policy_set_id, asset_charge_policy, asset_action_enabled, asset_action_module, asset_action_amount, asset_action_amounts_json, asset_action_group_policies_json, asset_action_policy_set_id, asset_action_direction, asset_action_label, banner_before_content_id, banner_after_content_id, popup_layer_id, created_by, created_at)
         VALUES
            (:content_id, :content_group_id, :title, :summary, :body_text, :body_format, :status, :layout_key, :asset_access_enabled, :asset_module, :asset_access_amount, :asset_access_amounts_json, :asset_access_group_policies_json, :asset_access_policy_set_id, :asset_charge_policy, :asset_action_enabled, :asset_action_module, :asset_action_amount, :asset_action_amounts_json, :asset_action_group_policies_json, :asset_action_policy_set_id, :asset_action_direction, :asset_action_label, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :created_by, :created_at)'
    );
    $stmt->execute([
        'content_id' => $pageId,
        'content_group_id' => (int) ($values['content_group_id'] ?? 0) > 0 ? (int) $values['content_group_id'] : null,
        'title' => (string) $values['title'],
        'summary' => (string) $values['summary'],
        'body_text' => (string) $values['body_text'],
        'body_format' => (string) ($values['body_format'] ?? 'plain'),
        'status' => (string) $values['status'],
        'layout_key' => (string) ($values['layout_key'] ?? ''),
        'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
        'asset_module' => (string) ($values['asset_module'] ?? ''),
        'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
        'asset_access_amounts_json' => (string) ($values['asset_access_amounts_json'] ?? '{}'),
        'asset_access_group_policies_json' => (string) ($values['asset_access_group_policies_json'] ?? ''),
        'asset_access_policy_set_id' => (int) ($values['asset_access_policy_set_id'] ?? 0),
        'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
        'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
        'asset_action_module' => (string) ($values['asset_action_module'] ?? ''),
        'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
        'asset_action_amounts_json' => (string) ($values['asset_action_amounts_json'] ?? '{}'),
        'asset_action_group_policies_json' => (string) ($values['asset_action_group_policies_json'] ?? ''),
        'asset_action_policy_set_id' => (int) ($values['asset_action_policy_set_id'] ?? 0),
        'asset_action_direction' => (string) ($values['asset_action_direction'] ?? 'grant'),
        'asset_action_label' => (string) ($values['asset_action_label'] ?? '완료'),
        'banner_before_content_id' => (int) ($values['banner_before_content_id'] ?? 0),
        'banner_after_content_id' => (int) ($values['banner_after_content_id'] ?? 0),
        'popup_layer_id' => (int) ($values['popup_layer_id'] ?? 0),
        'created_by' => $accountId,
        'created_at' => $now,
    ]);
}

function sr_content_copy_suggestion(array $content): array
{
    $title = sr_content_clean_single_line((string) ($content['title'] ?? '') . ' 복사본', 160);
    $slugBase = sr_content_clean_slug((string) ($content['slug'] ?? 'content') . '-copy');
    if (!sr_content_slug_is_valid($slugBase)) {
        $slugBase = 'content-copy';
    }

    return [
        'title' => $title,
        'slug' => $slugBase,
    ];
}

function sr_content_copy_series_suggestions(PDO $pdo, int $contentId): array
{
    if ($contentId < 1 || !sr_content_series_supported($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT s.id, s.series_key, s.title, si.episode_label, si.sort_order
         FROM sr_content_series_items si
         INNER JOIN sr_content_series s ON s.id = si.series_id
         WHERE si.active_content_id = :content_id
         ORDER BY s.id ASC'
    );
    $stmt->execute(['content_id' => $contentId]);

    $suggestions = [];
    foreach ($stmt->fetchAll() as $row) {
        $baseKey = sr_content_clean_slug((string) ($row['series_key'] ?? 'series') . '-copy');
        $baseKey = str_replace('-', '_', $baseKey);
        if (!sr_content_series_key_is_valid($baseKey)) {
            $baseKey = 'series_copy';
        }
        $candidate = $baseKey;
        $suffix = 2;
        while (sr_content_series_key_exists($pdo, $candidate)) {
            $candidate = substr($baseKey, 0, 54) . '_' . (string) $suffix;
            $suffix++;
        }

        $suggestions[] = [
            'series_id' => (int) $row['id'],
            'series_key' => $candidate,
            'title' => sr_content_clean_single_line((string) ($row['title'] ?? '') . ' 복사본', 160),
            'episode_label' => (string) ($row['episode_label'] ?? ''),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }

    return $suggestions;
}

function sr_content_copy(PDO $pdo, int $sourceContentId, array $values, int $accountId): int
{
    $source = sr_content_by_id($pdo, $sourceContentId);
    if (!is_array($source)) {
        throw new RuntimeException('복사할 콘텐츠를 찾을 수 없습니다.');
    }

    $newTitle = sr_content_clean_single_line((string) ($values['title'] ?? ''), 160);
    $newSlug = sr_content_clean_slug((string) ($values['slug'] ?? ''));
    $errors = [];
    if ($newTitle === '') {
        $errors[] = '새 콘텐츠 제목을 입력하세요.';
    }
    if (!sr_content_slug_is_valid($newSlug)) {
        $errors[] = 'slug는 3-120자의 소문자 영문, 숫자, 하이픈만 사용할 수 있습니다.';
    } elseif (sr_content_slug_exists($pdo, $newSlug, 0)) {
        $errors[] = '이미 사용 중인 slug입니다.';
    }
    if (sr_link_card_token_rejection_errors((string) ($source['body_text'] ?? '')) !== []) {
        $errors[] = 'legacy 링크 카드 토큰이 남아 있는 콘텐츠는 복사할 수 없습니다. 본문에서 토큰을 제거한 뒤 다시 시도하세요.';
    }
    if ($errors !== []) {
        throw new InvalidArgumentException(implode("\n", $errors));
    }
    if (!empty($values['copy_series'])) {
        $seriesErrors = sr_content_copy_series_validate_options($pdo, $sourceContentId, $values);
        if ($seriesErrors !== []) {
            throw new InvalidArgumentException(implode("\n", $seriesErrors));
        }
    }

    $now = sr_now();
    $pdo->beginTransaction();
    try {
        $copy = $source;
        $copy['title'] = $newTitle;
        $copy['slug'] = $newSlug;
        $copy['status'] = 'draft';
        $copy['scheduled_publish_at'] = '';
        $copy['published_at'] = null;

        $stmt = $pdo->prepare(
            'INSERT INTO sr_content_items
                (content_group_id, slug, title, summary, body_text, body_format, status, layout_key, asset_access_enabled, asset_module, asset_access_amount, asset_access_amounts_json, asset_access_group_policies_json, asset_access_policy_set_id, asset_charge_policy, asset_action_enabled, asset_action_module, asset_action_amount, asset_action_amounts_json, asset_action_group_policies_json, asset_action_policy_set_id, asset_action_direction, asset_action_label, banner_before_content_id, banner_after_content_id, popup_layer_id, seo_title, seo_description, created_by, updated_by, published_at, created_at, updated_at)
             VALUES
                (:content_group_id, :slug, :title, :summary, :body_text, :body_format, :status, :layout_key, :asset_access_enabled, :asset_module, :asset_access_amount, :asset_access_amounts_json, :asset_access_group_policies_json, :asset_access_policy_set_id, :asset_charge_policy, :asset_action_enabled, :asset_action_module, :asset_action_amount, :asset_action_amounts_json, :asset_action_group_policies_json, :asset_action_policy_set_id, :asset_action_direction, :asset_action_label, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :seo_title, :seo_description, :created_by, :updated_by, :published_at, :created_at, :updated_at)'
        );
        $stmt->execute([
            'content_group_id' => (int) ($copy['content_group_id'] ?? 0) > 0 ? (int) $copy['content_group_id'] : null,
            'slug' => $newSlug,
            'title' => $newTitle,
            'summary' => (string) ($copy['summary'] ?? ''),
            'body_text' => (string) ($copy['body_text'] ?? ''),
            'body_format' => (string) ($copy['body_format'] ?? 'plain'),
            'status' => 'draft',
            'layout_key' => (string) ($copy['layout_key'] ?? ''),
            'asset_access_enabled' => (int) ($copy['asset_access_enabled'] ?? 0),
            'asset_module' => (string) ($copy['asset_module'] ?? ''),
            'asset_access_amount' => (int) ($copy['asset_access_amount'] ?? 0),
            'asset_access_amounts_json' => (string) ($copy['asset_access_amounts_json'] ?? '{}'),
            'asset_access_group_policies_json' => (string) ($copy['asset_access_group_policies_json'] ?? ''),
            'asset_access_policy_set_id' => (int) ($copy['asset_access_policy_set_id'] ?? 0),
            'asset_charge_policy' => (string) ($copy['asset_charge_policy'] ?? 'once'),
            'asset_action_enabled' => (int) ($copy['asset_action_enabled'] ?? 0),
            'asset_action_module' => (string) ($copy['asset_action_module'] ?? ''),
            'asset_action_amount' => (int) ($copy['asset_action_amount'] ?? 0),
            'asset_action_amounts_json' => (string) ($copy['asset_action_amounts_json'] ?? '{}'),
            'asset_action_group_policies_json' => (string) ($copy['asset_action_group_policies_json'] ?? ''),
            'asset_action_policy_set_id' => (int) ($copy['asset_action_policy_set_id'] ?? 0),
            'asset_action_direction' => (string) ($copy['asset_action_direction'] ?? 'grant'),
            'asset_action_label' => (string) ($copy['asset_action_label'] ?? '완료'),
            'banner_before_content_id' => (int) ($copy['banner_before_content_id'] ?? 0),
            'banner_after_content_id' => (int) ($copy['banner_after_content_id'] ?? 0),
            'popup_layer_id' => (int) ($copy['popup_layer_id'] ?? 0),
            'seo_title' => (string) ($copy['seo_title'] ?? ''),
            'seo_description' => (string) ($copy['seo_description'] ?? ''),
            'created_by' => $accountId,
            'updated_by' => $accountId,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $newContentId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO sr_content_setting_sources (content_id, setting_key, source, created_at, updated_at)
             SELECT :new_content_id, setting_key, source, :created_at, :updated_at
             FROM sr_content_setting_sources
             WHERE content_id = :source_content_id'
        );
        $stmt->execute([
            'new_content_id' => $newContentId,
            'created_at' => $now,
            'updated_at' => $now,
            'source_content_id' => $sourceContentId,
        ]);

        $stmt = $pdo->prepare(
            "INSERT INTO sr_content_file_links
                (content_id, file_id, sort_order, status, created_at, updated_at)
             SELECT :new_content_id, linked_files.file_id, linked_files.sort_order, 'active', :created_at, :updated_at
             FROM (
                SELECT l.file_id, l.sort_order
                FROM sr_content_file_links l
                INNER JOIN sr_content_files f ON f.id = l.file_id AND f.status = 'active'
                WHERE l.content_id = :source_link_content_id
                  AND l.status = 'active'
                UNION ALL
                SELECT f.id AS file_id, 0 AS sort_order
                FROM sr_content_files f
                WHERE f.content_id = :source_legacy_content_id
                  AND f.status = 'active'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM sr_content_file_links existing_link
                      WHERE existing_link.content_id = :source_existing_link_content_id
                        AND existing_link.file_id = f.id
                        AND existing_link.status = 'active'
                  )
             ) linked_files
             ON DUPLICATE KEY UPDATE
                sort_order = VALUES(sort_order),
                status = 'active',
                updated_at = VALUES(updated_at)"
        );
        $stmt->execute([
            'new_content_id' => $newContentId,
            'created_at' => $now,
            'updated_at' => $now,
            'source_link_content_id' => $sourceContentId,
            'source_legacy_content_id' => $sourceContentId,
            'source_existing_link_content_id' => $sourceContentId,
        ]);

        sr_content_record_revision($pdo, $newContentId, $copy, $accountId, $now);
        if (!empty($values['copy_series'])) {
            sr_content_copy_series_for_content($pdo, $sourceContentId, $newContentId, $accountId, $now);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return $newContentId;
}

function sr_content_copy_series_for_content(PDO $pdo, int $sourceContentId, int $newContentId, int $accountId, string $now): array
{
    if ($sourceContentId < 1 || $newContentId < 1 || !sr_content_series_supported($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT s.*, si.episode_label, si.item_status, si.sort_order AS item_sort_order, si.created_by AS item_created_by, si.created_at AS item_created_at, si.updated_at AS item_updated_at
         FROM sr_content_series_items si
         INNER JOIN sr_content_series s ON s.id = si.series_id
         WHERE si.active_content_id = :content_id
         ORDER BY s.id ASC'
    );
    $stmt->execute(['content_id' => $sourceContentId]);

    $created = [];
    $insertSeries = $pdo->prepare(
        'INSERT INTO sr_content_series
            (series_key, title, description, status, visibility, sort_order, created_by, updated_by, created_at, updated_at)
         VALUES
            (:series_key, :title, :description, :status, :visibility, :sort_order, :created_by, :updated_by, :created_at, :updated_at)'
    );
    $insertItem = $pdo->prepare(
        'INSERT INTO sr_content_series_items
            (series_id, content_id, active_content_id, episode_label, item_status, sort_order, created_by, created_at, updated_at)
         VALUES
            (:series_id, :content_id, :active_content_id, :episode_label, :item_status, :sort_order, :created_by, :created_at, :updated_at)'
    );

    foreach ($stmt->fetchAll() as $series) {
        $seriesKey = sr_content_copy_series_option_value($sourceContentId, (int) $series['id'], 'series_keys');
        $seriesTitle = sr_content_copy_series_option_value($sourceContentId, (int) $series['id'], 'series_titles');
        if ($seriesKey === '') {
            $baseKey = str_replace('-', '_', sr_content_clean_slug((string) ($series['series_key'] ?? 'series') . '-copy'));
            if (!sr_content_series_key_is_valid($baseKey)) {
                $baseKey = 'series_copy';
            }
            $seriesKey = $baseKey;
            $suffix = 2;
            while (sr_content_series_key_exists($pdo, $seriesKey)) {
                $seriesKey = substr($baseKey, 0, 54) . '_' . (string) $suffix;
                $suffix++;
            }
        }
        if ($seriesTitle === '') {
            $seriesTitle = sr_content_clean_single_line((string) ($series['title'] ?? '') . ' 복사본', 160);
        }

        $insertSeries->execute([
            'series_key' => $seriesKey,
            'title' => $seriesTitle,
            'description' => (string) ($series['description'] ?? ''),
            'status' => (string) ($series['status'] ?? 'active'),
            'visibility' => (string) ($series['visibility'] ?? 'public'),
            'sort_order' => (int) ($series['sort_order'] ?? 0),
            'created_by' => $accountId,
            'updated_by' => $accountId,
            'created_at' => (string) ($series['created_at'] ?? $now),
            'updated_at' => (string) ($series['updated_at'] ?? $now),
        ]);
        $newSeriesId = (int) $pdo->lastInsertId();
        $insertItem->execute([
            'series_id' => $newSeriesId,
            'content_id' => $newContentId,
            'active_content_id' => $newContentId,
            'episode_label' => (string) ($series['episode_label'] ?? ''),
            'item_status' => (string) ($series['item_status'] ?? 'active'),
            'sort_order' => (int) ($series['item_sort_order'] ?? 0),
            'created_by' => $series['item_created_by'] !== null ? (int) $series['item_created_by'] : null,
            'created_at' => (string) ($series['item_created_at'] ?? $now),
            'updated_at' => (string) ($series['item_updated_at'] ?? $now),
        ]);
        $created[] = ['source_series_id' => (int) $series['id'], 'new_series_id' => $newSeriesId];
    }

    return $created;
}

function sr_content_copy_series_option_value(int $sourceContentId, int $seriesId, string $key): string
{
    $sourceContentId = max(0, $sourceContentId);
    $seriesId = max(0, $seriesId);
    if ($sourceContentId < 1 || $seriesId < 1 || !isset($GLOBALS['sr_content_copy_series_options']) || !is_array($GLOBALS['sr_content_copy_series_options'])) {
        return '';
    }
    $options = $GLOBALS['sr_content_copy_series_options'][$sourceContentId] ?? null;
    if (!is_array($options)) {
        return '';
    }
    $values = $options[$key] ?? null;
    if (!is_array($values)) {
        return '';
    }

    return trim((string) ($values[(string) $seriesId] ?? $values[$seriesId] ?? ''));
}

function sr_content_copy_series_validate_options(PDO $pdo, int $sourceContentId, array $values): array
{
    $suggestions = sr_content_copy_series_suggestions($pdo, $sourceContentId);
    if ($suggestions === []) {
        return [];
    }

    $keys = is_array($values['series_keys'] ?? null) ? $values['series_keys'] : [];
    $titles = is_array($values['series_titles'] ?? null) ? $values['series_titles'] : [];
    $errors = [];
    $seen = [];
    foreach ($suggestions as $suggestion) {
        $seriesId = (int) $suggestion['series_id'];
        $seriesKey = strtolower(trim((string) ($keys[(string) $seriesId] ?? $keys[$seriesId] ?? $suggestion['series_key'])));
        $seriesTitle = sr_content_clean_single_line((string) ($titles[(string) $seriesId] ?? $titles[$seriesId] ?? $suggestion['title']), 160);
        if (!sr_content_series_key_is_valid($seriesKey)) {
            $errors[] = '시리즈 key는 소문자 영문, 숫자, _만 사용할 수 있습니다.';
        } elseif (isset($seen[$seriesKey]) || sr_content_series_key_exists($pdo, $seriesKey)) {
            $errors[] = '이미 사용 중인 시리즈 key입니다: ' . $seriesKey;
        }
        if ($seriesTitle === '') {
            $errors[] = '새 시리즈 제목을 입력하세요.';
        }
        $seen[$seriesKey] = true;
        $keys[(string) $seriesId] = $seriesKey;
        $titles[(string) $seriesId] = $seriesTitle;
    }

    $GLOBALS['sr_content_copy_series_options'][$sourceContentId] = [
        'series_keys' => $keys,
        'series_titles' => $titles,
    ];

    return $errors;
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
