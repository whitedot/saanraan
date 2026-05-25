<?php

declare(strict_types=1);

function sr_community_public_board_by_key(PDO $pdo, string $boardKey): ?array
{
    $board = sr_community_board_by_key($pdo, $boardKey);
    if (!is_array($board) || (string) $board['status'] !== 'enabled' || !sr_community_account_can_read_board($pdo, $board, null)) {
        return null;
    }

    return $board;
}

function sr_community_account_can_read_board(PDO $pdo, array $board, ?array $account): bool
{
    if ((string) ($board['status'] ?? '') !== 'enabled') {
        return false;
    }

    $policy = sr_community_effective_board_policy($pdo, $board, 'read_policy');
    if ($policy === 'public') {
        return true;
    }

    $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
    if ($accountId < 1) {
        return false;
    }

    if ($policy === 'member') {
        $minLevel = sr_community_board_min_level($pdo, (int) $board['id'], 'read_min_level');
        return !empty(sr_community_account_satisfies_access($pdo, $accountId, [
            'min_level' => $minLevel,
        ])['allowed']);
    }

    if ($policy === 'group') {
        $groupKeys = sr_community_board_group_keys($pdo, (int) $board['id'], 'read_group_keys');
        $minLevel = sr_community_board_min_level($pdo, (int) $board['id'], 'read_min_level');
        return !empty(sr_community_account_satisfies_access($pdo, $accountId, [
            'group_keys' => $groupKeys,
            'min_level' => $minLevel,
        ])['allowed']);
    }

    return false;
}

function sr_community_board_requires_login(array $board): bool
{
    return in_array((string) ($board['effective_read_policy'] ?? $board['read_policy'] ?? ''), ['member', 'group'], true);
}

