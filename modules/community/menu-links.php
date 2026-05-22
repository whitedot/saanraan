<?php

declare(strict_types=1);

return static function (PDO $pdo): array {
    require_once __DIR__ . '/helpers.php';

    $links = [];
    foreach (sr_community_enabled_board_groups($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_community_board_group_key_is_valid($groupKey)) {
            continue;
        }

        $links[] = [
            'asset_type' => 'board_group',
            'asset_type_label' => sr_t('community::ui.text.ec060706'),
            'label' => (string) ($group['title'] ?? $groupKey),
            'url' => '/community#group-' . rawurlencode($groupKey),
        ];
    }

    foreach (sr_community_enabled_boards($pdo) as $board) {
        $boardKey = (string) ($board['board_key'] ?? '');
        if (!sr_community_board_key_is_valid($boardKey)) {
            continue;
        }

        $links[] = [
            'asset_type' => 'board',
            'asset_type_label' => sr_t('community::ui.text.4732a58f'),
            'label' => (string) ($board['title'] ?? $boardKey),
            'url' => '/community/board?key=' . rawurlencode($boardKey),
        ];
    }

    return $links;
};
