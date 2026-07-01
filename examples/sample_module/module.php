<?php

return [
    'name' => 'Sample Notice',
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => 'Minimal sample module for Saanraan extension contracts.',
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['admin', 'member'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'output-slots.php',
        ],
    ],
];
