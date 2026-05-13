<?php

declare(strict_types=1);

function toy_admin_module_menu_items(PDO $pdo): array
{
    $items = [];
    foreach (toy_admin_module_menu_groups($pdo) as $group) {
        foreach ($group['items'] as $item) {
            $items[] = $item;
        }
    }

    usort($items, function (array $left, array $right): int {
        return [$left['order'], $left['label'], $left['path']] <=> [$right['order'], $right['label'], $right['path']];
    });

    return $items;
}

function toy_admin_navigation_groups(PDO $pdo): array
{
    $groupsByCategory = [];
    foreach (array_merge(toy_admin_builtin_menu_groups($pdo), toy_admin_module_menu_groups($pdo)) as $group) {
        if (!is_array($group)) {
            continue;
        }

        $items = isset($group['items']) && is_array($group['items']) ? $group['items'] : [];
        if ($items === []) {
            continue;
        }

        $categoryKey = toy_admin_menu_category_key($group);
        if (!isset($groupsByCategory[$categoryKey])) {
            $groupsByCategory[$categoryKey] = [
                'category' => $categoryKey,
                'label' => toy_admin_menu_category_label($group),
                'order' => toy_admin_menu_category_order($group),
                'module_groups' => [],
                'items' => [],
            ];
        } else {
            $groupsByCategory[$categoryKey]['order'] = min(
                (int) $groupsByCategory[$categoryKey]['order'],
                toy_admin_menu_category_order($group)
            );
        }

        $moduleLabel = trim((string) ($group['label'] ?? ''));
        if ($moduleLabel === '') {
            $moduleLabel = toy_admin_module_menu_group_label((string) ($group['module_key'] ?? ''), []);
        }

        $moduleGroupKey = (string) ($group['module_key'] ?? '') . '|' . $moduleLabel;
        if (!isset($groupsByCategory[$categoryKey]['module_groups'][$moduleGroupKey])) {
            $groupsByCategory[$categoryKey]['module_groups'][$moduleGroupKey] = [
                'module_key' => (string) ($group['module_key'] ?? ''),
                'label' => $moduleLabel,
                'order' => (int) ($group['order'] ?? 1000),
                'items' => [],
            ];
        } else {
            $groupsByCategory[$categoryKey]['module_groups'][$moduleGroupKey]['order'] = min(
                (int) $groupsByCategory[$categoryKey]['module_groups'][$moduleGroupKey]['order'],
                (int) ($group['order'] ?? 1000)
            );
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $path = (string) ($item['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $groupsByCategory[$categoryKey]['module_groups'][$moduleGroupKey]['items'][$path] = $item;
            $groupsByCategory[$categoryKey]['items'][$path] = $item;
        }
    }

    $groups = array_values($groupsByCategory);
    foreach ($groups as &$group) {
        $group['items'] = array_values($group['items']);
        usort($group['items'], function (array $left, array $right): int {
            return [$left['order'], $left['label'], $left['path']] <=> [$right['order'], $right['label'], $right['path']];
        });

        $moduleGroups = array_values($group['module_groups']);
        foreach ($moduleGroups as &$moduleGroup) {
            $moduleGroup['items'] = array_values($moduleGroup['items']);
            usort($moduleGroup['items'], function (array $left, array $right): int {
                return [$left['order'], $left['label'], $left['path']] <=> [$right['order'], $right['label'], $right['path']];
            });
        }
        unset($moduleGroup);

        usort($moduleGroups, function (array $left, array $right): int {
            return [$left['order'], $left['label'], $left['module_key']] <=> [$right['order'], $right['label'], $right['module_key']];
        });
        $group['module_groups'] = $moduleGroups;
    }
    unset($group);

    usort($groups, function (array $left, array $right): int {
        return [$left['order'], $left['label']] <=> [$right['order'], $right['label']];
    });

    return $groups;
}

function toy_admin_builtin_menu_groups(PDO $pdo): array
{
    $pathsFile = TOY_ROOT . '/modules/admin/paths.php';
    $paths = is_file($pathsFile) ? include $pathsFile : [];
    $paths = is_array($paths) ? $paths : [];

    $groups = [
        [
            'module_key' => 'admin',
            'label' => '관리자',
            'admin_category' => 'system',
            'admin_category_label' => '시스템',
            'admin_category_order' => 0,
            'order' => 0,
            'items' => [
                ['label' => '대시보드', 'path' => '/admin', 'order' => 10],
                ['label' => '권한', 'path' => '/admin/roles', 'order' => 20],
                ['label' => '관리자 작업 로그', 'path' => '/admin/audit-logs', 'order' => 30],
                ['label' => '설정', 'path' => '/admin/settings', 'order' => 40],
                ['label' => '모듈', 'path' => '/admin/modules', 'order' => 50],
                ['label' => '업데이트', 'path' => '/admin/updates', 'order' => 60],
                ['label' => '보관 정리', 'path' => '/admin/retention', 'order' => 70],
            ],
        ],
    ];

    foreach ($groups as &$group) {
        $items = [];
        foreach ($group['items'] as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path !== '' && isset($paths['GET ' . $path])) {
                $items[] = $item;
            }
        }

        $group['items'] = $items;
    }
    unset($group);

    return $groups;
}

function toy_admin_module_menu_groups(PDO $pdo): array
{
    $groups = [];
    $menuFiles = toy_enabled_module_contract_files($pdo, 'admin-menu.php', ['admin']);
    $pathFiles = toy_enabled_module_contract_files($pdo, 'paths.php', ['admin']);

    foreach ($menuFiles as $moduleKey => $file) {
        $menu = toy_load_module_contract_file($moduleKey, $file);
        if (!is_array($menu)) {
            continue;
        }

        $pathsFile = (string) ($pathFiles[$moduleKey] ?? '');
        $paths = $pathsFile !== '' ? toy_load_module_contract_file($moduleKey, $pathsFile) : [];
        $paths = is_array($paths) ? $paths : [];

        $rawItems = isset($menu['items']) && is_array($menu['items']) ? $menu['items'] : $menu;
        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $label = trim((string) ($rawItem['label'] ?? ''));
            $path = trim((string) ($rawItem['path'] ?? ''));
            if (
                $label === ''
                || preg_match('/\A\/admin(?:\/[a-z0-9][a-z0-9_-]*)*\z/', $path) !== 1
                || !isset($paths['GET ' . $path])
            ) {
                continue;
            }

            $items[] = [
                'module_key' => $moduleKey,
                'label' => $label,
                'path' => $path,
                'order' => (int) ($rawItem['order'] ?? 1000),
            ];
        }

        if ($items === []) {
            continue;
        }

        usort($items, function (array $left, array $right): int {
            return [$left['order'], $left['label'], $left['path']] <=> [$right['order'], $right['label'], $right['path']];
        });

        $groups[] = [
            'module_key' => $moduleKey,
            'label' => toy_admin_module_menu_group_label($moduleKey, $menu),
            'order' => toy_admin_module_menu_group_order($moduleKey, $items, $menu),
            'admin_category' => toy_admin_module_menu_category_key($moduleKey),
            'admin_category_label' => toy_admin_module_menu_category_label($moduleKey),
            'admin_category_order' => toy_admin_module_menu_category_order($moduleKey),
            'items' => $items,
        ];
    }

    usort($groups, function (array $left, array $right): int {
        return [$left['order'], $left['label'], $left['module_key']] <=> [$right['order'], $right['label'], $right['module_key']];
    });

    return $groups;
}

