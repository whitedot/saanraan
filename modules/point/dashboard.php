<?php

return [
    [
        'key' => 'point',
        'title' => '포인트',
        'order' => 20,
        'layout' => 'stats',
        'items' => [
            [
                'label' => '보유 회원',
                'value_sql' => 'SELECT COUNT(*) AS value FROM sr_point_balances WHERE balance > 0',
                'overview' => ['role' => 'asset', 'title' => '포인트 총 잔액', 'path' => '/admin/points'],
                'detail_prefix' => '총 잔액 ',
                'detail_sql' => 'SELECT COALESCE(SUM(balance), 0) AS detail FROM sr_point_balances',
                'state' => 'info',
                'emphasis' => 'primary',
            ],
            [
                'label' => '최근 7일 거래',
                'value_sql' => 'SELECT COUNT(*) AS value FROM sr_point_transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                'overview' => ['role' => 'activity', 'path' => '/admin/points/transactions'],
                'detail_prefix' => '만료 예정 ',
                'detail_sql' => 'SELECT COALESCE(SUM(expires_remaining), 0) AS detail FROM sr_point_transactions WHERE expires_at IS NOT NULL AND expires_at >= NOW() AND expires_remaining > 0',
                'state' => 'warning',
            ],
        ],
    ],
];
