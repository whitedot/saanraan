<?php

declare(strict_types=1);

function toy_community_public_board_by_key(PDO $pdo, string $boardKey): ?array
{
    $board = toy_community_board_by_key($pdo, $boardKey);
    if (!is_array($board) || (string) $board['status'] !== 'enabled' || (string) $board['read_policy'] !== 'public') {
        return null;
    }

    return $board;
}

function toy_community_account_can_read_board(PDO $pdo, array $board, ?array $account): bool
{
    if ((string) ($board['status'] ?? '') !== 'enabled') {
        return false;
    }

    $policy = (string) ($board['read_policy'] ?? '');
    if ($policy === 'public') {
        return true;
    }

    $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
    if ($accountId < 1) {
        return false;
    }

    if ($policy === 'member') {
        return true;
    }

    if ($policy === 'group') {
        $groupKeys = toy_community_board_group_keys($pdo, (int) $board['id'], 'read_group_keys');
        return $groupKeys !== [] && toy_member_account_in_any_group($pdo, $accountId, $groupKeys);
    }

    return false;
}

function toy_community_board_requires_login(array $board): bool
{
    return in_array((string) ($board['read_policy'] ?? ''), ['member', 'group'], true);
}

function toy_community_public_posts(PDO $pdo, int $boardId, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT id, board_id, author_account_id, title, body_text, body_format, status, view_count, last_commented_at, created_at, updated_at
         FROM toy_community_posts
         WHERE board_id = :board_id
           AND status = 'published'
         ORDER BY id DESC
         LIMIT :limit_value"
    );
    $stmt->bindValue('board_id', $boardId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function toy_community_public_post(PDO $pdo, int $postId): ?array
{
    if ($postId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT p.id, p.board_id, p.author_account_id, p.title, p.body_text, p.body_format, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                b.board_key, b.title AS board_title, b.description AS board_description, b.status AS board_status, b.read_policy, b.comment_policy
         FROM toy_community_posts p
         INNER JOIN toy_community_boards b ON b.id = p.board_id
         WHERE p.id = :id
           AND p.status = 'published'
           AND b.status = 'enabled'
           AND b.read_policy = 'public'
         LIMIT 1"
    );
    $stmt->execute(['id' => $postId]);
    $post = $stmt->fetch();

    return is_array($post) ? $post : null;
}

function toy_community_post_for_read(PDO $pdo, int $postId, ?array $account): ?array
{
    if ($postId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT p.id, p.board_id, p.author_account_id, p.title, p.body_text, p.body_format, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                b.board_key, b.title AS board_title, b.description AS board_description, b.status AS board_status, b.read_policy, b.comment_policy
         FROM toy_community_posts p
         INNER JOIN toy_community_boards b ON b.id = p.board_id
         WHERE p.id = :id
           AND p.status = 'published'
           AND b.status = 'enabled'
         LIMIT 1"
    );
    $stmt->execute(['id' => $postId]);
    $post = $stmt->fetch();
    if (!is_array($post)) {
        return null;
    }

    $board = [
        'id' => (int) $post['board_id'],
        'status' => (string) $post['board_status'],
        'read_policy' => (string) $post['read_policy'],
    ];

    return toy_community_account_can_read_board($pdo, $board, $account) ? $post : null;
}

function toy_community_increment_post_view_count(PDO $pdo, int $postId): void
{
    if ($postId < 1) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE toy_community_posts
         SET view_count = view_count + 1
         WHERE id = :id'
    );
    $stmt->execute(['id' => $postId]);
}

