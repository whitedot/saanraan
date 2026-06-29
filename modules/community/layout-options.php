<?php

return [
    'community.basic' => [
        'label' => sr_t('community::ui.community.8f453af4'),
        'provider_module_key' => 'community',
        'provider_label' => sr_t('community::ui.community.4a285775'),
        'supports' => [
            'site',
            'content',
            'content.home',
            'content.group',
            'content.view',
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
            'layout' => SR_ROOT . '/modules/community/theme/basic/layout.php',
            'community_home' => SR_ROOT . '/modules/community/theme/basic/home.php',
            'ui_kit' => SR_ROOT . '/modules/community/theme/basic/ui-kit.php',
        ],
    ],
];
