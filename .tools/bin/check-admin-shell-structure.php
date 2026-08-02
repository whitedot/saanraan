#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$scripts = [
    'modules/admin/assets/admin-shell.js' => [
        'max_lines' => 2300,
        'markers' => ['window.AdminShell', 'initAnchorTabsScrollSpy', 'layoutMenuLists.forEach'],
        'forbidden' => ['const movedCellAnimations', 'const orderStorageKey'],
    ],
    'modules/admin/assets/admin-reordering.js' => [
        'max_lines' => 800,
        'markers' => ['window.AdminShellReordering', '[data-admin-sortable-row]', '[data-admin-reorder-list]'],
        'forbidden' => ['dashboardSectionsRoot'],
    ],
    'modules/admin/assets/admin-dashboard.js' => [
        'max_lines' => 950,
        'markers' => ['window.AdminDashboard', '[data-admin-dashboard-sections]', 'sr_admin_dashboard_section_order_v3'],
        'forbidden' => ['sortableRows', 'reorderLists'],
    ],
];

foreach ($scripts as $relativePath => $rules) {
    $path = $root . '/' . $relativePath;
    $contents = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($contents)) {
        $errors[] = 'Admin shell responsibility file is not readable: ' . $relativePath;
        continue;
    }

    $lineCount = substr_count($contents, "\n") + 1;
    if ($lineCount > (int) $rules['max_lines']) {
        $errors[] = $relativePath . ' exceeds ' . (string) $rules['max_lines'] . ' lines: ' . (string) $lineCount;
    }
    foreach ($rules['markers'] as $marker) {
        if (strpos($contents, $marker) === false) {
            $errors[] = $relativePath . ' is missing responsibility marker: ' . $marker;
        }
    }
    foreach ($rules['forbidden'] as $marker) {
        if (strpos($contents, $marker) !== false) {
            $errors[] = $relativePath . ' contains another responsibility marker: ' . $marker;
        }
    }
}

$shellPath = $root . '/modules/admin/helpers/shell.php';
$shell = is_file($shellPath) ? file_get_contents($shellPath) : false;
if (!is_string($shell)) {
    $errors[] = 'Admin shell helper is not readable.';
} else {
    $expectedScripts = [
        '/modules/admin/assets/admin-shell.js',
        '/modules/admin/assets/admin-reordering.js',
        '/modules/admin/assets/admin-dashboard.js',
    ];
    $previousPosition = -1;
    foreach ($expectedScripts as $script) {
        $position = strpos($shell, $script);
        if ($position === false || $position <= $previousPosition) {
            $errors[] = 'Admin shell responsibility scripts must be loaded explicitly in order: ' . $script;
            continue;
        }
        $previousPosition = $position;
    }
}

if ($errors !== []) {
    fwrite(STDERR, "admin shell structure checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "admin shell structure checks completed. files=3\n";
