<?php

return [
    'name' => '회원',
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => '회원 계정과 인증 모듈입니다.',
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
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
            'extension-points.php',
            'menu-links.php',
            'privacy-export.php',
        ],
        'consumes' => [
            'member-group-rules.php',
        ],
    ],
    'settings' => [
        'allow_registration' => true,
        'email_verification_enabled' => true,
        'login_identifier' => 'email',
        'login_throttle_window_seconds' => 900,
        'login_throttle_account_limit' => 5,
        'login_throttle_ip_limit' => 20,
        'password_reset_throttle_window_seconds' => 900,
        'password_reset_throttle_account_limit' => 3,
        'password_reset_throttle_ip_limit' => 10,
        'email_verification_throttle_window_seconds' => 900,
        'email_verification_throttle_account_limit' => 3,
        'email_verification_throttle_ip_limit' => 20,
        'register_throttle_window_seconds' => 900,
        'register_throttle_ip_limit' => 10,
        'member_skin_key' => 'basic',
        'profile_nickname_enabled' => true,
        'profile_phone_enabled' => true,
        'profile_birth_date_enabled' => true,
        'profile_avatar_enabled' => true,
        'profile_text_enabled' => true,
    ],
];
