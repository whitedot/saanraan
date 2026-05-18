<?php

return [
    'name' => '알림',
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => '사이트 알림과 외부 발송 대기열 모듈입니다.',
    'admin' => [
        'category' => 'operation',
        'category_label' => '운영',
        'category_order' => 40,
        'menu_order' => 10,
        'icon' => ['type' => 'symbol', 'name' => 'bell'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'menu-links.php',
            'privacy-export.php',
            'dashboard.php',
        ],
    ],
];
