<?php

declare(strict_types=1);

function sr_admin_module_menu_items(PDO $pdo): array
{
    $items = [];
    foreach (sr_admin_module_menu_groups($pdo) as $group) {
        foreach ($group['items'] as $item) {
            $items[] = $item;
        }
    }

    usort($items, function (array $left, array $right): int {
        return [$left['order'], $left['label'], $left['path']] <=> [$right['order'], $right['label'], $right['path']];
    });

    return $items;
}

function sr_admin_navigation_groups(PDO $pdo): array
{
    return sr_admin_apply_menu_overrides($pdo, sr_admin_navigation_source_groups($pdo));
}

function sr_admin_navigation_source_groups(PDO $pdo): array
{
    $groupsByCategory = [];
    foreach (array_merge(sr_admin_builtin_menu_groups($pdo), sr_admin_module_menu_groups($pdo)) as $group) {
        if (!is_array($group)) {
            continue;
        }

        $items = isset($group['items']) && is_array($group['items']) ? $group['items'] : [];
        if ($items === []) {
            continue;
        }

        $categoryKey = sr_admin_menu_category_key($group);
        if (!isset($groupsByCategory[$categoryKey])) {
            $groupsByCategory[$categoryKey] = [
                'category' => $categoryKey,
                'label' => sr_admin_menu_category_label($group),
                'order' => sr_admin_menu_category_order($group),
                'module_groups' => [],
                'items' => [],
            ];
        } else {
            $groupsByCategory[$categoryKey]['order'] = min(
                (int) $groupsByCategory[$categoryKey]['order'],
                sr_admin_menu_category_order($group)
            );
        }

        $moduleLabel = trim((string) ($group['label'] ?? ''));
        if ($moduleLabel === '') {
            $moduleLabel = sr_admin_module_menu_group_label((string) ($group['module_key'] ?? ''), []);
        }

        $moduleGroupKey = (string) ($group['module_key'] ?? '') . '|' . $moduleLabel;
        if (!isset($groupsByCategory[$categoryKey]['module_groups'][$moduleGroupKey])) {
            $groupsByCategory[$categoryKey]['module_groups'][$moduleGroupKey] = [
                'module_key' => (string) ($group['module_key'] ?? ''),
                'label' => $moduleLabel,
                'order' => (int) ($group['order'] ?? 1000),
                'admin_icon' => isset($group['admin_icon']) && is_array($group['admin_icon']) ? $group['admin_icon'] : [],
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

function sr_admin_builtin_menu_groups(PDO $pdo): array
{
    $pathsFile = SR_ROOT . '/modules/admin/paths.php';
    $paths = is_file($pathsFile) ? include $pathsFile : [];
    $paths = is_array($paths) ? $paths : [];

    $groups = [
        [
            'module_key' => 'admin',
            'label' => sr_t('admin::nav.admin'),
            'admin_category' => 'system',
            'admin_category_label' => sr_t('admin::nav.category.system'),
            'admin_category_order' => 0,
            'admin_icon' => ['type' => 'symbol', 'name' => 'settings'],
            'order' => 0,
            'items' => [
                ['label' => sr_t('admin::nav.dashboard'), 'path' => '/admin', 'order' => 10],
                ['label' => sr_t('admin::nav.settings'), 'path' => '/admin/settings', 'order' => 20],
                ['label' => sr_t('admin::nav.menu'), 'path' => '/admin/menu', 'order' => 30],
                ['label' => sr_t('admin::nav.modules'), 'path' => '/admin/modules', 'order' => 40],
                ['label' => sr_t('admin::nav.updates'), 'path' => '/admin/updates', 'order' => 50],
                ['label' => sr_t('admin::nav.roles'), 'path' => '/admin/roles', 'order' => 60],
                ['label' => sr_t('admin::nav.audit_logs'), 'path' => '/admin/audit-logs', 'order' => 70],
                ['label' => sr_t('admin::nav.retention'), 'path' => '/admin/retention', 'order' => 80],
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

function sr_admin_module_menu_groups(PDO $pdo): array
{
    $groups = [];
    $menuFiles = sr_enabled_module_contract_files($pdo, 'admin-menu.php', ['admin']);
    $pathFiles = sr_enabled_module_contract_files($pdo, 'paths.php', ['admin']);

    foreach ($menuFiles as $moduleKey => $file) {
        $menu = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($menu)) {
            continue;
        }

        $pathsFile = (string) ($pathFiles[$moduleKey] ?? '');
        $paths = $pathsFile !== '' ? sr_load_module_contract_file($moduleKey, $pathsFile) : [];
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

        $categoryKey = sr_admin_module_menu_category_key($moduleKey);
        $groups[] = [
            'module_key' => $moduleKey,
            'label' => sr_admin_module_menu_group_label($moduleKey, $menu),
            'order' => sr_admin_module_menu_group_order($moduleKey, $items, $menu),
            'admin_category' => $categoryKey,
            'admin_category_label' => sr_admin_module_menu_category_label($moduleKey),
            'admin_category_order' => sr_admin_module_menu_category_order($moduleKey),
            'admin_icon' => sr_admin_module_menu_icon($moduleKey, $categoryKey),
            'items' => $items,
        ];
    }

    usort($groups, function (array $left, array $right): int {
        return [$left['order'], $left['label'], $left['module_key']] <=> [$right['order'], $right['label'], $right['module_key']];
    });

    return $groups;
}

function sr_admin_module_menu_group_label(string $moduleKey, array $menu): string
{
    $label = '';
    if (isset($menu['items']) && is_array($menu['items'])) {
        $label = trim((string) ($menu['label'] ?? ''));
    }

    if ($label !== '') {
        return $label;
    }

    $metadata = sr_module_metadata($moduleKey);
    $name = trim((string) ($metadata['name'] ?? ''));

    return $name !== '' ? $name : $moduleKey;
}

function sr_admin_module_menu_group_order(string $moduleKey, array $items, array $menu): int
{
    $admin = sr_admin_module_admin_metadata($moduleKey);
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

function sr_admin_module_admin_metadata(string $moduleKey): array
{
    $metadata = sr_module_metadata($moduleKey);
    return isset($metadata['admin']) && is_array($metadata['admin']) ? $metadata['admin'] : [];
}

function sr_admin_module_menu_category_key(string $moduleKey): string
{
    $admin = sr_admin_module_admin_metadata($moduleKey);
    $category = trim((string) ($admin['category'] ?? ''));

    return preg_match('/\A[a-z0-9_]+\z/', $category) === 1 ? $category : 'other';
}

function sr_admin_module_menu_category_label(string $moduleKey): string
{
    $admin = sr_admin_module_admin_metadata($moduleKey);
    $label = trim((string) ($admin['category_label'] ?? ''));

    return $label !== '' ? $label : sr_admin_default_menu_category_label(sr_admin_module_menu_category_key($moduleKey));
}

function sr_admin_module_menu_category_order(string $moduleKey): int
{
    $admin = sr_admin_module_admin_metadata($moduleKey);

    return isset($admin['category_order']) ? (int) $admin['category_order'] : sr_admin_default_menu_category_order(sr_admin_module_menu_category_key($moduleKey));
}

function sr_admin_module_menu_icon(string $moduleKey, string $category): array
{
    $admin = sr_admin_module_admin_metadata($moduleKey);
    $icon = $admin['icon'] ?? null;

    if (is_string($icon)) {
        return sr_admin_menu_symbol_icon($icon) ?: sr_admin_default_menu_icon($category);
    }

    if (!is_array($icon)) {
        return sr_admin_default_menu_icon($category);
    }

    $type = trim((string) ($icon['type'] ?? 'symbol'));
    if ($type === 'asset') {
        $assetIcon = sr_admin_module_menu_asset_icon($moduleKey, $icon);
        return $assetIcon !== [] ? $assetIcon : sr_admin_default_menu_icon($category);
    }

    $name = trim((string) ($icon['name'] ?? $icon['symbol'] ?? ''));
    return sr_admin_menu_symbol_icon($name) ?: sr_admin_default_menu_icon($category);
}

function sr_admin_module_menu_asset_icon(string $moduleKey, array $icon): array
{
    if (preg_match('/\A[a-z0-9_]+\z/', $moduleKey) !== 1) {
        return [];
    }

    $path = str_replace('\\', '/', trim((string) ($icon['path'] ?? '')));
    if (preg_match('/\Aassets\/[a-zA-Z0-9_\/.-]+\.(png|webp)\z/i', $path) !== 1 || strpos($path, '..') !== false) {
        return [];
    }

    $assetDir = realpath(SR_ROOT . '/modules/' . $moduleKey . '/assets');
    $file = realpath(SR_ROOT . '/modules/' . $moduleKey . '/' . $path);
    if ($assetDir === false || $file === false || !is_file($file)) {
        return [];
    }

    $assetPrefix = rtrim($assetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($file, $assetPrefix)) {
        return [];
    }

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($extension, ['png', 'webp'], true)) {
        return [];
    }

    $url = sr_url('/modules/' . $moduleKey . '/' . $path);
    return [
        'type' => 'asset',
        'path' => $path,
        'url' => $url . '?v=' . rawurlencode((string) filemtime($file)),
        'alt' => trim((string) ($icon['alt'] ?? '')),
    ];
}

function sr_admin_menu_category_key(array $group): string
{
    $category = trim((string) ($group['admin_category'] ?? ''));

    return preg_match('/\A[a-z0-9_]+\z/', $category) === 1 ? $category : 'other';
}

function sr_admin_menu_category_label(array $group): string
{
    $label = trim((string) ($group['admin_category_label'] ?? ''));

    return $label !== '' ? $label : sr_admin_default_menu_category_label(sr_admin_menu_category_key($group));
}

function sr_admin_menu_category_order(array $group): int
{
    return isset($group['admin_category_order'])
        ? (int) $group['admin_category_order']
        : sr_admin_default_menu_category_order(sr_admin_menu_category_key($group));
}

function sr_admin_default_menu_category_label(string $category): string
{
    $labels = [
        'system' => '시스템',
        'member' => '회원',
        'site' => '사이트',
        'system_asset' => '사이트',
        'content' => '사이트',
        'operation' => '운영',
        'other' => '기타',
    ];

    return (string) ($labels[$category] ?? $category);
}

function sr_admin_default_menu_category_order(string $category): int
{
    $orders = [
        'system' => 0,
        'member' => 10,
        'site' => 20,
        'system_asset' => 20,
        'content' => 20,
        'operation' => 40,
        'other' => 1000,
    ];

    return (int) ($orders[$category] ?? 1000);
}

function sr_admin_menu_overrides(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT scope, target_key, sort_order, is_hidden FROM sr_admin_menu_overrides');
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '42S02') {
            return [];
        }

        throw $exception;
    }

    $overrides = [];
    foreach ($stmt->fetchAll() as $row) {
        $scope = (string) ($row['scope'] ?? '');
        $targetKey = (string) ($row['target_key'] ?? '');
        if (!in_array($scope, ['category', 'group', 'item'], true) || $targetKey === '') {
            continue;
        }

        $sortOrder = (int) ($row['sort_order'] ?? 1000);
        $isHidden = !empty($row['is_hidden']);
        if (sr_admin_menu_override_is_stale_default($scope, $targetKey, $sortOrder, $isHidden)) {
            continue;
        }
        if (!sr_admin_menu_target_can_hide($scope, $targetKey)) {
            $isHidden = false;
        }

        $overrides[$scope][$targetKey] = [
            'sort_order' => $sortOrder,
            'is_hidden' => $isHidden,
        ];
    }

    return $overrides;
}

function sr_admin_menu_override_is_stale_default(string $scope, string $targetKey, int $sortOrder, bool $isHidden): bool
{
    if ($isHidden || $scope !== 'group') {
        return false;
    }

    $legacyDefaults = [
        'point' => [30],
        'reward' => [40, 50],
        'deposit' => [30, 40],
        'page' => [10, 20, 30],
        'site_menu' => [20, 60],
        'banner' => [20, 70],
        'popup_layer' => [30, 80],
        'seo' => [40, 90],
    ];

    return in_array($sortOrder, $legacyDefaults[$targetKey] ?? [], true);
}

function sr_admin_menu_target_can_hide(string $scope, string $targetKey): bool
{
    $protectedTargets = [
        'category' => [
            'system' => true,
        ],
        'group' => [
            'admin' => true,
        ],
        'item' => [
            sr_admin_menu_item_target_key('admin', '/admin/menu') => true,
        ],
    ];

    return empty($protectedTargets[$scope][$targetKey]);
}

function sr_admin_apply_menu_overrides(PDO $pdo, array $groups): array
{
    $overrides = sr_admin_menu_overrides($pdo);
    $visibleGroups = [];

    foreach ($groups as $group) {
        if (!is_array($group)) {
            continue;
        }

        $categoryKey = (string) ($group['category'] ?? '');
        $categoryOverride = $overrides['category'][$categoryKey] ?? null;
        if (is_array($categoryOverride)) {
            if (!empty($categoryOverride['is_hidden'])) {
                continue;
            }

            $group['order'] = (int) $categoryOverride['sort_order'];
        }

        $moduleGroups = [];
        foreach ((array) ($group['module_groups'] ?? []) as $moduleGroup) {
            if (!is_array($moduleGroup)) {
                continue;
            }

            $moduleKey = (string) ($moduleGroup['module_key'] ?? '');
            $groupOverride = $overrides['group'][$moduleKey] ?? null;
            if (is_array($groupOverride)) {
                if (!empty($groupOverride['is_hidden'])) {
                    continue;
                }

                $moduleGroup['order'] = (int) $groupOverride['sort_order'];
            }

            $items = [];
            foreach ((array) ($moduleGroup['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $path = (string) ($item['path'] ?? '');
                $itemKey = sr_admin_menu_item_target_key($moduleKey, $path);
                $itemOverride = $overrides['item'][$itemKey] ?? null;
                if (is_array($itemOverride)) {
                    if (!empty($itemOverride['is_hidden'])) {
                        continue;
                    }

                    $item['order'] = (int) $itemOverride['sort_order'];
                }

                $items[] = $item;
            }

            if ($items === []) {
                continue;
            }

            usort($items, function (array $left, array $right): int {
                return [$left['order'], $left['label'], $left['path']] <=> [$right['order'], $right['label'], $right['path']];
            });
            $moduleGroup['items'] = $items;
            $moduleGroups[] = $moduleGroup;
        }

        if ($moduleGroups === []) {
            continue;
        }

        usort($moduleGroups, function (array $left, array $right): int {
            return [$left['order'], $left['label'], $left['module_key']] <=> [$right['order'], $right['label'], $right['module_key']];
        });
        $group['module_groups'] = $moduleGroups;

        $items = [];
        foreach ($moduleGroups as $moduleGroup) {
            foreach ((array) ($moduleGroup['items'] ?? []) as $item) {
                $items[] = $item;
            }
        }
        $group['items'] = $items;
        $visibleGroups[] = $group;
    }

    usort($visibleGroups, function (array $left, array $right): int {
        return [$left['order'], $left['label']] <=> [$right['order'], $right['label']];
    });

    return $visibleGroups;
}

function sr_admin_menu_item_target_key(string $moduleKey, string $path): string
{
    return $moduleKey . ':' . $path;
}

function sr_admin_menu_override_form_rows(PDO $pdo): array
{
    $overrides = sr_admin_menu_overrides($pdo);
    $rows = [];
    $sourceGroups = sr_admin_navigation_source_groups($pdo);

    usort($sourceGroups, function (array $left, array $right) use ($overrides): int {
        $leftKey = (string) ($left['category'] ?? '');
        $rightKey = (string) ($right['category'] ?? '');
        $leftOrder = (int) ($overrides['category'][$leftKey]['sort_order'] ?? $left['order'] ?? 1000);
        $rightOrder = (int) ($overrides['category'][$rightKey]['sort_order'] ?? $right['order'] ?? 1000);

        return [$leftOrder, (string) ($left['label'] ?? $leftKey)] <=> [$rightOrder, (string) ($right['label'] ?? $rightKey)];
    });

    foreach ($sourceGroups as $group) {
        if (!is_array($group)) {
            continue;
        }

        $categoryKey = (string) ($group['category'] ?? '');
        $categoryOrder = (int) ($group['order'] ?? 1000);
        $categoryOverride = $overrides['category'][$categoryKey] ?? [];
        $rows[] = sr_admin_menu_override_form_row(
            'category',
            $categoryKey,
            '',
            (string) ($group['label'] ?? $categoryKey),
            $categoryOrder,
            $categoryOverride,
            [
                'depth' => 0,
                'context' => '',
            ]
        );

        $moduleGroups = array_values((array) ($group['module_groups'] ?? []));
        usort($moduleGroups, function (array $left, array $right) use ($overrides): int {
            $leftKey = (string) ($left['module_key'] ?? '');
            $rightKey = (string) ($right['module_key'] ?? '');
            $leftOrder = (int) ($overrides['group'][$leftKey]['sort_order'] ?? $left['order'] ?? 1000);
            $rightOrder = (int) ($overrides['group'][$rightKey]['sort_order'] ?? $right['order'] ?? 1000);

            return [$leftOrder, (string) ($left['label'] ?? $leftKey), $leftKey] <=> [$rightOrder, (string) ($right['label'] ?? $rightKey), $rightKey];
        });

        foreach ($moduleGroups as $moduleGroup) {
            if (!is_array($moduleGroup)) {
                continue;
            }

            $moduleKey = (string) ($moduleGroup['module_key'] ?? '');
            $groupOrder = (int) ($moduleGroup['order'] ?? 1000);
            $groupOverride = $overrides['group'][$moduleKey] ?? [];
            $rows[] = sr_admin_menu_override_form_row(
                'group',
                $moduleKey,
                $categoryKey,
                (string) ($moduleGroup['label'] ?? $moduleKey),
                $groupOrder,
                $groupOverride,
                [
                    'depth' => 1,
                    'context' => (string) ($group['label'] ?? $categoryKey),
                ]
            );

            $moduleItems = array_values((array) ($moduleGroup['items'] ?? []));
            usort($moduleItems, function (array $left, array $right) use ($overrides, $moduleKey): int {
                $leftPath = (string) ($left['path'] ?? '');
                $rightPath = (string) ($right['path'] ?? '');
                $leftKey = sr_admin_menu_item_target_key($moduleKey, $leftPath);
                $rightKey = sr_admin_menu_item_target_key($moduleKey, $rightPath);
                $leftOrder = (int) ($overrides['item'][$leftKey]['sort_order'] ?? $left['order'] ?? 1000);
                $rightOrder = (int) ($overrides['item'][$rightKey]['sort_order'] ?? $right['order'] ?? 1000);

                return [$leftOrder, (string) ($left['label'] ?? $leftPath), $leftPath] <=> [$rightOrder, (string) ($right['label'] ?? $rightPath), $rightPath];
            });

            foreach ($moduleItems as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $path = (string) ($item['path'] ?? '');
                $targetKey = sr_admin_menu_item_target_key($moduleKey, $path);
                $itemOrder = (int) ($item['order'] ?? 1000);
                $itemOverride = $overrides['item'][$targetKey] ?? [];
                $rows[] = sr_admin_menu_override_form_row(
                    'item',
                    $targetKey,
                    $moduleKey,
                    (string) ($item['label'] ?? $path),
                    $itemOrder,
                    $itemOverride,
                    [
                        'depth' => 2,
                        'context' => (string) ($group['label'] ?? $categoryKey) . ' / ' . (string) ($moduleGroup['label'] ?? $moduleKey),
                        'path' => $path,
                    ]
                );
            }
        }
    }

    return $rows;
}

function sr_admin_menu_override_form_row(string $scope, string $targetKey, string $parentKey, string $label, int $defaultOrder, array $override, array $display = []): array
{
    $sortOrder = array_key_exists('sort_order', $override) ? (int) $override['sort_order'] : $defaultOrder;
    $canHide = sr_admin_menu_target_can_hide($scope, $targetKey);

    return [
        'scope' => $scope,
        'target_key' => $targetKey,
        'parent_key' => $parentKey,
        'form_key' => $scope . '|' . $targetKey,
        'label' => $label,
        'default_order' => $defaultOrder,
        'sort_order' => $sortOrder,
        'is_hidden' => $canHide && !empty($override['is_hidden']),
        'can_hide' => $canHide,
        'depth' => max(0, min(2, (int) ($display['depth'] ?? 0))),
        'context' => (string) ($display['context'] ?? ''),
        'path' => (string) ($display['path'] ?? ''),
    ];
}

function sr_admin_handle_menu_post(PDO $pdo, array $account): array
{
    $intent = sr_post_string('intent', 40);
    if (!in_array($intent, ['save_menu_overrides', 'reset_menu_overrides'], true)) {
        return sr_admin_action_result(['메뉴 작업 값이 올바르지 않습니다.'], '');
    }

    if ($intent === 'reset_menu_overrides') {
        if (sr_post_string('reset_confirmed', 1) !== '1') {
            return sr_admin_action_result(['관리자 메뉴 표시 설정 초기화에 동의해야 합니다.'], '');
        }

        sr_admin_ensure_menu_overrides_table($pdo);
        $pdo->exec('DELETE FROM sr_admin_menu_overrides');
        sr_admin_log_menu_override_change($pdo, $account, 'reset');
        return sr_admin_action_result([], '관리자 메뉴 표시 설정을 초기화했습니다.');
    }

    $allowedTargets = [];
    foreach (sr_admin_menu_override_form_rows($pdo) as $row) {
        $allowedTargets[(string) $row['form_key']] = $row;
    }

    $postedOrders = $_POST['sort_order'] ?? [];
    if (!is_array($postedOrders)) {
        return sr_admin_action_result(['메뉴 순서 값이 올바르지 않습니다.'], '');
    }

    $postedHidden = $_POST['is_hidden'] ?? [];
    $hiddenMap = [];
    if (is_array($postedHidden)) {
        foreach ($postedHidden as $hiddenKey) {
            if (is_string($hiddenKey)) {
                $hiddenMap[$hiddenKey] = true;
            }
        }
    }

    $errors = [];
    $changes = [];
    foreach ($allowedTargets as $formKey => $row) {
        $rawOrder = $postedOrders[$formKey] ?? '';
        if (!is_string($rawOrder) && !is_int($rawOrder)) {
            $errors[] = '메뉴 순서 값이 올바르지 않습니다.';
            continue;
        }

        $rawOrder = trim((string) $rawOrder);
        if (preg_match('/\A-?[0-9]{1,6}\z/', $rawOrder) !== 1) {
            $errors[] = '메뉴 순서는 숫자로 입력하세요.';
            continue;
        }

        $sortOrder = (int) $rawOrder;
        $isHidden = !empty($row['can_hide']) && !empty($hiddenMap[$formKey]);
        $changes[] = [
            'scope' => (string) $row['scope'],
            'target_key' => (string) $row['target_key'],
            'default_order' => (int) $row['default_order'],
            'sort_order' => $sortOrder,
            'is_hidden' => $isHidden,
        ];
    }

    if ($errors !== []) {
        return sr_admin_action_result(array_values(array_unique($errors)), '');
    }

    $now = sr_now();
    sr_admin_ensure_menu_overrides_table($pdo);
    foreach ($changes as $change) {
        if ((int) $change['sort_order'] === (int) $change['default_order'] && empty($change['is_hidden'])) {
            sr_admin_delete_menu_override($pdo, (string) $change['scope'], (string) $change['target_key']);
            continue;
        }

        sr_admin_save_menu_override(
            $pdo,
            (string) $change['scope'],
            (string) $change['target_key'],
            (int) $change['sort_order'],
            !empty($change['is_hidden']),
            $now
        );
    }

    sr_admin_log_menu_override_change($pdo, $account, 'save');
    return sr_admin_action_result([], '관리자 메뉴 표시 설정을 저장했습니다.');
}

function sr_admin_save_menu_override(PDO $pdo, string $scope, string $targetKey, int $sortOrder, bool $isHidden, string $now): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_admin_menu_overrides (scope, target_key, sort_order, is_hidden, updated_at)
         VALUES (:scope, :target_key, :sort_order, :is_hidden, :updated_at)
         ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), is_hidden = VALUES(is_hidden), updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'scope' => $scope,
        'target_key' => $targetKey,
        'sort_order' => $sortOrder,
        'is_hidden' => $isHidden ? 1 : 0,
        'updated_at' => $now,
    ]);
}

function sr_admin_ensure_menu_overrides_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS sr_admin_menu_overrides (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope VARCHAR(20) NOT NULL,
            target_key VARCHAR(190) NOT NULL,
            sort_order INT NOT NULL DEFAULT 1000,
            is_hidden TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sr_admin_menu_overrides_target (scope, target_key),
            KEY idx_sr_admin_menu_overrides_scope_order (scope, sort_order)
        )'
    );
}

function sr_admin_delete_menu_override(PDO $pdo, string $scope, string $targetKey): void
{
    $stmt = $pdo->prepare('DELETE FROM sr_admin_menu_overrides WHERE scope = :scope AND target_key = :target_key');
    $stmt->execute([
        'scope' => $scope,
        'target_key' => $targetKey,
    ]);
}

function sr_admin_log_menu_override_change(PDO $pdo, array $account, string $action): void
{
    sr_audit_log($pdo, [
        'actor_account_id' => (int) ($account['id'] ?? 0),
        'actor_type' => 'admin',
        'event_type' => 'admin.menu.updated',
        'target_type' => 'module',
        'target_id' => 'admin',
        'result' => 'success',
        'message' => 'Admin menu display settings updated.',
        'metadata' => [
            'action' => $action,
        ],
    ]);
}
