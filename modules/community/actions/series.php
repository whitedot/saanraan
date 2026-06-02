<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$settings = sr_community_settings($pdo);
sr_community_require_member_nickname($pdo, $account, $settings, (string) ($_SERVER['REQUEST_URI'] ?? '/community'));
$errors = [];
$notice = '';
$flash = isset($_SESSION['sr_community_series_flash']) && is_array($_SESSION['sr_community_series_flash'])
    ? $_SESSION['sr_community_series_flash']
    : [];
unset($_SESSION['sr_community_series_flash']);
$errors = isset($flash['errors']) && is_array($flash['errors']) ? array_values(array_map('strval', $flash['errors'])) : [];
$notice = (string) ($flash['notice'] ?? '');
$seriesSupported = sr_community_series_supported($pdo);

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
    if (!$seriesSupported) {
        $errors[] = '커뮤니티 시리즈 스키마 업데이트가 아직 적용되지 않았습니다.';
    }
    if ($title === '') {
        $errors[] = '시리즈 제목을 입력해 주세요.';
    }
    if (!is_string($description)) {
        $errors[] = '시리즈 설명이 너무 깁니다.';
        $description = '';
    }
    $board = sr_community_board_by_id($pdo, $boardId);
    if (!is_array($board) || (string) ($board['status'] ?? '') !== 'enabled') {
        $errors[] = '게시판을 선택해 주세요.';
    } elseif (!sr_community_account_can_write_board($pdo, $board, $account, sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit'))) {
        $errors[] = '글쓰기 권한이 있는 게시판만 선택할 수 있습니다.';
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
            } elseif ((int) ($series['board_id'] ?? 0) !== $boardId) {
                $errors[] = '시리즈 게시판을 확인해 주세요.';
            } elseif ((string) ($series['status'] ?? '') !== 'active') {
                $errors[] = '관리 중인 시리즈는 수정할 수 없습니다.';
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

    $_SESSION['sr_community_series_flash'] = [
        'errors' => $errors,
        'notice' => $notice,
    ];
    sr_redirect('/community/series');
}

$canWriteAsAdmin = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit');
$boards = [];
foreach ($pdo->query("SELECT id, board_key, title, board_group_id, status, write_policy FROM sr_community_boards WHERE status = 'enabled' ORDER BY sort_order ASC, id ASC")->fetchAll() as $boardOption) {
    if (sr_community_account_can_write_board($pdo, $boardOption, $account, $canWriteAsAdmin)) {
        $boards[] = $boardOption;
    }
}
$seriesList = sr_community_account_series($pdo, (int) $account['id']);
if (!$seriesSupported && sr_request_method() !== 'POST') {
    $errors[] = '커뮤니티 시리즈 스키마 업데이트가 아직 적용되지 않았습니다.';
}

include SR_ROOT . '/modules/community/views/series.php';
