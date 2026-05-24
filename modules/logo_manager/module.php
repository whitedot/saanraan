<?php

return [
    'name' => '로고 매니저',
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => '관리자/공개 화면의 로고 자산과 기간별 대체 적용을 관리합니다.',
    'admin' => [
        'category' => 'site',
        'category_label' => '사이트',
        'category_order' => 20,
        'menu_order' => 15,
        'icon' => ['type' => 'symbol', 'name' => 'image'],
        'stylesheets' => ['assets/admin.css'],
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
        ],
    ],
];
