<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/coupon/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/coupons', 'view');

sr_json_response([
    'items' => sr_coupon_target_search(
        $pdo,
        sr_get_string('reference_type', 60),
        sr_get_string('q', 120),
        20
    ),
]);
