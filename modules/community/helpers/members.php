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
