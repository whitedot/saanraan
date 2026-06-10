<?php

return [
    [
        'key' => 'deposit',
        'title' => '예치금',
        'order' => 30,
        'layout' => 'stats',
        'items' => [
            [
                'label' => '보유 회원',
                'value_sql' => 'SELECT COUNT(*) AS value FROM sr_deposit_balances WHERE balance > 0',
                'detail_prefix' => '총 잔액 ',
                'detail_sql' => 'SELECT COALESCE(SUM(balance), 0) AS detail FROM sr_deposit_balances',
                'state' => 'info',
                'emphasis' => 'primary',
            ],
            [
                'label' => '환불 대기',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_deposit_refund_requests WHERE status = 'pending'",
                'detail_prefix' => '최근 7일 거래 ',
                'detail_sql' => 'SELECT COUNT(*) AS detail FROM sr_deposit_transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                'state' => 'warning',
            ],
        ],
    ],
];
