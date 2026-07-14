<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';
require_once dirname(__DIR__, 3) . '/core/helpers/upload.php';

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
    return ['emoji', 'material', 'image'];
}

function sr_reaction_icon_type_label(string $iconType): string
{
    return [
        'emoji' => '이모지',
        'material' => 'Material 아이콘',
        'image' => '이미지 업로드',
    ][$iconType] ?? $iconType;
}

function sr_reaction_icon_upload_max_bytes(): int
{
    return 1048576;
}

function sr_reaction_icon_upload_was_provided(mixed $file): bool
{
    return sr_upload_was_provided($file);
}

function sr_reaction_icon_image_mime_is_allowed(string $mimeType): bool
{
    return sr_image_mime_is_allowed($mimeType);
}

function sr_reaction_icon_image_format_for_mime(string $mimeType): string
{
    return sr_image_format_for_mime($mimeType);
}

function sr_reaction_upload_icon_image(array $file): array
{
    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_reaction_icon_upload_max_bytes(),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $sourcePath = (string) $validated['tmp_name'];
    $dimensions = @getimagesize($sourcePath);
    if (!is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1) {
        throw new RuntimeException('리액션 아이콘 이미지 크기를 확인할 수 없습니다.');
    }
    if ((int) $dimensions[0] > 512 || (int) $dimensions[1] > 512) {
        throw new RuntimeException('리액션 아이콘 이미지는 가로/세로 512px 이하만 업로드할 수 있습니다.');
    }

    $targetFormat = sr_reaction_icon_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException('허용되지 않은 리액션 아이콘 이미지 형식입니다.');
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/reaction-icons/' . $datePath;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('리액션 아이콘 임시 저장 디렉터리를 만들 수 없습니다.');
    }

    $storedName = sr_upload_random_filename($targetFormat);
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);

    if (!sr_upload_reencode_image($sourcePath, $targetPath, $targetFormat, [
        'max_pixels' => 262144,
        'quality' => 86,
    ])) {
        throw new RuntimeException('리액션 아이콘 이미지 재인코딩에 실패했습니다.');
    }

    $storedMimeType = sr_upload_detect_mime($targetPath);
    if (!sr_reaction_icon_image_mime_is_allowed($storedMimeType)) {
        @unlink($targetPath);
        throw new RuntimeException('저장된 리액션 아이콘 이미지 MIME을 확인할 수 없습니다.');
    }

    $storageKey = 'reaction/icons/' . $datePath . '/' . $storedName;
    $stored = sr_storage_put_file($targetPath, $storageKey, [
        'content_type' => $storedMimeType,
    ]);
    @unlink($targetPath);

    return [
        'driver' => (string) $stored['driver'],
        'storage_key' => $storageKey,
        'storage_reference' => sr_storage_reference((string) $stored['driver'], $storageKey),
        'mime_type' => $storedMimeType,
    ];
}

function sr_reaction_icon_image_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Areaction/icons/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $key) === 1;
}

function sr_reaction_icon_image_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_reaction_icon_image_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
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

function sr_reaction_admin_record_filters(array $input): array
{
    return [
        'account_id' => max(0, (int) ($input['account_id'] ?? 0)),
        'target_module' => sr_reaction_clean_key((string) ($input['target_module'] ?? ''), 60),
        'target_type' => sr_reaction_clean_key((string) ($input['target_type'] ?? ''), 60),
        'target_id' => sr_reaction_target_id((string) ($input['target_id'] ?? '')),
        'reaction_key' => sr_reaction_clean_key((string) ($input['reaction_key'] ?? '')),
    ];
}

function sr_reaction_admin_record_query_parts(array $filters = []): array
{
    $filters = sr_reaction_admin_record_filters($filters);
    $where = [];
    $params = [];

    if ((int) $filters['account_id'] > 0) {
        $where[] = 'r.account_id = :account_id';
        $params['account_id'] = (int) $filters['account_id'];
    }
    foreach (['target_module', 'target_type', 'target_id', 'reaction_key'] as $field) {
        if ((string) $filters[$field] !== '') {
            $where[] = 'r.' . $field . ' = :' . $field;
            $params[$field] = (string) $filters[$field];
        }
    }

    return [
        'where' => $where === [] ? '' : 'WHERE ' . implode(' AND ', $where),
        'params' => $params,
    ];
}

