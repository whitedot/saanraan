<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    $columnExists = static function (PDO $pdo, string $tableName, string $columnName): bool {
        static $exists = [];
        if (!preg_match('/\Asr_[a-z0-9_]+\z/', $tableName) || !preg_match('/\A[a-zA-Z0-9_]+\z/', $columnName)) {
            return false;
        }

        $cacheKey = (string) spl_object_id($pdo) . ':' . $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $exists)) {
            return $exists[$cacheKey];
        }

        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $stmt = $pdo->query('PRAGMA table_info(' . $tableName . ')');
                foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                    if ((string) ($row['name'] ?? '') === $columnName) {
                        $exists[$cacheKey] = true;
                        return true;
                    }
                }
                $exists[$cacheKey] = false;
                return false;
            }

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
    $viewPaymentLogAnonymizedCount = 0;
    $authorSnapshotAnonymizedCount = 0;
    if ($columnExists($pdo, 'sr_content_comments', 'author_public_name_snapshot')) {
        $stmt = $pdo->prepare(
            'UPDATE sr_content_comments
             SET author_public_name_snapshot = \'\'
             WHERE author_account_id = :account_id
               AND author_public_name_snapshot <> \'\''
        );
        $stmt->execute(['account_id' => $accountId]);
        $authorSnapshotAnonymizedCount = $stmt->rowCount();
    }

    if (function_exists('sr_content_file_download_logs_table_exists') && sr_content_file_download_logs_table_exists($pdo)) {
        $fileDownloadSetSql = $columnExists($pdo, 'sr_content_file_download_logs', 'coupon_dedupe_key')
            ? "account_id = NULL,\n                 coupon_dedupe_key = ''"
            : 'account_id = NULL';
        $stmt = $pdo->prepare(
            'UPDATE sr_content_file_download_logs
             SET ' . $fileDownloadSetSql . '
             WHERE account_id = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);
        $fileDownloadLogAnonymizedCount = $stmt->rowCount();
    }

    if ($columnExists($pdo, 'sr_content_view_payment_logs', 'account_id')) {
        $viewPaymentSetSql = $columnExists($pdo, 'sr_content_view_payment_logs', 'coupon_dedupe_key')
            ? "account_id = NULL,\n                 coupon_dedupe_key = ''"
            : 'account_id = NULL';
        $stmt = $pdo->prepare(
            'UPDATE sr_content_view_payment_logs
             SET ' . $viewPaymentSetSql . '
             WHERE account_id = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);
        $viewPaymentLogAnonymizedCount = $stmt->rowCount();
    }

    $authorApplicationAnonymizedCount = 0;
    if ($columnExists($pdo, 'sr_content_author_applications', 'account_id')) {
        $stmt = $pdo->prepare(
            "UPDATE sr_content_author_applications
             SET account_id = NULL,
                 application_note = '',
                 review_note = '',
                 updated_at = :updated_at
             WHERE account_id = :account_id"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'updated_at' => sr_now(),
        ]);
        $authorApplicationAnonymizedCount = $stmt->rowCount();
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
        'content_author_snapshot_anonymized_count' => $authorSnapshotAnonymizedCount,
        'content_view_payment_log_anonymized_count' => $viewPaymentLogAnonymizedCount,
        'content_file_download_log_anonymized_count' => $fileDownloadLogAnonymizedCount,
        'content_author_application_anonymized_count' => $authorApplicationAnonymizedCount,
        'content_series_metadata_anonymized_count' => $seriesMetadataCount,
    ];
};
