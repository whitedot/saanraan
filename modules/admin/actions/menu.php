<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_menu_post($pdo, $account);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
}

$menuRows = sr_admin_menu_override_form_rows($pdo);

include SR_ROOT . '/modules/admin/views/menu.php';
