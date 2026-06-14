<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

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
    $stmt = $pdo->prepare(
        'SELECT p.id, p.board_id, ' . $categorySelectSql . ', p.author_account_id, ' . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_posts', 'p') . sr_community_post_extra_values_select($pdo, 'p') . ', author.status AS author_account_status, p.title, p.body_text, p.body_format, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
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

function sr_community_post_extra_values_column_exists(PDO $pdo): bool
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
                if ((string) ($row['name'] ?? '') === 'extra_values_json') {
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
               AND TABLE_NAME = \'sr_community_posts\'
               AND COLUMN_NAME = \'extra_values_json\''
        );
        $stmt->execute();
        $existsByConnection[$key] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
}

function sr_community_post_extra_values_select(PDO $pdo, string $alias): string
{
    return sr_community_post_extra_values_column_exists($pdo)
        ? ', ' . $alias . '.extra_values_json'
        : ", '' AS extra_values_json";
}

function sr_community_board_field_definitions_table_exists(PDO $pdo): bool
{
    return function_exists('sr_community_optional_table_exists')
        && sr_community_optional_table_exists($pdo, 'sr_community_board_field_definitions');
}

function sr_community_post_field_values_table_exists(PDO $pdo): bool
{
    return function_exists('sr_community_optional_table_exists')
        && sr_community_optional_table_exists($pdo, 'sr_community_post_field_values');
}

function sr_community_extra_field_type(string $type): string
{
    return in_array($type, ['text', 'textarea', 'select', 'checkbox'], true) ? $type : 'text';
}

function sr_community_extra_field_scalar_string(mixed $value): string
{
    return is_scalar($value) ? (string) $value : '';
}

function sr_community_normalize_extra_field_definitions(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $definitions = [];
    $seenKeys = [];
    foreach ($raw as $item) {
        if (!is_array($item) || count($definitions) >= 20) {
            continue;
        }

        $key = strtolower(trim(sr_community_extra_field_scalar_string($item['key'] ?? '')));
        if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) !== 1 || isset($seenKeys[$key])) {
            continue;
        }
        $label = trim(preg_replace('/\s+/', ' ', sr_community_extra_field_scalar_string($item['label'] ?? '')) ?? '');
        $label = function_exists('mb_substr') ? mb_substr($label, 0, 120) : substr($label, 0, 120);
        if ($label === '') {
            continue;
        }

        $type = sr_community_extra_field_type(sr_community_extra_field_scalar_string($item['type'] ?? 'text'));
        $options = [];
        if ($type === 'select') {
            $rawOptions = is_array($item['options'] ?? null) ? $item['options'] : [];
            foreach ($rawOptions as $option) {
                $option = trim(preg_replace('/\s+/', ' ', sr_community_extra_field_scalar_string($option)) ?? '');
                $option = function_exists('mb_substr') ? mb_substr($option, 0, 120) : substr($option, 0, 120);
                if ($option !== '' && !in_array($option, $options, true)) {
                    $options[] = $option;
                }
                if (count($options) >= 50) {
                    break;
                }
            }
            if ($options === []) {
                continue;
            }
        }

        $definitions[] = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => !empty($item['required']),
            'options' => $options,
            'visibility' => in_array(sr_community_extra_field_scalar_string($item['visibility'] ?? 'public'), ['public', 'admin'], true) ? sr_community_extra_field_scalar_string($item['visibility'] ?? 'public') : 'public',
            'show_on_view' => array_key_exists('show_on_view', $item) ? !empty($item['show_on_view']) : true,
            'show_in_admin' => !empty($item['show_in_admin']),
            'privacy_purpose' => trim(sr_community_extra_field_scalar_string($item['privacy_purpose'] ?? '')),
            'export_policy' => in_array(sr_community_extra_field_scalar_string($item['export_policy'] ?? 'include'), ['include', 'exclude'], true) ? sr_community_extra_field_scalar_string($item['export_policy'] ?? 'include') : 'include',
            'cleanup_policy' => in_array(sr_community_extra_field_scalar_string($item['cleanup_policy'] ?? 'anonymize'), ['anonymize', 'retain'], true) ? sr_community_extra_field_scalar_string($item['cleanup_policy'] ?? 'anonymize') : 'anonymize',
        ];
        $seenKeys[$key] = true;
    }

    return $definitions;
}

