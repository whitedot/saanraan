<?php

$adminPageTitle = '회원 제출 콘텐츠';
$adminPageSubtitle = '회원이 제출한 콘텐츠를 검수합니다.';
$adminContainerClass = 'admin-page-content-submissions admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/content/submissions')); ?>" class="filtering-form filtering filtering-plain ui-form-theme">
    <div class="filtering-fields filtering-fields-fit">
        <div class="filtering-field">
            <label for="content_submission_filter_status" class="filtering-label">상태</label>
            <select id="content_submission_filter_status" name="status" class="form-select filtering-input">
                <option value="">전체 상태</option>
                <?php foreach (sr_content_submission_statuses() as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($submissionStatus ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_content_submission_status_label($status)); ?></option>
                <?php } ?>
            </select>
        </div>
        <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
    </div>
</form>
<section class="admin-card card">
    <h2>제출 목록</h2>
    <table class="table">
        <thead><tr><th>ID</th><th>제목</th><th>작성자</th><th>그룹</th><th>상태</th><th>검수</th></tr></thead>
        <tbody>
            <?php foreach ($adminSubmissions as $submission) { ?>
                <tr>
                    <td>#<?php echo sr_e((string) (int) $submission['id']); ?></td>
                    <td><?php echo sr_e((string) $submission['title']); ?><br><span class="text-muted"><?php echo sr_e((string) mb_substr((string) ($submission['body_text'] ?? ''), 0, 120)); ?></span></td>
                    <td><?php echo sr_e((string) (($submission['author_display_name'] ?? '') ?: ($submission['author_email'] ?? ''))); ?></td>
                    <td><?php echo sr_e((string) ($submission['group_title'] ?? '')); ?></td>
                    <td><?php echo sr_e(sr_content_submission_status_label((string) $submission['review_status'])); ?></td>
                    <td>
                        <form method="post" action="<?php echo sr_e(sr_url('/admin/content/submissions')); ?>" class="admin-form-actions">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="submission_id" value="<?php echo sr_e((string) (int) $submission['id']); ?>">
                            <input type="text" name="review_note" value="<?php echo sr_e((string) ($submission['review_note'] ?? '')); ?>" class="form-input" placeholder="검수 메모">
                            <button type="submit" name="intent" value="approve" class="btn btn-sm btn-solid-primary">승인</button>
                            <button type="submit" name="intent" value="revision_requested" class="btn btn-sm btn-solid-light">수정 요청</button>
                            <button type="submit" name="intent" value="rejected" class="btn btn-sm btn-outline-danger">반려</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</section>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
