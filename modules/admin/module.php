<?php

return [
    'name' => '관리자',
    'version' => '2026.05.001',
    'type' => 'module',
    'description' => '관리자 대시보드 모듈입니다.',
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
