<?php

return [
    'name' => 'SEO',
    'version' => '2026.04.002',
    'type' => 'module',
    'description' => sr_t('seo::ui.seo.point.ea9a7371'),
    'admin' => [
        'category' => 'site',
        'category_label' => sr_t('seo::ui.text.b2c8d45c'),
        'category_order' => 20,
        'menu_order' => 50,
        'icon' => ['type' => 'symbol', 'name' => 'search'],
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
        'title_suffix' => '',
        'default_description' => '',
        'default_og_image' => '',
        'sitemap_include_home' => true,
        'robots_disallow_paths' => "/admin\n/account\n/login\n/register\n/password/reset",
    ],
];
