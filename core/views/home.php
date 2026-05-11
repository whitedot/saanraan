<?php

$pageTitle = isset($site['name']) ? (string) $site['name'] : 'Toycore';
$seo = [
    'title' => $pageTitle,
    'canonical' => toy_canonical_url($site, '/'),
];

if (isset($pdo) && $pdo instanceof PDO && toy_module_enabled($pdo, 'seo')) {
    $seoSettings = toy_module_settings($pdo, 'seo');
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

toy_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <?php echo toy_render_output_slot($pdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content']); ?>
        <h1><?php echo toy_e($pageTitle); ?></h1>
        <p>Toycore MVP가 설치되었습니다.</p>
        <p><a href="<?php echo toy_e(toy_url('/admin')); ?>">관리자 화면</a></p>
        <?php echo toy_render_output_slot($pdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content']); ?>
    </main>
<?php toy_public_layout_end(); ?>
