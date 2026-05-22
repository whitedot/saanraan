<?php

return [
    'name' => sr_t('popup_layer::ui.text.1063d585'),
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => sr_t('popup_layer::ui.text.c38dc775'),
    'admin' => [
        'category' => 'site',
        'category_label' => sr_t('popup_layer::ui.text.b2c8d45c'),
        'category_order' => 20,
        'menu_order' => 40,
        'icon' => ['type' => 'symbol', 'name' => 'layers'],
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
        'popup_layer_skin_key' => 'basic',
    ],
];
