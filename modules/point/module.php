<?php

return [
    'name' => '포인트',
    'version' => '2026.06.002',
    'type' => 'module',
    'description' => '회원 포인트 잔액과 거래 장부 모듈입니다.',
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
        'menu_order' => 30,
        'icon' => ['type' => 'symbol', 'name' => 'database'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['member', 'admin', 'asset_ledger'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'operational-status.php',
            'menu-links.php',
            'privacy-export.php',
            'asset-exchange.php',
            'member-assets.php',
            'member-withdrawal-assets.php',
            'dashboard.php',
        ],
        'consumes' => [
            'notification-events.php',
        ],
    ],
    'settings' => [
        'display_name' => '포인트',
        'unit_label' => 'P',
        'default_expiration_days' => '0',
    ],
];
