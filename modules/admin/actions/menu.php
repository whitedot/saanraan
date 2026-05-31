<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_owner($pdo, (int) $account['id']);

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_menu_post($pdo, $account);
    sr_admin_flash_result($postResult);
    sr_redirect('/admin/menu');
}

$menuRows = sr_admin_menu_override_form_rows($pdo);

include SR_ROOT . '/modules/admin/views/menu.php';
