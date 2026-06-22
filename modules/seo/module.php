<?php

return [
    'name' => 'SEO',
    'version' => '2026.05.001',
    'type' => 'module',
    'description' => '사이트맵과 robots.txt 운영 설정을 관리하는 SEO 모듈입니다.',
    'admin' => [
        'category' => 'site',
        'category_label' => '사이트',
        'category_order' => 20,
        'menu_order' => 50,
        'icon' => ['type' => 'symbol', 'name' => 'search'],
        'stylesheets' => ['assets/admin.css'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['admin'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
        ],
        'consumes' => [
            'sitemap.php',
        ],
    ],
    'settings' => [
        'sitemap_include_home' => true,
        'robots_disallow_paths' => "/admin\n/account\n/login\n/register\n/password/reset",
    ],
];
