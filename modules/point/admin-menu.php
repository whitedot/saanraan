<?php

return [
    'label' => '포인트',
    'order' => 40,
    'items' => [
        [
            'label' => '잔액',
            'path' => '/admin/points/balances',
            'order' => 10,
        ],
        [
            'label' => '거래 내역',
            'path' => '/admin/points/transactions',
            'order' => 30,
        ],
    ],
];
