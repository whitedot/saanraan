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
            'label' => '시도/보상 내역',
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
            'label' => '퀴즈 매뉴얼',
            'path' => '/admin/quiz/manual',
            'order' => 40,
        ],
    ],
];
