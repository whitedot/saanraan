<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_current_account($pdo);
$boards = [];
foreach (sr_community_enabled_boards($pdo) as $board) {
    if (sr_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)) {
        $boards[] = $board;
    }
}
$boardSections = [];
$ungroupedBoards = [];
foreach ($boards as $board) {
    $groupId = (int) ($board['board_group_id'] ?? 0);
    $groupStatus = (string) ($board['board_group_status'] ?? '');
    if ($groupId > 0 && $groupStatus === 'enabled') {
        if (!isset($boardSections[$groupId])) {
            $boardSections[$groupId] = [
                'group_id' => $groupId,
                'title' => (string) ($board['board_group_title'] ?? ''),
                'boards' => [],
            ];
        }

        $boardSections[$groupId]['boards'][] = $board;
        continue;
    }

    if ($groupId < 1) {
        $ungroupedBoards[] = $board;
    }
}
$settings = sr_community_settings($pdo);
$communityLayoutKey = sr_community_layout_key($settings, $site ?? null, $pdo);
$themeView = sr_community_layout_home_view($communityLayoutKey, $pdo);

include $themeView;
