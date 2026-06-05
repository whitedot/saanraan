<?php

declare(strict_types=1);

function sr_community_notification_available(PDO $pdo): bool
{
    return sr_community_notification_create_function($pdo) !== '';
}

function sr_community_notification_create_function(PDO $pdo): string
{
    if (!sr_module_enabled($pdo, 'notification') || !sr_module_contract_is_loadable('notification')) {
        return '';
    }

    $contractFile = SR_ROOT . '/modules/notification/notification-events.php';
    $contract = sr_load_module_contract_file('notification', $contractFile);
    if (!is_array($contract)) {
        return '';
    }

    $helpers = (string) ($contract['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $helperPath = SR_ROOT . '/modules/notification/' . $helpers;
    if (!is_file($helperPath)) {
        return '';
    }

    require_once $helperPath;

    $function = (string) ($contract['create_function'] ?? '');
    return $function !== '' && function_exists($function) ? $function : '';
}

function sr_community_create_account_notification(
    PDO $pdo,
    int $accountId,
    string $title,
    string $bodyText,
    string $linkUrl,
    ?int $createdByAccountId = null
): bool {
    $createNotificationFunction = sr_community_notification_create_function($pdo);
    if ($accountId < 1 || $createNotificationFunction === '') {
        return false;
    }

    try {
        $createNotificationFunction($pdo, [
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
    if ($tokens === []) {
        return [];
    }
    return sr_member_public_name_lookup_account_ids($pdo, $tokens, $excludeAccountIds);
}

function sr_community_create_comment_mention_notifications(
    PDO $pdo,
    int $postId,
    int $commentId,
    string $bodyText,
    int $createdByAccountId,
    array $excludeAccountIds = []
): array {
    $result = [
        'mention_candidate_count' => 0,
        'mention_notification_count' => 0,
        'mention_account_hashes' => [],
    ];
    if ($postId < 1 || $commentId < 1) {
        return $result;
    }

    $mentionedAccountIds = sr_community_mentioned_account_ids($pdo, $bodyText, array_merge($excludeAccountIds, [$createdByAccountId]));
    $result['mention_candidate_count'] = count($mentionedAccountIds);
    $config = sr_runtime_config();
    foreach ($mentionedAccountIds as $accountId) {
        $result['mention_account_hashes'][] = sr_member_public_account_hash($config, (int) $accountId);
    }
    foreach ($mentionedAccountIds as $accountId) {
        if (sr_community_create_account_notification(
            $pdo,
            $accountId,
            sr_t('community::notification.comment_mention.title'),
            sr_t('community::notification.comment_mention.body'),
            '/community/post?id=' . (string) $postId . '#comments',
            $createdByAccountId
        )) {
            $result['mention_notification_count']++;
        }
    }

    return $result;
}
