<?php

declare(strict_types=1);

function sr_community_default_settings(): array
{
    $metadata = sr_module_metadata('community');
    $settings = isset($metadata['settings']) && is_array($metadata['settings']) ? $metadata['settings'] : [];

    return [
        'posts_per_page' => (int) ($settings['posts_per_page'] ?? 20),
        'comments_per_page' => (int) ($settings['comments_per_page'] ?? 50),
        'post_create_window_seconds' => (int) ($settings['post_create_window_seconds'] ?? 300),
        'post_create_limit' => (int) ($settings['post_create_limit'] ?? 10),
        'comment_create_window_seconds' => (int) ($settings['comment_create_window_seconds'] ?? 300),
        'comment_create_limit' => (int) ($settings['comment_create_limit'] ?? 30),
        'report_create_window_seconds' => (int) ($settings['report_create_window_seconds'] ?? 300),
        'report_create_limit' => (int) ($settings['report_create_limit'] ?? 20),
        'message_create_window_seconds' => (int) ($settings['message_create_window_seconds'] ?? 300),
        'message_create_limit' => (int) ($settings['message_create_limit'] ?? 20),
        'attachment_max_bytes' => (int) ($settings['attachment_max_bytes'] ?? $settings['image_upload_max_bytes'] ?? 2097152),
        'image_uploads_enabled' => (bool) ($settings['image_uploads_enabled'] ?? true),
        'file_uploads_enabled' => (bool) ($settings['file_uploads_enabled'] ?? false),
        'file_attachment_max_bytes' => (int) ($settings['file_attachment_max_bytes'] ?? 5242880),
        'file_attachment_max_count' => (int) ($settings['file_attachment_max_count'] ?? 3),
        'file_allowed_extensions' => is_array($settings['file_allowed_extensions'] ?? null)
            ? $settings['file_allowed_extensions']
            : ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp'],
        'level_enabled' => (bool) ($settings['level_enabled'] ?? false),
        'level_auto_recalculate' => (bool) ($settings['level_auto_recalculate'] ?? false),
        'level_post_score' => (int) ($settings['level_post_score'] ?? 10),
        'level_comment_score' => (int) ($settings['level_comment_score'] ?? 2),
        'access_condition_priority' => is_string($settings['access_condition_priority'] ?? null) ? (string) $settings['access_condition_priority'] : 'both_required',
        'message_write_policy' => is_string($settings['message_write_policy'] ?? null) ? (string) $settings['message_write_policy'] : 'member',
        'message_write_group_keys' => $settings['message_write_group_keys'] ?? [],
        'message_write_min_level' => (int) ($settings['message_write_min_level'] ?? 0),
        'theme_key' => is_string($settings['theme_key'] ?? null) ? (string) $settings['theme_key'] : 'basic',
    ];
}

function sr_community_max_level_value(): int
{
    return 10;
}

function sr_community_normalize_level_value(mixed $value): int
{
    return min(sr_community_max_level_value(), max(0, (int) $value));
}

function sr_community_settings(PDO $pdo): array
{
    return sr_community_normalize_settings(sr_module_settings($pdo, 'community'));
}

function sr_community_normalize_settings(array $settings): array
{
    $settings = array_merge(sr_community_default_settings(), $settings);
    $settings['posts_per_page'] = min(100, max(1, (int) ($settings['posts_per_page'] ?? 20)));
    $settings['comments_per_page'] = min(100, max(1, (int) ($settings['comments_per_page'] ?? 50)));
    $settings['post_create_window_seconds'] = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    $settings['post_create_limit'] = min(100, max(1, (int) ($settings['post_create_limit'] ?? 10)));
    $settings['comment_create_window_seconds'] = min(86400, max(60, (int) ($settings['comment_create_window_seconds'] ?? 300)));
    $settings['comment_create_limit'] = min(300, max(1, (int) ($settings['comment_create_limit'] ?? 30)));
    $settings['report_create_window_seconds'] = min(86400, max(60, (int) ($settings['report_create_window_seconds'] ?? 300)));
    $settings['report_create_limit'] = min(200, max(1, (int) ($settings['report_create_limit'] ?? 20)));
    $settings['message_create_window_seconds'] = min(86400, max(60, (int) ($settings['message_create_window_seconds'] ?? 300)));
    $settings['message_create_limit'] = min(200, max(1, (int) ($settings['message_create_limit'] ?? 20)));
    $settings['attachment_max_bytes'] = min(10485760, max(1024, (int) ($settings['attachment_max_bytes'] ?? 2097152)));
    $settings['image_uploads_enabled'] = sr_community_bool_setting($settings['image_uploads_enabled'] ?? true);
    $settings['file_uploads_enabled'] = sr_community_bool_setting($settings['file_uploads_enabled'] ?? false);
    $settings['file_attachment_max_bytes'] = min(20971520, max(1024, (int) ($settings['file_attachment_max_bytes'] ?? 5242880)));
    $settings['file_attachment_max_count'] = min(5, max(0, (int) ($settings['file_attachment_max_count'] ?? 3)));
    $settings['file_allowed_extensions'] = sr_community_normalize_file_extensions(
        is_array($settings['file_allowed_extensions'] ?? null) ? $settings['file_allowed_extensions'] : (string) ($settings['file_allowed_extensions'] ?? '')
    );
    $settings['level_enabled'] = sr_community_bool_setting($settings['level_enabled'] ?? false);
    $settings['level_auto_recalculate'] = sr_community_bool_setting($settings['level_auto_recalculate'] ?? false);
    $settings['level_post_score'] = min(10000, max(0, (int) ($settings['level_post_score'] ?? 10)));
    $settings['level_comment_score'] = min(10000, max(0, (int) ($settings['level_comment_score'] ?? 2)));
    $settings['access_condition_priority'] = sr_community_access_condition_priority((string) ($settings['access_condition_priority'] ?? ''));
    $settings['message_write_policy'] = sr_community_message_write_policy((string) ($settings['message_write_policy'] ?? ''));
    $settings['message_write_group_keys'] = sr_community_group_keys_from_setting($settings['message_write_group_keys'] ?? []);
    $settings['message_write_min_level'] = sr_community_normalize_level_value($settings['message_write_min_level'] ?? 0);
    $settings['theme_key'] = sr_community_theme_key($settings);

    return $settings;
}

