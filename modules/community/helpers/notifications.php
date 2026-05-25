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
