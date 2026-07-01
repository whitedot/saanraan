<?php

return [
    'name' => '개인정보',
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => '관리자 전용 개인정보 요청 대응 기록과 사본 제공 보조 도구 모듈입니다.',
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'admin' => [
        'category' => 'operation',
        'category_label' => '운영',
        'category_order' => 40,
        'menu_order' => 20,
        'icon' => ['type' => 'symbol', 'name' => 'shield'],
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
        'consumes' => [
            'privacy-export.php',
            'admin-notification-events.php',
        ],
    ],
];
