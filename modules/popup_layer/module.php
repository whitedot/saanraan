<?php

return [
    'name' => 'Popup Layer',
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => 'Popup layer management and rendering module.',
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
            'extension-points.php',
        ],
    ],
];
