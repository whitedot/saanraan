<?php

$adminPageTitle = sr_t('admin::ui.settings.4738c9b6');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if (!$currentHomepageAvailable) { ?>
    <div class="admin-notice">
        <span class="admin-notice-icon">!</span>
        <div class="admin-notice-copy">
            <strong><?php echo sr_e(sr_t('admin::ui.save.active.794b5bfc')); ?></strong>
            <p><?php echo sr_e(sr_t('admin::ui.page.active.select.save.8a7dbcc6')); ?></p>
        </div>
    </div>
<?php } ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="site">
    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('admin::ui.text.f6fc85bc')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_name"><?php echo sr_e(sr_t('admin::ui.name.51f4c6af')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="admin_settings_name" type="text" name="name" value="<?php echo sr_e($values['name']); ?>" class="form-input" maxlength="120" required>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label"><?php echo sr_e(sr_t('admin::ui.url.09f44187')); ?></span>
            <div class="admin-form-field">
                <?php if ($values['base_url'] !== '') { ?>
                    <code><?php echo sr_e($values['base_url']); ?></code>
                <?php } else { ?>
                    <span><?php echo sr_e(sr_t('admin::ui.settings.9182f8fe')); ?></span>
                <?php } ?>
                <p class="admin-form-help"><?php echo sr_e(sr_t('admin::ui.search.active.admin.settings.7aedc357')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_timezone"><?php echo sr_e(sr_t('admin::ui.text.26e997a5')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <select id="admin_settings_timezone" name="timezone" class="form-select" required>
                    <?php foreach ($timezoneOptions as $timezoneOption) { ?>
                        <option value="<?php echo sr_e($timezoneOption); ?>"<?php echo $values['timezone'] === $timezoneOption ? ' selected' : ''; ?>>
                            <?php echo sr_e($timezoneOption); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_default_locale"><?php echo sr_e(sr_t('admin::ui.locale.c7cd39b4')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <select id="admin_settings_default_locale" name="default_locale" class="form-select" required>
                    <?php foreach ($localeOptions as $localeOption) { ?>
                        <option value="<?php echo sr_e($localeOption); ?>"<?php echo $values['default_locale'] === $localeOption ? ' selected' : ''; ?>>
                            <?php echo sr_e($localeOption); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_supported_locales"><?php echo sr_e(sr_t('admin::ui.locale.list.51d8e798')); ?></label>
            <div class="admin-form-field">
                <?php $selectedSupportedLocales = sr_supported_locales($values); ?>
                <?php echo sr_admin_checkbox_list_html('admin_settings_supported_locales', 'supported_locales', array_combine($localeOptions, $localeOptions) ?: [], $selectedSupportedLocales, sr_t('admin::ui.locale.9d745a6e')); ?>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_status"><?php echo sr_e(sr_t('admin::ui.status.e4163930')); ?></label>
            <div class="admin-form-field">
                <select id="admin_settings_status" name="status" class="form-select">
                                    <option value="active"<?php echo $values['status'] === 'active' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('admin::ui.text.0928a1b8')); ?></option>
                                    <option value="maintenance"<?php echo $values['status'] === 'maintenance' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('admin::ui.text.4fd02e48')); ?></option>
                                </select>
            </div>
        </div>
    </section>
    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('admin::ui.text.b5361f64')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_public_layout_key"><?php echo sr_e(sr_t('admin::ui.text.974e65f4')); ?></label>
            <div class="admin-form-field">
                <select id="admin_settings_public_layout_key" name="public_layout_key" class="form-select">
                                    <?php foreach (sr_public_layout_options($pdo) as $layoutKey => $layoutOption) { ?>
                                        <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo $values['public_layout_key'] === (string) $layoutKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                                        </option>
                                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_home_path"><?php echo sr_e(sr_t('admin::ui.text.214b5fb8')); ?></label>
            <div class="admin-form-field">
                <select id="admin_settings_home_path" name="home_path" class="form-select form-control-full">
                    <?php foreach ($homepageCandidates as $candidate) { ?>
                        <?php $candidatePath = (string) ($candidate['path'] ?? ''); ?>
                        <?php $candidateSelected = (string) ($values['home_path'] ?? '/') === $candidatePath; ?>
                        <option value="<?php echo sr_e($candidatePath); ?>"<?php echo $candidateSelected ? ' selected' : ''; ?><?php echo empty($candidate['available']) && !$candidateSelected ? ' disabled' : ''; ?>>
                            <?php echo sr_e((string) ($candidate['label'] ?? $candidatePath)); ?>
                            <?php echo $candidatePath !== '/' ? ' - ' . sr_e($candidatePath) : ''; ?>
                            <?php echo empty($candidate['available']) ? sr_t('admin::ui.active.6e2fcb45') : ''; ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help"><?php echo sr_e(sr_t('admin::ui.page.page.community.status.active.ee1178b4')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_ui_color_scheme"><?php echo sr_e(sr_t('admin::ui.ui.cf6c41c6')); ?></label>
            <div class="admin-form-field">
                <select id="admin_settings_ui_color_scheme" name="ui_color_scheme" class="form-select" data-admin-color-scheme-select>
                    <?php foreach (sr_color_scheme_options() as $colorScheme => $colorSchemeLabel) { ?>
                        <option value="<?php echo sr_e((string) $colorScheme); ?>"<?php echo $values['ui_color_scheme'] === (string) $colorScheme ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $colorSchemeLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </section>
    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('admin::settings.section.admin_screen')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_admin_skin_key"><?php echo sr_e(sr_t('admin::ui.admin.1465c5b7')); ?></label>
            <div class="admin-form-field">
                <select id="admin_settings_admin_skin_key" name="admin_skin_key" class="form-select">
                    <?php foreach ($adminSkinOptions as $skinKey => $skinOption) { ?>
                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $adminSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <a href="<?php echo sr_e(sr_url('/')); ?>" class="btn btn-solid-light" target="_blank" rel="noopener noreferrer"><?php echo sr_e(sr_t('admin::ui.text.b47e1675')); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.save.5fb92622')); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
