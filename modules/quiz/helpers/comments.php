<?php

declare(strict_types=1);

function sr_quiz_comments_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_quiz_comments LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_quiz_comment_statuses(): array
{
    return ['published', 'hidden', 'deleted'];
}

function sr_quiz_comment_status_label(string $status): string
{
    return [
        'published' => '게시',
        'hidden' => '숨김',
        'deleted' => '삭제',
    ][$status] ?? $status;
}

function sr_quiz_comment_author_public_name_snapshot(PDO $pdo, int $accountId): string
{
    $name = trim(sr_member_public_name_for_account_id($pdo, $accountId, '회원'));

    return function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120);
}

function sr_quiz_comment_thread_columns_exist(PDO $pdo): bool
{
    static $existsByConnection = [];
    $key = (string) spl_object_id($pdo);
    if (array_key_exists($key, $existsByConnection)) {
        return $existsByConnection[$key];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME IN (\'parent_comment_id\', \'thread_root_id\', \'depth\')'
        );
        $stmt->execute(['table_name' => 'sr_quiz_comments']);
        $existsByConnection[$key] = (int) $stmt->fetchColumn() === 3;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
}

function sr_quiz_comments(PDO $pdo, int $quizId, int $limit = 100): array
{
    if ($quizId < 1 || !sr_quiz_comments_table_exists($pdo)) {
        return [];
    }

    $orderSql = sr_quiz_comment_thread_columns_exist($pdo)
        ? 'COALESCE(c.thread_root_id, c.id) ASC, c.depth ASC, c.id ASC'
        : 'c.id ASC';
    $stmt = $pdo->prepare(
        "SELECT c.*, a.display_name AS author_display_name, a.status AS author_account_status
         FROM sr_quiz_comments c
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         WHERE c.quiz_id = :quiz_id
           AND c.status = 'published'
         ORDER BY " . $orderSql . "
         LIMIT :limit_value"
    );
    $stmt->bindValue('quiz_id', $quizId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    $comments = [];
    foreach ($stmt->fetchAll() as $comment) {
        $snapshot = trim((string) ($comment['author_public_name_snapshot'] ?? ''));
        $comment['author_public_name'] = !in_array((string) ($comment['author_account_status'] ?? ''), ['withdrawn', 'anonymized'], true) && $snapshot !== ''
            ? $snapshot
            : sr_member_public_name([
                'display_name' => (string) ($comment['author_display_name'] ?? ''),
                'status' => (string) ($comment['author_account_status'] ?? ''),
            ], sr_member_settings($pdo), '회원');
        $comments[] = $comment;
    }

    return $comments;
}

function sr_quiz_comment_input_values(): array
{
    $parentCommentIdValue = sr_post_string('parent_comment_id', 20);

    return [
        'body_text' => sr_post_string_without_truncation('body_text', 5000),
        'is_secret' => sr_post_string('is_secret', 10) === '1' ? 1 : 0,
        'parent_comment_id' => preg_match('/\A[1-9][0-9]*\z/', $parentCommentIdValue) === 1 ? (int) $parentCommentIdValue : 0,
    ];
}

function sr_quiz_validate_comment_input(array $values): array
{
    if (!is_string($values['body_text'] ?? null)) {
        return ['댓글은 5000자 이하로 입력해 주세요.'];
    }
    if (trim((string) $values['body_text']) === '') {
        return ['댓글 내용을 입력하세요.'];
    }

    return [];
}

function sr_quiz_validate_comment_parent(PDO $pdo, int $quizId, array $values): array
{
    $parentCommentId = (int) ($values['parent_comment_id'] ?? 0);
    if ($parentCommentId < 1) {
        return ['parent_comment' => null, 'errors' => []];
    }
    if (!sr_quiz_comment_thread_columns_exist($pdo)) {
        return ['parent_comment' => null, 'errors' => ['답글 기능을 사용할 수 없습니다. 업데이트를 먼저 적용해 주세요.']];
    }

    $parentComment = sr_quiz_comment_by_id($pdo, $parentCommentId);
    if (!is_array($parentComment) || (int) ($parentComment['quiz_id'] ?? 0) !== $quizId || (string) ($parentComment['status'] ?? '') !== 'published') {
        return ['parent_comment' => null, 'errors' => ['답글을 작성할 댓글을 찾을 수 없습니다.']];
    }
    if ((int) ($parentComment['depth'] ?? 1) >= 3) {
        return ['parent_comment' => null, 'errors' => ['답글은 3단계까지만 작성할 수 있습니다.']];
    }

    return ['parent_comment' => $parentComment, 'errors' => []];
}

function sr_quiz_create_comment(PDO $pdo, int $quizId, int $authorAccountId, array $values): int
{
    if (!sr_quiz_comments_table_exists($pdo)) {
        throw new RuntimeException('quiz_comments_not_installed');
    }

    $now = sr_now();
    $threadColumnSql = sr_quiz_comment_thread_columns_exist($pdo) ? 'parent_comment_id, thread_root_id, depth, ' : '';
    $threadValueSql = $threadColumnSql !== '' ? ':parent_comment_id, :thread_root_id, :depth, ' : '';
    $parentComment = is_array($values['parent_comment'] ?? null) ? $values['parent_comment'] : null;
    $parentCommentId = is_array($parentComment) ? (int) ($parentComment['id'] ?? 0) : 0;
    $depth = is_array($parentComment) ? min(3, max(2, (int) ($parentComment['depth'] ?? 1) + 1)) : 1;
    $threadRootId = is_array($parentComment) ? (int) (($parentComment['thread_root_id'] ?? 0) ?: ($parentComment['id'] ?? 0)) : null;
    $stmt = $pdo->prepare(
        'INSERT INTO sr_quiz_comments
            (quiz_id, ' . $threadColumnSql . 'author_account_id, author_public_name_snapshot, body_text, is_secret, status, created_at, updated_at)
         VALUES
            (:quiz_id, ' . $threadValueSql . ':author_account_id, :author_public_name_snapshot, :body_text, :is_secret, :status, :created_at, :updated_at)'
    );
    $params = [
        'quiz_id' => $quizId,
        'author_account_id' => $authorAccountId,
        'author_public_name_snapshot' => sr_quiz_comment_author_public_name_snapshot($pdo, $authorAccountId),
        'body_text' => trim((string) $values['body_text']),
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if ($threadColumnSql !== '') {
        $params['parent_comment_id'] = $parentCommentId > 0 ? $parentCommentId : null;
        $params['thread_root_id'] = $threadRootId;
        $params['depth'] = $depth;
    }
    $stmt->execute($params);

    $commentId = (int) $pdo->lastInsertId();
    if ($threadColumnSql !== '' && $parentCommentId < 1) {
        $stmt = $pdo->prepare(
            'UPDATE sr_quiz_comments
             SET thread_root_id = :thread_root_id
             WHERE id = :id'
        );
        $stmt->execute([
            'thread_root_id' => $commentId,
            'id' => $commentId,
        ]);
    }

    return $commentId;
}

function sr_quiz_comment_by_id(PDO $pdo, int $commentId): ?array
{
    if ($commentId < 1 || !sr_quiz_comments_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_comments
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $commentId]);
    $comment = $stmt->fetch();

    return is_array($comment) ? $comment : null;
}

function sr_quiz_account_can_edit_comment(array $comment, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) ($comment['author_account_id'] ?? 0) === (int) $account['id']
        && (string) ($comment['status'] ?? '') === 'published';
}

function sr_quiz_account_has_result(PDO $pdo, int $quizId, int $accountId): bool
{
    return sr_quiz_latest_attempt_result($pdo, $quizId, $accountId) !== null;
}

function sr_quiz_account_can_manage_comments(PDO $pdo, ?array $account): bool
{
    return is_array($account)
        && (int) ($account['id'] ?? 0) > 0
        && function_exists('sr_admin_has_permission')
        && (sr_admin_has_permission($pdo, (int) $account['id'], '/admin/quiz/comments', 'view')
            || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/quiz', 'edit'));
}

function sr_quiz_account_can_view_comment_body(array $comment, ?array $account, PDO $pdo): bool
{
    if ((int) ($comment['is_secret'] ?? 0) !== 1) {
        return true;
    }
    if (!is_array($account)) {
        return false;
    }

    return (int) ($account['id'] ?? 0) === (int) ($comment['author_account_id'] ?? 0)
        || sr_quiz_account_owns_comment_target($pdo, $comment, (int) ($account['id'] ?? 0))
        || sr_quiz_account_can_manage_comments($pdo, $account);
}

function sr_quiz_account_owns_comment_target(PDO $pdo, array $comment, int $accountId): bool
{
    $quizId = (int) ($comment['quiz_id'] ?? 0);
    if ($quizId < 1 || $accountId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT created_by_account_id
         FROM sr_quiz_sets
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $quizId]);

    return (int) $stmt->fetchColumn() === $accountId;
}

function sr_quiz_account_can_delete_comment(array $comment, array $account, PDO $pdo): bool
{
    return sr_quiz_account_can_edit_comment($comment, $account) || sr_quiz_account_can_manage_comments($pdo, $account);
}

function sr_quiz_update_comment_content(PDO $pdo, int $commentId, array $values): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_quiz_comments
         SET body_text = :body_text,
             is_secret = :is_secret,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'body_text' => trim((string) $values['body_text']),
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0,
        'updated_at' => sr_now(),
        'id' => $commentId,
    ]);
}

