<?php

return [
    [
        'key' => 'asset_exchange',
        'title' => '환전',
        'order' => 34,
        'layout' => 'stats',
        'items' => [
            [
                'label' => '활성 정책',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_asset_exchange_policies WHERE status = 'enabled'",
                'detail_prefix' => '전체 정책 ',
                'detail_sql' => 'SELECT COUNT(*) AS detail FROM sr_asset_exchange_policies',
                'state' => 'info',
                'emphasis' => 'primary',
            ],
            [
                'label' => '최근 7일 성공',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_asset_exchange_logs WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                'detail_prefix' => '실패 ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_asset_exchange_logs WHERE status = 'failed'",
                'state' => 'warning',
            ],
        ],
    ],
];
