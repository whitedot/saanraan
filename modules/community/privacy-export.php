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
    ];

    if ($accountId < 1) {
        return $empty;
    }

    $stmt = $pdo->prepare(
        'SELECT id, board_id, title, body_format, status, created_at, updated_at
         FROM toy_community_posts
         WHERE author_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['posts'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, post_id, status, created_at, updated_at
         FROM toy_community_comments
         WHERE author_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['comments'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, post_id, original_name, mime_type, size_bytes, checksum_sha256, width, height, status, created_at
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

    return $empty;
};
