<?php

return [
    'label' => '설문',
    'order' => 32,
    'items' => [
        [
            'label' => '설문 관리',
            'path' => '/admin/surveys',
            'order' => 10,
        ],
        [
            'label' => '응답 관리',
            'path' => '/admin/surveys/responses',
            'order' => 20,
        ],
        [
            'label' => '통계',
            'path' => '/admin/surveys/statistics',
            'order' => 30,
        ],
    ],
];
