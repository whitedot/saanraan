<?php

return [
    'name' => '포인트/금액 환전',
    'version' => '2026.06.001',
    'type' => 'module',
    'description' => '포인트/금액 항목 간 환전 기준값, 실행 로그, 정정 흐름을 관리합니다.',
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
        'menu_order' => 60,
        'icon' => ['type' => 'symbol', 'name' => 'wallet'],
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
            'menu-links.php',
            'privacy-export.php',
            'member-action-rows.php',
            'dashboard.php',
        ],
        'consumes' => [
            'asset-exchange.php',
            'notification-events.php',
        ],
    ],
    'settings' => [
        'exchange_enabled' => '1',
        'policy_default_status' => 'disabled',
        'relative_value_point' => '1',
        'relative_value_reward' => '1',
        'relative_value_deposit' => '1',
        'policy_default_min_amount' => '1',
        'policy_default_max_amount' => '',
        'policy_default_rounding_mode' => 'floor',
        'policy_default_fee_trigger' => 'none',
        'policy_default_fee_basis' => 'to_amount',
        'policy_default_fee_type' => 'rate',
        'policy_default_fee_rate_numerator' => '0',
        'policy_default_fee_fixed_amount' => '0',
        'policy_default_fee_min_amount' => '',
        'policy_default_fee_max_amount' => '',
    ],
];
