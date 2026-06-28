<?php

return [
    'label' => '설문·여론조사',
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
            'label' => '리워드 로그',
            'path' => '/admin/surveys/reward-logs',
            'order' => 25,
        ],
        [
            'label' => '통계',
            'path' => '/admin/surveys/statistics',
            'order' => 30,
        ],
        [
            'label' => '댓글 관리',
            'path' => '/admin/surveys/comments',
            'order' => 35,
        ],
        [
            'label' => '환경설정',
            'path' => '/admin/surveys/settings',
            'order' => 40,
        ],
        [
            'label' => '임베드 캐시',
            'path' => '/admin/surveys/embed-cache',
            'order' => 45,
        ],
        [
            'label' => '매뉴얼',
            'path' => '/admin/surveys/manual',
            'order' => 50,
        ],
    ],
];
