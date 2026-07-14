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

function sr_community_mark_comment_page_access(int $postId): void
{
    if ($postId < 1) {
        return;
    }

    $access = isset($_SESSION['sr_community_comment_page_access']) && is_array($_SESSION['sr_community_comment_page_access'])
        ? $_SESSION['sr_community_comment_page_access']
        : [];
    $now = time();
    foreach ($access as $storedPostId => $accessedAt) {
        if ((int) $accessedAt < $now - 7200) {
            unset($access[$storedPostId]);
        }
    }
    $access[(string) $postId] = $now;
    if (count($access) > 100) {
        asort($access);
        $access = array_slice($access, -100, null, true);
    }
    $_SESSION['sr_community_comment_page_access'] = $access;
}

function sr_community_has_comment_page_access(int $postId): bool
{
    if ($postId < 1 || !isset($_SESSION['sr_community_comment_page_access']) || !is_array($_SESSION['sr_community_comment_page_access'])) {
        return false;
    }

    return (int) ($_SESSION['sr_community_comment_page_access'][(string) $postId] ?? 0) >= time() - 7200;
}

function sr_community_post_comment_page(PDO $pdo, int $postId, int $page = 1, int $perPage = 50): array
{
    $perPage = max(1, min(100, $perPage));
    $total = sr_community_post_published_comment_count($pdo, $postId);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($totalPages, $page));
    $offset = ($page - 1) * $perPage;
    $nicknameSupported = function_exists('sr_member_nicknames_table_exists') && sr_member_nicknames_table_exists($pdo);
    $nicknameSelect = $nicknameSupported ? ', author_nickname.nickname AS author_nickname' : ", '' AS author_nickname";
    $nicknameJoin = $nicknameSupported ? 'LEFT JOIN sr_member_nicknames author_nickname ON author_nickname.account_id = c.author_account_id' : '';
    $stmt = $pdo->prepare(
        "SELECT c.id, c.post_id, c.parent_comment_id, c.thread_root_id, c.depth, c.author_account_id, c.author_public_name_snapshot" . sr_community_guest_author_select($pdo, 'sr_community_comments', 'c') . ", author.display_name AS author_display_name, author.status AS author_account_status" . $nicknameSelect . ", c.body_text, c.is_secret, c.status, c.created_at, c.updated_at
         FROM sr_community_comments c
         LEFT JOIN sr_member_accounts author ON author.id = c.author_account_id
         " . $nicknameJoin . "
         WHERE c.post_id = :post_id
           AND c.status = 'published'
         ORDER BY COALESCE(c.thread_root_id, c.id) ASC, c.depth ASC, c.id ASC
         LIMIT :limit_value OFFSET :offset_value"
    );
    $stmt->bindValue('post_id', $postId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $comments = $stmt->fetchAll();

    return [
        'comments' => $comments,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_previous' => $page > 1,
        'has_next' => $page < $totalPages,
    ];
}

function sr_community_post_comments(PDO $pdo, int $postId, int $limit = 50): array
{
    $page = sr_community_post_comment_page($pdo, $postId, 1, $limit);

    return is_array($page['comments'] ?? null) ? $page['comments'] : [];
}

function sr_community_comment_page_for_comment(PDO $pdo, int $postId, int $commentId, int $perPage): int
{
    if ($postId < 1 || $commentId < 1) {
        return 1;
    }

    $perPage = max(1, min(100, $perPage));

    $targetStmt = $pdo->prepare(
        "SELECT id, thread_root_id, depth
         FROM sr_community_comments
         WHERE id = :id
           AND post_id = :post_id
           AND status = 'published'
         LIMIT 1"
    );
    $targetStmt->execute([
        'id' => $commentId,
        'post_id' => $postId,
    ]);
    $target = $targetStmt->fetch();
    if (!is_array($target)) {
        return 1;
    }

    $targetThreadRootId = (int) (($target['thread_root_id'] ?? 0) ?: ($target['id'] ?? 0));
    $targetDepth = min(3, max(1, (int) ($target['depth'] ?? 1)));

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_community_comments
         WHERE post_id = :post_id
           AND status = 'published'
           AND (
               COALESCE(thread_root_id, id) < :target_thread_root_lt
               OR (COALESCE(thread_root_id, id) = :target_thread_root_depth AND depth < :target_depth_lt)
               OR (COALESCE(thread_root_id, id) = :target_thread_root_id AND depth = :target_depth_id AND id <= :target_id)
           )"
    );
    $stmt->bindValue('post_id', $postId, PDO::PARAM_INT);
    $stmt->bindValue('target_thread_root_lt', $targetThreadRootId, PDO::PARAM_INT);
    $stmt->bindValue('target_thread_root_depth', $targetThreadRootId, PDO::PARAM_INT);
    $stmt->bindValue('target_thread_root_id', $targetThreadRootId, PDO::PARAM_INT);
    $stmt->bindValue('target_depth_lt', $targetDepth, PDO::PARAM_INT);
    $stmt->bindValue('target_depth_id', $targetDepth, PDO::PARAM_INT);
    $stmt->bindValue('target_id', $commentId, PDO::PARAM_INT);
    $stmt->execute();

    $position = max(1, (int) $stmt->fetchColumn());

    return (int) ceil($position / $perPage);
}

