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
    sr_render_error(404, '게시판을 찾을 수 없습니다.');
}

$selectedSkinKey = sr_community_board_skin_key($pdo, $board);
if ($skinKey !== $selectedSkinKey) {
    sr_render_error(403, '이 게시판에서 사용할 수 없는 스킨 기능입니다.');
}

$action = sr_community_skin_action($selectedSkinKey, $actionKey, sr_request_method());
if ($action === null) {
    sr_render_error(404, '스킨 기능을 찾을 수 없습니다.');
}

$skinActionFile = (string) ($action['file'] ?? '');
include $skinActionFile;