function toy_admin_module_menu_group_label(string $moduleKey, array $menu): string
{
    $label = '';
    if (isset($menu['items']) && is_array($menu['items'])) {
        $label = trim((string) ($menu['label'] ?? ''));
    }

    if ($label !== '') {
        return $label;
    }

    $metadata = toy_module_metadata($moduleKey);
    $name = trim((string) ($metadata['name'] ?? ''));

    return $name !== '' ? $name : $moduleKey;
}

function toy_admin_module_menu_group_order(string $moduleKey, array $items, array $menu): int
{
    $admin = toy_admin_module_admin_metadata($moduleKey);
    if (isset($admin['menu_order'])) {
        return (int) $admin['menu_order'];
    }

    if (isset($menu['items']) && is_array($menu['items']) && isset($menu['order'])) {
        return (int) $menu['order'];
    }

    $order = 1000;
    foreach ($items as $item) {
        $order = min($order, (int) ($item['order'] ?? 1000));
    }

    return $order;
}

function toy_admin_module_admin_metadata(string $moduleKey): array
{
    $metadata = toy_module_metadata($moduleKey);
    return isset($metadata['admin']) && is_array($metadata['admin']) ? $metadata['admin'] : [];
}

function toy_admin_module_menu_category_key(string $moduleKey): string
{
    $admin = toy_admin_module_admin_metadata($moduleKey);
    $category = trim((string) ($admin['category'] ?? ''));

    return preg_match('/\A[a-z0-9_]+\z/', $category) === 1 ? $category : 'other';
}

function toy_admin_module_menu_category_label(string $moduleKey): string
{
    $admin = toy_admin_module_admin_metadata($moduleKey);
    $label = trim((string) ($admin['category_label'] ?? ''));

    return $label !== '' ? $label : toy_admin_default_menu_category_label(toy_admin_module_menu_category_key($moduleKey));
}

function toy_admin_module_menu_category_order(string $moduleKey): int
{
    $admin = toy_admin_module_admin_metadata($moduleKey);

    return isset($admin['category_order']) ? (int) $admin['category_order'] : toy_admin_default_menu_category_order(toy_admin_module_menu_category_key($moduleKey));
}

function toy_admin_menu_category_key(array $group): string
{
    $category = trim((string) ($group['admin_category'] ?? ''));

    return preg_match('/\A[a-z0-9_]+\z/', $category) === 1 ? $category : 'other';
}

function toy_admin_menu_category_label(array $group): string
{
    $label = trim((string) ($group['admin_category_label'] ?? ''));

    return $label !== '' ? $label : toy_admin_default_menu_category_label(toy_admin_menu_category_key($group));
}

function toy_admin_menu_category_order(array $group): int
{
    return isset($group['admin_category_order'])
        ? (int) $group['admin_category_order']
        : toy_admin_default_menu_category_order(toy_admin_menu_category_key($group));
}

function toy_admin_default_menu_category_label(string $category): string
{
    $labels = [
        'system' => '시스템',
        'member' => '회원',
        'site' => '사이트',
        'content' => '콘텐츠',
        'operation' => '운영',
        'member_asset' => '회원 자산',
        'asset' => '자산',
        'other' => '기타',
    ];

    return (string) ($labels[$category] ?? $category);
}

function toy_admin_default_menu_category_order(string $category): int
{
    $orders = [
        'system' => 0,
        'member' => 10,
        'site' => 20,
        'content' => 30,
        'operation' => 40,
        'member_asset' => 50,
        'asset' => 60,
        'other' => 1000,
    ];

    return (int) ($orders[$category] ?? 1000);
}