function sr_community_board_posts(PDO $pdo, int $boardId, int $limit = 20, int $offset = 0, string $keyword = ''): array
{
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $keyword = trim($keyword);
    $where = "p.board_id = :board_id AND p.status = 'published'";
    $params = ['board_id' => $boardId];
    if ($keyword !== '') {
        $where .= " AND (p.title LIKE :title_keyword ESCAPE '\\\\' OR p.body_text LIKE :body_keyword ESCAPE '\\\\')";
        $params['title_keyword'] = sr_community_like_pattern($keyword);
        $params['body_keyword'] = sr_community_like_pattern($keyword);
    }

    $stmt = $pdo->prepare(
        'SELECT p.id, p.board_id, p.author_account_id, p.title, p.body_text, p.body_format, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                (SELECT COUNT(*) FROM sr_community_comments c WHERE c.post_id = p.id AND c.status = \'published\') AS published_comment_count,
                (SELECT COUNT(*) FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\') AS active_attachment_count
         FROM sr_community_posts p
         WHERE ' . $where . '
         ORDER BY p.id DESC
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === 'board_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_board_post_count(PDO $pdo, int $boardId, string $keyword = ''): int
{
    if ($boardId < 1) {
        return 0;
    }

    $keyword = trim($keyword);
    $where = "board_id = :board_id AND status = 'published'";
    $params = ['board_id' => $boardId];
    if ($keyword !== '') {
        $where .= " AND (title LIKE :title_keyword ESCAPE '\\\\' OR body_text LIKE :body_keyword ESCAPE '\\\\')";
        $params['title_keyword'] = sr_community_like_pattern($keyword);
        $params['body_keyword'] = sr_community_like_pattern($keyword);
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_community_posts
         WHERE ' . $where
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === 'board_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function sr_community_public_posts(PDO $pdo, int $boardId, int $limit = 20, int $offset = 0, string $keyword = ''): array
{
    return sr_community_board_posts($pdo, $boardId, $limit, $offset, $keyword);
}

function sr_community_public_post_count(PDO $pdo, int $boardId, string $keyword = ''): int
{
    return sr_community_board_post_count($pdo, $boardId, $keyword);
}

function sr_community_like_pattern(string $keyword): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($keyword)) . '%';
}

function sr_community_public_post(PDO $pdo, int $postId): ?array
{
    if ($postId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT p.id, p.board_id, p.author_account_id, p.title, p.body_text, p.body_format, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                b.board_group_id, b.board_key, b.title AS board_title, b.description AS board_description, b.status AS board_status, b.read_policy, b.comment_policy
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
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
        'board_group_id' => (int) ($post['board_group_id'] ?? 0),
        'status' => (string) $post['board_status'],
        'read_policy' => (string) $post['read_policy'],
    ];

    if (!sr_community_account_can_read_board($pdo, $board, null)) {
        return null;
    }

    $post['read_policy'] = sr_community_effective_board_policy($pdo, $board, 'read_policy');
    return $post;
}

function sr_community_post_for_read(PDO $pdo, int $postId, ?array $account): ?array
{
    if ($postId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT p.id, p.board_id, p.author_account_id, p.title, p.body_text, p.body_format, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                b.board_group_id, b.board_key, b.title AS board_title, b.description AS board_description, b.status AS board_status, b.read_policy, b.comment_policy
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
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
        'board_group_id' => (int) ($post['board_group_id'] ?? 0),
        'status' => (string) $post['board_status'],
        'read_policy' => (string) $post['read_policy'],
        'comment_policy' => (string) $post['comment_policy'],
    ];

    if (!sr_community_account_can_read_board($pdo, $board, $account)) {
        return null;
    }

    $post['read_policy'] = sr_community_effective_board_policy($pdo, $board, 'read_policy');
    $post['comment_policy'] = sr_community_effective_board_policy($pdo, $board, 'comment_policy');
    return $post;
}

function sr_community_increment_post_view_count(PDO $pdo, int $postId): void
{
    if ($postId < 1) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_posts
         SET view_count = view_count + 1
         WHERE id = :id'
    );
    $stmt->execute(['id' => $postId]);
}

function sr_community_post_comments(PDO $pdo, int $postId, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT id, post_id, author_account_id, body_text, status, created_at, updated_at
         FROM sr_community_comments
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

function sr_community_public_comments(PDO $pdo, int $postId, int $limit = 50): array
{
    return sr_community_post_comments($pdo, $postId, $limit);
}

function sr_community_post_statuses(): array
{
    return ['published', 'hidden', 'deleted', 'pending'];
}

function sr_community_admin_post_query_parts(array $filters): array
{
    $where = [];
    $params = [];

    if ((string) ($filters['status'] ?? '') !== '') {
        $where[] = 'p.status = :status';
        $params['status'] = (string) $filters['status'];
    }

    if ((int) ($filters['board_id'] ?? 0) > 0) {
        $where[] = 'p.board_id = :board_id';
        $params['board_id'] = (int) $filters['board_id'];
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'title') {
            $where[] = 'p.title LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'author') {
            $where[] = '(a.display_name LIKE :author_display_keyword OR (a.status NOT IN (\'withdrawn\', \'anonymized\') AND author_nickname.nickname LIKE :author_nickname_keyword))';
            $params['author_display_keyword'] = '%' . $keyword . '%';
            $params['author_nickname_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'board') {
            $where[] = '(b.title LIKE :board_title_keyword OR b.board_key LIKE :board_key_keyword)';
            $params['board_title_keyword'] = '%' . $keyword . '%';
            $params['board_key_keyword'] = '%' . $keyword . '%';
        } else {
            $where[] = '(p.title LIKE :title_keyword OR a.display_name LIKE :author_keyword OR (a.status NOT IN (\'withdrawn\', \'anonymized\') AND author_nickname.nickname LIKE :author_nickname_keyword) OR b.title LIKE :board_title_keyword OR b.board_key LIKE :board_key_keyword)';
            $params['title_keyword'] = '%' . $keyword . '%';
            $params['author_keyword'] = '%' . $keyword . '%';
            $params['author_nickname_keyword'] = '%' . $keyword . '%';
            $params['board_title_keyword'] = '%' . $keyword . '%';
            $params['board_key_keyword'] = '%' . $keyword . '%';
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_community_admin_post_count(PDO $pdo, array $filters = []): int
{
    $queryParts = sr_community_admin_post_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value
            FROM sr_community_posts p
            INNER JOIN sr_community_boards b ON b.id = p.board_id
            LEFT JOIN sr_member_accounts a ON a.id = p.author_account_id
            LEFT JOIN sr_community_member_nicknames author_nickname ON author_nickname.account_id = a.id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_admin_posts(PDO $pdo, int $limit = 100, array $filters = [], int $offset = 0): array
{
    $useLimit = $limit > 0;
    if ($useLimit) {
        $limit = max(1, min(1000, $limit));
    }
    $queryParts = sr_community_admin_post_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $sql = 'SELECT p.id, p.board_id, p.author_account_id, p.title, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                   b.board_key, b.title AS board_title,
                   a.display_name AS author_display_name,
                   author_nickname.nickname AS author_nickname,
                   a.status AS author_account_status,
                   (SELECT COUNT(*) FROM sr_community_comments c WHERE c.post_id = p.id AND c.status = \'published\') AS published_comment_count,
                   (SELECT COUNT(*) FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\') AS active_attachment_count
            FROM sr_community_posts p
            INNER JOIN sr_community_boards b ON b.id = p.board_id
            LEFT JOIN sr_member_accounts a ON a.id = p.author_account_id
            LEFT JOIN sr_community_member_nicknames author_nickname ON author_nickname.account_id = a.id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.id DESC';
    if ($useLimit) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($useLimit) {
        $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_admin_post_by_id(PDO $pdo, int $postId): ?array
{
    if ($postId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT p.id, p.board_id, p.author_account_id, p.title, p.body_text, p.body_format, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                b.board_key, b.title AS board_title,
                a.display_name AS author_display_name,
                author_nickname.nickname AS author_nickname,
                a.status AS author_account_status
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         LEFT JOIN sr_member_accounts a ON a.id = p.author_account_id
         LEFT JOIN sr_community_member_nicknames author_nickname ON author_nickname.account_id = a.id
         WHERE p.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $postId]);
    $post = $stmt->fetch();

    return is_array($post) ? $post : null;
}

function sr_community_update_post_status(PDO $pdo, int $postId, string $status): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_posts
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $postId,
    ]);
}

function sr_community_update_post_content(PDO $pdo, int $postId, array $values): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_posts
         SET title = :title,
             body_text = :body_text,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'title' => trim((string) $values['title']),
        'body_text' => trim((string) $values['body_text']),
        'updated_at' => sr_now(),
        'id' => $postId,
    ]);
}

function sr_community_account_can_edit_post(array $post, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) $post['author_account_id'] === (int) $account['id']
        && (string) $post['status'] === 'published';
}

function sr_community_account_can_delete_post(array $post, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) $post['author_account_id'] === (int) $account['id']
        && (string) $post['status'] === 'published';
}

function sr_community_comment_statuses(): array
{
    return ['published', 'hidden', 'deleted'];
}

function sr_community_admin_comment_query_parts(array $filters): array
{
    $where = [];
    $params = [];

    if ((string) ($filters['status'] ?? '') !== '') {
        $where[] = 'c.status = :status';
        $params['status'] = (string) $filters['status'];
    }

    if ((int) ($filters['board_id'] ?? 0) > 0) {
        $where[] = 'b.id = :board_id';
        $params['board_id'] = (int) $filters['board_id'];
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'body') {
            $where[] = 'c.body_text LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'author') {
            $where[] = '(a.display_name LIKE :author_display_keyword OR (a.status NOT IN (\'withdrawn\', \'anonymized\') AND author_nickname.nickname LIKE :author_nickname_keyword))';
            $params['author_display_keyword'] = '%' . $keyword . '%';
            $params['author_nickname_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'post') {
            $where[] = 'p.title LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'board') {
            $where[] = '(b.title LIKE :board_title_keyword OR b.board_key LIKE :board_key_keyword)';
            $params['board_title_keyword'] = '%' . $keyword . '%';
            $params['board_key_keyword'] = '%' . $keyword . '%';
        } else {
            $where[] = '(c.body_text LIKE :body_keyword OR p.title LIKE :post_title_keyword OR a.display_name LIKE :author_keyword OR (a.status NOT IN (\'withdrawn\', \'anonymized\') AND author_nickname.nickname LIKE :author_nickname_keyword) OR b.title LIKE :board_title_keyword OR b.board_key LIKE :board_key_keyword)';
            $params['body_keyword'] = '%' . $keyword . '%';
            $params['post_title_keyword'] = '%' . $keyword . '%';
            $params['author_keyword'] = '%' . $keyword . '%';
            $params['author_nickname_keyword'] = '%' . $keyword . '%';
            $params['board_title_keyword'] = '%' . $keyword . '%';
            $params['board_key_keyword'] = '%' . $keyword . '%';
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_community_admin_comment_count(PDO $pdo, array $filters = []): int
{
    $queryParts = sr_community_admin_comment_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value
            FROM sr_community_comments c
            INNER JOIN sr_community_posts p ON p.id = c.post_id
            INNER JOIN sr_community_boards b ON b.id = p.board_id
            LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
            LEFT JOIN sr_community_member_nicknames author_nickname ON author_nickname.account_id = a.id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_admin_comments(PDO $pdo, int $limit = 100, array $filters = [], int $offset = 0): array
{
    $useLimit = $limit > 0;
    if ($useLimit) {
        $limit = max(1, min(1000, $limit));
    }
    $queryParts = sr_community_admin_comment_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $sql = 'SELECT c.id, c.post_id, c.author_account_id, c.body_text, c.status, c.created_at, c.updated_at,
                   p.title AS post_title,
                   b.board_key, b.title AS board_title,
                   a.display_name AS author_display_name,
                   author_nickname.nickname AS author_nickname,
                   a.status AS author_account_status
            FROM sr_community_comments c
            INNER JOIN sr_community_posts p ON p.id = c.post_id
            INNER JOIN sr_community_boards b ON b.id = p.board_id
            LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
            LEFT JOIN sr_community_member_nicknames author_nickname ON author_nickname.account_id = a.id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY c.id DESC';
    if ($useLimit) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($useLimit) {
        $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_admin_comment_by_id(PDO $pdo, int $commentId): ?array
{
    if ($commentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT c.id, c.post_id, c.author_account_id, c.body_text, c.status, c.created_at, c.updated_at,
                p.title AS post_title,
                b.board_key, b.title AS board_title,
                a.display_name AS author_display_name,
                author_nickname.nickname AS author_nickname
         FROM sr_community_comments c
         INNER JOIN sr_community_posts p ON p.id = c.post_id
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         LEFT JOIN sr_community_member_nicknames author_nickname ON author_nickname.account_id = a.id
         WHERE c.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $commentId]);
    $comment = $stmt->fetch();

    return is_array($comment) ? $comment : null;
}

function sr_community_update_comment_status(PDO $pdo, int $commentId, string $status): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_comments
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

function sr_community_update_comment_content(PDO $pdo, int $commentId, array $values): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_comments
         SET body_text = :body_text,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'body_text' => trim((string) $values['body_text']),
        'updated_at' => sr_now(),
        'id' => $commentId,
    ]);
}

function sr_community_account_can_edit_comment(array $comment, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) $comment['author_account_id'] === (int) $account['id']
        && (string) $comment['status'] === 'published';
}

function sr_community_account_can_delete_comment(array $comment, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) $comment['author_account_id'] === (int) $account['id']
        && (string) $comment['status'] === 'published';
}

function sr_community_account_can_write_board(PDO $pdo, array $board, array $account, bool $isAdminWriter = false): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1 || (string) ($board['status'] ?? '') !== 'enabled') {
        return false;
    }

    $policy = sr_community_effective_board_policy($pdo, $board, 'write_policy');
    if ($policy === 'member') {
        $minLevel = sr_community_board_min_level($pdo, (int) $board['id'], 'write_min_level');
        return !empty(sr_community_account_satisfies_access($pdo, $accountId, [
            'min_level' => $minLevel,
        ])['allowed']);
    }

    if ($policy === 'group') {
        $groupKeys = sr_community_board_group_keys($pdo, (int) $board['id'], 'write_group_keys');
        $minLevel = sr_community_board_min_level($pdo, (int) $board['id'], 'write_min_level');
        return !empty(sr_community_account_satisfies_access($pdo, $accountId, [
            'group_keys' => $groupKeys,
            'min_level' => $minLevel,
        ])['allowed']);
    }

    if ($policy === 'admin') {
        return $isAdminWriter;
    }

    return false;
}

