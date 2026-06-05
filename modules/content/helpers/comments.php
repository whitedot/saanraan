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
    $stmt = $pdo->prepare(
        "SELECT c.*, " . $snapshotSelect . " a.display_name AS author_display_name, " . $nicknameSelect . " a.status AS author_account_status
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

function sr_content_comment_input_values(): array
{
    return [
        'body_text' => sr_post_string_without_truncation('body_text', 5000),
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
    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_comments
            (content_id, author_account_id, ' . $snapshotColumnSql . 'body_text, status, created_at, updated_at)
         VALUES
            (:content_id, :author_account_id, ' . $snapshotValueSql . ':body_text, :status, :created_at, :updated_at)'
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
    $stmt->execute($params);

    return (int) $pdo->lastInsertId();
}

function sr_content_notification_available(PDO $pdo): bool
{
    return sr_content_notification_create_function($pdo) !== '';
}

function sr_content_notification_create_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_function');
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

function sr_content_mention_tokens(string $bodyText): array
{
    if (!preg_match_all('/@([^\s@#:,.;!?()\[\]{}<>]{2,40})/u', $bodyText, $matches)) {
        return [];
    }

    $tokens = [];
    foreach ($matches[1] as $token) {
        $token = trim((string) $token);
        if ($token !== '') {
            $tokens[$token] = true;
        }
    }

    return array_keys($tokens);
}

function sr_content_mentioned_account_ids(PDO $pdo, string $bodyText, array $excludeAccountIds = []): array
{
    $tokens = sr_content_mention_tokens($bodyText);
    if ($tokens === []) {
        return [];
    }

    return sr_member_public_name_lookup_account_ids($pdo, $tokens, $excludeAccountIds);
}

function sr_content_create_comment_notifications(PDO $pdo, array $page, int $commentId, string $bodyText, int $createdByAccountId): array
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
    if ($authorAccountId > 0 && $authorAccountId !== $createdByAccountId) {
        $result['content_author_notification_created'] = sr_content_create_account_notification($pdo, $authorAccountId, '새 콘텐츠 댓글이 등록되었습니다.', '회원님의 콘텐츠에 새 댓글이 등록되었습니다.', $link, $createdByAccountId);
    }

    $mentionedAccountIds = sr_content_mentioned_account_ids($pdo, $bodyText, [$createdByAccountId, $authorAccountId]);
    $result['mention_candidate_count'] = count($mentionedAccountIds);
    $config = sr_runtime_config();
    foreach ($mentionedAccountIds as $accountId) {
        $result['mention_account_hashes'][] = sr_member_public_account_hash($config, (int) $accountId);
    }
    foreach ($mentionedAccountIds as $accountId) {
        if (sr_content_create_account_notification($pdo, $accountId, '콘텐츠 댓글 멘션 알림', '콘텐츠 댓글에서 회원님을 언급했습니다.', $link, $createdByAccountId)) {
            $result['mention_notification_count']++;
        }
    }

    return $result;
}
