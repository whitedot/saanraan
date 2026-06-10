<?php

return [
    'name' => '개인정보',
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => '개인정보 처리 요청과 개인정보 사본 제공 조정 모듈입니다.',
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'admin' => [
        'category' => 'operation',
        'category_label' => '운영',
        'category_order' => 40,
        'menu_order' => 20,
        'icon' => ['type' => 'symbol', 'name' => 'shield'],
        'stylesheets' => ['assets/admin.css'],
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
            'menu-links.php',
        ],
        'consumes' => [
            'privacy-export.php',
            'admin-notification-events.php',
        ],
    ],
];
