<?php

$adminPageTitle = sr_t('banner::ui.banner.settings.cc368bd0');
$adminPageSubtitle = sr_t('banner::ui.banner.d6c7ef09');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/banners/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('banner::ui.banner.settings.cc368bd0')); ?></h2>
        <p><?php echo sr_e(sr_t('banner::ui.banner.banner.select.settings.115fa68f')); ?></p>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">
        <div class="admin-form-row">
            <label class="form-label" for="banner_admin_banner_settings_banner_skin_key"><?php echo sr_e(sr_t('banner::ui.banner.46b4fae5')); ?></label>
            <div class="admin-form-field">
                <select id="banner_admin_banner_settings_banner_skin_key" name="banner_skin_key" class="form-select">
                                    <?php foreach ($bannerSkinOptions as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $bannerSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                            (<?php echo sr_e(implode(', ', array_map('sr_banner_placement_kind_label', is_array($skinOption['supports'] ?? null) ? $skinOption['supports'] : ['inline']))); ?>)
                                        </option>
                                    <?php } ?>
                </select>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('banner::ui.banner.list.f989d740')); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('banner::ui.banner.settings.save.760342b3')); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
