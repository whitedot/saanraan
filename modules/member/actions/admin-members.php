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
$memberCreateValues = sr_admin_member_create_default_values(is_array($site ?? null) ? $site : []);
$memberEditValues = [];
$memberSettings = sr_member_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/members', 'edit');

    $postResult = sr_admin_handle_members_post($pdo, $account, $allowedStatuses, is_array($site ?? null) ? $site : []);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
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
$statusCounts = sr_admin_member_status_counts($pdo);
$members = sr_admin_member_rows_with_public_hash($runtimeConfig, sr_admin_members($pdo, $statusFilter, $searchFilter));
$memberPagination = sr_admin_pagination_meta(count($members), sr_admin_list_pagination_per_page(sr_admin_settings($pdo)), sr_admin_page_number_from_request());
if ($memberAdminPage === 'members') {
    $members = array_slice($members, ((int) $memberPagination['page'] - 1) * (int) $memberPagination['per_page'], (int) $memberPagination['per_page']);
}
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
