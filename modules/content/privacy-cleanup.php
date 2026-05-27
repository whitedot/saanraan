<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    if (!function_exists('sr_content_anonymize_access_entitlements')) {
        require_once SR_ROOT . '/modules/content/helpers.php';
    }

    return [
        'cleaned' => true,
        'event_type' => (string) ($context['event_type'] ?? ''),
        'content_access_entitlement_anonymized_count' => sr_content_anonymize_access_entitlements($pdo, $accountId),
    ];
};
