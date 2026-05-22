<?php

return [
    'name' => sr_t('logo_manager::ui.text.e046e24f'),
    'version' => '2026.05.001',
    'type' => 'module',
    'description' => sr_t('logo_manager::ui.admin.f4e398b2'),
    'admin' => [
        'category' => 'site',
        'category_label' => sr_t('logo_manager::ui.text.b2c8d45c'),
        'category_order' => 20,
        'menu_order' => 25,
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
        ],
    ],
];
