<?php

return [
    [
        'key' => 'community',
        'title' => sr_t('community::ui.community.4a285775'),
        'order' => 50,
        'view' => 'views/dashboard-summary.php',
        'items' => [
            [
                'label' => sr_t('community::ui.text.0b138cfe'),
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_community_posts WHERE status = 'published'",
                'detail_prefix' => sr_t('community::ui.text.c9fff683') . ' ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_community_comments WHERE status = 'published'",
                'state' => 'info',
                'emphasis' => 'primary',
            ],
            [
                'label' => sr_t('community::ui.text.bbb56c63'),
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_community_reports WHERE status = 'open'",
                'detail_prefix' => sr_t('community::ui.text.4732a58f') . ' ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_community_boards WHERE status = 'enabled'",
                'state' => 'warning',
            ],
        ],
    ],
];