function sr_community_bool_setting(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_access_condition_priority_values(): array
{
    return ['both_required', 'group_first', 'level_first'];
}

function sr_community_access_condition_priority(string $value): string
{
    return in_array($value, sr_community_access_condition_priority_values(), true) ? $value : 'both_required';
}

function sr_community_message_write_policy_values(): array
{
    return ['member', 'group', 'disabled'];
}

function sr_community_message_write_policy(string $value): string
{
    return in_array($value, sr_community_message_write_policy_values(), true) ? $value : 'member';
}

function sr_community_group_keys_from_setting(mixed $value): array
{
    if (is_array($value)) {
        return sr_community_normalize_board_group_keys($value);
    }

    $value = trim((string) $value);
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return sr_community_normalize_board_group_keys($decoded);
    }

    $rawKeys = preg_split('/[\s,]+/', $value);
    return sr_community_normalize_board_group_keys(is_array($rawKeys) ? $rawKeys : []);
}

function sr_community_level_tables_exist(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_community_levels LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_community_account_levels LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_community_level_logs LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_levels(PDO $pdo): array
{
    if (!sr_community_level_tables_exist($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT *
         FROM sr_community_levels
         ORDER BY level_value ASC, id ASC"
    );

    return $stmt->fetchAll();
}

function sr_community_enabled_levels(PDO $pdo): array
{
    if (!sr_community_level_tables_exist($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT *
         FROM sr_community_levels
         WHERE status = 'enabled'
         ORDER BY level_value ASC, id ASC"
    );

    return $stmt->fetchAll();
}

function sr_community_account_level_snapshot(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !sr_community_level_tables_exist($pdo)) {
        return sr_community_empty_account_level_snapshot($accountId);
    }

    $stmt = $pdo->prepare(
        'SELECT account_id, level_value, score_value, post_count, comment_count, evaluated_at, created_at, updated_at
         FROM sr_community_account_levels
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $snapshot = $stmt->fetch();

    return is_array($snapshot) ? $snapshot : sr_community_empty_account_level_snapshot($accountId);
}

function sr_community_empty_account_level_snapshot(int $accountId): array
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

function sr_community_account_meets_min_level(PDO $pdo, int $accountId, int $minLevel, ?array $settings = null): bool
{
    $minLevel = max(0, $minLevel);
    if ($minLevel < 1) {
        return true;
    }

    $settings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_settings($pdo);
    if (empty($settings['level_enabled'])) {
        return false;
    }

    $snapshot = sr_community_account_level_snapshot($pdo, $accountId);
    return (int) ($snapshot['level_value'] ?? 0) >= $minLevel;
}

function sr_community_account_satisfies_access(PDO $pdo, int $accountId, array $context): array
{
    $settings = sr_community_normalize_settings(is_array($context['settings'] ?? null) ? $context['settings'] : sr_community_settings($pdo));
    $groupKeys = sr_community_normalize_board_group_keys(is_array($context['group_keys'] ?? null) ? $context['group_keys'] : []);
    $minLevel = sr_community_normalize_level_value($context['min_level'] ?? 0);
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

    $groupMatched = !$hasGroupCondition ? true : sr_member_account_in_any_group($pdo, $accountId, $groupKeys);
    $levelMatched = !$hasLevelCondition ? true : sr_community_account_meets_min_level($pdo, $accountId, $minLevel, $settings);
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

function sr_community_recalculate_account_level(PDO $pdo, int $accountId, ?array $settings = null, string $reasonKey = 'activity_changed'): array
{
    if ($accountId < 1 || !sr_community_level_tables_exist($pdo)) {
        return sr_community_empty_account_level_snapshot($accountId);
    }

    $settings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_settings($pdo);
    if (empty($settings['level_enabled'])) {
        return sr_community_account_level_snapshot($pdo, $accountId);
    }

    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM sr_community_posts WHERE author_account_id = :post_account_id AND status = 'published') AS post_count,
            (SELECT COUNT(*) FROM sr_community_comments WHERE author_account_id = :comment_account_id AND status = 'published') AS comment_count"
    );
    $stmt->execute([
        'post_account_id' => $accountId,
        'comment_account_id' => $accountId,
    ]);
    $row = $stmt->fetch();
    $postCount = is_array($row) ? (int) $row['post_count'] : 0;
    $commentCount = is_array($row) ? (int) $row['comment_count'] : 0;
    $scoreValue = ($postCount * (int) $settings['level_post_score']) + ($commentCount * (int) $settings['level_comment_score']);
    $levelValue = sr_community_level_value_for_score($pdo, $scoreValue);
    if (sr_community_account_is_owner($pdo, $accountId)) {
        $levelValue = sr_community_max_level_value();
    }
    $before = sr_community_account_level_snapshot($pdo, $accountId);
    $now = sr_now();

    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_account_levels
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
        sr_community_log_level_change($pdo, $accountId, $before, [
            'level_value' => $levelValue,
            'score_value' => $scoreValue,
        ], $reasonKey);
    }

    return sr_community_account_level_snapshot($pdo, $accountId);
}

function sr_community_account_is_owner(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM sr_admin_account_roles WHERE account_id = :account_id AND role_key = 'owner' LIMIT 1");
        $stmt->execute(['account_id' => $accountId]);
        return is_array($stmt->fetch());
    } catch (Throwable $exception) {
        return false;
    }
}

