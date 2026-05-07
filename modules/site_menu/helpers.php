<?php

declare(strict_types=1);

function toy_site_menu_clean_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function toy_site_menu_clean_label(string $value, int $maxLength = 120): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function toy_site_menu_clean_url(string $value): string
{
    $value = trim($value);
    if (toy_is_safe_relative_url($value) || toy_is_http_url($value)) {
        return $value;
    }

    return '';
}

function toy_site_menu_link_suggestions(PDO $pdo): array
{
    $suggestions = [];

    foreach (toy_enabled_module_contract_files($pdo, 'menu-links.php', ['site_menu']) as $moduleKey => $file) {
        $links = include $file;
        if (!is_array($links)) {
            continue;
        }

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $label = toy_site_menu_clean_label((string) ($link['label'] ?? ''));
            $url = toy_site_menu_clean_url((string) ($link['url'] ?? ''));
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

function toy_site_menu_render(PDO $pdo, string $menuKey): string
{
    $menuKey = toy_site_menu_clean_key($menuKey);
    if ($menuKey === '') {
        return '';
    }

    $stmt = $pdo->prepare(
        "SELECT i.label, i.url, i.target
         FROM toy_site_menus m
         INNER JOIN toy_site_menu_items i ON i.menu_id = m.id
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

    $html = '<nav class="toy-site-menu toy-site-menu-' . toy_e($menuKey) . '" aria-label="' . toy_e($menuKey) . '">';
    foreach ($items as $item) {
        $target = (string) ($item['target'] ?? 'self');
        $targetAttribute = $target === 'blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
        $html .= '<a href="' . toy_e((string) $item['url']) . '"' . $targetAttribute . '>' . toy_e((string) $item['label']) . '</a>';
    }
    $html .= '</nav>';

    return $html;
}
