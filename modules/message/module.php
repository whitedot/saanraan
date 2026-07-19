<?php

return [
    'name' => '쪽지',
    'version' => '2026.07.003',
    'type' => 'module',
    'description' => '회원 간 쪽지 수발신을 제공하는 모듈입니다.',
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
        'menu_order' => 13,
        'icon' => ['type' => 'symbol', 'name' => 'message-circle'],
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
            'menu-links.php',
            'member-only-routes.php',
            'member-registration.php',
            'privacy-export.php',
            'privacy-cleanup.php',
            'report-targets.php',
            'public-message-summary.php',
        ],
        'consumes' => [
            'notification-events.php',
            'member-assets.php',
        ],
    ],
    'settings' => [
        'message_enabled' => true,
        'send_policy' => 'all',
        'receive_policy' => 'all',
        'send_group_keys' => [],
        'receive_group_keys' => [],
        'member_receive_opt_enabled' => true,
        'default_member_receive_enabled' => true,
        'message_create_window_seconds' => 300,
        'message_create_limit' => 20,
        'message_charge_enabled' => false,
        'message_charge_asset_module' => '',
        'message_charge_amount' => 0,
        'message_charge_settlement_currency' => 'KRW',
        'message_charge_amounts_json' => '',
        'message_charge_group_policies_json' => '',
        'message_charge_policy_set_id' => 0,
        'notification_cases' => [
            'message_received' => [
                'event_key' => 'message.received',
                'enabled' => true,
                'channels' => ['site'],
            ],
        ],
    ],
];
