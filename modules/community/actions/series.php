<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$settings = sr_community_settings($pdo);
sr_community_require_member_nickname($pdo, $account, $settings, (string) ($_SERVER['REQUEST_URI'] ?? '/community'));
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);
    $seriesId = (int) sr_post_string('series_id', 20);
    $boardId = (int) sr_post_string('board_id', 20);
    $title = trim(sr_post_string('title', 160));
    $description = sr_post_string_without_truncation('description', 2000);
    $visibility = sr_post_string('visibility', 30);
    if (!in_array($visibility, sr_community_series_visibility_values(), true)) {
        $visibility = 'public';
    }
    if ($title === '') {
        $errors[] = '시리즈 제목을 입력해 주세요.';
    }
    $board = sr_community_board_by_id($pdo, $boardId);
    if (!is_array($board) || (string) ($board['status'] ?? '') !== 'enabled') {
        $errors[] = '게시판을 선택해 주세요.';
    }
    if ($errors === []) {
        if ($intent === 'create') {
            sr_community_create_series($pdo, $boardId, (int) $account['id'], [
                'title' => $title,
                'description' => is_string($description) ? $description : '',
                'status' => 'active',
                'visibility' => $visibility,
            ], (int) $account['id']);
            $notice = '시리즈를 만들었습니다.';
        } elseif ($intent === 'update') {
            $series = sr_community_series_by_id($pdo, $seriesId);
            if (!is_array($series) || (int) $series['owner_account_id'] !== (int) $account['id']) {
                $errors[] = '시리즈를 찾을 수 없습니다.';
            } else {
                sr_community_update_series($pdo, $seriesId, [
                    'title' => $title,
                    'description' => is_string($description) ? $description : '',
                    'status' => 'active',
                    'visibility' => $visibility,
                ], (int) $account['id']);
                $notice = '시리즈를 수정했습니다.';
            }
        }
    }
}

$boards = $pdo->query("SELECT id, board_key, title FROM sr_community_boards WHERE status = 'enabled' ORDER BY sort_order ASC, id ASC")->fetchAll();
$seriesList = sr_community_account_series($pdo, (int) $account['id']);

include SR_ROOT . '/modules/community/views/series.php';
