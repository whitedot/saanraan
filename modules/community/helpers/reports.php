<?php

declare(strict_types=1);

function toy_community_report_reason_keys(): array
{
    return ['spam', 'abuse', 'personal_info', 'illegal', 'other'];
}

function toy_community_report_reason_label(string $reasonKey): string
{
    $labels = [
        'spam' => '스팸',
        'abuse' => '욕설/괴롭힘',
        'personal_info' => '개인정보 노출',
        'illegal' => '불법 정보',
        'other' => '기타',
    ];

    return (string) ($labels[$reasonKey] ?? $reasonKey);
}

function toy_community_report_statuses(): array
{
    return ['open', 'reviewing', 'resolved', 'dismissed'];
}

function toy_community_report_target(PDO $pdo, string $targetType, int $targetId, ?int $actorAccountId = null): ?array
{
    if ($targetId < 1) {
        return null;
    }

    if ($targetType === 'post') {
        $post = toy_community_public_post($pdo, $targetId);
        if (!is_array($post)) {
            return null;
        }

        return [
            'target_type' => 'post',
            'target_id' => (int) $post['id'],
            'reported_account_id' => (int) $post['author_account_id'],
            'post_id' => (int) $post['id'],
            'redirect_path' => '/community/post?id=' . (string) $post['id'],
        ];
    }

    if ($targetType === 'comment') {
        $comment = toy_community_public_comment($pdo, $targetId);
        if (!is_array($comment)) {
            return null;
        }

        return [
            'target_type' => 'comment',
            'target_id' => (int) $comment['id'],
            'reported_account_id' => (int) $comment['author_account_id'],
            'post_id' => (int) $comment['post_id'],
            'redirect_path' => '/community/post?id=' . (string) $comment['post_id'] . '#comments',
        ];
    }

    if ($targetType === 'message' && $actorAccountId !== null) {
        $message = toy_community_message_by_id_for_account($pdo, $targetId, $actorAccountId);
        if (!is_array($message)) {
            return null;
        }

        $reportedAccountId = (int) $message['sender_account_id'] === $actorAccountId
            ? (int) $message['recipient_account_id']
            : (int) $message['sender_account_id'];

        return [
            'target_type' => 'message',
            'target_id' => (int) $message['id'],
            'reported_account_id' => $reportedAccountId,
            'message_id' => (int) $message['id'],
            'redirect_path' => '/community/message?id=' . (string) $message['id'],
        ];
    }

    return null;
}

function toy_community_public_comment(PDO $pdo, int $commentId): ?array
{
    if ($commentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT c.id, c.post_id, c.author_account_id, c.body_text, c.status, c.created_at, c.updated_at,
                p.status AS post_status,
                b.status AS board_status, b.read_policy
         FROM toy_community_comments c
         INNER JOIN toy_community_posts p ON p.id = c.post_id
         INNER JOIN toy_community_boards b ON b.id = p.board_id
         WHERE c.id = :id
           AND c.status = 'published'
           AND p.status = 'published'
           AND b.status = 'enabled'
           AND b.read_policy = 'public'
         LIMIT 1"
    );
    $stmt->execute(['id' => $commentId]);
    $comment = $stmt->fetch();

    return is_array($comment) ? $comment : null;
}

function toy_community_report_exists(PDO $pdo, int $reporterAccountId, string $targetType, int $targetId): bool
{
    if ($reporterAccountId < 1 || $targetId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM toy_community_reports
         WHERE reporter_account_id = :reporter_account_id
           AND target_type = :target_type
           AND target_id = :target_id
         LIMIT 1'
    );
    $stmt->execute([
        'reporter_account_id' => $reporterAccountId,
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);

    return is_array($stmt->fetch());
}

function toy_community_create_report(PDO $pdo, array $data): int
{
    $now = toy_now();
    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_reports
            (target_type, target_id, reporter_account_id, reported_account_id, reason_key, memo_text, status, reviewer_account_id, review_note, created_at, updated_at, reviewed_at)
         VALUES
            (:target_type, :target_id, :reporter_account_id, :reported_account_id, :reason_key, :memo_text, :status, NULL, NULL, :created_at, :updated_at, NULL)'
    );
    $stmt->execute([
        'target_type' => (string) $data['target_type'],
        'target_id' => (int) $data['target_id'],
        'reporter_account_id' => (int) $data['reporter_account_id'],
        'reported_account_id' => (int) $data['reported_account_id'],
        'reason_key' => (string) $data['reason_key'],
        'memo_text' => (string) $data['memo_text'],
        'status' => 'open',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function toy_community_report_rate_limited(PDO $pdo, int $accountId, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['report_create_window_seconds'] ?? 300)));
    $limit = min(200, max(1, (int) ($settings['report_create_limit'] ?? 20)));

    return toy_community_rate_limits_table_exists($pdo)
        && toy_rate_limit_count($pdo, 'community.report.account', (string) $accountId, $windowSeconds) >= $limit;
}

function toy_community_record_report_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if (!toy_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['report_create_window_seconds'] ?? 300)));
    toy_rate_limit_increment($pdo, 'community.report.account', (string) $accountId, $windowSeconds);
}

function toy_community_reports(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare(
        'SELECT r.id, r.target_type, r.target_id, r.reporter_account_id, r.reported_account_id, r.reason_key, r.memo_text,
                r.status, r.reviewer_account_id, r.review_note, r.created_at, r.updated_at, r.reviewed_at,
                reporter.display_name AS reporter_display_name,
                reported.display_name AS reported_display_name
         FROM toy_community_reports r
         LEFT JOIN toy_member_accounts reporter ON reporter.id = r.reporter_account_id
         LEFT JOIN toy_member_accounts reported ON reported.id = r.reported_account_id
         ORDER BY r.id DESC
         LIMIT :limit_value'
    );
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function toy_community_report_by_id(PDO $pdo, int $reportId): ?array
{
    if ($reportId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM toy_community_reports WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $reportId]);
    $report = $stmt->fetch();

    return is_array($report) ? $report : null;
}

function toy_community_update_report_status(PDO $pdo, int $reportId, string $status, int $reviewerAccountId, string $reviewNote): void
{
    $now = toy_now();
    $stmt = $pdo->prepare(
        'UPDATE toy_community_reports
         SET status = :status,
             reviewer_account_id = :reviewer_account_id,
             review_note = :review_note,
             updated_at = :updated_at,
             reviewed_at = :reviewed_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'reviewer_account_id' => $reviewerAccountId,
        'review_note' => $reviewNote,
        'updated_at' => $now,
        'reviewed_at' => $now,
        'id' => $reportId,
    ]);
}
