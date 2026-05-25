<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    if (!function_exists('sr_community_delete_member_nickname')) {
        require_once SR_ROOT . '/modules/community/helpers/members.php';
    }

    $deleted = sr_community_delete_member_nickname($pdo, $accountId);

    return [
        'cleaned' => true,
        'event_type' => (string) ($context['event_type'] ?? ''),
        'community_member_nickname_deleted' => $deleted,
    ];
};
