<?php

return [
    'name' => 'SEO',
    'version' => '2026.04.002',
    'type' => 'module',
    'description' => 'SEO output helpers and sitemap endpoint.',
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
