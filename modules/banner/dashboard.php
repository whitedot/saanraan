<?php

return [
    [
        'key' => 'banner',
        'title' => sr_t('banner::ui.banner.63182d60'),
        'order' => 20,
        'default_visible' => false,
        'view' => 'views/dashboard-summary.php',
        'rows' => [
            [
                'label' => sr_t('banner::ui.banner.bb1a9b01'),
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_banners WHERE status = 'enabled'",
                'detail_prefix' => sr_t('banner::ui.save.prefix.674b6ae2'),
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_banners WHERE status = 'draft'",
            ],
        ],
    ],
];
