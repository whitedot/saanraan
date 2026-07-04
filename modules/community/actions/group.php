<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$groupKey = sr_get_string('key', 60);
$boardGroup = sr_community_enabled_board_group_by_key($pdo, $groupKey);
if (!is_array($boardGroup)) {
    sr_render_error(404, sr_t('community::action.error.board_group_not_found'));
}

$account = sr_member_current_account($pdo);
$settings = sr_community_settings($pdo);
$groupBoards = [];
foreach (sr_community_enabled_boards($pdo) as $board) {
    if ((int) ($board['board_group_id'] ?? 0) !== (int) ($boardGroup['id'] ?? 0)) {
        continue;
    }

    if (!sr_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)) {
        continue;
    }

    $boardIdentityPolicy = sr_community_identity_action_policy(
        $pdo,
        $board,
        is_array($account) ? $account : null,
        'enter',
        sr_community_board_group_path($groupKey)
    );
    if (!empty($boardIdentityPolicy['required']) && empty($boardIdentityPolicy['satisfied'])) {
        continue;
    }

    $groupBoards[] = $board;
}

$communityThemeFallbackViewFile = SR_ROOT . '/modules/community/views/group.php';
include sr_community_public_view_file($pdo, $settings, 'group.php', $communityThemeFallbackViewFile);
