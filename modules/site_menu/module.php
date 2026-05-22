<?php

return [
    'name' => sr_t('site_menu::ui.menu.a14f2522'),
    'version' => '2026.04.003',
    'type' => 'module',
    'description' => sr_t('site_menu::ui.menu.d47587ec'),
    'admin' => [
        'category' => 'site',
        'category_label' => sr_t('site_menu::ui.text.b2c8d45c'),
        'category_order' => 20,
        'menu_order' => 10,
        'icon' => ['type' => 'symbol', 'name' => 'menu-list'],
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
            'output-slots.php',
            'dashboard.php',
        ],
        'consumes' => [
            'menu-links.php',
        ],
    ],
];
