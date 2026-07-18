<?php

declare(strict_types=1);

function sr_survey_group_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_survey_group_key_is_valid(string $groupKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $groupKey) === 1;
}

function sr_survey_groups_table_exists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_survey_groups LIMIT 1');
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function sr_survey_setting_sources_table_exists(PDO $pdo): bool
{
    static $results = [];
    $objectId = spl_object_id($pdo);
    if (array_key_exists($objectId, $results)) {
        return $results[$objectId];
    }
    try {
        $pdo->query('SELECT 1 FROM sr_survey_setting_sources LIMIT 1');
        $results[$objectId] = true;
    } catch (Throwable $exception) {
        $results[$objectId] = false;
    }
    return $results[$objectId];
}

function sr_survey_groups(PDO $pdo, bool $enabledOnly = false): array
{
    if (!sr_survey_groups_table_exists($pdo)) {
        return [];
    }
    $where = $enabledOnly ? "WHERE g.status = 'enabled'" : '';
    return $pdo->query(
        'SELECT g.*, COUNT(s.id) AS item_count
         FROM sr_survey_groups g
         LEFT JOIN sr_survey_forms s ON s.survey_group_id = g.id AND s.deleted_at IS NULL
         ' . $where . '
         GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at
         ORDER BY g.sort_order ASC, g.id ASC'
    )->fetchAll();
}

