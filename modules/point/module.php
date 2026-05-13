<?php

return [
    'name' => '포인트',
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => '회원 포인트 잔액과 거래 장부 모듈입니다.',
    'admin' => [
        'category' => 'member_asset',
        'category_label' => '회원 자산',
        'category_order' => 50,
        'menu_order' => 10,
    ],
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
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
