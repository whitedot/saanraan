<?php

return [
    'community.basic' => [
        'label' => '커뮤니티 레이아웃',
        'provider_module_key' => 'community',
        'provider_label' => '커뮤니티',
        'supports' => ['site', 'community.home', 'page.view'],
        'views' => [
            'layout' => SR_ROOT . '/layouts/public/basic/layout.php',
            'community_home' => SR_ROOT . '/modules/community/themes/basic/home.php',
        ],
    ],
];
