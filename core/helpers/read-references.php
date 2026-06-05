<?php

declare(strict_types=1);

function sr_read_reference_contract_files(): array
{
    return [
        'coupon-references.php' => 'coupon_definition',
        'banner-references.php' => 'banner',
        'popup-layer-references.php' => 'popup_layer',
        'member-group-references.php' => 'member_group',
        'site-setting-references.php' => 'site_setting',
    ];
}

function sr_read_reference_statuses(): array
{
    return ['ok', 'stale', 'disabled_target', 'missing_target', 'unknown'];
}

function sr_read_reference_contract_file_for_target_type(string $targetType): string
{
    foreach (sr_read_reference_contract_files() as $contractFile => $knownTargetType) {
        if ($knownTargetType === $targetType) {
            return (string) $contractFile;
        }
    }

    return '';
}

function sr_read_reference_collect(PDO $pdo, string $contractFile, array $target, array $context = []): array
{
    $rows = [];
    $errors = [];
    $targetType = (string) ($target['target_type'] ?? '');
    $expectedTargetType = (string) (sr_read_reference_contract_files()[$contractFile] ?? '');
    if ($expectedTargetType === '' || $targetType !== $expectedTargetType) {
        return [
            'rows' => [],
            'errors' => ['읽기 참조 계약 대상이 올바르지 않습니다.'],
        ];
    }

    foreach (sr_enabled_module_contract_files($pdo, $contractFile) as $moduleKey => $file) {
        $contract = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($contract)) {
            $errors[] = $moduleKey . ' 계약 파일을 읽을 수 없습니다.';
            continue;
        }

        $entries = sr_read_reference_contract_entries($contract);
        if ($entries === []) {
            $errors[] = $moduleKey . ' 계약 항목이 비어 있습니다.';
            continue;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                $errors[] = $moduleKey . ' 계약 항목 형식이 올바르지 않습니다.';
                continue;
            }

            $entryErrors = sr_read_reference_prepare_entry($moduleKey, $entry, $targetType);
            if ($entryErrors !== []) {
                foreach ($entryErrors as $entryError) {
                    $errors[] = $moduleKey . ': ' . $entryError;
                }
                continue;
            }

            $countFunction = trim((string) $entry['count_function']);
            $rowsFunction = trim((string) $entry['rows_function']);
            $healthFunction = trim((string) $entry['health_function']);
            $adminUrlFunction = trim((string) $entry['admin_url_function']);

            try {
                $rawCount = $countFunction($pdo, $target, $context);
                if (!is_int($rawCount)) {
                    $errors[] = $moduleKey . ': count_function 반환값이 정수가 아닙니다.';
                    continue;
                }
                if ($rawCount < 0) {
                    $errors[] = $moduleKey . ': count_function 반환값이 음수입니다.';
                    continue;
                }
                $count = $rawCount;
                if ($count < 1) {
                    continue;
                }

                $rawRows = $rowsFunction($pdo, $target, $context);
                if (!is_array($rawRows)) {
                    $errors[] = $moduleKey . ': rows_function 반환값이 배열이 아닙니다.';
                    continue;
                }
                if ($rawRows === []) {
                    $errors[] = $moduleKey . ': count_function은 참조가 있다고 반환했지만 rows_function 반환값이 비어 있습니다.';
                    continue;
                }

                foreach ($rawRows as $rawRow) {
                    if (!is_array($rawRow)) {
                        $errors[] = $moduleKey . ': 참조 row 형식이 올바르지 않습니다.';
                        continue;
                    }

                    $health = $healthFunction($pdo, $target, $rawRow, $context);
                    if (!is_array($health)) {
                        $errors[] = $moduleKey . ': health_function 반환값이 배열이 아닙니다.';
                        $health = ['status' => 'unknown', 'message' => '상태를 확인할 수 없습니다.'];
                    }

                    $rawAdminUrl = $adminUrlFunction($rawRow, $context);
                    if (!is_string($rawAdminUrl)) {
                        $errors[] = $moduleKey . ': admin_url_function 반환값이 문자열이 아닙니다.';
                        continue;
                    }
                    $adminUrl = $rawAdminUrl;
                    $normalized = sr_read_reference_normalize_row($moduleKey, $entry, $target, $rawRow, $health, $adminUrl);
                    if (is_array($normalized['row'] ?? null)) {
                        $rows[] = $normalized['row'];
                    }
                    foreach (($normalized['errors'] ?? []) as $rowError) {
                        $errors[] = $moduleKey . ': ' . (string) $rowError;
                    }
                }
            } catch (Throwable $exception) {
                if (function_exists('sr_log_exception')) {
                    sr_log_exception($exception, 'read_reference_contract_' . $moduleKey . '_' . str_replace(['.', '-'], '_', $contractFile));
                }
                $errors[] = $moduleKey . ': 계약 callable 실행 중 오류가 발생했습니다.';
            }
        }
    }

    return [
        'rows' => $rows,
        'errors' => $errors,
    ];
}

function sr_read_reference_contract_entries(array $contract): array
{
    if ($contract === []) {
        return [];
    }

    if (isset($contract['count_function']) || isset($contract['rows_function'])) {
        return [$contract];
    }

    return $contract;
}

