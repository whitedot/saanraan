<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sr_require_csrf();
}

$postIdValue = sr_get_string('id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = sr_community_post_for_read($pdo, $postId, $account);
if (!is_array($post)) {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

if (!sr_community_account_can_edit_post($post, $account)) {
    sr_render_error(403, sr_t('community::action.error.post_edit_forbidden'));
}

$board = sr_community_board_by_id($pdo, (int) $post['board_id']);
if (!is_array($board)) {
    $board = [
        'id' => (int) $post['board_id'],
        'board_key' => (string) $post['board_key'],
        'title' => (string) $post['board_title'],
    ];
}
$settings = sr_community_settings($pdo);
$board['image_uploads_enabled'] = 0;
$board['file_uploads_enabled'] = 0;
$secretPostsEnabled = sr_community_effective_board_secret_posts_enabled($pdo, $board, $settings);
$categories = sr_community_categories($pdo, (int) $board['id'], true);
$currentCategory = (int) ($post['category_id'] ?? 0) > 0 ? sr_community_category_by_id($pdo, (int) $post['category_id']) : null;
if (is_array($currentCategory) && (string) $currentCategory['status'] !== 'enabled') {
    $categories[] = $currentCategory;
}
$categoryRequired = sr_community_board_category_required($pdo, (int) $board['id']);
$seriesOptions = sr_community_account_series($pdo, (int) $account['id'], (int) $board['id']);
$currentSeriesItem = sr_community_active_series_item_for_post($pdo, $postId);
if (is_array($currentSeriesItem)
    && (int) ($currentSeriesItem['owner_account_id'] ?? 0) === (int) $account['id']
    && (int) ($currentSeriesItem['board_id'] ?? 0) === (int) $board['id']
) {
    $knownSeriesIds = [];
    foreach ($seriesOptions as $seriesOption) {
        $knownSeriesIds[(int) ($seriesOption['id'] ?? 0)] = true;
    }
    if (!isset($knownSeriesIds[(int) $currentSeriesItem['series_id']])) {
        $seriesOptions[] = [
            'id' => (int) $currentSeriesItem['series_id'],
            'title' => (string) $currentSeriesItem['series_title'],
            'status' => (string) $currentSeriesItem['series_status'],
            'visibility' => (string) $currentSeriesItem['visibility'],
        ];
    }
}
$seriesValues = [
    'series_mode' => is_array($currentSeriesItem) ? 'existing' : 'none',
    'series_id' => is_array($currentSeriesItem) ? (int) $currentSeriesItem['series_id'] : 0,
    'new_series_title' => '',
    'episode_label' => is_array($currentSeriesItem) ? (string) ($currentSeriesItem['episode_label'] ?? '') : '',
    'sort_order' => is_array($currentSeriesItem) ? (int) ($currentSeriesItem['sort_order'] ?? 0) : 0,
];
$errors = [];
$values = [
    'title' => (string) $post['title'],
    'category_id' => (int) ($post['category_id'] ?? 0),
    'body_text' => (string) $post['body_text'],
    'body_format' => (string) ($post['body_format'] ?? 'plain'),
    'seo_title' => (string) ($post['seo_title'] ?? ''),
    'seo_description' => (string) ($post['seo_description'] ?? ''),
    'og_title' => (string) ($post['og_title'] ?? ''),
    'og_description' => (string) ($post['og_description'] ?? ''),
    'is_secret' => (int) ($post['is_secret'] ?? 0),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedPostIdValue = sr_post_string('post_id', 20);
    $submittedPostId = preg_match('/\A[1-9][0-9]*\z/', $submittedPostIdValue) === 1 ? (int) $submittedPostIdValue : 0;
    if ($submittedPostId !== $postId) {
        sr_render_error(400, sr_t('community::action.error.post_value_invalid'));
    }

    $values = sr_community_post_input_values($pdo, $board, $settings);
    $seriesSortOrder = sr_community_series_post_sort_order();
    $seriesValues = [
        'series_mode' => sr_post_string('series_mode', 20),
        'series_id' => (int) sr_post_string('series_id', 20),
        'new_series_title' => trim(sr_post_string('new_series_title', 160)),
        'episode_label' => trim(sr_post_string('series_episode_label', 80)),
        'sort_order' => $seriesSortOrder ?? 0,
    ];
    if (!in_array((string) $seriesValues['series_mode'], ['none', 'existing', 'new'], true)) {
        $seriesValues['series_mode'] = 'none';
    }
    $errors = sr_community_validate_post_input($values);
    $errors = array_merge($errors, sr_community_post_category_validation_errors($pdo, $board, $values, $post));
    if ((string) $seriesValues['series_mode'] !== 'none' && !sr_community_series_supported($pdo)) {
        $errors[] = '커뮤니티 시리즈 스키마 업데이트가 아직 적용되지 않았습니다.';
    }
    if ((string) $seriesValues['series_mode'] !== 'none' && $seriesSortOrder === null) {
        $errors[] = '시리즈 정렬 순서를 확인해 주세요.';
    }
    if ((string) $seriesValues['series_mode'] === 'existing') {
        $selectedSeries = sr_community_series_by_id($pdo, (int) $seriesValues['series_id']);
        if (!is_array($selectedSeries)
            || (int) ($selectedSeries['owner_account_id'] ?? 0) !== (int) $account['id']
            || (int) ($selectedSeries['board_id'] ?? 0) !== (int) $board['id']
            || !in_array((string) ($selectedSeries['status'] ?? ''), ['pending', 'active', 'hidden'], true)
        ) {
            $errors[] = '연결할 시리즈를 확인해 주세요.';
        }
    } elseif ((string) $seriesValues['series_mode'] === 'new' && (string) $seriesValues['new_series_title'] === '') {
        $errors[] = '새 시리즈 제목을 입력해 주세요.';
    }

    if ($errors === []) {
        sr_community_update_post_content($pdo, $postId, $values, (int) $account['id']);
        if ((string) $seriesValues['series_mode'] === 'new') {
            $seriesValues['series_id'] = sr_community_create_series($pdo, (int) $board['id'], (int) $account['id'], [
                'title' => (string) $seriesValues['new_series_title'],
                'description' => '',
                'status' => 'active',
                'visibility' => 'public',
            ], (int) $account['id']);
        }
        sr_community_set_post_series(
            $pdo,
            $postId,
            in_array((string) $seriesValues['series_mode'], ['existing', 'new'], true) ? (int) $seriesValues['series_id'] : 0,
            (string) $seriesValues['episode_label'],
            (int) $seriesValues['sort_order'],
            (int) $account['id']
        );
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'community.post.updated_by_author',
            'target_type' => 'community_post',
            'target_id' => (string) $postId,
            'result' => 'success',
            'message' => 'Community post updated by author.',
            'metadata' => [
                'board_key' => (string) $post['board_key'],
            ],
        ]);
        $_SESSION['sr_community_post_notice'] = sr_t('community::action.notice.post_updated');
        sr_redirect('/community/post?id=' . (string) $postId);
    }
}

$pageTitle = sr_t('community::action.title.post_edit');
$formAction = '/community/edit?id=' . (string) $postId;
$submitLabel = sr_t('community::action.submit.edit');
$postIdField = $postId;
$skinKey = sr_community_board_skin_key($pdo, $post);
$skinView = sr_community_skin_view($skinKey, 'form');

include $skinView;
