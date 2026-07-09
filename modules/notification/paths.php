<?php

return [
    'GET /admin/admin-notifications' => 'actions/admin-admin-notifications.php',
    'POST /admin/admin-notifications' => 'actions/admin-admin-notifications.php',
    'GET /admin/notifications' => 'actions/admin-notifications.php',
    'POST /admin/notifications' => 'actions/admin-notifications.php',
    'GET /admin/notifications/settings' => 'actions/admin-notification-settings.php',
    'POST /admin/notifications/settings' => 'actions/admin-notification-settings.php',
    'GET /admin/notifications/templates' => 'actions/admin-event-templates.php',
    'POST /admin/notifications/templates' => 'actions/admin-event-templates.php',
    'GET /admin/notifications/new' => 'actions/admin-notification-new.php',
    'POST /admin/notifications/create' => 'actions/admin-notification-create.php',
    'POST /admin/notifications/delete' => 'actions/admin-notification-delete.php',
    'GET /admin/notification-deliveries' => 'actions/admin-notification-deliveries.php',
    'POST /admin/notification-deliveries' => 'actions/admin-notification-deliveries.php',
    'POST /admin/notification-deliveries/status' => 'actions/admin-notification-delivery-status.php',
    'GET /account/notifications' => 'actions/account-notifications.php',
    'POST /account/notifications' => 'actions/account-notifications.php',
    'GET /account/notifications/read' => 'actions/account-notification-read.php',
];
