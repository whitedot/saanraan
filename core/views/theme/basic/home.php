<?php

$layoutPdo = isset($pdo) && $pdo instanceof PDO ? $pdo : null;
$pageTitle = sr_site_display_name(is_array($site ?? null) ? $site : null, $layoutPdo);
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/'),
];

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'body_class' => 'public-layout-home sr-site-home site-view-theme-basic',
    'style_profile' => 'kit',
    'stylesheets' => [
        '/assets/module.css',
        '/modules/banner/assets/module.css',
    ],
    'output_slots' => [
        ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content'],
        ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content'],
    ],
]);
?>
    <main class="public-ui-scope public-home">
        <?php echo sr_render_output_slot($pdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content']); ?>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p><?php echo sr_e($pageTitle . ' 사이트가 설치되었습니다.'); ?></p>
        <p><a href="<?php echo sr_e(sr_url('/admin')); ?>"><?php echo sr_e(sr_t('ui.admin.c68cbc05')); ?></a></p>
        <?php echo sr_render_output_slot($pdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content']); ?>
    </main>
<?php sr_public_layout_end(); ?>
