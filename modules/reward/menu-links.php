<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return static function (PDO $pdo): array {
    if (!sr_reward_usage_enabled($pdo)) {
        return [];
    }

    return [
        [
            'label' => sr_reward_display_name($pdo),
            'url' => '/account/rewards',
        ],
    ];
};
