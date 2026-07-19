<?php

return [
    'name' => '팝업레이어',
    'version' => '2026.06.003',
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
            'public-popup-layer.php',
        ],
        'consumes' => [
            'extension-points.php',
            'popup-layer-references.php',
            'coupon-targets.php',
        ],
    ],
    'settings' => [
        'popup_layer_skin_key' => 'basic',
        'popup_layer_default_status' => 'draft',
        'popup_layer_default_target_option' => '__public__',
        'popup_layer_default_match_type' => 'all',
        'popup_layer_default_dismiss_cookie_days' => 1,
        'popup_layer_editor' => 'textarea',
    ],
];
