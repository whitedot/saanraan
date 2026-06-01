<?php

declare(strict_types=1);

function sr_community_notification_available(PDO $pdo): bool
{
    if (!function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'notification')) {
        return false;
    }

    $helperPath = SR_ROOT . '/modules/notification/helpers.php';
    if (!is_file($helperPath)) {
        return false;
    }

    require_once $helperPath;

    return function_exists('sr_notification_create');
}

function sr_community_create_account_notification(
    PDO $pdo,
    int $accountId,
    string $title,
    string $bodyText,
    string $linkUrl,
    ?int $createdByAccountId = null
): bool {
    if ($accountId < 1 || !sr_community_notification_available($pdo)) {
        return false;
    }

    try {
        sr_notification_create($pdo, [
            'audience' => 'account',
            'account_id' => $accountId,
            'title' => $title,
            'body_text' => $bodyText,
            'link_url' => $linkUrl,
            'channels' => ['site'],
            'created_by_account_id' => $createdByAccountId,
        ]);
        return true;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'community_notification_create');
    }

    return false;
}

function sr_community_admin_permission_tables_exist(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_admin_account_roles LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_admin_account_permissions LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_notification_admin_account_ids(PDO $pdo): array
{
    if (!sr_community_admin_permission_tables_exist($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT DISTINCT a.id
         FROM sr_member_accounts a
         LEFT JOIN sr_admin_account_roles r ON r.account_id = a.id AND r.role_key = 'owner'
         LEFT JOIN sr_admin_account_permissions p ON p.account_id = a.id
         WHERE (r.id IS NOT NULL OR (p.menu_path = '/admin/community/reports' AND p.action_key = 'view'))
           AND a.status = 'active'
         ORDER BY a.id ASC"
    );

    $accountIds = [];
    foreach ($stmt->fetchAll() as $row) {
        $accountId = (int) ($row['id'] ?? 0);
        if ($accountId > 0) {
            $accountIds[] = $accountId;
        }
    }

    return $accountIds;
}

function sr_community_create_admin_report_notifications(
    PDO $pdo,
    int $reportId,
    string $targetType,
    int $targetId,
    string $reasonKey,
    int $createdByAccountId
): void {
    if ($reportId < 1) {
        return;
    }

    $bodyText = sr_t('community::notification.report.body', [
        'target_type' => $targetType,
        'target_id' => (string) $targetId,
        'reason' => sr_community_report_reason_label($reasonKey),
    ]);
    foreach (sr_community_notification_admin_account_ids($pdo) as $accountId) {
        sr_community_create_account_notification(
            $pdo,
            $accountId,
            sr_t('community::notification.report.title'),
            $bodyText,
            '/admin/community/reports',
            $createdByAccountId
        );
    }
}

function sr_community_mention_tokens(string $bodyText): array
{
    if (!preg_match_all('/@([^\s@#:,.;!?()\[\]{}<>]{2,40})/u', $bodyText, $matches)) {
        return [];
    }

    $tokens = [];
    foreach ($matches[1] as $token) {
        $token = trim((string) $token);
        if ($token !== '') {
            $tokens[$token] = true;
        }
    }

    return array_keys($tokens);
}

function sr_community_mentioned_account_ids(PDO $pdo, string $bodyText, array $excludeAccountIds = []): array
{
    $tokens = sr_community_mention_tokens($bodyText);
    if ($tokens === [] || !function_exists('sr_community_member_nicknames_table_exists') || !sr_community_member_nicknames_table_exists($pdo)) {
        return [];
    }

    $exclude = [];
    foreach ($excludeAccountIds as $accountId) {
        $accountId = (int) $accountId;
        if ($accountId > 0) {
            $exclude[$accountId] = true;
        }
    }

    $placeholders = implode(',', array_fill(0, count($tokens), '?'));
    $stmt = $pdo->prepare(
        "SELECT n.account_id
         FROM sr_community_member_nicknames n
         INNER JOIN sr_member_accounts a ON a.id = n.account_id
         WHERE n.nickname IN (" . $placeholders . ")
           AND a.status = 'active'"
    );
    $stmt->execute($tokens);

    $accountIds = [];
    foreach ($stmt->fetchAll() as $row) {
        $accountId = (int) ($row['account_id'] ?? 0);
        if ($accountId > 0 && !isset($exclude[$accountId])) {
            $accountIds[$accountId] = true;
        }
    }

    return array_keys($accountIds);
}

function sr_community_create_comment_mention_notifications(
    PDO $pdo,
    int $postId,
    int $commentId,
    string $bodyText,
    int $createdByAccountId,
    array $excludeAccountIds = []
): void {
    if ($postId < 1 || $commentId < 1) {
        return;
    }

    foreach (sr_community_mentioned_account_ids($pdo, $bodyText, array_merge($excludeAccountIds, [$createdByAccountId])) as $accountId) {
        sr_community_create_account_notification(
            $pdo,
            $accountId,
            sr_t('community::notification.comment_mention.title'),
            sr_t('community::notification.comment_mention.body'),
            '/community/post?id=' . (string) $postId . '#comments',
            $createdByAccountId
        );
    }
}
