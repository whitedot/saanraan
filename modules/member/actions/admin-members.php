<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/members', 'view');

$allowedStatuses = sr_admin_member_allowed_statuses();
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$memberAdminPage = isset($memberAdminPage) ? (string) $memberAdminPage : 'members';
if (!in_array($memberAdminPage, ['members', 'create_form', 'edit_form'], true)) {
    $memberAdminPage = 'members';
}
if (sr_request_method() === 'GET' && in_array($memberAdminPage, ['create_form', 'edit_form'], true)) {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/members', 'edit');
}
$memberCreateValues = sr_admin_member_create_default_values(is_array($site ?? null) ? $site : []);
$memberEditValues = [];
$memberSettings = sr_member_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/members', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent === 'batch_revoke_sessions') {
        $postResult = sr_admin_handle_member_batch_revoke_sessions_post($pdo, $account);
    } else {
        $postResult = sr_admin_handle_members_post($pdo, $account, $allowedStatuses, is_array($site ?? null) ? $site : []);
    }
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    if ($intent === 'batch_revoke_sessions') {
        sr_admin_flash_result(sr_admin_action_result($errors, $notice));
        sr_redirect(sr_admin_post_return_url('/admin/members'));
    }
    if (in_array($intent, ['status', 'revoke_sessions', 'evaluate_groups'], true)) {
        sr_admin_flash_result(sr_admin_action_result($errors, $notice));
        sr_redirect(sr_admin_post_return_url('/admin/members'));
    }
    if ($errors === [] && (int) ($postResult['created_account_id'] ?? 0) > 0) {
        sr_admin_flash_result(sr_admin_action_result([], $notice));
        sr_redirect('/admin/members');
    }
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
$memberSort = sr_admin_sort_from_request(sr_admin_member_sort_options(), sr_admin_member_default_sort());
$statusCounts = sr_admin_member_status_counts($pdo);
$memberPagination = sr_admin_pagination_from_total($pdo, $memberAdminPage === 'members' ? sr_admin_member_count($pdo, $statusFilter, $searchFilter) : 0);
$members = $memberAdminPage === 'members'
    ? sr_admin_member_rows_with_public_hash($runtimeConfig, sr_admin_members($pdo, $statusFilter, $searchFilter, (int) $memberPagination['per_page'], sr_admin_pagination_offset($memberPagination), $memberSort))
    : [];
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
        $errors[] = sr_t('member::action.admin.member_edit_not_found');
    }
}

include SR_ROOT . '/modules/member/views/admin-members.php';
