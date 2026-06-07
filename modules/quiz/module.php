<?php

return [
    'name' => '퀴즈',
    'version' => '2026.06.006',
    'type' => 'module',
    'description' => '퀴즈 응시, 채점, 콘텐츠 연계 보상을 관리하는 모듈입니다.',
    'admin' => [
        'category' => 'service',
        'category_label' => '서비스',
        'category_order' => 30,
        'menu_order' => 11,
        'icon' => ['type' => 'symbol', 'name' => 'service'],
        'stylesheets' => ['assets/admin.css'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['member', 'admin', 'content'],
    ],
    'settings' => [
        'mvp_source_module' => 'content',
        'mvp_source_type' => 'content_item',
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'menu-links.php',
            'layout-options.php',
            'privacy-export.php',
            'privacy-cleanup.php',
            'coupon-references.php',
        ],
        'consumes' => [
            'member-assets.php',
        ],
    ],
    'service_domain' => [
        'main_page' => [
            'label' => '퀴즈 메인',
            'path' => '/quiz',
        ],
    ],
];
