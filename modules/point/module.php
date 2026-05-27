<?php

return [
    'name' => '포인트',
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => '회원 포인트 잔액과 거래 장부 모듈입니다.',
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
        'menu_order' => 30,
        'icon' => ['type' => 'symbol', 'name' => 'coins'],
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
            'asset-exchange.php',
        ],
    ],
];
