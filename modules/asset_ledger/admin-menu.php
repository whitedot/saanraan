<?php

return [
    'label' => '포인트/금액 점검',
    'order' => 85,
    'items' => [
        [
            'label' => '정합성 점검',
            'path' => '/admin/assets/reconciliation',
            'order' => 10,
        ],
        [
            'label' => '보상 미회수',
            'path' => '/admin/assets/recovery-failures',
            'order' => 20,
        ],
    ],
];
