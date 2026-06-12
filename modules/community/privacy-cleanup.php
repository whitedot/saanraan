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
    $seriesScrapDeletedCount = 0;
    $authorSnapshotAnonymizedCount = 0;
    $submissionConsentAnonymizedCount = 0;

    foreach (['sr_community_posts', 'sr_community_comments'] as $tableName) {
        if (!$columnExists($pdo, $tableName, 'author_public_name_snapshot')) {
            continue;
        }

        $stmt = $pdo->prepare(
            'UPDATE ' . $tableName . '
             SET author_public_name_snapshot = \'\'
             WHERE author_account_id = :account_id
               AND author_public_name_snapshot <> \'\''
        );
        $stmt->execute(['account_id' => $accountId]);
        $authorSnapshotAnonymizedCount += $stmt->rowCount();
    }

    if (function_exists('sr_community_series_supported') && sr_community_series_supported($pdo)) {
        if (function_exists('sr_community_series_scraps_table_exists') && sr_community_series_scraps_table_exists($pdo)) {
            $stmt = $pdo->prepare(
                'DELETE FROM sr_community_series_scraps
                 WHERE account_id = :account_id'
            );
            $stmt->execute(['account_id' => $accountId]);
            $seriesScrapDeletedCount = $stmt->rowCount();
        }

        foreach (['created_by', 'updated_by', 'moderated_by'] as $columnName) {
            if (!$columnExists($pdo, 'sr_community_series', $columnName)) {
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

        if ($columnExists($pdo, 'sr_community_series_items', 'created_by')) {
            $stmt = $pdo->prepare(
                'UPDATE sr_community_series_items
                 SET created_by = NULL
                 WHERE created_by = :account_id'
            );
            $stmt->execute(['account_id' => $accountId]);
            $seriesMetadataCount += $stmt->rowCount();
        }
    }

    if ($columnExists($pdo, 'sr_community_submission_consents', 'account_id')) {
        $stmt = $pdo->prepare(
            'UPDATE sr_community_submission_consents
             SET account_id = NULL,
                 ip_hash = NULL,
                 user_agent_hash = NULL
             WHERE account_id = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);
        $submissionConsentAnonymizedCount = $stmt->rowCount();
    }

    return [
        'cleaned' => true,
        'event_type' => (string) ($context['event_type'] ?? ''),
        'community_member_nickname_deleted' => $deleted,
        'community_account_level_deleted' => (bool) ($levelCleanup['account_level_deleted'] ?? false),
        'community_level_log_deleted_count' => (int) ($levelCleanup['level_log_deleted_count'] ?? 0),
        'community_access_entitlement_anonymized_count' => $entitlementCount,
        'community_author_snapshot_anonymized_count' => $authorSnapshotAnonymizedCount,
        'community_submission_consent_anonymized_count' => $submissionConsentAnonymizedCount,
        'community_series_scrap_deleted_count' => $seriesScrapDeletedCount,
        'community_series_metadata_anonymized_count' => $seriesMetadataCount,
    ];
};
