<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
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
    ];
};
