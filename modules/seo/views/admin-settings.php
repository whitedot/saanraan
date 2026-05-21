<?php

$adminPageTitle = 'SEO 설정';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/seo')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="admin-card card">
        <h2>기본 메타</h2>
        <div class="admin-form-row">
            <label class="form-label" for="seo_admin_settings_title_suffix">제목 접미사</label>
            <div class="admin-form-field">
                <input id="seo_admin_settings_title_suffix" type="text" name="title_suffix" value="<?php echo sr_e((string) $settings['title_suffix']); ?>" class="form-input" maxlength="80">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="seo_admin_settings_default_description">기본 설명</label>
            <div class="admin-form-field">
                <input id="seo_admin_settings_default_description" type="text" name="default_description" value="<?php echo sr_e((string) $settings['default_description']); ?>" class="form-input" maxlength="255">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="seo_admin_settings_default_og_image">기본 OG 이미지 URL</label>
            <div class="admin-form-field">
                <input id="seo_admin_settings_default_og_image" type="text" name="default_og_image" value="<?php echo sr_e((string) $settings['default_og_image']); ?>" class="form-input" maxlength="255">
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>사이트맵</h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <span class="form-label">홈 URL 포함</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_seo_admin_settings_sitemap_include_home">
                                            <input id="modules_seo_admin_settings_sitemap_include_home" type="checkbox" name="sitemap_include_home" value="1" class="form-checkbox"<?php echo !empty($settings['sitemap_include_home']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('홈 URL 포함'); ?>
                                        </label>
                </div>
            </div>
        </div>
        <?php if ($sitemapUrl !== '') { ?>
            <p><a href="<?php echo sr_e(sr_url('/sitemap.xml')); ?>">sitemap.xml 확인</a></p>
        <?php } ?>
    </section>

    <section class="admin-card card">
        <h2>로봇 설정</h2>
        <div class="seo-robots-table">
            <div class="seo-robots-name">
                <strong>robots.txt</strong>
                <a href="<?php echo sr_e(sr_url('/robots.txt')); ?>">파일 확인</a>
            </div>
            <div class="seo-robots-content">
                <div class="admin-form-row">
                    <label class="form-label" for="seo_admin_settings_robots_disallow_paths">차단 경로</label>
                    <div class="admin-form-field">
                        <textarea id="seo_admin_settings_robots_disallow_paths" name="robots_disallow_paths" rows="8" maxlength="2000" class="form-textarea"><?php echo sr_e((string) $settings['robots_disallow_paths']); ?></textarea>
                    </div>
                </div>
                <pre><?php echo sr_e($robotsPreview); ?></pre>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
