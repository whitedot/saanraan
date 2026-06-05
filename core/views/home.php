<?php

$layoutPdo = $pdo instanceof PDO ? $pdo : null;
$homeViewFile = sr_public_layout_optional_view_file(sr_public_layout_key($site ?? null, $layoutPdo), 'home', $layoutPdo);
if ($homeViewFile !== null && realpath($homeViewFile) !== realpath(__FILE__)) {
    include $homeViewFile;
    return;
}

$pageTitle = isset($site['name']) ? (string) $site['name'] : 'Saanraan';
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/'),
];

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'stylesheets' => [
        '/assets/saanraan.css',
        '/modules/banner/assets/public.css',
    ],
]);
?>
    <main>
        <?php echo sr_render_output_slot($pdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content']); ?>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p><?php echo sr_e(sr_t('ui.saanraan.d60361f1')); ?></p>
        <p><a href="<?php echo sr_e(sr_url('/admin')); ?>"><?php echo sr_e(sr_t('ui.admin.c68cbc05')); ?></a></p>
        <?php echo sr_render_output_slot($pdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content']); ?>
    </main>
<?php sr_public_layout_end(); ?>