function sr_community_extra_field_definition_validation_errors(mixed $raw): array
{
    $errors = [];
    if (!is_array($raw)) {
        return ['추가 입력 항목 JSON은 배열이어야 합니다.'];
    }
    if (count($raw) > 20) {
        $errors[] = '추가 입력 항목은 20개 이하로 입력해 주세요.';
    }

    $seenKeys = [];
    foreach ($raw as $index => $item) {
        $rowLabel = '추가 입력 항목 #' . (string) ((int) $index + 1);
        if (!is_array($item)) {
            $errors[] = $rowLabel . ' 형식이 올바르지 않습니다.';
            continue;
        }

        $keyRaw = $item['key'] ?? '';
        $key = strtolower(trim(sr_community_extra_field_scalar_string($keyRaw)));
        if (!is_scalar($keyRaw)) {
            $errors[] = $rowLabel . '의 관리용 키 형식이 올바르지 않습니다.';
        }
        if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) !== 1) {
            $errors[] = $rowLabel . '의 관리용 키는 영문 소문자로 시작하고 소문자, 숫자, _만 사용할 수 있습니다.';
        } elseif (isset($seenKeys[$key])) {
            $errors[] = $rowLabel . '의 관리용 키가 중복되었습니다.';
        }
        if ($key !== '') {
            $seenKeys[$key] = true;
        }

        $labelRaw = $item['label'] ?? '';
        $label = trim(preg_replace('/\s+/', ' ', sr_community_extra_field_scalar_string($labelRaw)) ?? '');
        $labelLength = function_exists('mb_strlen') ? mb_strlen($label) : strlen($label);
        if (!is_scalar($labelRaw)) {
            $errors[] = $rowLabel . '의 라벨 형식이 올바르지 않습니다.';
        } elseif ($label === '') {
            $errors[] = $rowLabel . '의 라벨을 입력해 주세요.';
        } elseif ($labelLength > 120) {
            $errors[] = $rowLabel . '의 라벨은 120자 이하로 입력해 주세요.';
        }

        $typeRaw = $item['type'] ?? 'text';
        $type = sr_community_extra_field_scalar_string($typeRaw);
        if (!is_scalar($typeRaw)) {
            $errors[] = $rowLabel . '의 유형 형식이 올바르지 않습니다.';
        }
        if (!in_array($type, ['text', 'textarea', 'select', 'checkbox'], true)) {
            $errors[] = $rowLabel . '의 유형 값이 올바르지 않습니다.';
        }

        $visibilityRaw = $item['visibility'] ?? 'public';
        $visibility = sr_community_extra_field_scalar_string($visibilityRaw);
        if (!is_scalar($visibilityRaw)) {
            $errors[] = $rowLabel . '의 공개 범위 형식이 올바르지 않습니다.';
        }
        if (!in_array($visibility, ['public', 'admin'], true)) {
            $errors[] = $rowLabel . '의 공개 범위 값이 올바르지 않습니다.';
        }

        $privacyPurposeRaw = $item['privacy_purpose'] ?? '';
        $privacyPurpose = trim(sr_community_extra_field_scalar_string($privacyPurposeRaw));
        $privacyPurposeLength = function_exists('mb_strlen') ? mb_strlen($privacyPurpose) : strlen($privacyPurpose);
        if (!is_scalar($privacyPurposeRaw)) {
            $errors[] = $rowLabel . '의 개인정보 목적 형식이 올바르지 않습니다.';
        } elseif ($privacyPurposeLength > 255) {
            $errors[] = $rowLabel . '의 개인정보 목적은 255자 이하로 입력해 주세요.';
        }

        $exportPolicyRaw = $item['export_policy'] ?? 'include';
        $exportPolicy = sr_community_extra_field_scalar_string($exportPolicyRaw);
        if (!is_scalar($exportPolicyRaw)) {
            $errors[] = $rowLabel . '의 export 정책 형식이 올바르지 않습니다.';
        }
        if (!in_array($exportPolicy, ['include', 'exclude'], true)) {
            $errors[] = $rowLabel . '의 export 정책 값이 올바르지 않습니다.';
        }

        $cleanupPolicyRaw = $item['cleanup_policy'] ?? 'anonymize';
        $cleanupPolicy = sr_community_extra_field_scalar_string($cleanupPolicyRaw);
        if (!is_scalar($cleanupPolicyRaw)) {
            $errors[] = $rowLabel . '의 cleanup 정책 형식이 올바르지 않습니다.';
        }
        if (!in_array($cleanupPolicy, ['anonymize', 'retain'], true)) {
            $errors[] = $rowLabel . '의 cleanup 정책 값이 올바르지 않습니다.';
        }

        if ($type === 'select') {
            if (!is_array($item['options'] ?? null)) {
                $errors[] = $rowLabel . '의 선택지는 배열이어야 합니다.';
                continue;
            }
            $options = [];
            foreach ((array) $item['options'] as $optionIndex => $option) {
                if (!is_scalar($option)) {
                    $errors[] = $rowLabel . '의 선택지 #' . (string) ((int) $optionIndex + 1) . ' 형식이 올바르지 않습니다.';
                    continue;
                }
                $optionValue = trim(preg_replace('/\s+/', ' ', (string) $option) ?? '');
                $optionLength = function_exists('mb_strlen') ? mb_strlen($optionValue) : strlen($optionValue);
                if ($optionValue === '') {
                    $errors[] = $rowLabel . '의 선택지는 빈 값으로 저장할 수 없습니다.';
                    continue;
                }
                if ($optionLength > 120) {
                    $errors[] = $rowLabel . '의 선택지는 120자 이하로 입력해 주세요.';
                }
                if (in_array($optionValue, $options, true)) {
                    $errors[] = $rowLabel . '의 선택지가 중복되었습니다.';
                }
                $options[] = $optionValue;
            }
            if ($options === []) {
                $errors[] = $rowLabel . '의 선택지는 하나 이상 입력해 주세요.';
            }
            if (count($options) > 50) {
                $errors[] = $rowLabel . '의 선택지는 50개 이하로 입력해 주세요.';
            }
        }
    }

    return $errors;
}

