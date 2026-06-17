<?php

declare(strict_types=1);

function sr_community_post_extra_values_column_exists(PDO $pdo): bool
{
    static $existsByConnection = [];
    $key = (string) spl_object_id($pdo);
    if (array_key_exists($key, $existsByConnection)) {
        return $existsByConnection[$key];
    }

    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->query('PRAGMA table_info(sr_community_posts)');
            foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                if ((string) ($row['name'] ?? '') === 'extra_values_json') {
                    $existsByConnection[$key] = true;
                    return true;
                }
            }
            $existsByConnection[$key] = false;
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'sr_community_posts\'
               AND COLUMN_NAME = \'extra_values_json\''
        );
        $stmt->execute();
        $existsByConnection[$key] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
}

function sr_community_post_extra_values_select(PDO $pdo, string $alias): string
{
    return sr_community_post_extra_values_column_exists($pdo)
        ? ', ' . $alias . '.extra_values_json'
        : ", '' AS extra_values_json";
}

function sr_community_board_field_definitions_table_exists(PDO $pdo): bool
{
    return function_exists('sr_community_optional_table_exists')
        && sr_community_optional_table_exists($pdo, 'sr_community_board_field_definitions');
}

function sr_community_post_field_values_table_exists(PDO $pdo): bool
{
    return function_exists('sr_community_optional_table_exists')
        && sr_community_optional_table_exists($pdo, 'sr_community_post_field_values');
}

function sr_community_extra_field_type(string $type): string
{
    return in_array($type, ['text', 'textarea', 'select', 'checkbox'], true) ? $type : 'text';
}

function sr_community_extra_field_scalar_string(mixed $value): string
{
    return is_scalar($value) ? (string) $value : '';
}

function sr_community_normalize_extra_field_definitions(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $definitions = [];
    $seenKeys = [];
    foreach ($raw as $item) {
        if (!is_array($item) || count($definitions) >= 20) {
            continue;
        }

        $key = strtolower(trim(sr_community_extra_field_scalar_string($item['key'] ?? '')));
        if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) !== 1 || isset($seenKeys[$key])) {
            continue;
        }
        $label = trim(preg_replace('/\s+/', ' ', sr_community_extra_field_scalar_string($item['label'] ?? '')) ?? '');
        $label = function_exists('mb_substr') ? mb_substr($label, 0, 120) : substr($label, 0, 120);
        if ($label === '') {
            continue;
        }

        $type = sr_community_extra_field_type(sr_community_extra_field_scalar_string($item['type'] ?? 'text'));
        $options = [];
        if ($type === 'select') {
            $rawOptions = is_array($item['options'] ?? null) ? $item['options'] : [];
            foreach ($rawOptions as $option) {
                $option = trim(preg_replace('/\s+/', ' ', sr_community_extra_field_scalar_string($option)) ?? '');
                $option = function_exists('mb_substr') ? mb_substr($option, 0, 120) : substr($option, 0, 120);
                if ($option !== '' && !in_array($option, $options, true)) {
                    $options[] = $option;
                }
                if (count($options) >= 50) {
                    break;
                }
            }
            if ($options === []) {
                continue;
            }
        }

        $definitions[] = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => !empty($item['required']),
            'options' => $options,
            'visibility' => in_array(sr_community_extra_field_scalar_string($item['visibility'] ?? 'public'), ['public', 'admin'], true) ? sr_community_extra_field_scalar_string($item['visibility'] ?? 'public') : 'public',
            'show_on_view' => array_key_exists('show_on_view', $item) ? !empty($item['show_on_view']) : true,
            'show_in_admin' => !empty($item['show_in_admin']),
            'privacy_purpose' => trim(sr_community_extra_field_scalar_string($item['privacy_purpose'] ?? '')),
            'export_policy' => in_array(sr_community_extra_field_scalar_string($item['export_policy'] ?? 'include'), ['include', 'exclude'], true) ? sr_community_extra_field_scalar_string($item['export_policy'] ?? 'include') : 'include',
            'cleanup_policy' => in_array(sr_community_extra_field_scalar_string($item['cleanup_policy'] ?? 'anonymize'), ['anonymize', 'retain'], true) ? sr_community_extra_field_scalar_string($item['cleanup_policy'] ?? 'anonymize') : 'anonymize',
        ];
        $seenKeys[$key] = true;
    }

    return $definitions;
}

