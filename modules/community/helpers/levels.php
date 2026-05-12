<?php

declare(strict_types=1);

function toy_community_default_settings(): array
{
    return [
        'level_enabled' => false,
        'level_post_score' => 10,
        'level_comment_score' => 2,
        'access_condition_priority' => 'both_required',
        'message_write_policy' => 'member',
        'message_write_group_keys' => [],
        'message_write_min_level' => 0,
        'theme_key' => 'basic',
    ];
}

function toy_community_max_level_value(): int
{
    return 10;
}

function toy_community_normalize_level_value(mixed $value): int
{
    return min(toy_community_max_level_value(), max(0, (int) $value));
}

function toy_community_settings(PDO $pdo): array
{
    return toy_community_normalize_settings(toy_module_settings($pdo, 'community'));
}

function toy_community_normalize_settings(array $settings): array
{
    $settings = array_merge(toy_community_default_settings(), $settings);
    $settings['level_enabled'] = toy_community_bool_setting($settings['level_enabled'] ?? false);
    $settings['level_post_score'] = min(10000, max(0, (int) ($settings['level_post_score'] ?? 10)));
    $settings['level_comment_score'] = min(10000, max(0, (int) ($settings['level_comment_score'] ?? 2)));
    $settings['access_condition_priority'] = toy_community_access_condition_priority((string) ($settings['access_condition_priority'] ?? ''));
    $settings['message_write_policy'] = toy_community_message_write_policy((string) ($settings['message_write_policy'] ?? ''));
    $settings['message_write_group_keys'] = toy_community_group_keys_from_setting($settings['message_write_group_keys'] ?? []);
    $settings['message_write_min_level'] = toy_community_normalize_level_value($settings['message_write_min_level'] ?? 0);
    $settings['theme_key'] = toy_community_theme_key($settings);

    return $settings;
}

function toy_community_bool_setting(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function toy_community_access_condition_priority_values(): array
{
    return ['both_required', 'group_first', 'level_first'];
}

function toy_community_access_condition_priority(string $value): string
{
    return in_array($value, toy_community_access_condition_priority_values(), true) ? $value : 'both_required';
}

function toy_community_message_write_policy_values(): array
{
    return ['member', 'group', 'disabled'];
}

function toy_community_message_write_policy(string $value): string
{
    return in_array($value, toy_community_message_write_policy_values(), true) ? $value : 'member';
}

function toy_community_group_keys_from_setting(mixed $value): array
{
    if (is_array($value)) {
        return toy_community_normalize_board_group_keys($value);
    }

    $value = trim((string) $value);
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return toy_community_normalize_board_group_keys($decoded);
    }

    $rawKeys = preg_split('/[\s,]+/', $value);
    return toy_community_normalize_board_group_keys(is_array($rawKeys) ? $rawKeys : []);
}

