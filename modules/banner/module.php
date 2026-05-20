<?php

return [
    'name' => '배너',
    'version' => '2026.05.003',
    'type' => 'module',
    'description' => '공개 출력 슬롯용 배너 관리 모듈입니다.',
    'admin' => [
        'category' => 'site',
        'category_label' => '사이트',
        'category_order' => 20,
        'menu_order' => 30,
        'icon' => ['type' => 'symbol', 'name' => 'image'],
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
            'dashboard.php',
        ],
        'consumes' => [
            'extension-points.php',
        ],
    ],
    'settings' => [
        'banner_skin_key' => 'basic',
    ],
];