function sr_community_comment_pagination_html(int $postId, array $pagination): string
{
    $currentPage = max(1, (int) ($pagination['page'] ?? 1));
    $totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
    if ($postId < 1 || $totalPages <= 1) {
        return '';
    }

    $pageNumbers = [1 => 1, $totalPages => $totalPages];
    for ($page = max(1, $currentPage - 2); $page <= min($totalPages, $currentPage + 2); $page++) {
        $pageNumbers[$page] = $page;
    }
    ksort($pageNumbers);

    $html = '<nav class="community-comments-pagination" aria-label="댓글 페이지" data-community-comment-pagination>';
    $previousPage = 0;
    foreach ($pageNumbers as $pageNumber) {
        if ($previousPage > 0 && $pageNumber > $previousPage + 1) {
            $html .= '<span class="community-comments-pagination-gap" aria-hidden="true">…</span>';
        }
        if ($pageNumber === $currentPage) {
            $html .= '<span class="btn btn-solid-primary" aria-current="page">' . sr_e((string) $pageNumber) . '</span>';
        } else {
            $url = sr_url('/community/post?id=' . rawurlencode((string) $postId) . '&comment_page=' . rawurlencode((string) $pageNumber) . '#comments');
            $html .= '<a class="btn btn-ghost-default" href="' . sr_e($url) . '" data-community-comment-page="' . sr_e((string) $pageNumber) . '" aria-label="댓글 ' . sr_e((string) $pageNumber) . '페이지">' . sr_e((string) $pageNumber) . '</a>';
        }
        $previousPage = $pageNumber;
    }
    $html .= '</nav>';

    return $html;
}

function sr_community_comment_page_path(int $postId, int $page = 1, string $anchor = 'comments'): string
{
    $path = '/community/post?id=' . rawurlencode((string) max(0, $postId));
    if ($page > 1) {
        $path .= '&comment_page=' . rawurlencode((string) $page);
    }

    return $path . '#' . rawurlencode($anchor !== '' ? $anchor : 'comments');
}

function sr_community_account_can_view_comment_body(array $comment, array $post, ?array $account, ?PDO $pdo = null, array $permissionContext = []): bool
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
            || (!empty($permissionContext['can_manage_body']))
            || ($permissionContext === [] && $pdo instanceof PDO && sr_community_account_can_manage_post_body($pdo, $post, $account)));
}

function sr_community_comment_permission_context(PDO $pdo, array $post, ?array $account): array
{
    $context = [
        'can_manage_body' => false,
        'can_hide' => false,
        'can_delete' => false,
    ];
    $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
    if ($accountId < 1) {
        return $context;
    }

    $isOwner = function_exists('sr_admin_is_owner') && sr_admin_is_owner($pdo, $accountId);
    $adminPermissions = [];
    if (!$isOwner && function_exists('sr_admin_current_permission_keys')) {
        foreach (sr_admin_current_permission_keys($pdo, $accountId) as $permissionToken) {
            $adminPermissions[(string) $permissionToken] = true;
        }
    }
    $hasAdminPermission = static function (string $menuPath, string $actionKey) use ($isOwner, $adminPermissions): bool {
        return $isOwner || isset($adminPermissions[$menuPath . '|' . $actionKey]);
    };
    $boardPermissions = function_exists('sr_community_account_board_management_permissions')
        ? sr_community_account_board_management_permissions($pdo, (int) ($post['board_id'] ?? 0), $accountId)
        : [];

    $context['can_manage_body'] = $hasAdminPermission('/admin/community/posts', 'view')
        || $hasAdminPermission('/admin/community/posts', 'edit')
        || $hasAdminPermission('/admin/community/posts', 'delete')
        || isset($boardPermissions['view_manage']);
    $context['can_hide'] = $hasAdminPermission('/admin/community/comments', 'edit')
        || $hasAdminPermission('/admin/community/comments', 'delete')
        || $hasAdminPermission('/admin/community/posts', 'edit')
        || $hasAdminPermission('/admin/community/posts', 'delete')
        || isset($boardPermissions['hide_comment'])
        || isset($boardPermissions['delete_comment'])
        || isset($boardPermissions['delete_post']);
    $context['can_delete'] = $hasAdminPermission('/admin/community/comments', 'delete')
        || $hasAdminPermission('/admin/community/posts', 'delete')
        || isset($boardPermissions['delete_comment'])
        || isset($boardPermissions['delete_post']);

    return $context;
}

