<?php

$adminPageTitle = sr_t('point::ui.settings.title');
$adminPageSubtitle = sr_t('point::ui.settings.subtitle');
$settings = isset($settings) && is_array($settings) ? $settings : ['display_name' => '포인트', 'unit_label' => 'P', 'default_expiration_days' => '0'];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/points/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('point::ui.settings.title')); ?></h2>
        <?php echo sr_csrf_field(); ?>

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
        <div class="admin-form-row">
            <label class="form-label" for="point_settings_default_expiration_days"><?php echo sr_e(sr_t('point::ui.settings.default_expiration_days')); ?></label>
            <div class="admin-form-field">
                <div class="input-group admin-input-unit">
                    <input id="point_settings_default_expiration_days" type="number" name="default_expiration_days" value="<?php echo sr_e((string) ($settings['default_expiration_days'] ?? '0')); ?>" class="form-input" min="0" max="3650" step="1">
                    <span class="input-group-text"><?php echo sr_e(sr_t('point::ui.settings.days_unit')); ?></span>
                </div>
                <small class="admin-form-help"><?php echo sr_e(sr_t('point::ui.settings.default_expiration_days_help')); ?></small>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <div class="admin-form-secondary-actions">
            <small class="admin-form-help"><?php echo sr_e('수동 만료 실행은 만료 대상 포인트를 바로 처리하며, 위 환경설정 입력값은 함께 저장하지 않습니다.'); ?></small>
            <button type="submit" name="intent" value="expire_due" class="btn btn-solid-light" formnovalidate><?php echo sr_e(sr_t('point::ui.settings.expire_due')); ?></button>
        </div>
        <button type="submit" name="intent" value="save_settings" class="btn btn-solid-primary"><?php echo sr_e(sr_t('point::ui.settings.save')); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
