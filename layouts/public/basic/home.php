<?php

$homeSite = is_array($site ?? null) ? $site : [];
$homePdo = $pdo instanceof PDO ? $pdo : null;
$homeSiteName = (string) ($homeSite['name'] ?? 'Saanraan');
$homeDescription = sr_t('ui.page.2a0a8b79');
$seo = [
    'title' => $homeSiteName,
    'description' => $homeDescription,
    'canonical' => sr_canonical_url($homeSite, '/'),
];
if ($homePdo instanceof PDO && sr_module_enabled($homePdo, 'seo')) {
    $seoSettings = sr_module_settings($homePdo, 'seo');
    if (!empty($seoSettings['title_suffix']) && is_string($seoSettings['title_suffix'])) {
        $seo['title'] .= ' - ' . $seoSettings['title_suffix'];
    }
    if (!empty($seoSettings['default_description']) && is_string($seoSettings['default_description'])) {
        $seo['description'] = $seoSettings['default_description'];
    }
    if (!empty($seoSettings['default_og_image']) && is_string($seoSettings['default_og_image'])) {
        $seo['og'] = ['image' => $seoSettings['default_og_image']];
    }
}

sr_public_layout_begin($homePdo, $homeSite, $seo);
?>
    <main class="public-ui-scope public-home">
        <?php if ($homePdo instanceof PDO) { ?>
            <?php echo sr_render_output_slot($homePdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content']); ?>
        <?php } ?>
        <section class="public-home-hero">
            <h1 class="type-display-fluid"><?php echo sr_e($homeSiteName); ?></h1>
            <p><?php echo nl2br(sr_e($homeDescription)); ?></p>
        </section>
        <?php if ($homePdo instanceof PDO) { ?>
            <?php echo sr_render_output_slot($homePdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content']); ?>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
