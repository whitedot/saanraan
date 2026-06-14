<?php

return [
    'name' => '자동등록방지 CAPTCHA 제공자',
    'version' => '2026.06.001',
    'type' => 'plugin',
    'description' => '자동등록방지 모듈에 Turnstile, hCaptcha, reCAPTCHA provider 계약을 제공합니다.',
    'admin' => [
        'category' => 'plugin',
        'category_label' => '플러그인',
        'category_order' => 45,
        'menu_order' => 20,
        'icon' => ['type' => 'symbol', 'name' => 'shield'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['antispam'],
    ],
    'contracts' => [
        'provides' => [
            'antispam-providers.php',
        ],
    ],
];
