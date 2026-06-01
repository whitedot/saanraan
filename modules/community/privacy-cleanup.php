<?php

declare(strict_types=1);

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
        $stmt = $pdo->prepare(
            'UPDATE sr_community_series
             SET created_by = CASE WHEN created_by = :created_by_account_id THEN NULL ELSE created_by END,
                 updated_by = CASE WHEN updated_by = :updated_by_account_id THEN NULL ELSE updated_by END,
                 moderated_by = CASE WHEN moderated_by = :moderated_by_account_id THEN NULL ELSE moderated_by END
             WHERE created_by = :created_by_filter
                OR updated_by = :updated_by_filter
                OR moderated_by = :moderated_by_filter'
        );
        $stmt->execute([
            'created_by_account_id' => $accountId,
            'updated_by_account_id' => $accountId,
            'moderated_by_account_id' => $accountId,
            'created_by_filter' => $accountId,
            'updated_by_filter' => $accountId,
            'moderated_by_filter' => $accountId,
        ]);
        $seriesMetadataCount += $stmt->rowCount();

        $stmt = $pdo->prepare(
            'UPDATE sr_community_series_items
             SET created_by = NULL
             WHERE created_by = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);
        $seriesMetadataCount += $stmt->rowCount();
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
