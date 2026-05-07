<?php

$adminPageTitle = 'SEO 설정';
include TOY_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo toy_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo toy_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<form method="post" action="<?php echo toy_e(toy_url('/admin/seo')); ?>">
    <?php echo toy_csrf_field(); ?>

    <section>
        <h2>기본 메타</h2>
        <p>
            <label>Title suffix<br>
                <input type="text" name="title_suffix" value="<?php echo toy_e((string) $settings['title_suffix']); ?>" maxlength="80">
            </label>
        </p>
        <p>
            <label>기본 description<br>
                <input type="text" name="default_description" value="<?php echo toy_e((string) $settings['default_description']); ?>" maxlength="255">
            </label>
        </p>
        <p>
            <label>기본 OG image URL<br>
                <input type="text" name="default_og_image" value="<?php echo toy_e((string) $settings['default_og_image']); ?>" maxlength="255">
            </label>
        </p>
    </section>

    <section>
        <h2>Sitemap</h2>
        <p>
            <label>
                <input type="checkbox" name="sitemap_include_home" value="1"<?php echo !empty($settings['sitemap_include_home']) ? ' checked' : ''; ?>>
                홈 URL 포함
            </label>
        </p>
        <?php if ($sitemapUrl !== '') { ?>
            <p><a href="<?php echo toy_e(toy_url('/sitemap.xml')); ?>">sitemap.xml 확인</a></p>
        <?php } ?>
    </section>

    <section>
        <h2>Robots</h2>
        <p>
            <label>차단 경로<br>
                <textarea name="robots_disallow_paths" rows="8" maxlength="2000"><?php echo toy_e((string) $settings['robots_disallow_paths']); ?></textarea>
            </label>
        </p>
        <pre><?php echo toy_e($robotsPreview); ?></pre>
        <p><a href="<?php echo toy_e(toy_url('/robots.txt')); ?>">robots.txt 확인</a></p>
    </section>

    <button type="submit">저장</button>
</form>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
