<?php

declare(strict_types=1);

function sr_community_report_reason_keys(): array
{
    return ['spam', 'abuse', 'personal_info', 'illegal', 'other'];
}

function sr_community_report_reason_label(string $reasonKey): string
{
    $labels = [
        'spam' => sr_t('community::report.reason.spam'),
        'abuse' => sr_t('community::report.reason.abuse'),
        'personal_info' => sr_t('community::report.reason.personal_info'),
        'illegal' => sr_t('community::report.reason.illegal'),
        'other' => sr_t('community::report.reason.other'),
    ];

    return (string) ($labels[$reasonKey] ?? $reasonKey);
}

function sr_community_report_statuses(): array
{
    return ['open', 'reviewing', 'resolved', 'dismissed'];
}

function sr_community_report_account_label(?string $displayName, int $accountId, ?string $accountStatus = null): string
{
    $label = trim((string) $displayName);
    if ((string) $accountStatus === 'anonymized' && $label === 'withdrawn') {
        return sr_t('member::account.withdrawn_display_name');
    }

    if ($label !== '') {
        return $label;
    }

    return $accountId > 0 ? sr_t('community::report.account.member') : sr_t('community::report.account.unknown');
}

function sr_community_report_target(PDO $pdo, string $targetType, int $targetId, ?int $actorAccountId = null): ?array
{
    if ($targetId < 1) {
        return null;
    }

    if ($targetType === 'post') {
        $account = $actorAccountId !== null ? ['id' => $actorAccountId] : null;
        $post = sr_community_post_for_read($pdo, $targetId, $account);
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
        $account = $actorAccountId !== null ? ['id' => $actorAccountId] : null;
        $comment = sr_community_comment_for_read($pdo, $targetId, $account);
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
        $message = sr_community_message_participants_for_account($pdo, $targetId, $actorAccountId);
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

function sr_community_comment_for_read(PDO $pdo, int $commentId, ?array $account): ?array
{
    if ($commentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT c.id, c.post_id, c.author_account_id, c.status,
                p.status AS post_status,
                b.id AS board_id, b.board_group_id, b.status AS board_status, b.read_policy
         FROM sr_community_comments c
         INNER JOIN sr_community_posts p ON p.id = c.post_id
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         WHERE c.id = :id
           AND c.status = 'published'
           AND p.status = 'published'
           AND b.status = 'enabled'
         LIMIT 1"
    );
    $stmt->execute(['id' => $commentId]);
    $comment = $stmt->fetch();

    if (!is_array($comment)) {
        return null;
    }

    $board = [
        'id' => (int) $comment['board_id'],
        'board_group_id' => (int) ($comment['board_group_id'] ?? 0),
        'status' => (string) $comment['board_status'],
        'read_policy' => (string) $comment['read_policy'],
    ];

    return sr_community_account_can_read_board($pdo, $board, $account) ? $comment : null;
}

function sr_community_report_exists(PDO $pdo, int $reporterAccountId, string $targetType, int $targetId): bool
{
    if ($reporterAccountId < 1 || $targetId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_community_reports
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

function sr_community_create_report(PDO $pdo, array $data): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_reports
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

function sr_community_report_rate_limited(PDO $pdo, int $accountId, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['report_create_window_seconds'] ?? 300)));
    $limit = min(200, max(1, (int) ($settings['report_create_limit'] ?? 20)));

    return sr_community_rate_limits_table_exists($pdo)
        && sr_rate_limit_count($pdo, 'community.report.account', (string) $accountId, $windowSeconds) >= $limit;
}

function sr_community_record_report_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if (!sr_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['report_create_window_seconds'] ?? 300)));
    sr_rate_limit_increment($pdo, 'community.report.account', (string) $accountId, $windowSeconds);
}

