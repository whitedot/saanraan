<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/coupon/helpers.php';

$account = sr_member_require_login($pdo);
$coupons = sr_coupon_active_account_issues($pdo, (int) $account['id'], 100);

include SR_ROOT . '/modules/coupon/views/account-coupons.php';
