<?php

declare(strict_types=1);

function sr_community_admin_can_view_member_identifiers(PDO $pdo, ?array $account): bool
{
    if (!is_array($account) || !function_exists('sr_admin_has_permission')) {
        return false;
    }

    return sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'view')
        || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/comments', 'view')
        || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/reports', 'view')
        || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/members', 'view');
}

function sr_community_member_identifier_suffix(array $config, int $accountId, bool $showIdentifier): string
{
    return '';
}

function sr_community_member_label_with_identifier(string $label, array $config, int $accountId, bool $showIdentifier): string
{
    return $label . sr_community_member_identifier_suffix($config, $accountId, $showIdentifier);
}

function sr_community_public_display_name(array $account, ?array $settings = null): string
{
    $settings = is_array($settings) ? array_merge(sr_member_default_settings(), $settings) : sr_member_default_settings();
    $account['nickname'] = (string) ($account['nickname'] ?? $account['member_nickname'] ?? $account['community_nickname'] ?? '');

    return sr_member_public_name($account, $settings, sr_t('community::report.account.member'));
}

function sr_community_nickname_status_blocks_identity(string $status): bool
{
    return in_array($status, ['withdrawn', 'anonymized'], true);
}

function sr_community_member_nickname(PDO $pdo, int $accountId): string
{
    if ($accountId < 1) {
        return '';
    }

    if (!isset($GLOBALS['sr_community_member_nickname_cache']) || !is_array($GLOBALS['sr_community_member_nickname_cache'])) {
        $GLOBALS['sr_community_member_nickname_cache'] = [];
    }

    $cache = &$GLOBALS['sr_community_member_nickname_cache'];
    $cacheKey = (string) spl_object_id($pdo) . ':' . (string) $accountId;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = $pdo->prepare(
        'SELECT nickname
         FROM sr_member_nicknames
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();
    $cache[$cacheKey] = is_array($row) ? (string) ($row['nickname'] ?? '') : '';

    return $cache[$cacheKey];
}

function sr_community_set_member_nickname(PDO $pdo, int $accountId, string $nickname): void
{
    if ($accountId < 1) {
        return;
    }

    $nickname = trim($nickname);
    if (sr_community_member_nickname_exists($pdo, $nickname, $accountId)) {
        throw new RuntimeException('community_nickname_duplicate');
    }

    sr_member_set_nickname($pdo, $accountId, $nickname);
    sr_community_clear_member_nickname_cache($pdo, $accountId);
}

function sr_community_create_member_nickname(PDO $pdo, int $accountId, string $nickname): void
{
    if ($accountId < 1) {
        return;
    }

    sr_community_set_member_nickname($pdo, $accountId, $nickname);
    sr_audit_log($pdo, [
        'actor_account_id' => $accountId,
        'actor_type' => 'member',
        'event_type' => 'community.nickname.created',
        'target_type' => 'member_account',
        'target_id' => (string) $accountId,
        'result' => 'success',
        'message' => 'Community nickname created by member.',
        'metadata' => [
            'nickname_set' => true,
        ],
    ]);
}

function sr_community_clear_member_nickname_cache(PDO $pdo, int $accountId): void
{
    if (!isset($GLOBALS['sr_community_member_nickname_cache']) || !is_array($GLOBALS['sr_community_member_nickname_cache'])) {
        return;
    }

    unset($GLOBALS['sr_community_member_nickname_cache'][(string) spl_object_id($pdo) . ':' . (string) $accountId]);
}

function sr_community_member_nicknames_table_exists(PDO $pdo): bool
{
    return sr_member_nicknames_table_exists($pdo);
}

function sr_community_delete_member_nickname(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1 || !sr_member_nicknames_table_exists($pdo)) {
        return false;
    }

    $before = sr_member_nickname($pdo, $accountId);
    sr_member_delete_nickname($pdo, $accountId);
    sr_community_clear_member_nickname_cache($pdo, $accountId);

    return $before !== '';
}

