<?php

return [
    'label' => '쪽지',
    'order' => 31,
    'items' => [
        [
            'label' => '쪽지 환경설정',
            'path' => '/admin/message/settings',
            'order' => 10,
        ],
        [
            'label' => '쪽지 알림/메일 관리',
            'path' => '/admin/message/notification-templates',
            'requires_modules' => ['notification'],
            'order' => 20,
        ],
    ],
];