function sr_community_board_group_keys(PDO $pdo, int $boardId, string $settingKey): array
{
    if ($boardId < 1 || !in_array($settingKey, ['read_group_keys', 'write_group_keys', 'comment_group_keys'], true)) {
        return [];
    }

    $board = sr_community_board_by_id($pdo, $boardId);
    if (!is_array($board)) {
        return [];
    }

    $value = trim(sr_community_effective_board_setting($pdo, $board, $settingKey, ''));
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    $rawKeys = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
    return sr_community_normalize_board_group_keys(is_array($rawKeys) ? $rawKeys : []);
}

function sr_community_board_own_group_keys(PDO $pdo, int $boardId, string $settingKey): array
{
    if ($boardId < 1 || !in_array($settingKey, ['read_group_keys', 'write_group_keys', 'comment_group_keys'], true)) {
        return [];
    }

    $value = trim((string) sr_community_board_setting_value($pdo, $boardId, $settingKey));
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    $rawKeys = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
    return sr_community_normalize_board_group_keys(is_array($rawKeys) ? $rawKeys : []);
}

function sr_community_normalize_board_group_keys(array $rawKeys): array
{
    $groupKeys = [];
    foreach ($rawKeys as $rawKey) {
        $groupKey = trim((string) $rawKey);
        if ($groupKey !== '' && sr_member_group_key_is_valid($groupKey)) {
            $groupKeys[] = $groupKey;
        }
    }

    return array_values(array_unique($groupKeys));
}

