<?php

return [
    'label' => sr_t('deposit::ui.deposit.c7bac029'),
    'order' => 50,
    'items' => [
        [
            'label' => '환경설정',
            'path' => '/admin/deposits/settings',
            'order' => 5,
        ],
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
    ],
];
