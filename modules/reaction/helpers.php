<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once SR_ROOT . '/modules/reaction/helpers/admin.php';

function sr_reaction_allowed_target_map(): array
{
    return [
        'content/content' => true,
        'content/comment' => true,
        'community/post' => true,
        'community/comment' => true,
        'quiz/quiz_set' => true,
        'quiz/comment' => true,
        'survey/survey_form' => true,
        'survey/comment' => true,
    ];
}

function sr_reaction_clean_key(string $value, int $maxLength = 80): string
{
    $value = strtolower(trim($value));
    if ($value === '' || preg_match('/\A[a-z][a-z0-9_]{0,' . max(0, $maxLength - 1) . '}\z/', $value) !== 1) {
        return '';
    }

    return $value;
}

function sr_reaction_target_id(string $value): string
{
    $value = trim($value);
    return preg_match('/\A[1-9][0-9]*\z/', $value) === 1 ? $value : '';
}

function sr_reaction_target_key(string $targetModule, string $targetType): string
{
    $targetModule = sr_reaction_clean_key($targetModule, 60);
    $targetType = sr_reaction_clean_key($targetType, 60);
    if ($targetModule === '' || $targetType === '') {
        return '';
    }

    $key = $targetModule . '/' . $targetType;
    return isset(sr_reaction_allowed_target_map()[$key]) ? $key : '';
}

function sr_reaction_lock_clause(PDO $pdo): string
{
    try {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    } catch (Throwable) {
        return ' FOR UPDATE';
    }
}

function sr_reaction_tables_available(PDO $pdo): bool
{
    static $availableByConnection = [];
    $cacheKey = (string) spl_object_id($pdo);
    if (array_key_exists($cacheKey, $availableByConnection)) {
        return $availableByConnection[$cacheKey];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_reaction_definitions LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_reaction_presets LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_reaction_preset_items LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_reaction_records LIMIT 1');
    } catch (Throwable) {
        $availableByConnection[$cacheKey] = false;
        return $availableByConnection[$cacheKey];
    }

    $availableByConnection[$cacheKey] = true;
    return $availableByConnection[$cacheKey];
}

function sr_reaction_delete_target_records(PDO $pdo, string $targetModule, string $targetType, array $targetIds): int
{
    $targetKey = sr_reaction_target_key($targetModule, $targetType);
    if ($targetKey === '' || !sr_reaction_tables_available($pdo)) {
        return 0;
    }

    $cleanIds = [];
    foreach ($targetIds as $targetId) {
        $cleanId = sr_reaction_target_id((string) $targetId);
        if ($cleanId !== '') {
            $cleanIds[$cleanId] = $cleanId;
        }
    }
    if ($cleanIds === []) {
        return 0;
    }

    $placeholders = [];
    $params = [
        'target_module' => sr_reaction_clean_key($targetModule, 60),
        'target_type' => sr_reaction_clean_key($targetType, 60),
    ];
    foreach (array_values($cleanIds) as $index => $targetId) {
        $key = 'target_id_' . (string) $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $targetId;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM sr_reaction_records
         WHERE target_module = :target_module
           AND target_type = :target_type
           AND target_id IN (' . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($params);

    return $stmt->rowCount();
}

function sr_reaction_rate_limits_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_rate_limits LIMIT 1');
        $exists = true;
    } catch (Throwable) {
        $exists = false;
    }

    return $exists;
}

function sr_reaction_write_rate_limit_window_seconds(PDO $pdo): int
{
    return min(3600, max(10, (int) sr_site_setting($pdo, 'reaction_write_window_seconds', '60')));
}

function sr_reaction_write_rate_limited(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1 || !sr_reaction_rate_limits_table_exists($pdo)) {
        return false;
    }

    $limit = min(1000, max(1, (int) sr_site_setting($pdo, 'reaction_write_account_limit', '120')));
    return sr_rate_limit_count($pdo, 'reaction.write.account', (string) $accountId, sr_reaction_write_rate_limit_window_seconds($pdo)) >= $limit;
}

function sr_reaction_record_write_rate_limit(PDO $pdo, int $accountId): void
{
    if ($accountId < 1 || !sr_reaction_rate_limits_table_exists($pdo)) {
        return;
    }

    sr_rate_limit_increment($pdo, 'reaction.write.account', (string) $accountId, sr_reaction_write_rate_limit_window_seconds($pdo));
}

