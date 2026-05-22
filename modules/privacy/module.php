<?php

return [
    'name' => sr_t('privacy::ui.privacy.5cdfba05'),
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => sr_t('privacy::ui.privacy.privacy.733631a5'),
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'admin' => [
        'category' => 'operation',
        'category_label' => sr_t('privacy::ui.text.0928a1b8'),
        'category_order' => 40,
        'menu_order' => 20,
        'icon' => ['type' => 'symbol', 'name' => 'shield'],
        'stylesheets' => ['assets/admin.css'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'menu-links.php',
        ],
        'consumes' => [
            'privacy-export.php',
        ],
    ],
];
