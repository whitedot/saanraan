<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    if (!function_exists('sr_content_anonymize_access_entitlements')) {
        require_once SR_ROOT . '/modules/content/helpers.php';
    }

    $fileDownloadLogAnonymizedCount = 0;
    if (function_exists('sr_content_file_download_logs_table_exists') && sr_content_file_download_logs_table_exists($pdo)) {
        $stmt = $pdo->prepare('UPDATE sr_content_file_download_logs SET account_id = NULL WHERE account_id = :account_id');
        $stmt->execute(['account_id' => $accountId]);
        $fileDownloadLogAnonymizedCount = $stmt->rowCount();
    }

    return [
        'cleaned' => true,
        'event_type' => (string) ($context['event_type'] ?? ''),
        'content_access_entitlement_anonymized_count' => sr_content_anonymize_access_entitlements($pdo, $accountId),
        'content_file_download_log_anonymized_count' => $fileDownloadLogAnonymizedCount,
    ];
};
