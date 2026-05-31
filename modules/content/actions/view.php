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
$contentAdminPreview = false;
if (is_array($page) && (string) ($page['status'] ?? '') !== 'published') {
    $account = sr_member_current_account($pdo);
    if (
        (string) ($page['status'] ?? '') === 'draft'
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
    $pageAccess = sr_content_charge_view_access($pdo, $page, (int) $account['id'], sr_request_method() === 'POST');
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
$pageActionNotice = $_SESSION['sr_content_action_notice'] ?? '';
$pageActionErrors = $_SESSION['sr_content_action_errors'] ?? [];
unset($_SESSION['sr_content_action_notice'], $_SESSION['sr_content_action_errors']);

include SR_ROOT . '/modules/content/views/content.php';
