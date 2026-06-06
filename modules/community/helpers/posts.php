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

function sr_community_board_posts(PDO $pdo, int $boardId, int $limit = 20, int $offset = 0, string $keyword = '', int $categoryId = 0): array
{
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $keyword = trim($keyword);
    $categorySupported = sr_community_categories_supported($pdo);
    $where = "p.board_id = :board_id AND p.status = 'published'";
    $params = ['board_id' => $boardId];
    if ($keyword !== '') {
        $where .= " AND (p.title LIKE :title_keyword ESCAPE '\\\\' OR p.body_text LIKE :body_keyword ESCAPE '\\\\')";
        $params['title_keyword'] = sr_community_like_pattern($keyword);
        $params['body_keyword'] = sr_community_like_pattern($keyword);
    }
    if ($categorySupported && $categoryId > 0) {
        $where .= ' AND p.category_id = :category_id';
        $params['category_id'] = $categoryId;
    }

    $categorySelectSql = $categorySupported
        ? 'p.category_id, cat.category_key, cat.title AS category_title, cat.status AS category_status'
        : 'NULL AS category_id, NULL AS category_key, NULL AS category_title, NULL AS category_status';
    $categoryJoinSql = $categorySupported ? 'LEFT JOIN sr_community_categories cat ON cat.id = p.category_id' : '';
    $authorSnapshotSelectSql = sr_community_author_public_name_snapshot_select($pdo, 'sr_community_posts', 'p');
    $stmt = $pdo->prepare(
        'SELECT p.id, p.board_id, ' . $categorySelectSql . ', p.author_account_id, ' . $authorSnapshotSelectSql . ', author.status AS author_account_status, p.title, p.body_text, p.body_format, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                (SELECT COUNT(*) FROM sr_community_comments c WHERE c.post_id = p.id AND c.status = \'published\') AS published_comment_count,
                (SELECT COUNT(*) FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\') AS active_attachment_count
         FROM sr_community_posts p
         LEFT JOIN sr_member_accounts author ON author.id = p.author_account_id
         ' . $categoryJoinSql . '
         WHERE ' . $where . '
         ORDER BY p.id DESC
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, in_array($key, ['board_id', 'category_id'], true) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_board_post_count(PDO $pdo, int $boardId, string $keyword = '', int $categoryId = 0): int
{
    if ($boardId < 1) {
        return 0;
    }

    $keyword = trim($keyword);
    $categorySupported = sr_community_categories_supported($pdo);
    $where = "board_id = :board_id AND status = 'published'";
    $params = ['board_id' => $boardId];
    if ($keyword !== '') {
        $where .= " AND (title LIKE :title_keyword ESCAPE '\\\\' OR body_text LIKE :body_keyword ESCAPE '\\\\')";
        $params['title_keyword'] = sr_community_like_pattern($keyword);
        $params['body_keyword'] = sr_community_like_pattern($keyword);
    }
    if ($categorySupported && $categoryId > 0) {
        $where .= ' AND category_id = :category_id';
        $params['category_id'] = $categoryId;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_community_posts
         WHERE ' . $where
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, in_array($key, ['board_id', 'category_id'], true) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function sr_community_public_posts(PDO $pdo, int $boardId, int $limit = 20, int $offset = 0, string $keyword = '', int $categoryId = 0): array
{
    return sr_community_board_posts($pdo, $boardId, $limit, $offset, $keyword, $categoryId);
}

function sr_community_public_post_count(PDO $pdo, int $boardId, string $keyword = '', int $categoryId = 0): int
{
    return sr_community_board_post_count($pdo, $boardId, $keyword, $categoryId);
}

function sr_community_like_pattern(string $keyword): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($keyword)) . '%';
}

function sr_community_author_public_name_snapshot_column_exists(PDO $pdo, string $tableName): bool
{
    static $exists = [];
    if (!in_array($tableName, ['sr_community_posts', 'sr_community_comments'], true)) {
        return false;
    }
    $cacheKey = (string) spl_object_id($pdo) . ':' . $tableName;
    if (array_key_exists($cacheKey, $exists)) {
        return $exists[$cacheKey];
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
            'table_name' => $tableName,
            'column_name' => 'author_public_name_snapshot',
        ]);
        $exists[$cacheKey] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        $exists[$cacheKey] = false;
    }

    return $exists[$cacheKey];
}

function sr_community_author_public_name_snapshot_select(PDO $pdo, string $tableName, string $alias): string
{
    if (sr_community_author_public_name_snapshot_column_exists($pdo, $tableName)) {
        return $alias . '.author_public_name_snapshot';
    }

    return "'' AS author_public_name_snapshot";
}

function sr_community_author_public_name_snapshot(PDO $pdo, int $accountId): string
{
    $name = trim(sr_member_public_name_for_account_id($pdo, $accountId, sr_t('community::report.account.member')));

    return function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120);
}

