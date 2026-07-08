<?php

return [
    'name' => '쿠폰·이용권',
    'version' => '2026.06.009',
    'type' => 'module',
    'description' => '회원별 쿠폰 종류, 지급, 사용 내역을 관리합니다.',
    'admin' => [
        'category' => 'member',
        'category_label' => '회원',
        'category_order' => 10,
        'menu_order' => 60,
        'icon' => ['type' => 'symbol', 'name' => 'ticket'],
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
            'retention-targets.php',
            'member-withdrawal-assets.php',
            'member-summary-rows.php',
            'coupon-references.php',
            'dashboard.php',
            'url-embed-targets.php',
        ],
        'consumes' => [
            'coupon-references.php',
            'coupon-targets.php',
            'notification-events.php',
        ],
    ],
    'settings' => [
        'usage_enabled' => true,
        'coupon_zone_label' => '쿠폰존',
        'notification_cases' => [
            'issue_created' => ['event_key' => 'issue.created', 'enabled' => true, 'channels' => ['site']],
            'redemption_redeemed' => ['event_key' => 'redemption.redeemed', 'enabled' => true, 'channels' => ['site']],
            'redemption_refunded' => ['event_key' => 'redemption.refunded', 'enabled' => true, 'channels' => ['site']],
            'issue_refunded' => ['event_key' => 'issue.refunded', 'enabled' => true, 'channels' => ['site']],
            'issue_status_updated' => ['event_key' => 'issue.status_updated', 'enabled' => true, 'channels' => ['site']],
            'definition_disabled' => ['event_key' => 'issue.definition_disabled', 'enabled' => true, 'channels' => ['site']],
        ],
        'disabled_reclaim_notifications_enabled' => true,
        'disabled_reclaim_notification_event_key' => 'issue.definition_disabled',
        'disabled_reclaim_notification_channels' => ['site'],
    ],
];
