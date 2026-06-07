<?php

return [
    'quiz.basic' => [
        'label' => '퀴즈 기본 레이아웃',
        'provider_module_key' => 'quiz',
        'provider_label' => '퀴즈',
        'supports' => ['site', 'quiz.home', 'quiz.view'],
        'style_profile' => 'minimal',
        'views' => [
            'layout' => SR_ROOT . '/modules/quiz/layouts/basic/layout.php',
        ],
    ],
];
