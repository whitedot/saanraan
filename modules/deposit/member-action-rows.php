<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return static function (PDO $pdo, int $accountId): array {
    if (
        $accountId <= 0
        || !sr_deposit_usage_enabled($pdo)
        || !sr_deposit_refund_requests_enabled($pdo)
        || !sr_deposit_account_can_request_refund($pdo, $accountId)
    ) {
        return [];
    }

    $availableAmount = sr_deposit_refund_available_amount($pdo, $accountId);
    if ($availableAmount < sr_deposit_refund_min_amount()) {
        return [];
    }
    $displayName = sr_deposit_display_name($pdo);
    $unitLabel = sr_deposit_unit_label($pdo);

    return [
        [
            'label' => $displayName . ' 환불 신청',
            'value' => number_format($availableAmount) . $unitLabel . ' 가능',
            'url' => '/account/deposits#deposit-refund-request',
        ],
    ];
};
