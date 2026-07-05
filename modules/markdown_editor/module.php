<?php

return [
    'name' => 'Markdown Editor',
    'version' => '2026.07.001',
    'type' => 'plugin',
    'description' => 'Markdown 입력, 렌더링, 스타일 프로파일을 제공하는 플러그인입니다.',
    'admin' => [
        'category' => 'plugin',
        'category_label' => '플러그인',
        'category_order' => 45,
        'menu_order' => 20,
        'icon' => ['type' => 'symbol', 'name' => 'markdown'],
        'settings_path' => '/admin/markdown-editor/settings',
        'stylesheets' => ['assets/admin.css'],
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
            'markdown-renderer.php',
        ],
    ],
    'settings' => [
        'tables_enabled' => true,
        'task_lists_enabled' => true,
        'code_blocks_enabled' => true,
        'raw_html_enabled' => false,
        'style_profile_json' => [],
        'custom_declarations_json' => [],
    ],
];
