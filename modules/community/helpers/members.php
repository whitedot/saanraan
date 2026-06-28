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

function sr_community_clear_member_nickname_cache(PDO $pdo, int $accountId): void
{
    if (!isset($GLOBALS['sr_community_member_nickname_cache']) || !is_array($GLOBALS['sr_community_member_nickname_cache'])) {
        return;
    }

    unset($GLOBALS['sr_community_member_nickname_cache'][(string) spl_object_id($pdo) . ':' . (string) $accountId]);
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

function sr_community_member_nickname_exists(PDO $pdo, string $nickname, int $excludeAccountId = 0): bool
{
    return sr_member_nickname_exists($pdo, $nickname, $excludeAccountId);
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
