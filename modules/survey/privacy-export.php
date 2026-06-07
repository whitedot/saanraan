<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT r.id, r.survey_id, s.survey_key, s.title, r.status, r.submitted_at, r.rewarded_at
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
            'rows' => $stmt->fetchAll(),
        ],
    ];
};
