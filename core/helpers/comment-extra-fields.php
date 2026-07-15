<?php

declare(strict_types=1);

function sr_comment_extra_field_scalar_string(mixed $value): string
{
    return is_scalar($value) ? (string) $value : '';
}

function sr_comment_extra_field_definitions(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) {
        return [];
    }

    $definitions = [];
    $seenKeys = [];
    foreach (array_slice($value, 0, 20) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = strtolower(trim(sr_comment_extra_field_scalar_string($item['key'] ?? '')));
        $label = trim(sr_comment_extra_field_scalar_string($item['label'] ?? ''));
        $type = sr_comment_extra_field_scalar_string($item['type'] ?? 'text');
        if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) !== 1 || isset($seenKeys[$key]) || $label === '') {
            continue;
        }
        if (!in_array($type, ['text', 'textarea', 'select', 'checkbox'], true)) {
            $type = 'text';
        }
        $options = [];
        if ($type === 'select') {
            foreach ((array) ($item['options'] ?? []) as $option) {
                $option = trim(sr_comment_extra_field_scalar_string($option));
                if ($option !== '' && !in_array($option, $options, true)) {
                    $options[] = $option;
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
            'privacy_purpose' => trim(sr_comment_extra_field_scalar_string($item['privacy_purpose'] ?? '')),
            'show_privacy_purpose' => !array_key_exists('show_privacy_purpose', $item) || !empty($item['show_privacy_purpose']),
            'export_policy' => sr_comment_extra_field_scalar_string($item['export_policy'] ?? 'include') === 'exclude' ? 'exclude' : 'include',
            'cleanup_policy' => sr_comment_extra_field_scalar_string($item['cleanup_policy'] ?? 'anonymize') === 'retain' ? 'retain' : 'anonymize',
        ];
        $seenKeys[$key] = true;
    }

    return $definitions;
}

function sr_comment_extra_field_definition_errors(mixed $value, string $label = '댓글 추가 입력 항목'): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [$label . ' 데이터 형식이 올바르지 않습니다.'];
        }
        $value = $decoded;
    }
    if (!is_array($value)) {
        return [$label . ' 데이터는 배열이어야 합니다.'];
    }

    $errors = [];
    if (count($value) > 20) {
        $errors[] = $label . '은 20개 이하로 설정해 주세요.';
    }
    $seenKeys = [];
    foreach ($value as $index => $item) {
        $rowLabel = $label . ' #' . (string) ((int) $index + 1);
        if (!is_array($item)) {
            $errors[] = $rowLabel . ' 형식이 올바르지 않습니다.';
            continue;
        }
        $key = strtolower(trim(sr_comment_extra_field_scalar_string($item['key'] ?? '')));
        $fieldLabel = trim(sr_comment_extra_field_scalar_string($item['label'] ?? ''));
        $type = sr_comment_extra_field_scalar_string($item['type'] ?? 'text');
        if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) !== 1) {
            $errors[] = $rowLabel . '의 내부 식별자가 올바르지 않습니다.';
        } elseif (isset($seenKeys[$key])) {
            $errors[] = $rowLabel . '의 내부 식별자가 중복됩니다.';
        }
        if ($fieldLabel === '') {
            $errors[] = $rowLabel . '의 라벨을 입력해 주세요.';
        } elseif ((function_exists('mb_strlen') ? mb_strlen($fieldLabel) : strlen($fieldLabel)) > 120) {
            $errors[] = $rowLabel . '의 라벨은 120자 이하로 입력해 주세요.';
        }
        if (!in_array($type, ['text', 'textarea', 'select', 'checkbox'], true)) {
            $errors[] = $rowLabel . '의 유형 값이 올바르지 않습니다.';
        }
        if ($type === 'select') {
            $options = is_array($item['options'] ?? null) ? array_values(array_filter(array_map(static fn (mixed $option): string => trim(sr_comment_extra_field_scalar_string($option)), $item['options']), 'strlen')) : [];
            if ($options === []) {
                $errors[] = $rowLabel . '의 선택지를 하나 이상 입력해 주세요.';
            }
        }
        $privacyPurpose = trim(sr_comment_extra_field_scalar_string($item['privacy_purpose'] ?? ''));
        if ((function_exists('mb_strlen') ? mb_strlen($privacyPurpose) : strlen($privacyPurpose)) > 255) {
            $errors[] = $rowLabel . '의 수집·이용 목적은 255자 이하로 입력해 주세요.';
        }
        if (array_key_exists('show_privacy_purpose', $item) && !in_array($item['show_privacy_purpose'], [true, false, 0, 1, '0', '1'], true)) {
            $errors[] = $rowLabel . '의 수집·이용 목적 표시 설정 값이 올바르지 않습니다.';
        }
        if (!in_array(sr_comment_extra_field_scalar_string($item['export_policy'] ?? 'include'), ['include', 'exclude'], true)) {
            $errors[] = $rowLabel . '의 내 정보 사본 포함 설정 값이 올바르지 않습니다.';
        }
        if (!in_array(sr_comment_extra_field_scalar_string($item['cleanup_policy'] ?? 'anonymize'), ['anonymize', 'retain'], true)) {
            $errors[] = $rowLabel . '의 계정 정리 시 처리 설정 값이 올바르지 않습니다.';
        }
        if ($key !== '') {
            $seenKeys[$key] = true;
        }
    }

    return array_values(array_unique($errors));
}

