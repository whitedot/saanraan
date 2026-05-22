<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/page/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$errors = [];
$notice = $_SESSION['sr_page_group_admin_notice'] ?? '';
unset($_SESSION['sr_page_group_admin_notice']);
$sessionErrors = $_SESSION['sr_page_group_admin_errors'] ?? [];
$sessionValues = $_SESSION['sr_page_group_admin_values'] ?? [];
unset($_SESSION['sr_page_group_admin_errors'], $_SESSION['sr_page_group_admin_values']);
if (is_array($sessionErrors)) {
    $errors = array_merge($errors, array_map('strval', $sessionErrors));
}

$pageGroupsPage = isset($pageGroupsPage) ? (string) $pageGroupsPage : 'list';
if (!in_array($pageGroupsPage, ['list', 'new', 'edit'], true)) {
    $pageGroupsPage = 'list';
}

$allowedGroupStatuses = sr_page_group_statuses();
$editPageGroup = null;
if ($pageGroupsPage === 'edit') {
    $groupId = (int) sr_get_string('id', 20);
    $editPageGroup = sr_page_group_by_id($pdo, $groupId);
    if (!is_array($editPageGroup)) {
        sr_render_error(404, '수정할 페이지 그룹을 찾을 수 없습니다.');
    }
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);
    $groupId = (int) sr_post_string('group_id', 20);
    $isUpdate = $intent === 'update_group';

    if (!in_array($intent, ['create_group', 'update_group'], true)) {
        $errors[] = '요청한 작업이 올바르지 않습니다.';
    }

    $existing = $isUpdate ? sr_page_group_by_id($pdo, $groupId) : null;
    if ($isUpdate && !is_array($existing)) {
        $errors[] = '수정할 페이지 그룹을 찾을 수 없습니다.';
    }

    $groupKey = $isUpdate && is_array($existing) ? (string) ($existing['group_key'] ?? '') : sr_page_clean_slug(sr_post_string('group_key', 60));
    $title = sr_page_clean_single_line(sr_post_string('title', 120), 120);
    $description = sr_page_clean_text(sr_post_string('description', 2000), 2000);
    $status = sr_post_string('status', 30);
    $sortOrder = sr_admin_post_int_in_range('sort_order', 0, 1000000);

    if (!$isUpdate && !sr_page_group_key_is_valid($groupKey)) {
        $errors[] = '그룹 key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    } elseif (!$isUpdate && sr_page_group_key_exists($pdo, $groupKey)) {
        $errors[] = '이미 사용 중인 그룹 key입니다.';
    }

    if ($title === '') {
        $errors[] = '그룹 이름을 입력하세요.';
    }

    if (!in_array($status, $allowedGroupStatuses, true)) {
        $errors[] = '상태 값이 올바르지 않습니다.';
    }

    if ($sortOrder === null) {
        $errors[] = '정렬 순서는 0 이상의 정수여야 합니다.';
        $sortOrder = 0;
    }

    $values = [
        'id' => $groupId,
        'group_key' => $groupKey,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'sort_order' => (int) $sortOrder,
    ];

    if ($errors !== []) {
        $_SESSION['sr_page_group_admin_errors'] = $errors;
        $_SESSION['sr_page_group_admin_values'] = $values;
        sr_redirect($isUpdate && $groupId > 0 ? '/admin/page-groups/edit?id=' . (string) $groupId : '/admin/page-groups/new');
    }

    if ($isUpdate) {
        sr_page_update_group($pdo, $groupId, $values);
        $savedGroupId = $groupId;
    } else {
        $savedGroupId = sr_page_create_group($pdo, $values);
    }

    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => $isUpdate ? 'page_group.updated' : 'page_group.created',
        'target_type' => 'page_group',
        'target_id' => (string) $savedGroupId,
        'result' => 'success',
        'message' => $isUpdate ? 'Page group updated.' : 'Page group created.',
        'metadata' => [
            'group_key' => $groupKey,
            'status' => $status,
        ],
    ]);

    $_SESSION['sr_page_group_admin_notice'] = $isUpdate ? '페이지 그룹을 저장했습니다.' : '페이지 그룹을 만들었습니다.';
    sr_redirect('/admin/page-groups/edit?id=' . (string) $savedGroupId);
}

$pageGroupFilters = sr_page_admin_group_filters();
$pageGroupStatusCounts = sr_page_admin_group_status_counts($pdo);
$pageGroups = $pageGroupsPage === 'list' ? sr_page_admin_group_list($pdo, $pageGroupFilters) : [];
$values = is_array($sessionValues) ? $sessionValues : [];

include SR_ROOT . '/modules/page/views/admin-page-groups.php';
