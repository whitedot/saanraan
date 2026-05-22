<?php

return [
    [
        'key' => 'notification',
        'title' => sr_t('notification::ui.notification.12ddd6ca'),
        'order' => 40,
        'view' => 'views/dashboard-summary.php',
        'rows' => [
            [
                'label' => sr_t('notification::ui.all.notification.cff83725'),
                'value_sql' => 'SELECT COUNT(*) AS value FROM sr_notifications',
                'detail_prefix' => sr_t('notification::ui.text.send.waiting.prefix.02b6c547'),
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_notification_deliveries WHERE status = 'queued'",
            ],
        ],
    ],
];