function sr_read_reference_prepare_entry(string $moduleKey, array $entry, string $targetType): array
{
    $errors = [];
    $consumerModuleKey = (string) ($entry['consumer_module_key'] ?? '');
    if ($consumerModuleKey !== $moduleKey || !sr_is_safe_module_key($consumerModuleKey)) {
        $errors[] = 'consumer_module_key가 제공 모듈과 맞지 않습니다.';
    }

    foreach (['label', 'reference_type'] as $requiredKey) {
        if (!is_string($entry[$requiredKey] ?? null) || trim((string) $entry[$requiredKey]) === '') {
            $errors[] = $requiredKey . ' 필수값이 비어 있습니다.';
        }
    }

    $supportsTargetTypes = $entry['supports_target_types'] ?? null;
    if (!is_array($supportsTargetTypes) || $supportsTargetTypes === []) {
        $errors[] = 'supports_target_types가 비어 있습니다.';
    } else {
        $normalizedTargetTypes = [];
        foreach ($supportsTargetTypes as $supportedTargetType) {
            if (!is_string($supportedTargetType)) {
                $errors[] = 'supports_target_types 값이 올바르지 않습니다.';
                continue;
            }
            $supportedTargetType = trim($supportedTargetType);
            if ($supportedTargetType === '') {
                $errors[] = 'supports_target_types 값이 비어 있습니다.';
                continue;
            }
            if ($supportedTargetType !== $targetType) {
                $errors[] = 'supports_target_types가 대상 type과 맞지 않습니다.';
                continue;
            }
            $normalizedTargetTypes[] = $supportedTargetType;
        }
        if ($normalizedTargetTypes !== [] && !in_array($targetType, $normalizedTargetTypes, true)) {
            $errors[] = 'supports_target_types가 대상 type을 지원하지 않습니다.';
        }
    }

    $helpers = $entry['helpers'] ?? [];
    if (is_string($helpers) && $helpers !== '') {
        $helpers = [$helpers];
    }
    if (!is_array($helpers)) {
        $errors[] = 'helpers 형식이 올바르지 않습니다.';
    } else {
        foreach ($helpers as $helper) {
            if (!is_string($helper)) {
                $errors[] = 'helper 경로가 올바르지 않습니다.';
                continue;
            }
            $helperPath = sr_read_reference_helper_path($moduleKey, trim($helper));
            if ($helperPath === '') {
                $errors[] = 'helper 경로가 올바르지 않습니다.';
                continue;
            }
            require_once $helperPath;
        }
    }

    foreach (['count_function', 'rows_function', 'health_function', 'admin_url_function'] as $functionKey) {
        if (!is_string($entry[$functionKey] ?? null) || trim((string) $entry[$functionKey]) === '') {
            $errors[] = $functionKey . ' callable이 없습니다.';
            continue;
        }
        $functionName = trim((string) $entry[$functionKey]);
        if (!function_exists($functionName)) {
            $errors[] = $functionKey . ' callable이 없습니다.';
            continue;
        }
        if (!sr_read_reference_callable_signature_is_valid($functionKey, $functionName)) {
            $errors[] = $functionKey . ' callable 인자 수가 올바르지 않습니다.';
        }
    }

    return $errors;
}

function sr_read_reference_callable_signature_is_valid(string $functionKey, string $functionName): bool
{
    $expectedParameterCounts = [
        'count_function' => 3,
        'rows_function' => 3,
        'health_function' => 4,
        'admin_url_function' => 2,
    ];
    if (!isset($expectedParameterCounts[$functionKey]) || !function_exists($functionName)) {
        return false;
    }

    $reflection = new ReflectionFunction($functionName);
    $expected = $expectedParameterCounts[$functionKey];

    return $reflection->getNumberOfRequiredParameters() <= $expected
        && $reflection->getNumberOfParameters() >= $expected;
}

function sr_read_reference_helper_path(string $moduleKey, string $helper): string
{
    if ($helper === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helper) !== 1) {
        return '';
    }

    $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
    $path = $moduleDir . '/' . $helper;
    $realModuleDir = realpath($moduleDir);
    $realPath = realpath($path);
    if ($realModuleDir === false || $realPath === false || strpos($realPath, $realModuleDir . DIRECTORY_SEPARATOR) !== 0) {
        return '';
    }

    return $realPath;
}

