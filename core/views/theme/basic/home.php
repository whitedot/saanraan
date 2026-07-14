<?php

$homeSite = is_array($site ?? null) ? $site : [];
$homePdo = isset($pdo) && $pdo instanceof PDO ? $pdo : null;
$homeSiteName = sr_site_display_name($homeSite, $homePdo);
$seo = [
    'title' => $homeSiteName,
    'canonical' => sr_canonical_url($site, '/'),
];

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'body_class' => 'public-layout-home sr-site-home site-view-theme-basic',
    'style_profile' => 'kit',
    'stylesheets' => [
        '/assets/module.css',
        '/modules/banner/assets/module.css',
    ],
    'scripts' => [
        '/assets/module.js',
    ],
    'output_slots' => [
        ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content'],
        ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content'],
    ],
]);
?>
    <main class="public-ui-scope public-home">
        <?php if ($homePdo instanceof PDO) { ?>
            <?php echo sr_render_output_slot($homePdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content']); ?>
        <?php } ?>
        <?php include SR_ROOT . '/core/views/home-editorial.php'; ?>
        <?php if ($homePdo instanceof PDO) { ?>
            <?php echo sr_render_output_slot($homePdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content']); ?>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