function sr_community_maybe_recalculate_account_level(PDO $pdo, int $accountId, ?array $settings = null, string $reasonKey = 'activity_changed'): array
{
    $settings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_settings($pdo);
    if (empty($settings['level_auto_recalculate'])) {
        return sr_community_account_level_snapshot($pdo, $accountId);
    }

    return sr_community_recalculate_account_level($pdo, $accountId, $settings, $reasonKey);
}

function sr_community_level_value_for_score(PDO $pdo, int $scoreValue): int
{
    $levelValue = 0;
    foreach (sr_community_enabled_levels($pdo) as $level) {
        if ((int) ($level['min_score'] ?? 0) <= $scoreValue) {
            $levelValue = max($levelValue, (int) $level['level_value']);
        }
    }

    return $levelValue;
}

function sr_community_update_level_min_scores(PDO $pdo, array $minScoresById): int
{
    if (!sr_community_level_tables_exist($pdo)) {
        return 0;
    }

    $levels = sr_community_levels($pdo);
    $updates = [];
    $lastMinScore = 0;
    foreach ($levels as $level) {
        $levelId = (int) ($level['id'] ?? 0);
        if ($levelId < 1 || !array_key_exists($levelId, $minScoresById)) {
            continue;
        }

        $minScore = (int) $minScoresById[$levelId];
        if ($minScore < $lastMinScore) {
            throw new InvalidArgumentException('레벨 최소 점수는 낮은 레벨부터 같거나 커야 합니다.');
        }

        $lastMinScore = $minScore;
        if ($minScore !== (int) ($level['min_score'] ?? 0)) {
            $updates[$levelId] = $minScore;
        }
    }

    if ($updates === []) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_levels
         SET min_score = :min_score, updated_at = :updated_at
         WHERE id = :id'
    );
    $now = sr_now();
    foreach ($updates as $levelId => $minScore) {
        $stmt->execute([
            'min_score' => $minScore,
            'updated_at' => $now,
            'id' => $levelId,
        ]);
    }

    return count($updates);
}

function sr_community_log_level_change(PDO $pdo, int $accountId, array $before, array $after, string $reasonKey): void
{
    if (!sr_community_level_tables_exist($pdo)) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_level_logs
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
        'created_at' => sr_now(),
    ]);
}

function sr_community_recalculate_recent_account_levels(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    if (!sr_community_level_tables_exist($pdo)) {
        return ['accounts' => 0];
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM sr_member_accounts
         WHERE status IN ('active', 'pending')
         ORDER BY id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $settings = sr_community_settings($pdo);
    $count = 0;
    foreach ($stmt->fetchAll() as $row) {
        sr_community_recalculate_account_level($pdo, (int) $row['id'], $settings, 'admin_recalculate');
        $count++;
    }

    return ['accounts' => $count];
}
