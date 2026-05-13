<?php

$layoutSite = is_array($site ?? null) ? $site : null;
$layoutSeo = is_array($seo ?? null) ? $seo : [];
$layoutContent = is_string($contentHtml ?? null) ? $contentHtml : '';
$layoutPdo = $pdo instanceof PDO ? $pdo : null;
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="<?php echo sr_e(sr_color_scheme($layoutSite)); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo sr_seo_tags($layoutSeo, $layoutSite); ?>
    <?php echo sr_stylesheet_tag(); ?>
</head>
<body>
    <?php if ($layoutPdo instanceof PDO) { ?>
        <?php echo sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.header', 'slot_key' => 'navigation']); ?>
    <?php } ?>
    <?php echo $layoutContent; ?>
    <?php if ($layoutPdo instanceof PDO) { ?>
        <?php echo sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.footer', 'slot_key' => 'content']); ?>
    <?php } ?>
</body>
</html>
