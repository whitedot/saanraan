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

if (isset($pdo) && $pdo instanceof PDO && sr_module_enabled($pdo, 'seo')) {
    $seoSettings = sr_module_settings($pdo, 'seo');
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

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <?php echo sr_render_output_slot($pdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content']); ?>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p>Saanraan MVP가 설치되었습니다.</p>
        <p><a href="<?php echo sr_e(sr_url('/admin')); ?>">관리자 화면</a></p>
        <?php echo sr_render_output_slot($pdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content']); ?>
    </main>
<?php sr_public_layout_end(); ?>
