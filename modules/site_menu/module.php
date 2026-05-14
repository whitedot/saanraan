<?php

return [
    'name' => '사이트 메뉴',
    'version' => '2026.04.003',
    'type' => 'module',
    'description' => '사이트 공통 내비게이션 메뉴 관리 모듈입니다.',
    'admin' => [
        'category' => 'system_asset',
        'category_label' => '시스템 자산',
        'category_order' => 30,
        'menu_order' => 10,
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
