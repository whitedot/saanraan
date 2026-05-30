<?php

return [
    'content.basic' => [
        'label' => sr_t('content::layout.basic.label'),
        'provider_module_key' => 'content',
        'provider_label' => sr_t('content::layout.provider'),
        'supports' => ['site', 'content.view', 'content.group'],
        'views' => [
            'layout' => SR_ROOT . '/layouts/public/basic/layout.php',
        ],
    ],
];
