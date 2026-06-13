<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers.php';

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
    try {
        $pdo->query('SELECT 1 FROM sr_reaction_definitions LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_reaction_presets LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_reaction_preset_items LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_reaction_records LIMIT 1');
    } catch (Throwable) {
        return false;
    }

    return true;
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
    foreach (sr_enabled_module_contract_files($pdo, 'reaction-targets.php', ['reaction']) as $moduleKey => $file) {
        $contract = sr_load_module_contract_file($moduleKey, $file);
        $targets = is_array($contract['targets'] ?? null) ? $contract['targets'] : [];
        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }

            if ((string) ($target['target_module'] ?? '') === $targetModule && (string) ($target['target_type'] ?? '') === $targetType) {
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

function sr_reaction_default_preset_key(PDO $pdo): string
{
    $key = sr_reaction_clean_key((string) sr_site_setting($pdo, 'reaction_default_preset_key', 'emotions'));
    return $key !== '' ? $key : 'emotions';
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
    $explicitKeys = isset($target['reaction_keys']) && is_array($target['reaction_keys']) ? $target['reaction_keys'] : [];
    if ($explicitKeys !== []) {
        $keys = [];
        foreach ($explicitKeys as $key) {
            if (is_string($key)) {
                $cleanKey = sr_reaction_clean_key($key);
                if ($cleanKey !== '') {
                    $keys[] = $cleanKey;
                }
            }
        }
        return array_values(array_unique($keys));
    }

    $presetKey = sr_reaction_clean_key((string) ($target['preset_key'] ?? ''));
    if ($presetKey === '') {
        $presetKey = sr_reaction_default_preset_key($pdo);
    }

    $stmt = $pdo->prepare(
        "SELECT i.reaction_key
         FROM sr_reaction_preset_items i
         INNER JOIN sr_reaction_presets p ON p.preset_key = i.preset_key
         INNER JOIN sr_reaction_definitions d ON d.reaction_key = i.reaction_key
         WHERE i.preset_key = :preset_key
           AND i.is_public = 1
           AND p.status = 'active'
           AND p.selection_policy = 'single'
           AND d.status = 'active'
         ORDER BY i.sort_order ASC, i.id ASC
         LIMIT 12"
    );
    $stmt->execute(['preset_key' => $presetKey]);
    $keys = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = sr_reaction_clean_key((string) ($row['reaction_key'] ?? ''));
        if ($key !== '') {
            $keys[] = $key;
        }
    }

    return array_values(array_unique($keys));
}

