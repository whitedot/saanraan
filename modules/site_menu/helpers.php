<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/admin/helpers.php';

function sr_site_menu_clean_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function sr_site_menu_clean_label(string $value, int $maxLength = 120): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function sr_site_menu_clean_url(string $value): string
{
    $value = trim($value);
    if (sr_is_safe_relative_url($value) || sr_is_http_url($value)) {
        return $value;
    }

    return '';
}

function sr_site_menu_clean_asset_type(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{0,59}\z/', $value) === 1 ? $value : 'link';
}

function sr_site_menu_layout_slot_menu_key(string $slotKey): string
{
    $slotKey = strtolower(trim($slotKey));
    $map = [
        'navigation' => 'header',
        'primary_navigation' => 'header',
        'secondary_navigation' => 'footer',
        'tertiary_navigation' => 'utility',
        'quaternary_navigation' => '',
        'quinary_navigation' => '',
    ];

    return (string) ($map[$slotKey] ?? '');
}

function sr_site_menu_items_icon_name_column_exists(PDO $pdo): bool
{
    static $cache = [];

    $cacheKey = (string) spl_object_id($pdo);
    if (array_key_exists($cacheKey, $cache)) {
        return (bool) $cache[$cacheKey];
    }

    try {
        $stmt = $pdo->query(
            "SELECT COUNT(*) AS column_count
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'sr_site_menu_items'
               AND COLUMN_NAME = 'icon_name'"
        );
        $row = $stmt->fetch();
        $cache[$cacheKey] = is_array($row) && (int) ($row['column_count'] ?? 0) > 0;
    } catch (Throwable $exception) {
        $cache[$cacheKey] = false;
    }

    return (bool) $cache[$cacheKey];
}

function sr_site_menu_icon_allowed(PDO $pdo, string $name): bool
{
    $name = trim($name);
    if (sr_admin_menu_symbol_allowed($name)) {
        return true;
    }

    $custom = sr_admin_icon_custom_map($pdo)[$name] ?? null;
    return is_array($custom) && (string) ($custom['type'] ?? 'material') === 'material';
}

function sr_site_menu_icon_options(PDO $pdo): array
{
    $allowed = sr_admin_allowed_menu_symbol_icons();
    foreach (sr_admin_icon_custom_map($pdo) as $name => $custom) {
        $name = trim((string) $name);
        if (sr_admin_custom_icon_key_is_valid($name) && is_array($custom) && (string) ($custom['type'] ?? 'material') === 'material') {
            $allowed[$name] = true;
        }
    }
    ksort($allowed, SORT_STRING);

    return $allowed;
}

function sr_site_menu_clean_icon_name(PDO $pdo, string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return sr_site_menu_icon_allowed($pdo, $value) ? $value : '';
}

function sr_site_menu_options(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT menu_key, label, status FROM sr_site_menus ORDER BY menu_key ASC');
    } catch (Throwable $exception) {
        return [];
    }

    $menus = [];
    foreach ($stmt->fetchAll() as $row) {
        $menuKey = sr_site_menu_clean_key((string) ($row['menu_key'] ?? ''));
        if ($menuKey === '') {
            continue;
        }

        $menus[$menuKey] = [
            'menu_key' => $menuKey,
            'label' => sr_site_menu_clean_label((string) ($row['label'] ?? $menuKey)),
            'status' => (string) ($row['status'] ?? 'enabled'),
        ];
    }

    return $menus;
}

