<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return static function (PDO $pdo): array {
    if (!sr_reward_usage_enabled($pdo)) {
        return [];
    }

    return [
        [
            'label' => '적립금',
            'url' => '/account/rewards',
        ],
    ];
};
