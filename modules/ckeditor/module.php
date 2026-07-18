<?php

return [
    'name' => 'CKEditor',
    'version' => '2026.07.001',
    'type' => 'plugin',
    'description' => '설정된 textarea에 CKEditor 5 편집기를 붙이는 에디터 플러그인입니다.',
    'admin' => [
        'category' => 'plugin',
        'category_label' => '플러그인',
        'category_order' => 45,
        'menu_order' => 10,
        'icon' => ['type' => 'symbol', 'name' => 'content'],
        'settings_path' => '/admin/ckeditor/settings',
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['admin', 'member'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'editor-options.php',
        ],
    ],
    'settings' => [
        'asset_mode' => 'self_hosted',
        'cdn_version' => '48.3.0',
        'license_key' => 'GPL',
        'toolbar_preset' => 'standard',
    ],
];