function sr_reaction_normalize_target(array $target, string $targetModule, string $targetType, string $targetId): array
{
    $status = (string) ($target['status'] ?? 'broken');
    if (!in_array($status, ['active', 'private', 'deleted', 'broken'], true)) {
        $status = 'broken';
    }

    $canView = array_key_exists('can_view', $target)
        ? (bool) $target['can_view']
        : ($status === 'active' && !empty($target['public_url']));
    $canWrite = array_key_exists('can_write', $target)
        ? (bool) $target['can_write']
        : ($status === 'active' && $canView);
    $ownerAccountId = (int) ($target['owner_account_id'] ?? ($target['author_account_id'] ?? 0));
    $recipientAccountId = (int) ($target['recipient_account_id'] ?? ($target['notification_account_id'] ?? $ownerAccountId));
    $reactionKeys = [];
    foreach (($target['reaction_keys'] ?? []) as $key) {
        if (is_string($key)) {
            $cleanKey = sr_reaction_clean_key($key);
            if ($cleanKey !== '') {
                $reactionKeys[] = $cleanKey;
            }
        }
    }

    return [
        'found' => $status !== 'broken',
        'target_module' => $targetModule,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'status' => $status,
        'can_view' => $canView,
        'can_write' => $canWrite,
        'owner_account_id' => $ownerAccountId,
        'recipient_account_id' => $recipientAccountId,
        'notification_enabled' => !array_key_exists('notification_enabled', $target) || (bool) $target['notification_enabled'],
        'preset_key' => sr_reaction_clean_key((string) ($target['preset_key'] ?? '')),
        'reaction_keys' => array_values(array_unique($reactionKeys)),
        'label' => (string) ($target['label'] ?? ($target['label_snapshot'] ?? '')),
        'public_url' => (string) ($target['public_url'] ?? ''),
        'admin_url' => (string) ($target['admin_url'] ?? ''),
    ];
}

function sr_reaction_target_contract(PDO $pdo, string $targetModule, string $targetType): ?array
{
    $targetModule = sr_reaction_clean_key($targetModule, 60);
    $targetType = sr_reaction_clean_key($targetType, 60);
    if (sr_reaction_target_key($targetModule, $targetType) === '') {
        return null;
    }

    foreach (sr_enabled_module_contract_files($pdo, 'reaction-targets.php', ['reaction']) as $moduleKey => $file) {
        $providerModuleKey = sr_reaction_clean_key((string) $moduleKey, 60);
        if ($providerModuleKey === '') {
            continue;
        }

        $contract = sr_load_module_contract_file($moduleKey, $file);
        $targets = is_array($contract['targets'] ?? null) ? $contract['targets'] : [];
        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $contractTargetModule = sr_reaction_clean_key((string) ($target['target_module'] ?? ''), 60);
            $contractTargetType = sr_reaction_clean_key((string) ($target['target_type'] ?? ''), 60);
            if ($contractTargetModule !== $providerModuleKey) {
                continue;
            }

            if ($contractTargetModule === $targetModule && $contractTargetType === $targetType) {
                return $target;
            }
        }
    }

    return null;
}

function sr_reaction_resolve_target(PDO $pdo, string $targetModule, string $targetType, string $targetId, int $viewerAccountId, array $context = []): ?array
{
    if (isset($context['resolved_target']) && is_array($context['resolved_target'])) {
        return sr_reaction_normalize_target($context['resolved_target'], $targetModule, $targetType, $targetId);
    }

    $contract = sr_reaction_target_contract($pdo, $targetModule, $targetType);
    if (!is_array($contract)) {
        return null;
    }

    $resolve = $contract['resolve'] ?? null;
    if (!is_callable($resolve)) {
        return null;
    }

    try {
        $target = $resolve($pdo, [
            'target_module' => $targetModule,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'viewer_account_id' => $viewerAccountId,
            'context' => (string) ($context['context'] ?? 'public'),
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'reaction_target_resolve');
        return null;
    }

    return is_array($target) ? sr_reaction_normalize_target($target, $targetModule, $targetType, $targetId) : null;
}

function sr_reaction_resolve_targets(PDO $pdo, string $targetModule, string $targetType, array $targetIds, int $viewerAccountId, array $context = []): array
{
    $targetModule = sr_reaction_clean_key($targetModule, 60);
    $targetType = sr_reaction_clean_key($targetType, 60);
    if (sr_reaction_target_key($targetModule, $targetType) === '') {
        return [];
    }

    $cleanIds = [];
    foreach ($targetIds as $targetId) {
        $cleanId = sr_reaction_target_id((string) $targetId);
        if ($cleanId !== '') {
            $cleanIds[] = $cleanId;
        }
    }
    $cleanIds = array_values(array_unique($cleanIds));
    if ($cleanIds === []) {
        return [];
    }

    $prefilledTargets = [];
    if (isset($context['resolved_targets']) && is_array($context['resolved_targets'])) {
        foreach ($cleanIds as $targetId) {
            if (isset($context['resolved_targets'][$targetId]) && is_array($context['resolved_targets'][$targetId])) {
                $prefilledTargets[$targetId] = sr_reaction_normalize_target($context['resolved_targets'][$targetId], $targetModule, $targetType, $targetId);
            }
        }
        if (count($prefilledTargets) === count($cleanIds)) {
            return $prefilledTargets;
        }
    }

    $contract = sr_reaction_target_contract($pdo, $targetModule, $targetType);
    if (!is_array($contract)) {
        return $prefilledTargets;
    }

    $batchResolve = $contract['batch_resolve'] ?? null;
    if (is_callable($batchResolve)) {
        try {
            $targets = $batchResolve($pdo, [
                'target_module' => $targetModule,
                'target_type' => $targetType,
                'target_ids' => $cleanIds,
                'viewer_account_id' => $viewerAccountId,
                'context' => (string) ($context['context'] ?? 'public'),
            ]);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'reaction_target_batch_resolve');
            $targets = null;
        }

        if (is_array($targets)) {
            $resolved = $prefilledTargets;
            foreach ($targets as $key => $target) {
                if (!is_array($target)) {
                    continue;
                }
                $targetId = sr_reaction_target_id((string) ($target['target_id'] ?? (is_int($key) || is_string($key) ? $key : '')));
                if ($targetId !== '' && in_array($targetId, $cleanIds, true)) {
                    $resolved[$targetId] = sr_reaction_normalize_target($target, $targetModule, $targetType, $targetId);
                }
            }

            if (count($resolved) === count($cleanIds)) {
                return $resolved;
            }

            $prefilledTargets = $resolved;
        }
    }

    $resolved = $prefilledTargets;
    foreach ($cleanIds as $targetId) {
        if (isset($resolved[$targetId])) {
            continue;
        }
        $target = sr_reaction_resolve_target($pdo, $targetModule, $targetType, $targetId, $viewerAccountId, $context);
        if (is_array($target)) {
            $resolved[$targetId] = $target;
        }
    }

    return $resolved;
}

