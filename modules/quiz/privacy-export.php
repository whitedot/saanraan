<?php

return static function (PDO $pdo, int $accountId): array {
    $attemptStmt = $pdo->prepare(
        'SELECT id, quiz_id, status, source_module, source_type, source_id, source_title_snapshot, source_url_snapshot,
                return_url, started_at, submitted_at, scored_at, rewarded_at, total_score, passed,
                answer_snapshot_json, result_snapshot_json, created_at, updated_at
         FROM sr_quiz_attempts
         WHERE account_id = :account_id
         ORDER BY id ASC'
    );
    $attemptStmt->execute(['account_id' => $accountId]);
    $attempts = $attemptStmt->fetchAll();

    $grantStmt = $pdo->prepare(
        'SELECT id, quiz_id, attempt_id, reward_policy_id, source_module, source_type, source_id,
                reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, status,
                provider_reference_type, provider_reference_id, created_at, updated_at, granted_at, failed_at
         FROM sr_quiz_reward_grants
         WHERE account_id = :account_id
         ORDER BY id ASC'
    );
    $grantStmt->execute(['account_id' => $accountId]);
    $grants = $grantStmt->fetchAll();

    return [
        'quiz_attempts' => $attempts,
        'quiz_reward_grants' => $grants,
    ];
};
