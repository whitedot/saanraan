<?php

return [
    'name' => '리액션',
    'version' => '2026.06.002',
    'type' => 'module',
    'description' => '콘텐츠, 커뮤니티, 퀴즈, 설문이 함께 사용하는 공통 리액션 정의와 원장 모듈입니다.',
    'admin' => [
        'category' => 'operation',
        'category_label' => '운영',
        'category_order' => 40,
        'menu_order' => 30,
        'icon' => ['type' => 'symbol', 'name' => 'emoji_emotions'],
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
            'privacy-export.php',
            'privacy-cleanup.php',
            'public-reaction.php',
        ],
        'consumes' => [
            'reaction-targets.php',
            'notification-events.php',
        ],
    ],
    'settings' => [
        'reaction_default_preset_key' => 'emotions',
        'reaction_preset_visible_default' => 6,
        'reaction_preset_visible_hard_cap' => 12,
        'reaction_write_window_seconds' => 60,
        'reaction_write_account_limit' => 120,
    ],
];
