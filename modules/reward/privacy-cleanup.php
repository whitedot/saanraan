<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    $eventType = (string) ($context['event_type'] ?? '');
    $shouldClean = in_array($eventType, ['anonymize', 'member.anonymized', 'member.status_anonymized', 'member.status_withdrawn'], true);
    if ($accountId < 1 || !$shouldClean) {
        return [
            'cleaned' => false,
            'event_type' => $eventType,
            'reward_withdrawal_request_pii_cleared_count' => 0,
        ];
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_reward_withdrawal_requests
         SET bank_name = '',
             bank_account_number = '',
             bank_account_holder = '',
             requester_note = '',
             admin_note = '',
             updated_at = :updated_at
         WHERE account_id = :account_id
           AND (bank_name <> ''
                OR bank_account_number <> ''
                OR bank_account_holder <> ''
                OR requester_note <> ''
                OR admin_note <> '')"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'updated_at' => sr_now(),
    ]);

    return [
        'cleaned' => true,
        'event_type' => $eventType,
        'reward_withdrawal_request_pii_cleared_count' => $stmt->rowCount(),
    ];
};
