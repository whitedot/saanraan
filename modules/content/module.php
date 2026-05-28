<?php

return [
    'name' => '콘텐츠',
    'version' => '2026.05.014',
    'type' => 'module',
    'description' => '콘텐츠 작성과 공개 URL을 관리하는 모듈입니다.',
    'admin' => [
        'category' => 'site',
        'category_label' => '사이트',
        'category_order' => 20,
        'menu_order' => 20,
        'icon' => ['type' => 'symbol', 'name' => 'content'],
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
        'editor' => 'textarea',
        'once_history_policy' => 'all_access',
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'extension-points.php',
            'menu-links.php',
            'privacy-export.php',
            'privacy-cleanup.php',
            'sitemap.php',
            'member-group-rules.php',
            'coupon-targets.php',
        ],
        'consumes' => [
            'member-assets.php',
        ],
    ],
];
