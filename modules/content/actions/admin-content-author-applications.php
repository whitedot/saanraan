<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/author-applications', 'view');
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/author-applications', 'edit');
    $intent = sr_post_string('intent', 30);
    $note = sr_content_clean_text(sr_post_string('note', 2000), 2000);
    try {
        if (!in_array($intent, ['approve', 'reject'], true)) {
            throw new InvalidArgumentException('요청한 신청 처리 작업이 올바르지 않습니다.');
        }

        sr_content_review_author_application($pdo, (int) sr_post_string('application_id', 20), $intent === 'approve' ? 'approved' : 'rejected', (int) $account['id'], $note);
        $notice = $intent === 'approve' ? '콘텐츠 등록자 신청을 승인했습니다.' : '콘텐츠 등록자 신청을 반려했습니다.';
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$applicationStatus = sr_get_string('status', 30);
$contentAuthorApplications = sr_content_author_applications($pdo, $applicationStatus !== '' ? $applicationStatus : 'pending');
include SR_ROOT . '/modules/content/views/admin-content-author-applications.php';
