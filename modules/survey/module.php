<?php

return [
    'name' => '설문',
    'version' => '2026.06.001',
    'type' => 'module',
    'description' => '설문 작성, 공개 응답 수집, 응답 보상을 관리하는 모듈입니다.',
    'admin' => [
        'category' => 'service',
        'category_label' => '서비스',
        'category_order' => 30,
        'menu_order' => 12,
        'icon' => ['type' => 'symbol', 'name' => 'forms_add_on'],
        'stylesheets' => ['assets/admin.css'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'settings' => [
        'default_status' => 'draft',
        'default_reward_provider' => 'ledger_asset',
        'default_reward_module' => '',
        'default_reward_amount' => '',
        'default_reward_dedupe_scope' => 'per_survey',
        'public_list_limit' => 50,
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'menu-links.php',
            'privacy-export.php',
            'sitemap.php',
        ],
        'consumes' => [
            'member-assets.php',
        ],
    ],
    'service_domain' => [
        'main_page' => [
            'label' => '설문 메인',
            'path' => '/survey',
        ],
    ],
];
