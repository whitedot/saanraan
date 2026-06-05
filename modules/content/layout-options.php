<?php

return [
    'content.basic' => [
        'label' => sr_t('content::layout.basic.label'),
        'provider_module_key' => 'content',
        'provider_label' => sr_t('content::layout.provider'),
        'supports' => ['site', 'content.home', 'content.view', 'content.group'],
        'style_profile' => 'minimal',
        'views' => [
            'layout' => SR_ROOT . '/modules/content/layouts/basic/layout.php',
        ],
    ],
];
