<?php

$adminPageTitle = sr_t('point::ui.settings.title');
$adminPageSubtitle = sr_t('point::ui.settings.subtitle');
$settings = isset($settings) && is_array($settings) ? $settings : ['display_name' => '포인트', 'unit_label' => 'P'];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/points/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('point::ui.settings.title')); ?></h2>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">

        <div class="admin-form-row">
            <label class="form-label" for="point_settings_display_name"><?php echo sr_e(sr_t('point::ui.settings.display_name')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('point::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="point_settings_display_name" type="text" name="display_name" value="<?php echo sr_e((string) ($settings['display_name'] ?? '포인트')); ?>" class="form-input" maxlength="40" required>
                <small class="admin-form-help"><?php echo sr_e(sr_t('point::ui.settings.display_name_help')); ?></small>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="point_settings_unit_label"><?php echo sr_e(sr_t('point::ui.settings.unit_label')); ?></label>
            <div class="admin-form-field">
                <input id="point_settings_unit_label" type="text" name="unit_label" value="<?php echo sr_e((string) ($settings['unit_label'] ?? 'P')); ?>" class="form-input" maxlength="20">
                <small class="admin-form-help"><?php echo sr_e(sr_t('point::ui.settings.unit_label_help')); ?></small>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/points/balances')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('point::ui.point.47719e8e')); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('point::ui.settings.save')); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