function toy_community_level_tables_exist(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM toy_community_levels LIMIT 1');
        $pdo->query('SELECT 1 FROM toy_community_account_levels LIMIT 1');
        $pdo->query('SELECT 1 FROM toy_community_level_logs LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function toy_community_levels(PDO $pdo): array
{
    if (!toy_community_level_tables_exist($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT *
         FROM toy_community_levels
         ORDER BY level_value ASC, id ASC"
    );

    return $stmt->fetchAll();
}

function toy_community_enabled_levels(PDO $pdo): array
{
    if (!toy_community_level_tables_exist($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT *
         FROM toy_community_levels
         WHERE status = 'enabled'
         ORDER BY level_value ASC, id ASC"
    );

    return $stmt->fetchAll();
}

function toy_community_account_level_snapshot(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !toy_community_level_tables_exist($pdo)) {
        return toy_community_empty_account_level_snapshot($accountId);
    }

    $stmt = $pdo->prepare(
        'SELECT account_id, level_value, score_value, post_count, comment_count, evaluated_at, created_at, updated_at
         FROM toy_community_account_levels
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $snapshot = $stmt->fetch();

    return is_array($snapshot) ? $snapshot : toy_community_empty_account_level_snapshot($accountId);
}

function toy_community_empty_account_level_snapshot(int $accountId): array
{
    return [
        'account_id' => $accountId,
        'level_value' => 0,
        'score_value' => 0,
        'post_count' => 0,
        'comment_count' => 0,
        'evaluated_at' => null,
        'created_at' => null,
        'updated_at' => null,
    ];
}

function toy_community_account_meets_min_level(PDO $pdo, int $accountId, int $minLevel, ?array $settings = null): bool
{
    $minLevel = max(0, $minLevel);
    if ($minLevel < 1) {
        return true;
    }

    $settings = is_array($settings) ? toy_community_normalize_settings($settings) : toy_community_settings($pdo);
    if (empty($settings['level_enabled'])) {
        return false;
    }

    $snapshot = toy_community_account_level_snapshot($pdo, $accountId);
    return (int) ($snapshot['level_value'] ?? 0) >= $minLevel;
}

function toy_community_account_satisfies_access(PDO $pdo, int $accountId, array $context): array
{
    $settings = toy_community_normalize_settings(is_array($context['settings'] ?? null) ? $context['settings'] : toy_module_settings($pdo, 'community'));
    $groupKeys = toy_community_normalize_board_group_keys(is_array($context['group_keys'] ?? null) ? $context['group_keys'] : []);
    $minLevel = toy_community_normalize_level_value($context['min_level'] ?? 0);
    $groupRequired = !empty($context['group_required']);

    if ($accountId < 1) {
        return [
            'allowed' => false,
            'reason_key' => 'login_required',
            'matched_by' => '',
            'group_matched' => false,
            'level_matched' => false,
        ];
    }

    if ($groupRequired && $groupKeys === []) {
        return [
            'allowed' => false,
            'reason_key' => 'group_config_missing',
            'matched_by' => '',
            'group_matched' => false,
            'level_matched' => false,
        ];
    }

    $hasGroupCondition = $groupRequired || $groupKeys !== [];
    $hasLevelCondition = $minLevel > 0;
    if (!$hasGroupCondition && !$hasLevelCondition) {
        return [
            'allowed' => true,
            'reason_key' => '',
            'matched_by' => 'member',
            'group_matched' => false,
            'level_matched' => false,
        ];
    }

    $groupMatched = !$hasGroupCondition ? true : toy_member_account_in_any_group($pdo, $accountId, $groupKeys);
    $levelMatched = !$hasLevelCondition ? true : toy_community_account_meets_min_level($pdo, $accountId, $minLevel, $settings);
    $priority = (string) $settings['access_condition_priority'];

    if ($priority === 'group_first') {
        $allowed = ($hasGroupCondition && $groupMatched) || ($hasLevelCondition && $levelMatched);
        $matchedBy = $hasGroupCondition && $groupMatched ? 'group' : ($hasLevelCondition && $levelMatched ? 'level' : '');
    } elseif ($priority === 'level_first') {
        $allowed = ($hasLevelCondition && $levelMatched) || ($hasGroupCondition && $groupMatched);
        $matchedBy = $hasLevelCondition && $levelMatched ? 'level' : ($hasGroupCondition && $groupMatched ? 'group' : '');
    } else {
        $allowed = $groupMatched && $levelMatched;
        $matchedBy = $allowed ? ($hasGroupCondition && $hasLevelCondition ? 'group_level' : ($hasGroupCondition ? 'group' : 'level')) : '';
    }

    return [
        'allowed' => $allowed,
        'reason_key' => $allowed ? '' : 'access_condition_not_met',
        'matched_by' => $matchedBy,
        'group_matched' => $hasGroupCondition && $groupMatched,
        'level_matched' => $hasLevelCondition && $levelMatched,
    ];
}

function toy_community_recalculate_account_level(PDO $pdo, int $accountId, ?array $settings = null, string $reasonKey = 'activity_changed'): array
{
    if ($accountId < 1 || !toy_community_level_tables_exist($pdo)) {
        return toy_community_empty_account_level_snapshot($accountId);
    }

    $settings = is_array($settings) ? toy_community_normalize_settings($settings) : toy_community_settings($pdo);
    if (empty($settings['level_enabled'])) {
        return toy_community_account_level_snapshot($pdo, $accountId);
    }

    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM toy_community_posts WHERE author_account_id = :post_account_id AND status = 'published') AS post_count,
            (SELECT COUNT(*) FROM toy_community_comments WHERE author_account_id = :comment_account_id AND status = 'published') AS comment_count"
    );
    $stmt->execute([
        'post_account_id' => $accountId,
        'comment_account_id' => $accountId,
    ]);
    $row = $stmt->fetch();
    $postCount = is_array($row) ? (int) $row['post_count'] : 0;
    $commentCount = is_array($row) ? (int) $row['comment_count'] : 0;
    $scoreValue = ($postCount * (int) $settings['level_post_score']) + ($commentCount * (int) $settings['level_comment_score']);
    $levelValue = toy_community_level_value_for_score($pdo, $scoreValue);
    $before = toy_community_account_level_snapshot($pdo, $accountId);
    $now = toy_now();

    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_account_levels
            (account_id, level_value, score_value, post_count, comment_count, evaluated_at, created_at, updated_at)
         VALUES
            (:account_id, :level_value, :score_value, :post_count, :comment_count, :evaluated_at, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            level_value = VALUES(level_value),
            score_value = VALUES(score_value),
            post_count = VALUES(post_count),
            comment_count = VALUES(comment_count),
            evaluated_at = VALUES(evaluated_at),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'level_value' => $levelValue,
        'score_value' => $scoreValue,
        'post_count' => $postCount,
        'comment_count' => $commentCount,
        'evaluated_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    if ((int) ($before['level_value'] ?? 0) !== $levelValue || (int) ($before['score_value'] ?? 0) !== $scoreValue) {
        toy_community_log_level_change($pdo, $accountId, $before, [
            'level_value' => $levelValue,
            'score_value' => $scoreValue,
        ], $reasonKey);
    }

    return toy_community_account_level_snapshot($pdo, $accountId);
}

function toy_community_level_value_for_score(PDO $pdo, int $scoreValue): int
{
    $levelValue = 0;
    foreach (toy_community_enabled_levels($pdo) as $level) {
        if ((int) ($level['min_score'] ?? 0) <= $scoreValue) {
            $levelValue = max($levelValue, (int) $level['level_value']);
        }
    }

    return $levelValue;
}

function toy_community_log_level_change(PDO $pdo, int $accountId, array $before, array $after, string $reasonKey): void
{
    if (!toy_community_level_tables_exist($pdo)) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_level_logs
            (account_id, old_level_value, new_level_value, old_score_value, new_score_value, reason_key, created_at)
         VALUES
            (:account_id, :old_level_value, :new_level_value, :old_score_value, :new_score_value, :reason_key, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'old_level_value' => (int) ($before['level_value'] ?? 0),
        'new_level_value' => (int) ($after['level_value'] ?? 0),
        'old_score_value' => (int) ($before['score_value'] ?? 0),
        'new_score_value' => (int) ($after['score_value'] ?? 0),
        'reason_key' => preg_match('/\A[a-z][a-z0-9_]{0,59}\z/', $reasonKey) === 1 ? $reasonKey : 'activity_changed',
        'created_at' => toy_now(),
    ]);
}

function toy_community_recalculate_recent_account_levels(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    if (!toy_community_level_tables_exist($pdo)) {
        return ['accounts' => 0];
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM toy_member_accounts
         WHERE status IN ('active', 'pending')
         ORDER BY id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $settings = toy_community_settings($pdo);
    $count = 0;
    foreach ($stmt->fetchAll() as $row) {
        toy_community_recalculate_account_level($pdo, (int) $row['id'], $settings, 'admin_recalculate');
        $count++;
    }

    return ['accounts' => $count];
}
