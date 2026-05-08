<?php

return [
    'name' => 'Site Menu',
    'version' => '2026.04.003',
    'type' => 'module',
    'description' => 'Site-wide navigation menu management module.',
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
    ],
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'output-slots.php',
        ],
        'consumes' => [
            'menu-links.php',
        ],
    ],
];
