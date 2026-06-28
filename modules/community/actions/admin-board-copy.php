<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$boardId = sr_request_method() === 'POST' ? (int) sr_post_string('board_id', 20) : (int) sr_get_string('id', 20);
if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/boards', 'edit');
    $postedScopeInput = [
        'mode' => sr_post_string('mode', 20),
        'copy_series' => ($_POST['copy_series'] ?? '') === '1',
    ];
    if (array_key_exists('copy_scope', $_POST)) {
        $postedScopeInput['copy_scope'] = is_array($_POST['copy_scope'] ?? null) ? $_POST['copy_scope'] : [];
    }
    $postedScopeValues = sr_community_board_copy_scope_values($postedScopeInput);
    if (in_array('posts_comments', $postedScopeValues, true) || (string) sr_post_string('intent', 30) === 'start_batch') {
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
    'copy_scope' => sr_request_method() === 'POST' ? (is_array($_POST['copy_scope'] ?? null) ? $_POST['copy_scope'] : []) : ['settings'],
    'copy_series' => ($_POST['copy_series'] ?? '') === '1',
    'series_titles' => is_array($_POST['community_series_titles'] ?? null) ? $_POST['community_series_titles'] : [],
];
$values = sr_community_board_copy_normalized_values($values);
$copyCounts = sr_community_board_copy_counts($pdo, $boardId);
$selectedCopyCounts = sr_community_board_copy_counts_for_values($copyCounts, $values);
$batchErrors = !empty($values['copy_posts_comments']) ? sr_community_board_copy_batch_errors_for_values($copyCounts, $values) : [];
$limitErrors = !empty($values['copy_posts_comments'])
    ? array_merge(sr_community_board_copy_batch_threshold_errors($selectedCopyCounts), $batchErrors)
    : [];
$batchAvailable = $batchErrors === [];
$loadAssessment = sr_community_board_copy_load_assessment($selectedCopyCounts, $values, $batchAvailable);
$errors = [];

if (sr_request_method() === 'POST') {
    try {
        $scopeErrors = sr_community_board_copy_scope_errors($values);
        if ($scopeErrors !== []) {
            throw new InvalidArgumentException(implode("\n", $scopeErrors));
        }
        if (!empty($values['copy_posts_comments'])) {
            if ($batchErrors !== []) {
                throw new InvalidArgumentException(implode("\n", $batchErrors));
            }
            $values['mode'] = 'full';
            $newJobId = sr_community_board_copy_job_create($pdo, $boardId, $values, (int) $account['id']);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community_board.copy_job_created',
                'target_type' => 'community_board_copy_job',
                'target_id' => (string) $newJobId,
                'result' => 'success',
                'message' => 'Community board copy job created.',
                'metadata' => [
                    'source_board_id' => $boardId,
                    'counts' => $selectedCopyCounts,
                    'copy_scope' => $values['copy_scope'],
                    'batch' => true,
                    'failed_count' => 0,
                    'load_grade' => (string) $loadAssessment['grade'],
                    'confirmation_checked' => true,
                ],
            ]);
            sr_redirect('/admin/community/board-copy-jobs?id=' . (string) $newJobId);
        }
        if (sr_post_string('intent', 30) === 'start_batch') {
            throw new InvalidArgumentException('게시글+댓글을 선택한 뒤 복사 작업을 만드세요.');
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
                'counts' => $selectedCopyCounts,
                'copy_scope' => $values['copy_scope'],
                'batch' => false,
                'failed_count' => 0,
                'load_grade' => (string) $loadAssessment['grade'],
                'confirmation_checked' => true,
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

    sr_admin_flash_result(sr_admin_action_result($errors, ''));
    sr_redirect('/admin/community/boards');
}

include SR_ROOT . '/modules/community/views/admin-board-copy.php';
