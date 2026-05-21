<?php

return [
    'label' => '적립금',
    'order' => 60,
    'items' => [
        [
            'label' => '잔액',
            'path' => '/admin/rewards/balances',
            'order' => 10,
        ],
        [
            'label' => '거래 내역',
            'path' => '/admin/rewards/transactions',
            'order' => 30,
        ],
    ],
];
