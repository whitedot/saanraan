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

function sr_content_comments(PDO $pdo, int $contentId, int $limit = 100): array
{
    if ($contentId < 1 || !sr_content_comments_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT c.*, a.display_name AS author_display_name, a.status AS author_account_status
         FROM sr_content_comments c
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         WHERE c.content_id = :content_id
           AND c.status = 'published'
         ORDER BY c.id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('content_id', $contentId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
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
    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_comments
            (content_id, author_account_id, body_text, status, created_at, updated_at)
         VALUES
            (:content_id, :author_account_id, :body_text, :status, :created_at, :updated_at)'
    );
    $stmt->execute([
        'content_id' => $contentId,
        'author_account_id' => $authorAccountId,
        'body_text' => trim((string) $values['body_text']),
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_content_notification_available(PDO $pdo): bool
{
    if (!function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'notification')) {
        return false;
    }

    $helperPath = SR_ROOT . '/modules/notification/helpers.php';
    if (!is_file($helperPath)) {
        return false;
    }

    require_once $helperPath;

    return function_exists('sr_notification_create');
}

function sr_content_create_account_notification(PDO $pdo, int $accountId, string $title, string $bodyText, string $linkUrl, ?int $createdByAccountId = null): bool
{
    if ($accountId < 1 || !sr_content_notification_available($pdo)) {
        return false;
    }

    try {
        sr_notification_create($pdo, [
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

    $exclude = [];
    foreach ($excludeAccountIds as $accountId) {
        $accountId = (int) $accountId;
        if ($accountId > 0) {
            $exclude[$accountId] = true;
        }
    }

    $placeholders = implode(',', array_fill(0, count($tokens), '?'));
    $stmt = $pdo->prepare(
        "SELECT id
         FROM sr_member_accounts
         WHERE display_name IN (" . $placeholders . ")
           AND status = 'active'"
    );
    $stmt->execute($tokens);

    $accountIds = [];
    foreach ($stmt->fetchAll() as $row) {
        $accountId = (int) ($row['id'] ?? 0);
        if ($accountId > 0 && !isset($exclude[$accountId])) {
            $accountIds[$accountId] = true;
        }
    }

    return array_keys($accountIds);
}

function sr_content_create_comment_notifications(PDO $pdo, array $page, int $commentId, string $bodyText, int $createdByAccountId): void
{
    $contentId = (int) ($page['id'] ?? 0);
    $link = sr_content_path((string) ($page['slug'] ?? '')) . '#content-comments';
    $authorAccountId = (int) ($page['created_by'] ?? 0);
    if ($authorAccountId > 0 && $authorAccountId !== $createdByAccountId) {
        sr_content_create_account_notification($pdo, $authorAccountId, '새 콘텐츠 댓글이 등록되었습니다.', '회원님의 콘텐츠에 새 댓글이 등록되었습니다.', $link, $createdByAccountId);
    }

    foreach (sr_content_mentioned_account_ids($pdo, $bodyText, [$createdByAccountId, $authorAccountId]) as $accountId) {
        sr_content_create_account_notification($pdo, $accountId, '콘텐츠 댓글 멘션 알림', '콘텐츠 댓글에서 회원님을 언급했습니다.', $link, $createdByAccountId);
    }
}
