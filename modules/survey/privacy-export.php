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

    return [
        [
            'key' => 'survey.responses',
            'label' => '설문 응답',
            'rows' => array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'survey_id' => (int) ($row['survey_id'] ?? 0),
                    'survey_key' => (string) ($row['survey_key'] ?? ''),
                    'survey_title' => (string) ($row['title'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'quality_status' => (string) ($row['quality_status'] ?? ''),
                    'quality_note' => (string) ($row['quality_note'] ?? ''),
                    'consent_snapshot' => json_decode((string) ($row['consent_snapshot_json'] ?? '{}'), true) ?: [],
                    'metadata_snapshot' => json_decode((string) ($row['metadata_snapshot_json'] ?? '{}'), true) ?: [],
                    'answer_snapshot' => json_decode((string) ($row['answer_snapshot_json'] ?? '{}'), true) ?: [],
                    'submitted_at' => (string) ($row['submitted_at'] ?? ''),
                    'rewarded_at' => (string) ($row['rewarded_at'] ?? ''),
                ];
            }, $stmt->fetchAll()),
        ],
    ];
};
