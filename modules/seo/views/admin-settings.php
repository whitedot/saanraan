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
            <div class="admin-form-label"><span class="form-label">제목 접미사</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">제목 접미사</span>
                <input type="text" name="title_suffix" value="<?php echo sr_e((string) $settings['title_suffix']); ?>" maxlength="80">
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">기본 설명</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">기본 설명</span>
                <input type="text" name="default_description" value="<?php echo sr_e((string) $settings['default_description']); ?>" maxlength="255">
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">기본 OG 이미지 URL</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">기본 OG 이미지 URL</span>
                <input type="text" name="default_og_image" value="<?php echo sr_e((string) $settings['default_og_image']); ?>" maxlength="255">
                </label>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>사이트맵</h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">홈 URL 포함</span></div>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label">
                        <input type="checkbox" name="sitemap_include_home" value="1" class="form-checkbox"<?php echo !empty($settings['sitemap_include_home']) ? ' checked' : ''; ?>>
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
                    <div class="admin-form-label"><span class="form-label">차단 경로</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">차단 경로</span>
                        <textarea name="robots_disallow_paths" rows="8" maxlength="2000"><?php echo sr_e((string) $settings['robots_disallow_paths']); ?></textarea>
                        </label>
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
