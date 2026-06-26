<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return static function (PDO $pdo): array {
    if (!sr_coupon_usage_enabled($pdo)) {
        return [];
    }

    return [
        [
            'label' => '쿠폰존',
            'url' => '/coupons',
        ],
        [
            'label' => '보유 쿠폰·이용권',
            'url' => '/account/coupons',
        ],
    ];
};