function sr_community_board_group_keys_from_input(string $value): array
{
    if (trim($value) === '') {
        return [];
    }

    $rawKeys = preg_split('/[\s,]+/', $value);
    return sr_community_normalize_board_group_keys(is_array($rawKeys) ? $rawKeys : []);
}

function sr_community_board_group_keys_from_input_value(mixed $value): array
{
    if (is_array($value)) {
        return sr_community_normalize_board_group_keys($value);
    }

    if (is_string($value)) {
        return sr_community_board_group_keys_from_input($value);
    }

    return [];
}

function sr_community_invalid_board_group_keys_from_input(string $value): array
{
    if (trim($value) === '') {
        return [];
    }

    $rawKeys = preg_split('/[\s,]+/', $value);
    if (!is_array($rawKeys)) {
        return [];
    }

    $invalidKeys = [];
    foreach ($rawKeys as $rawKey) {
        $groupKey = trim((string) $rawKey);
        if ($groupKey !== '' && !sr_member_group_key_is_valid($groupKey)) {
            $invalidKeys[] = $groupKey;
        }
    }

    return array_values(array_unique($invalidKeys));
}

function sr_community_invalid_board_group_keys_from_input_value(mixed $value): array
{
    if (is_array($value)) {
        $invalidKeys = [];
        foreach ($value as $rawKey) {
            if (is_array($rawKey)) {
                $invalidKeys[] = 'array';
                continue;
            }

            $groupKey = trim((string) $rawKey);
            if ($groupKey !== '' && !sr_member_group_key_is_valid($groupKey)) {
                $invalidKeys[] = $groupKey;
            }
        }

        return array_values(array_unique($invalidKeys));
    }

    if (is_string($value)) {
        return sr_community_invalid_board_group_keys_from_input($value);
    }

    return [];
}

