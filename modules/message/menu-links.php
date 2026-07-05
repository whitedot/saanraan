<?php

declare(strict_types=1);

return static function (PDO $pdo): array {
    require_once __DIR__ . '/helpers.php';

    if (!sr_message_enabled($pdo)) {
        return [];
    }

    return [
        [
            'asset_type' => 'message_box',
            'asset_type_label' => '쪽지',
            'label' => '쪽지함',
            'url' => '/messages',
        ],
    ];
};
