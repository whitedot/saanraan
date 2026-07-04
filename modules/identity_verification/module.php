<?php

return [
    'name' => '본인확인',
    'version' => '2026.07.002',
    'type' => 'module',
    'description' => '외부 본인확인 provider 요청, 결과, 계정 연결과 개인정보 보관 경계를 소유합니다.',
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
        'menu_order' => 12,
        'icon' => ['type' => 'symbol', 'name' => 'verified_user'],
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
            'privacy-export.php',
            'privacy-cleanup.php',
            'retention-targets.php',
            'operational-status.php',
        ],
        'consumes' => [
            'identity-provider.php',
        ],
    ],
    'settings' => [
        'enabled' => false,
        'default_provider_key' => '',
        'attempt_ttl_seconds' => 600,
        'result_valid_days' => 365,
        'require_https' => true,
    ],
];
