<?php

declare(strict_types=1);

function sr_admin_shell_view(PDO $pdo, ?array $site, string $pageTitle, string $pageSubtitle = '', string $containerClass = ''): array
{
    $currentPath = sr_request_path();
    $navigationItems = sr_admin_shell_navigation_items($pdo, $currentPath);
    $auxiliaryLinks = sr_admin_shell_auxiliary_links($currentPath);

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
        'auxiliary_links' => $auxiliaryLinks,
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
            'icon' => sr_admin_shell_menu_icon($group['admin_icon'] ?? null, $category),
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
            $openedDefaultGroup = false;
            foreach ($sections as $sectionIndex => $section) {
                foreach ((array) ($section['groups'] ?? []) as $groupIndex => $navGroup) {
                    if (empty($navGroup['has_submenu'])) {
                        continue;
                    }

                    $sections[$sectionIndex]['section_class'] = ' is-active';
                    $sections[$sectionIndex]['groups'][$groupIndex]['item_class'] = ' is-open';
                    $sections[$sectionIndex]['groups'][$groupIndex]['panel_class'] = '';
                    $sections[$sectionIndex]['groups'][$groupIndex]['aria_expanded'] = 'true';
                    $openedDefaultGroup = true;
                    break 2;
                }
            }

            if (!$openedDefaultGroup) {
                $sections[0]['section_class'] = ' is-active';
            }
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
        $activePath = sr_admin_shell_active_menu_path($currentPath, $rawItems);

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $label = trim((string) ($rawItem['label'] ?? ''));
            $path = trim((string) ($rawItem['path'] ?? ''));
            if ($label === '' || $path === '') {
                continue;
            }

            $active = $activePath !== '' && $path === $activePath;
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

        $directItem = count($subItems) === 1 ? $subItems[0] : [];
        $hasSubmenu = $directItem === [];

        $navGroups[] = [
            'title' => $moduleLabel !== '' ? $moduleLabel : '메뉴',
            'icon' => sr_admin_shell_menu_icon($moduleGroup['admin_icon'] ?? null, $category),
            'icon_id' => sr_admin_shell_icon_id($category),
            'active' => $active,
            'item_class' => $active ? ' is-open is-active' : '',
            'panel_class' => $hasSubmenu && $active ? '' : ' hidden',
            'aria_expanded' => $hasSubmenu && $active ? 'true' : 'false',
            'menu_code' => preg_replace('/[^a-z0-9_-]+/', '-', strtolower((string) ($moduleGroup['module_key'] ?? $moduleLabel))),
            'has_submenu' => $hasSubmenu,
            'direct_url' => $hasSubmenu ? '' : (string) ($directItem['url'] ?? ''),
            'direct_path' => $hasSubmenu ? '' : (string) ($directItem['path'] ?? ''),
            'sub_items' => $subItems,
        ];
    }

    return $navGroups;
}

