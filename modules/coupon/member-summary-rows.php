<?php

return static function (PDO $pdo, int $accountId): array {
    require_once __DIR__ . '/helpers.php';

    if ($accountId <= 0 || !sr_coupon_usage_enabled($pdo)) {
        return [];
    }

    return [[
        'label' => '쿠폰·이용권',
        'value' => number_format(sr_coupon_usable_account_issue_count($pdo, $accountId)) . '개',
        'url' => '/account/coupons',
        'icon' => 'confirmation_number',
    ]];
};
