<?php

declare(strict_types=1);

function sr_community_notification_available(PDO $pdo): bool
{
    return sr_community_notification_create_function($pdo) !== '';
}

function sr_community_notification_create_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_function');
}

function sr_community_notification_event_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_community_admin_notification_create_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'admin-notification-events.php', 'create_function');
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

function sr_community_create_account_event_notification(
    PDO $pdo,
    int $accountId,
    string $eventKey,
    array $metadata,
    ?int $createdByAccountId = null
): bool {
    $createAccountEventFunction = sr_community_notification_event_function($pdo);
    if ($accountId < 1 || $createAccountEventFunction === '') {
        return false;
    }

    try {
        return $createAccountEventFunction($pdo, [
            'account_id' => $accountId,
            'module_key' => 'community',
            'event_key' => $eventKey,
            'created_by_account_id' => $createdByAccountId,
            'metadata' => $metadata,
        ]) !== null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'community_notification_event_create');
    }

    return false;
}

function sr_community_create_post_follow_notifications(PDO $pdo, array $post, ?int $createdByAccountId = null): int
{
    $postId = (int) ($post['id'] ?? 0);
    $authorAccountId = (int) ($post['author_account_id'] ?? 0);
    if ($postId < 1 || $authorAccountId < 1 || (string) ($post['status'] ?? '') !== 'published') {
        return 0;
    }

    $metadata = [
        'post_id' => $postId,
        'board_key' => (string) ($post['board_key'] ?? ''),
        'board_title' => (string) ($post['board_title'] ?? ''),
        'post_title' => (string) ($post['title'] ?? ''),
        'member_name' => sr_member_public_name_for_account_id($pdo, $authorAccountId, '회원'),
        'link_url' => '/community/post?id=' . (string) $postId,
        'created_at' => sr_now(),
    ];

    $createdCount = 0;
    foreach (sr_member_followers($pdo, $authorAccountId) as $followerAccountId) {
        if ($followerAccountId === $authorAccountId) {
            continue;
        }
        if (sr_community_create_account_event_notification($pdo, $followerAccountId, 'followed_author.post_created', $metadata, $createdByAccountId)) {
            $createdCount++;
        }
    }

    return $createdCount;
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

    $createAdminNotificationFunction = sr_community_admin_notification_create_function($pdo);
    $bodyText = sr_t('community::notification.report.body', [
        'target_type' => sr_community_report_target_type_label($targetType),
        'target_id' => (string) $targetId,
        'reason' => sr_community_report_reason_label($reasonKey),
    ]);
    if ($createAdminNotificationFunction !== '') {
        try {
            $adminNotificationId = $createAdminNotificationFunction($pdo, [
                'title' => sr_t('community::notification.report.title'),
                'body_text' => $bodyText,
                'severity' => $reasonKey === 'personal_info' || $reasonKey === 'illegal' ? 'danger' : 'warning',
                'source_module_key' => 'community',
                'event_key' => 'report.created',
                'target_type' => 'community_report',
                'target_id' => (string) $reportId,
                'action_url' => '/admin/community/reports',
                'permission_path' => '/admin/community/reports',
                'permission_action' => 'view',
                'dedupe_key' => 'community.report.' . (string) $reportId,
                'created_by_account_id' => $createdByAccountId,
            ]);
            if ($adminNotificationId !== null) {
                return;
            }
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'community_admin_notification_create');
        }
    }

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

function sr_community_mentioned_account_ids(PDO $pdo, string $bodyText, array $excludeAccountIds = []): array
{
    return sr_member_mention_account_ids($pdo, sr_runtime_config(), $bodyText, $excludeAccountIds);
}

function sr_community_create_comment_mention_notifications(
    PDO $pdo,
    int $postId,
    int $commentId,
    string $bodyText,
    int $createdByAccountId,
    array $excludeAccountIds = [],
    ?string $previousBodyText = null
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
    if ($previousBodyText !== null) {
        $previousAccountIds = sr_community_mentioned_account_ids($pdo, $previousBodyText, array_merge($excludeAccountIds, [$createdByAccountId]));
        $previousMap = array_fill_keys(array_map('intval', $previousAccountIds), true);
        $mentionedAccountIds = array_values(array_filter($mentionedAccountIds, static function (int $accountId) use ($previousMap): bool {
            return !isset($previousMap[$accountId]);
        }));
    }
    $result['mention_candidate_count'] = count($mentionedAccountIds);
    $config = sr_runtime_config();
    foreach ($mentionedAccountIds as $accountId) {
        $result['mention_account_hashes'][] = sr_member_public_account_hash($config, (int) $accountId);
    }
    foreach ($mentionedAccountIds as $accountId) {
        if (sr_community_create_account_event_notification(
            $pdo,
            $accountId,
            'comment.mention',
            [
                'post_id' => $postId,
                'comment_id' => $commentId,
                'member_name' => sr_member_public_name_for_account_id($pdo, $createdByAccountId, sr_t('community::report.account.member')),
                'link_url' => '/community/post?id=' . (string) $postId . '#comments',
                'created_at' => sr_now(),
            ],
            $createdByAccountId
        )) {
            $result['mention_notification_count']++;
        }
    }

    return $result;
}
