<?php

declare(strict_types=1);

function toy_community_notification_available(PDO $pdo): bool
{
    if (!function_exists('toy_module_enabled') || !toy_module_enabled($pdo, 'notification')) {
        return false;
    }

    $helperPath = TOY_ROOT . '/modules/notification/helpers.php';
    if (!is_file($helperPath)) {
        return false;
    }

    require_once $helperPath;

    return function_exists('toy_notification_create');
}

function toy_community_create_account_notification(
    PDO $pdo,
    int $accountId,
    string $title,
    string $bodyText,
    string $linkUrl,
    ?int $createdByAccountId = null
): void {
    if ($accountId < 1 || !toy_community_notification_available($pdo)) {
        return;
    }

    try {
        toy_notification_create($pdo, [
            'audience' => 'account',
            'account_id' => $accountId,
            'title' => $title,
            'body_text' => $bodyText,
            'link_url' => $linkUrl,
            'channels' => ['site'],
            'created_by_account_id' => $createdByAccountId,
        ]);
    } catch (Throwable $exception) {
        toy_log_exception($exception, 'community_notification_create');
    }
}

function toy_community_admin_role_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM toy_admin_account_roles LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function toy_community_notification_admin_account_ids(PDO $pdo): array
{
    if (!toy_community_admin_role_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT DISTINCT a.id
         FROM toy_admin_account_roles r
         INNER JOIN toy_member_accounts a ON a.id = r.account_id
         WHERE r.role_key IN ('owner', 'admin', 'manager')
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

function toy_community_create_admin_report_notifications(
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

    $bodyText = '신고 대상: ' . $targetType . ' #' . (string) $targetId
        . ' / 사유: ' . toy_community_report_reason_label($reasonKey);
    foreach (toy_community_notification_admin_account_ids($pdo) as $accountId) {
        toy_community_create_account_notification(
            $pdo,
            $accountId,
            '새 커뮤니티 신고가 접수되었습니다.',
            $bodyText,
            '/admin/community/reports',
            $createdByAccountId
        );
    }
}
