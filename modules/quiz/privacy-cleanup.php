<?php

return static function (PDO $pdo, int $accountId): void {
    $pdo->prepare(
        'UPDATE sr_quiz_attempts
         SET account_id = NULL,
             user_agent_hash = NULL,
             ip_hash = NULL
         WHERE account_id = :account_id'
    )->execute(['account_id' => $accountId]);

    $pdo->prepare(
        'UPDATE sr_quiz_reward_grants
         SET account_id = NULL,
             dedupe_key = CONCAT(\'anonymized:quiz_reward:\', id)
         WHERE account_id = :account_id'
    )->execute(['account_id' => $accountId]);

    try {
        $pdo->prepare(
            'UPDATE sr_quiz_comments
             SET author_account_id = NULL,
                 author_public_name_snapshot = \'\'
             WHERE author_account_id = :account_id'
        )->execute(['account_id' => $accountId]);
    } catch (Throwable $exception) {
        // Older installations may not have quiz comments yet.
    }
};
