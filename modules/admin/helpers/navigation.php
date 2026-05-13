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
    return toy_admin_apply_menu_overrides($pdo, toy_admin_navigation_source_groups($pdo));
}

function toy_admin_navigation_source_groups(PDO $pdo): array
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
                ['label' => '메뉴', 'path' => '/admin/menu', 'order' => 45],
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
        'content' => '사이트 구성',
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

function toy_admin_menu_overrides(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT scope, target_key, sort_order, is_hidden FROM toy_admin_menu_overrides');
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

        $overrides[$scope][$targetKey] = [
            'sort_order' => (int) ($row['sort_order'] ?? 1000),
            'is_hidden' => !empty($row['is_hidden']),
        ];
    }

    return $overrides;
}

function toy_admin_apply_menu_overrides(PDO $pdo, array $groups): array
{
    $overrides = toy_admin_menu_overrides($pdo);
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
                $itemKey = toy_admin_menu_item_target_key($moduleKey, $path);
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

function toy_admin_menu_item_target_key(string $moduleKey, string $path): string
{
    return $moduleKey . ':' . $path;
}

function toy_admin_menu_override_form_rows(PDO $pdo): array
{
    $overrides = toy_admin_menu_overrides($pdo);
    $rows = [];

    foreach (toy_admin_navigation_source_groups($pdo) as $group) {
        if (!is_array($group)) {
            continue;
        }

        $categoryKey = (string) ($group['category'] ?? '');
        $categoryOrder = (int) ($group['order'] ?? 1000);
        $categoryOverride = $overrides['category'][$categoryKey] ?? [];
        $rows[] = toy_admin_menu_override_form_row(
            'category',
            $categoryKey,
            (string) ($group['label'] ?? $categoryKey),
            $categoryOrder,
            $categoryOverride
        );

        foreach ((array) ($group['module_groups'] ?? []) as $moduleGroup) {
            if (!is_array($moduleGroup)) {
                continue;
            }

            $moduleKey = (string) ($moduleGroup['module_key'] ?? '');
            $groupOrder = (int) ($moduleGroup['order'] ?? 1000);
            $groupOverride = $overrides['group'][$moduleKey] ?? [];
            $rows[] = toy_admin_menu_override_form_row(
                'group',
                $moduleKey,
                (string) ($group['label'] ?? $categoryKey) . ' / ' . (string) ($moduleGroup['label'] ?? $moduleKey),
                $groupOrder,
                $groupOverride
            );

            foreach ((array) ($moduleGroup['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $path = (string) ($item['path'] ?? '');
                $targetKey = toy_admin_menu_item_target_key($moduleKey, $path);
                $itemOrder = (int) ($item['order'] ?? 1000);
                $itemOverride = $overrides['item'][$targetKey] ?? [];
                $rows[] = toy_admin_menu_override_form_row(
                    'item',
                    $targetKey,
                    (string) ($group['label'] ?? $categoryKey) . ' / ' . (string) ($moduleGroup['label'] ?? $moduleKey) . ' / ' . (string) ($item['label'] ?? $path),
                    $itemOrder,
                    $itemOverride
                );
            }
        }
    }

    return $rows;
}

function toy_admin_menu_override_form_row(string $scope, string $targetKey, string $label, int $defaultOrder, array $override): array
{
    $sortOrder = array_key_exists('sort_order', $override) ? (int) $override['sort_order'] : $defaultOrder;

    return [
        'scope' => $scope,
        'target_key' => $targetKey,
        'form_key' => $scope . '|' . $targetKey,
        'label' => $label,
        'default_order' => $defaultOrder,
        'sort_order' => $sortOrder,
        'is_hidden' => !empty($override['is_hidden']),
    ];
}

function toy_admin_handle_menu_post(PDO $pdo, array $account): array
{
    $intent = toy_post_string('intent', 40);
    if (!in_array($intent, ['save_menu_overrides', 'reset_menu_overrides'], true)) {
        return toy_admin_action_result(['메뉴 작업 값이 올바르지 않습니다.'], '');
    }

    if ($intent === 'reset_menu_overrides') {
        toy_admin_ensure_menu_overrides_table($pdo);
        $pdo->exec('DELETE FROM toy_admin_menu_overrides');
        toy_admin_log_menu_override_change($pdo, $account, 'reset');
        return toy_admin_action_result([], '관리자 메뉴 표시 설정을 초기화했습니다.');
    }

    $allowedTargets = [];
    foreach (toy_admin_menu_override_form_rows($pdo) as $row) {
        $allowedTargets[(string) $row['form_key']] = $row;
    }

    $postedOrders = $_POST['sort_order'] ?? [];
    if (!is_array($postedOrders)) {
        return toy_admin_action_result(['메뉴 순서 값이 올바르지 않습니다.'], '');
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
        $isHidden = !empty($hiddenMap[$formKey]);
        $changes[] = [
            'scope' => (string) $row['scope'],
            'target_key' => (string) $row['target_key'],
            'default_order' => (int) $row['default_order'],
            'sort_order' => $sortOrder,
            'is_hidden' => $isHidden,
        ];
    }

    if ($errors !== []) {
        return toy_admin_action_result(array_values(array_unique($errors)), '');
    }

    $now = toy_now();
    toy_admin_ensure_menu_overrides_table($pdo);
    foreach ($changes as $change) {
        if ((int) $change['sort_order'] === (int) $change['default_order'] && empty($change['is_hidden'])) {
            toy_admin_delete_menu_override($pdo, (string) $change['scope'], (string) $change['target_key']);
            continue;
        }

        toy_admin_save_menu_override(
            $pdo,
            (string) $change['scope'],
            (string) $change['target_key'],
            (int) $change['sort_order'],
            !empty($change['is_hidden']),
            $now
        );
    }

    toy_admin_log_menu_override_change($pdo, $account, 'save');
    return toy_admin_action_result([], '관리자 메뉴 표시 설정을 저장했습니다.');
}

function toy_admin_save_menu_override(PDO $pdo, string $scope, string $targetKey, int $sortOrder, bool $isHidden, string $now): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO toy_admin_menu_overrides (scope, target_key, sort_order, is_hidden, updated_at)
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

function toy_admin_ensure_menu_overrides_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS toy_admin_menu_overrides (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope VARCHAR(20) NOT NULL,
            target_key VARCHAR(190) NOT NULL,
            sort_order INT NOT NULL DEFAULT 1000,
            is_hidden TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_toy_admin_menu_overrides_target (scope, target_key),
            KEY idx_toy_admin_menu_overrides_scope_order (scope, sort_order)
        )'
    );
}

function toy_admin_delete_menu_override(PDO $pdo, string $scope, string $targetKey): void
{
    $stmt = $pdo->prepare('DELETE FROM toy_admin_menu_overrides WHERE scope = :scope AND target_key = :target_key');
    $stmt->execute([
        'scope' => $scope,
        'target_key' => $targetKey,
    ]);
}

function toy_admin_log_menu_override_change(PDO $pdo, array $account, string $action): void
{
    toy_audit_log($pdo, [
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
