<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/submissions', 'view');
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/submissions', 'edit');
    $errors = [];
    $notice = '';
    $submissionId = (int) sr_post_string('submission_id', 20);
    $intent = sr_post_string('intent', 30);
    $note = sr_content_clean_text(sr_post_string('review_note', 2000), 2000);
    try {
        if ($intent === 'approve') {
            sr_content_approve_submission($pdo, $submissionId, (int) $account['id'], $note);
            $notice = '제출 콘텐츠를 승인했습니다.';
        } elseif (in_array($intent, ['revision_requested', 'rejected', 'cancelled'], true)) {
            $submission = sr_content_submission_by_id($pdo, $submissionId);
            if (!is_array($submission)) {
                throw new InvalidArgumentException('제출본을 찾을 수 없습니다.');
            }

            $currentStatus = (string) ($submission['review_status'] ?? '');
            if ($currentStatus === 'approved' || (int) ($submission['content_id'] ?? 0) > 0) {
                throw new InvalidArgumentException('이미 승인된 제출본의 검수 상태는 되돌릴 수 없습니다.');
            }
            if (in_array($intent, ['revision_requested', 'rejected'], true) && $currentStatus !== 'pending_review') {
                throw new InvalidArgumentException('대기 중인 제출본만 수정 요청 또는 반려할 수 있습니다.');
            }
            if ($intent === 'cancelled' && !in_array($currentStatus, ['member_draft', 'pending_review', 'revision_requested', 'rejected'], true)) {
                throw new InvalidArgumentException('취소할 수 없는 제출 상태입니다.');
            }

            $now = sr_now();
            $stmt = $pdo->prepare(
                'UPDATE sr_content_submissions
                 SET review_status = :review_status,
                     review_note = :review_note,
                     reviewed_by = :reviewed_by,
                     reviewed_at = :reviewed_at,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'review_status' => $intent,
                'review_note' => $note,
                'reviewed_by' => (int) $account['id'],
                'reviewed_at' => $now,
                'updated_at' => $now,
                'id' => $submissionId,
            ]);
            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('검수 상태를 저장하지 못했습니다.');
            }
            $notice = '검수 상태를 저장했습니다.';
        } else {
            $errors[] = '요청한 검수 작업이 올바르지 않습니다.';
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
    sr_admin_flash_result(sr_admin_action_result($errors, $notice));
    sr_redirect(sr_admin_post_return_url('/admin/content/submissions'));
}

$submissionStatusInput = $_GET['status'] ?? [];
$submissionStatusValues = is_array($submissionStatusInput) ? $submissionStatusInput : [$submissionStatusInput];
$submissionStatuses = [];
foreach ($submissionStatusValues as $submissionStatusValue) {
    $submissionStatus = sr_content_clean_slug((string) $submissionStatusValue);
    if (in_array($submissionStatus, sr_content_submission_statuses(), true)) {
        $submissionStatuses[] = $submissionStatus;
    }
}
$submissionStatuses = array_values(array_unique($submissionStatuses));
$adminSubmissions = sr_content_admin_submissions($pdo, $submissionStatuses);
include SR_ROOT . '/modules/content/views/admin-content-submissions.php';
