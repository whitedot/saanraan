<?php

return [
    'name' => sr_t('deposit::ui.deposit.c7bac029'),
    'version' => '2026.04.001',
    'type' => 'module',
    'description' => sr_t('deposit::ui.member.deposit.ccf962e0'),
    'admin' => [
        'category' => 'member',
        'category_label' => sr_t('deposit::ui.member.e335b899'),
        'category_order' => 10,
        'menu_order' => 50,
        'icon' => ['type' => 'symbol', 'name' => 'wallet'],
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
