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
            if ($label === '' || $url === '') {
                continue;
            }

            $suggestions[] = [
                'module_key' => $moduleKey,
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
        "SELECT i.label, i.url, i.target
         FROM sr_site_menus m
         INNER JOIN sr_site_menu_items i ON i.menu_id = m.id
         WHERE m.menu_key = :menu_key
           AND m.status = 'enabled'
           AND i.status = 'enabled'
         ORDER BY i.sort_order ASC, i.id ASC"
    );
    $stmt->execute(['menu_key' => $menuKey]);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = $row;
    }

    if ($items === []) {
        return '';
    }

    $html = '<nav class="sr-site-menu sr-site-menu-' . sr_e($menuKey) . '" aria-label="' . sr_e($menuKey) . '">';
    foreach ($items as $item) {
        $target = (string) ($item['target'] ?? 'self');
        $targetAttribute = $target === 'blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
        $html .= '<a href="' . sr_e(sr_site_menu_item_href((string) $item['url'])) . '"' . $targetAttribute . '>' . sr_e((string) $item['label']) . '</a>';
    }
    $html .= '</nav>';

    return $html;
}
