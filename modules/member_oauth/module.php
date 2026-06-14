<?php

return [
    'name' => '회원 OAuth',
    'version' => '2026.06.002',
    'type' => 'module',
    'description' => 'OAuth/OIDC provider 로그인과 계정 연결을 회원 모듈 밖에서 소유합니다.',
    'requires' => [
        'modules' => ['member', 'admin', 'policy_documents'],
    ],
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
        'menu_order' => 40,
        'icon' => ['type' => 'symbol', 'name' => 'link'],
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
            'privacy-export.php',
            'privacy-cleanup.php',
        ],
        'consumes' => [
            'oauth-providers.php',
        ],
    ],
    'settings' => [
        'mock_enabled' => true,
        'mock_label' => 'Mock OAuth',
        'state_ttl_seconds' => 600,
        'completion_ttl_seconds' => 900,
    ],
];