function sr_community_author_display_name_from_row(array $row, ?array $settings = null, ?PDO $pdo = null): string
{
    if (sr_community_nickname_status_blocks_identity((string) ($row['author_account_status'] ?? ''))) {
        return sr_t('member::account.withdrawn_display_name');
    }

    $snapshot = trim((string) ($row['author_public_name_snapshot'] ?? ''));
    if ($snapshot !== '') {
        return $snapshot;
    }

    $label = sr_community_public_display_name([
        'display_name' => is_string($row['author_display_name'] ?? null) ? $row['author_display_name'] : '',
        'community_nickname' => is_string($row['author_nickname'] ?? null) ? $row['author_nickname'] : '',
        'status' => is_string($row['author_account_status'] ?? null) ? $row['author_account_status'] : '',
    ], $settings);
    if ($label !== sr_t('community::report.account.member') || !$pdo instanceof PDO) {
        return $label;
    }

    return sr_community_public_author_label($pdo, (int) ($row['author_account_id'] ?? 0));
}

function sr_community_author_label_from_row(array $row, array $config, bool $showIdentifier = false, ?array $settings = null, ?PDO $pdo = null): string
{
    $label = sr_community_author_display_name_from_row($row, $settings, $pdo);
    if ($label === sr_t('member::account.withdrawn_display_name')) {
        return $label;
    }

    return sr_community_member_label_with_identifier($label, $config, (int) ($row['author_account_id'] ?? 0), $showIdentifier);
}

function sr_community_public_post(PDO $pdo, int $postId): ?array
{
    if ($postId < 1) {
        return null;
    }

    $categorySupported = sr_community_categories_supported($pdo);
    $categorySelectSql = $categorySupported
        ? 'p.category_id, cat.category_key, cat.title AS category_title, cat.status AS category_status'
        : 'NULL AS category_id, NULL AS category_key, NULL AS category_title, NULL AS category_status';
    $categoryJoinSql = $categorySupported ? 'LEFT JOIN sr_community_categories cat ON cat.id = p.category_id' : '';
    $authorSnapshotSelectSql = sr_community_author_public_name_snapshot_select($pdo, 'sr_community_posts', 'p');
    $stmt = $pdo->prepare(
        "SELECT p.id, p.board_id, " . $categorySelectSql . ", p.author_account_id, " . $authorSnapshotSelectSql . ", author.status AS author_account_status, p.title, p.body_text, p.body_format, p.seo_title, p.seo_description, p.og_title, p.og_description, p.og_image_attachment_id, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                b.board_group_id, b.board_key, b.title AS board_title, b.description AS board_description, b.status AS board_status, b.read_policy, b.comment_policy
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         LEFT JOIN sr_member_accounts author ON author.id = p.author_account_id
         " . $categoryJoinSql . "
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

    $categorySupported = sr_community_categories_supported($pdo);
    $categorySelectSql = $categorySupported
        ? 'p.category_id, cat.category_key, cat.title AS category_title, cat.status AS category_status'
        : 'NULL AS category_id, NULL AS category_key, NULL AS category_title, NULL AS category_status';
    $categoryJoinSql = $categorySupported ? 'LEFT JOIN sr_community_categories cat ON cat.id = p.category_id' : '';
    $authorSnapshotSelectSql = sr_community_author_public_name_snapshot_select($pdo, 'sr_community_posts', 'p');
    $stmt = $pdo->prepare(
        "SELECT p.id, p.board_id, " . $categorySelectSql . ", p.author_account_id, " . $authorSnapshotSelectSql . ", author.status AS author_account_status, p.title, p.body_text, p.body_format, p.seo_title, p.seo_description, p.og_title, p.og_description, p.og_image_attachment_id, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                b.board_group_id, b.board_key, b.title AS board_title, b.description AS board_description, b.status AS board_status, b.read_policy, b.comment_policy
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         LEFT JOIN sr_member_accounts author ON author.id = p.author_account_id
         " . $categoryJoinSql . "
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
    $authorSnapshotSelectSql = sr_community_author_public_name_snapshot_select($pdo, 'sr_community_comments', 'c');
    $secretSelectSql = sr_community_comment_secret_column_exists($pdo) ? 'c.is_secret,' : '0 AS is_secret,';
    $stmt = $pdo->prepare(
        "SELECT c.id, c.post_id, c.author_account_id, " . $authorSnapshotSelectSql . ", author.status AS author_account_status, c.body_text, " . $secretSelectSql . " c.status, c.created_at, c.updated_at
         FROM sr_community_comments c
         LEFT JOIN sr_member_accounts author ON author.id = c.author_account_id
         WHERE c.post_id = :post_id
           AND c.status = 'published'
         ORDER BY c.id ASC
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

function sr_community_comment_secret_column_exists(PDO $pdo): bool
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
            'table_name' => 'sr_community_comments',
            'column_name' => 'is_secret',
        ]);
        $existsByConnection[$key] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
}

function sr_community_account_can_view_comment_body(array $comment, array $post, ?array $account): bool
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
            || $accountId === (int) ($post['author_account_id'] ?? 0));
}

