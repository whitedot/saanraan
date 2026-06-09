<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/authors', 'view');
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/authors', 'edit');
    $errors = [];
    $notice = '';
    $targetAccountId = (int) sr_post_string('account_id', 20);
    $status = in_array(sr_post_string('status', 20), ['allowed', 'blocked'], true) ? sr_post_string('status', 20) : 'allowed';
    $reviewOverride = in_array(sr_post_string('review_required_override', 20), ['inherit', 'required', 'exempt'], true) ? sr_post_string('review_required_override', 20) : 'inherit';
    $note = sr_content_clean_text(sr_post_string('note', 2000), 2000);
    try {
        if ($targetAccountId < 1) {
            throw new InvalidArgumentException('회원 ID를 입력하세요.');
        }
        if (sr_member_find_by_id($pdo, $targetAccountId) === null) {
            throw new InvalidArgumentException('회원을 찾을 수 없습니다.');
        }

        $now = sr_now();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_content_author_permissions
                (account_id, status, review_required_override, note, created_by, updated_by, created_at, updated_at)
             VALUES
                (:account_id, :status, :review_required_override, :note, :created_by, :updated_by, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                review_required_override = VALUES(review_required_override),
                note = VALUES(note),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'account_id' => $targetAccountId,
            'status' => $status,
            'review_required_override' => $reviewOverride,
            'note' => $note,
            'created_by' => (int) $account['id'],
            'updated_by' => (int) $account['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $notice = '작성자 승인을 저장했습니다.';
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
    if ($errors === []) {
        sr_admin_flash_result(sr_admin_action_result([], $notice));
        sr_redirect(sr_admin_post_return_url('/admin/content/authors'));
    }
}

$authorStatusInput = $_GET['status'] ?? [];
$authorStatusValues = is_array($authorStatusInput) ? $authorStatusInput : [$authorStatusInput];
$authorStatuses = [];
foreach ($authorStatusValues as $authorStatusValue) {
    $authorStatus = sr_content_clean_slug((string) $authorStatusValue);
    if (in_array($authorStatus, ['allowed', 'blocked'], true)) {
        $authorStatuses[] = $authorStatus;
    }
}
$authorStatuses = array_values(array_unique($authorStatuses));

$authorReviewInput = $_GET['review_required_override'] ?? [];
$authorReviewValues = is_array($authorReviewInput) ? $authorReviewInput : [$authorReviewInput];
$authorReviewOverrides = [];
foreach ($authorReviewValues as $authorReviewValue) {
    $authorReviewOverride = sr_content_clean_slug((string) $authorReviewValue);
    if (in_array($authorReviewOverride, ['inherit', 'required', 'exempt'], true)) {
        $authorReviewOverrides[] = $authorReviewOverride;
    }
}
$authorReviewOverrides = array_values(array_unique($authorReviewOverrides));

$contentAuthorPermissions = sr_content_author_permissions($pdo, $authorStatuses, $authorReviewOverrides);
include SR_ROOT . '/modules/content/views/admin-content-authors.php';
