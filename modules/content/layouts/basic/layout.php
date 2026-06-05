<?php

$layoutSite = is_array($site ?? null) ? $site : null;
$layoutSeo = is_array($seo ?? null) ? $seo : [];
$layoutContent = is_string($contentHtml ?? null) ? $contentHtml : '';
$layoutPdo = $pdo instanceof PDO ? $pdo : null;
$layoutContext = is_array($layoutContext ?? null) ? $layoutContext : [];
$layoutStylesheets = is_array($layoutContext['stylesheets'] ?? null) ? $layoutContext['stylesheets'] : [];
$layoutStylesheets[] = '/modules/content/assets/layout.css';
$layoutSiteName = trim((string) ($layoutSite['name'] ?? $layoutSite['site_name'] ?? 'Saanraan'));
$layoutBrandLogoHtml = '';
$layoutMobileBrandLogoHtml = '';
$layoutBrandLinkUrl = sr_url('/content');
$layoutFaviconHtml = '';
if ($layoutPdo instanceof PDO && sr_module_enabled($layoutPdo, 'logo_manager') && is_file(SR_ROOT . '/modules/logo_manager/helpers.php')) {
    require_once SR_ROOT . '/modules/logo_manager/helpers.php';
    $layoutBrandLogoHtml = sr_logo_manager_render_logo($layoutPdo, 'public.header.desktop', $layoutSite, [
        'class' => 'content-layout-brand-logo content-layout-brand-logo-desktop',
    ]);
    $layoutMobileBrandLogoHtml = sr_logo_manager_render_logo($layoutPdo, 'public.header.mobile', $layoutSite, [
        'class' => $layoutBrandLogoHtml !== ''
            ? 'content-layout-brand-logo content-layout-brand-logo-mobile'
            : 'content-layout-brand-logo',
    ]);
    $layoutBrandLogo = sr_logo_manager_active_logo($layoutPdo, 'public.header.desktop');
    if (is_array($layoutBrandLogo)) {
        $layoutBrandLink = sr_logo_manager_clean_url((string) ($layoutBrandLogo['link_url'] ?? ''));
        if ($layoutBrandLink !== '') {
            $layoutBrandLinkUrl = sr_logo_manager_url_for_output($layoutBrandLink);
        }
    }
    $layoutFaviconHtml = sr_logo_manager_favicon_link_tag($layoutPdo);
}
$layoutCopyrightYear = date('Y');
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo sr_seo_tags($layoutSeo, $layoutSite); ?>
    <?php echo $layoutFaviconHtml; ?>
    <?php echo sr_stylesheet_tag($layoutStylesheets, $layoutPdo); ?>
    <?php echo sr_icon_bootstrap_script(); ?>
</head>
<body>
    <header class="content-layout-header">
        <div class="content-layout-header-left">
            <button type="button" class="content-layout-icon-button" aria-label="<?php echo sr_e('메뉴'); ?>">
                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>menu</span>
            </button>
        </div>
        <div class="content-layout-header-center">
            <a class="content-layout-brand-link" href="<?php echo sr_e($layoutBrandLinkUrl); ?>">
                <?php if ($layoutBrandLogoHtml !== '' || $layoutMobileBrandLogoHtml !== '') { ?>
                    <?php echo $layoutMobileBrandLogoHtml; ?>
                    <?php echo $layoutBrandLogoHtml; ?>
                <?php } else { ?>
                    <span class="content-layout-brand-text"><?php echo sr_e($layoutSiteName !== '' ? $layoutSiteName : 'Saanraan'); ?></span>
                <?php } ?>
            </a>
        </div>
        <div class="content-layout-header-right">
            <button type="button" class="content-layout-icon-button" aria-label="<?php echo sr_e('검색'); ?>">
                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>search</span>
            </button>
        </div>
    </header>
    <div class="content-layout-main">
        <?php echo $layoutContent; ?>
    </div>
    <footer class="content-layout-footer">
        <p>Copyright <?php echo sr_e($layoutCopyrightYear); ?> <?php echo sr_e($layoutSiteName !== '' ? $layoutSiteName : 'Saanraan'); ?>.</p>
    </footer>
    <script src="<?php echo sr_e(sr_asset_url('/assets/common-ui.js')); ?>" defer></script>
</body>
</html>
