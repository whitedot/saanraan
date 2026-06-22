<?php

$adminPageTitle = sr_t('seo::ui.seo.settings.604d83e6');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/seo')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <h2><?php echo sr_e(sr_t('seo::ui.text.0c082164')); ?></h2>
        <div class="form-grid">
            <div class="form-row">
                <span class="form-label"><?php echo sr_e(sr_t('seo::ui.url.51ecf74b')); ?></span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('modules_seo_admin_settings_sitemap_include_home', 'sitemap_include_home', '1', !empty($settings['sitemap_include_home']), '포함'); ?>
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
