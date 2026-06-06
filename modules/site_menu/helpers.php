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

    return $url;
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
        $html .= '<li class="sr-site-menu-item sr-site-menu-item-depth-' . sr_e((string) $depth) . '">';
        $labelHtml = '';
        $iconName = trim((string) ($item['icon_name'] ?? ''));
        if ($iconName !== '' && sr_site_menu_icon_allowed($pdo, $iconName)) {
            $labelHtml .= sr_icon(sr_admin_icon_material_name($pdo, $iconName), 'sr-site-menu-link-icon');
        }
        $labelHtml .= '<span class="sr-site-menu-link-label">' . sr_e((string) $item['label']) . '</span>';
        $html .= '<a href="' . sr_e(sr_site_menu_item_href((string) $item['url'])) . '"' . $targetAttribute . '>' . $labelHtml . '</a>';
        $html .= $childrenHtml;
        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}
