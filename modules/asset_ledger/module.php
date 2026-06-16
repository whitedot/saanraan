<?php

return [
    'name' => '잔액 처리 기반',
    'version' => '2026.06.001',
    'type' => 'module',
    'description' => '회원 자산 모듈의 잔액 갱신과 거래 기록 primitive를 제공하는 숨김 기반 모듈입니다.',
    'admin' => [
        'hidden' => true,
        'foundation' => true,
        'category' => 'system',
        'category_label' => '시스템',
        'category_order' => 0,
        'menu_order' => 990,
        'icon' => ['type' => 'symbol', 'name' => 'account_balance'],
        'stylesheets' => ['assets/admin.css'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
        ],
    ],
];