function sr_comment_extra_field_definitions_json(mixed $value): string
{
    return json_encode(sr_comment_extra_field_definitions($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function sr_comment_extra_field_values_from_post(array $definitions, string $inputName = 'comment_extra_fields'): array
{
    $posted = $_POST[$inputName] ?? [];
    if (!is_array($posted)) {
        return ['values' => [], 'errors' => ['댓글 추가 입력값 형식이 올바르지 않습니다.']];
    }

    $values = [];
    $errors = [];
    foreach (sr_comment_extra_field_definitions($definitions) as $definition) {
        $key = (string) $definition['key'];
        $type = (string) $definition['type'];
        $rawValue = $posted[$key] ?? '';
        if (!is_scalar($rawValue)) {
            $errors[] = (string) $definition['label'] . ' 값 형식이 올바르지 않습니다.';
            continue;
        }
        $value = $type === 'checkbox' ? ((string) $rawValue === '1' ? '1' : '') : trim((string) $rawValue);
        $maxLength = $type === 'textarea' ? 5000 : 1000;
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length > $maxLength) {
            $errors[] = (string) $definition['label'] . ' 값은 ' . number_format($maxLength) . '자 이하로 입력해 주세요.';
        }
        if (!empty($definition['required']) && $value === '') {
            $errors[] = (string) $definition['label'] . ' 항목을 입력해 주세요.';
        }
        if ($type === 'select' && $value !== '' && !in_array($value, (array) $definition['options'], true)) {
            $errors[] = (string) $definition['label'] . ' 선택값이 올바르지 않습니다.';
        }
        $values[$key] = $value;
    }

    return ['values' => $values, 'errors' => array_values(array_unique($errors))];
}

function sr_comment_extra_field_snapshot_json(array $definitions, array $values): string
{
    $snapshot = [];
    foreach (sr_comment_extra_field_definitions($definitions) as $definition) {
        $key = (string) $definition['key'];
        $snapshot[] = [
            'key' => $key,
            'label' => (string) $definition['label'],
            'type' => (string) $definition['type'],
            'value' => is_scalar($values[$key] ?? '') ? (string) ($values[$key] ?? '') : '',
            'privacy_purpose' => (string) ($definition['privacy_purpose'] ?? ''),
            'show_privacy_purpose' => !empty($definition['show_privacy_purpose']),
            'export_policy' => (string) ($definition['export_policy'] ?? 'include'),
            'cleanup_policy' => (string) ($definition['cleanup_policy'] ?? 'anonymize'),
        ];
    }

    return json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function sr_comment_extra_field_snapshot_values(string $json): array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    $values = [];
    foreach ($decoded as $item) {
        if (is_array($item) && (string) ($item['key'] ?? '') !== '') {
            $values[(string) $item['key']] = is_scalar($item['value'] ?? '') ? (string) ($item['value'] ?? '') : '';
        }
    }
    return $values;
}

function sr_comment_extra_field_cleanup_account_snapshots(PDO $pdo, string $tableName, int $accountId): int
{
    $allowedTables = ['sr_community_comments', 'sr_content_comments', 'sr_quiz_comments', 'sr_survey_comments'];
    if ($accountId < 1 || !in_array($tableName, $allowedTables, true)) {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT id, extra_values_json FROM " . $tableName . " WHERE author_account_id = :account_id AND COALESCE(extra_values_json, '') <> ''");
    $stmt->execute(['account_id' => $accountId]);
    $update = $pdo->prepare('UPDATE ' . $tableName . ' SET extra_values_json = :extra_values_json, updated_at = :updated_at WHERE id = :id');
    $count = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $before = (string) ($row['extra_values_json'] ?? '');
        $after = sr_comment_extra_field_cleanup_json($before);
        if ($after === $before) {
            continue;
        }
        $update->execute(['extra_values_json' => $after, 'updated_at' => sr_now(), 'id' => (int) ($row['id'] ?? 0)]);
        $count++;
    }
    return $count;
}

function sr_comment_extra_fields_form_html(array $definitions, array $values = [], string $inputName = 'comment_extra_fields', string $idPrefix = 'comment_extra'): string
{
    $definitions = sr_comment_extra_field_definitions($definitions);
    if ($definitions === []) {
        return '';
    }

    $html = '<fieldset class="comment-extra-fields"><legend>추가 입력</legend>';
    foreach ($definitions as $definition) {
        $key = (string) $definition['key'];
        $label = (string) $definition['label'];
        $type = (string) $definition['type'];
        $required = !empty($definition['required']);
        $id = $idPrefix . '_' . $key;
        $name = $inputName . '[' . $key . ']';
        $value = is_scalar($values[$key] ?? '') ? (string) ($values[$key] ?? '') : '';
        $html .= '<p><label for="' . sr_e($id) . '"><span>' . sr_e($label) . ($required ? ' <span class="sr-required-label">(필수)</span>' : '') . '</span>';
        if ($type === 'textarea') {
            $html .= '<textarea id="' . sr_e($id) . '" name="' . sr_e($name) . '" rows="3" maxlength="5000" class="form-textarea"' . ($required ? ' required' : '') . '>' . sr_e($value) . '</textarea>';
        } elseif ($type === 'select') {
            $html .= '<select id="' . sr_e($id) . '" name="' . sr_e($name) . '" class="form-select"' . ($required ? ' required' : '') . '><option value="">선택</option>';
            foreach ((array) $definition['options'] as $option) {
                $option = (string) $option;
                $html .= '<option value="' . sr_e($option) . '"' . ($value === $option ? ' selected' : '') . '>' . sr_e($option) . '</option>';
            }
            $html .= '</select>';
        } elseif ($type === 'checkbox') {
            $html .= '<input id="' . sr_e($id) . '" type="checkbox" name="' . sr_e($name) . '" value="1" class="form-checkbox"' . ($value === '1' ? ' checked' : '') . ($required ? ' required' : '') . '>';
        } else {
            $html .= '<input id="' . sr_e($id) . '" type="text" name="' . sr_e($name) . '" maxlength="1000" value="' . sr_e($value) . '" class="form-input"' . ($required ? ' required' : '') . '>';
        }
        $purpose = trim((string) ($definition['privacy_purpose'] ?? ''));
        if ($purpose !== '' && !empty($definition['show_privacy_purpose'])) {
            $html .= '<small class="form-help">수집·이용 목적: ' . sr_e($purpose) . '</small>';
        }
        $html .= '</label></p>';
    }
    $html .= '</fieldset>';

    return $html;
}

function sr_comment_extra_fields_display_html(string $json): string
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return '';
    }
    $html = '<dl class="comment-extra-field-values">';
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string) ($item['label'] ?? ''));
        $value = is_scalar($item['value'] ?? '') ? (string) ($item['value'] ?? '') : '';
        if ($label === '' || $value === '') {
            continue;
        }
        $displayValue = (string) ($item['type'] ?? '') === 'checkbox' ? ($value === '1' ? '예' : '아니오') : $value;
        $html .= '<dt>' . sr_e($label) . '</dt><dd>' . nl2br(sr_e($displayValue)) . '</dd>';
    }
    $html .= '</dl>';

    return $html === '<dl class="comment-extra-field-values"></dl>' ? '' : $html;
}

function sr_comment_extra_field_export_json(string $json): string
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return '[]';
    }
    $decoded = array_values(array_filter($decoded, static fn (mixed $item): bool => is_array($item) && (string) ($item['export_policy'] ?? 'include') === 'include'));
    return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function sr_comment_extra_field_cleanup_json(string $json): string
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return '[]';
    }
    foreach ($decoded as &$item) {
        if (is_array($item) && (string) ($item['cleanup_policy'] ?? 'anonymize') !== 'retain') {
            $item['value'] = '';
        }
    }
    unset($item);
    return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}
