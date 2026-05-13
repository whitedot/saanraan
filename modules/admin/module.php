<?php

return [
    'name' => '관리자',
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => '관리자 대시보드 모듈입니다.',
    'admin' => [
        'category' => 'system',
        'category_label' => '시스템',
        'category_order' => 0,
        'menu_order' => 0,
    ],
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
    ],
    'requires' => [
        'modules' => ['member'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
        ],
    ],
    'settings' => [
        'admin_skin_key' => 'basic',
    ],
];
