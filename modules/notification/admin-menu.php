<?php

return [
    'label' => sr_t('notification::ui.notification.12ddd6ca'),
    'order' => 10,
    'items' => [
        [
            'label' => '운영 알림',
            'path' => '/admin/admin-notifications',
            'order' => 25,
        ],
        [
            'label' => sr_t('notification::ui.notification.list.7475cac1'),
            'path' => '/admin/notifications',
            'order' => 10,
        ],
        [
            'label' => sr_t('notification::ui.text.077631f5'),
            'path' => '/admin/notification-deliveries',
            'order' => 20,
        ],
        [
            'label' => sr_t('notification::ui.settings.845f5c6c'),
            'path' => '/admin/notifications/settings',
            'order' => 30,
        ],
    ],
];
