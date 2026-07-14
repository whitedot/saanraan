<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
$contentSubmissionFlash = isset($_SESSION['sr_content_submission_flash']) && is_array($_SESSION['sr_content_submission_flash'])
    ? $_SESSION['sr_content_submission_flash']
    : [];
unset($_SESSION['sr_content_submission_flash']);
$errors = isset($contentSubmissionFlash['errors']) && is_array($contentSubmissionFlash['errors'])
    ? array_values(array_map('strval', $contentSubmissionFlash['errors']))
    : [];
$notice = (string) ($contentSubmissionFlash['notice'] ?? '');
$contentSubmissionFormValues = isset($contentSubmissionFlash['values']) && is_array($contentSubmissionFlash['values'])
    ? $contentSubmissionFlash['values']
    : [];
$allowedSubmissionGroups = sr_content_member_submission_allowed_groups($pdo, (int) $account['id']);
$submissionId = (int) sr_get_string('id', 20);
$editingSubmission = $submissionId > 0 ? sr_content_submission_by_id($pdo, $submissionId) : null;
if ($submissionId > 0 && (!is_array($editingSubmission) || (int) ($editingSubmission['author_account_id'] ?? 0) !== (int) $account['id'])) {
    sr_render_error(404, '제출본을 찾을 수 없습니다.');
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 20);
    $postedSubmissionId = (int) sr_post_string('submission_id', 20);
    $contentSubmissionFormValues = [
        'content_group_id' => (int) sr_post_string('content_group_id', 20),
        'title' => sr_post_string('title', 160),
        'summary' => sr_post_string('summary', 2000),
        'body_text' => sr_post_string_without_truncation('body_text', 200000),
    ];
    try {
        $savedSubmissionId = sr_content_save_member_submission($pdo, (int) $account['id'], $contentSubmissionFormValues, $postedSubmissionId, $intent === 'submit');
        $_SESSION['sr_content_submission_flash'] = [
            'errors' => [],
            'notice' => $intent === 'submit' ? '콘텐츠를 제출했습니다.' : '임시저장했습니다.',
            'values' => [],
        ];
        sr_redirect('/account/content?id=' . (string) $savedSubmissionId);
    } catch (Throwable $exception) {
        $_SESSION['sr_content_submission_flash'] = [
            'errors' => [(string) $exception->getMessage()],
            'notice' => '',
            'values' => $contentSubmissionFormValues,
        ];
        sr_redirect('/account/content' . ($submissionId > 0 ? '?id=' . (string) $submissionId : ''));
    }
}

$memberSubmissions = sr_content_member_submissions($pdo, (int) $account['id']);
include SR_ROOT . '/modules/content/views/account-content.php';
