<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return static function (PDO $pdo, int $accountId): array {
    if ($accountId <= 0 || !sr_asset_exchange_enabled($pdo)) {
        return [];
    }

    return [
        [
            'label' => '환전 신청',
            'value' => '바로가기',
            'url' => '/account/asset-exchange#asset-exchange-request',
        ],
    ];
};