function toy_community_public_comments(PDO $pdo, int $postId, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT id, post_id, author_account_id, body_text, status, created_at, updated_at
         FROM toy_community_comments
         WHERE post_id = :post_id
           AND status = 'published'
         ORDER BY id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('post_id', $postId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function toy_community_post_statuses(): array
{
    return ['published', 'hidden', 'deleted', 'pending'];
}

function toy_community_admin_posts(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare(
        'SELECT p.id, p.board_id, p.author_account_id, p.title, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                b.board_key, b.title AS board_title,
                a.display_name AS author_display_name,
                (SELECT COUNT(*) FROM toy_community_comments c WHERE c.post_id = p.id AND c.status = \'published\') AS published_comment_count
         FROM toy_community_posts p
         INNER JOIN toy_community_boards b ON b.id = p.board_id
         LEFT JOIN toy_member_accounts a ON a.id = p.author_account_id
         ORDER BY p.id DESC
         LIMIT :limit_value'
    );
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function toy_community_admin_post_by_id(PDO $pdo, int $postId): ?array
{
    if ($postId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT p.id, p.board_id, p.author_account_id, p.title, p.body_text, p.body_format, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                b.board_key, b.title AS board_title,
                a.display_name AS author_display_name
         FROM toy_community_posts p
         INNER JOIN toy_community_boards b ON b.id = p.board_id
         LEFT JOIN toy_member_accounts a ON a.id = p.author_account_id
         WHERE p.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $postId]);
    $post = $stmt->fetch();

    return is_array($post) ? $post : null;
}

function toy_community_update_post_status(PDO $pdo, int $postId, string $status): void
{
    $stmt = $pdo->prepare(
        'UPDATE toy_community_posts
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => toy_now(),
        'id' => $postId,
    ]);
}

function toy_community_account_can_delete_post(array $post, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) $post['author_account_id'] === (int) $account['id']
        && (string) $post['status'] === 'published';
}

function toy_community_comment_statuses(): array
{
    return ['published', 'hidden', 'deleted'];
}

function toy_community_admin_comments(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare(
        'SELECT c.id, c.post_id, c.author_account_id, c.body_text, c.status, c.created_at, c.updated_at,
                p.title AS post_title,
                b.board_key, b.title AS board_title,
                a.display_name AS author_display_name
         FROM toy_community_comments c
         INNER JOIN toy_community_posts p ON p.id = c.post_id
         INNER JOIN toy_community_boards b ON b.id = p.board_id
         LEFT JOIN toy_member_accounts a ON a.id = c.author_account_id
         ORDER BY c.id DESC
         LIMIT :limit_value'
    );
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function toy_community_admin_comment_by_id(PDO $pdo, int $commentId): ?array
{
    if ($commentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT c.id, c.post_id, c.author_account_id, c.body_text, c.status, c.created_at, c.updated_at,
                p.title AS post_title,
                b.board_key, b.title AS board_title,
                a.display_name AS author_display_name
         FROM toy_community_comments c
         INNER JOIN toy_community_posts p ON p.id = c.post_id
         INNER JOIN toy_community_boards b ON b.id = p.board_id
         LEFT JOIN toy_member_accounts a ON a.id = c.author_account_id
         WHERE c.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $commentId]);
    $comment = $stmt->fetch();

    return is_array($comment) ? $comment : null;
}

function toy_community_update_comment_status(PDO $pdo, int $commentId, string $status): void
{
    $stmt = $pdo->prepare(
        'UPDATE toy_community_comments
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => toy_now(),
        'id' => $commentId,
    ]);
}

function toy_community_account_can_delete_comment(array $comment, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) $comment['author_account_id'] === (int) $account['id']
        && (string) $comment['status'] === 'published';
}

function toy_community_account_can_write_board(PDO $pdo, array $board, array $account, bool $isAdminWriter = false): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1 || (string) ($board['status'] ?? '') !== 'enabled') {
        return false;
    }

    $policy = (string) ($board['write_policy'] ?? '');
    if ($policy === 'member') {
        return true;
    }

    if ($policy === 'group') {
        $groupKeys = toy_community_board_group_keys($pdo, (int) $board['id'], 'write_group_keys');
        return $groupKeys !== [] && toy_member_account_in_any_group($pdo, $accountId, $groupKeys);
    }

    if ($policy === 'admin') {
        return $isAdminWriter;
    }

    return false;
}

function toy_community_board_group_keys(PDO $pdo, int $boardId, string $settingKey): array
{
    if ($boardId < 1 || !in_array($settingKey, ['read_group_keys', 'write_group_keys', 'comment_group_keys'], true)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT setting_value
         FROM toy_community_board_settings
         WHERE board_id = :board_id
           AND setting_key = :setting_key
         LIMIT 1'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'setting_key' => $settingKey,
    ]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return [];
    }

    $value = trim((string) ($row['setting_value'] ?? ''));
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    $rawKeys = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
    $groupKeys = [];
    foreach ($rawKeys as $rawKey) {
        $groupKey = trim((string) $rawKey);
        if ($groupKey !== '' && toy_member_group_key_is_valid($groupKey)) {
            $groupKeys[] = $groupKey;
        }
    }

    return array_values(array_unique($groupKeys));
}

function toy_community_post_input_values(): array
{
    return [
        'title' => toy_post_string_without_truncation('title', 160),
        'body_text' => toy_post_string_without_truncation('body_text', 20000),
    ];
}

