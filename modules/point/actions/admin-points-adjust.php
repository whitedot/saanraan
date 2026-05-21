<?php

declare(strict_types=1);

$pointAdminPage = 'balances';

if (sr_request_method() === 'GET') {
    $accountIdentifier = sr_get_string('account_identifier', 80);
    if ($accountIdentifier === '') {
        $accountIdentifier = sr_get_string('account_id', 80);
    }

    sr_redirect('/admin/points/balances' . ($accountIdentifier !== '' ? '?account_identifier=' . rawurlencode($accountIdentifier) : ''));
}

include SR_ROOT . '/modules/point/actions/admin-points.php';
