<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    require_once SR_ROOT . '/modules/survey/helpers.php';

    if ($accountId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT r.id, r.survey_id, s.survey_key, s.title, r.status, r.quality_status, r.quality_note,
                r.consent_snapshot_json, r.metadata_snapshot_json, r.answer_snapshot_json,
                r.submitted_at, r.rewarded_at
         FROM sr_survey_responses r
         INNER JOIN sr_survey_forms s ON s.id = r.survey_id
         WHERE r.account_id = :account_id
         ORDER BY r.submitted_at DESC, r.id DESC'
    );
    $stmt->execute(['account_id' => $accountId]);
    $responses = $stmt->fetchAll();
    $responseIds = array_values(array_filter(array_map(static function (array $row): int {
        return (int) ($row['id'] ?? 0);
    }, $responses)));

    $answersByResponseId = [];
    if ($responseIds !== []) {
        $placeholders = implode(', ', array_fill(0, count($responseIds), '?'));
        $answerStmt = $pdo->prepare(
            'SELECT response_id, question_id, question_key, choice_id, choice_key,
                    answer_text, answer_number, other_text, answer_snapshot_json, created_at
             FROM sr_survey_response_answers
             WHERE response_id IN (' . $placeholders . ')
             ORDER BY response_id ASC, id ASC'
        );
        $answerStmt->execute($responseIds);
        foreach ($answerStmt->fetchAll() as $answerRow) {
            $responseId = (int) ($answerRow['response_id'] ?? 0);
            if ($responseId < 1) {
                continue;
            }

            $answersByResponseId[$responseId][] = [
                'question_id' => (int) ($answerRow['question_id'] ?? 0),
                'question_key' => (string) ($answerRow['question_key'] ?? ''),
                'choice_id' => $answerRow['choice_id'] === null ? null : (int) $answerRow['choice_id'],
                'choice_key' => $answerRow['choice_key'] === null ? null : (string) $answerRow['choice_key'],
                'answer_text' => $answerRow['answer_text'] === null ? null : (string) $answerRow['answer_text'],
                'answer_number' => $answerRow['answer_number'] === null ? null : (string) $answerRow['answer_number'],
                'other_text' => $answerRow['other_text'] === null ? null : (string) $answerRow['other_text'],
                'answer_snapshot' => json_decode((string) ($answerRow['answer_snapshot_json'] ?? '{}'), true) ?: [],
                'created_at' => (string) ($answerRow['created_at'] ?? ''),
            ];
        }
    }

    $comments = [];
    try {
        $threadSelectSql = function_exists('sr_survey_comment_thread_columns_exist') && sr_survey_comment_thread_columns_exist($pdo)
            ? 'c.parent_comment_id, c.thread_root_id, c.depth,'
            : 'NULL AS parent_comment_id, c.id AS thread_root_id, 1 AS depth,';
        $commentStmt = $pdo->prepare(
            'SELECT c.id, c.survey_id, ' . $threadSelectSql . ' s.survey_key, s.title, c.author_public_name_snapshot,
                    c.body_text, c.is_secret, c.status, c.created_at, c.updated_at, c.deleted_at
             FROM sr_survey_comments c
             INNER JOIN sr_survey_forms s ON s.id = c.survey_id
             WHERE c.author_account_id = :account_id
             ORDER BY c.id ASC'
        );
        $commentStmt->execute(['account_id' => $accountId]);
        $comments = $commentStmt->fetchAll();
    } catch (Throwable $exception) {
        $comments = [];
    }

    return [
        [
            'key' => 'survey.responses',
            'label' => '설문 응답',
            'rows' => array_map(static function (array $row) use ($answersByResponseId): array {
                $responseId = (int) ($row['id'] ?? 0);
                return [
                    'id' => $responseId,
                    'survey_id' => (int) ($row['survey_id'] ?? 0),
                    'survey_key' => (string) ($row['survey_key'] ?? ''),
                    'survey_title' => (string) ($row['title'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'quality_status' => (string) ($row['quality_status'] ?? ''),
                    'quality_note' => (string) ($row['quality_note'] ?? ''),
                    'consent_snapshot' => json_decode((string) ($row['consent_snapshot_json'] ?? '{}'), true) ?: [],
                    'metadata_snapshot' => json_decode((string) ($row['metadata_snapshot_json'] ?? '{}'), true) ?: [],
                    'answer_snapshot' => json_decode((string) ($row['answer_snapshot_json'] ?? '{}'), true) ?: [],
                    'answers' => $answersByResponseId[$responseId] ?? [],
                    'submitted_at' => (string) ($row['submitted_at'] ?? ''),
                    'rewarded_at' => (string) ($row['rewarded_at'] ?? ''),
                ];
            }, $responses),
        ],
        [
            'key' => 'survey.comments',
            'label' => '설문 댓글',
            'rows' => array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'survey_id' => (int) ($row['survey_id'] ?? 0),
                    'survey_key' => (string) ($row['survey_key'] ?? ''),
                    'survey_title' => (string) ($row['title'] ?? ''),
                    'author_public_name_snapshot' => (string) ($row['author_public_name_snapshot'] ?? ''),
                    'body_text' => (string) ($row['body_text'] ?? ''),
                    'is_secret' => (int) ($row['is_secret'] ?? 0) === 1,
                    'status' => (string) ($row['status'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                    'deleted_at' => $row['deleted_at'] === null ? null : (string) $row['deleted_at'],
                ];
            }, $comments),
        ],
    ];
};
