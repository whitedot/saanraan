<?php

$adminPageTitle = sr_t('seo::ui.seo.settings.604d83e6');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php
$defaultOgImage = (string) ($settings['default_og_image'] ?? '');
$defaultOgImageUrl = '';
if (sr_is_http_url($defaultOgImage)) {
    $defaultOgImageUrl = $defaultOgImage;
} elseif (sr_is_safe_relative_url($defaultOgImage)) {
    $defaultOgImageUrl = sr_url($defaultOgImage);
}
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/seo')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('seo::ui.text.c8ef75e9')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="seo_admin_settings_title_suffix"><?php echo sr_e(sr_t('seo::ui.text.0404a3fe')); ?></label>
            <div class="admin-form-field">
                <input id="seo_admin_settings_title_suffix" type="text" name="title_suffix" value="<?php echo sr_e((string) $settings['title_suffix']); ?>" class="form-input" maxlength="80">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="seo_admin_settings_default_description"><?php echo sr_e(sr_t('seo::ui.text.dbf432cb')); ?></label>
            <div class="admin-form-field">
                <input id="seo_admin_settings_default_description" type="text" name="default_description" value="<?php echo sr_e((string) $settings['default_description']); ?>" class="form-input form-control-full" maxlength="255">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="seo_admin_settings_default_og_image_upload"><?php echo sr_e(sr_t('seo::ui.og.url.14dbf393')); ?></label>
            <div class="admin-form-field">
                <?php if ($defaultOgImageUrl !== '') { ?>
                    <div class="seo-og-image-current">
                        <img src="<?php echo sr_e($defaultOgImageUrl); ?>" alt="<?php echo sr_e(sr_t('seo::ui.og.current.8f910aba')); ?>">
                        <div>
                            <a href="<?php echo sr_e($defaultOgImageUrl); ?>"><?php echo sr_e(sr_t('seo::ui.og.current.8f910aba')); ?></a>
                            <label class="admin-form-check form-label" for="seo_admin_settings_delete_default_og_image">
                                <input id="seo_admin_settings_delete_default_og_image" type="checkbox" name="delete_default_og_image" value="1" class="form-checkbox">
                                <?php echo sr_admin_choice_label_html(sr_t('seo::ui.og.delete.f7ca7f83')); ?>
                            </label>
                        </div>
                    </div>
                <?php } ?>
                <input id="seo_admin_settings_default_og_image_upload" type="file" name="default_og_image_upload" accept="image/jpeg,image/png,image/webp" class="form-input">
                <p class="admin-form-help"><?php echo sr_e(sr_t('seo::ui.og.upload.help.80d2d781')); ?> <?php echo sr_e(sr_seo_format_bytes(sr_seo_og_image_upload_max_bytes())); ?><?php echo sr_e(sr_t('seo::ui.og.upload.help.suffix.1a055fd3')); ?></p>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('seo::ui.text.0c082164')); ?></h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('seo::ui.url.51ecf74b')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_seo_admin_settings_sitemap_include_home">
                                            <input id="modules_seo_admin_settings_sitemap_include_home" type="checkbox" name="sitemap_include_home" value="1" class="form-checkbox"<?php echo !empty($settings['sitemap_include_home']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('seo::ui.url.51ecf74b')); ?>
                                        </label>
                </div>
            </div>
        </div>
        <?php if ($sitemapUrl !== '') { ?>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('seo::ui.text.2a5fd734')); ?></span>
                <div class="admin-form-field">
                    <a href="<?php echo sr_e(sr_url('/sitemap.xml')); ?>"><?php echo sr_e(sr_t('seo::ui.sitemap.xml.f88f383f')); ?></a>
                </div>
            </div>
        <?php } ?>
    </section>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('seo::ui.settings.7ce8a229')); ?></h2>
        <div class="seo-robots-table">
            <div class="seo-robots-name">
                <strong>robots.txt</strong>
                <a href="<?php echo sr_e(sr_url('/robots.txt')); ?>"><?php echo sr_e(sr_t('seo::ui.text.0d512314')); ?></a>
            </div>
            <div class="seo-robots-content">
                <div class="admin-form-row">
                    <label class="form-label" for="seo_admin_settings_robots_disallow_paths"><?php echo sr_e(sr_t('seo::ui.text.553ea40a')); ?></label>
                    <div class="admin-form-field">
                        <textarea id="seo_admin_settings_robots_disallow_paths" name="robots_disallow_paths" rows="8" maxlength="2000" class="form-textarea"><?php echo sr_e((string) $settings['robots_disallow_paths']); ?></textarea>
                    </div>
                </div>
                <pre><?php echo sr_e($robotsPreview); ?></pre>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('seo::ui.save.5fb92622')); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
