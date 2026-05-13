<?php

$adminPageTitle = 'SEO 설정';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo sr_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo sr_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/seo')); ?>" class="admin-form-layout ui-form-theme ui-form-showcase">
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <h2>기본 메타</h2>
        <p>
            <label>제목 접미사<br>
                <input type="text" name="title_suffix" value="<?php echo sr_e((string) $settings['title_suffix']); ?>" maxlength="80">
            </label>
        </p>
        <p>
            <label>기본 설명<br>
                <input type="text" name="default_description" value="<?php echo sr_e((string) $settings['default_description']); ?>" maxlength="255">
            </label>
        </p>
        <p>
            <label>기본 OG 이미지 URL<br>
                <input type="text" name="default_og_image" value="<?php echo sr_e((string) $settings['default_og_image']); ?>" maxlength="255">
            </label>
        </p>
    </section>

    <section class="card">
        <h2>사이트맵</h2>
        <p>
            <label>
                <input type="checkbox" name="sitemap_include_home" value="1"<?php echo !empty($settings['sitemap_include_home']) ? ' checked' : ''; ?>>
                홈 URL 포함
            </label>
        </p>
        <?php if ($sitemapUrl !== '') { ?>
            <p><a href="<?php echo sr_e(sr_url('/sitemap.xml')); ?>">sitemap.xml 확인</a></p>
        <?php } ?>
    </section>

    <section class="card">
        <h2>로봇 설정</h2>
        <p>
            <label>차단 경로<br>
                <textarea name="robots_disallow_paths" rows="8" maxlength="2000"><?php echo sr_e((string) $settings['robots_disallow_paths']); ?></textarea>
            </label>
        </p>
        <pre><?php echo sr_e($robotsPreview); ?></pre>
        <p><a href="<?php echo sr_e(sr_url('/robots.txt')); ?>">robots.txt 확인</a></p>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
