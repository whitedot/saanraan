<?php

return [
    'name' => '팝업레이어',
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => '팝업레이어 관리와 출력 모듈입니다.',
    'admin' => [
        'category' => 'site',
        'category_label' => '사이트',
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
