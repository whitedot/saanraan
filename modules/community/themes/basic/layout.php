<?php

$layoutSite = is_array($site ?? null) ? $site : null;
$layoutSeo = is_array($seo ?? null) ? $seo : [];
$layoutContent = is_string($contentHtml ?? null) ? $contentHtml : '';
$layoutPdo = $pdo instanceof PDO ? $pdo : null;
$layoutContext = is_array($layoutContext ?? null) ? $layoutContext : [];
$layoutStylesheets = is_array($layoutContext['stylesheets'] ?? null) ? $layoutContext['stylesheets'] : [];
$layoutStyleProfile = is_string($layoutContext['style_profile'] ?? null) ? (string) $layoutContext['style_profile'] : 'minimal';
$layoutStylesheets[] = '/modules/community/assets/community-layout.css';
$layoutSiteMenus = is_array($layoutContext['site_menus'] ?? null) ? $layoutContext['site_menus'] : [];
$layoutCleanMenuKey = static function (string $value): string {
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
};
$layoutPrimaryMenuKey = array_key_exists('primary', $layoutSiteMenus) ? $layoutCleanMenuKey((string) $layoutSiteMenus['primary']) : '';
$layoutSiteName = trim((string) ($layoutSite['name'] ?? $layoutSite['site_name'] ?? 'Saanraan'));
$layoutBrandLogoHtml = '';
$layoutMobileBrandLogoHtml = '';
$layoutBrandLinkUrl = sr_url('/community');
$layoutFaviconHtml = '';
$layoutPrimaryNavigationHtml = '';
if ($layoutPdo instanceof PDO && sr_module_enabled($layoutPdo, 'logo_manager') && is_file(SR_ROOT . '/modules/logo_manager/helpers.php')) {
    require_once SR_ROOT . '/modules/logo_manager/helpers.php';
    $layoutBrandLogoHtml = sr_logo_manager_render_logo($layoutPdo, 'public.header.desktop', $layoutSite, [
        'class' => 'community-layout-brand-logo community-layout-brand-logo-desktop',
    ]);
    $layoutMobileBrandLogoHtml = sr_logo_manager_render_logo($layoutPdo, 'public.header.mobile', $layoutSite, [
        'class' => $layoutBrandLogoHtml !== ''
            ? 'community-layout-brand-logo community-layout-brand-logo-mobile'
            : 'community-layout-brand-logo',
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
if ($layoutPdo instanceof PDO && $layoutPrimaryMenuKey !== '') {
    $layoutPrimaryNavigationHtml = sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.header', 'slot_key' => 'primary_navigation', 'menu_key' => $layoutPrimaryMenuKey]);
}
if ($layoutPrimaryNavigationHtml !== '') {
    $layoutStylesheets[] = '/modules/site_menu/assets/public.css';
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
    <?php echo sr_stylesheet_tag($layoutStylesheets, $layoutPdo, ['style_profile' => $layoutStyleProfile]); ?>
    <?php echo sr_icon_bootstrap_script(); ?>
</head>
<body class="community-layout-body">
    <header class="community-layout-header" data-community-scroll-header>
        <a class="community-layout-brand-link" href="<?php echo sr_e($layoutBrandLinkUrl); ?>">
            <?php if ($layoutBrandLogoHtml !== '' || $layoutMobileBrandLogoHtml !== '') { ?>
                <?php echo $layoutMobileBrandLogoHtml; ?>
                <?php echo $layoutBrandLogoHtml; ?>
            <?php } else { ?>
                <span class="community-layout-brand-text"><?php echo sr_e($layoutSiteName !== '' ? $layoutSiteName : 'Saanraan'); ?></span>
            <?php } ?>
        </a>
        <nav class="community-layout-nav" aria-label="<?php echo sr_e('커뮤니티 메뉴'); ?>">
            <?php echo $layoutPrimaryNavigationHtml; ?>
        </nav>
        <div class="community-layout-actions">
            <a class="community-layout-icon-button" href="<?php echo sr_e(sr_url('/community')); ?>" aria-label="<?php echo sr_e('커뮤니티'); ?>">
                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>local_mall</span>
            </a>
            <a class="community-layout-icon-button" href="<?php echo sr_e(sr_url('/account')); ?>" aria-label="<?php echo sr_e('회원'); ?>">
                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>person</span>
            </a>
        </div>
    </header>
    <div class="community-layout-main">
        <?php echo $layoutContent; ?>
    </div>
    <footer class="community-layout-footer">
        <p>Copyright <?php echo sr_e($layoutCopyrightYear); ?> <?php echo sr_e($layoutSiteName !== '' ? $layoutSiteName : 'Saanraan'); ?>.</p>
    </footer>
    <script src="<?php echo sr_e(sr_asset_url('/assets/common-ui.js')); ?>" defer></script>
    <script src="<?php echo sr_e(sr_asset_url('/modules/community/assets/community-layout.js')); ?>" defer></script>
</body>
</html>
