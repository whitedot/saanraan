<?php

return [
    'label' => '퀴즈·테스트',
    'order' => 30,
    'items' => [
        [
            'label' => '퀴즈 관리',
            'path' => '/admin/quiz',
            'order' => 10,
        ],
        [
            'label' => '리워드 로그',
            'path' => '/admin/quiz/attempts',
            'order' => 20,
        ],
        [
            'label' => '댓글 관리',
            'path' => '/admin/quiz/comments',
            'order' => 25,
        ],
        [
            'label' => '퀴즈 환경설정',
            'path' => '/admin/quiz/settings',
            'order' => 30,
        ],
        [
            'label' => '임베드 캐시',
            'path' => '/admin/quiz/embed-cache',
            'order' => 35,
        ],
    ],
];
