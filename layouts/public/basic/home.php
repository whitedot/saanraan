<?php

$homeSite = is_array($site ?? null) ? $site : [];
$homePdo = $pdo instanceof PDO ? $pdo : null;
$homeSiteName = (string) ($homeSite['name'] ?? 'Saanraan');
$homeTitle = $homePdo instanceof PDO ? trim((string) sr_site_setting($homePdo, 'site.home.title', '')) : '';
if ($homeTitle === '') {
    $homeTitle = $homeSiteName;
}
$homeEyebrow = $homePdo instanceof PDO ? trim((string) sr_site_setting($homePdo, 'site.home.eyebrow', '')) : '';
$homeDescriptionSetting = $homePdo instanceof PDO ? trim((string) sr_site_setting($homePdo, 'site.home.description', '')) : '';
$homeDescription = $homeDescriptionSetting;
if ($homeDescriptionSetting === '') {
    $homeDescription = '새 홈페이지를 준비하고 있습니다.';
}
$homePrimaryLabel = $homePdo instanceof PDO ? trim((string) sr_site_setting($homePdo, 'site.home.primary_label', '')) : '';
$homePrimaryUrl = $homePdo instanceof PDO ? trim((string) sr_site_setting($homePdo, 'site.home.primary_url', '')) : '';
$homeSecondaryLabel = $homePdo instanceof PDO ? trim((string) sr_site_setting($homePdo, 'site.home.secondary_label', '')) : '';
$homeSecondaryUrl = $homePdo instanceof PDO ? trim((string) sr_site_setting($homePdo, 'site.home.secondary_url', '')) : '';
$homePrimaryEnabled = $homePrimaryLabel !== '' && (sr_is_safe_relative_url($homePrimaryUrl) || sr_is_http_url($homePrimaryUrl));
$homeSecondaryEnabled = $homeSecondaryLabel !== '' && (sr_is_safe_relative_url($homeSecondaryUrl) || sr_is_http_url($homeSecondaryUrl));
$homePrimaryHref = sr_is_http_url($homePrimaryUrl) ? $homePrimaryUrl : sr_url($homePrimaryUrl);
$homeSecondaryHref = sr_is_http_url($homeSecondaryUrl) ? $homeSecondaryUrl : sr_url($homeSecondaryUrl);
$seo = [
    'title' => $homeTitle,
    'description' => $homeDescriptionSetting,
    'canonical' => sr_canonical_url($homeSite, '/'),
];
if ($homePdo instanceof PDO && sr_module_enabled($homePdo, 'seo')) {
    $seoSettings = sr_module_settings($homePdo, 'seo');
    if (!empty($seoSettings['title_suffix']) && is_string($seoSettings['title_suffix'])) {
        $seo['title'] .= ' - ' . $seoSettings['title_suffix'];
    }
    if ($seo['description'] === '' && !empty($seoSettings['default_description']) && is_string($seoSettings['default_description'])) {
        $seo['description'] = $seoSettings['default_description'];
    }
    if (!empty($seoSettings['default_og_image']) && is_string($seoSettings['default_og_image'])) {
        $seo['og'] = ['image' => $seoSettings['default_og_image']];
    }
}
if ($seo['description'] === '') {
    $seo['description'] = $homeDescription;
}

sr_public_layout_begin($homePdo, $homeSite, $seo);
?>
    <main class="public-ui-scope public-home">
        <?php if ($homePdo instanceof PDO) { ?>
            <?php echo sr_render_output_slot($homePdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content']); ?>
        <?php } ?>
        <section class="public-home-hero">
            <?php if ($homeEyebrow !== '') { ?>
                <p class="public-home-eyebrow"><?php echo sr_e($homeEyebrow); ?></p>
            <?php } ?>
            <h1><?php echo sr_e($homeTitle); ?></h1>
            <p><?php echo nl2br(sr_e($homeDescription)); ?></p>
            <?php if ($homePrimaryEnabled || $homeSecondaryEnabled) { ?>
                <div class="public-ui-actions public-home-actions">
                    <?php if ($homePrimaryEnabled) { ?>
                        <a href="<?php echo sr_e($homePrimaryHref); ?>" class="public-ui-button"><?php echo sr_e($homePrimaryLabel); ?></a>
                    <?php } ?>
                    <?php if ($homeSecondaryEnabled) { ?>
                        <a href="<?php echo sr_e($homeSecondaryHref); ?>" class="public-ui-button public-ui-button-secondary"><?php echo sr_e($homeSecondaryLabel); ?></a>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>
        <?php if ($homePdo instanceof PDO) { ?>
            <?php echo sr_render_output_slot($homePdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content']); ?>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
