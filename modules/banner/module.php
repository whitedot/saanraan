<?php

return [
    'name' => '배너',
    'version' => '2026.06.003',
    'type' => 'module',
    'description' => '공개 출력 슬롯용 배너 관리 모듈입니다.',
    'admin' => [
        'category' => 'site',
        'category_label' => '사이트',
        'category_order' => 20,
        'menu_order' => 30,
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
            'retention-targets.php',
            'output-slots.php',
            'public-banner.php',
        ],
        'consumes' => [
            'extension-points.php',
            'coupon-targets.php',
            'banner-references.php',
        ],
    ],
    'settings' => [
        'banner_skin_key' => 'basic',
        'banner_default_status' => 'draft',
        'banner_default_target_option' => 'core|site.layout|before_layout',
        'banner_default_match_type' => 'all',
        'banner_default_sort_order' => 100,
    ],
];
