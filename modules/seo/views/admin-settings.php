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

    <section class="card">
        <h2><?php echo sr_e(sr_t('seo::ui.text.c8ef75e9')); ?></h2>
        <div class="form-row">
            <label class="form-label" for="seo_admin_settings_title_suffix"><?php echo sr_e(sr_t('seo::ui.text.0404a3fe')); ?></label>
            <div class="form-field">
                <input id="seo_admin_settings_title_suffix" type="text" name="title_suffix" value="<?php echo sr_e((string) $settings['title_suffix']); ?>" class="form-input" maxlength="80">
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="seo_admin_settings_default_description"><?php echo sr_e(sr_t('seo::ui.text.dbf432cb')); ?></label>
            <div class="form-field">
                <input id="seo_admin_settings_default_description" type="text" name="default_description" value="<?php echo sr_e((string) $settings['default_description']); ?>" class="form-input form-control-full" maxlength="255">
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="seo_admin_settings_default_og_image_upload"><?php echo sr_e(sr_t('seo::ui.og.url.14dbf393')); ?></label>
            <div class="form-field">
                <?php if ($defaultOgImageUrl !== '') { ?>
                    <div class="seo-og-image-current">
                        <img src="<?php echo sr_e($defaultOgImageUrl); ?>" alt="<?php echo sr_e(sr_t('seo::ui.og.current.8f910aba')); ?>">
                        <div>
                            <a href="<?php echo sr_e($defaultOgImageUrl); ?>"><?php echo sr_e(sr_t('seo::ui.og.current.8f910aba')); ?></a>
                            <label class="form-check form-label" for="seo_admin_settings_delete_default_og_image">
                                <input id="seo_admin_settings_delete_default_og_image" type="checkbox" name="delete_default_og_image" value="1" class="form-checkbox">
                                <?php echo sr_admin_choice_label_html(sr_t('seo::ui.og.delete.f7ca7f83')); ?>
                            </label>
                        </div>
                    </div>
                <?php } ?>
                <input id="seo_admin_settings_default_og_image_upload" type="file" name="default_og_image_upload" accept="image/jpeg,image/png,image/webp" class="form-input">
                <p class="form-help"><?php echo sr_e(sr_t('seo::ui.og.upload.help.80d2d781')); ?> <?php echo sr_e(sr_seo_format_bytes(sr_seo_og_image_upload_max_bytes())); ?><?php echo sr_e(sr_t('seo::ui.og.upload.help.suffix.1a055fd3')); ?></p>
            </div>
        </div>
    </section>

    <section class="card">
        <h2><?php echo sr_e(sr_t('seo::ui.text.0c082164')); ?></h2>
        <div class="form-grid">
            <div class="form-row">
                <span class="form-label"><?php echo sr_e(sr_t('seo::ui.url.51ecf74b')); ?></span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('modules_seo_admin_settings_sitemap_include_home', 'sitemap_include_home', '1', !empty($settings['sitemap_include_home']), sr_t('seo::ui.url.51ecf74b')); ?>
                </div>
            </div>
        </div>
        <?php if ($sitemapUrl !== '') { ?>
            <div class="form-row">
                <span class="form-label"><?php echo sr_e(sr_t('seo::ui.text.2a5fd734')); ?></span>
                <div class="form-field">
                    <div class="seo-sitemap-actions">
                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/sitemap.xml')); ?>" target="_blank" rel="noopener noreferrer"><?php echo sr_e(sr_t('seo::ui.sitemap.xml.f88f383f')); ?></a>
                        <button type="button" class="btn btn-sm btn-solid-light" data-seo-copy-url="<?php echo sr_e($sitemapUrl); ?>" data-copy-label="<?php echo sr_e(sr_t('seo::ui.sitemap.copy.1f43e9aa')); ?>" data-copy-success="<?php echo sr_e(sr_t('seo::ui.sitemap.copy.success.9d96e6c2')); ?>" data-copy-fail="<?php echo sr_e(sr_t('seo::ui.sitemap.copy.fail.54a85d2b')); ?>"><?php echo sr_e(sr_t('seo::ui.sitemap.copy.1f43e9aa')); ?></button>
                    </div>
                    <small class="form-help seo-sitemap-url"><?php echo sr_e($sitemapUrl); ?></small>
                </div>
            </div>
        <?php } ?>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('seo::ui.settings.7ce8a229')); ?></h2>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/robots.txt')); ?>" target="_blank" rel="noopener noreferrer"><?php echo sr_e(sr_t('seo::ui.text.0d512314')); ?></a>
        </div>
        <div class="form-row">
            <label class="form-label" for="seo_admin_settings_robots_disallow_paths"><?php echo sr_e(sr_t('seo::ui.text.553ea40a')); ?></label>
            <div class="form-field">
                <textarea id="seo_admin_settings_robots_disallow_paths" name="robots_disallow_paths" rows="8" maxlength="2000" class="form-textarea"><?php echo sr_e((string) $settings['robots_disallow_paths']); ?></textarea>
                <pre class="seo-robots-preview"><?php echo sr_e($robotsPreview); ?></pre>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('seo::ui.save.5fb92622')); ?></button>
    </div>
</form>

<script>
(function () {
    var copyButtons = Array.prototype.slice.call(document.querySelectorAll('[data-seo-copy-url]'));
    var fallbackCopy = function (value) {
        var textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            return document.execCommand('copy');
        } finally {
            document.body.removeChild(textarea);
        }
    };
    copyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-seo-copy-url') || '';
            var label = button.getAttribute('data-copy-label') || button.textContent;
            var success = button.getAttribute('data-copy-success') || label;
            var fail = button.getAttribute('data-copy-fail') || label;
            var setText = function (text) {
                button.textContent = text;
                window.setTimeout(function () {
                    button.textContent = label;
                }, 1600);
            };
            var done = function (ok) {
                setText(ok ? success : fail);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(function () {
                    done(true);
                }).catch(function () {
                    done(fallbackCopy(value));
                });
                return;
            }
            done(fallbackCopy(value));
        });
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