function sr_quiz_update_comment_status(PDO $pdo, int $commentId, string $status): void
{
    if (!in_array($status, sr_quiz_comment_statuses(), true)) {
        return;
    }
    if ($status === 'deleted') {
        sr_quiz_delete_comment_redacted($pdo, $commentId);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_quiz_comments
         SET status = :status,
             deleted_at = CASE WHEN :status_deleted = \'deleted\' THEN :deleted_at ELSE deleted_at END,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $now = sr_now();
    $stmt->execute([
        'status' => $status,
        'status_deleted' => $status,
        'deleted_at' => $now,
        'updated_at' => $now,
        'id' => $commentId,
    ]);
}

function sr_quiz_delete_comment_redacted(PDO $pdo, int $commentId): void
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_quiz_comments
         SET author_public_name_snapshot = '',
             body_text = :body_text,
             status = 'deleted',
             deleted_at = :deleted_at,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'body_text' => '삭제된 댓글입니다.',
        'deleted_at' => $now,
        'updated_at' => $now,
        'id' => $commentId,
    ]);
}

function sr_quiz_notification_event_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_quiz_create_account_event_notification(PDO $pdo, int $accountId, string $eventKey, array $metadata, ?int $createdByAccountId = null): bool
{
    $createAccountEventFunction = sr_quiz_notification_event_function($pdo);
    if ($accountId < 1 || $createAccountEventFunction === '') {
        return false;
    }

    try {
        return $createAccountEventFunction($pdo, [
            'account_id' => $accountId,
            'module_key' => 'quiz',
            'event_key' => $eventKey,
            'created_by_account_id' => $createdByAccountId,
            'metadata' => $metadata,
        ]) !== null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'quiz_notification_event_create');
    }

    return false;
}

