<?php

declare(strict_types=1);

$notificationAdminPage = 'list';
$notificationCreateModalOpen = true;
$_POST['intent'] = 'create';

include SR_ROOT . '/modules/notification/actions/admin-notifications.php';