function sr_community_public_account_summary(PDO $pdo, int $accountId): ?array
{
    $summary = sr_member_public_account_summary($pdo, $accountId);
    if (!is_array($summary)) {
        return null;
    }

    $summary['community_nickname'] = sr_community_nickname_status_blocks_identity((string) $summary['status'])
        ? ''
        : sr_community_member_nickname($pdo, (int) $summary['id']);

    return $summary;
}

function sr_community_public_account_summary_by_hash(PDO $pdo, array $config, string $publicHash): ?array
{
    $summary = sr_member_public_account_summary_by_hash($pdo, $config, $publicHash);
    if (!is_array($summary)) {
        return null;
    }

    $summary['community_nickname'] = sr_community_nickname_status_blocks_identity((string) $summary['status'])
        ? ''
        : sr_community_member_nickname($pdo, (int) $summary['id']);

    return $summary;
}

function sr_community_nickname_filter(PDO $pdo, array $config, bool $levelFilterEnabled = false, ?array $settings = null): array
{
    $field = sr_get_string('field', 30);
    $keyword = trim(sr_get_string('q', 120));
    $allowedFields = ['all', 'hash', 'email', 'nickname'];
    if (!in_array($field, $allowedFields, true)) {
        $field = 'all';
    }
    $levelValue = null;
    $levelInput = sr_get_string('level', 20);
    if ($levelFilterEnabled && $levelInput !== '' && preg_match('/\A[0-9]+\z/', $levelInput) === 1) {
        $levelValue = sr_community_normalize_level_value($levelInput, $settings);
    }

    $accountId = 0;
    if ($field === 'all' || $field === 'hash') {
        $accountId = sr_admin_member_account_id_from_lookup($pdo, $config, $field, $keyword);
    }

    return [
        'field' => $field,
        'keyword' => $keyword,
        'account_id' => $accountId,
        'level_value' => $levelValue,
        'level_enabled' => $levelFilterEnabled,
    ];
}

