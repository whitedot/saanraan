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

function sr_community_report_target_type_label(string $targetType): string
{
    $labels = [
        'post' => sr_t('community::ui.text.0b138cfe'),
        'comment' => sr_t('community::ui.text.c9fff683'),
        'message' => sr_t('community::ui.text.919bd592'),
    ];

    return (string) ($labels[$targetType] ?? $targetType);
}

function sr_community_report_statuses(): array
{
    return ['open', 'reviewing', 'resolved', 'dismissed'];
}

function sr_community_report_target_action_options(string $targetType): array
{
    if ($targetType === 'post') {
        return [
            'none' => '대상 조치 없음',
            'hide_post' => '게시글 숨김',
            'delete_post' => '게시글 삭제',
            'hide_post_suspend_publisher' => '게시글 숨김+게시자 정지',
            'delete_post_suspend_publisher' => '게시글 삭제+게시자 정지',
            'suspend_reported_account' => '게시자 정지',
        ];
    }
    if ($targetType === 'comment') {
        return [
            'none' => '대상 조치 없음',
            'hide_comment' => '댓글 숨김',
            'delete_comment' => '댓글 삭제',
            'hide_comment_suspend_publisher' => '댓글 숨김+게시자 정지',
            'delete_comment_suspend_publisher' => '댓글 삭제+게시자 정지',
            'suspend_reported_account' => '게시자 정지',
        ];
    }
    if ($targetType === 'message') {
        return [
            'none' => '대상 조치 없음',
            'suspend_reported_account' => '게시자 정지',
        ];
    }

    return ['none' => '대상 조치 없음'];
}

function sr_community_report_batch_target_action_options(): array
{
    return [
        'none' => '대상 조치 없음',
        'hide_target' => '게시글/댓글 숨김',
        'delete_target' => '게시글/댓글 삭제',
        'hide_target_suspend_publisher' => '게시글/댓글 숨김+게시자 정지',
        'delete_target_suspend_publisher' => '게시글/댓글 삭제+게시자 정지',
        'suspend_reported_account' => '게시자 정지',
    ];
}

function sr_community_report_batch_target_action_for_report(string $batchActionKey, string $targetType): string
{
    if ($batchActionKey === '' || $batchActionKey === 'none') {
        return 'none';
    }

    if ($batchActionKey === 'suspend_reported_account') {
        return 'suspend_reported_account';
    }

    if ($batchActionKey === 'hide_target') {
        if ($targetType === 'post') {
            return 'hide_post';
        }
        if ($targetType === 'comment') {
            return 'hide_comment';
        }
    }

    if ($batchActionKey === 'hide_target_suspend_publisher') {
        if ($targetType === 'post') {
            return 'hide_post_suspend_publisher';
        }
        if ($targetType === 'comment') {
            return 'hide_comment_suspend_publisher';
        }
    }

    if ($batchActionKey === 'delete_target') {
        if ($targetType === 'post') {
            return 'delete_post';
        }
        if ($targetType === 'comment') {
            return 'delete_comment';
        }
    }

    if ($batchActionKey === 'delete_target_suspend_publisher') {
        if ($targetType === 'post') {
            return 'delete_post_suspend_publisher';
        }
        if ($targetType === 'comment') {
            return 'delete_comment_suspend_publisher';
        }
    }

    return '';
}

function sr_community_report_reporter_action_options(): array
{
    return [
        'none' => '신고자 조치 없음',
        'suspend_reporter_account' => '허위신고자 정지',
    ];
}

function sr_community_report_status_policy_descriptions(): array
{
    return [
        'open' => '신고가 접수된 상태입니다. 대상 조치는 실행하지 않습니다.',
        'reviewing' => '운영자가 내용을 확인 중인 상태입니다. 대상 조치는 실행하지 않습니다.',
        'resolved' => '검토를 마친 상태입니다. 필요한 경우 대상 조치를 함께 실행할 수 있습니다.',
        'dismissed' => '제재 없이 기각한 상태입니다. 대상 조치는 실행하지 않으며 이미 적용된 조치를 되돌리지 않습니다.',
    ];
}