function sr_read_reference_normalize_row(string $moduleKey, array $entry, array $target, array $rawRow, array $health, string $adminUrl): array
{
    $errors = [];
    $status = sr_read_reference_string_value($health['status'] ?? ($rawRow['status'] ?? 'unknown'));
    if ($status === null) {
        $status = 'unknown';
        $errors[] = 'status 값이 올바르지 않습니다.';
    }
    if (!in_array($status, sr_read_reference_statuses(), true)) {
        $status = 'unknown';
        $errors[] = 'status 값이 올바르지 않습니다.';
    }

    if ($adminUrl !== '' && !sr_read_reference_admin_url_is_safe($adminUrl)) {
        $adminUrl = '';
        $errors[] = 'admin_url이 내부 상대 URL이 아닙니다.';
    }

    $row = [
        'consumer_module_key' => sr_read_reference_string_value($rawRow['consumer_module_key'] ?? $entry['consumer_module_key'] ?? $moduleKey) ?? '',
        'reference_type' => sr_read_reference_string_value($rawRow['reference_type'] ?? $entry['reference_type'] ?? '') ?? '',
        'reference_id' => sr_read_reference_string_value($rawRow['reference_id'] ?? '') ?? '',
        'title' => sr_read_reference_string_value($rawRow['title'] ?? '') ?? '',
        'target_type' => sr_read_reference_string_value($rawRow['target_type'] ?? $target['target_type'] ?? '') ?? '',
        'target_id' => sr_read_reference_string_value($rawRow['target_id'] ?? $target['target_id'] ?? 0) ?? '',
        'status' => $status,
        'admin_url' => $adminUrl,
    ];

    foreach (['target_key', 'policy_status', 'updated_at', 'message'] as $optionalKey) {
        if (array_key_exists($optionalKey, $rawRow)) {
            $optionalValue = sr_read_reference_string_value($rawRow[$optionalKey]);
            if ($optionalValue === null) {
                $errors[] = $optionalKey . ' 값이 올바르지 않습니다.';
                continue;
            }
            $row[$optionalKey] = $optionalValue;
        }
    }
    if (array_key_exists('metadata', $rawRow)) {
        if (!is_array($rawRow['metadata'])) {
            $errors[] = 'metadata 값이 올바르지 않습니다.';
        } else {
            $row['metadata'] = $rawRow['metadata'];
        }
    }
    if (isset($health['message'])) {
        $message = sr_read_reference_string_value($health['message']);
        if ($message === null) {
            $errors[] = 'message 값이 올바르지 않습니다.';
        } else {
            $row['message'] = $message;
        }
    }
    if (isset($health['policy_status'])) {
        $policyStatus = sr_read_reference_string_value($health['policy_status']);
        if ($policyStatus === null) {
            $errors[] = 'policy_status 값이 올바르지 않습니다.';
        } else {
            $row['policy_status'] = $policyStatus;
        }
    }

    foreach (['consumer_module_key', 'reference_type', 'reference_id', 'title', 'target_type', 'target_id', 'admin_url'] as $requiredKey) {
        if ((string) ($row[$requiredKey] ?? '') === '') {
            $errors[] = $requiredKey . ' 필수값이 비어 있습니다.';
        }
    }
    if (!sr_is_safe_module_key((string) ($row['consumer_module_key'] ?? '')) || (string) ($row['consumer_module_key'] ?? '') !== $moduleKey) {
        $errors[] = 'consumer_module_key가 제공 모듈과 맞지 않습니다.';
    }
    $expectedTargetType = sr_read_reference_string_value($target['target_type'] ?? '') ?? '';
    if ((string) ($row['target_type'] ?? '') !== $expectedTargetType) {
        $errors[] = 'target_type이 조회 대상과 맞지 않습니다.';
    }
    $expectedTargetId = sr_read_reference_string_value($target['target_id'] ?? 0) ?? '';
    if ((string) ($row['target_id'] ?? '') !== $expectedTargetId) {
        $errors[] = 'target_id가 조회 대상과 맞지 않습니다.';
    }
    $expectedTargetKey = sr_read_reference_string_value($target['target_key'] ?? '') ?? '';
    $rowTargetKey = (string) ($row['target_key'] ?? '');
    if (($expectedTargetKey !== '' && $rowTargetKey !== $expectedTargetKey) || ($expectedTargetKey === '' && $rowTargetKey !== '')) {
        $errors[] = 'target_key가 조회 대상과 맞지 않습니다.';
    }

    return [
        'row' => $errors === [] ? $row : null,
        'errors' => $errors,
    ];
}

function sr_read_reference_string_value(mixed $value): ?string
{
    if (is_string($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value) || is_bool($value)) {
        return (string) $value;
    }

    return null;
}

function sr_read_reference_admin_url_is_safe(string $adminUrl): bool
{
    if (!sr_is_safe_relative_url($adminUrl)) {
        return false;
    }

    $path = parse_url($adminUrl, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return false;
    }

    foreach (explode('/', $path) as $segment) {
        $decodedSegment = rawurldecode($segment);
        if ($segment === '..' || $decodedSegment === '..' || str_contains($decodedSegment, '/') || str_contains($decodedSegment, '\\')) {
            return false;
        }
    }

    return true;
}

function sr_read_reference_count(PDO $pdo, string $targetType, int $targetId, string $targetKey = '', array $context = []): int
{
    $contractFile = sr_read_reference_contract_file_for_target_type($targetType);
    if ($contractFile === '') {
        return 0;
    }

    $result = sr_read_reference_collect($pdo, $contractFile, [
        'owner_module_key' => (string) ($context['owner_module_key'] ?? ''),
        'target_type' => $targetType,
        'target_id' => $targetId,
        'target_key' => $targetKey,
    ], $context);

    return count($result['rows']);
}
