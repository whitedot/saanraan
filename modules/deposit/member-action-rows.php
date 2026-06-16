<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return static function (PDO $pdo, int $accountId): array {
    if (
        $accountId <= 0
        || !sr_deposit_refund_requests_enabled($pdo)
        || !sr_deposit_account_can_request_refund($pdo, $accountId)
    ) {
        return [];
    }

    $availableAmount = sr_deposit_refund_available_amount($pdo, $accountId);
    if ($availableAmount < sr_deposit_refund_min_amount()) {
        return [];
    }

    return [
        [
            'label' => '예치금 환불 신청',
            'value' => number_format($availableAmount) . '원 가능',
            'url' => '/account/deposits#deposit-refund-request',
        ],
    ];
};
