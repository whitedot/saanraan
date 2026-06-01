<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    $columnExists = static function (PDO $pdo, string $tableName, string $columnName): bool {
        static $exists = [];
        $cacheKey = $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $exists)) {
            return $exists[$cacheKey];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name'
            );
            $stmt->execute([
                'table_name' => $tableName,
                'column_name' => $columnName,
            ]);
            $exists[$cacheKey] = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $exception) {
            $exists[$cacheKey] = false;
        }

        return $exists[$cacheKey];
    };

    if (!function_exists('sr_content_anonymize_access_entitlements')) {
        require_once SR_ROOT . '/modules/content/helpers.php';
    }
    if (!function_exists('sr_content_series_supported')) {
        require_once SR_ROOT . '/modules/content/helpers.php';
    }

    $fileDownloadLogAnonymizedCount = 0;
    if (function_exists('sr_content_file_download_logs_table_exists') && sr_content_file_download_logs_table_exists($pdo)) {
        $stmt = $pdo->prepare('UPDATE sr_content_file_download_logs SET account_id = NULL WHERE account_id = :account_id');
        $stmt->execute(['account_id' => $accountId]);
        $fileDownloadLogAnonymizedCount = $stmt->rowCount();
    }

    $seriesMetadataCount = 0;
    if (function_exists('sr_content_series_supported') && sr_content_series_supported($pdo)) {
        foreach (['created_by', 'updated_by'] as $columnName) {
            if (!$columnExists($pdo, 'sr_content_series', $columnName)) {
                continue;
            }

            $stmt = $pdo->prepare(
                'UPDATE sr_content_series
                 SET ' . $columnName . ' = NULL
                 WHERE ' . $columnName . ' = :account_id'
            );
            $stmt->execute(['account_id' => $accountId]);
            $seriesMetadataCount += $stmt->rowCount();
        }

        if ($columnExists($pdo, 'sr_content_series_items', 'created_by')) {
            $stmt = $pdo->prepare(
                'UPDATE sr_content_series_items
                 SET created_by = NULL
                 WHERE created_by = :account_id'
            );
            $stmt->execute(['account_id' => $accountId]);
            $seriesMetadataCount += $stmt->rowCount();
        }
    }

    return [
        'cleaned' => true,
        'event_type' => (string) ($context['event_type'] ?? ''),
        'content_access_entitlement_anonymized_count' => sr_content_anonymize_access_entitlements($pdo, $accountId),
        'content_file_download_log_anonymized_count' => $fileDownloadLogAnonymizedCount,
        'content_series_metadata_anonymized_count' => $seriesMetadataCount,
    ];
};
