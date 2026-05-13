<?php

return [
    'name' => 'SEO',
    'version' => '2026.04.002',
    'type' => 'module',
    'description' => 'SEO 출력 helper와 사이트맵 엔드포인트 모듈입니다.',
    'admin' => [
        'category' => 'content',
        'category_label' => '사이트 구성',
        'category_order' => 30,
        'menu_order' => 40,
    ],
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
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
        'title_suffix' => '',
        'default_description' => '',
        'default_og_image' => '',
        'sitemap_include_home' => true,
        'robots_disallow_paths' => "/admin\n/account\n/login\n/register\n/password/reset",
    ],
];
