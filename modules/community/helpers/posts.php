<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';
require_once SR_ROOT . '/modules/community/helpers/posts-comments.php';
require_once SR_ROOT . '/modules/community/helpers/posts-extra-fields.php';
require_once SR_ROOT . '/modules/community/helpers/posts-writing.php';

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

function sr_community_board_list_sort_values(): array
{
    return ['latest', 'oldest', 'views', 'comments'];
}

function sr_community_board_list_sort_key(string $value): string
{
    return in_array($value, sr_community_board_list_sort_values(), true) ? $value : 'latest';
}

function sr_community_board_list_sort_sql(string $sort): string
{
    if ($sort === 'oldest') {
        return 'p.id ASC';
    }
    if ($sort === 'views') {
        return 'p.view_count DESC, p.id DESC';
    }
    if ($sort === 'comments') {
        return 'published_comment_count DESC, p.id DESC';
    }

    return 'p.id DESC';
}

function sr_community_board_posts(PDO $pdo, int $boardId, int $limit = 20, int $offset = 0, string $keyword = '', int $categoryId = 0, string $sort = 'latest'): array
{
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $keyword = trim($keyword);
    $orderSql = sr_community_board_list_sort_sql(sr_community_board_list_sort_key($sort));
    $categorySupported = sr_community_categories_supported($pdo);
    $where = "p.board_id = :board_id AND p.status = 'published'";
    $params = ['board_id' => $boardId];
    if ($keyword !== '') {
        $secretBodyCondition = sr_community_post_secret_column_exists($pdo)
            ? "p.is_secret = 0 AND p.body_text LIKE :body_keyword ESCAPE '\\\\'"
            : "p.body_text LIKE :body_keyword ESCAPE '\\\\'";
        $where .= " AND (p.title LIKE :title_keyword ESCAPE '\\\\' OR (" . $secretBodyCondition . '))';
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
    $secretPostSelectSql = sr_community_post_secret_column_exists($pdo) ? 'p.is_secret,' : '0 AS is_secret,';
    $stmt = $pdo->prepare(
        'SELECT p.id, p.board_id, ' . $categorySelectSql . ', p.author_account_id, ' . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_posts', 'p') . sr_community_post_extra_values_select($pdo, 'p') . ', author.status AS author_account_status, p.title, p.body_text, p.body_format, ' . $secretPostSelectSql . ' p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                (SELECT COUNT(*) FROM sr_community_comments c WHERE c.post_id = p.id AND c.status = \'published\') AS published_comment_count,
                (SELECT COUNT(*) FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\') AS active_attachment_count,
                (SELECT att.id FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\' AND att.mime_type IN (\'image/jpeg\', \'image/png\', \'image/gif\', \'image/webp\') ORDER BY att.id ASC LIMIT 1) AS list_image_attachment_id,
                (SELECT att.storage_driver FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\' AND att.mime_type IN (\'image/jpeg\', \'image/png\', \'image/gif\', \'image/webp\') ORDER BY att.id ASC LIMIT 1) AS list_image_storage_driver,
                (SELECT att.storage_key FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\' AND att.mime_type IN (\'image/jpeg\', \'image/png\', \'image/gif\', \'image/webp\') ORDER BY att.id ASC LIMIT 1) AS list_image_storage_key,
                (SELECT att.mime_type FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\' AND att.mime_type IN (\'image/jpeg\', \'image/png\', \'image/gif\', \'image/webp\') ORDER BY att.id ASC LIMIT 1) AS list_image_mime_type,
                (SELECT att.size_bytes FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\' AND att.mime_type IN (\'image/jpeg\', \'image/png\', \'image/gif\', \'image/webp\') ORDER BY att.id ASC LIMIT 1) AS list_image_size_bytes,
                (SELECT att.checksum_sha256 FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\' AND att.mime_type IN (\'image/jpeg\', \'image/png\', \'image/gif\', \'image/webp\') ORDER BY att.id ASC LIMIT 1) AS list_image_checksum_sha256,
                (SELECT att.width FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\' AND att.mime_type IN (\'image/jpeg\', \'image/png\', \'image/gif\', \'image/webp\') ORDER BY att.id ASC LIMIT 1) AS list_image_width,
                (SELECT att.height FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\' AND att.mime_type IN (\'image/jpeg\', \'image/png\', \'image/gif\', \'image/webp\') ORDER BY att.id ASC LIMIT 1) AS list_image_height
         FROM sr_community_posts p
         LEFT JOIN sr_member_accounts author ON author.id = p.author_account_id
         ' . $categoryJoinSql . '
         WHERE ' . $where . '
         ORDER BY ' . $orderSql . '
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

function sr_community_effective_board_int_setting(PDO $pdo, array $board, string $settingKey, int $default, int $min, int $max): int
{
    $value = sr_community_effective_board_setting($pdo, $board, $settingKey, (string) $default);

    return min($max, max($min, (int) $value));
}

function sr_community_board_list_excerpt_enabled(PDO $pdo, array $board): bool
{
    return in_array(sr_community_effective_board_setting($pdo, $board, 'list_excerpt_enabled', '0'), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_board_list_excerpt_length(PDO $pdo, array $board): int
{
    return sr_community_effective_board_int_setting($pdo, $board, 'list_excerpt_length', 120, 1, 1000);
}

function sr_community_board_list_per_page(PDO $pdo, array $board, array $settings = []): int
{
    $default = min(100, max(1, (int) ($settings['posts_per_page'] ?? 20)));

    return sr_community_effective_board_int_setting($pdo, $board, 'list_per_page', $default, 1, 100);
}

function sr_community_board_list_default_sort(PDO $pdo, array $board): string
{
    return sr_community_board_list_sort_key(sr_community_effective_board_setting($pdo, $board, 'list_default_sort', 'latest'));
}

function sr_community_body_plain_text(string $bodyText, string $bodyFormat = 'plain'): string
{
    if ($bodyFormat === 'html') {
        $bodyText = str_replace(['<br>', '<br/>', '<br />'], ' ', $bodyText);
        $bodyText = html_entity_decode(strip_tags($bodyText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    return trim(preg_replace('/\s+/', ' ', $bodyText) ?? '');
}

function sr_community_body_plain_length(string $bodyText, string $bodyFormat = 'plain'): int
{
    $plainText = sr_community_body_plain_text($bodyText, $bodyFormat);

    return function_exists('mb_strlen') ? mb_strlen($plainText) : strlen($plainText);
}

function sr_community_body_excerpt(string $bodyText, string $bodyFormat, int $length): string
{
    $length = max(1, min(1000, $length));
    $plainText = sr_community_body_plain_text($bodyText, $bodyFormat);
    $textLength = function_exists('mb_strlen') ? mb_strlen($plainText) : strlen($plainText);
    if ($textLength <= $length) {
        return $plainText;
    }

    return (function_exists('mb_substr') ? mb_substr($plainText, 0, $length) : substr($plainText, 0, $length)) . '...';
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
        $secretBodyCondition = sr_community_post_secret_column_exists($pdo)
            ? "is_secret = 0 AND body_text LIKE :body_keyword ESCAPE '\\\\'"
            : "body_text LIKE :body_keyword ESCAPE '\\\\'";
        $where .= " AND (title LIKE :title_keyword ESCAPE '\\\\' OR (" . $secretBodyCondition . '))';
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

function sr_community_public_posts(PDO $pdo, int $boardId, int $limit = 20, int $offset = 0, string $keyword = '', int $categoryId = 0, string $sort = 'latest'): array
{
    return sr_community_board_posts($pdo, $boardId, $limit, $offset, $keyword, $categoryId, $sort);
}

function sr_community_public_post_count(PDO $pdo, int $boardId, string $keyword = '', int $categoryId = 0): int
{
    return sr_community_board_post_count($pdo, $boardId, $keyword, $categoryId);
}

function sr_community_search_posts(PDO $pdo, array $boardIds, string $keyword, int $limit = 20, int $offset = 0, ?array $bodySearchBoardIds = null): array
{
    $boardIds = array_values(array_unique(array_filter(array_map('intval', $boardIds), static fn (int $boardId): bool => $boardId > 0)));
    $bodySearchBoardIds = $bodySearchBoardIds === null
        ? $boardIds
        : array_values(array_intersect($boardIds, array_unique(array_filter(array_map('intval', $bodySearchBoardIds), static fn (int $boardId): bool => $boardId > 0))));
    $keyword = trim($keyword);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    if ($boardIds === [] || $keyword === '') {
        return [];
    }

    $boardPlaceholders = [];
    $params = [
        'title_keyword' => sr_community_search_like_pattern($keyword),
    ];
    foreach ($boardIds as $index => $boardId) {
        $placeholder = 'board_id_' . (string) $index;
        $boardPlaceholders[] = ':' . $placeholder;
        $params[$placeholder] = $boardId;
    }
    $bodyBoardPlaceholders = [];
    foreach ($bodySearchBoardIds as $index => $boardId) {
        $placeholder = 'body_board_id_' . (string) $index;
        $bodyBoardPlaceholders[] = ':' . $placeholder;
        $params[$placeholder] = $boardId;
    }
    $categorySupported = sr_community_categories_supported($pdo);
    $categorySelectSql = $categorySupported
        ? 'p.category_id, cat.category_key, cat.title AS category_title, cat.status AS category_status'
        : 'NULL AS category_id, NULL AS category_key, NULL AS category_title, NULL AS category_status';
    $categoryJoinSql = $categorySupported ? 'LEFT JOIN sr_community_categories cat ON cat.id = p.category_id' : '';
    $secretPostSelectSql = sr_community_post_secret_column_exists($pdo) ? 'p.is_secret,' : '0 AS is_secret,';
    $secretBodyCondition = sr_community_post_secret_column_exists($pdo)
        ? "p.is_secret = 0 AND p.body_text LIKE :body_keyword ESCAPE '!'"
        : "p.body_text LIKE :body_keyword ESCAPE '!'";
    $searchCondition = "p.title LIKE :title_keyword ESCAPE '!'";
    if ($bodyBoardPlaceholders !== []) {
        $params['body_keyword'] = sr_community_search_like_pattern($keyword);
        $searchCondition .= ' OR (p.board_id IN (' . implode(', ', $bodyBoardPlaceholders) . ') AND (' . $secretBodyCondition . '))';
    }

    $stmt = $pdo->prepare(
        'SELECT p.id, p.board_id, b.board_key, b.title AS board_title, ' . $categorySelectSql . ', p.author_account_id, p.title, p.body_text, p.body_format, ' . $secretPostSelectSql . " p.status, p.view_count, p.created_at, p.updated_at,
                (SELECT COUNT(*) FROM sr_community_comments c WHERE c.post_id = p.id AND c.status = 'published') AS published_comment_count
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         " . $categoryJoinSql . "
         WHERE p.status = 'published'
           AND b.status = 'enabled'
           AND p.board_id IN (" . implode(', ', $boardPlaceholders) . ")
           AND (" . $searchCondition . ")
         ORDER BY p.id DESC
         LIMIT :limit_value OFFSET :offset_value"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, str_starts_with($key, 'board_id_') ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_search_like_pattern(string $keyword): string
{
    return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($keyword)) . '%';
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
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->query('PRAGMA table_info(' . $tableName . ')');
            foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                if ((string) ($row['name'] ?? '') === 'author_public_name_snapshot') {
                    $exists[$cacheKey] = true;
                    return true;
                }
            }
            $exists[$cacheKey] = false;
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
    if ($accountId < 1) {
        return '';
    }

    $name = trim(sr_member_public_name_for_account_id($pdo, $accountId, sr_t('community::report.account.member')));

    return function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120);
}

function sr_community_guest_author_columns_exist(PDO $pdo, string $tableName): bool
{
    static $existsByConnection = [];
    if (!in_array($tableName, ['sr_community_posts', 'sr_community_comments'], true)) {
        return false;
    }

    $key = (string) spl_object_id($pdo) . ':' . $tableName;
    if (array_key_exists($key, $existsByConnection)) {
        return $existsByConnection[$key];
    }

    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->query('PRAGMA table_info(' . $tableName . ')');
            $columns = [];
            foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                $columns[(string) ($row['name'] ?? '')] = true;
            }
            $existsByConnection[$key] = isset($columns['guest_author_name'], $columns['guest_password_hash'], $columns['guest_ip_hash'], $columns['guest_user_agent_hash']);
            return $existsByConnection[$key];
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME IN (\'guest_author_name\', \'guest_password_hash\', \'guest_ip_hash\', \'guest_user_agent_hash\')'
        );
        $stmt->execute(['table_name' => $tableName]);
        $existsByConnection[$key] = (int) $stmt->fetchColumn() === 4;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
}

function sr_community_guest_author_select(PDO $pdo, string $tableName, string $alias): string
{
    if (sr_community_guest_author_columns_exist($pdo, $tableName)) {
        return ', ' . $alias . '.guest_author_name, ' . $alias . '.guest_password_hash, ' . $alias . '.guest_ip_hash, ' . $alias . '.guest_user_agent_hash';
    }

    return ", '' AS guest_author_name, NULL AS guest_password_hash, NULL AS guest_ip_hash, NULL AS guest_user_agent_hash";
}

function sr_community_guest_author_snapshot(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    return function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120);
}

function sr_community_guest_author_input_values(): array
{
    return [
        'guest_author_name' => sr_post_string_without_truncation('guest_author_name', 120),
        'guest_password' => sr_post_string_without_truncation('guest_password', 255),
    ];
}

function sr_community_validate_guest_author_input(array $values): array
{
    $errors = [];
    $name = sr_community_guest_author_snapshot((string) ($values['guest_author_name'] ?? ''));
    $password = (string) ($values['guest_password'] ?? '');
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    $passwordLength = function_exists('mb_strlen') ? mb_strlen($password) : strlen($password);

    if ($name === '') {
        $errors[] = '비회원 작성자명을 입력해 주세요.';
    } elseif ($nameLength > 120) {
        $errors[] = '비회원 작성자명은 120자 이내로 입력해 주세요.';
    }
    if ($passwordLength < 8 || $passwordLength > 255) {
        $errors[] = '비회원 수정/삭제 비밀번호는 8자 이상 255자 이하로 입력해 주세요.';
    }

    return $errors;
}

function sr_community_guest_author_values_for_storage(array $values): array
{
    $password = (string) ($values['guest_password'] ?? '');

    return [
        'guest_author_name' => sr_community_guest_author_snapshot((string) ($values['guest_author_name'] ?? '')),
        'guest_password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
        'guest_ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
        'guest_user_agent_hash' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
    ];
}

function sr_community_guest_password_verified(array $row, string $password): bool
{
    $hash = (string) ($row['guest_password_hash'] ?? '');
    return $hash !== '' && password_verify($password, $hash);
}

function sr_community_guest_rate_limit_identifier(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
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
    $secretPostSelectSql = sr_community_post_secret_column_exists($pdo) ? 'p.is_secret,' : '0 AS is_secret,';
    $reactionPresetSelectSql = sr_community_post_reaction_preset_columns_exist($pdo) ? 'p.reaction_preset_key, p.reaction_comment_preset_key,' : "'' AS reaction_preset_key, '' AS reaction_comment_preset_key,";
    $stmt = $pdo->prepare(
        "SELECT p.id, p.board_id, " . $categorySelectSql . ", p.author_account_id, " . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_posts', 'p') . sr_community_post_extra_values_select($pdo, 'p') . ", author.status AS author_account_status, p.title, p.body_text, p.body_format, " . $reactionPresetSelectSql . " p.seo_title, p.seo_description, p.og_title, p.og_description, p.og_image_attachment_id, " . $secretPostSelectSql . " p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
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
    $secretPostSelectSql = sr_community_post_secret_column_exists($pdo) ? 'p.is_secret,' : '0 AS is_secret,';
    $reactionPresetSelectSql = sr_community_post_reaction_preset_columns_exist($pdo) ? 'p.reaction_preset_key, p.reaction_comment_preset_key,' : "'' AS reaction_preset_key, '' AS reaction_comment_preset_key,";
    $stmt = $pdo->prepare(
        "SELECT p.id, p.board_id, " . $categorySelectSql . ", p.author_account_id, " . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_posts', 'p') . sr_community_post_extra_values_select($pdo, 'p') . ", author.status AS author_account_status, p.title, p.body_text, p.body_format, " . $reactionPresetSelectSql . " p.seo_title, p.seo_description, p.og_title, p.og_description, p.og_image_attachment_id, " . $secretPostSelectSql . " p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
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

function sr_community_post_secret_column_exists(PDO $pdo): bool
{
    static $existsByConnection = [];
    $key = (string) spl_object_id($pdo);
    if (array_key_exists($key, $existsByConnection)) {
        return $existsByConnection[$key];
    }

    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->query('PRAGMA table_info(sr_community_posts)');
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
            'table_name' => 'sr_community_posts',
            'column_name' => 'is_secret',
        ]);
        $existsByConnection[$key] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
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

function sr_community_should_count_post_view(int $postId): bool
{
    if ($postId < 1) {
        return false;
    }

    $sessionKey = 'sr_community_viewed_posts';
    $viewed = isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey]) ? $_SESSION[$sessionKey] : [];
    $postKey = (string) $postId;
    if (isset($viewed[$postKey])) {
        return false;
    }

    $viewed[$postKey] = time();
    if (count($viewed) > 500) {
        asort($viewed);
        $viewed = array_slice($viewed, -500, null, true);
    }
    $_SESSION[$sessionKey] = $viewed;

    return true;
}

function sr_community_hidden_columns_exist(PDO $pdo, string $tableName): bool
{
    static $existsByConnection = [];
    if (!in_array($tableName, ['sr_community_posts', 'sr_community_comments'], true)) {
        return false;
    }

    $key = (string) spl_object_id($pdo) . ':' . $tableName;
    if (array_key_exists($key, $existsByConnection)) {
        return $existsByConnection[$key];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME IN (\'hidden_at\', \'hidden_until\', \'hidden_reason\', \'hidden_note\', \'hidden_by_account_id\', \'hidden_before_status\')'
        );
        $stmt->execute(['table_name' => $tableName]);
        $existsByConnection[$key] = (int) $stmt->fetchColumn() === 6;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
}

function sr_community_account_can_manage_post_body(PDO $pdo, array $post, ?array $account): bool
{
    if (!is_array($account) || (int) ($account['id'] ?? 0) < 1) {
        return false;
    }

    $accountId = (int) $account['id'];

    return (function_exists('sr_admin_has_permission')
            && (sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'view')
                || sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'edit')
                || sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'delete')))
        || (function_exists('sr_community_account_has_board_management_permission')
            && sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), $accountId, 'view_manage'));
}

function sr_community_account_can_view_post_body(PDO $pdo, array $post, ?array $account): bool
{
    if ((int) ($post['is_secret'] ?? 0) !== 1) {
        return true;
    }
    if (!is_array($account)) {
        return false;
    }

    $accountId = (int) ($account['id'] ?? 0);

    return $accountId > 0
        && ($accountId === (int) ($post['author_account_id'] ?? 0)
            || sr_community_account_can_manage_post_body($pdo, $post, $account));
}

function sr_community_relative_time_label(string $dateTime): string
{
    return sr_relative_time_label($dateTime);
}

function sr_community_post_statuses(): array
{
    return ['published', 'hidden', 'deleted', 'pending'];
}

function sr_community_admin_post_status_display_order(): array
{
    return ['pending', 'published', 'hidden', 'deleted'];
}

function sr_community_admin_status_action_label(string $status): string
{
    return match ($status) {
        'pending' => '대기',
        'published' => '공개',
        'hidden' => '숨김',
        'deleted' => '삭제',
        default => sr_admin_code_label($status, 'content_status'),
    };
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
        } elseif ($field === 'extra' && (!empty($filters['extra_field_values_supported']) || !empty($filters['extra_values_supported']))) {
            $where[] = !empty($filters['extra_field_values_supported'])
                ? 'EXISTS (SELECT 1 FROM sr_community_post_field_values ev WHERE ev.post_id = p.id AND ev.show_in_admin_snapshot = 1 AND ev.value_text LIKE :extra_values_keyword)'
                : 'p.extra_values_json LIKE :extra_values_keyword';
            $params['extra_values_keyword'] = '%' . $keyword . '%';
        } else {
            $extraValuesCondition = '';
            if (!empty($filters['extra_field_values_supported'])) {
                $extraValuesCondition = ' OR EXISTS (SELECT 1 FROM sr_community_post_field_values ev WHERE ev.post_id = p.id AND ev.show_in_admin_snapshot = 1 AND ev.value_text LIKE :extra_values_keyword)';
            } elseif (!empty($filters['extra_values_supported'])) {
                $extraValuesCondition = ' OR p.extra_values_json LIKE :extra_values_keyword';
            }
            $where[] = '(p.id = :keyword_id OR p.title LIKE :title_keyword OR a.display_name LIKE :author_keyword OR (a.status NOT IN (\'withdrawn\', \'anonymized\') AND author_nickname.nickname LIKE :author_nickname_keyword) OR b.title LIKE :board_title_keyword OR b.board_key LIKE :board_key_keyword' . $extraValuesCondition . ')';
            $params['keyword_id'] = preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0;
            $params['title_keyword'] = '%' . $keyword . '%';
            $params['author_keyword'] = '%' . $keyword . '%';
            $params['author_nickname_keyword'] = '%' . $keyword . '%';
            $params['board_title_keyword'] = '%' . $keyword . '%';
            $params['board_key_keyword'] = '%' . $keyword . '%';
            if (!empty($filters['extra_field_values_supported']) || !empty($filters['extra_values_supported'])) {
                $params['extra_values_keyword'] = '%' . $keyword . '%';
            }
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
        'view_count' => ['columns' => ['p.view_count', 'p.id']],
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
    $privacyConsentSelectSql = sr_community_submission_consents_table_exists($pdo)
        ? '(SELECT COUNT(*) FROM sr_community_submission_consents pc WHERE pc.subject_type = \'community.post\' AND pc.subject_id = p.id) AS privacy_consent_count,
                   (SELECT MAX(pc.created_at) FROM sr_community_submission_consents pc WHERE pc.subject_type = \'community.post\' AND pc.subject_id = p.id) AS privacy_consent_latest_at,'
        : '0 AS privacy_consent_count, NULL AS privacy_consent_latest_at,';
    $sql = 'SELECT p.id, p.board_id, ' . $categorySelectSql . ', p.author_account_id, ' . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_posts', 'p') . sr_community_post_extra_values_select($pdo, 'p') . ', p.title, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                   b.board_key, b.title AS board_title,
                   a.display_name AS author_display_name,
                   author_nickname.nickname AS author_nickname,
                   a.status AS author_account_status,
                   ' . $privacyConsentSelectSql . '
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
        'SELECT p.id, p.board_id, ' . $categorySelectSql . ', p.author_account_id, ' . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_posts', 'p') . sr_community_post_extra_values_select($pdo, 'p') . ', p.title, p.body_text, p.body_format, p.seo_title, p.seo_description, p.og_title, p.og_description, p.og_image_attachment_id, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
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

function sr_community_plain_text_html(string $value, bool $linkUrls = false): string
{
    return sr_plain_text_html($value, $linkUrls);
}

function sr_community_post_body_html(array $post, ?array $settings = null, ?PDO $pdo = null): string
{
    $bodyText = (string) ($post['body_text'] ?? '');
    if ((string) ($post['body_format'] ?? 'plain') === 'html') {
        $html = sr_community_sanitize_post_html($bodyText);
    } else {
        $linkUrls = sr_community_bool_setting($settings['plain_text_auto_link_urls'] ?? $post['plain_text_auto_link_urls'] ?? false);
        $html = sr_community_plain_text_html($bodyText, $linkUrls);
    }

    if ($pdo instanceof PDO && (string) ($post['body_format'] ?? 'plain') === 'html') {
        $html = sr_embed_manager_render_body_html($pdo, $html, 'community', 'post', (int) ($post['id'] ?? 0), 'body', ['mode' => 'public']);
    }

    return $html;
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
    return sr_rich_text_allowed_html_tags();
}

function sr_community_sanitize_post_html(string $html): string
{
    return sr_sanitize_rich_text_html($html);
}
