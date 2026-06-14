<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/member_oauth/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_owner($pdo, (int) $account['id']);
$settings = sr_member_oauth_settings($pdo);
$notice = '';
$errors = [];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
}

include SR_ROOT . '/modules/member_oauth/views/admin-settings.php';
