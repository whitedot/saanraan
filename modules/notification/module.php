<?php

return [
    'name' => '알림',
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => '사이트 알림과 외부 발송 대기열 모듈입니다.',
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
            'menu-links.php',
            'privacy-export.php',
        ],
    ],
];
