<?php

$layoutSite = is_array($site ?? null) ? $site : null;
$layoutSeo = is_array($seo ?? null) ? $seo : [];
$layoutContent = is_string($contentHtml ?? null) ? $contentHtml : '';
$layoutPdo = $pdo instanceof PDO ? $pdo : null;
?>
<!doctype html>
<html lang="<?php echo toy_e(toy_locale()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo toy_seo_tags($layoutSeo, $layoutSite); ?>
    <?php echo toy_stylesheet_tag(); ?>
</head>
<body>
    <?php if ($layoutPdo instanceof PDO) { ?>
        <?php echo toy_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.header', 'slot_key' => 'navigation']); ?>
    <?php } ?>
    <?php echo $layoutContent; ?>
    <?php if ($layoutPdo instanceof PDO) { ?>
        <?php echo toy_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.footer', 'slot_key' => 'content']); ?>
    <?php } ?>
</body>
</html>
