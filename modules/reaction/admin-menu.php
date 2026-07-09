<?php

return [
    'label' => '리액션 관리',
    'order' => 30,
    'items' => [
        [
            'label' => '리액션 정의',
            'path' => '/admin/reactions',
            'order' => 10,
        ],
        [
            'label' => 'Preset 관리',
            'path' => '/admin/reactions/presets',
            'order' => 20,
        ],
        [
            'label' => '레코드 점검',
            'path' => '/admin/reactions/records',
            'order' => 30,
        ],
        [
            'label' => '알림 템플릿 관리',
            'path' => '/admin/reactions/notification-templates',
            'order' => 40,
        ],
    ],
];