function sr_site_menu_seed_default_header_menu_items(array $mainPageOptionsByModule, array $enabledModuleKeys): array
{
    $candidates = [];
    $seenModuleKeys = [];
    foreach (array_values($enabledModuleKeys) as $enabledOrder => $moduleKey) {
        $moduleKey = (string) $moduleKey;
        if ($moduleKey === '' || isset($seenModuleKeys[$moduleKey])) {
            continue;
        }
        $seenModuleKeys[$moduleKey] = true;

        $option = is_array($mainPageOptionsByModule[$moduleKey] ?? null) ? $mainPageOptionsByModule[$moduleKey] : null;
        if ($option === null) {
            continue;
        }

        $label = sr_site_menu_clean_label((string) ($option['label'] ?? ''));
        $url = sr_site_menu_clean_url((string) ($option['path'] ?? ''));
        if ($label === '' || $url === '' || $url === '/') {
            continue;
        }

        $metadata = sr_module_metadata($moduleKey);
        $adminMetadata = is_array($metadata['admin'] ?? null) ? $metadata['admin'] : [];
        $candidates[] = [
            'label' => $label,
            'url' => $url,
            '_category_order' => (int) ($adminMetadata['category_order'] ?? 999),
            '_menu_order' => (int) ($adminMetadata['menu_order'] ?? 999),
            '_enabled_order' => (int) $enabledOrder,
        ];
    }

    usort($candidates, static function (array $left, array $right): int {
        return [
            (int) ($left['_category_order'] ?? 999),
            (int) ($left['_menu_order'] ?? 999),
            (string) ($left['label'] ?? ''),
            (string) ($left['url'] ?? ''),
            (int) ($left['_enabled_order'] ?? 999),
        ] <=> [
            (int) ($right['_category_order'] ?? 999),
            (int) ($right['_menu_order'] ?? 999),
            (string) ($right['label'] ?? ''),
            (string) ($right['url'] ?? ''),
            (int) ($right['_enabled_order'] ?? 999),
        ];
    });

    $items = [
        [
            'label' => '홈',
            'url' => '/',
            'sort_order' => 10,
        ],
    ];
    $sortOrder = 20;
    foreach ($candidates as $candidate) {
        $items[] = [
            'label' => (string) $candidate['label'],
            'url' => (string) $candidate['url'],
            'sort_order' => $sortOrder,
        ];
        $sortOrder += 10;
    }

    return $items;
}

