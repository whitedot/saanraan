<?php

return [
    'community.basic' => [
        'label' => sr_t('community::ui.community.8f453af4'),
        'provider_module_key' => 'community',
        'provider_label' => sr_t('community::ui.community.4a285775'),
        'supports' => ['site', 'community.home', 'content.view'],
        'views' => [
            'layout' => SR_ROOT . '/layouts/public/basic/layout.php',
            'community_home' => SR_ROOT . '/modules/community/themes/basic/home.php',
        ],
    ],
];
