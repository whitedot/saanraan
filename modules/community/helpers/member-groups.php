<?php

declare(strict_types=1);

function sr_community_member_group_rule_board_options(PDO $pdo): array
{
    $options = [];
    foreach (sr_community_enabled_boards($pdo) as $board) {
        $options[] = [
            'value' => (string) $board['id'],
            'label' => (string) $board['title'],
        ];
    }

    return $options;
}

function sr_community_member_group_evaluation_metadata(array $summary): array
{
    return [
        'group_rules_evaluated' => (int) ($summary['evaluated'] ?? 0),
        'group_memberships_granted' => (int) ($summary['granted'] ?? 0),
        'group_memberships_revoked' => (int) ($summary['revoked'] ?? 0),
    ];
}

function sr_community_member_group_rule_post_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $boardId = (int) ($params['board_id'] ?? 0);
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1 || $boardId < 1) {
        return ['matched' => false, 'metric' => 0, 'summary' => sr_t('community::member_group.summary.posts', ['count' => '0'])];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_community_posts
         WHERE author_account_id = :account_id
           AND board_id = :board_id
           AND status = 'published'"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'board_id' => $boardId,
    ]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return [
        'matched' => $count >= $minCount,
        'metric' => $count,
        'summary' => sr_t('community::member_group.summary.posts', ['count' => (string) $count]),
    ];
}

function sr_community_member_group_rule_total_post_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1) {
        return ['matched' => false, 'metric' => 0, 'summary' => sr_t('community::member_group.summary.posts', ['count' => '0'])];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_community_posts
         WHERE author_account_id = :account_id
           AND status = 'published'"
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return [
        'matched' => $count >= $minCount,
        'metric' => $count,
        'summary' => sr_t('community::member_group.summary.posts', ['count' => (string) $count]),
    ];
}

function sr_community_member_group_rule_comment_count_at_least(PDO $pdo, int $accountId, array $params): array
{
    $minCount = max(1, (int) ($params['min_count'] ?? 1));
    if ($accountId < 1) {
        return ['matched' => false, 'metric' => 0, 'summary' => sr_t('community::member_group.summary.comments', ['count' => '0'])];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_community_comments
         WHERE author_account_id = :account_id
           AND status = 'published'"
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();
    $count = is_array($row) ? (int) $row['count_value'] : 0;

    return [
        'matched' => $count >= $minCount,
        'metric' => $count,
        'summary' => sr_t('community::member_group.summary.comments', ['count' => (string) $count]),
    ];
}

function sr_community_member_group_rule_level_at_least(PDO $pdo, int $accountId, array $params): array
{
    $minLevel = sr_community_normalize_level_value($params['min_level'] ?? 0);
    $snapshot = sr_community_account_level_snapshot($pdo, $accountId);
    $level = (int) ($snapshot['level_value'] ?? 0);

    return [
        'matched' => $level >= $minLevel,
        'metric' => $level,
        'summary' => sr_t('community::member_group.summary.level', ['level' => (string) $level]),
    ];
}