function sr_site_menu_seed_default_header_menu(PDO $pdo, array $mainPageOptionsByModule, array $enabledModuleKeys): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_site_menus (menu_key, label, status, created_at, updated_at)
         VALUES (\'header\', \'상단 메뉴\', \'enabled\', :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE updated_at = updated_at'
    );
    $stmt->execute([
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $stmt = $pdo->prepare('SELECT id FROM sr_site_menus WHERE menu_key = \'header\' LIMIT 1');
    $stmt->execute();
    $menuId = (int) $stmt->fetchColumn();
    if ($menuId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_site_menu_items WHERE menu_id = :menu_id');
    $stmt->execute(['menu_id' => $menuId]);
    if ((int) $stmt->fetchColumn() > 0) {
        return 0;
    }

    $items = sr_site_menu_seed_default_header_menu_items($mainPageOptionsByModule, $enabledModuleKeys);

    $insert = $pdo->prepare(
        'INSERT INTO sr_site_menu_items
            (menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at)
         VALUES
            (:menu_id, NULL, :label, :url, \'self\', \'enabled\', :sort_order, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE updated_at = updated_at'
    );
    $created = 0;
    foreach ($items as $item) {
        $insert->execute([
            'menu_id' => $menuId,
            'label' => (string) $item['label'],
            'url' => (string) $item['url'],
            'sort_order' => (int) $item['sort_order'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $created += $insert->rowCount() > 0 ? 1 : 0;
    }

    return $created;
}

function sr_site_menu_link_suggestions(PDO $pdo): array
{
    $suggestions = [];

    foreach (sr_enabled_module_contract_files($pdo, 'menu-links.php', ['site_menu']) as $moduleKey => $file) {
        $links = sr_load_module_contract_file($moduleKey, $file);
        if (is_callable($links)) {
            $links = $links($pdo);
        }

        if (!is_array($links)) {
            continue;
        }

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $label = sr_site_menu_clean_label((string) ($link['label'] ?? ''));
            $url = sr_site_menu_clean_url((string) ($link['url'] ?? ''));
            $assetType = sr_site_menu_clean_asset_type((string) ($link['asset_type'] ?? $link['type'] ?? 'link'));
            $assetTypeLabel = sr_site_menu_clean_label((string) ($link['asset_type_label'] ?? $link['type_label'] ?? ''), 60);
            if ($assetTypeLabel === '') {
                $assetTypeLabel = '링크';
            }
            if ($label === '' || $url === '') {
                continue;
            }

            $suggestions[] = [
                'module_key' => $moduleKey,
                'asset_type' => $assetType,
                'asset_type_label' => $assetTypeLabel,
                'label' => $label,
                'url' => $url,
            ];
        }
    }

    return $suggestions;
}

function sr_site_menu_item_href(string $url): string
{
    if (sr_is_safe_relative_url($url)) {
        if ($url === '/login') {
            $next = sr_site_menu_current_login_next_path();
            if ($next !== '') {
                return sr_url('/login?next=' . rawurlencode($next));
            }
        }

        return sr_url($url);
    }

    return sr_is_http_url($url) ? $url : '#';
}

function sr_site_menu_current_login_next_path(): string
{
    $path = sr_request_path();
    if (in_array($path, ['/', '/login', '/logout', '/register', '/password/reset', '/password/reset/confirm'], true)) {
        return '';
    }

    $query = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        $path .= '?' . $query;
    }

    return sr_is_safe_relative_url($path) ? $path : '';
}

function sr_site_menu_normalize_path(string $path): string
{
    $path = '/' . trim($path, '/');
    return $path === '/' ? '/' : rtrim($path, '/');
}

function sr_site_menu_relative_url_parts(string $url): ?array
{
    if (!sr_is_safe_relative_url($url)) {
        return null;
    }

    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }

    $basePath = sr_base_path();
    $path = sr_site_menu_normalize_path($path);
    if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
        $path = substr($path, strlen($basePath));
        $path = is_string($path) && $path !== '' ? $path : '/';
    }

    $query = parse_url($url, PHP_URL_QUERY);

    return [
        'path' => sr_site_menu_normalize_path($path),
        'query' => is_string($query) ? $query : '',
    ];
}

function sr_site_menu_current_url_parts(): array
{
    static $parts = null;
    if (is_array($parts)) {
        return $parts;
    }

    $query = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    $parts = [
        'path' => sr_site_menu_normalize_path(sr_request_path()),
        'query' => is_string($query) ? $query : '',
    ];

    return $parts;
}

function sr_site_menu_query_contains(string $currentQuery, string $targetQuery): bool
{
    if ($targetQuery === '') {
        return true;
    }

    parse_str($targetQuery, $targetParams);
    parse_str($currentQuery, $currentParams);
    if (!is_array($targetParams) || $targetParams === [] || !is_array($currentParams)) {
        return $targetQuery === $currentQuery;
    }

    foreach ($targetParams as $key => $value) {
        if (!array_key_exists((string) $key, $currentParams) || $currentParams[(string) $key] != $value) {
            return false;
        }
    }

    return true;
}

function sr_site_menu_current_community_board_key(?PDO $pdo): string
{
    $current = sr_site_menu_current_url_parts();
    $path = (string) $current['path'];
    parse_str((string) $current['query'], $queryParams);

    if (in_array($path, ['/community/board', '/community/write'], true)) {
        $boardKey = is_array($queryParams) ? (string) ($queryParams['key'] ?? '') : '';
        return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $boardKey) === 1 ? $boardKey : '';
    }

    if (!in_array($path, ['/community/post', '/community/edit'], true) || !($pdo instanceof PDO)) {
        return '';
    }

    $postId = is_array($queryParams) ? (int) ($queryParams['id'] ?? 0) : 0;
    if ($postId <= 0) {
        return '';
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT b.board_key
             FROM sr_community_posts p
             INNER JOIN sr_community_boards b ON b.id = p.board_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $postId]);
        $row = $stmt->fetch();
    } catch (Throwable) {
        return '';
    }

    $boardKey = is_array($row) ? (string) ($row['board_key'] ?? '') : '';
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $boardKey) === 1 ? $boardKey : '';
}

function sr_site_menu_item_matches_current_community_board(?PDO $pdo, array $target): bool
{
    if ((string) ($target['path'] ?? '') !== '/community/board') {
        return false;
    }

    parse_str((string) ($target['query'] ?? ''), $targetParams);
    $targetBoardKey = is_array($targetParams) ? (string) ($targetParams['key'] ?? '') : '';
    if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $targetBoardKey) !== 1) {
        return false;
    }

    return $targetBoardKey === sr_site_menu_current_community_board_key($pdo);
}

