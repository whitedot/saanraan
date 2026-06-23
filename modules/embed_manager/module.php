<?php

return [
    'name' => '임베드 매니저',
    'version' => '2026.06.002',
    'type' => 'module',
    'description' => '본문 URL을 모듈별 공개 임베드로 해석하는 임베드 매니저 기반 모듈입니다.',
    'admin' => [
        'category' => 'operation',
        'category_label' => '운영',
        'category_order' => 40,
        'menu_order' => 40,
        'icon' => ['type' => 'symbol', 'name' => 'integration_instructions'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'settings' => [
        'url_embed_enabled' => false,
        'internal_url_embed_enabled' => true,
        'external_url_embed_enabled' => false,
        'embed_scope' => 'standalone_url_only',
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
        ],
        'consumes' => [
            'embed-manager-targets.php',
            'embed-manager-url-targets.php',
        ],
    ],
];
