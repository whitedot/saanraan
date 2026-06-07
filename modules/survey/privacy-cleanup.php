<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_survey_responses
         SET account_id = NULL,
             user_agent_hash = NULL,
             ip_hash = NULL,
             updated_at = :updated_at
         WHERE account_id = :account_id'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'updated_at' => sr_now(),
    ]);
    $responseCount = $stmt->rowCount();

    $grantStmt = $pdo->prepare(
        'UPDATE sr_survey_reward_grants
         SET account_id = NULL,
             dedupe_key = CONCAT(\'anonymized:survey_reward:\', id),
             updated_at = :updated_at
         WHERE account_id = :account_id'
    );
    $grantStmt->execute([
        'account_id' => $accountId,
        'updated_at' => sr_now(),
    ]);

    return [
        'cleaned' => true,
        'survey_response_anonymized_count' => $responseCount,
        'survey_reward_grant_anonymized_count' => $grantStmt->rowCount(),
    ];
};
