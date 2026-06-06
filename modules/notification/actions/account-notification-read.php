<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/notification/helpers.php';

$account = sr_member_require_login($pdo);
$notificationId = (int) sr_get_string('id', 20);
$nextUrl = sr_notification_clean_link_url(sr_get_string('next', 255));

sr_notification_mark_read($pdo, $notificationId, (int) $account['id']);

if ($nextUrl !== '') {
    if (sr_is_http_url($nextUrl)) {
        sr_redirect_external($nextUrl);
    }

    sr_redirect($nextUrl);
}

sr_redirect('/account/notifications');
