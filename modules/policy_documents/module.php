<?php

return [
    'name' => '약관/방침 관리',
    'version' => '2026.06.002',
    'type' => 'module',
    'description' => '약관, 방침, 동의 문서를 version 단위로 관리하고 다른 모듈이 참조할 수 있는 helper를 제공합니다.',
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'admin' => [
        'category' => 'operation',
        'category_label' => '운영',
        'category_order' => 40,
        'menu_order' => 50,
        'icon' => ['type' => 'symbol', 'name' => 'user'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'operational-status.php',
            'privacy-export.php',
            'privacy-cleanup.php',
        ],
    ],
];
