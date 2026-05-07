<?php

declare(strict_types=1);

function toy_admin_module_menu_items(PDO $pdo): array
{
    $items = [];
    $menuFiles = toy_enabled_module_contract_files($pdo, 'admin-menu.php', ['admin']);
    $pathFiles = toy_enabled_module_contract_files($pdo, 'paths.php', ['admin']);

    foreach ($menuFiles as $moduleKey => $file) {
        $menu = include $file;
        if (!is_array($menu)) {
            continue;
        }

        $pathsFile = (string) ($pathFiles[$moduleKey] ?? '');
        $paths = $pathsFile !== '' ? include $pathsFile : [];
        $paths = is_array($paths) ? $paths : [];

        $rawItems = isset($menu['items']) && is_array($menu['items']) ? $menu['items'] : $menu;
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
    }

    usort($items, function (array $left, array $right): int {
        return [$left['order'], $left['label'], $left['path']] <=> [$right['order'], $right['label'], $right['path']];
    });

    return $items;
}
