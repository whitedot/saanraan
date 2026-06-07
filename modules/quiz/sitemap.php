<?php

declare(strict_types=1);

return static function (PDO $pdo, ?array $site): array {
    require_once __DIR__ . '/helpers.php';

    $entries = [
        [
            'loc' => '/quiz',
            'lastmod' => substr(sr_now(), 0, 10),
            'changefreq' => 'daily',
            'priority' => '0.6',
        ],
    ];

    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT quiz_key, updated_at
         FROM sr_quiz_sets
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

    foreach ($stmt->fetchAll() as $quiz) {
        $quizKey = (string) ($quiz['quiz_key'] ?? '');
        if (!sr_quiz_key_is_valid($quizKey)) {
            continue;
        }

        $entries[] = [
            'loc' => '/quiz/' . $quizKey,
            'lastmod' => substr((string) ($quiz['updated_at'] ?? ''), 0, 10),
            'changefreq' => 'weekly',
            'priority' => '0.5',
        ];
    }

    return $entries;
};