function sr_community_report_target_action_policy_error(string $status, string $actionKey): string
{
    if ($actionKey === '' || $actionKey === 'none') {
        return '';
    }

    if ($status !== 'resolved') {
        return '대상 조치는 신고 상태를 처리 완료로 저장할 때만 실행할 수 있습니다.';
    }

    return '';
}

function sr_community_report_reporter_action_policy_error(string $status, string $actionKey): string
{
    if ($actionKey === '' || $actionKey === 'none') {
        return '';
    }

    if ($status !== 'dismissed') {
        return '허위신고자 조치는 신고 상태를 기각으로 저장할 때만 실행할 수 있습니다.';
    }

    return '';
}

function sr_community_report_account_label(?string $displayName, int $accountId, ?string $accountStatus = null, ?string $nickname = null, ?array $communitySettings = null): string
{
    if (sr_community_nickname_status_blocks_identity((string) $accountStatus)) {
        return sr_t('member::account.withdrawn_display_name');
    }

    $label = is_array($communitySettings)
        ? sr_community_public_display_name([
            'display_name' => (string) $displayName,
            'community_nickname' => (string) $nickname,
            'status' => (string) $accountStatus,
        ], $communitySettings)
        : trim((string) $displayName);

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

function sr_community_report_query_parts(array $filters): array
{
    $where = [];
    $params = [];

    if ((int) ($filters['report_id'] ?? 0) > 0) {
        $where[] = 'r.id = :report_id';
        $params['report_id'] = (int) $filters['report_id'];
    }

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['reason_key'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.reason_key', 'reason_key', $filters['reason_key']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'reporter') {
            $where[] = '(reporter.display_name LIKE :reporter_name_keyword OR (reporter.status NOT IN (\'withdrawn\', \'anonymized\') AND reporter_nickname.nickname LIKE :reporter_nickname_keyword) OR CAST(r.reporter_account_id AS CHAR) LIKE :reporter_id_keyword)';
            $params['reporter_name_keyword'] = '%' . $keyword . '%';
            $params['reporter_nickname_keyword'] = '%' . $keyword . '%';
            $params['reporter_id_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'reported') {
            $where[] = '(reported.display_name LIKE :reported_name_keyword OR (reported.status NOT IN (\'withdrawn\', \'anonymized\') AND reported_nickname.nickname LIKE :reported_nickname_keyword) OR CAST(r.reported_account_id AS CHAR) LIKE :reported_id_keyword)';
            $params['reported_name_keyword'] = '%' . $keyword . '%';
            $params['reported_nickname_keyword'] = '%' . $keyword . '%';
            $params['reported_id_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'reviewer') {
            $where[] = '(reviewer.display_name LIKE :reviewer_name_keyword OR (reviewer.status NOT IN (\'withdrawn\', \'anonymized\') AND reviewer_nickname.nickname LIKE :reviewer_nickname_keyword) OR CAST(r.reviewer_account_id AS CHAR) LIKE :reviewer_id_keyword)';
            $params['reviewer_name_keyword'] = '%' . $keyword . '%';
            $params['reviewer_nickname_keyword'] = '%' . $keyword . '%';
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
            $where[] = '(r.memo_text LIKE :memo_keyword OR r.review_note LIKE :review_note_keyword OR reporter.display_name LIKE :reporter_keyword OR (reporter.status NOT IN (\'withdrawn\', \'anonymized\') AND reporter_nickname.nickname LIKE :reporter_nickname_keyword) OR reported.display_name LIKE :reported_keyword OR (reported.status NOT IN (\'withdrawn\', \'anonymized\') AND reported_nickname.nickname LIKE :reported_nickname_keyword) OR reviewer.display_name LIKE :reviewer_keyword OR (reviewer.status NOT IN (\'withdrawn\', \'anonymized\') AND reviewer_nickname.nickname LIKE :reviewer_nickname_keyword) OR r.target_type LIKE :target_type_keyword OR CAST(r.target_id AS CHAR) LIKE :target_id_keyword)';
            $params['memo_keyword'] = '%' . $keyword . '%';
            $params['review_note_keyword'] = '%' . $keyword . '%';
            $params['reporter_keyword'] = '%' . $keyword . '%';
            $params['reporter_nickname_keyword'] = '%' . $keyword . '%';
            $params['reported_keyword'] = '%' . $keyword . '%';
            $params['reported_nickname_keyword'] = '%' . $keyword . '%';
            $params['reviewer_keyword'] = '%' . $keyword . '%';
            $params['reviewer_nickname_keyword'] = '%' . $keyword . '%';
            $params['target_type_keyword'] = '%' . $keyword . '%';
            $params['target_id_keyword'] = '%' . $keyword . '%';
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_community_report_count(PDO $pdo, array $filters = []): int
{
    $queryParts = sr_community_report_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value
            FROM sr_community_reports r'
            . sr_community_report_count_join_sql($filters);
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_report_count_join_sql(array $filters): string
{
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword === '') {
        return '';
    }

    $field = (string) ($filters['field'] ?? 'all');
    $usesAll = !in_array($field, ['target', 'reporter', 'reported', 'reviewer', 'memo'], true);
    $usesReporter = $field === 'reporter' || $usesAll;
    $usesReported = $field === 'reported' || $usesAll;
    $usesReviewer = $field === 'reviewer' || $usesAll;
    $joins = [];

    if ($usesReporter) {
        $joins[] = 'LEFT JOIN sr_member_accounts reporter ON reporter.id = r.reporter_account_id';
        $joins[] = 'LEFT JOIN sr_member_nicknames reporter_nickname ON reporter_nickname.account_id = reporter.id';
    }
    if ($usesReported) {
        $joins[] = 'LEFT JOIN sr_member_accounts reported ON reported.id = r.reported_account_id';
        $joins[] = 'LEFT JOIN sr_member_nicknames reported_nickname ON reported_nickname.account_id = reported.id';
    }
    if ($usesReviewer) {
        $joins[] = 'LEFT JOIN sr_member_accounts reviewer ON reviewer.id = r.reviewer_account_id';
        $joins[] = 'LEFT JOIN sr_member_nicknames reviewer_nickname ON reviewer_nickname.account_id = reviewer.id';
    }

    return $joins === [] ? '' : "\n            " . implode("\n            ", $joins);
}

function sr_community_reports(PDO $pdo, int $limit = 100, array $filters = [], int $offset = 0): array
{
    $useLimit = $limit > 0;
    if ($useLimit) {
        $limit = max(1, min(1000, $limit));
    }
    $queryParts = sr_community_report_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $sql = 'SELECT r.id, r.target_type, r.target_id, r.reporter_account_id, r.reported_account_id, r.reason_key, r.memo_text,
                   r.status, r.reviewer_account_id, r.review_note, r.created_at, r.updated_at, r.reviewed_at,
                   CASE
                       WHEN r.target_type = \'post\' THEN target_post.title
                       WHEN r.target_type = \'comment\' THEN target_comment_post.title
                       ELSE \'\'
                   END AS target_post_title,
                   CASE
                       WHEN r.target_type = \'post\' THEN target_post.id
                       WHEN r.target_type = \'comment\' THEN target_comment_post.id
                       ELSE NULL
                   END AS target_post_id,
                   reporter.display_name AS reporter_display_name,
                   reporter_nickname.nickname AS reporter_nickname,
                   reporter.status AS reporter_account_status,
                   reported.display_name AS reported_display_name,
                   reported_nickname.nickname AS reported_nickname,
                   reported.status AS reported_account_status,
                   reviewer.display_name AS reviewer_display_name,
                   reviewer_nickname.nickname AS reviewer_nickname,
                   reviewer.status AS reviewer_account_status
            FROM sr_community_reports r
            LEFT JOIN sr_member_accounts reporter ON reporter.id = r.reporter_account_id
            LEFT JOIN sr_member_accounts reported ON reported.id = r.reported_account_id
            LEFT JOIN sr_member_accounts reviewer ON reviewer.id = r.reviewer_account_id
            LEFT JOIN sr_member_nicknames reporter_nickname ON reporter_nickname.account_id = reporter.id
            LEFT JOIN sr_member_nicknames reported_nickname ON reported_nickname.account_id = reported.id
            LEFT JOIN sr_member_nicknames reviewer_nickname ON reviewer_nickname.account_id = reviewer.id
            LEFT JOIN sr_community_posts target_post ON r.target_type = \'post\' AND target_post.id = r.target_id
            LEFT JOIN sr_community_comments target_comment ON r.target_type = \'comment\' AND target_comment.id = r.target_id
            LEFT JOIN sr_community_posts target_comment_post ON target_comment_post.id = target_comment.post_id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY r.id DESC';
    if ($useLimit) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    if ($useLimit) {
        $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
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

function sr_community_apply_report_target_action(PDO $pdo, array $report, string $actionKey, int $adminAccountId, bool $requireAuditLog = false): array
{
    $targetType = (string) ($report['target_type'] ?? '');
    $targetId = (int) ($report['target_id'] ?? 0);
    if ($actionKey === '' || $actionKey === 'none') {
        return ['action_key' => 'none', 'applied' => false];
    }
    if (!array_key_exists($actionKey, sr_community_report_target_action_options($targetType))) {
        return ['action_key' => $actionKey, 'applied' => false, 'error' => 'invalid_action'];
    }

    $combinedActions = [
        'hide_post_suspend_publisher' => ['hide_post', 'suspend_reported_account'],
        'delete_post_suspend_publisher' => ['delete_post', 'suspend_reported_account'],
        'hide_comment_suspend_publisher' => ['hide_comment', 'suspend_reported_account'],
        'delete_comment_suspend_publisher' => ['delete_comment', 'suspend_reported_account'],
    ];
    if (array_key_exists($actionKey, $combinedActions)) {
        $results = [];
        $applied = false;
        foreach ($combinedActions[$actionKey] as $childActionKey) {
            $childResult = sr_community_apply_report_target_action($pdo, $report, $childActionKey, $adminAccountId, $requireAuditLog);
            $results[] = $childResult;
            if (!empty($childResult['error'])) {
                return [
                    'action_key' => $actionKey,
                    'applied' => $applied,
                    'error' => (string) $childResult['error'],
                    'results' => $results,
                ];
            }
            if (!empty($childResult['applied'])) {
                $applied = true;
            }
        }

        return [
            'action_key' => $actionKey,
            'applied' => $applied,
            'results' => $results,
        ];
    }

    if ($targetType === 'post' && in_array($actionKey, ['hide_post', 'delete_post'], true)) {
        $status = $actionKey === 'hide_post' ? 'hidden' : 'deleted';
        $post = sr_community_admin_post_by_id($pdo, $targetId);
        if (!is_array($post)) {
            return ['action_key' => $actionKey, 'applied' => false, 'error' => 'target_not_found'];
        }
        sr_community_update_post_status($pdo, $targetId, $status);
        $updatedAttachmentCount = in_array($status, ['hidden', 'deleted'], true)
            ? sr_community_update_post_attachments_status($pdo, $targetId, $status)
            : 0;
        sr_community_report_target_action_audit_log($pdo, [
            'actor_account_id' => $adminAccountId,
            'actor_type' => 'admin',
            'event_type' => 'community.report.target_post_action',
            'target_type' => 'community_post',
            'target_id' => (string) $targetId,
            'result' => 'success',
            'message' => 'Community report target post action applied.',
            'metadata' => [
                'report_id' => (int) ($report['id'] ?? 0),
                'before_status' => (string) ($post['status'] ?? ''),
                'after_status' => $status,
                'updated_attachment_count' => $updatedAttachmentCount,
            ],
        ], $requireAuditLog);
        return ['action_key' => $actionKey, 'applied' => true, 'target_status' => $status];
    }

    if ($targetType === 'comment' && in_array($actionKey, ['hide_comment', 'delete_comment'], true)) {
        $status = $actionKey === 'hide_comment' ? 'hidden' : 'deleted';
        $comment = sr_community_admin_comment_by_id($pdo, $targetId);
        if (!is_array($comment)) {
            return ['action_key' => $actionKey, 'applied' => false, 'error' => 'target_not_found'];
        }
        sr_community_update_comment_status($pdo, $targetId, $status);
        sr_community_report_target_action_audit_log($pdo, [
            'actor_account_id' => $adminAccountId,
            'actor_type' => 'admin',
            'event_type' => 'community.report.target_comment_action',
            'target_type' => 'community_comment',
            'target_id' => (string) $targetId,
            'result' => 'success',
            'message' => 'Community report target comment action applied.',
            'metadata' => [
                'report_id' => (int) ($report['id'] ?? 0),
                'before_status' => (string) ($comment['status'] ?? ''),
                'after_status' => $status,
                'post_id' => (int) ($comment['post_id'] ?? 0),
            ],
        ], $requireAuditLog);
        return ['action_key' => $actionKey, 'applied' => true, 'target_status' => $status];
    }

    if ($actionKey === 'suspend_reported_account') {
        $reportedAccountId = (int) ($report['reported_account_id'] ?? 0);
        if ($reportedAccountId < 1 || !function_exists('sr_member_update_status')) {
            return ['action_key' => $actionKey, 'applied' => false, 'error' => 'account_action_unavailable'];
        }
        sr_member_update_status($pdo, $reportedAccountId, 'suspended');
        sr_community_report_target_action_audit_log($pdo, [
            'actor_account_id' => $adminAccountId,
            'actor_type' => 'admin',
            'event_type' => 'community.report.reported_account_suspended',
            'target_type' => 'member_account',
            'target_id' => (string) $reportedAccountId,
            'result' => 'success',
            'message' => 'Reported account suspended from community report.',
            'metadata' => [
                'report_id' => (int) ($report['id'] ?? 0),
                'reported_target_type' => $targetType,
                'reported_target_id' => $targetId,
            ],
        ], $requireAuditLog);
        return ['action_key' => $actionKey, 'applied' => true, 'account_status' => 'suspended'];
    }

    return ['action_key' => $actionKey, 'applied' => false, 'error' => 'unsupported_action'];
}

function sr_community_apply_report_reporter_action(PDO $pdo, array $report, string $actionKey, int $adminAccountId, bool $requireAuditLog = false): array
{
    $targetType = (string) ($report['target_type'] ?? '');
    $targetId = (int) ($report['target_id'] ?? 0);
    if ($actionKey === '' || $actionKey === 'none') {
        return ['action_key' => 'none', 'applied' => false];
    }
    if (!array_key_exists($actionKey, sr_community_report_reporter_action_options())) {
        return ['action_key' => $actionKey, 'applied' => false, 'error' => 'invalid_action'];
    }

    if ($actionKey === 'suspend_reporter_account') {
        $reporterAccountId = (int) ($report['reporter_account_id'] ?? 0);
        if ($reporterAccountId < 1 || !function_exists('sr_member_update_status')) {
            return ['action_key' => $actionKey, 'applied' => false, 'error' => 'account_action_unavailable'];
        }
        sr_member_update_status($pdo, $reporterAccountId, 'suspended');
        sr_community_report_target_action_audit_log($pdo, [
            'actor_account_id' => $adminAccountId,
            'actor_type' => 'admin',
            'event_type' => 'community.report.reporter_account_suspended',
            'target_type' => 'member_account',
            'target_id' => (string) $reporterAccountId,
            'result' => 'success',
            'message' => 'Reporter account suspended from dismissed community report.',
            'metadata' => [
                'report_id' => (int) ($report['id'] ?? 0),
                'reported_target_type' => $targetType,
                'reported_target_id' => $targetId,
                'reported_account_id' => (int) ($report['reported_account_id'] ?? 0),
            ],
        ], $requireAuditLog);
        return ['action_key' => $actionKey, 'applied' => true, 'account_status' => 'suspended'];
    }

    return ['action_key' => $actionKey, 'applied' => false, 'error' => 'unsupported_action'];
}

function sr_community_report_target_action_audit_log(PDO $pdo, array $data, bool $required): void
{
    if ($required && function_exists('sr_audit_log_required')) {
        sr_audit_log_required($pdo, $data);
        return;
    }

    sr_audit_log($pdo, $data);
}
