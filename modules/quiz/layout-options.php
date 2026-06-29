<?php

return [
    'quiz.basic' => [
        'label' => '퀴즈·테스트 기본 레이아웃',
        'provider_module_key' => 'quiz',
        'provider_label' => '퀴즈·테스트',
        'supports' => ['site', 'quiz', 'quiz.home', 'quiz.view', 'quiz.result'],
        'style_profile' => 'minimal',
        'views' => [
            'layout' => SR_ROOT . '/modules/quiz/theme/basic/layout.php',
            'ui_kit' => SR_ROOT . '/modules/quiz/theme/basic/ui-kit.php',
        ],
    ],
];