function sr_community_account_can_hide_comment(PDO $pdo, array $comment, array $post, ?array $account, array $permissionContext = []): bool
{
    if (!is_array($account) || (int) ($account['id'] ?? 0) < 1 || (string) ($comment['status'] ?? '') !== 'published') {
        return false;
    }

    $accountId = (int) $account['id'];

    if ($permissionContext !== []) {
        return !empty($permissionContext['can_hide']);
    }

    return (function_exists('sr_admin_has_permission')
            && (sr_admin_has_permission($pdo, $accountId, '/admin/community/comments', 'edit')
                || sr_admin_has_permission($pdo, $accountId, '/admin/community/comments', 'delete')
                || sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'edit')
                || sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'delete')))
        || sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), $accountId, 'hide_comment')
        || sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), $accountId, 'delete_comment')
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
            FROM sr_community_comments c'
            . sr_community_admin_comment_count_join_sql($filters);
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_admin_comment_count_join_sql(array $filters): string
{
    $keyword = trim((string) ($filters['q'] ?? ''));
    $field = (string) ($filters['field'] ?? 'all');
    $usesAll = !in_array($field, ['body', 'author', 'post', 'board'], true);
    $needsPost = (int) ($filters['board_id'] ?? 0) > 0
        || ($keyword !== '' && ($field === 'post' || $field === 'board' || $usesAll));
    $needsBoard = (int) ($filters['board_id'] ?? 0) > 0
        || ($keyword !== '' && ($field === 'board' || $usesAll));
    $needsAuthor = $keyword !== '' && ($field === 'author' || $usesAll);
    $joins = [];

    if ($needsPost || $needsBoard) {
        $joins[] = 'INNER JOIN sr_community_posts p ON p.id = c.post_id';
    }
    if ($needsBoard) {
        $joins[] = 'INNER JOIN sr_community_boards b ON b.id = p.board_id';
    }
    if ($needsAuthor) {
        $joins[] = 'LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id';
        $joins[] = 'LEFT JOIN sr_member_nicknames author_nickname ON author_nickname.account_id = a.id';
    }

    return $joins === [] ? '' : "\n            " . implode("\n            ", $joins);
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
    $privacyConsentSelectSql = sr_community_submission_consents_table_exists($pdo)
        ? '(SELECT COUNT(*) FROM sr_community_submission_consents pc WHERE pc.subject_type = \'community.comment\' AND pc.subject_id = c.id) AS privacy_consent_count,
                   (SELECT MAX(pc.created_at) FROM sr_community_submission_consents pc WHERE pc.subject_type = \'community.comment\' AND pc.subject_id = c.id) AS privacy_consent_latest_at'
        : '0 AS privacy_consent_count, NULL AS privacy_consent_latest_at';
    $sql = 'SELECT c.id, c.post_id, c.parent_comment_id, c.thread_root_id, c.depth, c.author_account_id, c.author_public_name_snapshot' . sr_community_guest_author_select($pdo, 'sr_community_comments', 'c') . ', c.body_text, c.status, c.created_at, c.updated_at,
                   c.is_secret,
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

    $stmt = $pdo->prepare(
        'SELECT c.id, c.post_id, c.parent_comment_id, c.thread_root_id, c.depth, c.author_account_id, c.author_public_name_snapshot' . sr_community_guest_author_select($pdo, 'sr_community_comments', 'c') . ', c.body_text, c.is_secret, c.status, c.created_at, c.updated_at,
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

    sr_community_update_status_with_hidden_metadata($pdo, 'sr_community_comments', $commentId, $status, $options);
}

function sr_community_redact_deleted_comment(PDO $pdo, int $commentId): void
{
    if ($commentId < 1) {
        return;
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_community_comments
         SET status = 'deleted',
             body_text = :body_text,
             author_public_name_snapshot = '',
             guest_author_name = '',
             guest_password_hash = NULL,
             guest_ip_hash = NULL,
             guest_user_agent_hash = NULL,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'body_text' => sr_t('community::redaction.deleted_comment_body'),
        'updated_at' => sr_now(),
        'id' => $commentId,
    ]);
    sr_community_clear_hidden_target($pdo, 'comment', $commentId);
}

function sr_community_update_comment_content(PDO $pdo, int $commentId, array $values): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_comments
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

function sr_community_account_can_edit_comment(array $comment, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) $comment['author_account_id'] === (int) $account['id']
        && (string) $comment['status'] === 'published';
}

