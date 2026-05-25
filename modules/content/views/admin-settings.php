<?php

$adminPageTitle = '콘텐츠 환경설정';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/content/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <section class="admin-card card">
        <div class="admin-form-row">
            <label class="form-label" for="content_admin_settings_editor">에디터 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="content_admin_settings_editor" name="editor" class="form-select" required>
                    <?php foreach ($editorOptions as $editorKey => $editorLabel) { ?>
                        <option value="<?php echo sr_e((string) $editorKey); ?>"<?php echo (string) ($settings['editor'] ?? 'textarea') === (string) $editorKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $editorLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
