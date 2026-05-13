<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$errors = [];
$notice = '';

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    $postResult = toy_admin_handle_menu_post($pdo, $account);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
}

$menuRows = toy_admin_menu_override_form_rows($pdo);

include TOY_ROOT . '/modules/admin/views/menu.php';
