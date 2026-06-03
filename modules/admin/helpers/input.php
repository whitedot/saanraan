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
