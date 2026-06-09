<?php

return static function (PDO $pdo, int $accountId): array {
    require_once SR_ROOT . '/modules/quiz/helpers.php';

    $attemptStmt = $pdo->prepare(
        'SELECT id, quiz_id, status, source_module, source_type, source_id, source_title_snapshot, source_url_snapshot,
                return_url, started_at, submitted_at, scored_at, rewarded_at, total_score, passed, selected_result_id,
                answer_snapshot_json, scoring_snapshot_json, result_snapshot_json, created_at, updated_at
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

    $scoreStmt = $pdo->prepare(
        'SELECT s.id, s.attempt_id, s.result_id, s.category_key, s.score_value, s.is_selected, s.snapshot_json, s.created_at
         FROM sr_quiz_attempt_result_scores s
         INNER JOIN sr_quiz_attempts a ON a.id = s.attempt_id
         WHERE a.account_id = :account_id
         ORDER BY s.attempt_id ASC, s.id ASC'
    );
    $scoreStmt->execute(['account_id' => $accountId]);
    $resultScores = $scoreStmt->fetchAll();

    $comments = [];
    try {
        $threadSelectSql = function_exists('sr_quiz_comment_thread_columns_exist') && sr_quiz_comment_thread_columns_exist($pdo)
            ? 'parent_comment_id, thread_root_id, depth,'
            : 'NULL AS parent_comment_id, id AS thread_root_id, 1 AS depth,';
        $commentStmt = $pdo->prepare(
            'SELECT id, quiz_id, ' . $threadSelectSql . ' author_public_name_snapshot, body_text, is_secret, status, created_at, updated_at, deleted_at
             FROM sr_quiz_comments
             WHERE author_account_id = :account_id
             ORDER BY id ASC'
        );
        $commentStmt->execute(['account_id' => $accountId]);
        $comments = $commentStmt->fetchAll();
    } catch (Throwable $exception) {
        $comments = [];
    }

    return [
        'quiz_attempts' => $attempts,
        'quiz_attempt_result_scores' => $resultScores,
        'quiz_reward_grants' => $grants,
        'quiz_comments' => $comments,
    ];
};
