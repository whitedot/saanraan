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

    $deleted = sr_community_delete_member_nickname($pdo, $accountId);
    $levelCleanup = sr_community_delete_account_level_data($pdo, $accountId);
    $entitlementCount = sr_community_anonymize_access_entitlements($pdo, $accountId);

    return [
        'cleaned' => true,
        'event_type' => (string) ($context['event_type'] ?? ''),
        'community_member_nickname_deleted' => $deleted,
        'community_account_level_deleted' => (bool) ($levelCleanup['account_level_deleted'] ?? false),
        'community_level_log_deleted_count' => (int) ($levelCleanup['level_log_deleted_count'] ?? 0),
        'community_access_entitlement_anonymized_count' => $entitlementCount,
    ];
};
