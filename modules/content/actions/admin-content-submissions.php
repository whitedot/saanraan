<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/submissions', 'view');
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/submissions', 'edit');
    $submissionId = (int) sr_post_string('submission_id', 20);
    $intent = sr_post_string('intent', 30);
    $note = sr_content_clean_text(sr_post_string('review_note', 2000), 2000);
    try {
        if ($intent === 'approve') {
            sr_content_approve_submission($pdo, $submissionId, (int) $account['id'], $note);
            $notice = '제출 콘텐츠를 승인했습니다.';
        } elseif (in_array($intent, ['revision_requested', 'rejected', 'cancelled'], true)) {
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
                'reviewed_at' => sr_now(),
                'updated_at' => sr_now(),
                'id' => $submissionId,
            ]);
            $notice = '검수 상태를 저장했습니다.';
        } else {
            $errors[] = '요청한 검수 작업이 올바르지 않습니다.';
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$submissionStatus = sr_get_string('status', 30);
$adminSubmissions = sr_content_admin_submissions($pdo, $submissionStatus);
include SR_ROOT . '/modules/content/views/admin-content-submissions.php';
