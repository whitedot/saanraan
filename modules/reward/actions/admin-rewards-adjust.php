<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$rewardAdminPage = 'balances';

if (sr_request_method() === 'GET') {
    $account = sr_member_require_login($pdo);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/rewards/balances', 'view');

    $accountIdentifier = sr_get_string('account_identifier', 80);
    if ($accountIdentifier === '') {
        $accountIdentifier = sr_get_string('account_id', 80);
    }

    sr_redirect('/admin/rewards/balances' . ($accountIdentifier !== '' ? '?account_identifier=' . rawurlencode($accountIdentifier) : ''));
}

include SR_ROOT . '/modules/reward/actions/admin-rewards.php';
