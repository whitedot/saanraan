<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_current_account($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sr_require_csrf();
}

$postIdValue = sr_get_string('id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = sr_community_post_for_read($pdo, $postId, is_array($account) ? $account : null);
if (!is_array($post)) {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

$isGuestAuthor = !is_array($account) && (int) ($post['author_account_id'] ?? 0) < 1 && (string) ($post['guest_password_hash'] ?? '') !== '';
$guestEditSessionKey = 'sr_community_guest_post_edit_' . (string) $postId;
if ($isGuestAuthor && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION[$guestEditSessionKey]) && sr_community_guest_can_edit_post($post, sr_post_string_without_truncation('guest_password', 255) ?? '')) {
    $_SESSION[$guestEditSessionKey] = hash('sha256', (string) ($post['guest_password_hash'] ?? ''));
}
$guestEditVerified = $isGuestAuthor
    && isset($_SESSION[$guestEditSessionKey])
    && hash_equals((string) $_SESSION[$guestEditSessionKey], hash('sha256', (string) ($post['guest_password_hash'] ?? '')));

if (!(is_array($account) && sr_community_account_can_edit_post($post, $account)) && !$guestEditVerified) {
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
$extraFieldDefinitions = sr_community_board_extra_field_definitions($pdo, $board);
$storedExtraFieldValues = sr_community_extra_field_values_from_json((string) ($post['extra_values_json'] ?? ''));
$extraFieldValues = [];
foreach ($storedExtraFieldValues as $extraFieldKey => $extraFieldRow) {
    if (is_array($extraFieldRow)) {
        $extraFieldValues[(string) $extraFieldKey] = (string) ($extraFieldRow['value'] ?? '');
    }
}
$seriesOptions = is_array($account) ? sr_community_account_series($pdo, (int) $account['id'], (int) $board['id']) : [];
$currentSeriesItem = sr_community_active_series_item_for_post($pdo, $postId);
if (is_array($currentSeriesItem)
    && is_array($account)
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
    if (!isset($_POST['title']) && $guestEditVerified) {
        $_SESSION['sr_community_post_notice'] = '비회원 글 수정 비밀번호를 확인했습니다.';
        sr_redirect('/community/edit?id=' . (string) $postId);
    }

    $submittedPostIdValue = sr_post_string('post_id', 20);
    $submittedPostId = preg_match('/\A[1-9][0-9]*\z/', $submittedPostIdValue) === 1 ? (int) $submittedPostIdValue : 0;
    if ($submittedPostId !== $postId) {
        sr_render_error(400, sr_t('community::action.error.post_value_invalid'));
    }

    $values = sr_community_post_input_values($pdo, $board, $settings);
    $extraFieldValues = sr_community_extra_field_input_values($extraFieldDefinitions);
    $values['extra_values_json'] = sr_community_extra_field_values_json($extraFieldDefinitions, $extraFieldValues);
    $values['seo_title'] = (string) ($post['seo_title'] ?? '');
    $values['seo_description'] = (string) ($post['seo_description'] ?? '');
    $values['og_title'] = (string) ($post['og_title'] ?? '');
    $values['og_description'] = (string) ($post['og_description'] ?? '');
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
    $errors = array_merge($errors, sr_community_validate_extra_field_values($extraFieldDefinitions, $extraFieldValues));
    if ($extraFieldDefinitions !== [] && !sr_community_post_extra_values_column_exists($pdo)) {
        $errors[] = '게시판 추가 입력 스키마 업데이트가 아직 적용되지 않았습니다.';
    }
    $errors = array_merge($errors, sr_community_post_category_validation_errors($pdo, $board, $values, $post));
    $privacyConsentActionKeys = sr_community_privacy_consent_post_targets_from_request($values);
    $errors = array_merge($errors, sr_community_privacy_consent_validation_errors($pdo, $board, $privacyConsentActionKeys));
    if ((string) $seriesValues['series_mode'] !== 'none' && !sr_community_series_supported($pdo)) {
        $errors[] = '커뮤니티 시리즈 스키마 업데이트가 아직 적용되지 않았습니다.';
    }
    if (!is_array($account) && (string) $seriesValues['series_mode'] !== 'none') {
        $errors[] = '비회원 글은 시리즈를 연결할 수 없습니다.';
    }
    if ((string) $seriesValues['series_mode'] !== 'none' && $seriesSortOrder === null) {
        $errors[] = '시리즈 정렬 순서를 확인해 주세요.';
    }
    if ((string) $seriesValues['series_mode'] === 'existing') {
        $selectedSeries = sr_community_series_by_id($pdo, (int) $seriesValues['series_id']);
        if (!is_array($selectedSeries)
            || !is_array($account)
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
        $authorAccountId = is_array($account) ? (int) $account['id'] : 0;
        sr_community_update_post_content($pdo, $postId, $values, $authorAccountId);
        $privacyConsentRecordCount = sr_community_record_submission_consents($pdo, (int) $board['id'], $authorAccountId, 'community.post', $postId, $privacyConsentActionKeys, $board);
        if (is_array($account) && (string) $seriesValues['series_mode'] === 'new') {
            $seriesValues['series_id'] = sr_community_create_series($pdo, (int) $board['id'], $authorAccountId, [
                'title' => (string) $seriesValues['new_series_title'],
                'description' => '',
                'status' => 'active',
                'visibility' => 'public',
            ], $authorAccountId);
        }
        if (is_array($account)) {
            sr_community_set_post_series(
                $pdo,
                $postId,
                in_array((string) $seriesValues['series_mode'], ['existing', 'new'], true) ? (int) $seriesValues['series_id'] : 0,
                (string) $seriesValues['episode_label'],
                (int) $seriesValues['sort_order'],
                $authorAccountId
            );
        }
        sr_audit_log($pdo, [
            'actor_account_id' => $authorAccountId > 0 ? $authorAccountId : null,
            'actor_type' => is_array($account) ? 'member' : 'guest',
            'event_type' => 'community.post.updated_by_author',
            'target_type' => 'community_post',
            'target_id' => (string) $postId,
            'result' => 'success',
            'message' => 'Community post updated by author.',
            'metadata' => [
                'board_key' => (string) $post['board_key'],
                'privacy_consent_record_count' => $privacyConsentRecordCount,
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
