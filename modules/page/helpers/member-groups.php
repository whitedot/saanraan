<?php

declare(strict_types=1);

function sr_page_member_group_rule_count_summary(int $count, string $translationKey): array
{
    return [
        'metric' => $count,
        'summary' => sr_t($translationKey, ['count' => (string) $count]),
    ];
}

function sr_page_member_group_rule_page_options(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT p.id, p.slug, p.title, g.title AS page_group_title
         FROM sr_pages p
         LEFT JOIN sr_page_groups g ON g.id = p.page_group_id
         WHERE p.status = 'published'
         ORDER BY COALESCE(g.sort_order, 1000000) ASC, g.id ASC, p.title ASC, p.id ASC"
    );

    $options = [];
    foreach ($stmt->fetchAll() as $page) {
        $groupTitle = trim((string) ($page['page_group_title'] ?? ''));
        $label = $groupTitle !== '' ? $groupTitle . ' / ' . (string) $page['title'] : (string) $page['title'];
        $options[] = [
            'value' => (string) $page['id'],
            'label' => $label . ' (' . (string) $page['slug'] . ')',
        ];
    }

    return $options;
}

function sr_page_member_group_rule_page_group_options(PDO $pdo): array
{
    $options = [];
    foreach (sr_page_groups($pdo) as $group) {
        if ((string) ($group['status'] ?? '') !== 'enabled') {
            continue;
        }

        $options[] = [
            'value' => (string) $group['id'],
            'label' => (string) $group['title'] . ' (' . (string) $group['group_key'] . ')',
        ];
    }

    return $options;
}

function sr_page_member_group_rule_paid_view_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1) {
        return ['matched' => false] + sr_page_member_group_rule_count_summary(0, 'page::member_group.summary.paid_views');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_page_asset_access_logs
         WHERE account_id = :account_id
           AND access_kind = 'view'
           AND transaction_id > 0"
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_page_member_group_rule_count_summary($count, 'page::member_group.summary.paid_views');
}

function sr_page_member_group_rule_file_download_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1) {
        return ['matched' => false] + sr_page_member_group_rule_count_summary(0, 'page::member_group.summary.file_downloads');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_page_asset_access_logs
         WHERE account_id = :account_id
           AND access_kind = 'download'
           AND transaction_id > 0"
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_page_member_group_rule_count_summary($count, 'page::member_group.summary.file_downloads');
}

function sr_page_member_group_rule_page_paid_view_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageId = (int) ($params['page_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageId < 1) {
        return ['matched' => false] + sr_page_member_group_rule_count_summary(0, 'page::member_group.summary.paid_views');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_page_asset_access_logs
         WHERE account_id = :account_id
           AND page_id = :page_id
           AND access_kind = 'view'
           AND transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'page_id' => $pageId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_page_member_group_rule_count_summary($count, 'page::member_group.summary.paid_views');
}

function sr_page_member_group_rule_page_file_download_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageId = (int) ($params['page_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageId < 1) {
        return ['matched' => false] + sr_page_member_group_rule_count_summary(0, 'page::member_group.summary.file_downloads');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_page_asset_access_logs
         WHERE account_id = :account_id
           AND page_id = :page_id
           AND access_kind = 'download'
           AND transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'page_id' => $pageId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_page_member_group_rule_count_summary($count, 'page::member_group.summary.file_downloads');
}

function sr_page_member_group_rule_action_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1) {
        return ['matched' => false] + sr_page_member_group_rule_count_summary(0, 'page::member_group.summary.actions');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_page_asset_action_logs
         WHERE account_id = :account_id
           AND action_key = 'complete'
           AND transaction_id > 0"
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_page_member_group_rule_count_summary($count, 'page::member_group.summary.actions');
}

function sr_page_member_group_rule_page_group_paid_view_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageGroupId = (int) ($params['page_group_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageGroupId < 1) {
        return ['matched' => false] + sr_page_member_group_rule_count_summary(0, 'page::member_group.summary.paid_views');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_page_asset_access_logs l
         INNER JOIN sr_pages p ON p.id = l.page_id
         WHERE l.account_id = :account_id
           AND p.page_group_id = :page_group_id
           AND l.access_kind = 'view'
           AND l.transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'page_group_id' => $pageGroupId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_page_member_group_rule_count_summary($count, 'page::member_group.summary.paid_views');
}

function sr_page_member_group_rule_page_group_file_download_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageGroupId = (int) ($params['page_group_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageGroupId < 1) {
        return ['matched' => false] + sr_page_member_group_rule_count_summary(0, 'page::member_group.summary.file_downloads');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_page_asset_access_logs l
         INNER JOIN sr_pages p ON p.id = l.page_id
         WHERE l.account_id = :account_id
           AND p.page_group_id = :page_group_id
           AND l.access_kind = 'download'
           AND l.transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'page_group_id' => $pageGroupId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_page_member_group_rule_count_summary($count, 'page::member_group.summary.file_downloads');
}

function sr_page_member_group_rule_page_group_action_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageGroupId = (int) ($params['page_group_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageGroupId < 1) {
        return ['matched' => false] + sr_page_member_group_rule_count_summary(0, 'page::member_group.summary.actions');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_page_asset_action_logs l
         INNER JOIN sr_pages p ON p.id = l.page_id
         WHERE l.account_id = :account_id
           AND p.page_group_id = :page_group_id
           AND l.action_key = 'complete'
           AND l.transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'page_group_id' => $pageGroupId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_page_member_group_rule_count_summary($count, 'page::member_group.summary.actions');
}

function sr_page_member_group_rule_page_action_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageId = (int) ($params['page_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageId < 1) {
        return ['matched' => false] + sr_page_member_group_rule_count_summary(0, 'page::member_group.summary.actions');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_page_asset_action_logs
         WHERE account_id = :account_id
           AND page_id = :page_id
           AND action_key = 'complete'
           AND transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'page_id' => $pageId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_page_member_group_rule_count_summary($count, 'page::member_group.summary.actions');
}

function sr_page_member_group_evaluate_after_activity(PDO $pdo, int $accountId): void
{
    if ($accountId < 1 || !function_exists('sr_member_group_evaluate_account')) {
        return;
    }

    try {
        sr_member_group_evaluate_account($pdo, $accountId, ['source_module_key' => 'page']);
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'page_member_group_evaluation_failed');
        }
    }
}