function sr_community_account_can_hide_comment(PDO $pdo, array $comment, array $post, ?array $account): bool
{
    if (!is_array($account) || (int) ($account['id'] ?? 0) < 1 || (string) ($comment['status'] ?? '') !== 'published') {
        return false;
    }

    $accountId = (int) $account['id'];

    return (function_exists('sr_admin_has_permission')
            && (sr_admin_has_permission($pdo, $accountId, '/admin/community/comments', 'edit')
                || sr_admin_has_permission($pdo, $accountId, '/admin/community/comments', 'delete')
                || sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'edit')
                || sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'delete')))
        || sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), $accountId, 'delete_post');
}

function sr_community_relative_time_label(string $dateTime): string
{
    $timestamp = strtotime($dateTime);
    if ($timestamp === false) {
        return $dateTime;
    }

    $diff = max(0, time() - $timestamp);
    if ($diff < 60) {
        return '방금 전';
    }
    if ($diff < 3600) {
        return (string) floor($diff / 60) . '분 전';
    }
    if ($diff < 86400) {
        return (string) floor($diff / 3600) . '시간 전';
    }
    if ($diff < 2592000) {
        return (string) floor($diff / 86400) . '일 전';
    }
    if ($diff < 31536000) {
        return (string) floor($diff / 2592000) . '개월 전';
    }

    return (string) floor($diff / 31536000) . '년 전';
}

function sr_community_post_statuses(): array
{
    return ['published', 'hidden', 'deleted', 'pending'];
}