function sr_community_reports(PDO $pdo, int $limit = 100, array $filters = []): array
{
    $limit = max(1, min(200, $limit));
    $where = [];
    $params = [];

    if ((string) ($filters['status'] ?? '') !== '') {
        $where[] = 'r.status = :status';
        $params['status'] = (string) $filters['status'];
    }

    if ((string) ($filters['target_type'] ?? '') !== '') {
        $where[] = 'r.target_type = :target_type';
        $params['target_type'] = (string) $filters['target_type'];
    }

    if ((string) ($filters['reason_key'] ?? '') !== '') {
        $where[] = 'r.reason_key = :reason_key';
        $params['reason_key'] = (string) $filters['reason_key'];
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'reporter') {
            $where[] = '(reporter.display_name LIKE :reporter_name_keyword OR CAST(r.reporter_account_id AS CHAR) LIKE :reporter_id_keyword)';
            $params['reporter_name_keyword'] = '%' . $keyword . '%';
            $params['reporter_id_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'reported') {
            $where[] = '(reported.display_name LIKE :reported_name_keyword OR CAST(r.reported_account_id AS CHAR) LIKE :reported_id_keyword)';
            $params['reported_name_keyword'] = '%' . $keyword . '%';
            $params['reported_id_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'reviewer') {
            $where[] = '(reviewer.display_name LIKE :reviewer_name_keyword OR CAST(r.reviewer_account_id AS CHAR) LIKE :reviewer_id_keyword)';
            $params['reviewer_name_keyword'] = '%' . $keyword . '%';
            $params['reviewer_id_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'memo') {
            $where[] = '(r.memo_text LIKE :memo_keyword OR r.review_note LIKE :review_note_keyword)';
            $params['memo_keyword'] = '%' . $keyword . '%';
            $params['review_note_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'target') {
            $where[] = '(r.target_type LIKE :target_type_keyword OR CAST(r.target_id AS CHAR) LIKE :target_id_keyword)';
            $params['target_type_keyword'] = '%' . $keyword . '%';
            $params['target_id_keyword'] = '%' . $keyword . '%';
        } else {
            $where[] = '(r.memo_text LIKE :memo_keyword OR r.review_note LIKE :review_note_keyword OR reporter.display_name LIKE :reporter_keyword OR reported.display_name LIKE :reported_keyword OR reviewer.display_name LIKE :reviewer_keyword OR r.target_type LIKE :target_type_keyword OR CAST(r.target_id AS CHAR) LIKE :target_id_keyword)';
            $params['memo_keyword'] = '%' . $keyword . '%';
            $params['review_note_keyword'] = '%' . $keyword . '%';
            $params['reporter_keyword'] = '%' . $keyword . '%';
            $params['reported_keyword'] = '%' . $keyword . '%';
            $params['reviewer_keyword'] = '%' . $keyword . '%';
            $params['target_type_keyword'] = '%' . $keyword . '%';
            $params['target_id_keyword'] = '%' . $keyword . '%';
        }
    }

    $sql = 'SELECT r.id, r.target_type, r.target_id, r.reporter_account_id, r.reported_account_id, r.reason_key, r.memo_text,
                   r.status, r.reviewer_account_id, r.review_note, r.created_at, r.updated_at, r.reviewed_at,
                   reporter.display_name AS reporter_display_name,
                   reporter.status AS reporter_account_status,
                   reported.display_name AS reported_display_name,
                   reported.status AS reported_account_status,
                   reviewer.display_name AS reviewer_display_name,
                   reviewer.status AS reviewer_account_status
            FROM sr_community_reports r
            LEFT JOIN sr_member_accounts reporter ON reporter.id = r.reporter_account_id
            LEFT JOIN sr_member_accounts reported ON reported.id = r.reported_account_id
            LEFT JOIN sr_member_accounts reviewer ON reviewer.id = r.reviewer_account_id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY r.id DESC LIMIT :limit_value';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_report_by_id(PDO $pdo, int $reportId): ?array
{
    if ($reportId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_reports WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $reportId]);
    $report = $stmt->fetch();

    return is_array($report) ? $report : null;
}

function sr_community_update_report_status(PDO $pdo, int $reportId, string $status, int $reviewerAccountId, string $reviewNote): void
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'UPDATE sr_community_reports
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
