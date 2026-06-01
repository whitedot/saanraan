<?php include SR_ROOT . '/modules/admin/views/layout-header.php'; ?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
<section class="admin-card card admin-ui-scope">
    <h2>콘텐츠 시리즈</h2>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/content/series')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="create">
        <div class="admin-form-row"><label class="form-label" for="content_series_key_new">key <span class="sr-required-label">(필수)</span></label><div class="admin-form-field"><input id="content_series_key_new" name="series_key" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" required data-admin-key-input class="form-input"></div></div>
        <div class="admin-form-row"><label class="form-label" for="content_series_title_new">제목 <span class="sr-required-label">(필수)</span></label><div class="admin-form-field"><input id="content_series_title_new" name="title" maxlength="160" required class="form-input form-control-full"></div></div>
        <div class="admin-form-row"><label class="form-label" for="content_series_description_new">설명</label><div class="admin-form-field"><textarea id="content_series_description_new" name="description" rows="2" class="form-textarea"></textarea></div></div>
        <div class="admin-form-row"><label class="form-label" for="content_series_status_new">상태 <span class="sr-required-label">(필수)</span></label><div class="admin-form-field"><select id="content_series_status_new" name="status" class="form-select" required><option value="active">active</option><option value="pending">pending</option><option value="hidden">hidden</option><option value="archived">archived</option><option value="deleted">deleted</option></select></div></div>
        <div class="admin-form-row"><label class="form-label" for="content_series_visibility_new">공개 범위 <span class="sr-required-label">(필수)</span></label><div class="admin-form-field"><select id="content_series_visibility_new" name="visibility" class="form-select" required><option value="public">public</option><option value="member">member</option><option value="private">private</option></select></div></div>
        <div class="admin-form-row"><label class="form-label" for="content_series_sort_new">정렬</label><div class="admin-form-field"><input id="content_series_sort_new" type="number" name="sort_order" min="0" max="1000000" value="0" class="form-input"></div></div>
        <button type="submit" class="btn btn-solid-primary">추가</button>
    </form>
</section>
<section class="admin-card card admin-ui-scope">
    <h2>목록</h2>
    <div class="table-wrapper">
        <table class="table">
            <thead><tr><th>key</th><th>제목</th><th>상태</th><th>공개</th><th>정렬</th><th>관리</th></tr></thead>
            <tbody>
                <?php foreach ($seriesList as $series) { ?>
                    <tr>
                        <td><code><?php echo sr_e((string) $series['series_key']); ?></code></td>
                        <td colspan="5">
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/content/series')); ?>" class="admin-inline-form">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="update">
                                <input type="hidden" name="series_id" value="<?php echo sr_e((string) $series['id']); ?>">
                                <input type="hidden" name="series_key" value="<?php echo sr_e((string) $series['series_key']); ?>">
                                <input type="text" name="title" value="<?php echo sr_e((string) $series['title']); ?>" maxlength="160" required class="form-input">
                                <input type="text" name="description" value="<?php echo sr_e((string) ($series['description'] ?? '')); ?>" maxlength="2000" class="form-input">
                                <select name="status" class="form-select"><?php foreach (sr_content_series_statuses() as $status) { ?><option value="<?php echo sr_e($status); ?>"<?php echo (string) $series['status'] === $status ? ' selected' : ''; ?>><?php echo sr_e($status); ?></option><?php } ?></select>
                                <select name="visibility" class="form-select"><?php foreach (sr_content_series_visibility_values() as $visibility) { ?><option value="<?php echo sr_e($visibility); ?>"<?php echo (string) $series['visibility'] === $visibility ? ' selected' : ''; ?>><?php echo sr_e($visibility); ?></option><?php } ?></select>
                                <input type="number" name="sort_order" value="<?php echo sr_e((string) $series['sort_order']); ?>" min="0" max="1000000" class="form-input">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">저장</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
