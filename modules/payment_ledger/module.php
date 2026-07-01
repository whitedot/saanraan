<?php

return [
    'name' => '결제 기록 기반',
    'version' => '2026.07.001',
    'type' => 'module',
    'description' => '쿠폰, 회원 자산, 외부 결제, 접근권 부여를 하나의 결제 묶음으로 기록하는 숨김 기반 모듈입니다.',
    'admin' => [
        'hidden' => true,
        'foundation' => true,
        'category' => 'system',
        'category_label' => '시스템',
        'category_order' => 0,
        'menu_order' => 995,
        'icon' => ['type' => 'symbol', 'name' => 'receipt_long'],
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
            'privacy-export.php',
            'privacy-cleanup.php',
            'operational-status.php',
        ],
        'consumes' => [
            'payment-ledger-targets.php',
        ],
    ],
];