function sr_site_menu_item_is_current(string $url, ?PDO $pdo = null): bool
{
    $target = sr_site_menu_relative_url_parts($url);
    if ($target === null) {
        return false;
    }

    $current = sr_site_menu_current_url_parts();
    if ((string) $target['path'] !== (string) $current['path']) {
        return sr_site_menu_item_matches_current_community_board($pdo, $target);
    }

    return sr_site_menu_query_contains((string) $current['query'], (string) $target['query']);
}

function sr_site_menu_render(PDO $pdo, string $menuKey, string $layoutSlotKey = ''): string
{
    $menuKey = sr_site_menu_clean_key($menuKey);
    if ($menuKey === '') {
        return '';
    }

    $iconNameSelect = sr_site_menu_items_icon_name_column_exists($pdo) ? 'i.icon_name' : "'' AS icon_name";
    $stmt = $pdo->prepare(
        "SELECT i.id, i.parent_id, i.label, i.url, " . $iconNameSelect . ", i.target
         FROM sr_site_menus m
         INNER JOIN sr_site_menu_items i ON i.menu_id = m.id
         WHERE m.menu_key = :menu_key
           AND m.status = 'enabled'
           AND i.status = 'enabled'
         ORDER BY i.sort_order ASC, i.id ASC"
    );
    $stmt->execute(['menu_key' => $menuKey]);

    $items = [];
    $itemsByParent = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = $row;
        $parentId = (int) ($row['parent_id'] ?? 0);
        $itemsByParent[$parentId][] = $row;
    }

    if ($items === []) {
        return '';
    }

    $slotClass = $layoutSlotKey !== '' && preg_match('/\A[a-z0-9_]{1,80}\z/', $layoutSlotKey) === 1
        ? ' sr-site-menu-slot-' . str_replace('_', '-', sr_e($layoutSlotKey))
        : '';
    $html = '<nav class="sr-site-menu sr-site-menu-' . sr_e($menuKey) . $slotClass . '" aria-label="' . sr_e($menuKey) . '">';
    $html .= sr_site_menu_render_item_list($pdo, $itemsByParent, 0, 1);
    $html .= '</nav>';

    return $html;
}

function sr_site_menu_render_item_list(PDO $pdo, array $itemsByParent, int $parentId, int $depth): string
{
    if ($depth > 3 || empty($itemsByParent[$parentId])) {
        return '';
    }

    $html = '<ul class="sr-site-menu-list sr-site-menu-list-depth-' . sr_e((string) $depth) . '">';
    foreach ($itemsByParent[$parentId] as $item) {
        $itemId = (int) ($item['id'] ?? 0);
        $target = (string) ($item['target'] ?? 'self');
        $targetAttribute = $target === 'blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
        $childrenHtml = $itemId > 0 ? sr_site_menu_render_item_list($pdo, $itemsByParent, $itemId, $depth + 1) : '';
        $isCurrent = sr_site_menu_item_is_current((string) ($item['url'] ?? ''), $pdo);
        $hasCurrentChild = $childrenHtml !== '' && strpos($childrenHtml, 'aria-current="page"') !== false;
        $itemClass = 'sr-site-menu-item sr-site-menu-item-depth-' . (string) $depth;
        if ($isCurrent) {
            $itemClass .= ' is-current';
        } elseif ($hasCurrentChild) {
            $itemClass .= ' is-current-ancestor';
        }
        $currentAttribute = $isCurrent ? ' aria-current="page"' : '';
        $html .= '<li class="' . sr_e($itemClass) . '">';
        $labelHtml = '';
        $iconName = trim((string) ($item['icon_name'] ?? ''));
        if ($iconName !== '' && sr_site_menu_icon_allowed($pdo, $iconName)) {
            $labelHtml .= sr_icon(sr_admin_icon_material_name($pdo, $iconName), 'sr-site-menu-link-icon');
        }
        $labelHtml .= '<span class="sr-site-menu-link-label">' . sr_e((string) $item['label']) . '</span>';
        $html .= '<a href="' . sr_e(sr_site_menu_item_href((string) $item['url'])) . '"' . $targetAttribute . $currentAttribute . '>' . $labelHtml . '</a>';
        $html .= $childrenHtml;
        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}
