<?php

return [
    'name' => '페이지',
    'version' => '2026.05.007',
    'type' => 'module',
    'description' => '단일 페이지 작성과 공개 URL을 관리하는 모듈입니다.',
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
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'extension-points.php',
            'menu-links.php',
            'privacy-export.php',
            'sitemap.php',
        ],
    ],
];