function sr_community_board_group_keys_input_too_long(mixed $value, int $maxLength = 1000): bool
{
    if (is_array($value)) {
        $length = 0;
        foreach ($value as $rawKey) {
            if (is_array($rawKey)) {
                return true;
            }

            $length += strlen(trim((string) $rawKey)) + 1;
            if ($length > $maxLength) {
                return true;
            }
        }

        return false;
    }

    if (is_string($value)) {
        return strlen(trim($value)) > $maxLength;
    }

    return false;
}

function sr_community_board_group_keys_setting_value(array $groupKeys): string
{
    $normalizedKeys = sr_community_normalize_board_group_keys($groupKeys);
    $encoded = json_encode($normalizedKeys, JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '[]';
}

function sr_community_post_input_values(): array
{
    return [
        'title' => sr_post_string_without_truncation('title', 160),
        'body_text' => sr_post_string_without_truncation('body_text', 20000),
    ];
}

function sr_community_validate_post_input(array $values): array
{
    $errors = [];
    $title = $values['title'];
    $bodyText = $values['body_text'];

    if (!is_string($title)) {
        $errors[] = sr_t('community::action.error.post_title_too_long');
    } elseif (trim($title) === '') {
        $errors[] = sr_t('community::action.error.post_title_required');
    }

    if (!is_string($bodyText)) {
        $errors[] = sr_t('community::action.error.post_body_too_long');
    } elseif (trim($bodyText) === '') {
        $errors[] = sr_t('community::action.error.post_body_required');
    }

    return $errors;
}

function sr_community_create_post(PDO $pdo, int $boardId, int $authorAccountId, array $values): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_posts
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

function sr_community_post_rate_limited(PDO $pdo, int $accountId, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    $limit = min(100, max(1, (int) ($settings['post_create_limit'] ?? 10)));

    return sr_community_rate_limits_table_exists($pdo)
        && sr_rate_limit_count($pdo, 'community.post.account', (string) $accountId, $windowSeconds) >= $limit;
}

function sr_community_record_post_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if (!sr_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    sr_rate_limit_increment($pdo, 'community.post.account', (string) $accountId, $windowSeconds);
}

function sr_community_account_can_comment_post(PDO $pdo, array $post, array $account): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1 || (string) ($post['status'] ?? '') !== 'published' || (string) ($post['board_status'] ?? '') !== 'enabled') {
        return false;
    }

    $board = [
        'id' => (int) ($post['board_id'] ?? 0),
        'board_group_id' => (int) ($post['board_group_id'] ?? 0),
        'comment_policy' => (string) ($post['comment_policy'] ?? ''),
    ];
    $policy = sr_community_effective_board_policy($pdo, $board, 'comment_policy');
    if ($policy === 'member') {
        $minLevel = sr_community_board_min_level($pdo, (int) $post['board_id'], 'comment_min_level');
        return !empty(sr_community_account_satisfies_access($pdo, $accountId, [
            'min_level' => $minLevel,
        ])['allowed']);
    }

    if ($policy === 'group') {
        $groupKeys = sr_community_board_group_keys($pdo, (int) $post['board_id'], 'comment_group_keys');
        $minLevel = sr_community_board_min_level($pdo, (int) $post['board_id'], 'comment_min_level');
        return !empty(sr_community_account_satisfies_access($pdo, $accountId, [
            'group_keys' => $groupKeys,
            'min_level' => $minLevel,
        ])['allowed']);
    }

    return false;
}

