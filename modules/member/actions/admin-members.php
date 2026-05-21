<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$allowedStatuses = sr_admin_member_allowed_statuses();
$errors = [];
$notice = '';
$memberAdminPage = isset($memberAdminPage) ? (string) $memberAdminPage : 'members';
if (!in_array($memberAdminPage, ['members', 'create_form', 'edit_form'], true)) {
    $memberAdminPage = 'members';
}
$memberCreateValues = sr_admin_member_create_default_values(is_array($site ?? null) ? $site : []);
$memberEditValues = [];
$memberSettings = sr_member_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
    sr_require_csrf();

    $postResult = sr_admin_handle_members_post($pdo, $account, $allowedStatuses, is_array($site ?? null) ? $site : []);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    if (isset($postResult['create_values']) && is_array($postResult['create_values'])) {
        $memberCreateValues = $postResult['create_values'];
    }
    if (isset($postResult['edit_values']) && is_array($postResult['edit_values'])) {
        $memberEditValues = $postResult['edit_values'];
    }
}

$statusFilter = sr_admin_member_status_filter($allowedStatuses);
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$searchFilter = sr_admin_member_search_filter($pdo, $runtimeConfig);
$statusCounts = sr_admin_member_status_counts($pdo);
$members = sr_admin_member_rows_with_public_hash($runtimeConfig, sr_admin_members($pdo, $statusFilter, $searchFilter));
$editMember = null;
if ($memberAdminPage === 'edit_form') {
    $editMemberIdValue = sr_get_string('edit_id', 20);
    if ($editMemberIdValue === '') {
        $editMemberIdValue = sr_get_string('id', 20);
    }
    $editMemberId = preg_match('/\A[1-9][0-9]*\z/', $editMemberIdValue) === 1 ? (int) $editMemberIdValue : 0;
    $editMember = sr_admin_member_by_id($pdo, $editMemberId);
    if (is_array($editMember) && $memberEditValues === []) {
        $memberEditValues = $editMember;
    }
    if (!is_array($editMember) && $errors === []) {
        $errors[] = '수정할 회원을 찾을 수 없습니다.';
    }
}

include SR_ROOT . '/modules/member/views/admin-members.php';
