<?php

return [
    'label' => sr_t('point::ui.point.e5f25ab0'),
    'order' => 40,
    'items' => [
        [
            'label' => sr_t('point::ui.point.47719e8e'),
            'path' => '/admin/points/balances',
            'order' => 10,
        ],
        [
            'label' => sr_t('point::ui.point.cd2b311f'),
            'path' => '/admin/points/transactions',
            'order' => 30,
        ],
        [
            'label' => sr_t('point::ui.settings.menu'),
            'path' => '/admin/points/settings',
            'order' => 40,
        ],
        [
            'label' => '포인트 알림/메일 관리',
            'path' => '/admin/points/notification-templates',
            'requires_modules' => ['notification'],
            'order' => 45,
        ],
    ],
];
