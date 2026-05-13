<?php

declare(strict_types=1);

function sr_admin_shell_view(PDO $pdo, ?array $site, string $pageTitle, string $pageSubtitle = '', string $containerClass = ''): array
{
    $currentPath = sr_request_path();
    $navigationItems = sr_admin_shell_navigation_items($pdo, $currentPath);

    return [
        'site_title' => sr_admin_shell_site_title($site),
        'page_title' => $pageTitle !== '' ? $pageTitle : '관리자',
        'page_subtitle' => $pageSubtitle,
        'container_class' => sr_admin_shell_class_attr($containerClass),
        'dashboard_url' => sr_url('/admin'),
        'site_home_url' => sr_url('/'),
        'profile_url' => sr_url('/account'),
        'logout_url' => sr_url('/logout'),
        'navigation_items' => $navigationItems,
    ];
}

function sr_admin_shell_site_title(?array $site): string
{
    $siteName = is_array($site) ? trim((string) ($site['site_name'] ?? '')) : '';

    return $siteName !== '' ? $siteName : '산란';
}

function sr_admin_shell_navigation_items(PDO $pdo, string $currentPath): array
{
    $items = [];

    foreach (sr_admin_navigation_groups($pdo) as $group) {
        if (!is_array($group)) {
            continue;
        }

        $category = (string) ($group['category'] ?? 'other');
        $title = trim((string) ($group['label'] ?? ''));
        if ($title === '') {
            $title = sr_admin_default_menu_category_label($category);
        }

        $subItems = sr_admin_shell_navigation_sub_items($group, $currentPath);
        if ($subItems === []) {
            continue;
        }

        $active = false;
        foreach ($subItems as $subItem) {
            if (!empty($subItem['active'])) {
                $active = true;
                break;
            }
        }

        $items[] = [
            'title' => $title,
            'icon_id' => sr_admin_shell_icon_id($category),
            'active' => $active,
            'item_class' => $active ? ' is-open is-active' : '',
            'panel_class' => $active ? '' : ' hidden',
            'aria_expanded' => $active ? 'true' : 'false',
            'sub_items' => $subItems,
        ];
    }

    if ($items !== []) {
        $hasOpenItem = false;
        foreach ($items as $item) {
            if (!empty($item['active'])) {
                $hasOpenItem = true;
                break;
            }
        }

        if (!$hasOpenItem) {
            $items[0]['item_class'] = ' is-open';
            $items[0]['panel_class'] = '';
            $items[0]['aria_expanded'] = 'true';
        }
    }

    return $items;
}

function sr_admin_shell_navigation_sub_items(array $group, string $currentPath): array
{
    $subItems = [];
    $moduleGroups = isset($group['module_groups']) && is_array($group['module_groups']) ? $group['module_groups'] : [];
    $showModuleLabel = count($moduleGroups) > 1;

    foreach ($moduleGroups as $moduleGroup) {
        if (!is_array($moduleGroup)) {
            continue;
        }

        $moduleLabel = trim((string) ($moduleGroup['label'] ?? ''));
        $rawItems = isset($moduleGroup['items']) && is_array($moduleGroup['items']) ? $moduleGroup['items'] : [];

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $label = trim((string) ($rawItem['label'] ?? ''));
            $path = trim((string) ($rawItem['path'] ?? ''));
            if ($label === '' || $path === '') {
                continue;
            }

            $active = sr_admin_shell_path_matches($currentPath, $path);
            $title = $showModuleLabel && $moduleLabel !== '' ? $moduleLabel . ' / ' . $label : $label;

            $subItems[] = [
                'title' => $title,
                'path' => $path,
                'url' => sr_url($path),
                'active' => $active,
                'item_class' => $active ? ' is-active' : '',
                'menu_code' => preg_replace('/[^a-z0-9_-]+/', '-', strtolower(trim($path, '/'))),
            ];
        }
    }

    return $subItems;
}

function sr_admin_shell_path_matches(string $currentPath, string $itemPath): bool
{
    if ($currentPath === $itemPath) {
        return true;
    }

    if ($itemPath === '/admin') {
        return false;
    }

    return str_starts_with($currentPath, rtrim($itemPath, '/') . '/');
}

function sr_admin_shell_icon_id(string $category): string
{
    $icons = [
        'system' => 'settings',
        'member' => 'users',
        'site' => 'content',
        'content' => 'content',
        'operation' => 'stats',
        'member_asset' => 'folder',
        'asset' => 'folder',
        'other' => 'folder',
    ];

    return (string) ($icons[$category] ?? 'folder');
}

function sr_admin_shell_class_attr(string $class): string
{
    $tokens = [];
    foreach (preg_split('/\s+/', trim($class)) ?: [] as $token) {
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $token) === 1) {
            $tokens[] = $token;
        }
    }

    return implode(' ', $tokens);
}

function sr_admin_stylesheet_tag(): string
{
    return '<link rel="stylesheet" href="' . sr_e(sr_url('/modules/admin/assets/admin.css')) . '">';
}

function sr_admin_shell_script_tag(): string
{
    return '<script src="' . sr_e(sr_url('/modules/admin/assets/admin-shell.js')) . '" defer></script>';
}
