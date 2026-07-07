<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

if (sr_request_method() !== 'POST') {
    sr_json_response(['ok' => false, 'message' => 'method_not_allowed'], 405, ['Cache-Control: no-store']);
}

$account = sr_member_require_login_json($pdo);

$expectedCsrf = $_SESSION['sr_csrf_token'] ?? '';
$actualCsrf = $_POST['csrf_token'] ?? '';
if (!is_string($expectedCsrf) || !is_string($actualCsrf) || $expectedCsrf === '' || !hash_equals($expectedCsrf, $actualCsrf)) {
    sr_json_response(['ok' => false, 'message' => 'csrf_invalid'], 400, ['Cache-Control: no-store']);
}
sr_require_csrf();

$settings = sr_community_settings($pdo);
if (!sr_community_draft_autosave_enabled($settings)) {
    sr_json_response(['ok' => false, 'message' => 'draft_autosave_disabled'], 403, ['Cache-Control: no-store']);
}

try {
    sr_community_draft_cleanup($pdo, $settings, 20);
} catch (Throwable $exception) {
    sr_log_exception($exception, 'community_draft_autosave_cleanup');
}

$mode = sr_community_draft_mode(sr_post_string('draft_mode', 20));
$postId = preg_match('/\A[1-9][0-9]*\z/', sr_post_string('post_id', 20)) === 1 ? (int) sr_post_string('post_id', 20) : 0;
$board = null;
$post = null;

if ($mode === 'edit') {
    if ($postId < 1) {
        sr_json_response(['ok' => false, 'message' => 'post_required'], 422, ['Cache-Control: no-store']);
    }
    $post = sr_community_post_for_read($pdo, $postId, $account);
    if (!is_array($post) || !sr_community_account_can_edit_post($post, $account)) {
        sr_json_response(['ok' => false, 'message' => 'edit_forbidden'], 403, ['Cache-Control: no-store']);
    }
    $board = sr_community_board_by_id($pdo, (int) $post['board_id']);
} else {
    $boardKey = sr_post_string('board_key', 60);
    $board = sr_community_board_by_key($pdo, $boardKey);
    $isAdminWriter = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit');
    if (!is_array($board) || (string) ($board['status'] ?? '') !== 'enabled') {
        sr_json_response(['ok' => false, 'message' => 'board_not_found'], 404, ['Cache-Control: no-store']);
    }
    if (!sr_community_account_can_write_board($pdo, $board, $account, $isAdminWriter)
        && !sr_community_account_can_write_notice($pdo, $board, $account, $isAdminWriter)) {
        sr_json_response(['ok' => false, 'message' => 'write_forbidden'], 403, ['Cache-Control: no-store']);
    }
}

if (!is_array($board)) {
    sr_json_response(['ok' => false, 'message' => 'board_not_found'], 404, ['Cache-Control: no-store']);
}

$categoryEnabled = sr_community_board_category_enabled($pdo, (int) $board['id']);
$extraFieldDefinitions = sr_community_board_extra_field_definitions($pdo, $board);
$seriesEnabled = sr_community_effective_board_series_enabled($pdo, $board, $settings);
$values = sr_community_post_input_values($pdo, $board, $settings);
if (!$categoryEnabled) {
    $values['category_id'] = 0;
}
$extraFieldValues = sr_community_extra_field_input_values($extraFieldDefinitions);
$values['extra_values_json'] = sr_community_extra_field_values_json($extraFieldDefinitions, $extraFieldValues);
$seriesValues = [
    'series_mode' => $seriesEnabled ? sr_post_string('series_mode', 20) : 'none',
    'series_id' => $seriesEnabled ? (int) sr_post_string('series_id', 20) : 0,
    'new_series_title' => $seriesEnabled ? trim(sr_post_string('new_series_title', 160)) : '',
    'episode_label' => $seriesEnabled ? trim(sr_post_string('series_episode_label', 80)) : '',
    'sort_order' => $seriesEnabled ? (sr_community_series_post_sort_order() ?? 0) : 0,
];
if (!in_array((string) $seriesValues['series_mode'], ['none', 'existing', 'new'], true)) {
    $seriesValues['series_mode'] = 'none';
}

$draftAction = sr_post_string('draft_action', 20);
if ($draftAction === 'delete') {
    sr_community_draft_delete($pdo, (int) $account['id'], (int) $board['id'], $mode, $postId);
    sr_json_response(['ok' => true, 'deleted' => true], 200, ['Cache-Control: no-store']);
}

$formStateJson = sr_community_draft_form_state_json([
    'category_id' => (int) ($values['category_id'] ?? 0),
    'is_secret' => (int) ($values['is_secret'] ?? 0),
    'is_notice' => (int) ($values['is_notice'] ?? 0),
    'extra_field_values' => $extraFieldValues,
    'series_values' => $seriesValues,
]);
$baseContentHash = $mode === 'edit' && is_array($post) ? sr_community_draft_content_hash_for_post($pdo, $post) : '';
$result = sr_community_draft_upsert($pdo, [
    'account_id' => (int) $account['id'],
    'board_id' => (int) $board['id'],
    'draft_mode' => $mode,
    'post_id' => $postId,
    'base_content_hash' => $baseContentHash,
    'title' => (string) ($values['title'] ?? ''),
    'body_format' => (string) ($values['body_format'] ?? 'plain'),
    'body_text' => (string) ($values['body_text'] ?? ''),
    'form_state_json' => $formStateJson,
    'body_tmp_ref_count' => sr_community_draft_body_tmp_ref_count((string) ($values['body_text'] ?? '')),
], $settings);

if (empty($result['saved'])) {
    sr_json_response(['ok' => false, 'message' => (string) ($result['reason'] ?? 'draft_save_failed')], 503, ['Cache-Control: no-store']);
}

sr_json_response([
    'ok' => true,
    'saved' => true,
    'last_saved_at' => (string) ($result['last_saved_at'] ?? sr_now()),
], 200, ['Cache-Control: no-store']);
