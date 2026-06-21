<?php

return [
    'survey.basic' => [
        'label' => '설문·여론조사 기본 레이아웃',
        'provider_module_key' => 'survey',
        'provider_label' => '설문·여론조사',
        'supports' => ['site', 'survey.home', 'survey.view'],
        'style_profile' => 'minimal',
        'views' => [
            'layout' => SR_ROOT . '/modules/survey/layouts/basic/layout.php',
        ],
    ],
];
