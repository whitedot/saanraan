<?php

return [
    'name' => '개인정보',
    'version' => '2026.05.001',
    'type' => 'module',
    'description' => '개인정보 처리 요청과 개인정보 사본 제공 조정 모듈입니다.',
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'admin' => [
        'category' => 'privacy',
        'category_label' => '개인정보',
        'category_order' => 45,
        'menu_order' => 10,
    ],
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'menu-links.php',
        ],
        'consumes' => [
            'privacy-export.php',
        ],
    ],
];
