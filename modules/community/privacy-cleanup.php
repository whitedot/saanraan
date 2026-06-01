<?php

declare(strict_types=1);

function sr_community_privacy_cleanup_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
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
}

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    if (!function_exists('sr_community_delete_member_nickname')) {
        require_once SR_ROOT . '/modules/community/helpers/members.php';
    }
    if (!function_exists('sr_community_delete_account_level_data')) {
        require_once SR_ROOT . '/modules/community/helpers/levels.php';
    }
    if (!function_exists('sr_community_anonymize_access_entitlements')) {
        require_once SR_ROOT . '/modules/community/helpers/assets.php';
    }
    if (!function_exists('sr_community_series_supported')) {
        require_once SR_ROOT . '/modules/community/helpers.php';
    }

    $deleted = sr_community_delete_member_nickname($pdo, $accountId);
    $levelCleanup = sr_community_delete_account_level_data($pdo, $accountId);
    $entitlementCount = sr_community_anonymize_access_entitlements($pdo, $accountId);
    $seriesMetadataCount = 0;

    if (function_exists('sr_community_series_supported') && sr_community_series_supported($pdo)) {
        foreach (['created_by', 'updated_by', 'moderated_by'] as $columnName) {
            if (!sr_community_privacy_cleanup_column_exists($pdo, 'sr_community_series', $columnName)) {
                continue;
            }

            $stmt = $pdo->prepare(
                'UPDATE sr_community_series
                 SET ' . $columnName . ' = NULL
                 WHERE ' . $columnName . ' = :account_id'
            );
            $stmt->execute(['account_id' => $accountId]);
            $seriesMetadataCount += $stmt->rowCount();
        }

        if (sr_community_privacy_cleanup_column_exists($pdo, 'sr_community_series_items', 'created_by')) {
            $stmt = $pdo->prepare(
                'UPDATE sr_community_series_items
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
        'community_member_nickname_deleted' => $deleted,
        'community_account_level_deleted' => (bool) ($levelCleanup['account_level_deleted'] ?? false),
        'community_level_log_deleted_count' => (int) ($levelCleanup['level_log_deleted_count'] ?? 0),
        'community_access_entitlement_anonymized_count' => $entitlementCount,
        'community_series_metadata_anonymized_count' => $seriesMetadataCount,
    ];
};
