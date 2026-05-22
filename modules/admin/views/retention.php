<?php

$adminPageTitle = sr_t('admin::ui.text.fbef5b12');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/retention')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="settings">
    <section class="admin-card admin-list-card card">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.7a617d5f')); ?></h2>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_auth_logs_days"><?php echo sr_e(sr_t('admin::ui.text.6cc455be')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="admin_retention_auth_logs_days" type="number" name="auth_logs_days" value="<?php echo sr_e((string) $values['auth_logs_days']); ?>" class="form-input" min="1" max="3650" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_audit_logs_days"><?php echo sr_e(sr_t('admin::ui.admin.e6a220a3')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="admin_retention_audit_logs_days" type="number" name="audit_logs_days" value="<?php echo sr_e((string) $values['audit_logs_days']); ?>" class="form-input" min="1" max="3650" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_used_tokens_days"><?php echo sr_e(sr_t('admin::ui.active.e5845fb2')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="admin_retention_used_tokens_days" type="number" name="used_tokens_days" value="<?php echo sr_e((string) $values['used_tokens_days']); ?>" class="form-input" min="1" max="3650" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_sessions_days"><?php echo sr_e(sr_t('admin::ui.text.48581a76')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="admin_retention_sessions_days" type="number" name="sessions_days" value="<?php echo sr_e((string) $values['sessions_days']); ?>" class="form-input" min="1" max="3650" required>
            </div>
        </div>
        <?php if ($hasNotificationTables) { ?>
            <div class="admin-form-row">
                <label class="form-label" for="admin_retention_notifications_days"><?php echo sr_e(sr_t('admin::ui.notification.9bf7948a')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="admin_retention_notifications_days" type="number" name="notifications_days" value="<?php echo sr_e((string) $values['notifications_days']); ?>" class="form-input" min="1" max="3650" required>
                </div>
            </div>
        <?php } else { ?>
            <input type="hidden" name="notifications_days" value="<?php echo sr_e((string) $values['notifications_days']); ?>">
        <?php } ?>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_module_backups_days"><?php echo sr_e(sr_t('admin::ui.text.b4268fef')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="admin_retention_module_backups_days" type="number" name="module_backups_days" value="<?php echo sr_e((string) $values['module_backups_days']); ?>" class="form-input" min="1" max="3650" required>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.642180ad')); ?></span>
            <div class="admin-form-field">
                <label class="admin-form-check form-label" for="modules_admin_retention_auto_cleanup_enabled">
                                    <input id="modules_admin_retention_auto_cleanup_enabled" type="checkbox" name="auto_cleanup_enabled" value="1" class="form-checkbox"<?php echo (int) $values['auto_cleanup_enabled'] === 1 ? ' checked' : ''; ?>>
                                    <?php echo sr_admin_choice_label_html(sr_t('admin::ui.active.93c558d7')); ?>
                                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_auto_cleanup_interval_hours"><?php echo sr_e(sr_t('admin::ui.text.3a13e62b')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="admin_retention_auto_cleanup_interval_hours" type="number" name="auto_cleanup_interval_hours" value="<?php echo sr_e((string) $values['auto_cleanup_interval_hours']); ?>" class="form-input" min="1" max="720" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_auto_cleanup_batch_size"><?php echo sr_e(sr_t('admin::ui.text.df1bcbf1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="admin_retention_auto_cleanup_batch_size" type="number" name="auto_cleanup_batch_size" value="<?php echo sr_e((string) $values['auto_cleanup_batch_size']); ?>" class="form-input" min="1" max="5000" required>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-retention-cleanup-modal" data-overlay="#admin-retention-cleanup-modal">
            <?php echo sr_e(sr_t('admin::ui.text.90922df1')); ?>
        </button>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.save.864e6c0c')); ?></button>
    </div>
</form>

<div id="admin-retention-cleanup-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="admin-retention-cleanup-modal-label">
    <div class="modal-dialog modal-dialog-lg">
        <div class="modal-content">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/retention')); ?>" class="admin-form ui-form-theme">
                <div class="modal-header">
                    <h3 id="admin-retention-cleanup-modal-label" class="modal-title"><?php echo sr_e(sr_t('admin::ui.text.0b10a14f')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#admin-retention-cleanup-modal">
                        <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="cleanup">
                    <p><?php echo sr_e(sr_t('admin::ui.save.5d203a1c')); ?></p>
                    <div class="table-wrapper">
                    <table class="table">
                        <thead class="ui-table-head">
                            <tr>
                                <th><?php echo sr_e(sr_t('admin::ui.text.8c609deb')); ?></th>
                                <th><?php echo sr_e(sr_t('admin::ui.text.8e07f3ae')); ?></th>
                                <th><?php echo sr_e(sr_t('admin::ui.delete.783a19c6')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo sr_e(sr_t('admin::ui.text.428871d5')); ?></td>
                                <td><?php echo sr_e($previewCutoffs['auth_logs']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['auth_logs']); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo sr_e(sr_t('admin::ui.admin.d0bd9568')); ?></td>
                                <td><?php echo sr_e($previewCutoffs['audit_logs']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['audit_logs']); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo sr_e(sr_t('admin::ui.password.settings.86a49355')); ?></td>
                                <td><?php echo sr_e($previewCutoffs['used_tokens']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['password_resets']); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo sr_e(sr_t('admin::ui.email.e74975e8')); ?></td>
                                <td><?php echo sr_e($previewCutoffs['used_tokens']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['email_verifications']); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo sr_e(sr_t('admin::ui.text.7c27e716')); ?></td>
                                <td><?php echo sr_e($previewCutoffs['sessions']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['sessions']); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo sr_e(sr_t('admin::ui.php.19df2136')); ?></td>
                                <td><?php echo sr_e($previewCutoffs['sessions']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['runtime_sessions']); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo sr_e(sr_t('admin::ui.text.b3b88e44')); ?></td>
                                <td><?php echo sr_e($previewCutoffs['sessions']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['rate_limits']); ?></td>
                            </tr>
                            <?php if ($hasNotificationTables) { ?>
                                <tr>
                                    <td><?php echo sr_e(sr_t('admin::ui.notification.12ddd6ca')); ?></td>
                                    <td><?php echo sr_e($previewCutoffs['notifications']); ?></td>
                                    <td><?php echo sr_e((string) $previewCounts['notifications']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo sr_e(sr_t('admin::ui.notification.56c30db0')); ?></td>
                                    <td><?php echo sr_e($previewCutoffs['notifications']); ?></td>
                                    <td><?php echo sr_e((string) $previewCounts['notification_deliveries']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo sr_e(sr_t('admin::ui.notification.82294dd1')); ?></td>
                                    <td><?php echo sr_e($previewCutoffs['notifications']); ?></td>
                                    <td><?php echo sr_e((string) $previewCounts['notification_reads']); ?></td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <td><?php echo sr_e(sr_t('admin::ui.text.b7aa8533')); ?></td>
                                <td><?php echo sr_e($previewCutoffs['module_backups']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['module_backups']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    <div class="admin-form-row">
                        <span class="form-label"><?php echo sr_e(sr_t('admin::ui.delete.b5dd39cf')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span>
                        <div class="admin-form-field">
                            <label class="admin-form-check form-label" for="modules_admin_retention_cleanup_confirmed">
                                                            <input id="modules_admin_retention_cleanup_confirmed" type="checkbox" name="cleanup_confirmed" value="1" class="form-checkbox" required data-overlay-focus>
                                                            <?php echo sr_admin_choice_label_html(sr_t('admin::ui.delete.ec013040')); ?>
                                                        </label>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="admin_retention_cleanup_phrase"><?php echo sr_e(sr_t('admin::ui.text.82e63a67')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <input id="admin_retention_cleanup_phrase" type="text" name="cleanup_phrase" maxlength="20" placeholder="DELETE" required class="form-input">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#admin-retention-cleanup-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('admin::ui.text.90922df1')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
