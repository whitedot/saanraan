<?php

$adminPageTitle = '콘텐츠 등록자 신청';
$adminPageSubtitle = '';
$adminContainerClass = 'admin-page-content-author-applications admin-ui-scope';
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/content/author-applications');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/content/author-applications')); ?>" class="filtering-form filtering filtering-plain ui-form-theme">
    <input type="hidden" name="filter" value="1">
    <div class="filtering-fields filtering-fields-fit">
        <div class="filtering-field">
            <span class="filtering-label">상태</span>
            <?php echo sr_admin_filter_toggle_group_html('content_author_application_filter_status', 'status', array_combine(sr_content_author_application_statuses(), array_map('sr_content_author_application_status_label', sr_content_author_application_statuses())), $applicationStatuses ?? ['pending'], '전체 신청'); ?>
        </div>
        <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
    </div>
</form>
<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">신청 목록</h2>
        </div>
    </div>
    <?php echo sr_admin_pagination_summary_html($contentAuthorApplicationPagination); ?>
    <div class="table-wrapper">
        <table class="table table-list">
            <caption class="sr-only">콘텐츠 등록자 신청 목록</caption>
            <thead>
                <tr><th>회원</th><th>상태</th><th>신청 사유</th><th>검토</th><th>신청일</th></tr>
            </thead>
            <tbody>
                <?php if (($contentAuthorApplications ?? []) === []) { ?>
                    <tr><td colspan="5" class="admin-empty-state">콘텐츠 등록자 신청이 없습니다.</td></tr>
                <?php } else { ?>
                    <?php foreach ($contentAuthorApplications as $application) { ?>
                        <tr>
                            <td class="admin-table-break">#<?php echo sr_e((string) (int) $application['account_id']); ?><br><?php echo sr_e((string) (($application['display_name'] ?? '') ?: ($application['email'] ?? ''))); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_content_author_application_status_label((string) $application['status'])); ?></td>
                            <td class="admin-table-break"><?php echo nl2br(sr_e((string) ($application['application_note'] ?? ''))); ?></td>
                            <td>
                                <?php if ((string) ($application['status'] ?? '') === 'pending') { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/content/author-applications')); ?>" class="form-actions">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/content/author-applications')); ?>">
                                        <input type="hidden" name="application_id" value="<?php echo sr_e((string) (int) $application['id']); ?>">
                                        <textarea name="note" rows="2" class="form-input" placeholder="검토 메모"></textarea>
                                        <button type="submit" name="intent" value="approve" class="btn btn-sm btn-solid-primary">승인</button>
                                        <button type="submit" name="intent" value="reject" class="btn btn-sm btn-outline-danger">반려</button>
                                    </form>
                                <?php } else { ?>
                                    <?php echo sr_e((string) ($application['review_note'] ?? '')); ?>
                                <?php } ?>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_content_time_html((string) ($application['created_at'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_pagination_html($contentAuthorApplicationPagination, '콘텐츠 등록자 신청 목록 페이지'); ?>
</section>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
