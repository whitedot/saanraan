<?php

return [
    'content.basic' => [
        'label' => sr_t('content::layout.basic.label'),
        'provider_module_key' => 'content',
        'provider_label' => sr_t('content::layout.provider'),
        'supports' => ['site', 'content', 'content.home', 'content.group', 'content.view'],
        'style_profile' => 'minimal',
        'views' => [
            'layout' => SR_ROOT . '/modules/content/theme/basic/layout.php',
            'ui_kit' => SR_ROOT . '/modules/content/theme/basic/ui-kit.php',
        ],
    ],
];
