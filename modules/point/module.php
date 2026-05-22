<?php

return [
    'name' => sr_t('point::ui.point.e5f25ab0'),
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => sr_t('point::ui.member.point.3ff6e198'),
    'admin' => [
        'category' => 'member',
        'category_label' => sr_t('point::ui.member.e335b899'),
        'category_order' => 10,
        'menu_order' => 30,
        'icon' => ['type' => 'symbol', 'name' => 'coins'],
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
            'privacy-export.php',
        ],
    ],
];
