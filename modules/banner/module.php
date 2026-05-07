<?php

return [
    'name' => 'Banner',
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => 'Content banner management module for public output slots.',
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
            'output-slots.php',
        ],
        'consumes' => [
            'extension-points.php',
        ],
    ],
];