function sr_community_nickname_query_parts(array $filter = []): array
{
    $params = [];
    $where = [
        "a.status NOT IN ('withdrawn', 'anonymized')",
        "n.nickname <> ''",
    ];
    $field = (string) ($filter['field'] ?? 'all');
    $keyword = trim((string) ($filter['keyword'] ?? ''));
    $accountId = (int) ($filter['account_id'] ?? 0);
    $levelValue = $filter['level_value'] ?? null;

    if ($keyword !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
        if ($field === 'hash') {
            $where[] = $accountId > 0 ? 'a.id = :account_id' : '1 = 0';
            if ($accountId > 0) {
                $params['account_id'] = $accountId;
            }
        } elseif ($field === 'email') {
            $where[] = "a.email LIKE :keyword_like ESCAPE '\\\\'";
            $params['keyword_like'] = $like;
        } elseif ($field === 'nickname') {
            $where[] = "(a.status NOT IN ('withdrawn', 'anonymized') AND n.nickname LIKE :keyword_like ESCAPE '\\\\')";
            $params['keyword_like'] = $like;
        } else {
            $clauses = [
                "a.email LIKE :keyword_email_like ESCAPE '\\\\'",
                "(a.status NOT IN ('withdrawn', 'anonymized') AND n.nickname LIKE :keyword_nickname_like ESCAPE '\\\\')",
            ];
            $params['keyword_email_like'] = $like;
            $params['keyword_nickname_like'] = $like;
            if ($accountId > 0) {
                $clauses[] = 'a.id = :account_id';
                $params['account_id'] = $accountId;
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
    }
    if (!empty($filter['level_enabled']) && $levelValue !== null) {
        $where[] = 'COALESCE(l.level_value, 0) = :level_value';
        $params['level_value'] = (int) $levelValue;
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_community_nickname_count(PDO $pdo, array $filter = []): int
{
    $queryParts = sr_community_nickname_query_parts($filter);
    $whereSql = $queryParts['where'] === [] ? '' : 'WHERE ' . implode(' AND ', $queryParts['where']);
    $levelJoinSql = !empty($filter['level_enabled']) && sr_community_level_tables_exist($pdo)
        ? 'LEFT JOIN sr_community_account_levels l ON l.account_id = a.id'
        : '';
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_member_accounts a
         INNER JOIN sr_member_nicknames n ON n.account_id = a.id
         ' . $levelJoinSql . '
         ' . $whereSql
    );
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_admin_nickname_sort_options(bool $levelEnabled = false): array
{
    $options = [
        'email' => ['columns' => ['a.email', 'a.id']],
        'nickname' => ['columns' => ['n.nickname', 'a.id']],
        'status' => ['columns' => ['a.status', 'a.id']],
        'updated_at' => ['columns' => ['n.updated_at', 'a.id']],
        'created_at' => ['columns' => ['a.id']],
    ];

    if ($levelEnabled) {
        $options['level_value'] = ['columns' => ['COALESCE(l.level_value, 0)', 'a.id']];
    }

    return $options;
}

function sr_community_admin_nickname_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_community_nickname_rows(PDO $pdo, array $filter = [], int $limit = 0, int $offset = 0, array $sort = []): array
{
    $queryParts = sr_community_nickname_query_parts($filter);
    $whereSql = $queryParts['where'] === [] ? '' : 'WHERE ' . implode(' AND ', $queryParts['where']);
    $limitSql = $limit > 0 ? ' LIMIT :limit_value OFFSET :offset_value' : '';
    $includeLevel = !empty($filter['level_enabled']) && sr_community_level_tables_exist($pdo);
    $sortOptions = sr_community_admin_nickname_sort_options($includeLevel);
    $defaultSort = sr_community_admin_nickname_default_sort();
    $levelSelectSql = $includeLevel
        ? ',
                COALESCE(l.level_value, 0) AS community_level_value,
                COALESCE(l.score_value, 0) AS community_score_value,
                COALESCE(l.post_count, 0) AS community_level_post_count,
                COALESCE(l.comment_count, 0) AS community_level_comment_count,
                l.evaluated_at AS community_level_evaluated_at'
        : '';
    $levelJoinSql = $includeLevel
        ? 'LEFT JOIN sr_community_account_levels l ON l.account_id = a.id'
        : '';
    $stmt = $pdo->prepare(
        'SELECT a.id, a.email, a.display_name, a.status, a.created_at,
                CASE WHEN a.status IN (\'withdrawn\', \'anonymized\') THEN \'\' ELSE COALESCE(n.nickname, \'\') END AS nickname,
                CASE WHEN a.status IN (\'withdrawn\', \'anonymized\') THEN NULL ELSE n.updated_at END AS nickname_updated_at
                ' . $levelSelectSql . '
         FROM sr_member_accounts a
         INNER JOIN sr_member_nicknames n ON n.account_id = a.id
         ' . $levelJoinSql . '
         ' . $whereSql . '
         ' . sr_admin_sort_order_sql($sortOptions, $sort, $defaultSort) . $limitSql
    );
    foreach ($queryParts['params'] as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_member_nickname_exists(PDO $pdo, string $nickname, int $excludeAccountId = 0): bool
{
    return sr_member_nickname_exists($pdo, $nickname, $excludeAccountId);
}

function sr_community_member_registration_fields(PDO $pdo): array
{
    $settings = sr_community_settings($pdo);
    if (empty($settings['nickname_enabled'])) {
        return [];
    }

    return [
        [
            'key' => 'community_nickname',
            'type' => 'text',
            'label' => sr_t('community::ui.nickname'),
            'help' => sr_t('community::ui.nickname.register.help'),
            'maxlength' => 80,
            'required' => true,
        ],
    ];
}

function sr_community_member_registration_validate(PDO $pdo, array $values, array $context = []): array
{
    $settings = sr_community_settings($pdo);
    if (empty($settings['nickname_enabled'])) {
        return [];
    }

    $nickname = trim((string) ($values['community_nickname'] ?? ''));
    if ($nickname === '') {
        return [sr_t('community::action.nickname_required')];
    }

    if (sr_community_member_nickname_exists($pdo, $nickname)) {
        return [sr_t('community::action.nickname_duplicate')];
    }

    return [];
}

function sr_community_member_registration_save(PDO $pdo, int $accountId, array $values, array $context = []): array
{
    $settings = sr_community_settings($pdo);
    $nickname = trim((string) ($values['community_nickname'] ?? ''));
    if (empty($settings['nickname_enabled']) || $nickname === '') {
        return ['community_nickname_set' => false];
    }

    try {
        sr_community_create_member_nickname($pdo, $accountId, $nickname);
    } catch (Throwable $exception) {
        if ($exception instanceof RuntimeException && $exception->getMessage() === 'community_nickname_duplicate') {
            throw $exception;
        }
        if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
            throw new RuntimeException('community_nickname_duplicate', 0, $exception);
        }
        throw $exception;
    }

    return ['community_nickname_set' => true];
}

function sr_community_random_member_nickname(PDO $pdo, string $currentNickname = ''): string
{
    $currentNickname = trim($currentNickname);
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $nickname = '회원' . (string) random_int(100000, 999999);
        if ($nickname !== $currentNickname && !sr_community_member_nickname_exists($pdo, $nickname)) {
            return $nickname;
        }
    }

    return '회원' . bin2hex(random_bytes(4));
}

function sr_community_nickname_reset_reason_options(): array
{
    return [
        'inappropriate' => sr_t('community::nickname_reset_reason.inappropriate'),
        'personal_info' => sr_t('community::nickname_reset_reason.personal_info'),
        'impersonation' => sr_t('community::nickname_reset_reason.impersonation'),
        'spam' => sr_t('community::nickname_reset_reason.spam'),
        'policy' => sr_t('community::nickname_reset_reason.policy'),
    ];
}

function sr_community_nickname_reset_reason_label(string $reason): string
{
    $reason = trim($reason);
    $options = sr_community_nickname_reset_reason_options();

    return isset($options[$reason]) ? (string) $options[$reason] : '';
}

function sr_community_safe_next_path(string $next, string $fallback = '/community'): string
{
    $next = trim($next);
    $parts = parse_url($next);
    if (!is_array($parts)
        || isset($parts['scheme'])
        || isset($parts['host'])
        || str_contains($next, "\r")
        || str_contains($next, "\n")
    ) {
        return $fallback;
    }

    $path = (string) ($parts['path'] ?? '');
    if ($path === ''
        || ($path !== '/community' && !str_starts_with($path, '/community/'))
        || $path === '/community/nickname'
    ) {
        return $fallback;
    }

    $query = isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== ''
        ? '?' . $parts['query']
        : '';
    $fragment = isset($parts['fragment']) && is_string($parts['fragment']) && $parts['fragment'] !== ''
        ? '#' . $parts['fragment']
        : '';

    return $path . $query . $fragment;
}

function sr_community_member_needs_nickname(PDO $pdo, array $account, array $settings): bool
{
    return false;
}

function sr_community_require_member_nickname(PDO $pdo, array $account, array $settings, string $nextPath): void
{
    return;
}

function sr_community_handle_member_nickname_setup_post(PDO $pdo, array $account): array
{
    $errors = [];
    $notice = '';
    $nicknameInput = sr_post_string_without_truncation('nickname', 80);
    $nickname = $nicknameInput === null ? '' : trim($nicknameInput);

    if (sr_community_nickname_status_blocks_identity((string) ($account['status'] ?? ''))) {
        $errors[] = sr_t('community::action.nickname_setup_blocked');
    }

    if ($nicknameInput === null) {
        $errors[] = sr_t('community::action.nickname_too_long');
    } elseif ($nickname === '') {
        $errors[] = sr_t('community::action.nickname_required');
    } elseif (sr_community_member_nickname_exists($pdo, $nickname, (int) ($account['id'] ?? 0))) {
        $errors[] = sr_t('community::action.nickname_duplicate');
    }

    if ($errors === []) {
        try {
            sr_community_create_member_nickname($pdo, (int) $account['id'], $nickname);
            $notice = sr_t('community::action.nickname_saved');
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException && $exception->getMessage() === 'community_nickname_duplicate') {
                $errors[] = sr_t('community::action.nickname_duplicate');
            } elseif ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                $errors[] = sr_t('community::action.nickname_duplicate');
            } else {
                throw $exception;
            }
        }
    }

    return [
        'errors' => $errors,
        'notice' => $notice,
    ];
}

function sr_community_handle_nickname_reset_post(PDO $pdo, array $account): array
{
    $errors = [];
    $notice = '';
    $targetAccountId = sr_admin_post_positive_int('account_id');
    $resetReason = sr_post_string('reset_reason', 40);
    $resetReasonLabel = sr_community_nickname_reset_reason_label($resetReason);

    if ($targetAccountId <= 0) {
        $errors[] = sr_t('member::action.admin.member_required');
    }

    if ($resetReasonLabel === '') {
        $errors[] = sr_t('community::action.admin.nickname_reset_reason_required');
    }

    $targetAccount = null;
    if ($errors === []) {
        $targetAccount = sr_admin_member_by_id($pdo, $targetAccountId);
        if (!is_array($targetAccount)) {
            $errors[] = sr_t('member::action.admin.member_not_found');
        } elseif (sr_community_nickname_status_blocks_identity((string) ($targetAccount['status'] ?? ''))) {
            $errors[] = sr_t('community::action.admin.nickname_anonymized_disallowed');
        }
    }

    $beforeNickname = '';
    if ($errors === []) {
        $beforeNickname = sr_community_member_nickname($pdo, $targetAccountId);
        if ($beforeNickname === '') {
            $errors[] = sr_t('community::action.admin.nickname_reset_requires_existing');
        }
    }

    if ($errors === []) {
        $nickname = '';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $nickname = sr_community_random_member_nickname($pdo, $beforeNickname);
            try {
                sr_community_set_member_nickname($pdo, $targetAccountId, $nickname);
                break;
            } catch (Throwable $exception) {
                $isDuplicate = ($exception instanceof RuntimeException && $exception->getMessage() === 'community_nickname_duplicate')
                    || ($exception instanceof PDOException && (string) $exception->getCode() === '23000');
                if (!$isDuplicate || $attempt >= 9) {
                    throw $exception;
                }
            }
        }

        $notificationAvailable = sr_community_notification_available($pdo);
        $notificationSent = $notificationAvailable
            ? sr_community_create_account_notification(
                $pdo,
                $targetAccountId,
                sr_t('community::notification.nickname_reset.title'),
                sr_t('community::notification.nickname_reset.body', [
                    'nickname' => $nickname,
                    'reason' => $resetReasonLabel,
                ]),
                '/account/notifications',
                (int) $account['id']
            )
            : false;

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'community.nickname.reset',
            'target_type' => 'member_account',
            'target_id' => (string) $targetAccountId,
            'result' => 'success',
            'message' => 'Community nickname reset by admin.',
            'metadata' => [
                'nickname_changed' => true,
                'nickname_was_set' => $beforeNickname !== '',
                'nickname_set' => $nickname !== '',
                'previous_nickname' => $beforeNickname,
                'reset_reason' => $resetReason,
                'reset_reason_label' => $resetReasonLabel,
                'notification_available' => $notificationAvailable,
                'notification_sent' => $notificationSent,
            ],
        ]);

        if ($notificationSent) {
            $notice = sr_t('community::action.admin.nickname_reset');
        } elseif ($notificationAvailable) {
            $notice = sr_t('community::action.admin.nickname_reset_notification_failed');
        } else {
            $notice = sr_t('community::action.admin.nickname_reset_without_notification');
        }
    }

    return sr_admin_action_result($errors, $notice);
}
