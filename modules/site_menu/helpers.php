<?php

declare(strict_types=1);

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

function sr_site_menu_render(PDO $pdo, string $menuKey): string
{
    $menuKey = sr_site_menu_clean_key($menuKey);
    if ($menuKey === '') {
        return '';
    }

    $stmt = $pdo->prepare(
        "SELECT i.id, i.parent_id, i.label, i.url, i.target
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

    $html = '<nav class="sr-site-menu sr-site-menu-' . sr_e($menuKey) . '" aria-label="' . sr_e($menuKey) . '">';
    $html .= sr_site_menu_render_item_list($itemsByParent, 0, 1);
    $html .= '</nav>';

    return $html;
}

function sr_site_menu_render_item_list(array $itemsByParent, int $parentId, int $depth): string
{
    if ($depth > 3 || empty($itemsByParent[$parentId])) {
        return '';
    }

    $html = '<ul class="sr-site-menu-list sr-site-menu-list-depth-' . sr_e((string) $depth) . '">';
    foreach ($itemsByParent[$parentId] as $item) {
        $itemId = (int) ($item['id'] ?? 0);
        $target = (string) ($item['target'] ?? 'self');
        $targetAttribute = $target === 'blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
        $childrenHtml = $itemId > 0 ? sr_site_menu_render_item_list($itemsByParent, $itemId, $depth + 1) : '';
        $html .= '<li class="sr-site-menu-item sr-site-menu-item-depth-' . sr_e((string) $depth) . '">';
        $html .= '<a href="' . sr_e(sr_site_menu_item_href((string) $item['url'])) . '"' . $targetAttribute . '>' . sr_e((string) $item['label']) . '</a>';
        $html .= $childrenHtml;
        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}
