<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$depositAdminPage = 'balances';

if (sr_request_method() === 'GET') {
    $account = sr_member_require_login($pdo);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/deposits/balances', 'view');

    $accountIdentifier = sr_get_string('account_identifier', 80);
    if ($accountIdentifier === '') {
        $accountIdentifier = sr_get_string('account_id', 80);
    }

    sr_redirect('/admin/deposits/balances' . ($accountIdentifier !== '' ? '?account_identifier=' . rawurlencode($accountIdentifier) : ''));
}

include SR_ROOT . '/modules/deposit/actions/admin-deposits.php';
