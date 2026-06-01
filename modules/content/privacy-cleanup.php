<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

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
        $stmt = $pdo->prepare(
            'UPDATE sr_content_series
             SET created_by = CASE WHEN created_by = :created_by_account_id THEN NULL ELSE created_by END,
                 updated_by = CASE WHEN updated_by = :updated_by_account_id THEN NULL ELSE updated_by END
             WHERE created_by = :created_by_filter
                OR updated_by = :updated_by_filter'
        );
        $stmt->execute([
            'created_by_account_id' => $accountId,
            'updated_by_account_id' => $accountId,
            'created_by_filter' => $accountId,
            'updated_by_filter' => $accountId,
        ]);
        $seriesMetadataCount += $stmt->rowCount();

        $stmt = $pdo->prepare(
            'UPDATE sr_content_series_items
             SET created_by = NULL
             WHERE created_by = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);
        $seriesMetadataCount += $stmt->rowCount();
    }

    return [
        'cleaned' => true,
        'event_type' => (string) ($context['event_type'] ?? ''),
        'content_access_entitlement_anonymized_count' => sr_content_anonymize_access_entitlements($pdo, $accountId),
        'content_file_download_log_anonymized_count' => $fileDownloadLogAnonymizedCount,
        'content_series_metadata_anonymized_count' => $seriesMetadataCount,
    ];
};
