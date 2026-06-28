<?php

declare(strict_types=1);

function sr_clean_single_line(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $value)) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_clean_text(string $value, int $maxLength): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    if (function_exists('mb_substr')) {
        return trim(mb_substr($value, 0, $maxLength));
    }

    return trim(substr($value, 0, $maxLength));
}

function sr_datetime_local_value(mixed $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
}

function sr_clean_admin_datetime(string $value, bool $includeSeconds = true): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $value) !== 1) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (
        !$date instanceof DateTimeImmutable
        || (is_array($errors) && ((int) ($errors['warning_count'] ?? 0) > 0 || (int) ($errors['error_count'] ?? 0) > 0))
        || $date->format('Y-m-d\TH:i') !== $value
    ) {
        return null;
    }

    return $date->format($includeSeconds ? 'Y-m-d H:i:s' : 'Y-m-d H:i:00');
}

function sr_relative_time_label(string $dateTime): string
{
    $timestamp = strtotime($dateTime);
    if ($timestamp === false) {
        return $dateTime;
    }

    $seconds = time() - $timestamp;
    $isFuture = $seconds < 0;
    $diff = abs($seconds);
    $suffix = $isFuture ? ' 후' : ' 전';

    if ($diff < 60) {
        return $isFuture ? '잠시 후' : '방금 전';
    }
    if ($diff < 3600) {
        return (string) floor($diff / 60) . '분' . $suffix;
    }
    if ($diff < 86400) {
        return (string) floor($diff / 3600) . '시간' . $suffix;
    }
    if ($diff < 2592000) {
        return (string) floor($diff / 86400) . '일' . $suffix;
    }
    if ($diff < 31536000) {
        return (string) floor($diff / 2592000) . '개월' . $suffix;
    }

    return (string) floor($diff / 31536000) . '년' . $suffix;
}

function sr_format_bytes(int $bytes): string
{
    $bytes = max(0, $bytes);

    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format($bytes) . ' bytes';
}

function sr_truthy(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
}

function sr_json_array(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function sr_write_file_atomically(string $path, string $contents, int $directoryMode = 0775, int $fileMode = 0664): bool
{
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, $directoryMode, true) && !is_dir($directory)) {
        return false;
    }

    $temporaryPath = $path . '.tmp.' . bin2hex(random_bytes(6));
    $handle = @fopen($temporaryPath, 'wb');
    if ($handle === false) {
        return false;
    }

    $written = false;
    if (flock($handle, LOCK_EX)) {
        $length = strlen($contents);
        $offset = 0;
        $written = true;
        while ($offset < $length) {
            $chunkLength = fwrite($handle, substr($contents, $offset));
            if ($chunkLength === false || $chunkLength === 0) {
                $written = false;
                break;
            }
            $offset += $chunkLength;
        }
        $written = $written && fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);

    if (!$written || !@rename($temporaryPath, $path)) {
        @unlink($temporaryPath);
        return false;
    }

    @chmod($path, $fileMode);
    return true;
}

function sr_image_format_for_mime(string $mimeType, bool $allowSvg = false, bool $allowGif = false): string
{
    return match (strtolower(trim($mimeType))) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => $allowGif ? 'gif' : '',
        'image/svg+xml' => $allowSvg ? 'svg' : '',
        default => '',
    };
}

function sr_image_mime_is_allowed(string $mimeType, bool $allowSvg = false, bool $allowGif = false): bool
{
    return sr_image_format_for_mime($mimeType, $allowSvg, $allowGif) !== '';
}
