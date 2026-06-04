<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$boardId = sr_request_method() === 'POST' ? (int) sr_post_string('board_id', 20) : (int) sr_get_string('id', 20);
if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/boards', 'edit');
    if ((string) sr_post_string('mode', 20) === 'full') {
        sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit');
    }
    sr_require_csrf();
} else {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/boards', 'view');
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/posts', 'view');
}

$sourceBoard = sr_community_board_by_id($pdo, $boardId);
if (!is_array($sourceBoard)) {
    sr_render_error(404, '복사할 게시판을 찾을 수 없습니다.');
}

$suggestion = sr_community_board_copy_suggestion($sourceBoard);
$values = [
    'board_key' => sr_request_method() === 'POST' ? sr_post_string('board_key', 60) : (string) $suggestion['board_key'],
    'title' => sr_request_method() === 'POST' ? sr_post_string('title', 120) : (string) $suggestion['title'],
    'mode' => sr_request_method() === 'POST' ? sr_post_string('mode', 20) : 'settings',
    'copy_series' => ($_POST['copy_series'] ?? '') === '1',
];
$copyCounts = sr_community_board_copy_counts($pdo, $boardId);
$limitErrors = sr_community_board_copy_limit_errors($copyCounts);
$errors = [];

if (sr_request_method() === 'POST') {
    try {
        if (sr_post_string('intent', 30) === 'start_batch') {
            $newJobId = sr_community_board_copy_job_create($pdo, $boardId, $values, (int) $account['id']);
            sr_redirect('/admin/community/board-copy-jobs?id=' . (string) $newJobId);
        }
        $newBoardId = sr_community_copy_board($pdo, $boardId, $values, (int) $account['id']);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'community_board.copied',
            'target_type' => 'community_board',
            'target_id' => (string) $newBoardId,
            'result' => 'success',
            'message' => 'Community board copied.',
            'metadata' => [
                'source_board_id' => $boardId,
                'mode' => (string) $values['mode'],
            ],
        ]);
        $_SESSION['sr_community_admin_notice'] = '게시판 복사본을 만들었습니다.';
        sr_redirect('/admin/community/boards/edit?id=' . (string) $newBoardId);
    } catch (InvalidArgumentException $exception) {
        $errors = preg_split('/\n+/', $exception->getMessage()) ?: [$exception->getMessage()];
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'community_board_copy_failed');
        }
        $errors = ['게시판 복사 중 오류가 발생했습니다.'];
    }
}

include SR_ROOT . '/modules/community/views/admin-board-copy.php';
