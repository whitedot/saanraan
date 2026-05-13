<?php

declare(strict_types=1);

$notificationAdminPage = 'list';
$_POST['intent'] = 'delete_notification';

include SR_ROOT . '/modules/notification/actions/admin-notifications.php';