function sr_community_admin_post_query_parts(array $filters, bool $categorySupported = true): array
{
    $where = [];
    $params = [];

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('p.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if ((int) ($filters['board_id'] ?? 0) > 0) {
        $where[] = 'p.board_id = :board_id';
        $params['board_id'] = (int) $filters['board_id'];
    }

    if ($categorySupported && (int) ($filters['category_id'] ?? 0) > 0) {
        $where[] = 'p.category_id = :category_id';
        $params['category_id'] = (int) $filters['category_id'];
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
    $queryParts = sr_community_admin_post_query_parts($filters, sr_community_categories_supported($pdo));
    $sql = 'SELECT COUNT(*) AS count_value
            FROM sr_community_posts p
            INNER JOIN sr_community_boards b ON b.id = p.board_id
            LEFT JOIN sr_member_accounts a ON a.id = p.author_account_id
            LEFT JOIN sr_member_nicknames author_nickname ON author_nickname.account_id = a.id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_admin_post_sort_options(): array
{
    return [
        'board' => ['columns' => ['b.title', 'p.id']],
        'title' => ['columns' => ['p.title', 'p.id']],
        'author' => ['columns' => ["COALESCE(author_nickname.nickname, a.display_name, '')", 'p.id']],
        'status' => ['columns' => ['p.status', 'p.id']],
        'published_comment_count' => ['columns' => ['published_comment_count', 'p.id']],
        'active_attachment_count' => ['columns' => ['active_attachment_count', 'p.id']],
        'created_at' => ['columns' => ['p.created_at', 'p.id']],
    ];
}

function sr_community_admin_post_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_community_admin_posts(PDO $pdo, int $limit = 100, array $filters = [], int $offset = 0, array $sort = []): array
{
    $useLimit = $limit > 0;
    if ($useLimit) {
        $limit = max(1, min(1000, $limit));
    }
    $categorySupported = sr_community_categories_supported($pdo);
    $queryParts = sr_community_admin_post_query_parts($filters, $categorySupported);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $categorySelectSql = $categorySupported
        ? 'p.category_id, cat.category_key, cat.title AS category_title, cat.status AS category_status'
        : 'NULL AS category_id, NULL AS category_key, NULL AS category_title, NULL AS category_status';
    $categoryJoinSql = $categorySupported ? 'LEFT JOIN sr_community_categories cat ON cat.id = p.category_id' : '';
    $authorSnapshotSelectSql = sr_community_author_public_name_snapshot_select($pdo, 'sr_community_posts', 'p');
    $sql = 'SELECT p.id, p.board_id, ' . $categorySelectSql . ', p.author_account_id, ' . $authorSnapshotSelectSql . ', p.title, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                   b.board_key, b.title AS board_title,
                   a.display_name AS author_display_name,
                   author_nickname.nickname AS author_nickname,
                   a.status AS author_account_status,
                   (SELECT COUNT(*) FROM sr_community_comments c WHERE c.post_id = p.id AND c.status = \'published\') AS published_comment_count,
                   (SELECT COUNT(*) FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\') AS active_attachment_count
            FROM sr_community_posts p
            INNER JOIN sr_community_boards b ON b.id = p.board_id
            ' . $categoryJoinSql . '
            LEFT JOIN sr_member_accounts a ON a.id = p.author_account_id
            LEFT JOIN sr_member_nicknames author_nickname ON author_nickname.account_id = a.id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= sr_admin_sort_order_sql(sr_community_admin_post_sort_options(), $sort, sr_community_admin_post_default_sort());
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

    $categorySupported = sr_community_categories_supported($pdo);
    $categorySelectSql = $categorySupported
        ? 'p.category_id, cat.category_key, cat.title AS category_title, cat.status AS category_status'
        : 'NULL AS category_id, NULL AS category_key, NULL AS category_title, NULL AS category_status';
    $categoryJoinSql = $categorySupported ? 'LEFT JOIN sr_community_categories cat ON cat.id = p.category_id' : '';
    $authorSnapshotSelectSql = sr_community_author_public_name_snapshot_select($pdo, 'sr_community_posts', 'p');
    $stmt = $pdo->prepare(
        'SELECT p.id, p.board_id, ' . $categorySelectSql . ', p.author_account_id, ' . $authorSnapshotSelectSql . ', p.title, p.body_text, p.body_format, p.seo_title, p.seo_description, p.og_title, p.og_description, p.og_image_attachment_id, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                b.board_key, b.title AS board_title,
                a.display_name AS author_display_name,
                author_nickname.nickname AS author_nickname,
                a.status AS author_account_status
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         ' . $categoryJoinSql . '
         LEFT JOIN sr_member_accounts a ON a.id = p.author_account_id
         LEFT JOIN sr_member_nicknames author_nickname ON author_nickname.account_id = a.id
         WHERE p.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $postId]);
    $post = $stmt->fetch();

    return is_array($post) ? $post : null;
}

function sr_community_link_card_search_post_targets(PDO $pdo, string $keyword, int $limit = 10): array
{
    $keyword = trim(preg_replace('/\s+/', ' ', $keyword) ?? '');
    $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 120) : substr($keyword, 0, 120);
    $limit = max(1, min(20, $limit));
    $where = $keyword === '' ? '1 = 1' : "(p.id = :id OR p.title LIKE :keyword_post_title ESCAPE '\\\\' OR b.title LIKE :keyword_board_title ESCAPE '\\\\' OR b.board_key LIKE :keyword_board_key ESCAPE '\\\\')";
    $params = [];
    if ($keyword !== '') {
        $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
        $params = [
            'id' => preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0,
            'keyword_post_title' => $keywordLike,
            'keyword_board_title' => $keywordLike,
            'keyword_board_key' => $keywordLike,
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.body_text, p.status, p.updated_at,
                b.board_key, b.title AS board_title
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         WHERE p.status = \'published\'
           AND b.status = \'enabled\'
           AND b.read_policy = \'public\'
           AND ' . $where . '
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT ' . $limit
    );
    $stmt->execute($params);

    return array_map(static function (array $row): array {
        $postId = (string) (int) ($row['id'] ?? 0);
        $summary = trim(strip_tags((string) ($row['body_text'] ?? '')));
        $summary = preg_replace('/\s+/', ' ', $summary) ?? '';
        $summary = function_exists('mb_substr') ? mb_substr($summary, 0, 120) : substr($summary, 0, 120);

        return [
            'module' => 'community',
            'entity_type' => 'post',
            'entity_id' => $postId,
            'title' => (string) ($row['title'] ?? ''),
            'summary' => $summary,
            'url' => '/community/post?id=' . rawurlencode($postId),
            'status' => (string) ($row['status'] ?? ''),
            'meta' => '게시글 #' . $postId . ' / 게시판: ' . (string) ($row['board_title'] ?? '') . ' (' . (string) ($row['board_key'] ?? '') . ')',
        ];
    }, $stmt->fetchAll());
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
    if ($status === 'deleted') {
        sr_community_cleanup_body_files_for_deleted_posts($pdo, [$postId]);
    }
}

function sr_community_update_post_og_image(PDO $pdo, int $postId, ?int $attachmentId): void
{
    if ($postId < 1) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_posts
         SET og_image_attachment_id = :og_image_attachment_id,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'og_image_attachment_id' => is_int($attachmentId) && $attachmentId > 0 ? $attachmentId : 0,
        'updated_at' => sr_now(),
        'id' => $postId,
    ]);
}

function sr_community_update_post_content(PDO $pdo, int $postId, array $values, int $accountId = 0): void
{
    if ($pdo->inTransaction()) {
        throw new RuntimeException('게시글 본문 이미지를 포함한 수정은 외부 트랜잭션에서 처리할 수 없습니다.');
    }

    $createdBodyFiles = [];
    $finalizedTmpFiles = [];
    $pdo->beginTransaction();

    try {
        $bodyFormat = in_array((string) ($values['body_format'] ?? 'plain'), ['plain', 'html'], true)
            ? (string) $values['body_format']
            : 'plain';
        $bodyText = trim((string) $values['body_text']);
        if (sr_link_card_token_rejection_errors($bodyText) !== []) {
            throw new InvalidArgumentException('링크 카드 토큰은 게시글 본문에 저장할 수 없습니다.');
        }

        if ($bodyFormat === 'html') {
            $bodyText = sr_community_finalize_body_files($pdo, $postId, $bodyText, $accountId, false, $createdBodyFiles, $finalizedTmpFiles);
        }
        $categorySupported = sr_community_categories_supported($pdo);
        $categorySetSql = $categorySupported ? 'category_id = :category_id,' : '';
        $stmt = $pdo->prepare(
            'UPDATE sr_community_posts
             SET ' . $categorySetSql . '
                 title = :title,
                 body_text = :body_text,
                 body_format = :body_format,
                 seo_title = :seo_title,
                 seo_description = :seo_description,
                 og_title = :og_title,
                 og_description = :og_description,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $params = [
            'title' => trim((string) $values['title']),
            'body_text' => $bodyText,
            'body_format' => $bodyFormat,
            'seo_title' => sr_community_seo_text((string) ($values['seo_title'] ?? ''), 160),
            'seo_description' => sr_community_seo_text((string) ($values['seo_description'] ?? ''), 255),
            'og_title' => sr_community_seo_text((string) ($values['og_title'] ?? ''), 160),
            'og_description' => sr_community_seo_text((string) ($values['og_description'] ?? ''), 255),
            'updated_at' => sr_now(),
            'id' => $postId,
        ];
        if ($categorySupported) {
            $params['category_id'] = (int) ($values['category_id'] ?? 0) > 0 ? (int) $values['category_id'] : null;
        }
        $stmt->execute($params);
        sr_link_card_clear_legacy_refs($pdo, 'sr_community_link_refs', 'post_id', $postId);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_community_cleanup_storage_file_refs($pdo, $createdBodyFiles, 'body_file_update_rollback', $postId, '게시글 수정 실패 후 본문 이미지 저장소 정리에 실패했습니다.');
        throw $exception;
    }

    if ($bodyFormat === 'html') {
        sr_community_cleanup_storage_file_refs($pdo, $finalizedTmpFiles, 'body_file_tmp_finalized', $postId, '게시글 수정 후 임시 본문 이미지 정리에 실패했습니다.');
        sr_community_cleanup_unreferenced_body_files($pdo, $postId, $bodyText);
    } else {
        sr_community_cleanup_unreferenced_body_files($pdo, $postId, '');
    }
}

function sr_community_account_can_edit_post(array $post, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) $post['author_account_id'] === (int) $account['id']
        && (string) $post['status'] === 'published';
}

function sr_community_account_can_delete_post(array $post, array $account, ?PDO $pdo = null): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1 || (string) ($post['status'] ?? '') !== 'published') {
        return false;
    }

    if ((int) ($post['author_account_id'] ?? 0) === $accountId) {
        return true;
    }

    if (!$pdo instanceof PDO) {
        return false;
    }

    if (function_exists('sr_admin_has_permission') && sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'delete')) {
        return true;
    }

    return sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), $accountId, 'delete_post');
}