function sr_reaction_admin_record_count(PDO $pdo, array $filters = []): int
{
    if (!sr_reaction_tables_available($pdo)) {
        return 0;
    }

    $query = sr_reaction_admin_record_query_parts($filters);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_reaction_records r ' . $query['where']);
    $stmt->execute($query['params']);

    return max(0, (int) $stmt->fetchColumn());
}

function sr_reaction_admin_records(PDO $pdo, array $filters = [], int $limit = 100, int $offset = 0): array
{
    if (!sr_reaction_tables_available($pdo)) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $query = sr_reaction_admin_record_query_parts($filters);

    $stmt = $pdo->prepare(
        'SELECT r.*,
                d.label AS reaction_label,
                d.status AS reaction_status
         FROM sr_reaction_records r
         LEFT JOIN sr_reaction_definitions d ON d.reaction_key = r.reaction_key
         ' . $query['where'] . '
         ORDER BY r.updated_at DESC, r.id DESC
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($query['params'] as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
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
    $iconValue = $iconType === 'image'
        ? trim((string) ($input['icon_value'] ?? ''))
        : sr_reaction_clean_label((string) ($input['icon_value'] ?? ''), 80);
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
    if ($iconType === 'material') {
        if ($iconValue === '') {
            $errors[] = 'Material 아이콘 key를 입력하세요.';
        }
        $iconValue = sr_material_icon_name($iconValue);
    } elseif ($iconType === 'image') {
        if ($iconValue === '' || sr_reaction_icon_image_storage_reference($iconValue) === null) {
            $errors[] = '이미지 아이콘을 선택한 경우 아이콘 이미지를 업로드하세요.';
        }
    } elseif ($iconValue === '') {
        $errors[] = '아이콘 값을 입력하세요.';
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
        $keyStmt = $pdo->prepare('SELECT reaction_key FROM sr_reaction_definitions WHERE id = :id LIMIT 1');
        $keyStmt->execute(['id' => $values['id']]);
        $keyRow = $keyStmt->fetch();
        if (!is_array($keyRow)) {
            return ['ok' => false, 'errors' => ['리액션 정의를 찾을 수 없습니다.']];
        }
        $definitionKey = sr_reaction_clean_key((string) ($keyRow['reaction_key'] ?? ''));
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
        return ['ok' => true, 'operation' => 'updated', 'reaction_key' => $definitionKey, 'values' => $values];
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

    return ['ok' => true, 'operation' => 'created', 'reaction_key' => $values['reaction_key'], 'values' => $values];
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
        return ['ok' => true, 'operation' => $operation, 'preset_key' => $presetKey, 'values' => $values];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_log_exception($exception, 'reaction_preset_save');
        return ['ok' => false, 'errors' => ['Preset 저장 중 오류가 발생했습니다.']];
    }
}

function sr_reaction_cleanup_policies(): array
{
    return ['keep_public_hidden', 'keep_admin_statistics', 'delete', 'merge'];
}

function sr_reaction_record_impact(PDO $pdo, string $reactionKey): array
{
    $reactionKey = sr_reaction_clean_key($reactionKey);
    if ($reactionKey === '' || !sr_reaction_tables_available($pdo)) {
        return ['record_count' => 0, 'target_count' => 0, 'account_count' => 0];
    }

    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = '';
    }
    $targetExpression = $driver === 'sqlite'
        ? "target_module || '/' || target_type || '/' || target_id"
        : "CONCAT(target_module, '/', target_type, '/', target_id)";
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS record_count,
                COUNT(DISTINCT ' . $targetExpression . ') AS target_count,
                COUNT(DISTINCT account_id) AS account_count
         FROM sr_reaction_records
         WHERE reaction_key = :reaction_key'
    );
    $stmt->execute(['reaction_key' => $reactionKey]);
    $row = $stmt->fetch();

    return [
        'record_count' => is_array($row) ? (int) ($row['record_count'] ?? 0) : 0,
        'target_count' => is_array($row) ? (int) ($row['target_count'] ?? 0) : 0,
        'account_count' => is_array($row) ? (int) ($row['account_count'] ?? 0) : 0,
    ];
}