function sr_community_extra_field_definitions_from_storage(PDO $pdo, int $boardId): array
{
    if ($boardId < 1 || !sr_community_board_field_definitions_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT field_key, label, field_type, is_required, visibility, show_on_view, show_in_admin, sort_order, validation_json, privacy_purpose, export_policy, cleanup_policy
         FROM sr_community_board_field_definitions
         WHERE board_id = :board_id
           AND status = \'enabled\'
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['board_id' => $boardId]);

    $definitions = [];
    foreach ($stmt->fetchAll() as $row) {
        $validation = json_decode((string) ($row['validation_json'] ?? ''), true);
        $definitions[] = [
            'key' => (string) ($row['field_key'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'type' => (string) ($row['field_type'] ?? 'text'),
            'required' => (int) ($row['is_required'] ?? 0) === 1,
            'options' => is_array($validation['options'] ?? null) ? $validation['options'] : [],
            'visibility' => (string) ($row['visibility'] ?? 'public'),
            'show_on_view' => (int) ($row['show_on_view'] ?? 1) === 1,
            'show_in_admin' => (int) ($row['show_in_admin'] ?? 0) === 1,
            'privacy_purpose' => (string) ($row['privacy_purpose'] ?? ''),
            'export_policy' => (string) ($row['export_policy'] ?? 'include'),
            'cleanup_policy' => (string) ($row['cleanup_policy'] ?? 'anonymize'),
        ];
    }

    return sr_community_normalize_extra_field_definitions($definitions);
}

function sr_community_extra_field_definitions_from_json(string $json): array
{
    $json = trim($json);
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    return sr_community_normalize_extra_field_definitions($decoded);
}

function sr_community_extra_field_definitions_json_from_input(string $json): ?string
{
    $json = trim($json);
    if ($json === '') {
        return '[]';
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return null;
    }
    if (sr_community_extra_field_definition_validation_errors($decoded) !== []) {
        return null;
    }

    return json_encode(sr_community_normalize_extra_field_definitions($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function sr_community_extra_field_definitions_input_errors(?string $json): array
{
    if (!is_string($json)) {
        return ['추가 입력 항목 JSON은 20000자 이하로 입력해 주세요.'];
    }

    $json = trim($json);
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return ['추가 입력 항목 JSON 형식을 확인해 주세요.'];
    }

    return sr_community_extra_field_definition_validation_errors($decoded);
}

function sr_community_board_extra_field_definitions(PDO $pdo, array $board): array
{
    $boardId = (int) ($board['id'] ?? 0);
    if ($boardId > 0 && sr_community_board_setting_source($pdo, $boardId, 'extra_fields_json') === 'board') {
        $stored = sr_community_extra_field_definitions_from_storage($pdo, $boardId);
        if ($stored !== []) {
            return $stored;
        }
    }

    $json = sr_community_effective_board_setting($pdo, $board, 'extra_fields_json', '[]');
    return sr_community_extra_field_definitions_from_json($json);
}

function sr_community_sync_board_field_definitions(PDO $pdo, int $boardId, array $definitions): void
{
    if ($boardId < 1 || !sr_community_board_field_definitions_table_exists($pdo)) {
        return;
    }

    $now = sr_now();
    $pdo->prepare(
        'UPDATE sr_community_board_field_definitions
         SET status = \'disabled\', updated_at = :updated_at
         WHERE board_id = :board_id'
    )->execute([
        'board_id' => $boardId,
        'updated_at' => $now,
    ]);

    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $insertSql = $isSqlite
        ? 'INSERT INTO sr_community_board_field_definitions
            (board_id, field_key, label, field_type, is_required, visibility, show_on_view, show_in_admin, sort_order, validation_json, privacy_purpose, export_policy, cleanup_policy, status, created_at, updated_at)
         VALUES
            (:board_id, :field_key, :label, :field_type, :is_required, :visibility, :show_on_view, :show_in_admin, :sort_order, :validation_json, :privacy_purpose, :export_policy, :cleanup_policy, \'enabled\', :created_at, :updated_at)
         ON CONFLICT(board_id, field_key) DO UPDATE SET
            label = excluded.label,
            field_type = excluded.field_type,
            is_required = excluded.is_required,
            visibility = excluded.visibility,
            show_on_view = excluded.show_on_view,
            show_in_admin = excluded.show_in_admin,
            sort_order = excluded.sort_order,
            validation_json = excluded.validation_json,
            privacy_purpose = excluded.privacy_purpose,
            export_policy = excluded.export_policy,
            cleanup_policy = excluded.cleanup_policy,
            status = \'enabled\',
            updated_at = excluded.updated_at'
        : 'INSERT INTO sr_community_board_field_definitions
            (board_id, field_key, label, field_type, is_required, visibility, show_on_view, show_in_admin, sort_order, validation_json, privacy_purpose, export_policy, cleanup_policy, status, created_at, updated_at)
         VALUES
            (:board_id, :field_key, :label, :field_type, :is_required, :visibility, :show_on_view, :show_in_admin, :sort_order, :validation_json, :privacy_purpose, :export_policy, :cleanup_policy, \'enabled\', :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            label = VALUES(label),
            field_type = VALUES(field_type),
            is_required = VALUES(is_required),
            visibility = VALUES(visibility),
            show_on_view = VALUES(show_on_view),
            show_in_admin = VALUES(show_in_admin),
            sort_order = VALUES(sort_order),
            validation_json = VALUES(validation_json),
            privacy_purpose = VALUES(privacy_purpose),
            export_policy = VALUES(export_policy),
            cleanup_policy = VALUES(cleanup_policy),
            status = \'enabled\',
            updated_at = VALUES(updated_at)';
    $stmt = $pdo->prepare($insertSql);

    foreach (array_values($definitions) as $index => $definition) {
        $stmt->execute([
            'board_id' => $boardId,
            'field_key' => (string) ($definition['key'] ?? ''),
            'label' => (string) ($definition['label'] ?? ''),
            'field_type' => (string) ($definition['type'] ?? 'text'),
            'is_required' => !empty($definition['required']) ? 1 : 0,
            'visibility' => (string) ($definition['visibility'] ?? 'public'),
            'show_on_view' => !empty($definition['show_on_view']) ? 1 : 0,
            'show_in_admin' => !empty($definition['show_in_admin']) ? 1 : 0,
            'sort_order' => ($index + 1) * 10,
            'validation_json' => json_encode(['options' => array_values((array) ($definition['options'] ?? []))], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'privacy_purpose' => (string) ($definition['privacy_purpose'] ?? ''),
            'export_policy' => (string) ($definition['export_policy'] ?? 'include'),
            'cleanup_policy' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function sr_community_sync_group_board_field_definitions(PDO $pdo, int $groupId, array $definitions): int
{
    if ($groupId < 1
        || !sr_community_board_field_definitions_table_exists($pdo)
        || !sr_community_optional_table_exists($pdo, 'sr_community_board_setting_sources')) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT b.id
         FROM sr_community_boards b
         INNER JOIN sr_community_board_setting_sources s
            ON s.board_id = b.id
           AND s.setting_key = \'extra_fields_json\'
           AND s.source = \'group\'
         WHERE b.board_group_id = :group_id
         ORDER BY b.id ASC'
    );
    $stmt->execute(['group_id' => $groupId]);
    $boardIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    foreach ($boardIds as $boardId) {
        sr_community_sync_board_field_definitions($pdo, (int) $boardId, $definitions);
    }

    return count($boardIds);
}

function sr_community_extra_field_input_values(array $definitions): array
{
    $posted = $_POST['community_extra_fields'] ?? [];
    $posted = is_array($posted) ? $posted : [];
    $values = [];
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        $type = (string) ($definition['type'] ?? 'text');
        if ($type === 'checkbox') {
            if (isset($posted[$key]) && !is_scalar($posted[$key])) {
                $values[$key] = ['invalid_extra_field_value' => true];
                continue;
            }
            $values[$key] = isset($posted[$key]) && (string) $posted[$key] === '1' ? '1' : '0';
            continue;
        }
        $value = $posted[$key] ?? '';
        $values[$key] = is_scalar($value) ? trim((string) $value) : ['invalid_extra_field_value' => true];
    }

    return $values;
}

function sr_community_extra_field_value_max_length(string $type): int
{
    return $type === 'textarea' ? 5000 : 1000;
}

function sr_community_validate_extra_field_values(array $definitions, array $values): array
{
    $errors = [];
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        $label = (string) ($definition['label'] ?? $key);
        $type = (string) ($definition['type'] ?? 'text');
        $rawValue = $values[$key] ?? '';
        if (is_array($rawValue)) {
            $errors[] = $label . ' 값 형식이 올바르지 않습니다.';
            continue;
        }
        $value = (string) $rawValue;
        if (!empty($definition['required'])) {
            if ($type === 'checkbox') {
                if ($value !== '1') {
                    $errors[] = $label . '을(를) 확인해 주세요.';
                }
            } elseif (trim($value) === '') {
                $errors[] = $label . '을(를) 입력해 주세요.';
            }
        }
        if ($type !== 'checkbox') {
            $maxLength = sr_community_extra_field_value_max_length($type);
            $valueLength = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            if ($valueLength > $maxLength) {
                $errors[] = $label . '은(는) ' . (string) $maxLength . '자 이하로 입력해 주세요.';
            }
        }
        if ($type === 'select' && $value !== '' && !in_array($value, (array) ($definition['options'] ?? []), true)) {
            $errors[] = $label . ' 선택 값이 올바르지 않습니다.';
        }
        if ($type === 'checkbox' && !in_array($value, ['0', '1'], true)) {
            $errors[] = $label . ' 값이 올바르지 않습니다.';
        }
    }

    return $errors;
}

function sr_community_extra_field_values_json(array $definitions, array $values): string
{
    $stored = [];
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        if ($key === '') {
            continue;
        }
        $rawValue = $values[$key] ?? '';
        $value = is_array($rawValue) ? '' : (string) $rawValue;
        $stored[$key] = [
            'label' => (string) ($definition['label'] ?? $key),
            'type' => (string) ($definition['type'] ?? 'text'),
            'value' => $value,
            'visibility' => (string) ($definition['visibility'] ?? 'public'),
            'show_on_view' => !empty($definition['show_on_view']),
            'show_in_admin' => !empty($definition['show_in_admin']),
            'export_policy' => (string) ($definition['export_policy'] ?? 'include'),
            'cleanup_policy' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
        ];
    }

    return json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function sr_community_extra_field_values_export_json(string $json): string
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return '[]';
    }

    foreach ($decoded as $key => $item) {
        if (!is_array($item)) {
            unset($decoded[$key]);
            continue;
        }
        $exportPolicy = (string) ($item['export_policy'] ?? 'include');
        if ($exportPolicy !== 'include') {
            unset($decoded[$key]);
        }
    }

    return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function sr_community_extra_field_values_cleanup_json(string $json): string
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return '[]';
    }

    foreach ($decoded as &$item) {
        if (!is_array($item)) {
            continue;
        }
        $cleanupPolicy = (string) ($item['cleanup_policy'] ?? 'anonymize');
        if ($cleanupPolicy !== 'retain') {
            $item['value'] = '';
        }
    }
    unset($item);

    return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function sr_community_save_post_field_values(PDO $pdo, int $postId, array $definitions, array $values): void
{
    if ($postId < 1 || !sr_community_post_field_values_table_exists($pdo)) {
        return;
    }

    $now = sr_now();
    $pdo->prepare('DELETE FROM sr_community_post_field_values WHERE post_id = :post_id')->execute(['post_id' => $postId]);
    if ($definitions === []) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_post_field_values
            (post_id, field_key, label_snapshot, field_type_snapshot, visibility_snapshot, show_on_view_snapshot, show_in_admin_snapshot, privacy_purpose_snapshot, export_policy_snapshot, cleanup_policy_snapshot, value_text, value_json, created_at, updated_at)
         VALUES
            (:post_id, :field_key, :label_snapshot, :field_type_snapshot, :visibility_snapshot, :show_on_view_snapshot, :show_in_admin_snapshot, :privacy_purpose_snapshot, :export_policy_snapshot, :cleanup_policy_snapshot, :value_text, :value_json, :created_at, :updated_at)'
    );
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        if ($key === '') {
            continue;
        }
        $rawValue = $values[$key] ?? '';
        $value = is_array($rawValue) ? '' : (string) $rawValue;
        $stmt->execute([
            'post_id' => $postId,
            'field_key' => $key,
            'label_snapshot' => (string) ($definition['label'] ?? $key),
            'field_type_snapshot' => (string) ($definition['type'] ?? 'text'),
            'visibility_snapshot' => (string) ($definition['visibility'] ?? 'public'),
            'show_on_view_snapshot' => !empty($definition['show_on_view']) ? 1 : 0,
            'show_in_admin_snapshot' => !empty($definition['show_in_admin']) ? 1 : 0,
            'privacy_purpose_snapshot' => (string) ($definition['privacy_purpose'] ?? ''),
            'export_policy_snapshot' => (string) ($definition['export_policy'] ?? 'include'),
            'cleanup_policy_snapshot' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
            'value_text' => $value,
            'value_json' => json_encode([
                'value' => $value,
                'visibility' => (string) ($definition['visibility'] ?? 'public'),
                'show_on_view' => !empty($definition['show_on_view']),
                'show_in_admin' => !empty($definition['show_in_admin']),
                'privacy_purpose' => (string) ($definition['privacy_purpose'] ?? ''),
                'export_policy' => (string) ($definition['export_policy'] ?? 'include'),
                'cleanup_policy' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function sr_community_redact_post_field_values(PDO $pdo, int $postId): void
{
    if ($postId < 1 || !sr_community_post_field_values_table_exists($pdo)) {
        return;
    }

    $pdo->prepare(
        "UPDATE sr_community_post_field_values
         SET value_text = '',
             value_json = NULL,
             updated_at = :updated_at
         WHERE post_id = :post_id"
    )->execute([
        'post_id' => $postId,
        'updated_at' => sr_now(),
    ]);
}

function sr_community_extra_field_values_from_json(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function sr_community_extra_fields_form_html(array $definitions, array $values = []): string
{
    if ($definitions === []) {
        return '';
    }

    $html = '<fieldset class="community-extra-fields">';
    $html .= '<legend>' . sr_e('추가 입력') . '</legend>';
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        $label = (string) ($definition['label'] ?? $key);
        $type = (string) ($definition['type'] ?? 'text');
        $required = !empty($definition['required']);
        $id = 'modules_community_extra_' . $key;
        $name = 'community_extra_fields[' . $key . ']';
        $rawValue = $values[$key] ?? '';
        $value = is_array($rawValue) ? '' : (string) $rawValue;
        $html .= '<p><label for="' . sr_e($id) . '"><span>' . sr_e($label) . ($required ? ' <span class="sr-required-label">' . sr_e(sr_t('community::ui.required.1f227c67')) . '</span>' : '') . '</span>';
        if ($type === 'textarea') {
            $html .= '<textarea id="' . sr_e($id) . '" name="' . sr_e($name) . '" rows="4" cols="80" maxlength="5000"' . ($required ? ' required' : '') . '>' . sr_e($value) . '</textarea>';
        } elseif ($type === 'select') {
            $html .= '<select id="' . sr_e($id) . '" name="' . sr_e($name) . '"' . ($required ? ' required' : '') . '>';
            $html .= '<option value="">' . sr_e('선택') . '</option>';
            foreach ((array) ($definition['options'] ?? []) as $option) {
                $option = (string) $option;
                $html .= '<option value="' . sr_e($option) . '"' . ($value === $option ? ' selected' : '') . '>' . sr_e($option) . '</option>';
            }
            $html .= '</select>';
        } elseif ($type === 'checkbox') {
            $html .= '<input id="' . sr_e($id) . '" type="checkbox" name="' . sr_e($name) . '" value="1"' . ($value === '1' ? ' checked' : '') . ($required ? ' required' : '') . '>';
        } else {
            $html .= '<input id="' . sr_e($id) . '" type="text" name="' . sr_e($name) . '" maxlength="1000" value="' . sr_e($value) . '"' . ($required ? ' required' : '') . '>';
        }
        $html .= '</label></p>';
    }
    $html .= '</fieldset>';

    return $html;
}

function sr_community_extra_fields_display_html(array $values): string
{
    if ($values === []) {
        return '';
    }

    $html = '<dl class="community-extra-field-values">';
    foreach ($values as $item) {
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string) ($item['label'] ?? ''));
        $value = (string) ($item['value'] ?? '');
        $showOnView = array_key_exists('show_on_view', $item) ? !empty($item['show_on_view']) : true;
        if ($label === '' || $value === '' || !$showOnView || (string) ($item['visibility'] ?? 'public') === 'admin') {
            continue;
        }
        $displayValue = (string) ($item['type'] ?? '') === 'checkbox' ? ($value === '1' ? '예' : '아니오') : $value;
        $html .= '<dt>' . sr_e($label) . '</dt><dd>' . nl2br(sr_e($displayValue)) . '</dd>';
    }
    $html .= '</dl>';

    return $html;
}

function sr_community_extra_fields_admin_summary_html(array $values): string
{
    if ($values === []) {
        return '';
    }

    $html = '<dl class="admin-community-extra-field-values">';
    foreach ($values as $item) {
        if (!is_array($item) || empty($item['show_in_admin'])) {
            continue;
        }

        $label = trim((string) ($item['label'] ?? ''));
        $value = (string) ($item['value'] ?? '');
        if ($label === '' || $value === '') {
            continue;
        }

        $displayValue = (string) ($item['type'] ?? '') === 'checkbox' ? ($value === '1' ? '예' : '아니오') : $value;
        $html .= '<dt>' . sr_e($label) . '</dt><dd>' . nl2br(sr_e($displayValue)) . '</dd>';
    }
    $html .= '</dl>';

    return $html === '<dl class="admin-community-extra-field-values"></dl>' ? '' : $html;
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
    $stmt = $pdo->prepare(
        "SELECT p.id, p.board_id, " . $categorySelectSql . ", p.author_account_id, " . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_posts', 'p') . sr_community_post_extra_values_select($pdo, 'p') . ", author.status AS author_account_status, p.title, p.body_text, p.body_format, p.seo_title, p.seo_description, p.og_title, p.og_description, p.og_image_attachment_id, " . $secretPostSelectSql . " p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
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
    $stmt = $pdo->prepare(
        "SELECT p.id, p.board_id, " . $categorySelectSql . ", p.author_account_id, " . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_posts', 'p') . sr_community_post_extra_values_select($pdo, 'p') . ", author.status AS author_account_status, p.title, p.body_text, p.body_format, p.seo_title, p.seo_description, p.og_title, p.og_description, p.og_image_attachment_id, " . $secretPostSelectSql . " p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
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

function sr_community_admin_comment_status_display_order(): array
{
    return ['published', 'hidden', 'deleted'];
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
            $where[] = '(p.title LIKE :title_keyword OR a.display_name LIKE :author_keyword OR (a.status NOT IN (\'withdrawn\', \'anonymized\') AND author_nickname.nickname LIKE :author_nickname_keyword) OR b.title LIKE :board_title_keyword OR b.board_key LIKE :board_key_keyword' . $extraValuesCondition . ')';
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

    $secretCondition = sr_community_post_secret_column_exists($pdo) ? 'AND p.is_secret = 0' : '';
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.body_text, p.status, p.updated_at,
                b.board_key, b.title AS board_title
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         WHERE p.status = \'published\'
           AND b.status = \'enabled\'
           AND b.read_policy = \'public\'
           ' . $secretCondition . '
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
            'embed' => sr_embed_manager_search_payload('community', 'post', $postId, (string) ($row['title'] ?? ''), 'card'),
            'status' => (string) ($row['status'] ?? ''),
            'meta' => '게시글 #' . $postId . ' / 게시판: ' . (string) ($row['board_title'] ?? '') . ' (' . (string) ($row['board_key'] ?? '') . ')',
        ];
    }, $stmt->fetchAll());
}

function sr_community_update_post_status(PDO $pdo, int $postId, string $status, array $options = []): void
{
    if ($status === 'deleted') {
        sr_community_redact_deleted_post($pdo, $postId);
        sr_community_cleanup_body_files_for_deleted_posts($pdo, [$postId]);
        return;
    }

    if (sr_community_hidden_columns_exist($pdo, 'sr_community_posts')) {
        sr_community_update_status_with_hidden_metadata($pdo, 'sr_community_posts', $postId, $status, $options);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_posts
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id
           AND status <> \'deleted\''
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $postId,
    ]);
}

function sr_community_update_status_with_hidden_metadata(PDO $pdo, string $tableName, int $id, string $status, array $options = []): void
{
    if ($id < 1 || !in_array($tableName, ['sr_community_posts', 'sr_community_comments'], true)) {
        return;
    }

    $now = sr_now();
    if ($status === 'hidden') {
        $stmt = $pdo->prepare(
            'UPDATE ' . $tableName . '
             SET status = :status,
                 hidden_at = :hidden_at,
                 hidden_until = :hidden_until,
                 hidden_reason = :hidden_reason,
                 hidden_note = :hidden_note,
                 hidden_by_account_id = :hidden_by_account_id,
                 hidden_before_status = CASE WHEN status <> \'hidden\' THEN status ELSE hidden_before_status END,
                 updated_at = :updated_at
             WHERE id = :id
               AND status <> \'deleted\''
        );
        $stmt->execute([
            'status' => $status,
            'hidden_at' => $now,
            'hidden_until' => $options['hidden_until'] ?? null,
            'hidden_reason' => (string) ($options['hidden_reason'] ?? ''),
            'hidden_note' => (string) ($options['hidden_note'] ?? ''),
            'hidden_by_account_id' => isset($options['hidden_by_account_id']) && (int) $options['hidden_by_account_id'] > 0 ? (int) $options['hidden_by_account_id'] : null,
            'updated_at' => $now,
            'id' => $id,
        ]);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE ' . $tableName . '
         SET status = :status,
             hidden_at = NULL,
             hidden_until = NULL,
             hidden_reason = \'\',
             hidden_note = NULL,
             hidden_by_account_id = NULL,
             hidden_before_status = \'\',
             updated_at = :updated_at
         WHERE id = :id
           AND status <> \'deleted\''
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => $now,
        'id' => $id,
    ]);
}

function sr_community_redact_deleted_post(PDO $pdo, int $postId): void
{
    if ($postId < 1) {
        return;
    }

    $now = sr_now();
    $guestRedactionSql = sr_community_guest_author_columns_exist($pdo, 'sr_community_posts')
        ? "guest_author_name = '',
             guest_password_hash = NULL,
             guest_ip_hash = NULL,
             guest_user_agent_hash = NULL,"
        : '';
    $extraValuesRedactionSql = sr_community_post_extra_values_column_exists($pdo) ? "extra_values_json = '[]'," : '';
    $stmt = $pdo->prepare(
        "UPDATE sr_community_posts
         SET status = 'deleted',
             title = :title,
             body_text = '',
             body_format = 'plain',
             author_public_name_snapshot = '',
             " . $guestRedactionSql . "
             " . $extraValuesRedactionSql . "
             seo_title = '',
             seo_description = '',
             og_title = '',
             og_description = '',
             og_image_attachment_id = NULL,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'title' => sr_t('community::redaction.deleted_post_title'),
        'updated_at' => $now,
        'id' => $postId,
    ]);
    sr_community_redact_post_field_values($pdo, $postId);

    $pdo->prepare('DELETE FROM sr_community_link_refs WHERE post_id = :post_id')->execute(['post_id' => $postId]);
    if (function_exists('sr_embed_manager_sync_body_refs')) {
        sr_embed_manager_sync_body_refs($pdo, 'community', 'post', $postId, 'body', '', null);
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
        $extraValuesSetSql = sr_community_post_extra_values_column_exists($pdo) ? 'extra_values_json = :extra_values_json,' : '';
        $secretSetSql = sr_community_post_secret_column_exists($pdo) ? 'is_secret = :is_secret,' : '';
        $stmt = $pdo->prepare(
            'UPDATE sr_community_posts
             SET ' . $categorySetSql . '
                 ' . $extraValuesSetSql . '
                 title = :title,
                 body_text = :body_text,
                 body_format = :body_format,
                 seo_title = :seo_title,
                 seo_description = :seo_description,
                 og_title = :og_title,
                 og_description = :og_description,
                 ' . $secretSetSql . '
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
        if ($extraValuesSetSql !== '') {
            $params['extra_values_json'] = (string) ($values['extra_values_json'] ?? '[]');
        }
        if ($secretSetSql !== '') {
            $params['is_secret'] = (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0;
        }
        $stmt->execute($params);
        if ($bodyFormat === 'html') {
            sr_embed_manager_sync_body_refs($pdo, 'community', 'post', $postId, 'body', $bodyText, $accountId > 0 ? $accountId : null);
        } else {
            sr_embed_manager_sync_body_refs($pdo, 'community', 'post', $postId, 'body', '', $accountId > 0 ? $accountId : null);
        }
        sr_community_save_post_field_values(
            $pdo,
            $postId,
            is_array($values['extra_field_definitions'] ?? null) ? $values['extra_field_definitions'] : [],
            is_array($values['extra_field_values'] ?? null) ? $values['extra_field_values'] : []
        );
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

function sr_community_guest_can_edit_post(array $post, string $password): bool
{
    return (int) ($post['author_account_id'] ?? 0) < 1
        && (string) ($post['status'] ?? '') === 'published'
        && sr_community_guest_password_verified($post, $password);
}

function sr_community_guest_can_delete_post(array $post, string $password): bool
{
    return sr_community_guest_can_edit_post($post, $password);
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

function sr_community_account_can_write_board(PDO $pdo, array $board, ?array $account, bool $isAdminWriter = false): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    if ((string) ($board['status'] ?? '') !== 'enabled') {
        return false;
    }

    $policy = sr_community_effective_board_policy($pdo, $board, 'write_policy');
    if ($policy === 'guest') {
        return true;
    }

    if ($accountId < 1) {
        return false;
    }

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
        'seo_title' => '',
        'seo_description' => '',
        'og_title' => '',
        'og_description' => '',
        'is_secret' => sr_post_string('is_secret', 10) === '1'
            && $pdo instanceof PDO
            && is_array($board)
            && sr_community_effective_board_secret_posts_enabled($pdo, $board, $settings) ? 1 : 0,
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
    $guestAuthorColumnSql = sr_community_guest_author_columns_exist($pdo, 'sr_community_posts') ? 'guest_author_name, guest_password_hash, guest_ip_hash, guest_user_agent_hash, ' : '';
    $guestAuthorValueSql = $guestAuthorColumnSql !== '' ? ':guest_author_name, :guest_password_hash, :guest_ip_hash, :guest_user_agent_hash, ' : '';
    $extraValuesColumnSql = sr_community_post_extra_values_column_exists($pdo) ? 'extra_values_json, ' : '';
    $extraValuesValueSql = $extraValuesColumnSql !== '' ? ':extra_values_json, ' : '';
    $secretColumnSql = sr_community_post_secret_column_exists($pdo) ? 'is_secret, ' : '';
    $secretValueSql = $secretColumnSql !== '' ? ':is_secret, ' : '';
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (board_id, ' . $categoryColumnSql . 'author_account_id, ' . $authorSnapshotColumnSql . $guestAuthorColumnSql . $extraValuesColumnSql . 'title, body_text, body_format, seo_title, seo_description, og_title, og_description, ' . $secretColumnSql . 'status, view_count, last_commented_at, created_at, updated_at)
         VALUES
            (:board_id, ' . $categoryValueSql . ':author_account_id, ' . $authorSnapshotValueSql . $guestAuthorValueSql . $extraValuesValueSql . ':title, :body_text, :body_format, :seo_title, :seo_description, :og_title, :og_description, ' . $secretValueSql . ':status, 0, NULL, :created_at, :updated_at)'
    );
    $params = [
        'board_id' => $boardId,
        'author_account_id' => $authorAccountId > 0 ? $authorAccountId : null,
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
    if ($extraValuesColumnSql !== '') {
        $params['extra_values_json'] = (string) ($values['extra_values_json'] ?? '[]');
    }
    if ($secretColumnSql !== '') {
        $params['is_secret'] = (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0;
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
                $bodyText = $finalBodyText;
                $pdo->prepare('UPDATE sr_community_posts SET body_text = :body_text, updated_at = :updated_at WHERE id = :id')->execute([
                    'body_text' => $finalBodyText,
                    'updated_at' => $now,
                    'id' => $postId,
                ]);
            }
            sr_embed_manager_sync_body_refs($pdo, 'community', 'post', $postId, 'body', $bodyText, $authorAccountId);
        } else {
            sr_embed_manager_sync_body_refs($pdo, 'community', 'post', $postId, 'body', '', $authorAccountId);
        }
        sr_community_save_post_field_values(
            $pdo,
            $postId,
            is_array($values['extra_field_definitions'] ?? null) ? $values['extra_field_definitions'] : [],
            is_array($values['extra_field_values'] ?? null) ? $values['extra_field_values'] : []
        );
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

function sr_community_guest_post_rate_limited(PDO $pdo, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    $limit = min(100, max(1, (int) ($settings['post_create_limit'] ?? 10)));

    return sr_community_rate_limits_table_exists($pdo)
        && sr_rate_limit_count($pdo, 'community.post.guest', sr_community_guest_rate_limit_identifier(), $windowSeconds) >= $limit;
}

function sr_community_record_guest_post_rate_limit(PDO $pdo, array $settings): void
{
    if (!sr_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    sr_rate_limit_increment($pdo, 'community.post.guest', sr_community_guest_rate_limit_identifier(), $windowSeconds);
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
    $secretSelectSql = sr_community_post_secret_column_exists($pdo) ? 'p.is_secret,' : '0 AS is_secret,';
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.body_text, ' . $secretSelectSql . ' p.status, b.status AS board_status, b.read_policy
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
            && (string) ($row['read_policy'] ?? 'public') === 'public'
            && (int) ($row['is_secret'] ?? 0) !== 1;
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
    return sr_rich_text_allowed_html_tags();
}

function sr_community_sanitize_post_html(string $html): string
{
    return sr_sanitize_rich_text_html($html);
}
