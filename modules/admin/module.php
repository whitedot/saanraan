<?php

return [
    'name' => 'Admin',
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => 'Admin dashboard module.',
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
    ],
    'requires' => [
        'modules' => ['member'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
        ],
    ],
];
