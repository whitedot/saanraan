<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';
require_once dirname(__DIR__, 3) . '/core/helpers/upload.php';

function sr_member_empty_profile(): array
{
    return [
        'birth_date' => '',
        'is_adult' => '',
        'profile_image_path' => '',
    ];
}

function sr_member_profile(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare(
        'SELECT birth_date, is_adult, profile_image_path
         FROM sr_member_profiles
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $profile = $stmt->fetch();

    if (!is_array($profile)) {
        return sr_member_empty_profile();
    }

    return [
        'birth_date' => is_string($profile['birth_date']) ? $profile['birth_date'] : '',
        'is_adult' => $profile['is_adult'] === null ? '' : ((int) $profile['is_adult'] === 1 ? '1' : '0'),
        'profile_image_path' => (string) $profile['profile_image_path'],
    ];
}

function sr_member_save_profile(PDO $pdo, int $accountId, array $profile): void
{
    $now = sr_now();
    $birthDate = trim((string) ($profile['birth_date'] ?? ''));
    if ($birthDate === '') {
        $birthDate = null;
    }
    $isAdult = trim((string) ($profile['is_adult'] ?? ''));
    $isAdult = $isAdult === '' ? null : ($isAdult === '1' ? 1 : 0);

    $sql = 'INSERT INTO sr_member_profiles
            (account_id, birth_date, is_adult, profile_image_path, created_at, updated_at)
         VALUES
            (:account_id, :birth_date, :is_adult, :profile_image_path, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            birth_date = VALUES(birth_date),
            is_adult = VALUES(is_adult),
            profile_image_path = VALUES(profile_image_path),
            updated_at = VALUES(updated_at)';
    $params = [
        'account_id' => $accountId,
        'birth_date' => $birthDate,
        'is_adult' => $isAdult,
        'profile_image_path' => trim((string) ($profile['profile_image_path'] ?? '')),
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function sr_member_delete_profile(PDO $pdo, int $accountId): void
{
    $stmt = $pdo->prepare('DELETE FROM sr_member_profiles WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);
    sr_member_delete_profile_field_values($pdo, $accountId);
}

function sr_member_profile_values_from_post(array $policies, array $baseProfile): array
{
    $profile = array_merge(sr_member_empty_profile(), $baseProfile);
    if (!empty($policies['birth_date']['visible'])) {
        $profile['birth_date'] = sr_post_string('birth_date', 10);
    }
    if (!empty($policies['is_adult']['visible'])) {
        $isAdult = sr_post_string('is_adult', 1);
        $profile['is_adult'] = $isAdult;
    }

    return $profile;
}

function sr_member_profile_validation_errors(array $profile, array $policies, array $options = []): array
{
    $errors = [];
    $validateAvatar = (bool) ($options['validate_profile_image'] ?? true);
    foreach ($policies as $field => $policy) {
        if (empty($policy['visible']) || empty($policy['required'])) {
            continue;
        }

        if ($field === 'profile_image_path') {
            if ($validateAvatar && !sr_member_profile_image_reference_is_valid((string) ($profile['profile_image_path'] ?? ''))) {
                $errors[] = sr_t('member::profile.error.profile_image_required');
            }
            continue;
        }

        if (trim((string) ($profile[$field] ?? '')) === '') {
            $errors[] = sr_member_profile_required_message((string) $field);
        }
    }

    if ($validateAvatar && !empty($policies['profile_image_path']['visible'])) {
        $avatarPath = (string) ($profile['profile_image_path'] ?? '');
        if ($avatarPath !== '' && !sr_member_profile_image_reference_is_valid($avatarPath)) {
            $errors[] = sr_t('member::profile.error.profile_image_reupload');
        }
    }
    if (!empty($policies['birth_date']['visible'])) {
        $birthDate = (string) ($profile['birth_date'] ?? '');
        if ($birthDate !== '' && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $birthDate) !== 1) {
            $errors[] = sr_t('member::profile.error.birth_date_format');
        } elseif ($birthDate !== '') {
            $birthParts = explode('-', $birthDate);
            if (!checkdate((int) $birthParts[1], (int) $birthParts[2], (int) $birthParts[0])) {
                $errors[] = sr_t('member::profile.error.birth_date_invalid');
            }
        }
    }
    if (!empty($policies['is_adult']['visible'])) {
        $isAdult = (string) ($profile['is_adult'] ?? '');
        if ($isAdult !== '' && !in_array($isAdult, ['0', '1'], true)) {
            $errors[] = sr_t('member::profile.error.is_adult_invalid');
        }
    }

    return array_values(array_unique($errors));
}

function sr_member_profile_required_message(string $field): string
{
    return match ($field) {
        'birth_date' => sr_t('member::profile.error.birth_date_required'),
        'is_adult' => sr_t('member::profile.error.is_adult_required'),
        'profile_image_path' => sr_t('member::profile.error.profile_image_required'),
        default => sr_t('member::profile.error.required'),
    };
}

function sr_member_profile_field_values_table_exists(PDO $pdo): bool
{
    static $existsByConnection = [];

    $key = (string) spl_object_id($pdo);
    if (array_key_exists($key, $existsByConnection)) {
        return $existsByConnection[$key];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_member_profile_field_values LIMIT 1');
        $existsByConnection[$key] = true;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
}

function sr_member_profile_extra_field_type(string $type): string
{
    return in_array($type, ['text', 'textarea', 'select', 'checkbox'], true) ? $type : 'text';
}

function sr_member_profile_extra_field_scalar_string(mixed $value): string
{
    return is_scalar($value) ? (string) $value : '';
}

function sr_member_normalize_profile_extra_field_definitions(mixed $raw): array
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

        $key = strtolower(trim(sr_member_profile_extra_field_scalar_string($item['key'] ?? '')));
        if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) !== 1 || isset($seenKeys[$key])) {
            continue;
        }

        $label = trim(preg_replace('/\s+/', ' ', sr_member_profile_extra_field_scalar_string($item['label'] ?? '')) ?? '');
        $label = function_exists('mb_substr') ? mb_substr($label, 0, 120) : substr($label, 0, 120);
        if ($label === '') {
            continue;
        }

        $type = sr_member_profile_extra_field_type(sr_member_profile_extra_field_scalar_string($item['type'] ?? 'text'));
        $options = [];
        if ($type === 'select') {
            $rawOptions = is_array($item['options'] ?? null) ? $item['options'] : [];
            foreach ($rawOptions as $option) {
                $option = trim(preg_replace('/\s+/', ' ', sr_member_profile_extra_field_scalar_string($option)) ?? '');
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
            'visibility' => in_array(sr_member_profile_extra_field_scalar_string($item['visibility'] ?? 'public'), ['public', 'admin'], true) ? sr_member_profile_extra_field_scalar_string($item['visibility'] ?? 'public') : 'public',
            'show_on_profile' => array_key_exists('show_on_profile', $item) ? !empty($item['show_on_profile']) : true,
            'show_in_admin' => !empty($item['show_in_admin']),
            'privacy_purpose' => trim(sr_member_profile_extra_field_scalar_string($item['privacy_purpose'] ?? '')),
            'export_policy' => in_array(sr_member_profile_extra_field_scalar_string($item['export_policy'] ?? 'include'), ['include', 'exclude'], true) ? sr_member_profile_extra_field_scalar_string($item['export_policy'] ?? 'include') : 'include',
            'cleanup_policy' => in_array(sr_member_profile_extra_field_scalar_string($item['cleanup_policy'] ?? 'anonymize'), ['anonymize', 'retain'], true) ? sr_member_profile_extra_field_scalar_string($item['cleanup_policy'] ?? 'anonymize') : 'anonymize',
        ];
        $seenKeys[$key] = true;
    }

    return $definitions;
}

function sr_member_profile_extra_field_definition_validation_errors(mixed $raw): array
{
    $errors = [];
    if (!is_array($raw)) {
        return ['프로필 추가 항목 형식이 올바르지 않습니다.'];
    }
    if (count($raw) > 20) {
        $errors[] = '프로필 추가 항목은 20개 이하로 입력해 주세요.';
    }

    $seenKeys = [];
    foreach ($raw as $index => $item) {
        $rowLabel = '프로필 추가 항목 #' . (string) ((int) $index + 1);
        if (!is_array($item)) {
            $errors[] = $rowLabel . ' 형식이 올바르지 않습니다.';
            continue;
        }

        $keyRaw = $item['key'] ?? '';
        $key = strtolower(trim(sr_member_profile_extra_field_scalar_string($keyRaw)));
        if (!is_scalar($keyRaw)) {
            $errors[] = $rowLabel . '의 Key 형식이 올바르지 않습니다.';
        }
        if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) !== 1) {
            $errors[] = $rowLabel . '의 Key는 영문 소문자로 시작하고 소문자, 숫자, _만 사용할 수 있습니다.';
        } elseif (isset($seenKeys[$key])) {
            $errors[] = $rowLabel . '의 Key가 중복되었습니다.';
        }
        if ($key !== '') {
            $seenKeys[$key] = true;
        }

        $labelRaw = $item['label'] ?? '';
        $label = trim(preg_replace('/\s+/', ' ', sr_member_profile_extra_field_scalar_string($labelRaw)) ?? '');
        $labelLength = function_exists('mb_strlen') ? mb_strlen($label) : strlen($label);
        if (!is_scalar($labelRaw)) {
            $errors[] = $rowLabel . '의 라벨 형식이 올바르지 않습니다.';
        } elseif ($label === '') {
            $errors[] = $rowLabel . '의 라벨을 입력해 주세요.';
        } elseif ($labelLength > 120) {
            $errors[] = $rowLabel . '의 라벨은 120자 이하로 입력해 주세요.';
        }

        $typeRaw = $item['type'] ?? 'text';
        $type = sr_member_profile_extra_field_scalar_string($typeRaw);
        if (!is_scalar($typeRaw)) {
            $errors[] = $rowLabel . '의 유형 형식이 올바르지 않습니다.';
        }
        if (!in_array($type, ['text', 'textarea', 'select', 'checkbox'], true)) {
            $errors[] = $rowLabel . '의 유형 값이 올바르지 않습니다.';
        }

        $visibilityRaw = $item['visibility'] ?? 'public';
        $visibility = sr_member_profile_extra_field_scalar_string($visibilityRaw);
        if (!is_scalar($visibilityRaw)) {
            $errors[] = $rowLabel . '의 공개 범위 형식이 올바르지 않습니다.';
        }
        if (!in_array($visibility, ['public', 'admin'], true)) {
            $errors[] = $rowLabel . '의 공개 범위 값이 올바르지 않습니다.';
        }

        $privacyPurposeRaw = $item['privacy_purpose'] ?? '';
        $privacyPurpose = trim(sr_member_profile_extra_field_scalar_string($privacyPurposeRaw));
        $privacyPurposeLength = function_exists('mb_strlen') ? mb_strlen($privacyPurpose) : strlen($privacyPurpose);
        if (!is_scalar($privacyPurposeRaw)) {
            $errors[] = $rowLabel . '의 수집·이용 목적 형식이 올바르지 않습니다.';
        } elseif ($privacyPurposeLength > 255) {
            $errors[] = $rowLabel . '의 수집·이용 목적은 255자 이하로 입력해 주세요.';
        }

        foreach (['export_policy' => ['include', 'exclude'], 'cleanup_policy' => ['anonymize', 'retain']] as $policyKey => $allowedValues) {
            $policyRaw = $item[$policyKey] ?? ($policyKey === 'export_policy' ? 'include' : 'anonymize');
            $policy = sr_member_profile_extra_field_scalar_string($policyRaw);
            if (!is_scalar($policyRaw) || !in_array($policy, $allowedValues, true)) {
                $policyLabel = $policyKey === 'export_policy' ? '내 정보 사본 포함 설정' : '계정 정리 시 처리 설정';
                $errors[] = $rowLabel . '의 ' . $policyLabel . ' 값이 올바르지 않습니다.';
            }
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

function sr_member_profile_extra_field_definitions_from_json(string $json): array
{
    $json = trim($json);
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    return sr_member_normalize_profile_extra_field_definitions($decoded);
}

function sr_member_default_profile_extra_field_definitions(): array
{
    return [
        [
            'key' => 'phone',
            'label' => '휴대폰 번호',
            'type' => 'text',
            'required' => false,
            'options' => [],
            'visibility' => 'public',
            'show_on_profile' => false,
            'show_in_admin' => true,
            'privacy_purpose' => '회원 본인확인 및 연락처 관리',
            'export_policy' => 'include',
            'cleanup_policy' => 'anonymize',
        ],
    ];
}

function sr_member_merge_default_profile_extra_field_definitions(array $definitions): array
{
    $seenKeys = [];
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        if ($key !== '') {
            $seenKeys[$key] = true;
        }
    }

    foreach (sr_member_default_profile_extra_field_definitions() as $defaultDefinition) {
        $defaultKey = (string) ($defaultDefinition['key'] ?? '');
        if ($defaultKey !== '' && !isset($seenKeys[$defaultKey])) {
            $definitions[] = $defaultDefinition;
        }
    }

    return sr_member_normalize_profile_extra_field_definitions($definitions);
}

function sr_member_profile_extra_field_definitions_input_errors(?string $json): array
{
    if (!is_string($json)) {
        return ['프로필 추가 항목은 20000자 이하로 입력해 주세요.'];
    }

    $json = trim($json);
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return ['프로필 추가 항목 형식을 확인해 주세요.'];
    }

    return sr_member_profile_extra_field_definition_validation_errors($decoded);
}

function sr_member_profile_extra_field_definitions_json_from_input(string $json): ?string
{
    $json = trim($json);
    if ($json === '') {
        return '[]';
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded) || sr_member_profile_extra_field_definition_validation_errors($decoded) !== []) {
        return null;
    }

    return json_encode(sr_member_normalize_profile_extra_field_definitions($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function sr_member_profile_extra_field_definitions(array $settings): array
{
    return sr_member_merge_default_profile_extra_field_definitions(
        sr_member_profile_extra_field_definitions_from_json((string) ($settings['profile_fields_json'] ?? '[]'))
    );
}

function sr_member_profile_extra_field_input_values(array $definitions): array
{
    $posted = $_POST['member_profile_fields'] ?? [];
    $posted = is_array($posted) ? $posted : [];
    $values = [];
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        $type = (string) ($definition['type'] ?? 'text');
        if ($type === 'checkbox') {
            if (isset($posted[$key]) && !is_scalar($posted[$key])) {
                $values[$key] = ['invalid_profile_field_value' => true];
                continue;
            }
            $values[$key] = isset($posted[$key]) && (string) $posted[$key] === '1' ? '1' : '0';
            continue;
        }

        $value = $posted[$key] ?? '';
        $values[$key] = is_scalar($value) ? trim((string) $value) : ['invalid_profile_field_value' => true];
    }

    return $values;
}

function sr_member_profile_extra_field_value_max_length(string $type): int
{
    return $type === 'textarea' ? 5000 : 1000;
}

function sr_member_validate_profile_extra_field_values(array $definitions, array $values): array
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
            if ($type === 'checkbox' && $value !== '1') {
                $errors[] = $label . '을(를) 확인해 주세요.';
            } elseif ($type !== 'checkbox' && trim($value) === '') {
                $errors[] = $label . '을(를) 입력해 주세요.';
            }
        }
        if ($type !== 'checkbox') {
            $maxLength = sr_member_profile_extra_field_value_max_length($type);
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

function sr_member_profile_extra_field_values(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !sr_member_profile_field_values_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT field_key, label_snapshot, field_type_snapshot, visibility_snapshot, show_on_profile_snapshot, show_in_admin_snapshot,
                privacy_purpose_snapshot, export_policy_snapshot, cleanup_policy_snapshot, value_text, value_json
         FROM sr_member_profile_field_values
         WHERE account_id = :account_id
         ORDER BY id ASC'
    );
    $stmt->execute(['account_id' => $accountId]);

    $values = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = (string) ($row['field_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $values[$key] = [
            'label' => (string) ($row['label_snapshot'] ?? $key),
            'type' => (string) ($row['field_type_snapshot'] ?? 'text'),
            'value' => (string) ($row['value_text'] ?? ''),
            'visibility' => (string) ($row['visibility_snapshot'] ?? 'public'),
            'show_on_profile' => (int) ($row['show_on_profile_snapshot'] ?? 1) === 1,
            'show_in_admin' => (int) ($row['show_in_admin_snapshot'] ?? 0) === 1,
            'privacy_purpose' => (string) ($row['privacy_purpose_snapshot'] ?? ''),
            'export_policy' => (string) ($row['export_policy_snapshot'] ?? 'include'),
            'cleanup_policy' => (string) ($row['cleanup_policy_snapshot'] ?? 'anonymize'),
        ];
    }

    return $values;
}

function sr_member_profile_extra_field_plain_values(PDO $pdo, int $accountId): array
{
    $stored = sr_member_profile_extra_field_values($pdo, $accountId);
    $values = [];
    foreach ($stored as $key => $item) {
        $values[(string) $key] = is_array($item) ? (string) ($item['value'] ?? '') : '';
    }

    return $values;
}

function sr_member_save_profile_extra_field_values(PDO $pdo, int $accountId, array $definitions, array $values): void
{
    if ($accountId < 1 || !sr_member_profile_field_values_table_exists($pdo)) {
        return;
    }

    $now = sr_now();
    $pdo->prepare('DELETE FROM sr_member_profile_field_values WHERE account_id = :account_id')->execute(['account_id' => $accountId]);
    if ($definitions === []) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_profile_field_values
            (account_id, field_key, label_snapshot, field_type_snapshot, visibility_snapshot, show_on_profile_snapshot, show_in_admin_snapshot,
             privacy_purpose_snapshot, export_policy_snapshot, cleanup_policy_snapshot, value_text, value_json, created_at, updated_at)
         VALUES
            (:account_id, :field_key, :label_snapshot, :field_type_snapshot, :visibility_snapshot, :show_on_profile_snapshot, :show_in_admin_snapshot,
             :privacy_purpose_snapshot, :export_policy_snapshot, :cleanup_policy_snapshot, :value_text, :value_json, :created_at, :updated_at)'
    );
    foreach ($definitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        if ($key === '') {
            continue;
        }

        $rawValue = $values[$key] ?? '';
        $value = is_array($rawValue) ? '' : (string) $rawValue;
        $stmt->execute([
            'account_id' => $accountId,
            'field_key' => $key,
            'label_snapshot' => (string) ($definition['label'] ?? $key),
            'field_type_snapshot' => (string) ($definition['type'] ?? 'text'),
            'visibility_snapshot' => (string) ($definition['visibility'] ?? 'public'),
            'show_on_profile_snapshot' => !empty($definition['show_on_profile']) ? 1 : 0,
            'show_in_admin_snapshot' => !empty($definition['show_in_admin']) ? 1 : 0,
            'privacy_purpose_snapshot' => (string) ($definition['privacy_purpose'] ?? ''),
            'export_policy_snapshot' => (string) ($definition['export_policy'] ?? 'include'),
            'cleanup_policy_snapshot' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
            'value_text' => $value,
            'value_json' => json_encode([
                'value' => $value,
                'visibility' => (string) ($definition['visibility'] ?? 'public'),
                'show_on_profile' => !empty($definition['show_on_profile']),
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

function sr_member_save_profile_extra_field_value(PDO $pdo, int $accountId, array $definition, string $value): bool
{
    if ($accountId < 1 || !sr_member_profile_field_values_table_exists($pdo)) {
        return false;
    }

    $key = (string) ($definition['key'] ?? '');
    if ($key === '') {
        return false;
    }

    $now = sr_now();
    $upsertSql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? 'INSERT INTO sr_member_profile_field_values
            (account_id, field_key, label_snapshot, field_type_snapshot, visibility_snapshot, show_on_profile_snapshot, show_in_admin_snapshot,
             privacy_purpose_snapshot, export_policy_snapshot, cleanup_policy_snapshot, value_text, value_json, created_at, updated_at)
         VALUES
            (:account_id, :field_key, :label_snapshot, :field_type_snapshot, :visibility_snapshot, :show_on_profile_snapshot, :show_in_admin_snapshot,
             :privacy_purpose_snapshot, :export_policy_snapshot, :cleanup_policy_snapshot, :value_text, :value_json, :created_at, :updated_at)
         ON CONFLICT(account_id, field_key) DO UPDATE SET
            label_snapshot = excluded.label_snapshot,
            field_type_snapshot = excluded.field_type_snapshot,
            visibility_snapshot = excluded.visibility_snapshot,
            show_on_profile_snapshot = excluded.show_on_profile_snapshot,
            show_in_admin_snapshot = excluded.show_in_admin_snapshot,
            privacy_purpose_snapshot = excluded.privacy_purpose_snapshot,
            export_policy_snapshot = excluded.export_policy_snapshot,
            cleanup_policy_snapshot = excluded.cleanup_policy_snapshot,
            value_text = excluded.value_text,
            value_json = excluded.value_json,
            updated_at = excluded.updated_at'
        : 'INSERT INTO sr_member_profile_field_values
            (account_id, field_key, label_snapshot, field_type_snapshot, visibility_snapshot, show_on_profile_snapshot, show_in_admin_snapshot,
             privacy_purpose_snapshot, export_policy_snapshot, cleanup_policy_snapshot, value_text, value_json, created_at, updated_at)
         VALUES
            (:account_id, :field_key, :label_snapshot, :field_type_snapshot, :visibility_snapshot, :show_on_profile_snapshot, :show_in_admin_snapshot,
             :privacy_purpose_snapshot, :export_policy_snapshot, :cleanup_policy_snapshot, :value_text, :value_json, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            label_snapshot = VALUES(label_snapshot),
            field_type_snapshot = VALUES(field_type_snapshot),
            visibility_snapshot = VALUES(visibility_snapshot),
            show_on_profile_snapshot = VALUES(show_on_profile_snapshot),
            show_in_admin_snapshot = VALUES(show_in_admin_snapshot),
            privacy_purpose_snapshot = VALUES(privacy_purpose_snapshot),
            export_policy_snapshot = VALUES(export_policy_snapshot),
            cleanup_policy_snapshot = VALUES(cleanup_policy_snapshot),
            value_text = VALUES(value_text),
            value_json = VALUES(value_json),
            updated_at = VALUES(updated_at)';
    $stmt = $pdo->prepare($upsertSql);
    $stmt->execute([
        'account_id' => $accountId,
        'field_key' => $key,
        'label_snapshot' => (string) ($definition['label'] ?? $key),
        'field_type_snapshot' => (string) ($definition['type'] ?? 'text'),
        'visibility_snapshot' => (string) ($definition['visibility'] ?? 'public'),
        'show_on_profile_snapshot' => !empty($definition['show_on_profile']) ? 1 : 0,
        'show_in_admin_snapshot' => !empty($definition['show_in_admin']) ? 1 : 0,
        'privacy_purpose_snapshot' => (string) ($definition['privacy_purpose'] ?? ''),
        'export_policy_snapshot' => (string) ($definition['export_policy'] ?? 'include'),
        'cleanup_policy_snapshot' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
        'value_text' => $value,
        'value_json' => json_encode([
            'value' => $value,
            'visibility' => (string) ($definition['visibility'] ?? 'public'),
            'show_on_profile' => !empty($definition['show_on_profile']),
            'show_in_admin' => !empty($definition['show_in_admin']),
            'privacy_purpose' => (string) ($definition['privacy_purpose'] ?? ''),
            'export_policy' => (string) ($definition['export_policy'] ?? 'include'),
            'cleanup_policy' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $stmt->rowCount() > 0;
}

function sr_member_delete_profile_field_values(PDO $pdo, int $accountId): void
{
    if ($accountId < 1 || !sr_member_profile_field_values_table_exists($pdo)) {
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM sr_member_profile_field_values WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);
}

function sr_member_delete_profile_extra_field_values_by_keys(PDO $pdo, array $fieldKeys): int
{
    if (!sr_member_profile_field_values_table_exists($pdo)) {
        return 0;
    }

    $keys = [];
    foreach ($fieldKeys as $fieldKey) {
        $key = strtolower(trim((string) $fieldKey));
        if ($key !== '' && preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) === 1) {
            $keys[$key] = true;
        }
    }
    $keys = array_keys($keys);
    if ($keys === []) {
        return 0;
    }

    $placeholders = [];
    $params = [];
    foreach ($keys as $index => $key) {
        $paramKey = 'field_key_' . $index;
        $placeholder = ':' . $paramKey;
        $placeholders[] = $placeholder;
        $params[$paramKey] = $key;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM sr_member_profile_field_values
         WHERE field_key IN (' . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($params);

    return $stmt->rowCount();
}

function sr_member_profile_extra_field_value_count_by_keys(PDO $pdo, array $fieldKeys): int
{
    if (!sr_member_profile_field_values_table_exists($pdo)) {
        return 0;
    }

    $keys = [];
    foreach ($fieldKeys as $fieldKey) {
        $key = strtolower(trim((string) $fieldKey));
        if ($key !== '' && preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) === 1) {
            $keys[$key] = true;
        }
    }
    $keys = array_keys($keys);
    if ($keys === []) {
        return 0;
    }

    $placeholders = [];
    $params = [];
    foreach ($keys as $index => $key) {
        $paramKey = 'field_key_' . $index;
        $placeholder = ':' . $paramKey;
        $placeholders[] = $placeholder;
        $params[$paramKey] = $key;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_member_profile_field_values
         WHERE field_key IN (' . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_member_profile_extra_field_form_html(array $definition, array $values = [], string $idPrefix = 'modules_member_profile_extra', array $options = []): string
{
    $key = (string) ($definition['key'] ?? '');
    if ($key === '') {
        return '';
    }

    $label = (string) ($definition['label'] ?? $key);
    $type = (string) ($definition['type'] ?? 'text');
    $required = !empty($definition['required']);
    $id = $idPrefix . '_' . $key;
    $name = 'member_profile_fields[' . $key . ']';
    $rawValue = $values[$key] ?? '';
    $value = is_array($rawValue) ? '' : (string) $rawValue;
    $lockedKeys = isset($options['locked_keys']) && is_array($options['locked_keys']) ? $options['locked_keys'] : [];
    $locked = in_array($key, array_map('strval', $lockedKeys), true);
    $lockedFieldAttribute = $locked ? ' data-member-identity-locked-field="' . sr_e($key) . '"' : '';
    $html = '<p><label for="' . sr_e($id) . '"><span>' . sr_e($label) . ($required ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : '') . '</span>';
    if ($type === 'textarea') {
        $html .= '<textarea id="' . sr_e($id) . '" name="' . sr_e($name) . '" rows="4" cols="80" maxlength="5000" class="form-textarea"' . ($required ? ' required' : '') . ($locked ? ' readonly' : '') . $lockedFieldAttribute . '>' . sr_e($value) . '</textarea>';
    } elseif ($type === 'select') {
        if ($locked) {
            $html .= '<input type="hidden" name="' . sr_e($name) . '" value="' . sr_e($value) . '">';
        }
        $html .= '<select id="' . sr_e($id) . '"' . ($locked ? '' : ' name="' . sr_e($name) . '"') . ' class="form-select"' . ($required ? ' required' : '') . ($locked ? ' disabled' : '') . $lockedFieldAttribute . '>';
        $html .= '<option value="">' . sr_e('선택') . '</option>';
        foreach ((array) ($definition['options'] ?? []) as $option) {
            $option = (string) $option;
            $html .= '<option value="' . sr_e($option) . '"' . ($value === $option ? ' selected' : '') . '>' . sr_e($option) . '</option>';
        }
        $html .= '</select>';
    } elseif ($type === 'checkbox') {
        if ($locked) {
            $html .= '<input type="hidden" name="' . sr_e($name) . '" value="' . ($value === '1' ? '1' : '0') . '">';
        }
        $html .= '<input id="' . sr_e($id) . '" type="checkbox"' . ($locked ? '' : ' name="' . sr_e($name) . '"') . ' value="1" class="form-checkbox"' . ($value === '1' ? ' checked' : '') . ($required ? ' required' : '') . ($locked ? ' disabled' : '') . $lockedFieldAttribute . '>';
    } else {
        $html .= '<input id="' . sr_e($id) . '" type="text" name="' . sr_e($name) . '" maxlength="1000" value="' . sr_e($value) . '" class="form-input"' . ($required ? ' required' : '') . ($locked ? ' readonly' : '') . $lockedFieldAttribute . '>';
    }
    $html .= '</label></p>';

    return $html;
}

function sr_member_profile_extra_fields_form_html(array $definitions, array $values = [], string $idPrefix = 'modules_member_profile_extra', bool $wrap = true, array $options = []): string
{
    if ($definitions === []) {
        return '';
    }

    $html = $wrap ? '<fieldset class="member-profile-extra-fields"><legend>' . sr_e('추가 프로필') . '</legend>' : '';
    foreach ($definitions as $definition) {
        $html .= sr_member_profile_extra_field_form_html($definition, $values, $idPrefix, $options);
    }
    $html .= $wrap ? '</fieldset>' : '';

    return $html;
}

function sr_member_profile_extra_field_values_export(array $values): array
{
    foreach ($values as $key => $item) {
        if (!is_array($item) || (string) ($item['export_policy'] ?? 'include') !== 'include') {
            unset($values[$key]);
        }
    }

    return $values;
}

function sr_member_profile_image_upload_max_bytes(): int
{
    return 2097152;
}

function sr_member_format_bytes(int $bytes): string
{
    return sr_format_bytes($bytes);
}

function sr_member_profile_image_upload_was_provided(mixed $file): bool
{
    return sr_upload_was_provided($file);
}

function sr_member_profile_image_format_for_mime(string $mimeType): string
{
    return sr_image_format_for_mime($mimeType);
}

function sr_member_profile_image_mime_is_allowed(string $mimeType): bool
{
    return sr_image_mime_is_allowed($mimeType);
}

function sr_member_upload_profile_image(array $file): ?array
{
    if (!sr_member_profile_image_upload_was_provided($file)) {
        return null;
    }

    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_member_profile_image_upload_max_bytes(),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $targetFormat = sr_member_profile_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException(sr_t('member::profile.error.profile_image_type_disallowed'));
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/member-profile-images/' . $datePath;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException(sr_t('member::profile.error.profile_image_temp_dir_failed'));
    }

    $storedName = sr_upload_random_filename($targetFormat);
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);

    try {
        if (!sr_upload_reencode_image((string) $validated['tmp_name'], $targetPath, $targetFormat, [
            'max_pixels' => 12000000,
            'quality' => 85,
        ])) {
            throw new RuntimeException(sr_t('member::profile.error.profile_image_reencode_failed'));
        }

        $imageInfo = @getimagesize($targetPath);
        $storedMimeType = sr_upload_detect_mime($targetPath);
        $checksum = hash_file('sha256', $targetPath);
        $sizeBytes = filesize($targetPath);
        if (!is_array($imageInfo) || !sr_member_profile_image_mime_is_allowed($storedMimeType) || !is_string($checksum) || !is_int($sizeBytes)) {
            throw new RuntimeException(sr_t('member::profile.error.profile_image_metadata_failed'));
        }

        $storageKey = 'member/profile-images/' . $datePath . '/' . $storedName;
        $stored = sr_storage_put_file($targetPath, $storageKey, [
            'content_type' => $storedMimeType,
        ]);

        return [
            'driver' => (string) $stored['driver'],
            'key' => $storageKey,
            'reference' => sr_storage_reference((string) $stored['driver'], $storageKey),
            'mime_type' => $storedMimeType,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => $checksum,
            'width' => (int) $imageInfo[0],
            'height' => (int) $imageInfo[1],
        ];
    } finally {
        if (is_file($targetPath)) {
            @unlink($targetPath);
        }
    }
}

function sr_member_profile_image_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Amember/(?:profile-images|avatars)/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $key) === 1;
}

function sr_member_profile_image_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_member_profile_image_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
}

function sr_member_profile_image_reference_is_valid(string $reference): bool
{
    return is_array(sr_member_profile_image_storage_reference($reference));
}

function sr_member_profile_image_url(string $reference): string
{
    $storage = sr_member_profile_image_storage_reference($reference);
    if (!is_array($storage)) {
        return '';
    }

    $key = (string) $storage['key'];
    $version = pathinfo($key, PATHINFO_FILENAME);
    $url = '/member/profile-image?file=' . rawurlencode(sr_storage_reference((string) $storage['driver'], $key));
    if (preg_match('/\A[a-f0-9]{32}\z/', $version) === 1) {
        $url .= '&v=' . rawurlencode($version);
    }

    return $url;
}

function sr_member_profile_image_src(string $reference): string
{
    $url = sr_member_profile_image_url($reference);
    if ($url === '') {
        return '';
    }

    return sr_is_http_url($url) ? $url : sr_url($url);
}

function sr_member_public_profile_images_enabled(PDO $pdo): bool
{
    if (!function_exists('sr_member_settings') || !function_exists('sr_member_profile_field_policies')) {
        return true;
    }

    $profilePolicies = sr_member_profile_field_policies(sr_member_settings($pdo));

    return !empty($profilePolicies['profile_image_path']['visible']);
}

function sr_member_public_profile_image_sources(PDO $pdo, array $accountIds): array
{
    $normalizedAccountIds = [];
    foreach ($accountIds as $accountId) {
        $accountId = (int) $accountId;
        if ($accountId > 0) {
            $normalizedAccountIds[$accountId] = $accountId;
        }
    }
    if ($normalizedAccountIds === []) {
        return [];
    }

    if (!sr_member_public_profile_images_enabled($pdo)) {
        return [];
    }

    $normalizedAccountIds = array_slice(array_values($normalizedAccountIds), 0, 500);
    $placeholders = [];
    $params = [];
    foreach ($normalizedAccountIds as $index => $accountId) {
        $placeholder = 'account_id_' . (string) $index;
        $placeholders[] = ':' . $placeholder;
        $params[$placeholder] = $accountId;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT p.account_id, p.profile_image_path
             FROM sr_member_profiles p
             INNER JOIN sr_member_accounts a ON a.id = p.account_id
             WHERE p.account_id IN (" . implode(', ', $placeholders) . ")
               AND a.status NOT IN ('withdrawn', 'anonymized')"
        );
        foreach ($params as $placeholder => $accountId) {
            $stmt->bindValue($placeholder, $accountId, PDO::PARAM_INT);
        }
        $stmt->execute();
    } catch (Throwable $exception) {
        return [];
    }

    $sources = [];
    foreach ($stmt->fetchAll() as $row) {
        $accountId = (int) ($row['account_id'] ?? 0);
        $source = sr_member_profile_image_src((string) ($row['profile_image_path'] ?? ''));
        if ($accountId > 0 && $source !== '') {
            $sources[$accountId] = $source;
        }
    }

    return $sources;
}

function sr_member_public_profile_image_html(string $source, string $className = '', string $sizeKey = 'medium', string $fallbackLabel = '', int $sizePixels = 0): string
{
    $source = trim($source);
    $sizeKey = function_exists('sr_member_profile_image_size_key')
        ? sr_member_profile_image_size_key($sizeKey)
        : (in_array($sizeKey, ['small', 'medium', 'large'], true) ? $sizeKey : 'medium');
    if ($sizePixels < 1) {
        $sizePixels = function_exists('sr_member_profile_image_size_pixels')
            ? sr_member_profile_image_size_pixels($sizeKey)
            : ($sizeKey === 'small' ? 24 : ($sizeKey === 'large' ? 40 : 32));
    }
    $sizePixels = max(16, min(128, $sizePixels));
    $sizeStyle = ' style="--member-profile-image-size: ' . (string) $sizePixels . 'px"';
    $classes = ['member-profile-image', 'member-profile-image-size-' . $sizeKey];
    foreach (preg_split('/\s+/', trim($className)) ?: [] as $class) {
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $class) === 1) {
            $classes[] = $class;
        }
    }

    if ($source === '') {
        $fallbackLabel = trim(preg_replace('/\s+/u', ' ', $fallbackLabel) ?? $fallbackLabel);
        if ($fallbackLabel === '') {
            return '';
        }
        if (function_exists('grapheme_substr')) {
            $initial = (string) grapheme_substr($fallbackLabel, 0, 1);
        } elseif (function_exists('mb_substr')) {
            $initial = mb_substr($fallbackLabel, 0, 1, 'UTF-8');
        } else {
            $initial = substr($fallbackLabel, 0, 1);
        }
        if ($initial === '') {
            return '';
        }

        $classes[] = 'member-profile-image-fallback';
        $classes[] = 'member-default-avatar';
        $classes[] = sr_member_default_avatar_color_class(hash('sha256', $fallbackLabel));

        return '<span class="' . sr_e(implode(' ', array_values(array_unique($classes)))) . '"' . $sizeStyle . ' aria-hidden="true">' . sr_e($initial) . '</span>';
    }

    return '<img class="' . sr_e(implode(' ', array_values(array_unique($classes)))) . '" src="' . sr_e($source) . '" alt="" width="' . (string) $sizePixels . '" height="' . (string) $sizePixels . '"' . $sizeStyle . ' decoding="async" aria-hidden="true">';
}

function sr_member_default_avatar_color_class(string $publicHash): string
{
    return 'member-avatar-color-' . (string) sr_member_default_avatar_color_index($publicHash);
}

function sr_member_default_avatar_color_palette(): array
{
    return [
        '#b91c1c',
        '#c2410c',
        '#a16207',
        '#4d7c0f',
        '#047857',
        '#0f766e',
        '#0369a1',
        '#1d4ed8',
        '#4f46e5',
        '#7e22ce',
        '#be185d',
        '#9f1239',
    ];
}

function sr_member_default_avatar_color_index(string $publicHash): int
{
    $hashPrefix = strtolower(substr(trim($publicHash), 0, 6));
    if (preg_match('/\A[a-f0-9]{6}\z/', $hashPrefix) !== 1) {
        return 8;
    }

    $target = [
        hexdec(substr($hashPrefix, 0, 2)),
        hexdec(substr($hashPrefix, 2, 2)),
        hexdec(substr($hashPrefix, 4, 2)),
    ];
    $palette = sr_member_default_avatar_color_palette();

    $closestIndex = 0;
    $closestDistance = PHP_INT_MAX;
    foreach ($palette as $index => $color) {
        $colorPrefix = substr($color, 1);
        $candidate = [
            hexdec(substr($colorPrefix, 0, 2)),
            hexdec(substr($colorPrefix, 2, 2)),
            hexdec(substr($colorPrefix, 4, 2)),
        ];
        $distance = ($target[0] - $candidate[0]) ** 2
            + ($target[1] - $candidate[1]) ** 2
            + ($target[2] - $candidate[2]) ** 2;
        if ($distance < $closestDistance) {
            $closestDistance = $distance;
            $closestIndex = (int) $index;
        }
    }

    return $closestIndex;
}

function sr_member_delete_profile_image_reference(string $reference): void
{
    $storage = sr_member_profile_image_storage_reference($reference);
    if (is_array($storage)) {
        sr_storage_delete((string) $storage['driver'], (string) $storage['key']);
    }
}
