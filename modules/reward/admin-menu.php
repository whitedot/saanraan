<?php

return [
    'label' => sr_t('reward::ui.text.d20e87cb'),
    'order' => 60,
    'items' => [
        [
            'label' => sr_t('reward::ui.text.abe10d3e'),
            'path' => '/admin/rewards/balances',
            'order' => 10,
        ],
        [
            'label' => sr_t('reward::ui.text.abaae118'),
            'path' => '/admin/rewards/transactions',
            'order' => 30,
        ],
        [
            'label' => '적립금 출금 신청',
            'path' => '/admin/rewards/withdrawal-requests',
            'order' => 40,
        ],
        [
            'label' => '적립금 환경설정',
            'path' => '/admin/rewards/settings',
            'order' => 50,
        ],
        [
            'label' => '적립금 알림/메일 관리',
            'path' => '/admin/rewards/notification-templates',
            'requires_modules' => ['notification'],
            'order' => 55,
        ],
    ],
];
