<?php

return [
    [
        'point_key' => 'quiz.home',
        'label' => '퀴즈·테스트 초기화면',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'screen',
                'label' => '화면',
                'kind' => 'content',
            ],
        ],
    ],
    [
        'point_key' => 'quiz.list',
        'label' => '퀴즈·테스트 목록',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'screen',
                'label' => '화면',
                'kind' => 'content',
            ],
        ],
    ],
    [
        'point_key' => 'quiz.view',
        'label' => '퀴즈·테스트 보기',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'screen',
                'label' => '화면',
                'kind' => 'content',
            ],
        ],
    ],
    [
        'point_key' => 'quiz.sidebar.summary',
        'label' => '퀴즈 사이드 요약',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'after_summary',
                'label' => '사이드 요약 다음',
                'kind' => 'content',
            ],
        ],
    ],
];