function sr_admin_shell_active_menu_path(string $currentPath, array $rawItems): string
{
    $activePath = '';
    foreach ($rawItems as $rawItem) {
        if (!is_array($rawItem)) {
            continue;
        }

        $path = trim((string) ($rawItem['path'] ?? ''));
        if ($path === '' || !sr_admin_shell_path_matches($currentPath, $path)) {
            continue;
        }

        if ($activePath === '' || strlen($path) > strlen($activePath)) {
            $activePath = $path;
        }
    }

    return $activePath;
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

function sr_admin_shell_auxiliary_links(string $currentPath): array
{
    $pathsFile = SR_ROOT . '/modules/admin/paths.php';
    $paths = is_file($pathsFile) ? include $pathsFile : [];
    $paths = is_array($paths) ? $paths : [];

    $links = [];
    foreach ([
        ['label' => 'UI-KIT', 'path' => '/admin/ui-kit'],
    ] as $link) {
        $path = (string) ($link['path'] ?? '');
        if ($path === '' || !isset($paths['GET ' . $path])) {
            continue;
        }

        $links[] = [
            'title' => (string) ($link['label'] ?? $path),
            'path' => $path,
            'url' => sr_url($path),
            'active' => sr_admin_shell_path_matches($currentPath, $path),
        ];
    }

    return $links;
}

function sr_admin_shell_icon_id(string $category): string
{
    return sr_admin_default_menu_icon_id($category);
}

function sr_admin_shell_menu_icon(mixed $icon, string $category): array
{
    if (is_array($icon)) {
        $type = trim((string) ($icon['type'] ?? 'symbol'));
        if ($type === 'asset') {
            $url = trim((string) ($icon['url'] ?? ''));
            if ($url !== '' && sr_is_safe_relative_url($url)) {
                return [
                    'type' => 'asset',
                    'url' => $url,
                    'alt' => trim((string) ($icon['alt'] ?? '')),
                ];
            }
        }

        $symbolIcon = sr_admin_menu_symbol_icon((string) ($icon['name'] ?? ''));
        if ($symbolIcon !== []) {
            return [
                'type' => 'material',
                'name' => sr_admin_material_icon_name((string) $symbolIcon['name']),
                'symbol_name' => (string) $symbolIcon['name'],
            ];
        }
    }

    $symbolName = sr_admin_shell_icon_id($category);

    return [
        'type' => 'material',
        'name' => sr_admin_material_icon_name($symbolName),
        'symbol_name' => $symbolName,
    ];
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

function sr_admin_stylesheet_tag(?PDO $pdo = null): string
{
    $tags = [
        '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>',
        '<link rel="preload" as="style" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" crossorigin>',
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" crossorigin>',
        '<link rel="preload" as="font" type="font/ttf" href="' . sr_e(sr_material_icon_font_url()) . '" crossorigin>',
        '<link rel="stylesheet" href="' . sr_e(sr_admin_asset_url('/assets/tokens.css')) . '">',
        '<link rel="stylesheet" href="' . sr_e(sr_admin_asset_url('/assets/icons.css')) . '">',
        '<link rel="stylesheet" href="' . sr_e(sr_admin_asset_url('/modules/admin/assets/common.css')) . '">',
        '<link rel="stylesheet" href="' . sr_e(sr_admin_asset_url('/assets/admin-ui.css')) . '">',
        '<link rel="stylesheet" href="' . sr_e(sr_admin_asset_url('/modules/admin/assets/admin.css')) . '">',
    ];

    if ($pdo instanceof PDO) {
        foreach (sr_admin_module_stylesheet_paths($pdo) as $stylesheet) {
            $tags[] = '<link rel="stylesheet" href="' . sr_e(sr_admin_asset_url($stylesheet)) . '">';
        }
    }

    return implode(PHP_EOL, $tags);
}

function sr_admin_module_stylesheet_paths(PDO $pdo): array
{
    $stylesheets = [];
    foreach (sr_enabled_module_keys($pdo) as $moduleKey) {
        $admin = sr_admin_module_admin_metadata($moduleKey);
        $declared = $admin['stylesheets'] ?? [];
        if (!is_array($declared)) {
            continue;
        }

        foreach ($declared as $stylesheet) {
            $path = sr_admin_module_stylesheet_path($moduleKey, $stylesheet);
            if ($path !== '') {
                $stylesheets[$path] = $path;
            }
        }
    }

    return array_values($stylesheets);
}

function sr_admin_module_stylesheet_path(string $moduleKey, mixed $stylesheet): string
{
    if (!sr_is_safe_module_key($moduleKey) || !is_string($stylesheet)) {
        return '';
    }

    $path = str_replace('\\', '/', trim($stylesheet));
    if (preg_match('/\Aassets\/[a-zA-Z0-9_\/.-]+\.css\z/', $path) !== 1 || strpos($path, '..') !== false) {
        return '';
    }

    $assetDir = realpath(SR_ROOT . '/modules/' . $moduleKey . '/assets');
    $file = realpath(SR_ROOT . '/modules/' . $moduleKey . '/' . $path);
    if ($assetDir === false || $file === false || !is_file($file)) {
        return '';
    }

    $assetPrefix = rtrim($assetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($file, $assetPrefix)) {
        return '';
    }

    return '/modules/' . $moduleKey . '/' . $path;
}

function sr_admin_shell_script_tag(): string
{
    return '<script src="' . sr_e(sr_admin_asset_url('/assets/common-ui.js')) . '" defer></script>' . PHP_EOL
        . '<script src="' . sr_e(sr_admin_asset_url('/modules/admin/assets/admin-shell.js')) . '" defer></script>' . PHP_EOL
        . '<script src="' . sr_e(sr_admin_asset_url('/modules/admin/assets/asset-adjust.js')) . '" defer></script>';
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
