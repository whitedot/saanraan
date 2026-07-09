<?php

$adminPageTitle = sr_t('point::ui.settings.title');
$adminPageSubtitle = '';
$settings = isset($settings) && is_array($settings) ? $settings : ['usage_enabled' => true, 'display_name' => '포인트', 'unit_label' => 'P', 'default_expiration_days' => '0'];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/points/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2><?php echo sr_e(sr_t('point::ui.settings.title')); ?></h2>
        <?php echo sr_csrf_field(); ?>

        <div class="form-row">
            <label class="form-label" for="point_settings_usage_enabled">포인트 사용 여부</label>
            <div class="form-field">
                <?php echo sr_admin_switch_html('point_settings_usage_enabled', 'usage_enabled', '1', !empty($settings['usage_enabled']), '사용'); ?>
                <small class="form-help">사용하지 않으면 보상, 환전, 쿠폰 유료 발급 등 포인트를 선택하거나 새 거래를 만드는 사용처에서 제외됩니다.</small>
            </div>
        </div>
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

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
