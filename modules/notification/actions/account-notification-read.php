<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/notification/helpers.php';

$account = sr_member_require_login($pdo);
$notificationId = (int) sr_get_string('id', 20);
$token = sr_get_string_without_truncation('token', 32) ?? '';

$nextUrl = sr_notification_mark_read_redirect_link($pdo, $notificationId, (int) $account['id'], $token);

if ($nextUrl !== '') {
    if (sr_is_http_url($nextUrl)) {
        sr_redirect_external($nextUrl);
    }

    sr_redirect($nextUrl);
}

sr_redirect('/account/notifications');
