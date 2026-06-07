<?php

declare(strict_types=1);

return static function (PDO $pdo, ?array $site): array {
    require_once __DIR__ . '/helpers.php';

    $entries = [
        [
            'loc' => '/survey',
            'lastmod' => substr(sr_now(), 0, 10),
            'changefreq' => 'daily',
            'priority' => '0.6',
        ],
    ];
    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT survey_key, updated_at
         FROM sr_survey_forms
         WHERE status = 'active'
           AND deleted_at IS NULL
           AND (starts_at IS NULL OR starts_at <= :now_start)
           AND (ends_at IS NULL OR ends_at >= :now_end)
         ORDER BY updated_at DESC, id DESC
         LIMIT 1000"
    );
    $stmt->execute([
        'now_start' => $now,
        'now_end' => $now,
    ]);
    foreach ($stmt->fetchAll() as $survey) {
        $surveyKey = (string) ($survey['survey_key'] ?? '');
        if (!sr_survey_key_is_valid($surveyKey)) {
            continue;
        }
        $entries[] = [
            'loc' => '/survey/' . $surveyKey,
            'lastmod' => substr((string) ($survey['updated_at'] ?? ''), 0, 10),
            'changefreq' => 'weekly',
            'priority' => '0.5',
        ];
    }

    return $entries;
};
