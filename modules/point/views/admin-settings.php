<?php

$adminPageTitle = sr_t('point::ui.settings.title');
$adminPageSubtitle = '';
$settings = isset($settings) && is_array($settings) ? $settings : ['display_name' => '포인트', 'unit_label' => 'P', 'default_expiration_days' => '0'];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/points/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2><?php echo sr_e(sr_t('point::ui.settings.title')); ?></h2>
        <?php echo sr_csrf_field(); ?>

        <div class="form-row">
            <label class="form-label" for="point_settings_display_name"><?php echo sr_e(sr_t('point::ui.settings.display_name')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('point::ui.required.1f227c67')); ?></span></label>
            <div class="form-field">
                <input id="point_settings_display_name" type="text" name="display_name" value="<?php echo sr_e((string) ($settings['display_name'] ?? '포인트')); ?>" class="form-input" maxlength="40" required>
                <small class="form-help"><?php echo sr_e(sr_t('point::ui.settings.display_name_help')); ?></small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="point_settings_unit_label"><?php echo sr_e(sr_t('point::ui.settings.unit_label')); ?></label>
            <div class="form-field">
                <input id="point_settings_unit_label" type="text" name="unit_label" value="<?php echo sr_e((string) ($settings['unit_label'] ?? 'P')); ?>" class="form-input" maxlength="20">
                <small class="form-help"><?php echo sr_e(sr_t('point::ui.settings.unit_label_help')); ?></small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="point_settings_default_expiration_days"><?php echo sr_e(sr_t('point::ui.settings.default_expiration_days')); ?></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="point_settings_default_expiration_days" type="number" name="default_expiration_days" value="<?php echo sr_e((string) ($settings['default_expiration_days'] ?? '0')); ?>" class="form-input" min="0" max="3650" step="1">
                    <span class="input-group-text"><?php echo sr_e(sr_t('point::ui.settings.days_unit')); ?></span>
                </div>
                <small class="form-help"><?php echo sr_e(sr_t('point::ui.settings.default_expiration_days_help')); ?></small>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-split">
        <button type="submit" name="intent" value="save_settings" class="btn btn-solid-primary"><?php echo sr_e(sr_t('point::ui.settings.save')); ?></button>
    </div>
</form>

<form method="post" action="<?php echo sr_e(sr_url('/admin/points/settings')); ?>" class="admin-form ui-form-theme" data-point-expire-form data-confirm-message="<?php echo sr_e(sr_t('point::ui.settings.expire_due_confirm')); ?>">
    <section class="card">
        <h2><?php echo sr_e(sr_t('point::ui.settings.expire_due')); ?></h2>
        <p><?php echo sr_e(sr_t('point::ui.settings.expire_due_help')); ?></p>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="expire_due">
        <input type="hidden" name="expire_confirmed" value="0" data-point-expire-confirmed>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e(sr_t('point::ui.settings.expire_due')); ?></span>
            <div class="form-field">
                <button type="submit" class="btn btn-solid-light" formnovalidate><?php echo sr_e(sr_t('point::ui.settings.expire_due')); ?></button>
            </div>
        </div>
    </section>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-point-expire-form]');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        var confirmed = form.querySelector('[data-point-expire-confirmed]');
        var message = form.getAttribute('data-confirm-message') || '';
        if (!window.confirm(message)) {
            if (confirmed) {
                confirmed.value = '0';
            }
            event.preventDefault();
            return;
        }

        if (confirmed) {
            confirmed.value = '1';
        }
    });
});
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