function sr_community_comment_statuses(): array
{
    return ['published', 'hidden', 'deleted'];
}

function sr_community_admin_comment_query_parts(array $filters): array
{
    $where = [];
    $params = [];

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('c.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
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
            LEFT JOIN sr_member_nicknames author_nickname ON author_nickname.account_id = a.id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_admin_comment_sort_options(): array
{
    return [
        'post' => ['columns' => ['p.title', 'c.id']],
        'author' => ['columns' => ["COALESCE(author_nickname.nickname, a.display_name, '')", 'c.id']],
        'body' => ['columns' => ['c.body_text', 'c.id']],
        'status' => ['columns' => ['c.status', 'c.id']],
        'created_at' => ['columns' => ['c.created_at', 'c.id']],
    ];
}

function sr_community_admin_comment_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_community_admin_comments(PDO $pdo, int $limit = 100, array $filters = [], int $offset = 0, array $sort = []): array
{
    $useLimit = $limit > 0;
    if ($useLimit) {
        $limit = max(1, min(1000, $limit));
    }
    $queryParts = sr_community_admin_comment_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $authorSnapshotSelectSql = sr_community_author_public_name_snapshot_select($pdo, 'sr_community_comments', 'c');
    $secretSelectSql = sr_community_comment_secret_column_exists($pdo) ? 'c.is_secret,' : '0 AS is_secret,';
    $sql = 'SELECT c.id, c.post_id, c.author_account_id, ' . $authorSnapshotSelectSql . ', c.body_text, c.status, c.created_at, c.updated_at,
                   ' . $secretSelectSql . '
                   p.title AS post_title,
                   b.board_key, b.title AS board_title,
                   a.display_name AS author_display_name,
                   author_nickname.nickname AS author_nickname,
                   a.status AS author_account_status
            FROM sr_community_comments c
            INNER JOIN sr_community_posts p ON p.id = c.post_id
            INNER JOIN sr_community_boards b ON b.id = p.board_id
            LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
            LEFT JOIN sr_member_nicknames author_nickname ON author_nickname.account_id = a.id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= sr_admin_sort_order_sql(sr_community_admin_comment_sort_options(), $sort, sr_community_admin_comment_default_sort());
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

    $authorSnapshotSelectSql = sr_community_author_public_name_snapshot_select($pdo, 'sr_community_comments', 'c');
    $secretSelectSql = sr_community_comment_secret_column_exists($pdo) ? 'c.is_secret,' : '0 AS is_secret,';
    $stmt = $pdo->prepare(
        'SELECT c.id, c.post_id, c.author_account_id, ' . $authorSnapshotSelectSql . ', c.body_text, ' . $secretSelectSql . ' c.status, c.created_at, c.updated_at,
                p.title AS post_title,
                b.board_key, b.title AS board_title,
                a.display_name AS author_display_name,
                author_nickname.nickname AS author_nickname,
                a.status AS author_account_status
         FROM sr_community_comments c
         INNER JOIN sr_community_posts p ON p.id = c.post_id
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         LEFT JOIN sr_member_nicknames author_nickname ON author_nickname.account_id = a.id
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
    $secretSql = sr_community_comment_secret_column_exists($pdo) ? 'is_secret = :is_secret,' : '';
    $stmt = $pdo->prepare(
        'UPDATE sr_community_comments
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

function sr_community_account_can_edit_comment(array $comment, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) $comment['author_account_id'] === (int) $account['id']
        && (string) $comment['status'] === 'published';
}

function sr_community_account_can_delete_comment(array $comment, array $account, ?PDO $pdo = null, ?array $post = null): bool
{
    if ((int) ($account['id'] ?? 0) > 0
        && (int) $comment['author_account_id'] === (int) $account['id']
        && (string) $comment['status'] === 'published') {
        return true;
    }
    if (!$pdo instanceof PDO || !is_array($post)) {
        return false;
    }

    return sr_community_account_can_hide_comment($pdo, $comment, $post, $account);
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

function sr_community_post_input_values(?PDO $pdo = null, ?array $board = null, ?array $settings = null): array
{
    $bodyFormat = 'plain';
    if ($pdo instanceof PDO && sr_post_string('body_format', 20) === 'html' && sr_community_html_post_body_enabled($pdo, $board, $settings)) {
        $bodyFormat = 'html';
    }

    $bodyText = sr_post_string_without_truncation('body_text', 20000);
    if ($bodyFormat === 'html' && is_string($bodyText)) {
        $bodyText = sr_community_sanitize_post_html($bodyText);
    }

    return [
        'title' => sr_post_string_without_truncation('title', 160),
        'category_id' => preg_match('/\A[1-9][0-9]*\z/', sr_post_string('category_id', 20)) === 1 ? (int) sr_post_string('category_id', 20) : 0,
        'body_text' => $bodyText,
        'body_format' => $bodyFormat,
        'seo_title' => sr_community_seo_text(sr_post_string('seo_title', 160), 160),
        'seo_description' => sr_community_seo_text(sr_post_string('seo_description', 255), 255),
        'og_title' => sr_community_seo_text(sr_post_string('og_title', 160), 160),
        'og_description' => sr_community_seo_text(sr_post_string('og_description', 255), 255),
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
    } elseif (sr_community_body_text_is_empty($bodyText, (string) ($values['body_format'] ?? 'plain'))) {
        $errors[] = sr_t('community::action.error.post_body_required');
    }
    if (is_string($bodyText)) {
        $errors = array_merge($errors, sr_link_card_token_rejection_errors($bodyText));
    }

    return $errors;
}

function sr_community_create_post(PDO $pdo, int $boardId, int $authorAccountId, array $values): int
{
    if ($pdo->inTransaction()) {
        throw new RuntimeException('게시글 본문 이미지를 포함한 작성은 외부 트랜잭션에서 처리할 수 없습니다.');
    }

    $bodyFormat = in_array((string) ($values['body_format'] ?? 'plain'), ['plain', 'html'], true)
        ? (string) $values['body_format']
        : 'plain';
    $bodyText = trim((string) ($values['body_text'] ?? ''));
    if (sr_link_card_token_rejection_errors($bodyText) !== []) {
        throw new InvalidArgumentException('링크 카드 토큰은 게시글 본문에 저장할 수 없습니다.');
    }

    $now = sr_now();
    $categorySupported = sr_community_categories_supported($pdo);
    $categoryColumnSql = $categorySupported ? 'category_id, ' : '';
    $categoryValueSql = $categorySupported ? ':category_id, ' : '';
    $authorSnapshotColumnSql = sr_community_author_public_name_snapshot_column_exists($pdo, 'sr_community_posts') ? 'author_public_name_snapshot, ' : '';
    $authorSnapshotValueSql = $authorSnapshotColumnSql !== '' ? ':author_public_name_snapshot, ' : '';
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (board_id, ' . $categoryColumnSql . 'author_account_id, ' . $authorSnapshotColumnSql . 'title, body_text, body_format, seo_title, seo_description, og_title, og_description, status, view_count, last_commented_at, created_at, updated_at)
         VALUES
            (:board_id, ' . $categoryValueSql . ':author_account_id, ' . $authorSnapshotValueSql . ':title, :body_text, :body_format, :seo_title, :seo_description, :og_title, :og_description, :status, 0, NULL, :created_at, :updated_at)'
    );
    $params = [
        'board_id' => $boardId,
        'author_account_id' => $authorAccountId,
        'title' => trim((string) $values['title']),
        'body_text' => $bodyText,
        'body_format' => $bodyFormat,
        'seo_title' => sr_community_seo_text((string) ($values['seo_title'] ?? ''), 160),
        'seo_description' => sr_community_seo_text((string) ($values['seo_description'] ?? ''), 255),
        'og_title' => sr_community_seo_text((string) ($values['og_title'] ?? ''), 160),
        'og_description' => sr_community_seo_text((string) ($values['og_description'] ?? ''), 255),
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if ($categorySupported) {
        $params['category_id'] = (int) ($values['category_id'] ?? 0) > 0 ? (int) $values['category_id'] : null;
    }
    if ($authorSnapshotColumnSql !== '') {
        $params['author_public_name_snapshot'] = sr_community_author_public_name_snapshot($pdo, $authorAccountId);
    }
    $pdo->beginTransaction();

    $createdBodyFiles = [];
    $finalizedTmpFiles = [];
    try {
        $stmt->execute($params);
        $postId = (int) $pdo->lastInsertId();
        if ($bodyFormat === 'html') {
            $finalBodyText = sr_community_finalize_body_files($pdo, $postId, $bodyText, $authorAccountId, true, $createdBodyFiles, $finalizedTmpFiles);
            if ($finalBodyText !== $bodyText) {
                $pdo->prepare('UPDATE sr_community_posts SET body_text = :body_text, updated_at = :updated_at WHERE id = :id')->execute([
                    'body_text' => $finalBodyText,
                    'updated_at' => $now,
                    'id' => $postId,
                ]);
            }
        }
        sr_link_card_clear_legacy_refs($pdo, 'sr_community_link_refs', 'post_id', $postId);
        $pdo->commit();
        sr_community_cleanup_storage_file_refs($pdo, $finalizedTmpFiles, 'body_file_tmp_finalized', $postId, '게시글 작성 후 임시 본문 이미지 정리에 실패했습니다.');

        return $postId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_community_cleanup_storage_file_refs($pdo, $createdBodyFiles, 'body_file_create_rollback', isset($postId) ? (int) $postId : 0, '게시글 작성 실패 후 본문 이미지 저장소 정리에 실패했습니다.');
        throw $exception;
    }
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
        'is_secret' => sr_post_string('is_secret', 10) === '1' ? 1 : 0,
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
    $authorSnapshotColumnSql = sr_community_author_public_name_snapshot_column_exists($pdo, 'sr_community_comments') ? 'author_public_name_snapshot, ' : '';
    $authorSnapshotValueSql = $authorSnapshotColumnSql !== '' ? ':author_public_name_snapshot, ' : '';
    $secretColumnSql = sr_community_comment_secret_column_exists($pdo) ? 'is_secret, ' : '';
    $secretValueSql = $secretColumnSql !== '' ? ':is_secret, ' : '';
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_comments
            (post_id, author_account_id, ' . $authorSnapshotColumnSql . 'body_text, ' . $secretColumnSql . 'status, created_at, updated_at)
         VALUES
            (:post_id, :author_account_id, ' . $authorSnapshotValueSql . ':body_text, ' . $secretValueSql . ':status, :created_at, :updated_at)'
    );
    $params = [
        'post_id' => $postId,
        'author_account_id' => $authorAccountId,
        'body_text' => trim((string) $values['body_text']),
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if ($authorSnapshotColumnSql !== '') {
        $params['author_public_name_snapshot'] = sr_community_author_public_name_snapshot($pdo, $authorAccountId);
    }
    if ($secretColumnSql !== '') {
        $params['is_secret'] = (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0;
    }
    $stmt->execute($params);
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

    static $memberSettingsCache = [];
    $settingsCacheKey = (string) spl_object_id($pdo);
    if (!isset($memberSettingsCache[$settingsCacheKey])) {
        $memberSettingsCache[$settingsCacheKey] = sr_member_settings($pdo);
    }

    $displayName = sr_community_public_display_name($summary, $memberSettingsCache[$settingsCacheKey]);
    $label = $displayName !== '' ? $displayName : sr_t('community::report.account.member');
    $runtimeConfig = is_array($config) ? $config : sr_runtime_config();

    return sr_community_member_label_with_identifier($label, $runtimeConfig, $accountId, $showIdentifier);
}

function sr_community_plain_text_html(string $value): string
{
    return nl2br(sr_e($value), false);
}

function sr_community_post_body_html(array $post): string
{
    $bodyText = (string) ($post['body_text'] ?? '');
    if ((string) ($post['body_format'] ?? 'plain') === 'html') {
        $html = sr_community_sanitize_post_html($bodyText);
    } else {
        $html = sr_community_plain_text_html($bodyText);
    }

    return $html;
}

function sr_community_link_card_resolve_many(PDO $pdo, array $types): array
{
    $ids = [];
    foreach ($types['post'] ?? [] as $id) {
        if (preg_match('/\A[1-9][0-9]*\z/', (string) $id) === 1) {
            $ids[(int) $id] = true;
        }
    }
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.body_text, p.status, b.status AS board_status, b.read_policy
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         WHERE p.id IN (' . $placeholders . ')'
    );
    $stmt->execute(array_keys($ids));

    $resolved = [];
    foreach ($stmt->fetchAll() as $row) {
        $postId = (string) (int) ($row['id'] ?? 0);
        $isReadable = (string) ($row['status'] ?? '') === 'published'
            && (string) ($row['board_status'] ?? '') === 'enabled'
            && (string) ($row['read_policy'] ?? 'public') === 'public';
        $summary = trim(strip_tags((string) ($row['body_text'] ?? '')));
        $summary = preg_replace('/\s+/', ' ', $summary) ?? '';
        $summary = function_exists('mb_substr') ? mb_substr($summary, 0, 160) : substr($summary, 0, 160);
        $resolved[sr_community_link_card_ref_key($postId)] = [
            'module' => 'community',
            'entity_type' => 'post',
            'entity_id' => $postId,
            'title' => $isReadable ? (string) ($row['title'] ?? '') : '연결할 수 없는 게시글',
            'summary' => $isReadable ? $summary : '',
            'url' => $isReadable ? '/community/post?id=' . rawurlencode($postId) : '',
            'status' => (string) ($row['status'] ?? ''),
            'broken' => !$isReadable,
        ];
    }

    foreach (array_keys($ids) as $id) {
        $key = sr_community_link_card_ref_key((string) $id);
        if (!isset($resolved[$key])) {
            $resolved[$key] = sr_community_link_card_broken_result((string) $id);
        }
    }

    return $resolved;
}

function sr_community_link_card_broken_result(string $postId): array
{
    return [
        'module' => 'community',
        'entity_type' => 'post',
        'entity_id' => $postId,
        'title' => '연결할 수 없는 게시글',
        'summary' => '',
        'url' => '',
        'status' => 'broken',
        'broken' => true,
    ];
}

function sr_community_link_card_ref_key(string $postId): string
{
    return 'community:post:' . $postId;
}

function sr_community_admin_link_refs(PDO $pdo, bool $brokenOnly = false): array
{
    return [];
}

function sr_community_html_post_body_enabled(PDO $pdo, ?array $board = null, ?array $settings = null): bool
{
    if (!sr_module_enabled($pdo, 'ckeditor') || !is_file(SR_ROOT . '/modules/ckeditor/helpers.php')) {
        return false;
    }

    if (is_array($board)) {
        return sr_community_effective_post_editor($pdo, $board, $settings) === 'ckeditor';
    }

    $settings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_settings($pdo);
    return sr_editor_effective_key($pdo, (string) ($settings['post_editor'] ?? 'textarea')) === 'ckeditor';
}

function sr_community_body_text_is_empty(string $bodyText, string $bodyFormat): bool
{
    if ($bodyFormat !== 'html') {
        return trim($bodyText) === '';
    }

    $plainText = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $bodyText)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    return $plainText === '';
}

function sr_community_allowed_post_html_tags(): array
{
    return [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'blockquote' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href'],
        'h2' => [],
        'h3' => [],
        'img' => ['src', 'alt', 'width', 'height'],
    ];
}

function sr_community_sanitize_post_html(string $html): string
{
    if (!class_exists('DOMDocument')) {
        return sr_community_plain_text_html(strip_tags($html));
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="sr-community-html-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return '';
    }

    $root = null;
    foreach ($document->getElementsByTagName('div') as $div) {
        if ($div instanceof DOMElement && $div->getAttribute('id') === 'sr-community-html-root') {
            $root = $div;
            break;
        }
    }
    if (!$root instanceof DOMElement) {
        return '';
    }

    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= sr_community_sanitize_post_html_node($child);
    }

    return trim($output);
}

function sr_community_sanitize_post_html_node(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return sr_e($node->wholeText);
    }

    if (!$node instanceof DOMElement) {
        return '';
    }

    $tagName = strtolower($node->tagName);
    if (in_array($tagName, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
        return '';
    }

    $allowedTags = sr_community_allowed_post_html_tags();
    $children = '';
    foreach ($node->childNodes as $child) {
        $children .= sr_community_sanitize_post_html_node($child);
    }

    if (!isset($allowedTags[$tagName])) {
        return $children;
    }

    if ($tagName === 'br') {
        return '<br>';
    }

    $attributes = sr_community_sanitize_post_html_attributes($node, $tagName, $allowedTags[$tagName]);
    if ($tagName === 'img') {
        return $attributes === '' ? '' : '<img' . $attributes . '>';
    }

    return '<' . $tagName . $attributes . '>' . $children . '</' . $tagName . '>';
}

function sr_community_sanitize_post_html_attributes(DOMElement $node, string $tagName, array $allowedAttributes): string
{
    $attributes = '';
    foreach ($allowedAttributes as $attributeName) {
        if (!$node->hasAttribute($attributeName)) {
            continue;
        }

        $value = trim($node->getAttribute($attributeName));
        if ($attributeName === 'href' || $attributeName === 'src') {
            if (!sr_is_safe_relative_url($value) && !sr_is_http_url($value)) {
                continue;
            }
            if ($attributeName === 'src' && sr_is_http_url($value) && strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
                continue;
            }
        } elseif ($attributeName === 'width' || $attributeName === 'height') {
            if (preg_match('/\A[1-9][0-9]{0,3}\z/', $value) !== 1) {
                continue;
            }
        } elseif ($attributeName === 'alt') {
            $value = function_exists('mb_substr') ? mb_substr($value, 0, 160) : substr($value, 0, 160);
        }

        $attributes .= ' ' . $attributeName . '="' . sr_e($value) . '"';
    }

    if ($tagName === 'a' && $attributes !== '') {
        $attributes .= ' rel="nofollow noopener noreferrer"';
    }

    return $attributes;
}
