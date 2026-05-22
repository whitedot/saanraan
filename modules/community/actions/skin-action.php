<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/community/helpers.php';

sr_require_csrf();

$skinKey = sr_post_string('skin_key', 40);
$actionKey = sr_post_string('action_key', 40);
$boardIdValue = sr_post_string('board_id', 20);
$boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
$board = $boardId > 0 ? sr_community_board_by_id($pdo, $boardId) : null;
if (!is_array($board) || (string) ($board['status'] ?? '') !== 'enabled') {
    sr_render_error(404, sr_t('community::action.error.board_not_found'));
}

$selectedSkinKey = sr_community_board_skin_key($pdo, $board);
if ($skinKey !== $selectedSkinKey) {
    sr_render_error(403, sr_t('community::action.error.skin_feature_forbidden'));
}

$action = sr_community_skin_action($selectedSkinKey, $actionKey, sr_request_method());
if ($action === null) {
    sr_render_error(404, sr_t('community::action.error.skin_feature_not_found'));
}

$skinActionFile = (string) ($action['file'] ?? '');
include $skinActionFile;