function sr_reaction_default_preset_key(PDO $pdo): string
{
    $key = sr_reaction_clean_key((string) sr_site_setting($pdo, 'reaction_default_preset_key', 'emotions'));
    return $key !== '' ? $key : 'emotions';
}

function sr_reaction_setting_preset_key(PDO $pdo, mixed $value): string
{
    $presetKey = sr_reaction_clean_key((string) $value);
    if ($presetKey === '') {
        return '';
    }

    return sr_reaction_preset_is_available($pdo, $presetKey) ? $presetKey : '';
}

function sr_reaction_disabled_preset_key(): string
{
    return '__disabled';
}

function sr_reaction_setting_preset_key_or_disabled(PDO $pdo, mixed $value): string
{
    $rawValue = trim((string) $value);
    if ($rawValue === sr_reaction_disabled_preset_key()) {
        return sr_reaction_disabled_preset_key();
    }

    return sr_reaction_setting_preset_key($pdo, $rawValue);
}

function sr_reaction_preset_options_with_disabled(PDO $pdo, bool $includeDefault = true): array
{
    $options = sr_reaction_preset_options($pdo, $includeDefault);
    $disabledKey = sr_reaction_disabled_preset_key();

    return array_merge(
        array_slice($options, 0, $includeDefault ? 1 : 0, true),
        [$disabledKey => '사용안함'],
        array_slice($options, $includeDefault ? 1 : 0, null, true)
    );
}

