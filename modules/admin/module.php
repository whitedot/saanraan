<?php

return [
    'name' => '관리자',
    'version' => '2026.05.005',
    'type' => 'module',
    'description' => '관리자 대시보드 모듈입니다.',
    'admin' => [
        'category' => 'system',
        'category_label' => '시스템',
        'category_order' => 0,
        'menu_order' => 0,
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['member'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
        ],
    ],
    'settings' => [
        'admin_skin_key' => 'basic',
        'admin_color_scheme' => 'light',
        'list_pagination_per_page' => 50,
    ],
];