function toy_community_validate_post_input(array $values): array
{
    $errors = [];
    $title = $values['title'];
    $bodyText = $values['body_text'];

    if (!is_string($title)) {
        $errors[] = '제목은 160자 이내로 입력해 주세요.';
    } elseif (trim($title) === '') {
        $errors[] = '제목을 입력해 주세요.';
    }

    if (!is_string($bodyText)) {
        $errors[] = '본문은 20000자 이내로 입력해 주세요.';
    } elseif (trim($bodyText) === '') {
        $errors[] = '본문을 입력해 주세요.';
    }

    return $errors;
}

function toy_community_create_post(PDO $pdo, int $boardId, int $authorAccountId, array $values): int
{
    $now = toy_now();
    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_posts
            (board_id, author_account_id, title, body_text, body_format, status, view_count, last_commented_at, created_at, updated_at)
         VALUES
            (:board_id, :author_account_id, :title, :body_text, :body_format, :status, 0, NULL, :created_at, :updated_at)'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'author_account_id' => $authorAccountId,
        'title' => trim((string) $values['title']),
        'body_text' => trim((string) $values['body_text']),
        'body_format' => 'plain',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function toy_community_post_rate_limited(PDO $pdo, int $accountId, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    $limit = min(100, max(1, (int) ($settings['post_create_limit'] ?? 10)));

    return toy_community_rate_limits_table_exists($pdo)
        && toy_rate_limit_count($pdo, 'community.post.account', (string) $accountId, $windowSeconds) >= $limit;
}

function toy_community_record_post_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if (!toy_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    toy_rate_limit_increment($pdo, 'community.post.account', (string) $accountId, $windowSeconds);
}

function toy_community_account_can_comment_post(PDO $pdo, array $post, array $account): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1 || (string) ($post['status'] ?? '') !== 'published' || (string) ($post['board_status'] ?? '') !== 'enabled') {
        return false;
    }

    $policy = (string) ($post['comment_policy'] ?? '');
    if ($policy === 'member') {
        return true;
    }

    if ($policy === 'group') {
        $groupKeys = toy_community_board_group_keys($pdo, (int) $post['board_id'], 'comment_group_keys');
        return $groupKeys !== [] && toy_member_account_in_any_group($pdo, $accountId, $groupKeys);
    }

    return false;
}

function toy_community_comment_input_values(): array
{
    return [
        'body_text' => toy_post_string_without_truncation('body_text', 5000),
    ];
}

function toy_community_validate_comment_input(array $values): array
{
    $bodyText = $values['body_text'];
    if (!is_string($bodyText)) {
        return ['댓글은 5000자 이내로 입력해 주세요.'];
    }

    if (trim($bodyText) === '') {
        return ['댓글을 입력해 주세요.'];
    }

    return [];
}

function toy_community_create_comment(PDO $pdo, int $postId, int $authorAccountId, array $values): int
{
    $now = toy_now();
    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_comments
            (post_id, author_account_id, body_text, status, created_at, updated_at)
         VALUES
            (:post_id, :author_account_id, :body_text, :status, :created_at, :updated_at)'
    );
    $stmt->execute([
        'post_id' => $postId,
        'author_account_id' => $authorAccountId,
        'body_text' => trim((string) $values['body_text']),
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $commentId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'UPDATE toy_community_posts
         SET last_commented_at = :last_commented_at,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'last_commented_at' => $now,
        'updated_at' => $now,
        'id' => $postId,
    ]);

    return $commentId;
}

function toy_community_comment_rate_limited(PDO $pdo, int $accountId, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['comment_create_window_seconds'] ?? 300)));
    $limit = min(300, max(1, (int) ($settings['comment_create_limit'] ?? 30)));

    return toy_community_rate_limits_table_exists($pdo)
        && toy_rate_limit_count($pdo, 'community.comment.account', (string) $accountId, $windowSeconds) >= $limit;
}

function toy_community_record_comment_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if (!toy_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['comment_create_window_seconds'] ?? 300)));
    toy_rate_limit_increment($pdo, 'community.comment.account', (string) $accountId, $windowSeconds);
}

function toy_community_rate_limits_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM toy_rate_limits LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function toy_community_public_author_label(PDO $pdo, int $accountId): string
{
    $summary = toy_member_public_account_summary($pdo, $accountId);
    if (!is_array($summary) || (string) $summary['status'] === 'anonymized') {
        return '탈퇴 회원';
    }

    $displayName = trim((string) $summary['display_name']);
    return $displayName !== '' ? $displayName : '회원 #' . (string) $accountId;
}

function toy_community_plain_text_html(string $value): string
{
    return nl2br(toy_e($value), false);
}
