<?php

declare(strict_types=1);

function sr_content_member_group_rule_count_summary(int $count, string $translationKey): array
{
    return [
        'metric' => $count,
        'summary' => sr_t($translationKey, ['count' => (string) $count]),
    ];
}

function sr_content_member_group_rule_content_options(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT p.id, p.slug, p.title, g.title AS content_group_title
         FROM sr_content_items p
         LEFT JOIN sr_content_groups g ON g.id = p.content_group_id
         WHERE p.status = 'published'
         ORDER BY COALESCE(g.sort_order, 1000000) ASC, g.id ASC, p.title ASC, p.id ASC"
    );

    $options = [];
    foreach ($stmt->fetchAll() as $page) {
        $groupTitle = trim((string) ($page['content_group_title'] ?? ''));
        $label = $groupTitle !== '' ? $groupTitle . ' / ' . (string) $page['title'] : (string) $page['title'];
        $options[] = [
            'value' => (string) $page['id'],
            'label' => $label . ' (' . (string) $page['slug'] . ')',
        ];
    }

    return $options;
}

function sr_content_member_group_rule_content_group_options(PDO $pdo): array
{
    $options = [];
    foreach (sr_content_groups($pdo) as $group) {
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

function sr_content_member_group_rule_paid_view_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1) {
        return ['matched' => false] + sr_content_member_group_rule_count_summary(0, 'content::member_group.summary.paid_views');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_content_asset_access_logs
         WHERE account_id = :account_id
           AND access_kind = 'view'
           AND transaction_id > 0"
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_content_member_group_rule_count_summary($count, 'content::member_group.summary.paid_views');
}

function sr_content_member_group_rule_file_download_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1) {
        return ['matched' => false] + sr_content_member_group_rule_count_summary(0, 'content::member_group.summary.file_downloads');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_content_asset_access_logs
         WHERE account_id = :account_id
           AND access_kind = 'download'
           AND transaction_id > 0"
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_content_member_group_rule_count_summary($count, 'content::member_group.summary.file_downloads');
}

function sr_content_member_group_rule_content_paid_view_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageId = (int) ($params['content_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageId < 1) {
        return ['matched' => false] + sr_content_member_group_rule_count_summary(0, 'content::member_group.summary.paid_views');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_content_asset_access_logs
         WHERE account_id = :account_id
           AND content_id = :content_id
           AND access_kind = 'view'
           AND transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'content_id' => $pageId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_content_member_group_rule_count_summary($count, 'content::member_group.summary.paid_views');
}

function sr_content_member_group_rule_content_file_download_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageId = (int) ($params['content_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageId < 1) {
        return ['matched' => false] + sr_content_member_group_rule_count_summary(0, 'content::member_group.summary.file_downloads');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_content_asset_access_logs
         WHERE account_id = :account_id
           AND content_id = :content_id
           AND access_kind = 'download'
           AND transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'content_id' => $pageId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_content_member_group_rule_count_summary($count, 'content::member_group.summary.file_downloads');
}

function sr_content_member_group_rule_action_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1) {
        return ['matched' => false] + sr_content_member_group_rule_count_summary(0, 'content::member_group.summary.actions');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_content_asset_action_logs
         WHERE account_id = :account_id
           AND action_key = 'complete'
           AND transaction_id > 0"
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_content_member_group_rule_count_summary($count, 'content::member_group.summary.actions');
}

function sr_content_member_group_rule_content_group_paid_view_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageGroupId = (int) ($params['content_group_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageGroupId < 1) {
        return ['matched' => false] + sr_content_member_group_rule_count_summary(0, 'content::member_group.summary.paid_views');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_content_asset_access_logs l
         INNER JOIN sr_content_items p ON p.id = l.content_id
         WHERE l.account_id = :account_id
           AND p.content_group_id = :content_group_id
           AND l.access_kind = 'view'
           AND l.transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'content_group_id' => $pageGroupId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_content_member_group_rule_count_summary($count, 'content::member_group.summary.paid_views');
}

function sr_content_member_group_rule_content_group_file_download_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageGroupId = (int) ($params['content_group_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageGroupId < 1) {
        return ['matched' => false] + sr_content_member_group_rule_count_summary(0, 'content::member_group.summary.file_downloads');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_content_asset_access_logs l
         INNER JOIN sr_content_items p ON p.id = l.content_id
         WHERE l.account_id = :account_id
           AND p.content_group_id = :content_group_id
           AND l.access_kind = 'download'
           AND l.transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'content_group_id' => $pageGroupId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_content_member_group_rule_count_summary($count, 'content::member_group.summary.file_downloads');
}

function sr_content_member_group_rule_content_group_action_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageGroupId = (int) ($params['content_group_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageGroupId < 1) {
        return ['matched' => false] + sr_content_member_group_rule_count_summary(0, 'content::member_group.summary.actions');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_content_asset_action_logs l
         INNER JOIN sr_content_items p ON p.id = l.content_id
         WHERE l.account_id = :account_id
           AND p.content_group_id = :content_group_id
           AND l.action_key = 'complete'
           AND l.transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'content_group_id' => $pageGroupId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_content_member_group_rule_count_summary($count, 'content::member_group.summary.actions');
}

function sr_content_member_group_rule_content_action_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $pageId = (int) ($params['content_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $pageId < 1) {
        return ['matched' => false] + sr_content_member_group_rule_count_summary(0, 'content::member_group.summary.actions');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_content_asset_action_logs
         WHERE account_id = :account_id
           AND content_id = :content_id
           AND action_key = 'complete'
           AND transaction_id > 0"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'content_id' => $pageId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return ['matched' => $count >= $minCount] + sr_content_member_group_rule_count_summary($count, 'content::member_group.summary.actions');
}

function sr_content_member_group_evaluate_after_activity(PDO $pdo, int $accountId): void
{
    if ($accountId < 1 || !function_exists('sr_member_group_evaluate_account')) {
        return;
    }

    try {
        sr_member_group_evaluate_account($pdo, $accountId, ['source_module_key' => 'content']);
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_member_group_evaluation_failed');
        }
    }
}
