<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    require_once SR_ROOT . '/modules/notification/helpers.php';

    return [
        'cleaned' => true,
        'event_type' => (string) ($context['event_type'] ?? ''),
        'notification_push_endpoint_disabled_count' => sr_notification_cleanup_member_push_endpoints($pdo, $accountId),
    ];
};
