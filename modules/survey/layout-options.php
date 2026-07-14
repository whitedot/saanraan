<?php

return [
    'survey.basic' => [
        'label' => '설문·여론조사 기본 레이아웃',
        'provider_module_key' => 'survey',
        'provider_label' => '설문·여론조사',
        'supports' => [
            'site',
            'content',
            'content.home',
            'content.group',
            'content.view',
            'content.search',
            'community',
            'community.home',
            'community.group',
            'community.list',
            'community.post',
            'community.form',
            'community.search',
            'quiz',
            'quiz.home',
            'quiz.view',
            'quiz.result',
            'survey',
            'survey.home',
            'survey.view',
            'survey.complete',
        ],
        'style_profile' => 'minimal',
        'views' => [
            'layout' => SR_ROOT . '/modules/survey/theme/basic/layout.php',
            'ui_kit' => SR_ROOT . '/modules/survey/theme/basic/ui-kit.php',
        ],
    ],
];
