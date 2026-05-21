<?php

declare(strict_types=1);

$rewardAdminPage = 'balances';

if (sr_request_method() === 'GET') {
    $accountIdentifier = sr_get_string('account_identifier', 80);
    if ($accountIdentifier === '') {
        $accountIdentifier = sr_get_string('account_id', 80);
    }

    sr_redirect('/admin/rewards/balances' . ($accountIdentifier !== '' ? '?account_identifier=' . rawurlencode($accountIdentifier) : ''));
}

include SR_ROOT . '/modules/reward/actions/admin-rewards.php';
