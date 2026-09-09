<?php

return [
    [
        'key' => 'reward',
        'title' => '적립금',
        'order' => 25,
        'layout' => 'stats',
        'items' => [
            [
                'label' => '보유 회원',
                'value_sql' => 'SELECT COUNT(*) AS value FROM sr_reward_balances WHERE balance > 0',
                'overview' => ['role' => 'asset', 'title' => '적립금 총 잔액', 'path' => '/admin/rewards'],
                'detail_prefix' => '총 잔액 ',
                'detail_sql' => 'SELECT COALESCE(SUM(balance), 0) AS detail FROM sr_reward_balances',
                'state' => 'success',
                'emphasis' => 'primary',
            ],
            [
                'label' => '출금 대기',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_reward_withdrawal_requests WHERE status = 'pending'",
                'overview' => ['role' => 'task', 'path' => '/admin/rewards/withdrawal-requests?status%5B%5D=pending'],
                'detail_prefix' => '최근 7일 거래 ',
                'detail_sql' => 'SELECT COUNT(*) AS detail FROM sr_reward_transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                'state' => 'warning',
            ],
        ],
    ],
];
