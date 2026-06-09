<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/author-applications', 'view');
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/author-applications', 'edit');
    $errors = [];
    $notice = '';
    $intent = sr_post_string('intent', 30);
    $applicationId = sr_admin_post_positive_int('application_id');
    $note = sr_content_clean_text(sr_post_string('note', 2000), 2000);
    try {
        if (!in_array($intent, ['approve', 'reject'], true)) {
            throw new InvalidArgumentException('요청한 신청 처리 작업이 올바르지 않습니다.');
        }
        if ($applicationId < 1) {
            throw new InvalidArgumentException('처리할 신청을 선택하세요.');
        }

        sr_content_review_author_application($pdo, $applicationId, $intent === 'approve' ? 'approved' : 'rejected', (int) $account['id'], $note);
        $notice = $intent === 'approve' ? '콘텐츠 등록자 신청을 승인했습니다.' : '콘텐츠 등록자 신청을 반려했습니다.';
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
    sr_admin_flash_result(sr_admin_action_result($errors, $notice));
    sr_redirect(sr_admin_post_return_url('/admin/content/author-applications'));
}

$applicationStatusInput = array_key_exists('filter', $_GET) ? ($_GET['status'] ?? []) : ($_GET['status'] ?? ['pending']);
$applicationStatusValues = is_array($applicationStatusInput) ? $applicationStatusInput : [$applicationStatusInput];
$applicationStatuses = [];
foreach ($applicationStatusValues as $applicationStatusValue) {
    $applicationStatus = sr_content_clean_slug((string) $applicationStatusValue);
    if (in_array($applicationStatus, sr_content_author_application_statuses(), true)) {
        $applicationStatuses[] = $applicationStatus;
    }
}
$applicationStatuses = array_values(array_unique($applicationStatuses));
if ($applicationStatusInput === [] || $applicationStatusInput === '') {
    $applicationStatuses = [];
}
$contentAuthorApplications = sr_content_author_applications($pdo, $applicationStatuses);
include SR_ROOT . '/modules/content/views/admin-content-author-applications.php';
