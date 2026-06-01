<?php include SR_ROOT . '/modules/admin/views/layout-header.php'; ?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
<section class="admin-card card admin-ui-scope">
    <h2>커뮤니티 시리즈</h2>
    <div class="table-wrapper">
        <table class="table">
            <thead><tr><th>제목</th><th>게시판</th><th>소유자</th><th>상태</th><th>공개</th><th>관리</th></tr></thead>
            <tbody>
                <?php foreach ($seriesList as $series) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $series['title']); ?></td>
                        <td><?php echo sr_e((string) $series['board_title']); ?></td>
                        <td><?php echo sr_e((string) ($series['owner_display_name'] ?? '')); ?></td>
                        <td><?php echo sr_e((string) $series['status']); ?></td>
                        <td><?php echo sr_e((string) $series['visibility']); ?></td>
                        <td>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/series')); ?>" class="admin-inline-form">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="series_id" value="<?php echo sr_e((string) $series['id']); ?>">
                                <select name="status" class="form-select"><?php foreach (sr_community_series_statuses() as $status) { ?><option value="<?php echo sr_e($status); ?>"<?php echo (string) $series['status'] === $status ? ' selected' : ''; ?>><?php echo sr_e($status); ?></option><?php } ?></select>
                                <select name="visibility" class="form-select"><?php foreach (sr_community_series_visibility_values() as $visibility) { ?><option value="<?php echo sr_e($visibility); ?>"<?php echo (string) $series['visibility'] === $visibility ? ' selected' : ''; ?>><?php echo sr_e($visibility); ?></option><?php } ?></select>
                                <input type="text" name="admin_note" maxlength="2000" value="<?php echo sr_e((string) ($series['admin_note'] ?? '')); ?>" class="form-input">
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
