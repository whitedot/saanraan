<?php

return [
    'name' => sr_t('reward::ui.text.d20e87cb'),
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => sr_t('reward::ui.member.1391f876'),
    'admin' => [
        'category' => 'member',
        'category_label' => sr_t('reward::ui.member.e335b899'),
        'category_order' => 10,
        'menu_order' => 40,
        'icon' => ['type' => 'symbol', 'name' => 'gift'],
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
