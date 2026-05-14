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
    $siteName = is_array($site) ? trim((string) ($site['site_name'] ?? $site['name'] ?? '')) : '';

    return $siteName !== '' ? $siteName : '산란';
}

function sr_admin_shell_navigation_items(PDO $pdo, string $currentPath): array
{
    $sections = [];

    foreach (sr_admin_navigation_groups($pdo) as $group) {
        if (!is_array($group)) {
            continue;
        }

        $category = (string) ($group['category'] ?? 'other');
        $title = trim((string) ($group['label'] ?? ''));
        if ($title === '') {
            $title = sr_admin_default_menu_category_label($category);
        }

        $navGroups = sr_admin_shell_navigation_group_items($group, $currentPath);
        if ($navGroups === []) {
            continue;
        }

        $active = false;
        foreach ($navGroups as $navGroup) {
            if (!empty($navGroup['active'])) {
                $active = true;
                break;
            }
        }

        $sections[] = [
            'title' => $title,
            'icon_id' => sr_admin_shell_icon_id($category),
            'active' => $active,
            'section_class' => $active ? ' is-active' : '',
            'groups' => $navGroups,
        ];
    }

    if ($sections !== []) {
        $hasOpenItem = false;
        foreach ($sections as $section) {
            if (!empty($section['active'])) {
                $hasOpenItem = true;
                break;
            }
        }

        if (!$hasOpenItem) {
            $sections[0]['section_class'] = ' is-active';
            $sections[0]['groups'][0]['item_class'] = ' is-open';
            $sections[0]['groups'][0]['panel_class'] = '';
            $sections[0]['groups'][0]['aria_expanded'] = 'true';
        }
    }

    return $sections;
}

function sr_admin_shell_navigation_group_items(array $group, string $currentPath): array
{
    $navGroups = [];
    $moduleGroups = isset($group['module_groups']) && is_array($group['module_groups']) ? $group['module_groups'] : [];
    $category = (string) ($group['category'] ?? 'other');

    foreach ($moduleGroups as $moduleGroup) {
        if (!is_array($moduleGroup)) {
            continue;
        }

        $moduleLabel = trim((string) ($moduleGroup['label'] ?? ''));
        if ($moduleLabel === '') {
            $moduleLabel = (string) ($moduleGroup['module_key'] ?? '');
        }

        $rawItems = isset($moduleGroup['items']) && is_array($moduleGroup['items']) ? $moduleGroup['items'] : [];
        $subItems = [];

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
            $subItems[] = [
                'title' => $label,
                'path' => $path,
                'url' => sr_url($path),
                'active' => $active,
                'item_class' => $active ? ' is-current is-active' : '',
                'menu_code' => preg_replace('/[^a-z0-9_-]+/', '-', strtolower(trim($path, '/'))),
            ];
        }

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

        $navGroups[] = [
            'title' => $moduleLabel !== '' ? $moduleLabel : '메뉴',
            'icon_id' => sr_admin_shell_icon_id($category),
            'active' => $active,
            'item_class' => $active ? ' is-open is-active' : '',
            'panel_class' => $active ? '' : ' hidden',
            'aria_expanded' => $active ? 'true' : 'false',
            'menu_code' => preg_replace('/[^a-z0-9_-]+/', '-', strtolower((string) ($moduleGroup['module_key'] ?? $moduleLabel))),
            'sub_items' => $subItems,
        ];
    }

    return $navGroups;
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
    return '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>' . PHP_EOL
        . '<link rel="preload" as="style" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" crossorigin>' . PHP_EOL
        . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" crossorigin>' . PHP_EOL
        . '<link rel="stylesheet" href="' . sr_e(sr_admin_asset_url('/assets/common.css')) . '">' . PHP_EOL
        . '<link rel="stylesheet" href="' . sr_e(sr_admin_asset_url('/modules/admin/assets/admin.css')) . '">';
}

function sr_admin_shell_script_tag(): string
{
    return '<script src="' . sr_e(sr_url('/modules/admin/assets/admin-shell.js')) . '" defer></script>';
}

function sr_admin_asset_url(string $path): string
{
    $url = sr_url($path);
    $file = SR_ROOT . $path;
    if (!is_file($file)) {
        return $url;
    }

    return $url . '?v=' . rawurlencode((string) filemtime($file));
}

function sr_admin_begin_content_capture(): void
{
}

function sr_admin_flush_content_capture(): void
{
}

/**
 * @return array{0: string, 1: string}
 */
function sr_admin_choice_label_parts(string $labelText): array
{
    $labelText = trim(preg_replace('/\s+/u', ' ', $labelText) ?? $labelText);
    if ($labelText === '') {
        return ['', ''];
    }

    $suffixes = ['허용', '사용', '포함'];
    foreach ($suffixes as $suffix) {
        if (str_ends_with($labelText, $suffix)) {
            $hidden = trim(substr($labelText, 0, strlen($labelText) - strlen($suffix)));
            return [$hidden !== '' ? $hidden . ' ' : '', $suffix];
        }
    }

    if (str_ends_with($labelText, '했습니다.')) {
        return ['', '확인했습니다.'];
    }

    return ['', $labelText];
}

function sr_admin_choice_label_html(string $labelText): string
{
    [$hiddenText, $visibleText] = sr_admin_choice_label_parts($labelText);
    if ($visibleText === '') {
        return '';
    }

    $html = '';
    if ($hiddenText !== '') {
        $html .= '<span class="sr-only">' . sr_e($hiddenText) . '</span>';
    }

    return $html . sr_e($visibleText);
}
