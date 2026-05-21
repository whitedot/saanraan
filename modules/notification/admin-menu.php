<?php

return [
    'label' => '알림',
    'order' => 27,
    'items' => [
        [
            'label' => '알림 목록',
            'path' => '/admin/notifications',
            'order' => 10,
        ],
        [
            'label' => '발송 대기열',
            'path' => '/admin/notification-deliveries',
            'order' => 20,
        ],
    ],
];