function sr_community_account_can_delete_comment(array $comment, array $account, ?PDO $pdo = null, ?array $post = null, array $permissionContext = []): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId > 0
        && (int) $comment['author_account_id'] === (int) $account['id']
        && (string) $comment['status'] === 'published') {
        return true;
    }
    if (!$pdo instanceof PDO || !is_array($post)) {
        return false;
    }
    if ((string) ($comment['status'] ?? '') !== 'published') {
        return false;
    }
    if ($permissionContext !== []) {
        return !empty($permissionContext['can_delete']);
    }

    return (function_exists('sr_admin_has_permission')
            && (sr_admin_has_permission($pdo, $accountId, '/admin/community/comments', 'delete')
                || sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'delete')))
        || sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), $accountId, 'delete_comment')
        || sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), $accountId, 'delete_post');
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
    $parentComment = is_array($values['parent_comment'] ?? null) ? $values['parent_comment'] : null;
    $parentCommentId = is_array($parentComment) ? (int) ($parentComment['id'] ?? 0) : 0;
    $depth = is_array($parentComment) ? min(3, max(2, (int) ($parentComment['depth'] ?? 1) + 1)) : 1;
    $threadRootId = is_array($parentComment) ? (int) (($parentComment['thread_root_id'] ?? 0) ?: ($parentComment['id'] ?? 0)) : null;
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_comments
            (post_id, parent_comment_id, thread_root_id, depth, author_account_id, author_public_name_snapshot, guest_author_name, guest_password_hash, guest_ip_hash, guest_user_agent_hash, body_text, is_secret, status, created_at, updated_at)
         VALUES
            (:post_id, :parent_comment_id, :thread_root_id, :depth, :author_account_id, :author_public_name_snapshot, :guest_author_name, :guest_password_hash, :guest_ip_hash, :guest_user_agent_hash, :body_text, :is_secret, :status, :created_at, :updated_at)'
    );
    $guestValues = sr_community_guest_author_values_for_storage($values);
    $params = [
        'post_id' => $postId,
        'parent_comment_id' => $parentCommentId > 0 ? $parentCommentId : null,
        'thread_root_id' => $threadRootId,
        'depth' => $depth,
        'author_account_id' => $authorAccountId > 0 ? $authorAccountId : null,
        'author_public_name_snapshot' => $authorAccountId > 0
            ? sr_community_author_public_name_snapshot($pdo, $authorAccountId)
            : sr_community_guest_author_snapshot((string) ($values['guest_author_name'] ?? '')),
        'guest_author_name' => $authorAccountId > 0 ? '' : (string) $guestValues['guest_author_name'],
        'guest_password_hash' => $authorAccountId > 0 ? null : $guestValues['guest_password_hash'],
        'guest_ip_hash' => $authorAccountId > 0 ? null : $guestValues['guest_ip_hash'],
        'guest_user_agent_hash' => $authorAccountId > 0 ? null : $guestValues['guest_user_agent_hash'],
        'body_text' => trim((string) $values['body_text']),
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $stmt->execute($params);
    $commentId = (int) $pdo->lastInsertId();
    if ($parentCommentId < 1) {
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
    if (function_exists('sr_community_feed_cache_mark_all_stale')) {
        sr_community_feed_cache_mark_all_stale($pdo, 'comment_created');
    }

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
