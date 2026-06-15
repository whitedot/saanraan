<?php

$homeSite = is_array($site ?? null) ? $site : [];
$homePdo = $pdo instanceof PDO ? $pdo : null;
$homeSiteName = sr_site_display_name($homeSite, $homePdo);
$homeDescription = sr_t('ui.page.2a0a8b79');
$seo = [
    'title' => $homeSiteName,
    'canonical' => sr_canonical_url($homeSite, '/'),
];

sr_public_layout_begin($homePdo, $homeSite, $seo, [
    'style_profile' => 'kit',
    'stylesheets' => [
        '/assets/theme.css',
        '/assets/module.css',
        '/modules/banner/assets/module.css',
    ],
]);
?>
    <main class="public-ui-scope public-home">
        <?php if ($homePdo instanceof PDO) { ?>
            <?php echo sr_render_output_slot($homePdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content']); ?>
        <?php } ?>
        <section class="public-home-hero">
            <h1 class="type-display-fluid"><?php echo sr_e($homeSiteName); ?></h1>
            <p class="type-section-title"><?php echo nl2br(sr_e($homeDescription)); ?></p>
        </section>
        <?php if ($homePdo instanceof PDO) { ?>
            <?php echo sr_render_output_slot($homePdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content']); ?>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