function sr_reaction_preset_is_available(PDO $pdo, string $presetKey): bool
{
    $presetKey = sr_reaction_clean_key($presetKey);
    if ($presetKey === '' || !sr_reaction_tables_available($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT 1
         FROM sr_reaction_presets
         WHERE preset_key = :preset_key
           AND status = 'active'
           AND selection_policy = 'single'
         LIMIT 1"
    );
    $stmt->execute(['preset_key' => $presetKey]);

    return $stmt->fetchColumn() !== false;
}

function sr_reaction_preset_options(PDO $pdo, bool $includeDefault = true): array
{
    $options = [];
    if ($includeDefault) {
        $defaultKey = sr_reaction_default_preset_key($pdo);
        $options[''] = '리액션 기본값 (' . $defaultKey . ')';
    }

    if (!sr_reaction_tables_available($pdo)) {
        return $options;
    }

    $stmt = $pdo->query(
        "SELECT preset_key, label
         FROM sr_reaction_presets
         WHERE status = 'active'
           AND selection_policy = 'single'
         ORDER BY sort_order ASC, id ASC"
    );
    if ($stmt === false) {
        return $options;
    }

    foreach ($stmt->fetchAll() as $row) {
        $presetKey = sr_reaction_clean_key((string) ($row['preset_key'] ?? ''));
        if ($presetKey === '') {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        $options[$presetKey] = ($label !== '' ? $label : $presetKey) . ' (' . $presetKey . ')';
    }

    return $options;
}

function sr_reaction_active_definition(PDO $pdo, string $reactionKey): ?array
{
    $reactionKey = sr_reaction_clean_key($reactionKey);
    if ($reactionKey === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_reaction_definitions
         WHERE reaction_key = :reaction_key
           AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute(['reaction_key' => $reactionKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_reaction_allowed_keys(PDO $pdo, array $target): array
{
    $presetKey = sr_reaction_clean_key((string) ($target['preset_key'] ?? ''));
    $explicitKeys = isset($target['reaction_keys']) && is_array($target['reaction_keys']) ? $target['reaction_keys'] : [];
    $cleanExplicitKeys = [];
    foreach ($explicitKeys as $key) {
        if (is_string($key)) {
            $cleanKey = sr_reaction_clean_key($key);
            if ($cleanKey !== '') {
                $cleanExplicitKeys[] = $cleanKey;
            }
        }
    }
    $cleanExplicitKeys = array_slice(array_values(array_unique($cleanExplicitKeys)), 0, 12);

    if ($presetKey !== '') {
        $presetKeys = sr_reaction_public_preset_keys($pdo, $presetKey);
        if ($presetKeys !== []) {
            return $presetKeys;
        }

        $defaultPresetKeys = sr_reaction_public_preset_keys($pdo, sr_reaction_default_preset_key($pdo));
        return $defaultPresetKeys !== [] ? $defaultPresetKeys : $cleanExplicitKeys;
    }

    if ($cleanExplicitKeys !== []) {
        return $cleanExplicitKeys;
    }

    $defaultPresetKeys = sr_reaction_public_preset_keys($pdo, sr_reaction_default_preset_key($pdo));
    if ($defaultPresetKeys !== []) {
        return $defaultPresetKeys;
    }

    return [];
}

function sr_reaction_public_preset_keys(PDO $pdo, string $presetKey): array
{
    $presetKey = sr_reaction_clean_key($presetKey);
    if ($presetKey === '') {
        return [];
    }
    static $keysByPreset = [];
    $cacheKey = (string) spl_object_id($pdo) . ':' . $presetKey;
    if (array_key_exists($cacheKey, $keysByPreset)) {
        return $keysByPreset[$cacheKey];
    }

    $presetStmt = $pdo->prepare(
        "SELECT visible_key_limit
         FROM sr_reaction_presets
         WHERE preset_key = :preset_key
           AND status = 'active'
           AND selection_policy = 'single'
         LIMIT 1"
    );
    $presetStmt->execute(['preset_key' => $presetKey]);
    $preset = $presetStmt->fetch();
    if (!is_array($preset)) {
        $keysByPreset[$cacheKey] = [];
        return $keysByPreset[$cacheKey];
    }

    $visibleKeyLimit = max(1, min(12, (int) ($preset['visible_key_limit'] ?? 6)));
    $stmt = $pdo->prepare(
        "SELECT i.reaction_key
         FROM sr_reaction_preset_items i
         INNER JOIN sr_reaction_definitions d ON d.reaction_key = i.reaction_key
         WHERE i.preset_key = :preset_key
           AND i.is_public = 1
           AND d.status = 'active'
         ORDER BY i.sort_order ASC, i.id ASC
         LIMIT " . (string) $visibleKeyLimit
    );
    $stmt->execute(['preset_key' => $presetKey]);
    $keys = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = sr_reaction_clean_key((string) ($row['reaction_key'] ?? ''));
        if ($key !== '') {
            $keys[] = $key;
        }
    }

    $keysByPreset[$cacheKey] = array_values(array_unique($keys));
    return $keysByPreset[$cacheKey];
}

function sr_reaction_public_definitions(PDO $pdo, array $keys): array
{
    $keys = array_values(array_unique(array_filter(array_map(static function (mixed $key): string {
        return is_string($key) ? sr_reaction_clean_key($key) : '';
    }, $keys))));
    if ($keys === []) {
        return [];
    }
    static $definitionsByKeys = [];
    $cacheKey = (string) spl_object_id($pdo) . ':' . implode(',', $keys);
    if (array_key_exists($cacheKey, $definitionsByKeys)) {
        return $definitionsByKeys[$cacheKey];
    }

    $placeholders = [];
    $params = [];
    foreach ($keys as $index => $key) {
        $param = 'reaction_key_' . (string) $index;
        $placeholders[] = ':' . $param;
        $params[$param] = $key;
    }

    $stmt = $pdo->prepare(
        'SELECT reaction_key, label, icon_type, icon_value, color_hex, color_swatch
         FROM sr_reaction_definitions
         WHERE status = \'active\'
           AND reaction_key IN (' . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = sr_reaction_clean_key((string) ($row['reaction_key'] ?? ''));
        if ($key !== '') {
            $rows[$key] = $row;
        }
    }

    $ordered = [];
    foreach ($keys as $key) {
        if (isset($rows[$key])) {
            $ordered[$key] = $rows[$key];
        }
    }

    $definitionsByKeys[$cacheKey] = $ordered;
    return $definitionsByKeys[$cacheKey];
}

function sr_reaction_my_record(PDO $pdo, int $accountId, string $targetModule, string $targetType, string $targetId, bool $lock = false): ?array
{
    if ($accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_reaction_records
         WHERE account_id = :account_id
           AND target_module = :target_module
           AND target_type = :target_type
           AND target_id = :target_id
         LIMIT 1' . ($lock ? sr_reaction_lock_clause($pdo) : '')
    );
    $stmt->execute([
        'account_id' => $accountId,
        'target_module' => $targetModule,
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_reaction_counts(PDO $pdo, string $targetModule, string $targetType, string $targetId, array $allowedKeys = []): array
{
    $params = [
        'target_module' => $targetModule,
        'target_type' => $targetType,
        'target_id' => $targetId,
    ];
    $where = '';
    if ($allowedKeys !== []) {
        $placeholders = [];
        foreach (array_values($allowedKeys) as $index => $key) {
            $param = 'reaction_key_' . (string) $index;
            $placeholders[] = ':' . $param;
            $params[$param] = $key;
        }
        $where = ' AND reaction_key IN (' . implode(', ', $placeholders) . ')';
    }

    $stmt = $pdo->prepare(
        'SELECT reaction_key, COUNT(*) AS count_value
         FROM sr_reaction_records
         WHERE target_module = :target_module
           AND target_type = :target_type
           AND target_id = :target_id' . $where . '
         GROUP BY reaction_key'
    );
    $stmt->execute($params);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = sr_reaction_clean_key((string) ($row['reaction_key'] ?? ''));
        if ($key !== '') {
            $counts[$key] = (int) ($row['count_value'] ?? 0);
        }
    }

    return $counts;
}

function sr_reaction_record_summaries(PDO $pdo, string $targetModule, string $targetType, array $targetIds, int $accountId = 0): array
{
    $targetModule = sr_reaction_clean_key($targetModule, 60);
    $targetType = sr_reaction_clean_key($targetType, 60);
    if (sr_reaction_target_key($targetModule, $targetType) === '') {
        return [];
    }

    $cleanIds = [];
    foreach ($targetIds as $targetId) {
        $targetId = sr_reaction_target_id((string) $targetId);
        if ($targetId !== '') {
            $cleanIds[$targetId] = $targetId;
        }
    }
    if ($cleanIds === []) {
        return [];
    }

    $summaries = [];
    $placeholders = [];
    $params = [
        'target_module' => $targetModule,
        'target_type' => $targetType,
        'account_id' => $accountId > 0 ? $accountId : -1,
    ];
    foreach (array_values($cleanIds) as $index => $targetId) {
        $summaries[$targetId] = ['counts' => [], 'my_record' => null];
        $param = 'target_id_' . (string) $index;
        $placeholders[] = ':' . $param;
        $params[$param] = $targetId;
    }
    $stmt = $pdo->prepare(
        'SELECT target_id, reaction_key, COUNT(*) AS count_value,
                MAX(CASE WHEN account_id = :account_id THEN 1 ELSE 0 END) AS is_mine
         FROM sr_reaction_records
         WHERE target_module = :target_module
           AND target_type = :target_type
           AND target_id IN (' . implode(', ', $placeholders) . ')
         GROUP BY target_id, reaction_key'
    );
    $stmt->execute($params);

    foreach ($stmt->fetchAll() as $row) {
        $targetId = sr_reaction_target_id((string) ($row['target_id'] ?? ''));
        $reactionKey = sr_reaction_clean_key((string) ($row['reaction_key'] ?? ''));
        if ($targetId === '' || $reactionKey === '' || !isset($summaries[$targetId])) {
            continue;
        }
        $summaries[$targetId]['counts'][$reactionKey] = (int) ($row['count_value'] ?? 0);
        if ((int) ($row['is_mine'] ?? 0) === 1) {
            $summaries[$targetId]['my_record'] = ['reaction_key' => $reactionKey];
        }
    }

    return $summaries;
}

function sr_reaction_public_icon_html(array $definition): string
{
    $iconType = (string) ($definition['icon_type'] ?? 'emoji');
    $iconValue = trim((string) ($definition['icon_value'] ?? ''));
    if ($iconValue === '') {
        return '';
    }

    if ($iconType === 'image') {
        if (sr_reaction_icon_image_storage_reference($iconValue) === null) {
            return '';
        }

        return '<img class="sr-reaction-image" src="' . sr_e(sr_url('/reaction/icon?file=' . rawurlencode($iconValue))) . '" alt="">';
    }

    if ($iconType === 'material') {
        return sr_material_icon_html($iconValue);
    }

    return '<span class="sr-reaction-emoji" aria-hidden="true">' . sr_e($iconValue) . '</span>';
}

function sr_reaction_render_widget(PDO $pdo, string $targetModule, string $targetType, string $targetId, ?array $account = null, array $options = []): string
{
    if (!sr_reaction_tables_available($pdo)) {
        return '';
    }

    $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
    $targetModule = sr_reaction_clean_key($targetModule, 60);
    $targetType = sr_reaction_clean_key($targetType, 60);
    $targetId = sr_reaction_target_id($targetId);
    if (sr_reaction_target_key($targetModule, $targetType) === '' || $targetId === '') {
        return '';
    }

    $resolveContext = [
        'context' => (string) ($options['context'] ?? 'public'),
    ];
    if (isset($options['resolved_target']) && is_array($options['resolved_target'])) {
        $resolveContext['resolved_target'] = $options['resolved_target'];
    }
    $target = sr_reaction_resolve_target($pdo, $targetModule, $targetType, $targetId, $accountId, $resolveContext);
    if (!is_array($target) || (string) ($target['status'] ?? '') !== 'active' || empty($target['can_view'])) {
        return '';
    }

    $allowedKeys = sr_reaction_allowed_keys($pdo, $target);
    $definitions = sr_reaction_public_definitions($pdo, $allowedKeys);
    if ($definitions === []) {
        return '';
    }
    $allowedKeys = array_keys($definitions);
    $counts = isset($options['counts']) && is_array($options['counts'])
        ? $options['counts']
        : sr_reaction_counts($pdo, $targetModule, $targetType, $targetId, $allowedKeys);
    $myRecord = array_key_exists('my_record', $options)
        ? (is_array($options['my_record']) ? $options['my_record'] : null)
        : sr_reaction_my_record($pdo, $accountId, $targetModule, $targetType, $targetId);
    $myKey = is_array($myRecord) ? sr_reaction_clean_key((string) ($myRecord['reaction_key'] ?? '')) : '';
    $isOwner = $accountId > 0 && (int) ($target['owner_account_id'] ?? 0) === $accountId;
    $canWrite = $accountId > 0
        && !empty($target['can_write'])
        && !$isOwner;
    if ($isOwner) {
        $definitions = array_filter($definitions, static function (array $definition, string $key) use ($counts): bool {
            return (int) ($counts[$key] ?? 0) > 0;
        }, ARRAY_FILTER_USE_BOTH);
        if ($definitions === []) {
            return '';
        }
    }
    $widgetClass = 'sr-reaction-widget sr-reaction-widget--target-' . $targetType;

    ob_start();
    ?>
    <div class="<?php echo sr_e($widgetClass); ?>" data-sr-reaction-widget data-action="<?php echo sr_e(sr_url('/reaction/write')); ?>" data-target-module="<?php echo sr_e($targetModule); ?>" data-target-type="<?php echo sr_e($targetType); ?>" data-target-id="<?php echo sr_e($targetId); ?>" data-csrf-token="<?php echo sr_e(sr_csrf_token()); ?>">
        <div class="sr-reaction-buttons">
            <?php foreach ($definitions as $key => $definition) { ?>
                <?php
                $isActive = $myKey === $key;
                $count = (int) ($counts[$key] ?? 0);
                $buttonLabel = (string) ($definition['label'] ?? $key);
                $buttonClass = 'btn sr-reaction-button ' . ($isActive ? 'btn-solid-success is-active' : 'btn-ghost-default');
                ?>
                <?php if ($isOwner) { ?>
                    <span class="btn sr-reaction-button sr-reaction-summary btn-ghost-default" aria-label="<?php echo sr_e($buttonLabel . ' ' . number_format($count)); ?>">
                        <?php if ($targetType === 'comment') { ?>
                            <?php echo sr_reaction_public_icon_html($definition); ?>
                            <span class="sr-reaction-button-label"><?php echo sr_e($buttonLabel); ?></span>
                            <span class="sr-reaction-count" data-reaction-count="<?php echo sr_e($key); ?>"><?php echo sr_e(number_format($count)); ?></span>
                        <?php } else { ?>
                            <?php echo sr_reaction_public_icon_html($definition); ?>
                            <span class="sr-reaction-body-label">
                                <span class="sr-reaction-button-label"><?php echo sr_e($buttonLabel); ?></span>
                                <span class="sr-reaction-count" data-reaction-count="<?php echo sr_e($key); ?>"><?php echo sr_e(number_format($count)); ?></span>
                            </span>
                        <?php } ?>
                    </span>
                <?php } else { ?>
                    <button type="button" class="<?php echo sr_e($buttonClass); ?>" data-reaction-key="<?php echo sr_e($key); ?>" aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"<?php echo $canWrite ? '' : ' disabled'; ?>>
                    <?php if ($targetType === 'comment') { ?>
                        <?php echo sr_reaction_public_icon_html($definition); ?>
                        <span class="sr-reaction-button-label"><?php echo sr_e($buttonLabel); ?></span>
                        <span class="sr-reaction-count" data-reaction-count="<?php echo sr_e($key); ?>"><?php echo sr_e(number_format($count)); ?></span>
                    <?php } else { ?>
                        <?php echo sr_reaction_public_icon_html($definition); ?>
                        <span class="sr-reaction-body-label">
                            <span class="sr-reaction-button-label"><?php echo sr_e($buttonLabel); ?></span>
                            <span class="sr-reaction-count" data-reaction-count="<?php echo sr_e($key); ?>"><?php echo sr_e(number_format($count)); ?></span>
                        </span>
                    <?php } ?>
                    </button>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
    <?php
    return trim((string) ob_get_clean());
}

function sr_reaction_public_script_html(): string
{
    static $rendered = false;
    if ($rendered) {
        return '';
    }
    $rendered = true;

    return '<script src="' . sr_e(sr_asset_url('/modules/reaction/assets/public.js')) . '" defer></script>';
}
function sr_reaction_create_account_event(PDO $pdo, int $recipientAccountId, int $actorAccountId, array $target, string $reactionKey): bool
{
    if ($recipientAccountId < 1 || $actorAccountId < 1 || $recipientAccountId === $actorAccountId) {
        return false;
    }
    if ((string) ($target['status'] ?? '') !== 'active' || empty($target['can_write']) || empty($target['notification_enabled'])) {
        return false;
    }

    $createAccountEventFunction = sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
    if ($createAccountEventFunction === '') {
        return false;
    }

    $actorName = function_exists('sr_member_public_name_for_account_id')
        ? sr_member_public_name_for_account_id($pdo, $actorAccountId, '회원')
        : '회원';
    $definition = sr_reaction_active_definition($pdo, $reactionKey);
    $reactionLabel = is_array($definition) ? (string) ($definition['label'] ?? $reactionKey) : $reactionKey;
    $metadata = [
        'reaction_key' => $reactionKey,
        'reaction_label' => $reactionLabel,
        'member_name' => $actorName,
        'target_module' => (string) ($target['target_module'] ?? ''),
        'target_type' => (string) ($target['target_type'] ?? ''),
        'target_id' => (string) ($target['target_id'] ?? ''),
        'target_label' => (string) ($target['label'] ?? ''),
        'link_url' => (string) ($target['public_url'] ?? ''),
    ];

    if (sr_reaction_account_event_recently_exists($pdo, $recipientAccountId, $actorAccountId, $metadata)) {
        return false;
    }

    try {
        return $createAccountEventFunction($pdo, [
            'account_id' => $recipientAccountId,
            'module_key' => 'reaction',
            'event_key' => 'target.reacted',
            'created_by_account_id' => $actorAccountId,
            'metadata' => $metadata,
        ]) !== null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'reaction_notification_event_create');
    }

    return false;
}

function sr_reaction_account_event_recently_exists(PDO $pdo, int $recipientAccountId, int $actorAccountId, array $metadata): bool
{
    if ($recipientAccountId < 1 || $actorAccountId < 1 || !function_exists('sr_notification_event_template')) {
        return false;
    }

    $template = sr_notification_event_template($pdo, 'reaction', 'target.reacted');
    if (!is_array($template) || (string) ($template['status'] ?? '') !== 'active' || !function_exists('sr_notification_render_template')) {
        return false;
    }

    try {
        $title = sr_notification_render_template((string) ($template['title_template'] ?? ''), $metadata);
        $bodyText = sr_notification_render_template((string) ($template['body_template'] ?? ''), $metadata);
        $linkUrl = sr_notification_render_template((string) ($template['link_template'] ?? ''), $metadata);
        $stmt = $pdo->prepare(
            'SELECT id
             FROM sr_notifications
             WHERE account_id = :account_id
               AND audience = \'account\'
               AND created_by_account_id = :created_by_account_id
               AND title = :title
               AND body_text = :body_text
               AND link_url = :link_url
               AND created_at >= :created_after
             LIMIT 1'
        );
        $stmt->execute([
            'account_id' => $recipientAccountId,
            'created_by_account_id' => $actorAccountId,
            'title' => $title,
            'body_text' => $bodyText,
            'link_url' => $linkUrl,
            'created_after' => date('Y-m-d H:i:s', time() - 3600),
        ]);

        return is_array($stmt->fetch());
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'reaction_notification_dedupe_check');
        return false;
    }
}

function sr_reaction_write(PDO $pdo, int $accountId, string $targetModule, string $targetType, string $targetId, string $reactionKey, string $intent = 'toggle', array $context = []): array
{
    $targetModule = sr_reaction_clean_key($targetModule, 60);
    $targetType = sr_reaction_clean_key($targetType, 60);
    $targetId = sr_reaction_target_id($targetId);
    $reactionKey = sr_reaction_clean_key($reactionKey);
    $intent = in_array($intent, ['apply', 'cancel', 'toggle'], true) ? $intent : 'toggle';
    $targetKey = sr_reaction_target_key($targetModule, $targetType);

    $result = [
        'ok' => false,
        'error' => '',
        'changed' => false,
        'operation' => 'none',
        'my_reaction_key' => '',
        'counts' => [],
        'notification_created' => false,
    ];

    if ($accountId < 1) {
        $result['error'] = 'login_required';
        return $result;
    }
    if ($targetKey === '' || $targetId === '') {
        $result['error'] = 'invalid_target';
        return $result;
    }
    if ($reactionKey === '') {
        $result['error'] = 'invalid_reaction';
        return $result;
    }
    if (!sr_reaction_tables_available($pdo)) {
        $result['error'] = 'not_available';
        return $result;
    }

    $isCancelIntent = $intent === 'cancel';
    $target = sr_reaction_resolve_target($pdo, $targetModule, $targetType, $targetId, $accountId, $context);
    if (!$isCancelIntent) {
        if (!is_array($target)) {
            $result['error'] = 'target_contract_missing';
            return $result;
        }
        if ((int) ($target['owner_account_id'] ?? 0) === $accountId) {
            $result['error'] = 'self_reaction_not_allowed';
            return $result;
        }
        if ((string) ($target['status'] ?? '') !== 'active' || empty($target['can_view']) || empty($target['can_write'])) {
            $result['error'] = 'target_not_writable';
            return $result;
        }

        $allowedKeys = sr_reaction_allowed_keys($pdo, $target);
        if (!in_array($reactionKey, $allowedKeys, true) || sr_reaction_active_definition($pdo, $reactionKey) === null) {
            $result['error'] = 'reaction_not_allowed';
            return $result;
        }
    } else {
        $allowedKeys = [];
        if (is_array($target) && !empty($target['can_view'])) {
            $allowedKeys = sr_reaction_allowed_keys($pdo, $target);
        }
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $now = sr_now();
        $existing = sr_reaction_my_record($pdo, $accountId, $targetModule, $targetType, $targetId, true);
        $existingKey = is_array($existing) ? sr_reaction_clean_key((string) ($existing['reaction_key'] ?? '')) : '';
        $nextKey = $existingKey;

        if ($intent === 'cancel' || ($intent === 'toggle' && $existingKey === $reactionKey)) {
            if ($existingKey !== '') {
                $stmt = $pdo->prepare('DELETE FROM sr_reaction_records WHERE id = :id');
                $stmt->execute(['id' => (int) ($existing['id'] ?? 0)]);
                $result['changed'] = $stmt->rowCount() > 0;
                $result['operation'] = 'cancel';
            } else {
                $result['operation'] = 'noop';
            }
            $nextKey = '';
        } elseif ($existingKey === $reactionKey) {
            $result['operation'] = 'noop';
        } elseif ($existingKey !== '') {
            $stmt = $pdo->prepare(
                'UPDATE sr_reaction_records
                 SET reaction_key = :reaction_key,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'reaction_key' => $reactionKey,
                'updated_at' => $now,
                'id' => (int) ($existing['id'] ?? 0),
            ]);
            $result['changed'] = $stmt->rowCount() > 0;
            $result['operation'] = 'change';
            $nextKey = $reactionKey;
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_reaction_records
                    (account_id, target_module, target_type, target_id, reaction_key, created_at, updated_at)
                 VALUES
                    (:account_id, :target_module, :target_type, :target_id, :reaction_key, :created_at, :updated_at)'
            );
            $stmt->execute([
                'account_id' => $accountId,
                'target_module' => $targetModule,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'reaction_key' => $reactionKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $result['changed'] = true;
            $result['operation'] = 'apply';
            $nextKey = $reactionKey;
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        $result['ok'] = true;
        $result['my_reaction_key'] = $nextKey;
        $result['counts'] = sr_reaction_counts($pdo, $targetModule, $targetType, $targetId, $allowedKeys);
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_log_exception($exception, 'reaction_write');
        $result['error'] = 'write_failed';
        return $result;
    }

    if ($result['ok'] && $result['changed'] && in_array($result['operation'], ['apply', 'change'], true) && is_array($target)) {
        $result['notification_created'] = sr_reaction_create_account_event(
            $pdo,
            (int) ($target['recipient_account_id'] ?? 0),
            $accountId,
            $target,
            $reactionKey
        );
    }

    return $result;
}
