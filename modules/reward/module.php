<?php

return [
    'name' => '적립금',
    'version' => '2026.06.002',
    'type' => 'module',
    'description' => '회원 적립금 잔액과 거래 장부 모듈입니다.',
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
        'menu_order' => 40,
        'icon' => ['type' => 'symbol', 'name' => 'gift'],
        'stylesheets' => ['assets/admin.css'],
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
            'menu-links.php',
            'privacy-export.php',
            'asset-exchange.php',
            'member-assets.php',
            'member-withdrawal-assets.php',
            'member-group-references.php',
            'dashboard.php',
        ],
        'consumes' => [
            'notification-events.php',
        ],
    ],
    'settings' => [
        'withdrawal_requests_enabled' => false,
        'withdrawal_allowed_group_keys_json' => '[]',
    ],
];
