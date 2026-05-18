<?php

return [
    'name' => '페이지',
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => '단일 페이지 작성과 공개 URL을 관리하는 모듈입니다.',
    'admin' => [
        'category' => 'site',
        'category_label' => '사이트 구성',
        'category_order' => 25,
        'menu_order' => 30,
        'icon' => ['type' => 'symbol', 'name' => 'content'],
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
            'extension-points.php',
            'menu-links.php',
            'sitemap.php',
        ],
    ],
];
