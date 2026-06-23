<?php

declare(strict_types=1);

function sr_community_board_comment_body_min_length(PDO $pdo, array $board): int
{
    return sr_community_effective_board_int_setting($pdo, $board, 'comment_body_min_length', 0, 0, 5000);
}

function sr_community_board_comment_body_max_length(PDO $pdo, array $board): int
{
    return sr_community_effective_board_int_setting($pdo, $board, 'comment_body_max_length', 0, 0, 5000);
}

function sr_community_validate_comment_body_length(PDO $pdo, array $board, array $values): array
{
    $bodyText = $values['body_text'] ?? '';
    if (!is_string($bodyText)) {
        return [];
    }

    $length = sr_community_body_plain_length($bodyText);
    $minLength = sr_community_board_comment_body_min_length($pdo, $board);
    $maxLength = sr_community_board_comment_body_max_length($pdo, $board);
    $errors = [];
    if ($minLength > 0 && $length < $minLength) {
        $errors[] = '댓글 본문은 최소 ' . number_format($minLength) . '자 이상 입력해 주세요.';
    }
    if ($maxLength > 0 && $length > $maxLength) {
        $errors[] = '댓글 본문은 최대 ' . number_format($maxLength) . '자까지 입력할 수 있습니다.';
    }

    return $errors;
}

function sr_community_post_published_comment_count(PDO $pdo, int $postId): int
{
    if ($postId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sr_community_comments WHERE post_id = :post_id AND status = 'published'");
    $stmt->execute(['post_id' => $postId]);

    return (int) $stmt->fetchColumn();
}

function sr_community_post_comments(PDO $pdo, int $postId, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $authorSnapshotSelectSql = sr_community_author_public_name_snapshot_select($pdo, 'sr_community_comments', 'c');
    $secretSelectSql = sr_community_comment_secret_column_exists($pdo) ? 'c.is_secret,' : '0 AS is_secret,';
    $threadSelectSql = sr_community_comment_thread_columns_exist($pdo)
        ? 'c.parent_comment_id, c.thread_root_id, c.depth,'
        : 'NULL AS parent_comment_id, c.id AS thread_root_id, 1 AS depth,';
    $orderSql = sr_community_comment_thread_columns_exist($pdo)
        ? 'COALESCE(c.thread_root_id, c.id) ASC, c.depth ASC, c.id ASC'
        : 'c.id ASC';
    $stmt = $pdo->prepare(
        "SELECT c.id, c.post_id, " . $threadSelectSql . " c.author_account_id, " . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_comments', 'c') . ", author.status AS author_account_status, c.body_text, " . $secretSelectSql . " c.status, c.created_at, c.updated_at
         FROM sr_community_comments c
         LEFT JOIN sr_member_accounts author ON author.id = c.author_account_id
         WHERE c.post_id = :post_id
           AND c.status = 'published'
         ORDER BY " . $orderSql . "
         LIMIT :limit_value"
    );
    $stmt->bindValue('post_id', $postId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_comment_thread_columns_exist(PDO $pdo): bool
{
    static $existsByConnection = [];
    $key = (string) spl_object_id($pdo);
    if (array_key_exists($key, $existsByConnection)) {
        return $existsByConnection[$key];
    }

    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $columns = [];
            $stmt = $pdo->query('PRAGMA table_info(sr_community_comments)');
            foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                $columns[(string) ($row['name'] ?? '')] = true;
            }
            $existsByConnection[$key] = isset($columns['parent_comment_id'], $columns['thread_root_id'], $columns['depth']);
            return $existsByConnection[$key];
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME IN (\'parent_comment_id\', \'thread_root_id\', \'depth\')'
        );
        $stmt->execute(['table_name' => 'sr_community_comments']);
        $existsByConnection[$key] = (int) $stmt->fetchColumn() === 3;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
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
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->query('PRAGMA table_info(sr_community_comments)');
            foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                if ((string) ($row['name'] ?? '') === 'is_secret') {
                    $existsByConnection[$key] = true;
                    return true;
                }
            }
            $existsByConnection[$key] = false;
            return false;
        }

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

function sr_community_account_can_view_comment_body(array $comment, array $post, ?array $account, ?PDO $pdo = null): bool
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
            || $accountId === (int) ($post['author_account_id'] ?? 0)
            || ($pdo instanceof PDO && sr_community_account_can_manage_post_body($pdo, $post, $account)));
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

