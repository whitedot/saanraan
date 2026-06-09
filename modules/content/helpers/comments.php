<?php

declare(strict_types=1);

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

function sr_content_comments_author_public_name_snapshot_column_exists(PDO $pdo): bool
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
               AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'table_name' => 'sr_content_comments',
            'column_name' => 'author_public_name_snapshot',
        ]);
        $existsByConnection[$key] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
}

function sr_content_comments_is_secret_column_exists(PDO $pdo): bool
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
               AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'table_name' => 'sr_content_comments',
            'column_name' => 'is_secret',
        ]);
        $existsByConnection[$key] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
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
    $snapshotSelect = sr_content_comments_author_public_name_snapshot_column_exists($pdo) ? 'c.author_public_name_snapshot,' : "'' AS author_public_name_snapshot,";
    $secretSelect = sr_content_comments_is_secret_column_exists($pdo) ? 'c.is_secret,' : '0 AS is_secret,';
    $stmt = $pdo->prepare(
        "SELECT c.*, " . $snapshotSelect . " " . $secretSelect . " a.display_name AS author_display_name, " . $nicknameSelect . " a.status AS author_account_status
         FROM sr_content_comments c
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         " . $join . "
         WHERE c.content_id = :content_id
           AND c.status = 'published'
         ORDER BY c.id ASC
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

function sr_content_recent_comments(PDO $pdo, int $limit = 8): array
{
    if (!sr_content_comments_table_exists($pdo)) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $join = sr_member_nicknames_table_exists($pdo) ? 'LEFT JOIN sr_member_nicknames n ON n.account_id = a.id' : '';
    $nicknameSelect = sr_member_nicknames_table_exists($pdo) ? 'n.nickname AS author_nickname,' : "'' AS author_nickname,";
    $snapshotSelect = sr_content_comments_author_public_name_snapshot_column_exists($pdo) ? 'c.author_public_name_snapshot,' : "'' AS author_public_name_snapshot,";
    $secretCondition = sr_content_comments_is_secret_column_exists($pdo) ? 'AND c.is_secret = 0' : '';
    $stmt = $pdo->prepare(
        "SELECT c.id, c.content_id, c.author_account_id, c.body_text, c.created_at, c.updated_at,
                " . $snapshotSelect . "
                p.slug AS content_slug, p.title AS content_title,
                a.display_name AS author_display_name, " . $nicknameSelect . " a.status AS author_account_status
         FROM sr_content_comments c
         INNER JOIN sr_content_items p ON p.id = c.content_id AND p.status = 'published'
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         " . $join . "
         WHERE c.status = 'published'
           " . $secretCondition . "
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
    return [
        'body_text' => sr_post_string_without_truncation('body_text', 5000),
        'is_secret' => sr_post_string('is_secret', 10) === '1' ? 1 : 0,
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

function sr_content_create_comment(PDO $pdo, int $contentId, int $authorAccountId, array $values): int
{
    if (!sr_content_comments_table_exists($pdo)) {
        throw new RuntimeException('content_comments_not_installed');
    }

    $now = sr_now();
    $snapshotColumnSql = sr_content_comments_author_public_name_snapshot_column_exists($pdo) ? 'author_public_name_snapshot, ' : '';
    $snapshotValueSql = $snapshotColumnSql !== '' ? ':author_public_name_snapshot, ' : '';
    $secretColumnSql = sr_content_comments_is_secret_column_exists($pdo) ? 'is_secret, ' : '';
    $secretValueSql = $secretColumnSql !== '' ? ':is_secret, ' : '';
    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_comments
            (content_id, author_account_id, ' . $snapshotColumnSql . 'body_text, ' . $secretColumnSql . 'status, created_at, updated_at)
         VALUES
            (:content_id, :author_account_id, ' . $snapshotValueSql . ':body_text, ' . $secretValueSql . ':status, :created_at, :updated_at)'
    );
    $params = [
        'content_id' => $contentId,
        'author_account_id' => $authorAccountId,
        'body_text' => trim((string) $values['body_text']),
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if ($snapshotColumnSql !== '') {
        $params['author_public_name_snapshot'] = sr_content_comment_author_public_name_snapshot($pdo, $authorAccountId);
    }
    if ($secretColumnSql !== '') {
        $params['is_secret'] = (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0;
    }
    $stmt->execute($params);

    return (int) $pdo->lastInsertId();
}

function sr_content_comment_by_id(PDO $pdo, int $commentId): ?array
{
    if ($commentId < 1 || !sr_content_comments_table_exists($pdo)) {
        return null;
    }

    $secretSelect = sr_content_comments_is_secret_column_exists($pdo) ? 'is_secret,' : '0 AS is_secret,';
    $stmt = $pdo->prepare(
        'SELECT id, content_id, author_account_id, body_text, ' . $secretSelect . ' status, created_at, updated_at
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
    $secretSql = sr_content_comments_is_secret_column_exists($pdo) ? 'is_secret = :is_secret,' : '';
    $stmt = $pdo->prepare(
        'UPDATE sr_content_comments
         SET body_text = :body_text,
             ' . $secretSql . '
             updated_at = :updated_at
         WHERE id = :id'
    );
    $params = [
        'body_text' => trim((string) $values['body_text']),
        'updated_at' => sr_now(),
        'id' => $commentId,
    ];
    if ($secretSql !== '') {
        $params['is_secret'] = (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0;
    }
    $stmt->execute($params);
}

function sr_content_update_comment_status(PDO $pdo, int $commentId, string $status): void
{
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

function sr_content_relative_time_label(string $dateTime): string
{
    $timestamp = strtotime($dateTime);
    if ($timestamp === false) {
        return $dateTime;
    }

    $seconds = time() - $timestamp;
    $isFuture = $seconds < 0;
    $diff = abs($seconds);
    $suffix = $isFuture ? ' 후' : ' 전';

    if ($diff < 60) {
        return $isFuture ? '잠시 후' : '방금 전';
    }
    if ($diff < 3600) {
        return (string) floor($diff / 60) . '분' . $suffix;
    }
    if ($diff < 86400) {
        return (string) floor($diff / 3600) . '시간' . $suffix;
    }
    if ($diff < 2592000) {
        return (string) floor($diff / 86400) . '일' . $suffix;
    }
    if ($diff < 31536000) {
        return (string) floor($diff / 2592000) . '개월' . $suffix;
    }

    return (string) floor($diff / 31536000) . '년' . $suffix;
}

function sr_content_notification_available(PDO $pdo): bool
{
    return sr_content_notification_create_function($pdo) !== '';
}

function sr_content_notification_create_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_function');
}

function sr_content_notification_event_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_content_create_account_notification(PDO $pdo, int $accountId, string $title, string $bodyText, string $linkUrl, ?int $createdByAccountId = null): bool
{
    $createNotificationFunction = sr_content_notification_create_function($pdo);
    if ($accountId < 1 || $createNotificationFunction === '') {
        return false;
    }

    try {
        $createNotificationFunction($pdo, [
            'audience' => 'account',
            'account_id' => $accountId,
            'title' => $title,
            'body_text' => $bodyText,
            'link_url' => $linkUrl,
            'channels' => ['site'],
            'created_by_account_id' => $createdByAccountId,
        ]);
        return true;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'content_notification_create');
    }

    return false;
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

function sr_content_mention_tokens(string $bodyText): array
{
    $tokens = [];
    foreach (sr_member_mention_token_rows($bodyText) as $row) {
        $tokens[(string) $row['token']] = true;
    }

    return array_keys($tokens);
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

function sr_content_create_comment_notifications(PDO $pdo, array $page, int $commentId, string $bodyText, int $createdByAccountId, bool $createMentionNotifications = true): array
{
    $result = [
        'content_author_notification_created' => false,
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
        'member_name' => $memberName,
        'link_url' => $link,
        'created_at' => sr_now(),
    ];
    if ($authorAccountId > 0 && $authorAccountId !== $createdByAccountId) {
        $result['content_author_notification_created'] = sr_content_create_account_event_notification($pdo, $authorAccountId, 'comment.created', $metadata, $createdByAccountId);
    }

    if ($createMentionNotifications) {
        $mentionResult = sr_content_create_comment_mention_notifications($pdo, $page, $commentId, $bodyText, $createdByAccountId);
        $result['mention_candidate_count'] = (int) ($mentionResult['mention_candidate_count'] ?? 0);
        $result['mention_notification_count'] = (int) ($mentionResult['mention_notification_count'] ?? 0);
        $result['mention_account_hashes'] = $mentionResult['mention_account_hashes'] ?? [];
    }

    return $result;
}
