<?php

return [
    'name' => '적립금',
    'version' => '2026.06.003',
    'type' => 'module',
    'description' => '회원 적립금 잔액과 거래 장부 모듈입니다.',
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
        'menu_order' => 40,
        'icon' => ['type' => 'symbol', 'name' => 'savings'],
        'stylesheets' => ['assets/admin.css'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
    ],
    'requires' => [
        'modules' => ['member', 'admin', 'asset_ledger'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'menu-links.php',
            'privacy-export.php',
            'privacy-cleanup.php',
            'asset-exchange.php',
            'member-assets.php',
            'member-withdrawal-assets.php',
            'member-action-rows.php',
            'member-group-references.php',
            'dashboard.php',
        ],
        'consumes' => [
            'notification-events.php',
        ],
    ],
    'settings' => [
        'usage_enabled' => true,
        'display_name' => '적립금',
        'unit_label' => '원',
        'default_expiration_days' => '0',
        'withdrawal_requests_enabled' => false,
        'identity_withdrawal_required' => false,
        'withdrawal_allowed_group_keys_json' => '[]',
        'notification_cases' => [
            'transaction_grant' => ['event_key' => 'transaction.grant', 'enabled' => true, 'channels' => ['site']],
            'transaction_refund' => ['event_key' => 'transaction.refund', 'enabled' => true, 'channels' => ['site']],
            'transaction_exchange_in' => ['event_key' => 'transaction.exchange_in', 'enabled' => true, 'channels' => ['site']],
            'transaction_use' => ['event_key' => 'transaction.use', 'enabled' => true, 'channels' => ['site']],
            'transaction_exchange_out' => ['event_key' => 'transaction.exchange_out', 'enabled' => true, 'channels' => ['site']],
            'transaction_exchange_fee' => ['event_key' => 'transaction.exchange_fee', 'enabled' => true, 'channels' => ['site']],
            'transaction_expire' => ['event_key' => 'transaction.expire', 'enabled' => true, 'channels' => ['site']],
            'transaction_withdraw' => ['event_key' => 'transaction.withdraw', 'enabled' => true, 'channels' => ['site']],
            'transaction_reclaim' => ['event_key' => 'transaction.reclaim', 'enabled' => true, 'channels' => ['site']],
            'transaction_adjustment_increase' => ['event_key' => 'transaction.adjustment.increase', 'enabled' => true, 'channels' => ['site']],
            'transaction_adjustment_decrease' => ['event_key' => 'transaction.adjustment.decrease', 'enabled' => true, 'channels' => ['site']],
        ],
    ],
];