function sr_community_admin_comment_status_display_order(): array
{
    return ['published', 'hidden', 'deleted'];
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
            $where[] = '(c.id = :keyword_id OR c.body_text LIKE :body_keyword OR p.title LIKE :post_title_keyword OR a.display_name LIKE :author_keyword OR (a.status NOT IN (\'withdrawn\', \'anonymized\') AND author_nickname.nickname LIKE :author_nickname_keyword) OR b.title LIKE :board_title_keyword OR b.board_key LIKE :board_key_keyword)';
            $params['keyword_id'] = preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0;
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
    $threadSelectSql = sr_community_comment_thread_columns_exist($pdo)
        ? 'c.parent_comment_id, c.thread_root_id, c.depth,'
        : 'NULL AS parent_comment_id, c.id AS thread_root_id, 1 AS depth,';
    $privacyConsentSelectSql = sr_community_submission_consents_table_exists($pdo)
        ? '(SELECT COUNT(*) FROM sr_community_submission_consents pc WHERE pc.subject_type = \'community.comment\' AND pc.subject_id = c.id) AS privacy_consent_count,
                   (SELECT MAX(pc.created_at) FROM sr_community_submission_consents pc WHERE pc.subject_type = \'community.comment\' AND pc.subject_id = c.id) AS privacy_consent_latest_at'
        : '0 AS privacy_consent_count, NULL AS privacy_consent_latest_at';
    $sql = 'SELECT c.id, c.post_id, ' . $threadSelectSql . ' c.author_account_id, ' . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_comments', 'c') . ', c.body_text, c.status, c.created_at, c.updated_at,
                   ' . $secretSelectSql . '
                   p.title AS post_title,
                   b.board_key, b.title AS board_title,
                   a.display_name AS author_display_name,
                   author_nickname.nickname AS author_nickname,
                   a.status AS author_account_status,
                   ' . $privacyConsentSelectSql . '
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
    $threadSelectSql = sr_community_comment_thread_columns_exist($pdo)
        ? 'c.parent_comment_id, c.thread_root_id, c.depth,'
        : 'NULL AS parent_comment_id, c.id AS thread_root_id, 1 AS depth,';
    $stmt = $pdo->prepare(
        'SELECT c.id, c.post_id, ' . $threadSelectSql . ' c.author_account_id, ' . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_comments', 'c') . ', c.body_text, ' . $secretSelectSql . ' c.status, c.created_at, c.updated_at,
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

function sr_community_update_comment_status(PDO $pdo, int $commentId, string $status, array $options = []): void
{
    if ($status === 'deleted') {
        sr_community_redact_deleted_comment($pdo, $commentId);
        return;
    }

    if (sr_community_hidden_columns_exist($pdo, 'sr_community_comments')) {
        sr_community_update_status_with_hidden_metadata($pdo, 'sr_community_comments', $commentId, $status, $options);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_comments
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id
           AND status <> \'deleted\''
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $commentId,
    ]);
}

function sr_community_redact_deleted_comment(PDO $pdo, int $commentId): void
{
    if ($commentId < 1) {
        return;
    }

    $guestRedactionSql = sr_community_guest_author_columns_exist($pdo, 'sr_community_comments')
        ? "guest_author_name = '',
             guest_password_hash = NULL,
             guest_ip_hash = NULL,
             guest_user_agent_hash = NULL,"
        : '';
    $stmt = $pdo->prepare(
        "UPDATE sr_community_comments
         SET status = 'deleted',
             body_text = :body_text,
             author_public_name_snapshot = '',
             " . $guestRedactionSql . "
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'body_text' => sr_t('community::redaction.deleted_comment_body'),
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

function sr_community_guest_can_edit_comment(array $comment, string $password): bool
{
    return (int) ($comment['author_account_id'] ?? 0) < 1
        && (string) ($comment['status'] ?? '') === 'published'
        && sr_community_guest_password_verified($comment, $password);
}

function sr_community_guest_can_delete_comment(array $comment, string $password): bool
{
    return sr_community_guest_can_edit_comment($comment, $password);
}

function sr_community_account_can_comment_post(PDO $pdo, array $post, ?array $account): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    if ((string) ($post['status'] ?? '') !== 'published' || (string) ($post['board_status'] ?? '') !== 'enabled') {
        return false;
    }

    $board = [
        'id' => (int) ($post['board_id'] ?? 0),
        'board_group_id' => (int) ($post['board_group_id'] ?? 0),
        'comment_policy' => (string) ($post['comment_policy'] ?? ''),
    ];
    $policy = sr_community_effective_board_policy($pdo, $board, 'comment_policy');
    if ($policy === 'guest') {
        return true;
    }

    if ($accountId < 1) {
        return false;
    }

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
    $parentCommentIdValue = sr_post_string('parent_comment_id', 20);

    return [
        'body_text' => sr_post_string_without_truncation('body_text', 5000),
        'is_secret' => sr_post_string('is_secret', 10) === '1' ? 1 : 0,
        'parent_comment_id' => preg_match('/\A[1-9][0-9]*\z/', $parentCommentIdValue) === 1 ? (int) $parentCommentIdValue : 0,
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

function sr_community_validate_comment_parent(PDO $pdo, int $postId, array $values): array
{
    $parentCommentId = (int) ($values['parent_comment_id'] ?? 0);
    if ($parentCommentId < 1) {
        return ['parent_comment' => null, 'errors' => []];
    }
    if (!sr_community_comment_thread_columns_exist($pdo)) {
        return ['parent_comment' => null, 'errors' => ['답글 기능을 사용할 수 없습니다. 업데이트를 먼저 적용해 주세요.']];
    }

    $parentComment = sr_community_admin_comment_by_id($pdo, $parentCommentId);
    if (!is_array($parentComment) || (int) ($parentComment['post_id'] ?? 0) !== $postId || (string) ($parentComment['status'] ?? '') !== 'published') {
        return ['parent_comment' => null, 'errors' => ['답글을 작성할 댓글을 찾을 수 없습니다.']];
    }
    if ((int) ($parentComment['depth'] ?? 1) >= 3) {
        return ['parent_comment' => null, 'errors' => ['답글은 3단계까지만 작성할 수 있습니다.']];
    }

    return ['parent_comment' => $parentComment, 'errors' => []];
}

function sr_community_create_comment(PDO $pdo, int $postId, int $authorAccountId, array $values): int
{
    $now = sr_now();
    $authorSnapshotColumnSql = sr_community_author_public_name_snapshot_column_exists($pdo, 'sr_community_comments') ? 'author_public_name_snapshot, ' : '';
    $authorSnapshotValueSql = $authorSnapshotColumnSql !== '' ? ':author_public_name_snapshot, ' : '';
    $guestAuthorColumnSql = sr_community_guest_author_columns_exist($pdo, 'sr_community_comments') ? 'guest_author_name, guest_password_hash, guest_ip_hash, guest_user_agent_hash, ' : '';
    $guestAuthorValueSql = $guestAuthorColumnSql !== '' ? ':guest_author_name, :guest_password_hash, :guest_ip_hash, :guest_user_agent_hash, ' : '';
    $secretColumnSql = sr_community_comment_secret_column_exists($pdo) ? 'is_secret, ' : '';
    $secretValueSql = $secretColumnSql !== '' ? ':is_secret, ' : '';
    $threadColumnSql = sr_community_comment_thread_columns_exist($pdo) ? 'parent_comment_id, thread_root_id, depth, ' : '';
    $threadValueSql = $threadColumnSql !== '' ? ':parent_comment_id, :thread_root_id, :depth, ' : '';
    $parentComment = is_array($values['parent_comment'] ?? null) ? $values['parent_comment'] : null;
    $parentCommentId = is_array($parentComment) ? (int) ($parentComment['id'] ?? 0) : 0;
    $depth = is_array($parentComment) ? min(3, max(2, (int) ($parentComment['depth'] ?? 1) + 1)) : 1;
    $threadRootId = is_array($parentComment) ? (int) (($parentComment['thread_root_id'] ?? 0) ?: ($parentComment['id'] ?? 0)) : null;
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_comments
            (post_id, ' . $threadColumnSql . 'author_account_id, ' . $authorSnapshotColumnSql . $guestAuthorColumnSql . 'body_text, ' . $secretColumnSql . 'status, created_at, updated_at)
         VALUES
            (:post_id, ' . $threadValueSql . ':author_account_id, ' . $authorSnapshotValueSql . $guestAuthorValueSql . ':body_text, ' . $secretValueSql . ':status, :created_at, :updated_at)'
    );
    $params = [
        'post_id' => $postId,
        'author_account_id' => $authorAccountId > 0 ? $authorAccountId : null,
        'body_text' => trim((string) $values['body_text']),
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if ($authorSnapshotColumnSql !== '') {
        $params['author_public_name_snapshot'] = $authorAccountId > 0
            ? sr_community_author_public_name_snapshot($pdo, $authorAccountId)
            : sr_community_guest_author_snapshot((string) ($values['guest_author_name'] ?? ''));
    }
    if ($guestAuthorColumnSql !== '') {
        $guestValues = sr_community_guest_author_values_for_storage($values);
        $params['guest_author_name'] = $authorAccountId > 0 ? '' : (string) $guestValues['guest_author_name'];
        $params['guest_password_hash'] = $authorAccountId > 0 ? null : $guestValues['guest_password_hash'];
        $params['guest_ip_hash'] = $authorAccountId > 0 ? null : $guestValues['guest_ip_hash'];
        $params['guest_user_agent_hash'] = $authorAccountId > 0 ? null : $guestValues['guest_user_agent_hash'];
    }
    if ($secretColumnSql !== '') {
        $params['is_secret'] = (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0;
    }
    if ($threadColumnSql !== '') {
        $params['parent_comment_id'] = $parentCommentId > 0 ? $parentCommentId : null;
        $params['thread_root_id'] = $threadRootId;
        $params['depth'] = $depth;
    }
    $stmt->execute($params);
    $commentId = (int) $pdo->lastInsertId();
    if ($threadColumnSql !== '' && $parentCommentId < 1) {
        $stmt = $pdo->prepare(
            'UPDATE sr_community_comments
             SET thread_root_id = :thread_root_id
             WHERE id = :id'
        );
        $stmt->execute([
            'thread_root_id' => $commentId,
            'id' => $commentId,
        ]);
    }

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

function sr_community_guest_comment_rate_limited(PDO $pdo, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['comment_create_window_seconds'] ?? 300)));
    $limit = min(300, max(1, (int) ($settings['comment_create_limit'] ?? 30)));

    return sr_community_rate_limits_table_exists($pdo)
        && sr_rate_limit_count($pdo, 'community.comment.guest', sr_community_guest_rate_limit_identifier(), $windowSeconds) >= $limit;
}

function sr_community_record_comment_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if (!sr_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['comment_create_window_seconds'] ?? 300)));
    sr_rate_limit_increment($pdo, 'community.comment.account', (string) $accountId, $windowSeconds);
}

function sr_community_record_guest_comment_rate_limit(PDO $pdo, array $settings): void
{
    if (!sr_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['comment_create_window_seconds'] ?? 300)));
    sr_rate_limit_increment($pdo, 'community.comment.guest', sr_community_guest_rate_limit_identifier(), $windowSeconds);
}