function sr_survey_admin_group_count(PDO $pdo): int
{
    if (!sr_survey_groups_table_exists($pdo)) {
        return 0;
    }

    $row = $pdo->query('SELECT COUNT(*) AS count_value FROM sr_survey_groups')->fetch();
    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_survey_admin_group_sort_options(): array
{
    return [
        'group_key' => ['columns' => ['g.group_key', 'g.id']],
        'title' => ['columns' => ['g.title', 'g.id']],
        'status' => ['columns' => ['g.status', 'g.id']],
        'item_count' => ['columns' => ['item_count', 'g.id']],
        'sort_order' => ['columns' => ['g.sort_order', 'g.id']],
    ];
}

function sr_survey_admin_group_default_sort(): array
{
    return sr_admin_sort_default('sort_order', 'asc');
}

function sr_survey_admin_groups(PDO $pdo, int $limit, int $offset, array $sort): array
{
    if (!sr_survey_groups_table_exists($pdo)) {
        return [];
    }

    $sql = 'SELECT g.*, COUNT(s.id) AS item_count
            FROM sr_survey_groups g
            LEFT JOIN sr_survey_forms s ON s.survey_group_id = g.id AND s.deleted_at IS NULL
            GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at'
        . sr_admin_sort_order_sql(sr_survey_admin_group_sort_options(), $sort, sr_survey_admin_group_default_sort())
        . ' LIMIT :limit_value OFFSET :offset_value';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('limit_value', max(1, min(1000, $limit)), PDO::PARAM_INT);
    $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_survey_group_by_id(PDO $pdo, int $groupId): ?array
{
    if ($groupId < 1) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_groups WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $groupId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function sr_survey_group_key_exists(PDO $pdo, string $groupKey, int $exceptId = 0): bool
{
    $stmt = $pdo->prepare('SELECT id FROM sr_survey_groups WHERE group_key = :group_key AND id <> :except_id LIMIT 1');
    $stmt->execute(['group_key' => $groupKey, 'except_id' => $exceptId]);
    return is_array($stmt->fetch());
}

function sr_survey_save_group(PDO $pdo, array $values, int $groupId = 0): int
{
    $now = sr_now();
    if ($groupId > 0) {
        $stmt = $pdo->prepare('UPDATE sr_survey_groups SET title = :title, description = :description, status = :status, sort_order = :sort_order, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['title' => (string) $values['title'], 'description' => (string) ($values['description'] ?? ''), 'status' => (string) $values['status'], 'sort_order' => (int) $values['sort_order'], 'updated_at' => $now, 'id' => $groupId]);
        return $groupId;
    }
    $stmt = $pdo->prepare('INSERT INTO sr_survey_groups (group_key, title, description, status, sort_order, created_at, updated_at) VALUES (:group_key, :title, :description, :status, :sort_order, :created_at, :updated_at)');
    $stmt->execute(['group_key' => (string) $values['group_key'], 'title' => (string) $values['title'], 'description' => (string) ($values['description'] ?? ''), 'status' => (string) $values['status'], 'sort_order' => (int) $values['sort_order'], 'created_at' => $now, 'updated_at' => $now]);
    return (int) $pdo->lastInsertId();
}

function sr_survey_delete_group(PDO $pdo, int $groupId): bool
{
    if (!is_array(sr_survey_group_by_id($pdo, $groupId))) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $now = sr_now();
        if (sr_survey_setting_sources_table_exists($pdo)) {
            $pdo->prepare(
                "UPDATE sr_survey_setting_sources
                 SET source = 'item', updated_at = :updated_at
                 WHERE source = 'group'
                   AND survey_id IN (SELECT id FROM sr_survey_forms WHERE survey_group_id = :group_id)"
            )->execute(['updated_at' => $now, 'group_id' => $groupId]);
        }
        $pdo->prepare('UPDATE sr_survey_forms SET survey_group_id = NULL, updated_at = :updated_at WHERE survey_group_id = :group_id')->execute(['updated_at' => $now, 'group_id' => $groupId]);
        $pdo->prepare('DELETE FROM sr_survey_groups WHERE id = :id')->execute(['id' => $groupId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    return true;
}

function sr_survey_setting_scope(string $scope): string
{
    return in_array($scope, ['item', 'group', 'all'], true) ? $scope : 'item';
}

function sr_survey_group_setting_bundles(): array
{
    return [
        'skin_key' => ['skin_key'],
        'research_purpose' => ['research_purpose'],
        'target_population' => ['target_population'],
        'recruitment_method' => ['recruitment_method'],
        'project_brief' => ['project_brief'],
        'sponsor_name' => ['sponsor_name'],
        'research_region' => ['research_region'],
        'research_language' => ['research_language'],
        'fieldwork_method' => ['fieldwork_method'],
        'sample_method' => ['sample_method'],
        'target_sample_size' => ['target_sample_size'],
        'sample_frame' => ['sample_frame'],
        'quota_policy' => ['quota_policy'],
        'response_rate_basis' => ['response_rate_basis'],
        'estimated_minutes' => ['estimated_minutes'],
        'organizer_name' => ['organizer_name'],
        'contact_text' => ['contact_text'],
        'public_listed' => ['public_listed'],
        'robots_policy' => ['robots_policy'],
        'status' => ['status'],
        'starts_at' => ['starts_at'],
        'ends_at' => ['ends_at'],
        'login_required' => ['login_required'],
        'anonymous_allowed' => ['anonymous_allowed'],
        'member_group_keys' => ['member_group_keys_json'],
        'response_limit' => ['response_limit_policy', 'response_limit_period_seconds'],
        'analysis_plan' => ['analysis_plan'],
        'weighting_policy' => ['weighting_policy'],
        'margin_error_note' => ['margin_error_note'],
        'methodology_disclosure' => ['methodology_disclosure'],
        'ethics_note' => ['ethics_note'],
        'sensitive_data_policy' => ['sensitive_data_policy'],
        'recontact_policy' => ['recontact_policy'],
        'withdrawal_policy' => ['withdrawal_policy'],
        'vendor_name' => ['vendor_name'],
        'external_channel_policy' => ['external_channel_policy'],
        'invite_token_policy' => ['invite_token_policy'],
        'consent_required' => ['consent_required'],
        'consent_text' => ['consent_text'],
        'privacy_notice' => ['privacy_notice'],
        'comments_enabled' => ['comments_enabled'],
        'secret_comments_enabled' => ['secret_comments_enabled'],
        'reaction_preset_key' => ['reaction_preset_key'],
        'reaction_comment_preset_key' => ['reaction_comment_preset_key'],
        'comment_editor_key' => ['comment_editor_key'],
        'comment_extra_fields_json' => ['comment_extra_fields_json'],
        'reward' => ['reward_enabled'],
    ];
}

function sr_survey_setting_sources(PDO $pdo, int $surveyId): array
{
    if ($surveyId < 1 || !sr_survey_setting_sources_table_exists($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT setting_key, source FROM sr_survey_setting_sources WHERE survey_id = :survey_id');
    $stmt->execute(['survey_id' => $surveyId]);
    $sources = [];
    foreach ($stmt->fetchAll() as $row) {
        $sources[(string) ($row['setting_key'] ?? '')] = sr_survey_setting_scope((string) ($row['source'] ?? 'item'));
    }
    return $sources;
}

function sr_survey_setting_source(PDO $pdo, int $surveyId, string $settingKey): string
{
    return sr_survey_setting_scope((string) (sr_survey_setting_sources($pdo, $surveyId)[$settingKey] ?? 'item'));
}

function sr_survey_set_setting_source(PDO $pdo, int $surveyId, string $settingKey, string $source): void
{
    $now = sr_now();
    $params = ['survey_id' => $surveyId, 'setting_key' => $settingKey, 'source' => sr_survey_setting_scope($source), 'updated_at' => $now];
    $stmt = $pdo->prepare('UPDATE sr_survey_setting_sources SET source = :source, updated_at = :updated_at WHERE survey_id = :survey_id AND setting_key = :setting_key');
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        return;
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO sr_survey_setting_sources (survey_id, setting_key, source, created_at, updated_at) VALUES (:survey_id, :setting_key, :source, :created_at, :updated_at)');
        $stmt->execute(array_merge($params, ['created_at' => $now]));
    } catch (PDOException $exception) {
        $stmt = $pdo->prepare('UPDATE sr_survey_setting_sources SET source = :source, updated_at = :updated_at WHERE survey_id = :survey_id AND setting_key = :setting_key');
        $stmt->execute($params);
    }
}

function sr_survey_apply_group_setting_scopes(PDO $pdo, int $surveyId, int $groupId, array $values, int $accountId, string $now): void
{
    foreach (sr_survey_group_setting_bundles() as $settingKey => $columns) {
        $scope = sr_survey_setting_scope((string) ($values['source_' . $settingKey] ?? 'item'));
        if ($scope === 'group' && $groupId < 1) {
            continue;
        }
        $where = 'id = :item_id';
        $params = ['item_id' => $surveyId];
        if ($scope === 'group') {
            $where = 'survey_group_id = :group_id AND deleted_at IS NULL';
            $params = ['group_id' => $groupId];
        } elseif ($scope === 'all') {
            $where = 'deleted_at IS NULL';
            $params = [];
        }
        $targetStmt = $pdo->prepare('SELECT id FROM sr_survey_forms WHERE ' . $where);
        $targetStmt->execute($params);
        $targetIds = array_map('intval', array_column($targetStmt->fetchAll(), 'id'));
        if ($targetIds === []) {
            continue;
        }
        $assignments = [];
        $updateValues = [];
        foreach ($columns as $column) {
            $assignments[] = $column . ' = ?';
            if ($column === 'member_group_keys_json') {
                $updateValues[] = sr_survey_member_group_keys_json($values['member_group_keys'] ?? []);
            } elseif ($column === 'comment_extra_fields_json') {
                $updateValues[] = sr_comment_extra_field_definitions_json($values[$column] ?? '[]');
            } elseif ($column === 'starts_at' || $column === 'ends_at') {
                $updateValues[] = sr_survey_clean_admin_datetime((string) ($values[$column] ?? ''));
            } elseif ($column === 'response_limit_period_seconds') {
                $updateValues[] = (string) ($values['response_limit_policy'] ?? '') === 'per_period' ? max(1, (int) ($values[$column] ?? 0)) : null;
            } else {
                $updateValues[] = $values[$column] ?? '';
            }
        }
        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        $stmt = $pdo->prepare('UPDATE sr_survey_forms SET ' . implode(', ', $assignments) . ', updated_by_account_id = ?, updated_at = ? WHERE id IN (' . $placeholders . ')');
        $stmt->execute(array_merge($updateValues, [$accountId, $now], $targetIds));
        if ($settingKey === 'reward') {
            foreach ($targetIds as $targetId) {
                if ($targetId === $surveyId) {
                    continue;
                }
                $pdo->prepare('UPDATE sr_survey_reward_policies SET status = \'disabled\', updated_at = :updated_at WHERE survey_id = :survey_id AND status = \'active\'')->execute(['updated_at' => $now, 'survey_id' => $targetId]);
                $pdo->prepare('INSERT INTO sr_survey_reward_policies (survey_id, status, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, sort_order, settings_json, created_at, updated_at) SELECT :target_id, status, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, sort_order, settings_json, :created_at, :updated_at FROM sr_survey_reward_policies WHERE survey_id = :source_id AND status = \'active\' ORDER BY id DESC LIMIT 1')->execute(['target_id' => $targetId, 'created_at' => $now, 'updated_at' => $now, 'source_id' => $surveyId]);
            }
        }
        foreach ($targetIds as $targetId) {
            sr_survey_set_setting_source($pdo, $targetId, $settingKey, 'item');
        }
        sr_survey_set_setting_source($pdo, $surveyId, $settingKey, $scope);
    }
}
