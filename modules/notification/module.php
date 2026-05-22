<?php

return [
    'name' => sr_t('notification::ui.notification.12ddd6ca'),
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => sr_t('notification::ui.notification.e8d3fbb5'),
    'admin' => [
        'category' => 'operation',
        'category_label' => sr_t('notification::ui.text.0928a1b8'),
        'category_order' => 40,
        'menu_order' => 10,
        'icon' => ['type' => 'symbol', 'name' => 'bell'],
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
            'menu-links.php',
            'privacy-export.php',
            'dashboard.php',
        ],
    ],
];
