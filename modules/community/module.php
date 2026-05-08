<?php

return [
    'name' => 'Community',
    'version' => '2026.05.001',
    'type' => 'module',
    'description' => 'Board-style community module.',
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
    ],
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'menu-links.php',
            'extension-points.php',
            'privacy-export.php',
            'sitemap.php',
            'member-group-rules.php',
        ],
    ],
    'settings' => [
        'posts_per_page' => 20,
        'comments_per_page' => 50,
        'post_create_window_seconds' => 300,
        'post_create_limit' => 10,
        'comment_create_window_seconds' => 300,
        'comment_create_limit' => 30,
        'report_create_window_seconds' => 300,
        'report_create_limit' => 20,
        'message_create_window_seconds' => 300,
        'message_create_limit' => 20,
        'image_upload_max_bytes' => 2097152,
        'image_uploads_enabled' => true,
        'theme_key' => 'basic',
    ],
];
