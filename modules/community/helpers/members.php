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
        || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'view');
}

function sr_community_member_identifier_suffix(array $config, int $accountId, bool $showIdentifier): string
{
    if (!$showIdentifier || $accountId < 1) {
        return '';
    }

    $publicHash = sr_member_public_account_hash($config, $accountId);
    if ($publicHash === '') {
        return sr_t('community::member.identifier.id_suffix', ['id' => (string) $accountId]);
    }

    return sr_t('community::member.identifier.id_hash_suffix', [
        'id' => (string) $accountId,
        'hash' => $publicHash,
    ]);
}

function sr_community_member_label_with_identifier(string $label, array $config, int $accountId, bool $showIdentifier): string
{
    return $label . sr_community_member_identifier_suffix($config, $accountId, $showIdentifier);
}

function sr_community_public_display_name(array $account, ?array $settings = null): string
{
    if (sr_community_nickname_status_blocks_identity((string) ($account['status'] ?? ''))) {
        return sr_t('member::account.withdrawn_display_name');
    }

    $settings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_default_settings();
    $nickname = trim((string) ($account['community_nickname'] ?? ''));
    if (!empty($settings['nickname_enabled']) && $nickname !== '') {
        return $nickname;
    }

    return trim((string) ($account['display_name'] ?? ''));
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
         FROM sr_community_member_nicknames
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();
    $cache[$cacheKey] = is_array($row) ? (string) ($row['nickname'] ?? '') : '';

    return $cache[$cacheKey];
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
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_community_member_nicknames LIMIT 1');
        $exists = true;
    } catch (Throwable) {
        $exists = false;
    }

    return $exists;
}

function sr_community_delete_member_nickname(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1 || !sr_community_member_nicknames_table_exists($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare('DELETE FROM sr_community_member_nicknames WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);
    sr_community_clear_member_nickname_cache($pdo, $accountId);

    return $stmt->rowCount() > 0;
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

function sr_community_nickname_filter(PDO $pdo, array $config): array
{
    $field = sr_get_string('field', 30);
    $keyword = trim(sr_get_string('q', 120));
    $allowedFields = ['all', 'hash', 'email', 'name', 'nickname'];
    if (!in_array($field, $allowedFields, true)) {
        $field = 'all';
    }

    $accountId = 0;
    if ($field === 'all' || $field === 'hash') {
        $accountId = sr_admin_member_account_id_from_lookup($pdo, $config, $field, $keyword);
    }

    return [
        'field' => $field,
        'keyword' => $keyword,
        'account_id' => $accountId,
    ];
}

function sr_community_nickname_query_parts(array $filter = []): array
{
    $params = [];
    $where = [];
    $field = (string) ($filter['field'] ?? 'all');
    $keyword = trim((string) ($filter['keyword'] ?? ''));
    $accountId = (int) ($filter['account_id'] ?? 0);

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
        } elseif ($field === 'name') {
            $where[] = "a.display_name LIKE :keyword_like ESCAPE '\\\\'";
            $params['keyword_like'] = $like;
        } elseif ($field === 'nickname') {
            $where[] = "(a.status NOT IN ('withdrawn', 'anonymized') AND n.nickname LIKE :keyword_like ESCAPE '\\\\')";
            $params['keyword_like'] = $like;
        } else {
            $clauses = [
                "a.email LIKE :keyword_email_like ESCAPE '\\\\'",
                "a.display_name LIKE :keyword_name_like ESCAPE '\\\\'",
                "(a.status NOT IN ('withdrawn', 'anonymized') AND n.nickname LIKE :keyword_nickname_like ESCAPE '\\\\')",
            ];
            $params['keyword_email_like'] = $like;
            $params['keyword_name_like'] = $like;
            $params['keyword_nickname_like'] = $like;
            if ($accountId > 0) {
                $clauses[] = 'a.id = :account_id';
                $params['account_id'] = $accountId;
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
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
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_member_accounts a
         LEFT JOIN sr_community_member_nicknames n ON n.account_id = a.id
         ' . $whereSql
    );
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_nickname_rows(PDO $pdo, array $filter = [], int $limit = 0, int $offset = 0): array
{
    $queryParts = sr_community_nickname_query_parts($filter);
    $whereSql = $queryParts['where'] === [] ? '' : 'WHERE ' . implode(' AND ', $queryParts['where']);
    $limitSql = $limit > 0 ? ' LIMIT :limit_value OFFSET :offset_value' : '';
    $stmt = $pdo->prepare(
        'SELECT a.id, a.email, a.display_name, a.status, a.created_at,
                CASE WHEN a.status IN (\'withdrawn\', \'anonymized\') THEN \'\' ELSE COALESCE(n.nickname, \'\') END AS nickname,
                CASE WHEN a.status IN (\'withdrawn\', \'anonymized\') THEN NULL ELSE n.updated_at END AS nickname_updated_at
         FROM sr_member_accounts a
         LEFT JOIN sr_community_member_nicknames n ON n.account_id = a.id
         ' . $whereSql . '
         ORDER BY a.id DESC' . $limitSql
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

function sr_community_handle_nickname_post(PDO $pdo, array $account, array $settings): array
{
    $errors = [];
    $notice = '';
    $targetAccountId = sr_admin_post_positive_int('account_id');
    $nicknameInput = sr_post_string_without_truncation('nickname', 80);
    $nickname = $nicknameInput === null ? '' : trim($nicknameInput);
    $nicknameRequired = !empty($settings['nickname_enabled']) && !empty($settings['nickname_required']);

    if ($targetAccountId <= 0) {
        $errors[] = sr_t('member::action.admin.member_required');
    }

    if ($nicknameInput === null) {
        $errors[] = sr_t('community::action.admin.nickname_too_long');
    }

    if ($nicknameRequired && $nickname === '') {
        $errors[] = sr_t('community::action.admin.nickname_required');
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
        if ($beforeNickname !== $nickname) {
            $now = sr_now();
            $stmt = $pdo->prepare(
                'INSERT INTO sr_community_member_nicknames
                    (account_id, nickname, created_at, updated_at)
                 VALUES
                    (:account_id, :nickname, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    nickname = VALUES(nickname),
                    updated_at = VALUES(updated_at)'
            );
            $stmt->execute([
                'account_id' => $targetAccountId,
                'nickname' => $nickname,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            sr_community_clear_member_nickname_cache($pdo, $targetAccountId);
        }

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'community.nickname.updated',
            'target_type' => 'member_account',
            'target_id' => (string) $targetAccountId,
            'result' => 'success',
            'message' => 'Community nickname updated by admin.',
            'metadata' => [
                'nickname_changed' => $beforeNickname !== $nickname,
                'nickname_set' => $nickname !== '',
            ],
        ]);

        $notice = sr_t('community::action.admin.nickname_updated');
    }

    return sr_admin_action_result($errors, $notice);
}
