<?php

return [
    'name' => '회원 OAuth 제공자',
    'version' => '2026.06.001',
    'type' => 'plugin',
    'description' => '회원 OAuth 모듈에 Google, Kakao, Naver, GitHub, Apple ID 로그인 제공자 계약을 제공합니다.',
    'admin' => [
        'category' => 'plugin',
        'category_label' => '플러그인',
        'category_order' => 45,
        'menu_order' => 30,
        'icon' => ['type' => 'symbol', 'name' => 'key'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['member_oauth'],
    ],
    'contracts' => [
        'provides' => [
            'oauth-providers.php',
        ],
    ],
];
