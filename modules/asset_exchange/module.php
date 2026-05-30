<?php

return [
    'name' => '포인트/금액 환전',
    'version' => '2026.05.004',
    'type' => 'module',
    'description' => '설치된 포인트/금액 항목 간 환전 정책과 실행 로그를 관리합니다.',
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
        'menu_order' => 60,
        'icon' => ['type' => 'symbol', 'name' => 'wallet'],
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
        ],
    ],
];
