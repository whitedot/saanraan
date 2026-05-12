<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    $empty = [
        'posts' => [],
        'comments' => [],
        'attachments' => [],
        'reports' => [],
        'messages' => [],
        'scraps' => [],
        'level' => [],
        'level_logs' => [],
    ];

    if ($accountId < 1) {
        return $empty;
    }

    $stmt = $pdo->prepare(
        'SELECT id, board_id, title, body_text, body_format, status, created_at, updated_at
         FROM toy_community_posts
         WHERE author_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['posts'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, post_id, body_text, status, created_at, updated_at
         FROM toy_community_comments
         WHERE author_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['comments'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, post_id, original_name, mime_type, size_bytes, width, height, status, created_at
         FROM toy_community_attachments
         WHERE uploader_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['attachments'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, target_type, target_id, reported_account_id, reason_key, memo_text, status, created_at, updated_at
         FROM toy_community_reports
         WHERE reporter_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['reports'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, sender_account_id, recipient_account_id, body_text, status, read_at, sender_deleted_at, recipient_deleted_at, created_at, updated_at
         FROM toy_community_messages
         WHERE sender_account_id = :account_id OR recipient_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['messages'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, post_id, created_at
         FROM toy_community_scraps
         WHERE account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['scraps'] = $stmt->fetchAll();

    try {
        $stmt = $pdo->prepare(
            'SELECT account_id, level_value, score_value, post_count, comment_count, evaluated_at, created_at, updated_at
             FROM toy_community_account_levels
             WHERE account_id = :account_id
             LIMIT 1'
        );
        $stmt->execute(['account_id' => $accountId]);
        $level = $stmt->fetch();
        $empty['level'] = is_array($level) ? $level : [];

        $stmt = $pdo->prepare(
            'SELECT id, old_level_value, new_level_value, old_score_value, new_score_value, reason_key, created_at
             FROM toy_community_level_logs
             WHERE account_id = :account_id
             ORDER BY id ASC
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $empty['level_logs'] = $stmt->fetchAll();
    } catch (Throwable $exception) {
        $empty['level'] = [];
        $empty['level_logs'] = [];
    }

    return $empty;
};
