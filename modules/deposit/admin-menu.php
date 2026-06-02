<?php

return [
    'label' => sr_t('deposit::ui.deposit.c7bac029'),
    'order' => 50,
    'items' => [
        [
            'label' => sr_t('deposit::ui.deposit.2a642cec'),
            'path' => '/admin/deposits/balances',
            'order' => 10,
        ],
        [
            'label' => sr_t('deposit::ui.deposit.93f727b8'),
            'path' => '/admin/deposits/transactions',
            'order' => 30,
        ],
        [
            'label' => '예치금 환불 신청',
            'path' => '/admin/deposits/refund-requests',
            'order' => 40,
        ],
        [
            'label' => '환경설정',
            'path' => '/admin/deposits/settings',
            'order' => 50,
        ],
    ],
];
