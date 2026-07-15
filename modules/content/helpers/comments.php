<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_content_comments_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_content_comments LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_content_comment_author_public_name_snapshot(PDO $pdo, int $accountId): string
{
    $name = trim(sr_member_public_name_for_account_id($pdo, $accountId, '회원'));

    return function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120);
}

function sr_content_comments(PDO $pdo, int $contentId, int $limit = 100): array
{
    if ($contentId < 1 || !sr_content_comments_table_exists($pdo)) {
        return [];
    }

    $join = sr_member_nicknames_table_exists($pdo) ? 'LEFT JOIN sr_member_nicknames n ON n.account_id = a.id' : '';
    $nicknameSelect = sr_member_nicknames_table_exists($pdo) ? 'n.nickname AS author_nickname,' : "'' AS author_nickname,";
    $stmt = $pdo->prepare(
        "SELECT c.*, a.display_name AS author_display_name, " . $nicknameSelect . " a.status AS author_account_status
         FROM sr_content_comments c
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         " . $join . "
         WHERE c.content_id = :content_id
           AND c.status = 'published'
         ORDER BY COALESCE(c.thread_root_id, c.id) ASC, c.depth ASC, c.id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('content_id', $contentId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    $settings = sr_member_settings($pdo);
    $comments = [];
    foreach ($stmt->fetchAll() as $comment) {
        $snapshot = trim((string) ($comment['author_public_name_snapshot'] ?? ''));
        $comment['author_public_name'] = !in_array((string) ($comment['author_account_status'] ?? ''), ['withdrawn', 'anonymized'], true) && $snapshot !== ''
            ? $snapshot
            : sr_member_public_name([
            'display_name' => (string) ($comment['author_display_name'] ?? ''),
            'nickname' => (string) ($comment['author_nickname'] ?? ''),
            'status' => (string) ($comment['author_account_status'] ?? ''),
        ], $settings, '회원');
        $comments[] = $comment;
    }

    return $comments;
}

function sr_content_comment_page(PDO $pdo, int $contentId, int $page = 1, int $perPage = 20): array
{
    $perPage = max(1, min(100, $perPage));
    if ($contentId < 1 || !sr_content_comments_table_exists($pdo)) {
        return ['comments' => [], 'page' => 1, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 1, 'has_previous' => false, 'has_next' => false];
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM sr_content_comments WHERE content_id = :content_id AND status = 'published'");
    $countStmt->execute(['content_id' => $contentId]);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $join = sr_member_nicknames_table_exists($pdo) ? 'LEFT JOIN sr_member_nicknames n ON n.account_id = a.id' : '';
    $nicknameSelect = sr_member_nicknames_table_exists($pdo) ? 'n.nickname AS author_nickname,' : "'' AS author_nickname,";
    $stmt = $pdo->prepare(
        "SELECT c.*, a.display_name AS author_display_name, " . $nicknameSelect . " a.status AS author_account_status
         FROM sr_content_comments c
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         " . $join . "
         WHERE c.content_id = :content_id
           AND c.status = 'published'
         ORDER BY COALESCE(c.thread_root_id, c.id) ASC, c.depth ASC, c.id ASC
         LIMIT :limit_value OFFSET :offset_value"
    );
    $stmt->bindValue('content_id', $contentId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $settings = sr_member_settings($pdo);
    $comments = [];
    foreach ($stmt->fetchAll() as $comment) {
        $snapshot = trim((string) ($comment['author_public_name_snapshot'] ?? ''));
        $comment['author_public_name'] = !in_array((string) ($comment['author_account_status'] ?? ''), ['withdrawn', 'anonymized'], true) && $snapshot !== ''
            ? $snapshot
            : sr_member_public_name([
                'display_name' => (string) ($comment['author_display_name'] ?? ''),
                'nickname' => (string) ($comment['author_nickname'] ?? ''),
                'status' => (string) ($comment['author_account_status'] ?? ''),
            ], $settings, '회원');
        $comments[] = $comment;
    }

    return [
        'comments' => $comments,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'has_previous' => $page > 1,
        'has_next' => $page < $totalPages,
    ];
}

function sr_content_comment_page_for_comment(PDO $pdo, int $contentId, int $commentId, int $perPage = 20): int
{
    $perPage = max(1, min(100, $perPage));
    $targetStmt = $pdo->prepare("SELECT id, COALESCE(thread_root_id, id) AS root_id, depth FROM sr_content_comments WHERE id = :id AND content_id = :content_id AND status = 'published' LIMIT 1");
    $targetStmt->execute(['id' => $commentId, 'content_id' => $contentId]);
    $target = $targetStmt->fetch();
    if (!is_array($target)) {
        return 1;
    }

    $positionStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_content_comments
         WHERE content_id = :content_id
           AND status = 'published'
           AND (
               COALESCE(thread_root_id, id) < :root_before
               OR (COALESCE(thread_root_id, id) = :root_equal AND depth < :depth_before)
               OR (COALESCE(thread_root_id, id) = :root_same AND depth = :depth_equal AND id <= :comment_id)
           )"
    );
    $positionStmt->execute([
        'content_id' => $contentId,
        'root_before' => (int) $target['root_id'],
        'root_equal' => (int) $target['root_id'],
        'depth_before' => (int) $target['depth'],
        'root_same' => (int) $target['root_id'],
        'depth_equal' => (int) $target['depth'],
        'comment_id' => $commentId,
    ]);

    return max(1, (int) ceil(((int) $positionStmt->fetchColumn()) / $perPage));
}

function sr_content_recent_comments(PDO $pdo, int $limit = 8): array
{
    if (!sr_content_comments_table_exists($pdo)) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $join = sr_member_nicknames_table_exists($pdo) ? 'LEFT JOIN sr_member_nicknames n ON n.account_id = a.id' : '';
    $nicknameSelect = sr_member_nicknames_table_exists($pdo) ? 'n.nickname AS author_nickname,' : "'' AS author_nickname,";
    $stmt = $pdo->prepare(
        "SELECT c.id, c.content_id, c.author_account_id, c.body_text, c.created_at, c.updated_at,
                c.author_public_name_snapshot,
                p.slug AS content_slug, p.title AS content_title,
                a.display_name AS author_display_name, " . $nicknameSelect . " a.status AS author_account_status
         FROM sr_content_comments c
         INNER JOIN sr_content_items p ON p.id = c.content_id AND p.status = 'published'
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         " . $join . "
         WHERE c.status = 'published'
           AND c.is_secret = 0
         ORDER BY c.created_at DESC, c.id DESC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $settings = sr_member_settings($pdo);
    $comments = [];
    foreach ($stmt->fetchAll() as $comment) {
        $snapshot = trim((string) ($comment['author_public_name_snapshot'] ?? ''));
        $comment['author_public_name'] = !in_array((string) ($comment['author_account_status'] ?? ''), ['withdrawn', 'anonymized'], true) && $snapshot !== ''
            ? $snapshot
            : sr_member_public_name([
            'display_name' => (string) ($comment['author_display_name'] ?? ''),
            'nickname' => (string) ($comment['author_nickname'] ?? ''),
            'status' => (string) ($comment['author_account_status'] ?? ''),
        ], $settings, '회원');
        $comments[] = $comment;
    }

    return $comments;
}

function sr_content_comment_input_values(): array
{
    $parentCommentIdValue = sr_post_string('parent_comment_id', 20);

    return [
        'body_text' => sr_post_string_without_truncation('body_text', 5000),
        'is_secret' => sr_post_string('is_secret', 10) === '1' ? 1 : 0,
        'parent_comment_id' => preg_match('/\A[1-9][0-9]*\z/', $parentCommentIdValue) === 1 ? (int) $parentCommentIdValue : 0,
    ];
}

function sr_content_validate_comment_input(array $values): array
{
    if (!is_string($values['body_text'] ?? null)) {
        return ['댓글은 5000자 이하로 입력해 주세요.'];
    }
    if (trim((string) $values['body_text']) === '') {
        return ['댓글 내용을 입력하세요.'];
    }

    return [];
}

function sr_content_validate_comment_parent(PDO $pdo, int $contentId, array $values): array
{
    $parentCommentId = (int) ($values['parent_comment_id'] ?? 0);
    if ($parentCommentId < 1) {
        return ['parent_comment' => null, 'errors' => []];
    }

    $parentComment = sr_content_comment_by_id($pdo, $parentCommentId);
    if (!is_array($parentComment) || (int) ($parentComment['content_id'] ?? 0) !== $contentId || (string) ($parentComment['status'] ?? '') !== 'published') {
        return ['parent_comment' => null, 'errors' => ['답글을 작성할 댓글을 찾을 수 없습니다.']];
    }
    if ((int) ($parentComment['depth'] ?? 1) >= 3) {
        return ['parent_comment' => null, 'errors' => ['답글은 3단계까지만 작성할 수 있습니다.']];
    }

    return ['parent_comment' => $parentComment, 'errors' => []];
}

function sr_content_create_comment(PDO $pdo, int $contentId, int $authorAccountId, array $values): int
{
    if (!sr_content_comments_table_exists($pdo)) {
        throw new RuntimeException('content_comments_not_installed');
    }

    $now = sr_now();
    $parentComment = is_array($values['parent_comment'] ?? null) ? $values['parent_comment'] : null;
    $parentCommentId = is_array($parentComment) ? (int) ($parentComment['id'] ?? 0) : 0;
    $depth = is_array($parentComment) ? min(3, max(2, (int) ($parentComment['depth'] ?? 1) + 1)) : 1;
    $threadRootId = is_array($parentComment) ? (int) (($parentComment['thread_root_id'] ?? 0) ?: ($parentComment['id'] ?? 0)) : null;
    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_comments
            (content_id, parent_comment_id, thread_root_id, depth, author_account_id, author_public_name_snapshot, body_text, extra_values_json, is_secret, status, created_at, updated_at)
         VALUES
            (:content_id, :parent_comment_id, :thread_root_id, :depth, :author_account_id, :author_public_name_snapshot, :body_text, :extra_values_json, :is_secret, :status, :created_at, :updated_at)'
    );
    $params = [
        'content_id' => $contentId,
        'parent_comment_id' => $parentCommentId > 0 ? $parentCommentId : null,
        'thread_root_id' => $threadRootId,
        'depth' => $depth,
        'author_account_id' => $authorAccountId,
        'author_public_name_snapshot' => sr_content_comment_author_public_name_snapshot($pdo, $authorAccountId),
        'body_text' => trim((string) $values['body_text']),
        'extra_values_json' => (string) ($values['extra_values_json'] ?? '[]'),
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $stmt->execute($params);

    $commentId = (int) $pdo->lastInsertId();
    if ($parentCommentId < 1) {
        $stmt = $pdo->prepare(
            'UPDATE sr_content_comments
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

function sr_content_comment_by_id(PDO $pdo, int $commentId): ?array
{
    if ($commentId < 1 || !sr_content_comments_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, content_id, parent_comment_id, thread_root_id, depth, author_account_id, body_text, is_secret, status, created_at, updated_at
         FROM sr_content_comments
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $commentId]);
    $comment = $stmt->fetch();

    return is_array($comment) ? $comment : null;
}

function sr_content_account_can_edit_comment(array $comment, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) ($comment['author_account_id'] ?? 0) === (int) $account['id']
        && (string) ($comment['status'] ?? '') === 'published';
}

function sr_content_account_can_delete_comment(array $comment, array $account, ?PDO $pdo = null): bool
{
    if (sr_content_account_can_edit_comment($comment, $account)) {
        return true;
    }
    if (!$pdo instanceof PDO) {
        return false;
    }

    return sr_content_account_can_hide_comment($pdo, $comment, $account);
}

function sr_content_account_can_hide_comment(PDO $pdo, array $comment, ?array $account): bool
{
    if (!is_array($account) || (int) ($account['id'] ?? 0) < 1 || (string) ($comment['status'] ?? '') !== 'published') {
        return false;
    }

    return function_exists('sr_admin_has_permission')
        && (sr_admin_has_permission($pdo, (int) $account['id'], '/admin/content', 'edit')
            || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/content', 'delete'));
}

function sr_content_account_can_view_comment_body(array $comment, array $page, ?array $account, ?PDO $pdo = null): bool
{
    if ((int) ($comment['is_secret'] ?? 0) !== 1) {
        return true;
    }
    if (!is_array($account)) {
        return false;
    }

    $accountId = (int) ($account['id'] ?? 0);

    return $accountId > 0
        && ($accountId === (int) ($comment['author_account_id'] ?? 0)
            || $accountId === (int) ($page['created_by'] ?? 0)
            || ($pdo instanceof PDO && sr_content_account_can_hide_comment($pdo, $comment, $account)));
}

function sr_content_update_comment_content(PDO $pdo, int $commentId, array $values): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_content_comments
         SET body_text = :body_text,
             is_secret = :is_secret,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $params = [
        'body_text' => trim((string) $values['body_text']),
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0,
        'updated_at' => sr_now(),
        'id' => $commentId,
    ];
    $stmt->execute($params);
}

function sr_content_update_comment_status(PDO $pdo, int $commentId, string $status): void
{
    if ($status === 'deleted') {
        sr_content_delete_comment_redacted($pdo, $commentId);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_content_comments
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $commentId,
    ]);
}

function sr_content_delete_comment_redacted(PDO $pdo, int $commentId): void
{
    $stmt = $pdo->prepare(
        "UPDATE sr_content_comments
         SET body_text = :body_text,
             extra_values_json = NULL,
             author_public_name_snapshot = '',
             status = 'deleted',
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'body_text' => sr_t('content::redaction.deleted_comment_body'),
        'updated_at' => sr_now(),
        'id' => $commentId,
    ]);
}

function sr_content_notification_create_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_function');
}

function sr_content_notification_event_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_content_create_account_event_notification(
    PDO $pdo,
    int $accountId,
    string $eventKey,
    array $metadata,
    ?int $createdByAccountId = null
): bool {
    $createAccountEventFunction = sr_content_notification_event_function($pdo);
    if ($accountId < 1 || $createAccountEventFunction === '') {
        return false;
    }

    try {
        return $createAccountEventFunction($pdo, [
            'account_id' => $accountId,
            'module_key' => 'content',
            'event_key' => $eventKey,
            'created_by_account_id' => $createdByAccountId,
            'metadata' => $metadata,
        ]) !== null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'content_notification_event_create');
    }

    return false;
}

function sr_content_create_follow_notifications(PDO $pdo, array $page, ?int $createdByAccountId = null): int
{
    $contentId = (int) ($page['id'] ?? 0);
    $authorAccountId = (int) ($page['created_by'] ?? 0);
    if ($contentId < 1 || $authorAccountId < 1 || (string) ($page['status'] ?? '') !== 'published') {
        return 0;
    }

    $metadata = [
        'content_id' => $contentId,
        'content_title' => (string) ($page['title'] ?? ''),
        'member_name' => sr_member_public_name_for_account_id($pdo, $authorAccountId, '회원'),
        'link_url' => sr_content_path((string) ($page['slug'] ?? '')),
        'created_at' => sr_now(),
    ];

    $createdCount = 0;
    foreach (sr_member_followers($pdo, $authorAccountId) as $followerAccountId) {
        if ($followerAccountId === $authorAccountId) {
            continue;
        }
        if (sr_content_create_account_event_notification($pdo, $followerAccountId, 'followed_author.content_created', $metadata, $createdByAccountId)) {
            $createdCount++;
        }
    }

    return $createdCount;
}

function sr_content_mentioned_account_ids(PDO $pdo, string $bodyText, array $excludeAccountIds = []): array
{
    return sr_member_mention_account_ids($pdo, sr_runtime_config(), $bodyText, $excludeAccountIds);
}

function sr_content_create_comment_mention_notifications(
    PDO $pdo,
    array $page,
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
    $contentId = (int) ($page['id'] ?? 0);
    if ($contentId < 1 || $commentId < 1) {
        return $result;
    }

    $authorAccountId = (int) ($page['created_by'] ?? 0);
    $excludeAccountIds[] = $createdByAccountId;
    if ($authorAccountId > 0) {
        $excludeAccountIds[] = $authorAccountId;
    }
    $mentionedAccountIds = sr_content_mentioned_account_ids($pdo, $bodyText, $excludeAccountIds);
    if ($previousBodyText !== null) {
        $previousAccountIds = sr_content_mentioned_account_ids($pdo, $previousBodyText, $excludeAccountIds);
        $previousMap = array_fill_keys(array_map('intval', $previousAccountIds), true);
        $mentionedAccountIds = array_values(array_filter($mentionedAccountIds, static function (int $accountId) use ($previousMap): bool {
            return !isset($previousMap[$accountId]);
        }));
    }

    $result['mention_candidate_count'] = count($mentionedAccountIds);
    $config = sr_runtime_config();
    $metadata = [
        'content_id' => $contentId,
        'comment_id' => $commentId,
        'member_name' => sr_member_public_name_for_account_id($pdo, $createdByAccountId, '회원'),
        'link_url' => sr_content_path((string) ($page['slug'] ?? '')) . '#content-comments',
        'created_at' => sr_now(),
    ];
    foreach ($mentionedAccountIds as $accountId) {
        $result['mention_account_hashes'][] = sr_member_public_account_hash($config, (int) $accountId);
    }
    foreach ($mentionedAccountIds as $accountId) {
        if (sr_content_create_account_event_notification($pdo, (int) $accountId, 'comment.mention', $metadata, $createdByAccountId)) {
            $result['mention_notification_count']++;
        }
    }

    return $result;
}

function sr_content_create_comment_notifications(PDO $pdo, array $page, int $commentId, string $bodyText, int $createdByAccountId, bool $createMentionNotifications = true, array $mentionExcludeAccountIds = [], ?array $parentComment = null): array
{
    $result = [
        'content_author_notification_created' => false,
        'parent_author_notification_created' => false,
        'mention_candidate_count' => 0,
        'mention_notification_count' => 0,
        'mention_account_hashes' => [],
    ];
    $contentId = (int) ($page['id'] ?? 0);
    $link = sr_content_path((string) ($page['slug'] ?? '')) . '#content-comments';
    $authorAccountId = (int) ($page['created_by'] ?? 0);
    $memberName = sr_member_public_name_for_account_id($pdo, $createdByAccountId, '회원');
    $metadata = [
        'content_id' => $contentId,
        'comment_id' => $commentId,
        'parent_comment_id' => is_array($parentComment) ? (int) ($parentComment['id'] ?? 0) : 0,
        'member_name' => $memberName,
        'link_url' => $link,
        'created_at' => sr_now(),
    ];
    if ($authorAccountId > 0 && $authorAccountId !== $createdByAccountId) {
        $result['content_author_notification_created'] = sr_content_create_account_event_notification($pdo, $authorAccountId, 'comment.created', $metadata, $createdByAccountId);
    }
    if (is_array($parentComment)
        && (int) ($parentComment['author_account_id'] ?? 0) > 0
        && (int) ($parentComment['author_account_id'] ?? 0) !== $createdByAccountId
        && (int) ($parentComment['author_account_id'] ?? 0) !== $authorAccountId) {
        $result['parent_author_notification_created'] = sr_content_create_account_event_notification($pdo, (int) $parentComment['author_account_id'], 'comment.created', $metadata, $createdByAccountId);
    }

    if ($createMentionNotifications) {
        $mentionResult = sr_content_create_comment_mention_notifications($pdo, $page, $commentId, $bodyText, $createdByAccountId, $mentionExcludeAccountIds);
        $result['mention_candidate_count'] = (int) ($mentionResult['mention_candidate_count'] ?? 0);
        $result['mention_notification_count'] = (int) ($mentionResult['mention_notification_count'] ?? 0);
        $result['mention_account_hashes'] = $mentionResult['mention_account_hashes'] ?? [];
    }

    return $result;
}