function sr_reaction_public_definitions(PDO $pdo, array $keys): array
{
    $keys = array_values(array_unique(array_filter(array_map(static function (mixed $key): string {
        return is_string($key) ? sr_reaction_clean_key($key) : '';
    }, $keys))));
    if ($keys === []) {
        return [];
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

    return $ordered;
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

function sr_reaction_public_icon_html(array $definition): string
{
    $iconType = (string) ($definition['icon_type'] ?? 'emoji');
    $iconValue = trim((string) ($definition['icon_value'] ?? ''));
    if ($iconValue === '') {
        return '';
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
    $counts = sr_reaction_counts($pdo, $targetModule, $targetType, $targetId, $allowedKeys);
    $myRecord = sr_reaction_my_record($pdo, $accountId, $targetModule, $targetType, $targetId);
    $myKey = is_array($myRecord) ? sr_reaction_clean_key((string) ($myRecord['reaction_key'] ?? '')) : '';
    $canWrite = $accountId > 0
        && !empty($target['can_write'])
        && (int) ($target['owner_account_id'] ?? 0) !== $accountId;
    $loginUrl = (string) ($options['login_url'] ?? ('/login?next=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/'))));
    $label = (string) ($options['label'] ?? '리액션');

    ob_start();
    ?>
    <div class="sr-reaction-widget" data-sr-reaction-widget data-action="<?php echo sr_e(sr_url('/reaction/write')); ?>" data-target-module="<?php echo sr_e($targetModule); ?>" data-target-type="<?php echo sr_e($targetType); ?>" data-target-id="<?php echo sr_e($targetId); ?>" data-csrf-token="<?php echo sr_e(sr_csrf_token()); ?>">
        <div class="sr-reaction-label"><?php echo sr_e($label); ?></div>
        <div class="sr-reaction-buttons">
            <?php foreach ($definitions as $key => $definition) { ?>
                <?php
                $isActive = $myKey === $key;
                $count = (int) ($counts[$key] ?? 0);
                $buttonLabel = (string) ($definition['label'] ?? $key);
                ?>
                <button type="button" class="sr-reaction-button<?php echo $isActive ? ' is-active' : ''; ?>" data-reaction-key="<?php echo sr_e($key); ?>" aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"<?php echo $canWrite ? '' : ' disabled'; ?>>
                    <?php echo sr_reaction_public_icon_html($definition); ?>
                    <span class="sr-reaction-button-label"><?php echo sr_e($buttonLabel); ?></span>
                    <span class="sr-reaction-count" data-reaction-count="<?php echo sr_e($key); ?>"><?php echo sr_e(number_format($count)); ?></span>
                </button>
            <?php } ?>
        </div>
        <?php if ($accountId < 1) { ?>
            <a class="sr-reaction-login" href="<?php echo sr_e(sr_url($loginUrl)); ?>">로그인 후 반응할 수 있습니다.</a>
        <?php } elseif (!$canWrite && (int) ($target['owner_account_id'] ?? 0) === $accountId) { ?>
            <p class="sr-reaction-note">내가 작성한 대상에는 반응할 수 없습니다.</p>
        <?php } ?>
        <p class="sr-reaction-message" data-sr-reaction-message hidden></p>
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

function sr_reaction_definition_statuses(): array
{
    return ['active', 'disabled'];
}

function sr_reaction_preset_statuses(): array
{
    return ['active', 'disabled'];
}

function sr_reaction_icon_types(): array
{
    return ['emoji', 'material'];
}

function sr_reaction_admin_definitions(PDO $pdo): array
{
    if (!sr_reaction_tables_available($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        'SELECT d.*,
                (SELECT COUNT(*) FROM sr_reaction_records r WHERE r.reaction_key = d.reaction_key) AS record_count
         FROM sr_reaction_definitions d
         ORDER BY d.sort_order ASC, d.id ASC'
    );

    return $stmt !== false ? $stmt->fetchAll() : [];
}

function sr_reaction_admin_presets(PDO $pdo): array
{
    if (!sr_reaction_tables_available($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        'SELECT *
         FROM sr_reaction_presets
         ORDER BY sort_order ASC, id ASC'
    );

    return $stmt !== false ? $stmt->fetchAll() : [];
}

function sr_reaction_admin_preset_items(PDO $pdo): array
{
    if (!sr_reaction_tables_available($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        'SELECT preset_key, reaction_key, sort_order, is_public
         FROM sr_reaction_preset_items
         ORDER BY preset_key ASC, sort_order ASC, id ASC'
    );
    $items = [];
    if ($stmt !== false) {
        foreach ($stmt->fetchAll() as $row) {
            $presetKey = sr_reaction_clean_key((string) ($row['preset_key'] ?? ''));
            if ($presetKey !== '') {
                $items[$presetKey][] = $row;
            }
        }
    }

    return $items;
}

function sr_reaction_clean_label(string $value, int $maxLength = 80): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_reaction_clean_color_hex(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return preg_match('/\A#[0-9a-fA-F]{6}\z/', $value) === 1 ? strtolower($value) : '';
}

function sr_reaction_validate_definition_input(PDO $pdo, array $input): array
{
    $definitionId = max(0, (int) ($input['id'] ?? 0));
    $key = sr_reaction_clean_key((string) ($input['reaction_key'] ?? ''));
    $label = sr_reaction_clean_label((string) ($input['label'] ?? ''));
    $iconType = (string) ($input['icon_type'] ?? 'emoji');
    $iconValue = sr_reaction_clean_label((string) ($input['icon_value'] ?? ''), 40);
    $colorHex = sr_reaction_clean_color_hex((string) ($input['color_hex'] ?? ''));
    $colorSwatch = sr_reaction_clean_key((string) ($input['color_swatch'] ?? ''), 40);
    $description = sr_reaction_clean_label((string) ($input['description'] ?? ''), 255);
    $status = (string) ($input['status'] ?? 'active');
    $sortOrder = max(0, min(999999, (int) ($input['sort_order'] ?? 100)));
    $errors = [];

    if ($definitionId < 1 && $key === '') {
        $errors[] = '리액션 키는 영문 소문자, 숫자, _ 조합으로 입력하세요.';
    }
    if ($label === '') {
        $errors[] = '표시명을 입력하세요.';
    }
    if (!in_array($iconType, sr_reaction_icon_types(), true)) {
        $errors[] = '아이콘 유형을 확인하세요.';
    }
    if (!in_array($status, sr_reaction_definition_statuses(), true)) {
        $errors[] = '상태 값을 확인하세요.';
    }
    if ((string) ($input['color_hex'] ?? '') !== '' && $colorHex === '') {
        $errors[] = '색상은 #RRGGBB 형식으로 입력하세요.';
    }

    if ($definitionId < 1 && $key !== '') {
        $stmt = $pdo->prepare('SELECT id FROM sr_reaction_definitions WHERE reaction_key = :reaction_key LIMIT 1');
        $stmt->execute(['reaction_key' => $key]);
        if (is_array($stmt->fetch())) {
            $errors[] = '이미 사용 중인 리액션 키입니다.';
        }
    }

    return [
        'errors' => $errors,
        'values' => [
            'id' => $definitionId,
            'reaction_key' => $key,
            'label' => $label,
            'icon_type' => $iconType,
            'icon_value' => $iconValue,
            'color_hex' => $colorHex,
            'color_swatch' => $colorSwatch,
            'description' => $description,
            'status' => $status,
            'sort_order' => $sortOrder,
        ],
    ];
}

function sr_reaction_save_definition(PDO $pdo, array $input, int $actorAccountId): array
{
    $validation = sr_reaction_validate_definition_input($pdo, $input);
    $errors = $validation['errors'];
    $values = $validation['values'];
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    $now = sr_now();
    if ((int) $values['id'] > 0) {
        $stmt = $pdo->prepare(
            'UPDATE sr_reaction_definitions
             SET label = :label,
                 icon_type = :icon_type,
                 icon_value = :icon_value,
                 color_hex = :color_hex,
                 color_swatch = :color_swatch,
                 description = :description,
                 status = :status,
                 sort_order = :sort_order,
                 updated_by_account_id = :updated_by_account_id,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'label' => $values['label'],
            'icon_type' => $values['icon_type'],
            'icon_value' => $values['icon_value'],
            'color_hex' => $values['color_hex'],
            'color_swatch' => $values['color_swatch'],
            'description' => $values['description'],
            'status' => $values['status'],
            'sort_order' => $values['sort_order'],
            'updated_by_account_id' => $actorAccountId,
            'updated_at' => $now,
            'id' => $values['id'],
        ]);
        return ['ok' => true, 'operation' => 'updated'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_reaction_definitions
            (reaction_key, label, icon_type, icon_value, color_hex, color_swatch, description, status, sort_order, is_seed, created_by_account_id, updated_by_account_id, created_at, updated_at)
         VALUES
            (:reaction_key, :label, :icon_type, :icon_value, :color_hex, :color_swatch, :description, :status, :sort_order, 0, :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
    );
    $stmt->execute([
        'reaction_key' => $values['reaction_key'],
        'label' => $values['label'],
        'icon_type' => $values['icon_type'],
        'icon_value' => $values['icon_value'],
        'color_hex' => $values['color_hex'],
        'color_swatch' => $values['color_swatch'],
        'description' => $values['description'],
        'status' => $values['status'],
        'sort_order' => $values['sort_order'],
        'created_by_account_id' => $actorAccountId,
        'updated_by_account_id' => $actorAccountId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return ['ok' => true, 'operation' => 'created'];
}

function sr_reaction_validate_preset_input(PDO $pdo, array $input): array
{
    $presetId = max(0, (int) ($input['id'] ?? 0));
    $presetKey = sr_reaction_clean_key((string) ($input['preset_key'] ?? ''));
    $label = sr_reaction_clean_label((string) ($input['label'] ?? ''));
    $description = sr_reaction_clean_label((string) ($input['description'] ?? ''), 255);
    $status = (string) ($input['status'] ?? 'active');
    $visibleKeyLimit = max(1, min(12, (int) ($input['visible_key_limit'] ?? 6)));
    $sortOrder = max(0, min(999999, (int) ($input['sort_order'] ?? 100)));
    $reactionKeys = [];
    foreach ((array) ($input['reaction_keys'] ?? []) as $key) {
        $cleanKey = is_string($key) ? sr_reaction_clean_key($key) : '';
        if ($cleanKey !== '') {
            $reactionKeys[] = $cleanKey;
        }
    }
    $reactionKeys = array_values(array_unique($reactionKeys));
    $errors = [];

    if ($presetId < 1 && $presetKey === '') {
        $errors[] = 'Preset 키는 영문 소문자, 숫자, _ 조합으로 입력하세요.';
    }
    if ($label === '') {
        $errors[] = 'Preset 이름을 입력하세요.';
    }
    if (!in_array($status, sr_reaction_preset_statuses(), true)) {
        $errors[] = 'Preset 상태 값을 확인하세요.';
    }
    if ($reactionKeys === []) {
        $errors[] = 'Preset에 표시할 리액션을 하나 이상 선택하세요.';
    }

    if ($presetId < 1 && $presetKey !== '') {
        $stmt = $pdo->prepare('SELECT id FROM sr_reaction_presets WHERE preset_key = :preset_key LIMIT 1');
        $stmt->execute(['preset_key' => $presetKey]);
        if (is_array($stmt->fetch())) {
            $errors[] = '이미 사용 중인 preset 키입니다.';
        }
    }

    if ($reactionKeys !== []) {
        $placeholders = [];
        $params = [];
        foreach ($reactionKeys as $index => $key) {
            $param = 'reaction_key_' . (string) $index;
            $placeholders[] = ':' . $param;
            $params[$param] = $key;
        }
        $stmt = $pdo->prepare('SELECT reaction_key FROM sr_reaction_definitions WHERE reaction_key IN (' . implode(', ', $placeholders) . ')');
        $stmt->execute($params);
        $existing = [];
        foreach ($stmt->fetchAll() as $row) {
            $existing[] = (string) ($row['reaction_key'] ?? '');
        }
        foreach ($reactionKeys as $key) {
            if (!in_array($key, $existing, true)) {
                $errors[] = '정의되지 않은 리액션 키가 포함되어 있습니다.';
                break;
            }
        }
    }

    return [
        'errors' => $errors,
        'values' => [
            'id' => $presetId,
            'preset_key' => $presetKey,
            'label' => $label,
            'description' => $description,
            'status' => $status,
            'visible_key_limit' => $visibleKeyLimit,
            'sort_order' => $sortOrder,
            'reaction_keys' => $reactionKeys,
        ],
    ];
}

function sr_reaction_save_preset(PDO $pdo, array $input, int $actorAccountId): array
{
    $validation = sr_reaction_validate_preset_input($pdo, $input);
    $errors = $validation['errors'];
    $values = $validation['values'];
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    $now = sr_now();
    $pdo->beginTransaction();
    try {
        $presetKey = (string) $values['preset_key'];
        if ((int) $values['id'] > 0) {
            $stmt = $pdo->prepare('SELECT preset_key FROM sr_reaction_presets WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $values['id']]);
            $row = $stmt->fetch();
            if (!is_array($row)) {
                throw new RuntimeException('preset_not_found');
            }
            $presetKey = (string) ($row['preset_key'] ?? '');
            $stmt = $pdo->prepare(
                'UPDATE sr_reaction_presets
                 SET label = :label,
                     description = :description,
                     status = :status,
                     selection_policy = \'single\',
                     visible_key_limit = :visible_key_limit,
                     sort_order = :sort_order,
                     updated_by_account_id = :updated_by_account_id,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'label' => $values['label'],
                'description' => $values['description'],
                'status' => $values['status'],
                'visible_key_limit' => $values['visible_key_limit'],
                'sort_order' => $values['sort_order'],
                'updated_by_account_id' => $actorAccountId,
                'updated_at' => $now,
                'id' => $values['id'],
            ]);
            $operation = 'updated';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_reaction_presets
                    (preset_key, label, description, status, selection_policy, visible_key_limit, sort_order, created_by_account_id, updated_by_account_id, created_at, updated_at)
                 VALUES
                    (:preset_key, :label, :description, :status, \'single\', :visible_key_limit, :sort_order, :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
            );
            $stmt->execute([
                'preset_key' => $presetKey,
                'label' => $values['label'],
                'description' => $values['description'],
                'status' => $values['status'],
                'visible_key_limit' => $values['visible_key_limit'],
                'sort_order' => $values['sort_order'],
                'created_by_account_id' => $actorAccountId,
                'updated_by_account_id' => $actorAccountId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $operation = 'created';
        }

        $stmt = $pdo->prepare('DELETE FROM sr_reaction_preset_items WHERE preset_key = :preset_key');
        $stmt->execute(['preset_key' => $presetKey]);
        $sortOrder = 10;
        foreach ($values['reaction_keys'] as $reactionKey) {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_reaction_preset_items
                    (preset_key, reaction_key, sort_order, is_public, created_at, updated_at)
                 VALUES
                    (:preset_key, :reaction_key, :sort_order, 1, :created_at, :updated_at)'
            );
            $stmt->execute([
                'preset_key' => $presetKey,
                'reaction_key' => $reactionKey,
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sortOrder += 10;
        }

        $pdo->commit();
        return ['ok' => true, 'operation' => $operation];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_log_exception($exception, 'reaction_preset_save');
        return ['ok' => false, 'errors' => ['Preset 저장 중 오류가 발생했습니다.']];
    }
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

    try {
        return $createAccountEventFunction($pdo, [
            'account_id' => $recipientAccountId,
            'module_key' => 'reaction',
            'event_key' => 'target.reacted',
            'created_by_account_id' => $actorAccountId,
            'metadata' => [
                'reaction_key' => $reactionKey,
                'reaction_label' => $reactionLabel,
                'member_name' => $actorName,
                'target_module' => (string) ($target['target_module'] ?? ''),
                'target_type' => (string) ($target['target_type'] ?? ''),
                'target_id' => (string) ($target['target_id'] ?? ''),
                'target_label' => (string) ($target['label'] ?? ''),
                'link_url' => (string) ($target['public_url'] ?? ''),
            ],
        ]) !== null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'reaction_notification_event_create');
    }

    return false;
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
