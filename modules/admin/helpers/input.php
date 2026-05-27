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
