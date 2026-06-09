<?php

$adminPageTitle = '콘텐츠 작성자 승인';
$adminPageSubtitle = '개별 회원 콘텐츠 제출 권한을 관리합니다.';
$adminContainerClass = 'admin-page-content-authors admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/content/authors')); ?>" class="filtering-form filtering filtering-plain ui-form-theme">
    <div class="filtering-fields filtering-fields-fit">
        <div class="filtering-field">
            <span class="filtering-label">상태</span>
            <?php echo sr_admin_filter_toggle_group_html('content_author_filter_status', 'status', ['allowed' => sr_content_author_permission_status_label('allowed'), 'blocked' => sr_content_author_permission_status_label('blocked')], $authorStatuses ?? [], '전체 상태'); ?>
        </div>
        <div class="filtering-field">
            <span class="filtering-label">검수</span>
            <?php echo sr_admin_filter_toggle_group_html('content_author_filter_review_required_override', 'review_required_override', ['inherit' => sr_content_author_review_override_label('inherit'), 'required' => sr_content_author_review_override_label('required'), 'exempt' => sr_content_author_review_override_label('exempt')], $authorReviewOverrides ?? [], '전체 검수'); ?>
        </div>
        <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
    </div>
</form>
<section class="admin-card card">
    <h2>작성자 승인 추가/수정</h2>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/content/authors')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/content/authors')); ?>">
        <div class="admin-form-row">
            <label class="form-label" for="content_author_account_id">회원 ID <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field"><input id="content_author_account_id" type="number" min="1" name="account_id" class="form-input"></div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="content_author_status">상태</label>
            <div class="admin-form-field">
                <select id="content_author_status" name="status" class="form-select">
                    <option value="allowed">허용</option>
                    <option value="blocked">차단</option>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="content_author_review_required_override">검수 예외</label>
            <div class="admin-form-field">
                <select id="content_author_review_required_override" name="review_required_override" class="form-select">
                    <option value="inherit">상속</option>
                    <option value="required">항상 검수</option>
                    <option value="exempt">검수 면제</option>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="content_author_note">메모</label>
            <div class="admin-form-field"><textarea id="content_author_note" name="note" rows="3" class="form-input"></textarea></div>
        </div>
        <div class="admin-form-actions"><button type="submit" class="btn btn-solid-primary">저장</button></div>
    </form>
</section>
<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">승인 목록</h2>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <caption class="sr-only">콘텐츠 작성자 승인 목록</caption>
            <thead class="ui-table-head">
                <tr><th>회원</th><th>상태</th><th>검수</th><th>메모</th><th>수정일</th></tr>
            </thead>
            <tbody>
                <?php if ($contentAuthorPermissions === []) { ?>
                    <tr><td colspan="5" class="admin-empty-state">콘텐츠 작성자 승인이 없습니다.</td></tr>
                <?php } else { ?>
                    <?php foreach ($contentAuthorPermissions as $permission) { ?>
                        <tr>
                            <td class="admin-table-break">#<?php echo sr_e((string) (int) $permission['account_id']); ?><br><?php echo sr_e((string) (($permission['display_name'] ?? '') ?: ($permission['email'] ?? ''))); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_content_author_permission_status_label((string) $permission['status'])); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_content_author_review_override_label((string) $permission['review_required_override'])); ?></td>
                            <td class="admin-table-break"><?php echo sr_e((string) ($permission['note'] ?? '')); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_content_time_html((string) ($permission['updated_at'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