function sr_community_comment_input_values(): array
{
    return [
        'body_text' => sr_post_string_without_truncation('body_text', 5000),
    ];
}

function sr_community_validate_comment_input(array $values): array
{
    $bodyText = $values['body_text'];
    if (!is_string($bodyText)) {
        return [sr_t('community::action.error.comment_body_too_long')];
    }

    if (trim($bodyText) === '') {
        return [sr_t('community::action.error.comment_body_required')];
    }

    return [];
}

function sr_community_create_comment(PDO $pdo, int $postId, int $authorAccountId, array $values): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_comments
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
        'UPDATE sr_community_posts
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

function sr_community_comment_rate_limited(PDO $pdo, int $accountId, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['comment_create_window_seconds'] ?? 300)));
    $limit = min(300, max(1, (int) ($settings['comment_create_limit'] ?? 30)));

    return sr_community_rate_limits_table_exists($pdo)
        && sr_rate_limit_count($pdo, 'community.comment.account', (string) $accountId, $windowSeconds) >= $limit;
}

function sr_community_record_comment_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if (!sr_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['comment_create_window_seconds'] ?? 300)));
    sr_rate_limit_increment($pdo, 'community.comment.account', (string) $accountId, $windowSeconds);
}

function sr_community_rate_limits_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_rate_limits LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_public_author_label(PDO $pdo, int $accountId, bool $showIdentifier = false, ?array $config = null): string
{
    $summary = sr_community_public_account_summary($pdo, $accountId);
    if (!is_array($summary) || sr_community_nickname_status_blocks_identity((string) $summary['status'])) {
        return sr_t('member::account.withdrawn_display_name');
    }

    static $communitySettingsCache = [];
    $settingsCacheKey = (string) spl_object_id($pdo);
    if (!isset($communitySettingsCache[$settingsCacheKey])) {
        $communitySettingsCache[$settingsCacheKey] = sr_community_settings($pdo);
    }

    $displayName = sr_community_public_display_name($summary, $communitySettingsCache[$settingsCacheKey]);
    $label = $displayName !== '' ? $displayName : sr_t('community::report.account.member');
    $runtimeConfig = is_array($config) ? $config : sr_runtime_config();

    return sr_community_member_label_with_identifier($label, $runtimeConfig, $accountId, $showIdentifier);
}

function sr_community_plain_text_html(string $value): string
{
    return nl2br(sr_e($value), false);
}
