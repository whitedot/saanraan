<?php

$layoutSite = is_array($site ?? null) ? $site : null;
$layoutSeo = is_array($seo ?? null) ? $seo : [];
$layoutContent = is_string($contentHtml ?? null) ? $contentHtml : '';
$layoutPdo = $pdo instanceof PDO ? $pdo : null;
$layoutContext = is_array($layoutContext ?? null) ? $layoutContext : [];
$layoutStylesheets = is_array($layoutContext['stylesheets'] ?? null) ? $layoutContext['stylesheets'] : [];
$layoutSiteName = trim((string) ($layoutSite['name'] ?? $layoutSite['site_name'] ?? 'Saanraan'));
$layoutBrandLogoHtml = '';
$layoutMobileBrandLogoHtml = '';
$layoutBrandLinkUrl = sr_url('/');
$layoutFaviconHtml = '';
if ($layoutPdo instanceof PDO && sr_module_enabled($layoutPdo, 'logo_manager') && is_file(SR_ROOT . '/modules/logo_manager/helpers.php')) {
    require_once SR_ROOT . '/modules/logo_manager/helpers.php';
    $layoutBrandLogoHtml = sr_logo_manager_render_logo($layoutPdo, 'public_header', $layoutSite, [
        'class' => 'public-site-brand-logo public-site-brand-logo-desktop',
    ]);
    $layoutMobileBrandLogoHtml = sr_logo_manager_render_logo($layoutPdo, 'mobile', $layoutSite, [
        'class' => $layoutBrandLogoHtml !== ''
            ? 'public-site-brand-logo public-site-brand-logo-mobile'
            : 'public-site-brand-logo',
    ]);
    $layoutBrandAssignment = sr_logo_manager_active_assignment($layoutPdo, 'public_header');
    if (is_array($layoutBrandAssignment)) {
        $layoutBrandLink = sr_logo_manager_clean_url((string) ($layoutBrandAssignment['link_url'] ?? ''));
        if ($layoutBrandLink !== '') {
            $layoutBrandLinkUrl = sr_logo_manager_url_for_output($layoutBrandLink);
        }
    }
    $layoutFaviconHtml = sr_logo_manager_favicon_link_tag($layoutPdo);
}
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo sr_seo_tags($layoutSeo, $layoutSite); ?>
    <?php echo $layoutFaviconHtml; ?>
    <?php echo sr_stylesheet_tag($layoutStylesheets); ?>
    <?php echo sr_material_icon_bootstrap_script(); ?>
</head>
<body>
    <header class="public-site-header">
        <a class="public-site-brand-link" href="<?php echo sr_e($layoutBrandLinkUrl); ?>">
            <?php if ($layoutBrandLogoHtml !== '' || $layoutMobileBrandLogoHtml !== '') { ?>
                <?php echo $layoutMobileBrandLogoHtml; ?>
                <?php echo $layoutBrandLogoHtml; ?>
            <?php } else { ?>
                <span class="public-site-brand-text"><?php echo sr_e($layoutSiteName !== '' ? $layoutSiteName : 'Saanraan'); ?></span>
            <?php } ?>
        </a>
        <?php if ($layoutPdo instanceof PDO) { ?>
            <?php echo sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.header', 'slot_key' => 'navigation']); ?>
        <?php } ?>
    </header>
    <?php echo $layoutContent; ?>
    <?php if ($layoutPdo instanceof PDO) { ?>
        <?php echo sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.footer', 'slot_key' => 'content']); ?>
    <?php } ?>
    <script src="<?php echo sr_e(sr_asset_url('/assets/common-ui.js')); ?>" defer></script>
</body>
</html>
