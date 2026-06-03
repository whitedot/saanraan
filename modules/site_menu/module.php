<?php

return [
    'name' => '사이트 메뉴',
    'version' => '2026.06.001',
    'type' => 'module',
    'description' => '사이트 공통 내비게이션 메뉴 관리 모듈입니다.',
    'admin' => [
        'category' => 'site',
        'category_label' => '사이트',
        'category_order' => 20,
        'menu_order' => 10,
        'icon' => ['type' => 'symbol', 'name' => 'menu-list'],
        'stylesheets' => ['assets/admin.css'],
    ],
    'public' => [
        'stylesheets' => ['assets/public.css'],
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
            'output-slots.php',
        ],
        'consumes' => [
            'menu-links.php',
        ],
    ],
];
