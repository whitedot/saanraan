<?php

return [
    'name' => '자동등록방지',
    'version' => '2026.06.001',
    'type' => 'module',
    'description' => '회원가입과 공개 제출 폼의 자동등록방지 challenge와 provider 검증 정책을 제공합니다.',
    'admin' => [
        'category' => 'security',
        'category_label' => '보안',
        'category_order' => 35,
        'menu_order' => 20,
        'icon' => ['type' => 'symbol', 'name' => 'shield'],
        'settings_path' => '/admin/antispam/settings',
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
            'antispam-targets.php',
            'antispam-providers.php',
        ],
    ],
    'settings' => [
        'enabled' => false,
        'default_mode' => 'guest',
        'challenge_type' => 'math',
        'ttl_seconds' => 600,
        'min_submit_seconds' => 2,
        'provider_timeout_seconds' => 3,
        'provider_failure_policy' => 'fail_closed',
        'verify_remote_ip_enabled' => false,
        'provider_action_check_enabled' => true,
        'provider_hostname_check_enabled' => true,
    ],
];
