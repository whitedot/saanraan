<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return static function (PDO $pdo, int $accountId): array {
    if (
        $accountId <= 0
        || !sr_reward_usage_enabled($pdo)
        || !sr_reward_withdrawal_requests_enabled($pdo)
        || !sr_reward_account_can_request_withdrawal($pdo, $accountId)
    ) {
        return [];
    }

    $availableAmount = sr_reward_withdrawal_available_amount($pdo, $accountId);
    if ($availableAmount < sr_reward_withdrawal_min_amount()) {
        return [];
    }
    $displayName = sr_reward_display_name($pdo);
    $unitLabel = sr_reward_unit_label($pdo);

    return [
        [
            'label' => $displayName . ' 출금 신청',
            'value' => number_format($availableAmount) . $unitLabel . ' 가능',
            'url' => '/account/rewards#reward-withdrawal-request',
        ],
    ];
};
