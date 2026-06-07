<?php

return [
    'name' => '콘텐츠 임베드',
    'version' => '2026.06.001',
    'type' => 'module',
    'description' => '본문 안에 여러 모듈 대상을 marker와 참조 행으로 연결하는 콘텐츠 임베드 기반 모듈입니다.',
    'admin' => [
        'category' => 'service',
        'category_label' => '서비스',
        'category_order' => 30,
        'menu_order' => 990,
        'icon' => ['type' => 'symbol', 'name' => 'ink_highlighter'],
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
            'content-embed-targets.php',
        ],
    ],
];