function sr_community_extra_field_definition_validation_errors(mixed $raw): array
{
    $errors = [];
    if (!is_array($raw)) {
        return ['추가 입력 항목 JSON은 배열이어야 합니다.'];
    }
    if (count($raw) > 20) {
        $errors[] = '추가 입력 항목은 20개 이하로 입력해 주세요.';
    }

    $seenKeys = [];
    foreach ($raw as $index => $item) {
        $rowLabel = '추가 입력 항목 #' . (string) ((int) $index + 1);
        if (!is_array($item)) {
            $errors[] = $rowLabel . ' 형식이 올바르지 않습니다.';
            continue;
        }

        $keyRaw = $item['key'] ?? '';
        $key = strtolower(trim(sr_community_extra_field_scalar_string($keyRaw)));
        if (!is_scalar($keyRaw)) {
            $errors[] = $rowLabel . '의 관리용 키 형식이 올바르지 않습니다.';
        }
        if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) !== 1) {
            $errors[] = $rowLabel . '의 관리용 키는 영문 소문자로 시작하고 소문자, 숫자, _만 사용할 수 있습니다.';
        } elseif (isset($seenKeys[$key])) {
            $errors[] = $rowLabel . '의 관리용 키가 중복되었습니다.';
        }
        if ($key !== '') {
            $seenKeys[$key] = true;
        }

        $labelRaw = $item['label'] ?? '';
        $label = trim(preg_replace('/\s+/', ' ', sr_community_extra_field_scalar_string($labelRaw)) ?? '');
        $labelLength = function_exists('mb_strlen') ? mb_strlen($label) : strlen($label);
        if (!is_scalar($labelRaw)) {
            $errors[] = $rowLabel . '의 라벨 형식이 올바르지 않습니다.';
        } elseif ($label === '') {
            $errors[] = $rowLabel . '의 라벨을 입력해 주세요.';
        } elseif ($labelLength > 120) {
            $errors[] = $rowLabel . '의 라벨은 120자 이하로 입력해 주세요.';
        }

        $typeRaw = $item['type'] ?? 'text';
        $type = sr_community_extra_field_scalar_string($typeRaw);
        if (!is_scalar($typeRaw)) {
            $errors[] = $rowLabel . '의 유형 형식이 올바르지 않습니다.';
        }
        if (!in_array($type, ['text', 'textarea', 'select', 'checkbox'], true)) {
            $errors[] = $rowLabel . '의 유형 값이 올바르지 않습니다.';
        }

        $visibilityRaw = $item['visibility'] ?? 'public';
        $visibility = sr_community_extra_field_scalar_string($visibilityRaw);
        if (!is_scalar($visibilityRaw)) {
            $errors[] = $rowLabel . '의 공개 범위 형식이 올바르지 않습니다.';
        }
        if (!in_array($visibility, ['public', 'admin'], true)) {
            $errors[] = $rowLabel . '의 공개 범위 값이 올바르지 않습니다.';
        }

        $privacyPurposeRaw = $item['privacy_purpose'] ?? '';
        $privacyPurpose = trim(sr_community_extra_field_scalar_string($privacyPurposeRaw));
        $privacyPurposeLength = function_exists('mb_strlen') ? mb_strlen($privacyPurpose) : strlen($privacyPurpose);
        if (!is_scalar($privacyPurposeRaw)) {
            $errors[] = $rowLabel . '의 개인정보 목적 형식이 올바르지 않습니다.';
        } elseif ($privacyPurposeLength > 255) {
            $errors[] = $rowLabel . '의 개인정보 목적은 255자 이하로 입력해 주세요.';
        }

        $exportPolicyRaw = $item['export_policy'] ?? 'include';
        $exportPolicy = sr_community_extra_field_scalar_string($exportPolicyRaw);
        if (!is_scalar($exportPolicyRaw)) {
            $errors[] = $rowLabel . '의 export 정책 형식이 올바르지 않습니다.';
        }
        if (!in_array($exportPolicy, ['include', 'exclude'], true)) {
            $errors[] = $rowLabel . '의 export 정책 값이 올바르지 않습니다.';
        }

        $cleanupPolicyRaw = $item['cleanup_policy'] ?? 'anonymize';
        $cleanupPolicy = sr_community_extra_field_scalar_string($cleanupPolicyRaw);
        if (!is_scalar($cleanupPolicyRaw)) {
            $errors[] = $rowLabel . '의 cleanup 정책 형식이 올바르지 않습니다.';
        }
        if (!in_array($cleanupPolicy, ['anonymize', 'retain'], true)) {
            $errors[] = $rowLabel . '의 cleanup 정책 값이 올바르지 않습니다.';
        }

        if ($type === 'select') {
            if (!is_array($item['options'] ?? null)) {
                $errors[] = $rowLabel . '의 선택지는 배열이어야 합니다.';
                continue;
            }
            $options = [];
            foreach ((array) $item['options'] as $optionIndex => $option) {
                if (!is_scalar($option)) {
                    $errors[] = $rowLabel . '의 선택지 #' . (string) ((int) $optionIndex + 1) . ' 형식이 올바르지 않습니다.';
                    continue;
                }
                $optionValue = trim(preg_replace('/\s+/', ' ', (string) $option) ?? '');
                $optionLength = function_exists('mb_strlen') ? mb_strlen($optionValue) : strlen($optionValue);
                if ($optionValue === '') {
                    $errors[] = $rowLabel . '의 선택지는 빈 값으로 저장할 수 없습니다.';
                    continue;
                }
                if ($optionLength > 120) {
                    $errors[] = $rowLabel . '의 선택지는 120자 이하로 입력해 주세요.';
                }
                if (in_array($optionValue, $options, true)) {
                    $errors[] = $rowLabel . '의 선택지가 중복되었습니다.';
                }
                $options[] = $optionValue;
            }
            if ($options === []) {
                $errors[] = $rowLabel . '의 선택지는 하나 이상 입력해 주세요.';
            }
            if (count($options) > 50) {
                $errors[] = $rowLabel . '의 선택지는 50개 이하로 입력해 주세요.';
            }
        }
    }

    return $errors;
}

