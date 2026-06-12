<?php

declare(strict_types=1);

function sr_admin_post_positive_int(string $key, int $maxLength = 20): int
{
    $value = $_POST[$key] ?? '';
    if (is_array($value)) {
        return 0;
    }

    $value = trim((string) $value);
    if (preg_match('/\A\d{1,3}(?:,\d{3})+\z/', $value) === 1) {
        $value = str_replace(',', '', $value);
    }
    if ($value === '' || strlen($value) > $maxLength || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
        return 0;
    }

    return (int) $value;
}

function sr_admin_get_positive_int(string $key, int $maxLength = 20): int
{
    $value = $_GET[$key] ?? '';
    if (is_array($value)) {
        return 0;
    }

    $value = trim((string) $value);
    if (preg_match('/\A\d{1,3}(?:,\d{3})+\z/', $value) === 1) {
        $value = str_replace(',', '', $value);
    }
    if ($value === '' || strlen($value) > $maxLength || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
        return 0;
    }

    return (int) $value;
}

function sr_admin_positive_int_list_from_input(mixed $values, ?bool &$hasInvalid = null, int $maxLength = 20): array
{
    $hasInvalid = !is_array($values);
    if (!is_array($values)) {
        return [];
    }

    $ids = [];
    foreach ($values as $rawValue) {
        if (is_array($rawValue)) {
            $hasInvalid = true;
            continue;
        }

        $value = trim((string) $rawValue);
        if ($value === '' || strlen($value) > $maxLength || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            $hasInvalid = true;
            continue;
        }

        $id = (int) $value;
        $ids[$id] = $id;
    }

    return array_values($ids);
}

function sr_admin_post_int_in_range(string $key, int $min, int $max, int $maxLength = 10): ?int
{
    $value = $_POST[$key] ?? '';
    if (is_array($value)) {
        return null;
    }

    $value = trim((string) $value);
    if (preg_match('/\A\d{1,3}(?:,\d{3})+\z/', $value) === 1) {
        $value = str_replace(',', '', $value);
    }
    if ($value === '' || strlen($value) > $maxLength || preg_match('/\A\d+\z/', $value) !== 1) {
        return null;
    }

    $integerValue = (int) $value;
    if ($integerValue < $min || $integerValue > $max) {
        return null;
    }

    return $integerValue;
}

function sr_admin_get_allowed_array(string $key, array $allowedValues, int $maxLength = 80): array
{
    $rawValue = $_GET[$key] ?? [];
    $rawValues = is_array($rawValue) ? $rawValue : [$rawValue];
    $allowedLookup = array_fill_keys(array_map('strval', $allowedValues), true);
    $values = [];

    foreach ($rawValues as $rawItem) {
        if (is_array($rawItem)) {
            continue;
        }

        $value = trim((string) $rawItem);
        if ($value === '' || strlen($value) > $maxLength || !isset($allowedLookup[$value])) {
            continue;
        }

        $values[$value] = $value;
    }

    return array_values($values);
}

function sr_admin_get_allowed_single_array(string $key, array $allowedValues, int $maxLength = 80): array
{
    $values = sr_admin_get_allowed_array($key, $allowedValues, $maxLength);

    return $values === [] ? [] : [(string) $values[0]];
}

function sr_admin_single_value_query_keys(): array
{
    return [
        'status',
        'target_type',
        'request_type',
        'reason_key',
        'audience',
        'delivery_channel',
        'delivery_status',
        'evaluation_policy',
        'group_id',
        'source_module_key',
        'asset',
        'target',
        'visibility',
        'refundable_policy',
        'download_type',
        'refund_status',
        'from_module_key',
        'to_module_key',
    ];
}

function sr_admin_context_single_value_query_keys(string $path): array
{
    $singleValueKeysByPath = [
        '/admin/asset-exchange' => ['status', 'from_module_key', 'to_module_key'],
        '/admin/asset-exchange/logs' => ['status', 'asset'],
        '/admin/banners' => ['target'],
        '/admin/community/boards' => ['group_id'],
        '/admin/community/series' => ['visibility'],
        '/admin/content' => ['content_group_id'],
        '/admin/content/file-downloads' => ['download_type'],
        '/admin/content/files' => ['status'],
        '/admin/content/series' => ['visibility'],
        '/admin/coupons' => ['status', 'target_type'],
        '/admin/coupons/issues' => ['target_type'],
        '/admin/coupons/redemptions' => ['status', 'refundable_policy', 'target_type'],
        '/admin/deposits/refund-requests' => ['status'],
        '/admin/member-group-rules' => ['status', 'evaluation_policy', 'group_id', 'source_module_key'],
        '/admin/notification-deliveries' => ['delivery_channel'],
        '/admin/notifications' => ['audience', 'status'],
        '/admin/popup-layers' => ['target'],
        '/admin/privacy-requests' => ['request_type'],
        '/admin/rewards/withdrawal-requests' => ['status'],
    ];

    return $singleValueKeysByPath[$path] ?? [];
}

function sr_admin_first_scalar_query_value(array $values): ?string
{
    foreach ($values as $value) {
        if (is_array($value)) {
            continue;
        }

        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }
    }

    return null;
}

function sr_admin_normalize_query_params(array $params, string $path = ''): array
{
    $normalized = [];
    $singleValueKeys = array_fill_keys(sr_admin_single_value_query_keys(), true);
    $contextSingleValueKeys = array_fill_keys(sr_admin_context_single_value_query_keys($path), true);

    foreach ($params as $key => $value) {
        if (is_array($value)) {
            if (isset($contextSingleValueKeys[(string) $key])) {
                $scalarValue = sr_admin_first_scalar_query_value($value);
                if ($scalarValue !== null) {
                    $normalized[$key] = $scalarValue;
                }
                continue;
            }

            if (isset($singleValueKeys[(string) $key]) && array_is_list($value) && count($value) === 1 && !is_array($value[0])) {
                $normalized[$key] = (string) $value[0];
                continue;
            }

            $normalized[$key] = $value;
            continue;
        }

        $normalized[$key] = $value;
    }

    return $normalized;
}

function sr_admin_sql_in_condition(string $column, string $paramPrefix, $values): array
{
    $values = is_array($values) ? $values : [$values];
    $placeholders = [];
    $params = [];
    $index = 0;
    foreach ($values as $value) {
        if (is_array($value)) {
            continue;
        }
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }
        $paramKey = $paramPrefix . '_' . $index;
        $placeholders[] = ':' . $paramKey;
        $params[$paramKey] = $value;
        $index++;
    }

    if ($placeholders === []) {
        return ['1 = 1', []];
    }

    return [$column . ' IN (' . implode(', ', $placeholders) . ')', $params];
}
