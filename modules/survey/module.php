<?php

return [
    'name' => '설문',
    'version' => '2026.06.005',
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
        'default_login_required' => 1,
        'default_consent_required' => 0,
        'default_response_limit_policy' => 'per_survey_once',
        'default_response_limit_period_seconds' => 0,
        'public_list_limit' => 50,
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'menu-links.php',
            'homepage-candidates.php',
            'layout-options.php',
            'privacy-export.php',
            'privacy-cleanup.php',
            'coupon-references.php',
            'member-group-references.php',
            'sitemap.php',
        ],
        'consumes' => [
            'member-assets.php',
            'notification-events.php',
        ],
    ],
    'service_domain' => [
        'main_page' => [
            'label' => '설문 메인',
            'path' => '/survey',
        ],
    ],
];
