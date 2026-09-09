<?php

return [
    [
        'key' => 'coupon',
        'title' => '쿠폰',
        'order' => 32,
        'layout' => 'stats',
        'items' => [
            [
                'label' => '활성 쿠폰',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_coupon_definitions WHERE status = 'active'",
                'overview' => ['role' => 'asset', 'title' => '쿠폰 지급', 'path' => '/admin/coupons'],
                'detail_prefix' => '활성 지급 ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_coupon_issues WHERE status = 'active'",
                'state' => 'success',
                'emphasis' => 'primary',
            ],
            [
                'label' => '최근 7일 사용',
                'value_sql' => 'SELECT COUNT(*) AS value FROM sr_coupon_redemptions WHERE redeemed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                'overview' => ['role' => 'activity', 'path' => '/admin/coupons/redemptions'],
                'detail_prefix' => '환불 처리 ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_coupon_redemptions WHERE status = 'refunded'",
                'state' => 'info',
            ],
        ],
    ],
];
