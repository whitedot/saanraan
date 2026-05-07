<?php

return [
    'name' => 'Sample Notice',
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => 'Minimal sample module for Toycore extension contracts.',
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
    ],
    'requires' => [
        'modules' => ['admin'],
    ],
    'contracts' => [
        'provides' => [
            'admin-menu.php',
            'output-slots.php',
        ],
    ],
];
