<?php

return [
    'label' => '예치금',
    'order' => 50,
    'items' => [
        [
            'label' => '잔액',
            'path' => '/admin/deposits/balances',
            'order' => 10,
        ],
        [
            'label' => '조정',
            'path' => '/admin/deposits/adjust',
            'order' => 20,
        ],
        [
            'label' => '거래 내역',
            'path' => '/admin/deposits/transactions',
            'order' => 30,
        ],
    ],
];