function sr_quiz_mentioned_account_ids(PDO $pdo, string $bodyText, array $excludeAccountIds = []): array
{
    return sr_member_mention_account_ids($pdo, sr_runtime_config(), $bodyText, $excludeAccountIds);
}

function sr_quiz_create_comment_mention_notifications(
    PDO $pdo,
    array $quiz,
    int $commentId,
    string $bodyText,
    int $createdByAccountId,
    array $excludeAccountIds = [],
    ?string $previousBodyText = null
): array {
    $result = [
        'mention_candidate_count' => 0,
        'mention_notification_count' => 0,
        'mention_account_hashes' => [],
    ];
    $quizId = (int) ($quiz['id'] ?? 0);
    if ($quizId < 1 || $commentId < 1) {
        return $result;
    }

    $excludeAccountIds[] = $createdByAccountId;
    $mentionedAccountIds = sr_quiz_mentioned_account_ids($pdo, $bodyText, $excludeAccountIds);
    if ($previousBodyText !== null) {
        $previousAccountIds = sr_quiz_mentioned_account_ids($pdo, $previousBodyText, $excludeAccountIds);
        $previousMap = array_fill_keys(array_map('intval', $previousAccountIds), true);
        $mentionedAccountIds = array_values(array_filter($mentionedAccountIds, static function (int $accountId) use ($previousMap): bool {
            return !isset($previousMap[$accountId]);
        }));
    }

    $result['mention_candidate_count'] = count($mentionedAccountIds);
    $config = sr_runtime_config();
    $metadata = [
        'quiz_id' => $quizId,
        'comment_id' => $commentId,
        'member_name' => sr_member_public_name_for_account_id($pdo, $createdByAccountId, '회원'),
        'link_url' => '/quiz/' . rawurlencode((string) ($quiz['quiz_key'] ?? '')) . '?result=1#quiz-comments',
        'created_at' => sr_now(),
    ];
    foreach ($mentionedAccountIds as $accountId) {
        $result['mention_account_hashes'][] = sr_member_public_account_hash($config, (int) $accountId);
    }
    foreach ($mentionedAccountIds as $accountId) {
        if (sr_quiz_create_account_event_notification($pdo, (int) $accountId, 'comment.mention', $metadata, $createdByAccountId)) {
            $result['mention_notification_count']++;
        }
    }

    return $result;
}

function sr_quiz_admin_comment_filters_from_request(): array
{
    return [
        'q' => sr_quiz_clean_single_line(sr_get_string('q', 120), 120),
        'status' => sr_quiz_clean_key(sr_get_string('status', 20), 20),
        'secret' => sr_quiz_clean_key(sr_get_string('secret', 10), 10),
    ];
}

function sr_quiz_admin_comments(PDO $pdo, array $filters = [], int $limit = 100): array
{
    if (!sr_quiz_comments_table_exists($pdo)) {
        return [];
    }

    $where = ['1 = 1'];
    $params = [];
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $where[] = '(q.quiz_key LIKE :keyword OR q.title LIKE :keyword OR c.body_text LIKE :keyword OR c.author_public_name_snapshot LIKE :keyword)';
        $params['keyword'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    }
    $status = (string) ($filters['status'] ?? '');
    if ($status !== '' && in_array($status, sr_quiz_comment_statuses(), true)) {
        $where[] = 'c.status = :status';
        $params['status'] = $status;
    }
    $secret = (string) ($filters['secret'] ?? '');
    if ($secret === 'yes' || $secret === 'no') {
        $where[] = 'c.is_secret = :is_secret';
        $params['is_secret'] = $secret === 'yes' ? 1 : 0;
    }

    $stmt = $pdo->prepare(
        'SELECT c.*, q.quiz_key, q.title AS quiz_title
         FROM sr_quiz_comments c
         INNER JOIN sr_quiz_sets q ON q.id = c.quiz_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.created_at DESC, c.id DESC
         LIMIT ' . (string) max(1, min(200, $limit))
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}
