<?php

return [
    'name' => sr_t('banner::ui.banner.63182d60'),
    'version' => '2026.05.003',
    'type' => 'module',
    'description' => sr_t('banner::ui.banner.83cb1ff1'),
    'admin' => [
        'category' => 'site',
        'category_label' => sr_t('banner::ui.text.b2c8d45c'),
        'category_order' => 20,
        'menu_order' => 30,
        'icon' => ['type' => 'symbol', 'name' => 'image'],
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
            'extension-points.php',
        ],
    ],
    'settings' => [
        'banner_skin_key' => 'basic',
    ],
];
