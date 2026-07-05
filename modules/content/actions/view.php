<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/content/helpers/member-groups.php';
require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
if (sr_module_enabled($pdo, 'banner') && is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (sr_module_enabled($pdo, 'popup_layer') && is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
if (sr_request_method() === 'POST') {
    sr_require_csrf();
}

$slug = sr_content_slug_from_request_path();
$page = $slug !== '' ? sr_content_by_slug($pdo, $slug) : null;
$account = sr_member_current_account($pdo);
$contentAdminPreview = false;
$contentAdminPreviewRequested = sr_get_string('preview', 20) === 'admin';
if (is_array($page) && (string) ($page['status'] ?? '') !== 'published') {
    $contentAdminCanPreview = is_array($account)
        && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/content', 'view');
    if (
        in_array((string) ($page['status'] ?? ''), ['draft', 'scheduled'], true)
        && $contentAdminCanPreview
    ) {
        $contentAdminPreview = true;
    } else {
        $page = null;
    }
}
if (
    is_array($page)
    && !$contentAdminPreview
    && $contentAdminPreviewRequested
    && (string) ($page['status'] ?? '') === 'published'
    && is_array($account)
    && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/content', 'view')
) {
    $contentAdminPreview = true;
}
if (!is_array($page)) {
    sr_render_error(404, sr_t('content::action.error.content_not_found'));
}

$contentSettings = sr_content_settings($pdo);
$contentSecretCommentsEnabled = !empty($contentSettings['secret_comments_enabled']);
if (!$contentAdminPreview && (!empty($contentSettings['identity_content_view_required']) || !empty($contentSettings['identity_content_view_adult_required']))) {
    $account = sr_member_require_login($pdo);
    if (!sr_module_enabled($pdo, 'identity_verification') || !is_file(SR_ROOT . '/modules/identity_verification/helpers.php')) {
        sr_render_error(403, '이 콘텐츠를 보려면 본인확인이 필요합니다. 본인확인 기능을 사용할 수 없어 열람할 수 없습니다.');
    }
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
    $contentIdentityReturnUrl = sr_content_path((string) $page['slug']);
    if (!empty($contentSettings['identity_content_view_required'])) {
        $contentIdentityPolicy = sr_identity_verification_requirement_policy($pdo, (int) $account['id'], 'content.view', 'required', $contentIdentityReturnUrl);
        if (empty($contentIdentityPolicy['satisfied'])) {
            sr_render_error(403, '이 콘텐츠를 보려면 본인확인이 필요합니다. 본인확인을 완료한 뒤 다시 열어 주세요.');
        }
    }
    if (!empty($contentSettings['identity_content_view_adult_required'])) {
        $contentAdultAvailable = sr_identity_verification_available($pdo, 'content.view.adult');
        if (!$contentAdultAvailable || !sr_identity_verification_account_satisfies_adult($pdo, (int) $account['id'], 'content.view.adult')) {
            sr_render_error(403, '이 콘텐츠를 보려면 성인 본인확인이 필요합니다. 성인 본인확인을 완료한 뒤 다시 열어 주세요.');
        }
    }
}

$pageAccess = ['allowed' => true, 'charged' => false, 'message' => ''];
if (!$contentAdminPreview && sr_content_asset_access_required($page)) {
    $account = sr_member_require_login($pdo);
    $assetRequestToken = sr_post_string_without_truncation('asset_request_token', 64) ?? '';
    $assetConfirmedPost = sr_request_method() === 'POST' && sr_post_string('asset_confirm', 1) === '1';
    $assetExchangeConfirmed = $assetConfirmedPost && sr_post_string('asset_exchange_confirm', 1) === '1';
    $couponIssueIdValue = sr_request_method() === 'POST' ? (sr_post_string('coupon_issue_id', 20) ?? '') : '';
    $couponIssueId = $assetConfirmedPost && preg_match('/\A[1-9][0-9]*\z/', $couponIssueIdValue) === 1 ? (int) $couponIssueIdValue : 0;
    $pageAccess = sr_content_charge_view_access($pdo, $page, (int) $account['id'], sr_request_method() === 'POST', $assetRequestToken, $couponIssueId, true, $assetConfirmedPost, $assetExchangeConfirmed);
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
if (!$contentAdminPreview && !empty($pageAccess['allowed']) && sr_content_should_count_view((int) $page['id'])) {
    sr_content_increment_view_count($pdo, (int) $page['id']);
    $page['view_count'] = (int) ($page['view_count'] ?? 0) + 1;
}

$contentFiles = !empty($pageAccess['allowed']) ? sr_content_files_for_content($pdo, (int) $page['id']) : [];
$contentImageFiles = [];
foreach ($contentFiles as $contentFile) {
    if (!sr_content_file_is_image($contentFile) || sr_content_file_download_required($contentFile)) {
        continue;
    }

    $contentFile['original_url'] = sr_content_file_public_url($contentFile, (int) $page['id'], true);
    $contentFile['thumbnail_url'] = sr_content_file_view_image_thumbnail_url($pdo, $contentFile, (int) $page['id']);
    if ((string) ($contentFile['original_url'] ?? '') !== '' && (string) ($contentFile['thumbnail_url'] ?? '') !== '') {
        $contentImageFiles[] = $contentFile;
    }
}
$contentSeriesContext = sr_content_series_for_content($pdo, (int) $page['id'], is_array($account) ? $account : null, $contentAdminPreview);
$contentComments = !empty($pageAccess['allowed']) ? sr_content_comments($pdo, (int) $page['id']) : [];
$contentCommentNotice = $_SESSION['sr_content_comment_notice'] ?? '';
$contentCommentErrors = $_SESSION['sr_content_comment_errors'] ?? [];
$contentCommentBody = $_SESSION['sr_content_comment_body'] ?? '';
$contentCommentIsSecret = !empty($_SESSION['sr_content_comment_is_secret']);
$contentCommentParentId = isset($_SESSION['sr_content_comment_parent_id']) ? (int) $_SESSION['sr_content_comment_parent_id'] : 0;
unset($_SESSION['sr_content_comment_notice'], $_SESSION['sr_content_comment_errors'], $_SESSION['sr_content_comment_body'], $_SESSION['sr_content_comment_is_secret'], $_SESSION['sr_content_comment_parent_id']);
$pageActionNotice = $_SESSION['sr_content_action_notice'] ?? '';
$pageActionErrors = $_SESSION['sr_content_action_errors'] ?? [];
unset($_SESSION['sr_content_action_notice'], $_SESSION['sr_content_action_errors']);
$memberFollowFeedback = isset($_SESSION['sr_member_follow_feedback']) && is_array($_SESSION['sr_member_follow_feedback'])
    ? $_SESSION['sr_member_follow_feedback']
    : ['notice' => '', 'errors' => []];
unset($_SESSION['sr_member_follow_feedback']);
$contentLayoutSettings = $contentSettings;

$contentThemeFallbackViewFile = SR_ROOT . '/modules/content/views/content.php';
include sr_content_public_view_file($pdo, $contentLayoutSettings, 'content.php');
