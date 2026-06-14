<?php

return [
    'name' => '임베드 매니저',
    'version' => '2026.06.001',
    'type' => 'module',
    'description' => '본문 안에 여러 모듈 대상을 marker와 참조 행으로 연결하는 임베드 매니저 기반 모듈입니다.',
    'admin' => [
        'category' => 'operation',
        'category_label' => '운영',
        'category_order' => 40,
        'menu_order' => 40,
        'icon' => ['type' => 'symbol', 'name' => 'integration_instructions'],
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
        ],
        'consumes' => [
            'embed-manager-targets.php',
        ],
    ],
];
