<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/content/helpers/member-groups.php';
require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
if (sr_request_method() === 'POST') {
    sr_require_csrf();
}

$slug = sr_content_slug_from_request_path();
$page = $slug !== '' ? sr_content_by_slug($pdo, $slug) : null;
$account = sr_member_current_account($pdo);
$contentAdminPreview = false;
if (is_array($page) && (string) ($page['status'] ?? '') !== 'published') {
    if (
        in_array((string) ($page['status'] ?? ''), ['draft', 'scheduled'], true)
        && is_array($account)
        && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/content', 'view')
    ) {
        $contentAdminPreview = true;
    } else {
        $page = null;
    }
}
if (!is_array($page)) {
    sr_render_error(404, sr_t('content::action.error.content_not_found'));
}

$pageAccess = ['allowed' => true, 'charged' => false, 'message' => ''];
if (!$contentAdminPreview && sr_content_asset_access_required($page)) {
    $account = sr_member_require_login($pdo);
    $assetRequestToken = sr_post_string_without_truncation('asset_request_token', 32) ?? '';
    $pageAccess = sr_content_charge_view_access($pdo, $page, (int) $account['id'], sr_request_method() === 'POST', $assetRequestToken);
    if (!empty($pageAccess['charged'])) {
        sr_content_member_group_evaluate_after_activity($pdo, (int) $account['id']);
    }
    if (sr_request_method() === 'POST' && !empty($pageAccess['allowed'])) {
        if (sr_content_asset_policy_requires_confirmation((string) ($page['asset_charge_policy'] ?? 'once'))) {
            sr_content_mark_asset_confirmation_session('view', (int) $account['id'], (int) $page['id'], (string) ($pageAccess['confirmation_fingerprint'] ?? ''));
        }
        sr_redirect(sr_content_path((string) $page['slug']));
    }
}

$contentFiles = sr_content_files_for_content($pdo, (int) $page['id']);
$contentSettings = sr_content_settings($pdo);
$contentSecretCommentsEnabled = !empty($contentSettings['secret_comments_enabled']);
$contentSeriesContext = sr_content_series_for_content($pdo, (int) $page['id'], is_array($account) ? $account : null, $contentAdminPreview);
$contentComments = !empty($pageAccess['allowed']) ? sr_content_comments($pdo, (int) $page['id']) : [];
$contentQuizLinks = [];
if (sr_module_enabled($pdo, 'quiz') && is_file(SR_ROOT . '/modules/quiz/helpers.php')) {
    require_once SR_ROOT . '/modules/quiz/helpers.php';
    $contentQuizLinks = !empty($pageAccess['allowed']) ? sr_quiz_content_quizzes($pdo, (int) $page['id']) : [];
}
$contentCommentNotice = $_SESSION['sr_content_comment_notice'] ?? '';
$contentCommentErrors = $_SESSION['sr_content_comment_errors'] ?? [];
$contentCommentBody = $_SESSION['sr_content_comment_body'] ?? '';
$contentCommentIsSecret = !empty($_SESSION['sr_content_comment_is_secret']);
$contentCommentParentId = isset($_SESSION['sr_content_comment_parent_id']) ? (int) $_SESSION['sr_content_comment_parent_id'] : 0;
unset($_SESSION['sr_content_comment_notice'], $_SESSION['sr_content_comment_errors'], $_SESSION['sr_content_comment_body'], $_SESSION['sr_content_comment_is_secret'], $_SESSION['sr_content_comment_parent_id']);
$pageActionNotice = $_SESSION['sr_content_action_notice'] ?? '';
$pageActionErrors = $_SESSION['sr_content_action_errors'] ?? [];
unset($_SESSION['sr_content_action_notice'], $_SESSION['sr_content_action_errors']);

include SR_ROOT . '/modules/content/views/content.php';
