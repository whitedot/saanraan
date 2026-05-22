<?php

return [
    'name' => sr_t('admin::ui.admin.78496a61'),
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => sr_t('admin::ui.admin.dashboard.84c5309f'),
    'admin' => [
        'category' => 'system',
        'category_label' => sr_t('admin::ui.text.3e1b8796'),
        'category_order' => 0,
        'menu_order' => 0,
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['member'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
        ],
    ],
    'settings' => [
        'admin_skin_key' => 'basic',
    ],
];
