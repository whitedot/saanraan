<?php

return [
    'name' => '쿠폰·이용권',
    'version' => '2026.06.007',
    'type' => 'module',
    'description' => '회원별 쿠폰 종류, 지급, 사용 내역을 관리합니다.',
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
        'menu_order' => 60,
        'icon' => ['type' => 'symbol', 'name' => 'ticket'],
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
            'menu-links.php',
            'privacy-export.php',
            'member-withdrawal-assets.php',
            'coupon-references.php',
            'dashboard.php',
            'embed-manager-url-targets.php',
        ],
        'consumes' => [
            'coupon-references.php',
            'coupon-targets.php',
            'notification-events.php',
        ],
    ],
];
