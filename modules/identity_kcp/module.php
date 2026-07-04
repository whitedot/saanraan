<?php

return [
    'name' => 'NHN KCP 본인확인 제공자',
    'version' => '2026.07.001',
    'type' => 'plugin',
    'description' => '본인확인 모듈에 NHN KCP 휴대폰 본인확인 V2 provider 계약을 제공합니다.',
    'requires' => [
        'modules' => ['identity_verification'],
    ],
    'admin' => [
        'category' => 'plugin',
        'category_label' => '플러그인',
        'category_order' => 45,
        'menu_order' => 30,
        'icon' => ['type' => 'symbol', 'name' => 'phone_iphone'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'contracts' => [
        'provides' => [
            'identity-provider.php',
        ],
    ],
];
