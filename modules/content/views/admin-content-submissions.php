<?php

$adminPageTitle = '회원 제출 콘텐츠';
$adminPageSubtitle = '회원이 제출한 콘텐츠를 검수합니다.';
$adminContainerClass = 'admin-page-content-submissions admin-ui-scope';
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/content/submissions');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/content/submissions')); ?>" class="filtering-form filtering filtering-plain ui-form-theme">
    <div class="filtering-fields filtering-fields-fit">
        <div class="filtering-field">
            <span class="filtering-label">상태</span>
            <?php echo sr_admin_filter_toggle_group_html('content_submission_filter_status', 'status', array_combine(sr_content_submission_statuses(), array_map('sr_content_submission_status_label', sr_content_submission_statuses())), $submissionStatuses ?? [], '전체 상태'); ?>
        </div>
        <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
    </div>
</form>
<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">제출 목록</h2>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <caption class="sr-only">회원 제출 콘텐츠 목록</caption>
            <thead class="ui-table-head">
                <tr><th>ID</th><th>제목</th><th>작성자</th><th>그룹</th><th>상태</th><th>검수</th></tr>
            </thead>
            <tbody>
                <?php if ($adminSubmissions === []) { ?>
                    <tr>
                        <td colspan="6" class="admin-empty-state">회원 제출 콘텐츠가 없습니다.</td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($adminSubmissions as $submission) { ?>
                        <tr>
                            <td class="admin-table-nowrap">#<?php echo sr_e((string) (int) $submission['id']); ?></td>
                            <td class="admin-table-break"><?php echo sr_e((string) $submission['title']); ?><br><span class="text-muted"><?php echo sr_e((string) mb_substr((string) ($submission['body_text'] ?? ''), 0, 120)); ?></span></td>
                            <td class="admin-table-break"><?php echo sr_e((string) (($submission['author_display_name'] ?? '') ?: ($submission['author_email'] ?? ''))); ?></td>
                            <td class="admin-table-break"><?php echo sr_e((string) ($submission['group_title'] ?? '')); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_content_submission_status_label((string) $submission['review_status'])); ?></td>
                            <td>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/content/submissions')); ?>" class="admin-form-actions">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/content/submissions')); ?>">
                                    <input type="hidden" name="submission_id" value="<?php echo sr_e((string) (int) $submission['id']); ?>">
                                    <input type="text" name="review_note" value="<?php echo sr_e((string) ($submission['review_note'] ?? '')); ?>" class="form-input" placeholder="검수 메모">
                                    <button type="submit" name="intent" value="approve" class="btn btn-sm btn-solid-primary">승인</button>
                                    <button type="submit" name="intent" value="revision_requested" class="btn btn-sm btn-solid-light">수정 요청</button>
                                    <button type="submit" name="intent" value="rejected" class="btn btn-sm btn-outline-danger">반려</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
