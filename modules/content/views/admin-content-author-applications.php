<?php

$adminPageTitle = '콘텐츠 등록자 신청';
$adminPageSubtitle = '회원의 콘텐츠 등록자 신청을 검토합니다.';
$adminContainerClass = 'admin-page-content-author-applications admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
<section class="admin-card card">
    <form method="get" action="<?php echo sr_e(sr_url('/admin/content/author-applications')); ?>" class="filtering-form filtering filtering-plain ui-form-theme">
        <select name="status" class="form-select">
            <option value="">대기 신청</option>
            <?php foreach (sr_content_author_application_statuses() as $statusOption) { ?>
                <option value="<?php echo sr_e($statusOption); ?>"<?php echo (string) ($applicationStatus ?? 'pending') === $statusOption ? ' selected' : ''; ?>><?php echo sr_e(sr_content_author_application_status_label($statusOption)); ?></option>
            <?php } ?>
        </select>
        <button type="submit" class="btn btn-solid-primary">검색</button>
    </form>
</section>
<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">신청 목록</h2>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <caption class="sr-only">콘텐츠 등록자 신청 목록</caption>
            <thead class="ui-table-head">
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
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/content/author-applications')); ?>" class="admin-form-actions">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="application_id" value="<?php echo sr_e((string) (int) $application['id']); ?>">
                                        <input type="text" name="note" value="" class="form-input" placeholder="검토 메모">
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
</section>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