function sr_reaction_cleanup_disabled_records(PDO $pdo, string $reactionKey, string $policy, string $mergeTargetKey, string $confirmation, int $actorAccountId): array
{
    $reactionKey = sr_reaction_clean_key($reactionKey);
    $policy = sr_reaction_clean_key($policy, 40);
    $mergeTargetKey = sr_reaction_clean_key($mergeTargetKey);
    $errors = [];
    if ($reactionKey === '') {
        $errors[] = '정리할 리액션 키를 확인하세요.';
    }
    if (!in_array($policy, sr_reaction_cleanup_policies(), true)) {
        $errors[] = '처리 방식을 확인하세요.';
    }

    $definition = null;
    if ($reactionKey !== '') {
        $stmt = $pdo->prepare('SELECT * FROM sr_reaction_definitions WHERE reaction_key = :reaction_key LIMIT 1');
        $stmt->execute(['reaction_key' => $reactionKey]);
        $definition = $stmt->fetch();
        if (!is_array($definition)) {
            $errors[] = '리액션 정의를 찾을 수 없습니다.';
        } elseif ((string) ($definition['status'] ?? '') !== 'disabled') {
            $errors[] = '사용 중지된 리액션만 기존 사용 기록을 정리할 수 있습니다.';
        }
    }

    if (in_array($policy, ['delete', 'merge'], true) && $confirmation !== $reactionKey) {
        $errors[] = '확인 문구로 리액션 키를 정확히 입력하세요.';
    }
    if ($policy === 'merge') {
        if ($mergeTargetKey === '' || $mergeTargetKey === $reactionKey) {
            $errors[] = '병합 대상 리액션 키를 확인하세요.';
        } elseif (sr_reaction_active_definition($pdo, $mergeTargetKey) === null) {
            $errors[] = '병합 대상은 사용 중인 리액션이어야 합니다.';
        }
    }
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    $impact = sr_reaction_record_impact($pdo, $reactionKey);
    if (in_array($policy, ['keep_public_hidden', 'keep_admin_statistics'], true)) {
        return [
            'ok' => true,
            'policy' => $policy,
            'impact' => $impact,
            'deleted_count' => 0,
            'merged_count' => 0,
            'conflict_deleted_count' => 0,
        ];
    }

    $deletedCount = 0;
    $mergedCount = 0;
    $conflictDeletedCount = 0;
    $pdo->beginTransaction();
    try {
        if ($policy === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM sr_reaction_records WHERE reaction_key = :reaction_key');
            $stmt->execute(['reaction_key' => $reactionKey]);
            $deletedCount = $stmt->rowCount();
        } else {
            $stmt = $pdo->prepare('SELECT * FROM sr_reaction_records WHERE reaction_key = :reaction_key ORDER BY id ASC');
            $stmt->execute(['reaction_key' => $reactionKey]);
            foreach ($stmt->fetchAll() as $row) {
                $recordId = (int) ($row['id'] ?? 0);
                $accountId = (int) ($row['account_id'] ?? 0);
                $targetModule = (string) ($row['target_module'] ?? '');
                $targetType = (string) ($row['target_type'] ?? '');
                $targetId = (string) ($row['target_id'] ?? '');
                $existing = sr_reaction_my_record($pdo, $accountId, $targetModule, $targetType, $targetId, true);
                if (is_array($existing) && (int) ($existing['id'] ?? 0) !== $recordId && (string) ($existing['reaction_key'] ?? '') === $mergeTargetKey) {
                    $delete = $pdo->prepare('DELETE FROM sr_reaction_records WHERE id = :id');
                    $delete->execute(['id' => $recordId]);
                    $conflictDeletedCount += $delete->rowCount();
                    continue;
                }

                $update = $pdo->prepare('UPDATE sr_reaction_records SET reaction_key = :reaction_key, updated_at = :updated_at WHERE id = :id');
                $update->execute([
                    'reaction_key' => $mergeTargetKey,
                    'updated_at' => sr_now(),
                    'id' => $recordId,
                ]);
                $mergedCount += $update->rowCount();
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_log_exception($exception, 'reaction_disabled_records_cleanup');
        return ['ok' => false, 'errors' => ['기존 사용 기록 처리 중 오류가 발생했습니다.']];
    }

    return [
        'ok' => true,
        'policy' => $policy,
        'impact' => $impact,
        'deleted_count' => $deletedCount,
        'merged_count' => $mergedCount,
        'conflict_deleted_count' => $conflictDeletedCount,
    ];
}
