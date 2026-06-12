<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    $attemptStmt = $pdo->prepare(
        'UPDATE sr_quiz_attempts
         SET account_id = NULL,
             user_agent_hash = NULL,
             ip_hash = NULL
         WHERE account_id = :account_id'
    );
    $attemptStmt->execute(['account_id' => $accountId]);

    $grantStmt = $pdo->prepare(
        'UPDATE sr_quiz_reward_grants
         SET account_id = NULL,
             dedupe_key = CONCAT(\'anonymized:quiz_reward:\', id)
         WHERE account_id = :account_id'
    );
    $grantStmt->execute(['account_id' => $accountId]);

    $commentCount = 0;
    try {
        $commentStmt = $pdo->prepare(
            'UPDATE sr_quiz_comments
             SET author_account_id = NULL,
                 author_public_name_snapshot = \'\'
             WHERE author_account_id = :account_id'
        );
        $commentStmt->execute(['account_id' => $accountId]);
        $commentCount = $commentStmt->rowCount();
    } catch (Throwable $exception) {
        // Older installations may not have quiz comments yet.
        $commentCount = 0;
    }

    return [
        'cleaned' => true,
        'event_type' => (string) ($context['event_type'] ?? ''),
        'quiz_attempt_anonymized_count' => $attemptStmt->rowCount(),
        'quiz_reward_grant_anonymized_count' => $grantStmt->rowCount(),
        'quiz_comment_anonymized_count' => $commentCount,
    ];
};