function sr_community_extra_field_definitions_from_storage(PDO $pdo, int $boardId): array
{
    if ($boardId < 1 || !sr_community_board_field_definitions_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT field_key, label, field_type, is_required, visibility, show_on_view, show_in_admin, sort_order, validation_json, privacy_purpose, export_policy, cleanup_policy
         FROM sr_community_board_field_definitions
         WHERE board_id = :board_id
           AND status = \'enabled\'
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['board_id' => $boardId]);

    $definitions = [];
    foreach ($stmt->fetchAll() as $row) {
        $validation = json_decode((string) ($row['validation_json'] ?? ''), true);
        $definitions[] = [
            'key' => (string) ($row['field_key'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'type' => (string) ($row['field_type'] ?? 'text'),
            'required' => (int) ($row['is_required'] ?? 0) === 1,
            'options' => is_array($validation['options'] ?? null) ? $validation['options'] : [],
            'visibility' => (string) ($row['visibility'] ?? 'public'),
            'show_on_view' => (int) ($row['show_on_view'] ?? 1) === 1,
            'show_in_admin' => (int) ($row['show_in_admin'] ?? 0) === 1,
            'privacy_purpose' => (string) ($row['privacy_purpose'] ?? ''),
            'export_policy' => (string) ($row['export_policy'] ?? 'include'),
            'cleanup_policy' => (string) ($row['cleanup_policy'] ?? 'anonymize'),
        ];
    }

    return sr_community_normalize_extra_field_definitions($definitions);
}

function sr_community_extra_field_definitions_from_json(string $json): array
{
    $json = trim($json);
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    return sr_community_normalize_extra_field_definitions($decoded);
}

function sr_community_extra_field_definitions_json_from_input(string $json): ?string
{
    $json = trim($json);
    if ($json === '') {
        return '[]';
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return null;
    }
    if (sr_community_extra_field_definition_validation_errors($decoded) !== []) {
        return null;
    }

    return json_encode(sr_community_normalize_extra_field_definitions($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function sr_community_extra_field_definitions_input_errors(?string $json): array
{
    if (!is_string($json)) {
        return ['추가 입력 항목 JSON은 20000자 이하로 입력해 주세요.'];
    }

    $json = trim($json);
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return ['추가 입력 항목 JSON 형식을 확인해 주세요.'];
    }

    return sr_community_extra_field_definition_validation_errors($decoded);
}

function sr_community_board_extra_field_definitions(PDO $pdo, array $board): array
{
    $boardId = (int) ($board['id'] ?? 0);
    if ($boardId > 0 && sr_community_board_setting_source($pdo, $boardId, 'extra_fields_json') === 'board') {
        $stored = sr_community_extra_field_definitions_from_storage($pdo, $boardId);
        if ($stored !== []) {
            return $stored;
        }
    }

    $json = sr_community_effective_board_setting($pdo, $board, 'extra_fields_json', '[]');
    return sr_community_extra_field_definitions_from_json($json);
}

function sr_community_sync_board_field_definitions(PDO $pdo, int $boardId, array $definitions): void
{
    if ($boardId < 1 || !sr_community_board_field_definitions_table_exists($pdo)) {
        return;
    }

    $now = sr_now();
    $pdo->prepare(
        'UPDATE sr_community_board_field_definitions
         SET status = \'disabled\', updated_at = :updated_at
         WHERE board_id = :board_id'
    )->execute([
        'board_id' => $boardId,
        'updated_at' => $now,
    ]);

    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $insertSql = $isSqlite
        ? 'INSERT INTO sr_community_board_field_definitions
            (board_id, field_key, label, field_type, is_required, visibility, show_on_view, show_in_admin, sort_order, validation_json, privacy_purpose, export_policy, cleanup_policy, status, created_at, updated_at)
         VALUES
            (:board_id, :field_key, :label, :field_type, :is_required, :visibility, :show_on_view, :show_in_admin, :sort_order, :validation_json, :privacy_purpose, :export_policy, :cleanup_policy, \'enabled\', :created_at, :updated_at)
         ON CONFLICT(board_id, field_key) DO UPDATE SET
            label = excluded.label,
            field_type = excluded.field_type,
            is_required = excluded.is_required,
            visibility = excluded.visibility,
            show_on_view = excluded.show_on_view,
            show_in_admin = excluded.show_in_admin,
            sort_order = excluded.sort_order,
            validation_json = excluded.validation_json,
            privacy_purpose = excluded.privacy_purpose,
            export_policy = excluded.export_policy,
            cleanup_policy = excluded.cleanup_policy,
            status = \'enabled\',
            updated_at = excluded.updated_at'
        : 'INSERT INTO sr_community_board_field_definitions
            (board_id, field_key, label, field_type, is_required, visibility, show_on_view, show_in_admin, sort_order, validation_json, privacy_purpose, export_policy, cleanup_policy, status, created_at, updated_at)
         VALUES
            (:board_id, :field_key, :label, :field_type, :is_required, :visibility, :show_on_view, :show_in_admin, :sort_order, :validation_json, :privacy_purpose, :export_policy, :cleanup_policy, \'enabled\', :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            label = VALUES(label),
            field_type = VALUES(field_type),
            is_required = VALUES(is_required),
            visibility = VALUES(visibility),
            show_on_view = VALUES(show_on_view),
            show_in_admin = VALUES(show_in_admin),
            sort_order = VALUES(sort_order),
            validation_json = VALUES(validation_json),
            privacy_purpose = VALUES(privacy_purpose),
            export_policy = VALUES(export_policy),
            cleanup_policy = VALUES(cleanup_policy),
            status = \'enabled\',
            updated_at = VALUES(updated_at)';
    $stmt = $pdo->prepare($insertSql);

    foreach (array_values($definitions) as $index => $definition) {
        $stmt->execute([
            'board_id' => $boardId,
            'field_key' => (string) ($definition['key'] ?? ''),
            'label' => (string) ($definition['label'] ?? ''),
            'field_type' => (string) ($definition['type'] ?? 'text'),
            'is_required' => !empty($definition['required']) ? 1 : 0,
            'visibility' => (string) ($definition['visibility'] ?? 'public'),
            'show_on_view' => !empty($definition['show_on_view']) ? 1 : 0,
            'show_in_admin' => !empty($definition['show_in_admin']) ? 1 : 0,
            'sort_order' => ($index + 1) * 10,
            'validation_json' => json_encode(['options' => array_values((array) ($definition['options'] ?? []))], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'privacy_purpose' => (string) ($definition['privacy_purpose'] ?? ''),
            'export_policy' => (string) ($definition['export_policy'] ?? 'include'),
            'cleanup_policy' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function sr_community_sync_group_board_field_definitions(PDO $pdo, int $groupId, array $definitions): int
{
    if ($groupId < 1
        || !sr_community_board_field_definitions_table_exists($pdo)
        || !sr_community_optional_table_exists($pdo, 'sr_community_board_setting_sources')) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT b.id
         FROM sr_community_boards b
         INNER JOIN sr_community_board_setting_sources s
            ON s.board_id = b.id
           AND s.setting_key = \'extra_fields_json\'
           AND s.source = \'group\'
         WHERE b.board_group_id = :group_id
         ORDER BY b.id ASC'
    );
    $stmt->execute(['group_id' => $groupId]);
    $boardIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    foreach ($boardIds as $boardId) {
        sr_community_sync_board_field_definitions($pdo, (int) $boardId, $definitions);
    }

    return count($boardIds);
}

function sr_community_extra_field_input_values(array $definitions): array
{
    $posted = $_POST['community_extra_fields'] ?? [];
    $posted = is_array($posted) ? $posted : [];
    $values = [];
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        $type = (string) ($definition['type'] ?? 'text');
        if ($type === 'checkbox') {
            if (isset($posted[$key]) && !is_scalar($posted[$key])) {
                $values[$key] = ['invalid_extra_field_value' => true];
                continue;
            }
            $values[$key] = isset($posted[$key]) && (string) $posted[$key] === '1' ? '1' : '0';
            continue;
        }
        $value = $posted[$key] ?? '';
        $values[$key] = is_scalar($value) ? trim((string) $value) : ['invalid_extra_field_value' => true];
    }

    return $values;
}

function sr_community_extra_field_value_max_length(string $type): int
{
    return $type === 'textarea' ? 5000 : 1000;
}

function sr_community_validate_extra_field_values(array $definitions, array $values): array
{
    $errors = [];
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        $label = (string) ($definition['label'] ?? $key);
        $type = (string) ($definition['type'] ?? 'text');
        $rawValue = $values[$key] ?? '';
        if (is_array($rawValue)) {
            $errors[] = $label . ' 값 형식이 올바르지 않습니다.';
            continue;
        }
        $value = (string) $rawValue;
        if (!empty($definition['required'])) {
            if ($type === 'checkbox') {
                if ($value !== '1') {
                    $errors[] = $label . '을(를) 확인해 주세요.';
                }
            } elseif (trim($value) === '') {
                $errors[] = $label . '을(를) 입력해 주세요.';
            }
        }
        if ($type !== 'checkbox') {
            $maxLength = sr_community_extra_field_value_max_length($type);
            $valueLength = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            if ($valueLength > $maxLength) {
                $errors[] = $label . '은(는) ' . (string) $maxLength . '자 이하로 입력해 주세요.';
            }
        }
        if ($type === 'select' && $value !== '' && !in_array($value, (array) ($definition['options'] ?? []), true)) {
            $errors[] = $label . ' 선택 값이 올바르지 않습니다.';
        }
        if ($type === 'checkbox' && !in_array($value, ['0', '1'], true)) {
            $errors[] = $label . ' 값이 올바르지 않습니다.';
        }
    }

    return $errors;
}

function sr_community_extra_field_values_json(array $definitions, array $values): string
{
    $stored = [];
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        if ($key === '') {
            continue;
        }
        $rawValue = $values[$key] ?? '';
        $value = is_array($rawValue) ? '' : (string) $rawValue;
        $stored[$key] = [
            'label' => (string) ($definition['label'] ?? $key),
            'type' => (string) ($definition['type'] ?? 'text'),
            'value' => $value,
            'visibility' => (string) ($definition['visibility'] ?? 'public'),
            'show_on_view' => !empty($definition['show_on_view']),
            'show_in_admin' => !empty($definition['show_in_admin']),
            'export_policy' => (string) ($definition['export_policy'] ?? 'include'),
            'cleanup_policy' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
        ];
    }

    return json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function sr_community_extra_field_values_export_json(string $json): string
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return '[]';
    }

    foreach ($decoded as $key => $item) {
        if (!is_array($item)) {
            unset($decoded[$key]);
            continue;
        }
        $exportPolicy = (string) ($item['export_policy'] ?? 'include');
        if ($exportPolicy !== 'include') {
            unset($decoded[$key]);
        }
    }

    return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function sr_community_extra_field_values_cleanup_json(string $json): string
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return '[]';
    }

    foreach ($decoded as &$item) {
        if (!is_array($item)) {
            continue;
        }
        $cleanupPolicy = (string) ($item['cleanup_policy'] ?? 'anonymize');
        if ($cleanupPolicy !== 'retain') {
            $item['value'] = '';
        }
    }
    unset($item);

    return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function sr_community_save_post_field_values(PDO $pdo, int $postId, array $definitions, array $values): void
{
    if ($postId < 1 || !sr_community_post_field_values_table_exists($pdo)) {
        return;
    }

    $now = sr_now();
    $pdo->prepare('DELETE FROM sr_community_post_field_values WHERE post_id = :post_id')->execute(['post_id' => $postId]);
    if ($definitions === []) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_post_field_values
            (post_id, field_key, label_snapshot, field_type_snapshot, visibility_snapshot, show_on_view_snapshot, show_in_admin_snapshot, privacy_purpose_snapshot, export_policy_snapshot, cleanup_policy_snapshot, value_text, value_json, created_at, updated_at)
         VALUES
            (:post_id, :field_key, :label_snapshot, :field_type_snapshot, :visibility_snapshot, :show_on_view_snapshot, :show_in_admin_snapshot, :privacy_purpose_snapshot, :export_policy_snapshot, :cleanup_policy_snapshot, :value_text, :value_json, :created_at, :updated_at)'
    );
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        if ($key === '') {
            continue;
        }
        $rawValue = $values[$key] ?? '';
        $value = is_array($rawValue) ? '' : (string) $rawValue;
        $stmt->execute([
            'post_id' => $postId,
            'field_key' => $key,
            'label_snapshot' => (string) ($definition['label'] ?? $key),
            'field_type_snapshot' => (string) ($definition['type'] ?? 'text'),
            'visibility_snapshot' => (string) ($definition['visibility'] ?? 'public'),
            'show_on_view_snapshot' => !empty($definition['show_on_view']) ? 1 : 0,
            'show_in_admin_snapshot' => !empty($definition['show_in_admin']) ? 1 : 0,
            'privacy_purpose_snapshot' => (string) ($definition['privacy_purpose'] ?? ''),
            'export_policy_snapshot' => (string) ($definition['export_policy'] ?? 'include'),
            'cleanup_policy_snapshot' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
            'value_text' => $value,
            'value_json' => json_encode([
                'value' => $value,
                'visibility' => (string) ($definition['visibility'] ?? 'public'),
                'show_on_view' => !empty($definition['show_on_view']),
                'show_in_admin' => !empty($definition['show_in_admin']),
                'privacy_purpose' => (string) ($definition['privacy_purpose'] ?? ''),
                'export_policy' => (string) ($definition['export_policy'] ?? 'include'),
                'cleanup_policy' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function sr_community_redact_post_field_values(PDO $pdo, int $postId): void
{
    if ($postId < 1 || !sr_community_post_field_values_table_exists($pdo)) {
        return;
    }

    $pdo->prepare(
        "UPDATE sr_community_post_field_values
         SET value_text = '',
             value_json = NULL,
             updated_at = :updated_at
         WHERE post_id = :post_id"
    )->execute([
        'post_id' => $postId,
        'updated_at' => sr_now(),
    ]);
}

function sr_community_extra_field_values_from_json(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function sr_community_extra_fields_form_html(array $definitions, array $values = []): string
{
    if ($definitions === []) {
        return '';
    }

    $html = '<fieldset class="community-extra-fields">';
    $html .= '<legend>' . sr_e('추가 입력') . '</legend>';
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        $label = (string) ($definition['label'] ?? $key);
        $type = (string) ($definition['type'] ?? 'text');
        $required = !empty($definition['required']);
        $id = 'modules_community_extra_' . $key;
        $name = 'community_extra_fields[' . $key . ']';
        $rawValue = $values[$key] ?? '';
        $value = is_array($rawValue) ? '' : (string) $rawValue;
        $html .= '<p><label for="' . sr_e($id) . '"><span>' . sr_e($label) . ($required ? ' <span class="sr-required-label">' . sr_e(sr_t('community::ui.required.1f227c67')) . '</span>' : '') . '</span>';
        if ($type === 'textarea') {
            $html .= '<textarea id="' . sr_e($id) . '" name="' . sr_e($name) . '" rows="4" cols="80" maxlength="5000"' . ($required ? ' required' : '') . '>' . sr_e($value) . '</textarea>';
        } elseif ($type === 'select') {
            $html .= '<select id="' . sr_e($id) . '" name="' . sr_e($name) . '"' . ($required ? ' required' : '') . '>';
            $html .= '<option value="">' . sr_e('선택') . '</option>';
            foreach ((array) ($definition['options'] ?? []) as $option) {
                $option = (string) $option;
                $html .= '<option value="' . sr_e($option) . '"' . ($value === $option ? ' selected' : '') . '>' . sr_e($option) . '</option>';
            }
            $html .= '</select>';
        } elseif ($type === 'checkbox') {
            $html .= '<input id="' . sr_e($id) . '" type="checkbox" name="' . sr_e($name) . '" value="1"' . ($value === '1' ? ' checked' : '') . ($required ? ' required' : '') . '>';
        } else {
            $html .= '<input id="' . sr_e($id) . '" type="text" name="' . sr_e($name) . '" maxlength="1000" value="' . sr_e($value) . '"' . ($required ? ' required' : '') . '>';
        }
        $html .= '</label></p>';
    }
    $html .= '</fieldset>';

    return $html;
}

function sr_community_extra_fields_display_html(array $values): string
{
    if ($values === []) {
        return '';
    }

    $html = '<dl class="community-extra-field-values">';
    foreach ($values as $item) {
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string) ($item['label'] ?? ''));
        $value = (string) ($item['value'] ?? '');
        $showOnView = array_key_exists('show_on_view', $item) ? !empty($item['show_on_view']) : true;
        if ($label === '' || $value === '' || !$showOnView || (string) ($item['visibility'] ?? 'public') === 'admin') {
            continue;
        }
        $displayValue = (string) ($item['type'] ?? '') === 'checkbox' ? ($value === '1' ? '예' : '아니오') : $value;
        $html .= '<dt>' . sr_e($label) . '</dt><dd>' . nl2br(sr_e($displayValue)) . '</dd>';
    }
    $html .= '</dl>';

    return $html;
}

function sr_community_extra_fields_admin_summary_html(array $values): string
{
    if ($values === []) {
        return '';
    }

    $html = '<dl class="admin-community-extra-field-values">';
    foreach ($values as $item) {
        if (!is_array($item) || empty($item['show_in_admin'])) {
            continue;
        }

        $label = trim((string) ($item['label'] ?? ''));
        $value = (string) ($item['value'] ?? '');
        if ($label === '' || $value === '') {
            continue;
        }

        $displayValue = (string) ($item['type'] ?? '') === 'checkbox' ? ($value === '1' ? '예' : '아니오') : $value;
        $html .= '<dt>' . sr_e($label) . '</dt><dd>' . nl2br(sr_e($displayValue)) . '</dd>';
    }
    $html .= '</dl>';

    return $html === '<dl class="admin-community-extra-field-values"></dl>' ? '' : $html;
}
