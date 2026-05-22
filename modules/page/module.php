<?php

return [
    'name' => sr_t('page::ui.page.6c84a1b3'),
    'version' => '2026.05.007',
    'type' => 'module',
    'description' => sr_t('page::ui.page.611ca13a'),
    'admin' => [
        'category' => 'site',
        'category_label' => sr_t('page::ui.text.b2c8d45c'),
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
