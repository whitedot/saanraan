<?php

declare(strict_types=1);

if (!function_exists('sr_quiz_privacy_decode_json_object')) {
    function sr_quiz_privacy_decode_json_object(?string $json): array
    {
        $json = trim((string) $json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

return static function (PDO $pdo, int $accountId): array {
    require_once SR_ROOT . '/modules/quiz/helpers.php';

    if ($accountId < 1) {
        return [
            'quiz_attempts' => [],
            'quiz_attempt_answers' => [],
            'quiz_attempt_result_scores' => [],
            'quiz_reward_grants' => [],
            'quiz_comments' => [],
        ];
    }

    $attemptStmt = $pdo->prepare(
        'SELECT id, quiz_id, status, source_module, source_type, source_id, source_title_snapshot, source_url_snapshot,
                return_url, started_at, submitted_at, scored_at, rewarded_at, total_score, passed, selected_result_id,
                answer_snapshot_json, scoring_snapshot_json, result_snapshot_json, created_at, updated_at
         FROM sr_quiz_attempts
         WHERE account_id = :account_id
         ORDER BY id ASC'
    );
    $attemptStmt->execute(['account_id' => $accountId]);
    $attempts = array_map(static function (array $row): array {
        $row['answer_snapshot'] = sr_quiz_privacy_decode_json_object($row['answer_snapshot_json'] ?? null);
        $row['scoring_snapshot'] = sr_quiz_privacy_decode_json_object($row['scoring_snapshot_json'] ?? null);
        $row['result_snapshot'] = sr_quiz_privacy_decode_json_object($row['result_snapshot_json'] ?? null);
        unset($row['answer_snapshot_json'], $row['scoring_snapshot_json'], $row['result_snapshot_json']);

        return $row;
    }, $attemptStmt->fetchAll());

    $answerStmt = $pdo->prepare(
        'SELECT aa.id, aa.attempt_id, aa.question_id, aa.question_key, aa.choice_id, aa.choice_key,
                aa.answer_text, aa.answer_snapshot_json, aa.score_awarded, aa.category_scores_json, aa.created_at
         FROM sr_quiz_attempt_answers aa
         INNER JOIN sr_quiz_attempts a ON a.id = aa.attempt_id
         WHERE a.account_id = :account_id
         ORDER BY aa.attempt_id ASC, aa.id ASC'
    );
    $answerStmt->execute(['account_id' => $accountId]);
    $answers = array_map(static function (array $row): array {
        $row['answer_snapshot'] = sr_quiz_privacy_decode_json_object($row['answer_snapshot_json'] ?? null);
        $row['category_scores'] = sr_quiz_privacy_decode_json_object($row['category_scores_json'] ?? null);
        unset($row['answer_snapshot_json'], $row['category_scores_json']);

        return $row;
    }, $answerStmt->fetchAll());

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
    $resultScores = array_map(static function (array $row): array {
        $row['snapshot'] = sr_quiz_privacy_decode_json_object($row['snapshot_json'] ?? null);
        unset($row['snapshot_json']);

        return $row;
    }, $scoreStmt->fetchAll());

    $comments = [];
    try {
        $commentStmt = $pdo->prepare(
            'SELECT id, quiz_id, parent_comment_id, thread_root_id, depth, author_public_name_snapshot, body_text, extra_values_json, is_secret, status, created_at, updated_at, deleted_at
             FROM sr_quiz_comments
             WHERE author_account_id = :account_id
             ORDER BY id ASC'
        );
        $commentStmt->execute(['account_id' => $accountId]);
        $comments = $commentStmt->fetchAll();
        foreach ($comments as &$comment) {
            $comment['extra_values_json'] = sr_comment_extra_field_export_json((string) ($comment['extra_values_json'] ?? ''));
        }
        unset($comment);
    } catch (Throwable $exception) {
        $comments = [];
    }

    return [
        'quiz_attempts' => $attempts,
        'quiz_attempt_answers' => $answers,
        'quiz_attempt_result_scores' => $resultScores,
        'quiz_reward_grants' => $grants,
        'quiz_comments' => $comments,
    ];
};
