<?php

return [
    'name' => 'CKEditor',
    'version' => '2026.05.001',
    'type' => 'plugin',
    'description' => '커뮤니티 게시글 textarea에 CKEditor 5 편집기를 선택적으로 붙이는 플러그인입니다.',
    'admin' => [
        'category' => 'site',
        'category_label' => '사이트',
        'category_order' => 20,
        'menu_order' => 80,
        'icon' => ['type' => 'symbol', 'name' => 'content'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['member', 'admin', 'community'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
        ],
    ],
    'settings' => [
        'asset_mode' => 'self_hosted',
        'cdn_version' => '48.1.0',
        'license_key' => 'GPL',
        'community_posts_enabled' => true,
        'toolbar_preset' => 'community_post_basic',
    ],
];
