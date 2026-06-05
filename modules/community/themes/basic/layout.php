<?php

$layoutSite = is_array($site ?? null) ? $site : null;
$layoutSeo = is_array($seo ?? null) ? $seo : [];
$layoutContent = is_string($contentHtml ?? null) ? $contentHtml : '';
$layoutPdo = $pdo instanceof PDO ? $pdo : null;
$layoutContext = is_array($layoutContext ?? null) ? $layoutContext : [];
$layoutStylesheets = is_array($layoutContext['stylesheets'] ?? null) ? $layoutContext['stylesheets'] : [];
$layoutStylesheets[] = '/modules/community/assets/community-layout.css';
$layoutSiteMenus = is_array($layoutContext['site_menus'] ?? null) ? $layoutContext['site_menus'] : [];
$layoutCleanMenuKey = static function (string $value): string {
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
};
$layoutPrimaryMenuKey = array_key_exists('primary', $layoutSiteMenus) ? $layoutCleanMenuKey((string) $layoutSiteMenus['primary']) : 'header';
$layoutSecondaryMenuKey = array_key_exists('secondary', $layoutSiteMenus) ? $layoutCleanMenuKey((string) $layoutSiteMenus['secondary']) : '';
$layoutTertiaryMenuKey = array_key_exists('tertiary', $layoutSiteMenus) ? $layoutCleanMenuKey((string) $layoutSiteMenus['tertiary']) : '';
$layoutSiteName = trim((string) ($layoutSite['name'] ?? $layoutSite['site_name'] ?? 'Saanraan'));
$layoutBrandLogoHtml = '';
$layoutMobileBrandLogoHtml = '';
$layoutBrandLinkUrl = sr_url('/community');
$layoutFaviconHtml = '';
$layoutPrimaryNavigationHtml = '';
$layoutFooterNavigationHtml = '';
$layoutFooterHtml = '';
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
if ($layoutPdo instanceof PDO) {
    $layoutPrimaryNavigationHtml = sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.header', 'slot_key' => 'primary_navigation', 'menu_key' => $layoutPrimaryMenuKey]);
    $layoutFooterNavigationHtml .= sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.footer', 'slot_key' => 'secondary_navigation', 'menu_key' => $layoutSecondaryMenuKey]);
    $layoutFooterNavigationHtml .= sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.footer', 'slot_key' => 'tertiary_navigation', 'menu_key' => $layoutTertiaryMenuKey]);
    $layoutFooterHtml .= $layoutFooterNavigationHtml;
    $layoutFooterHtml .= sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.footer', 'slot_key' => 'content']);
}
if ($layoutPrimaryNavigationHtml !== '' || $layoutFooterNavigationHtml !== '') {
    $layoutStylesheets[] = '/modules/site_menu/assets/public.css';
}
$layoutBrandInitialSource = $layoutSiteName !== '' ? $layoutSiteName : 'S';
$layoutBrandInitial = function_exists('mb_substr') ? mb_substr($layoutBrandInitialSource, 0, 1) : substr($layoutBrandInitialSource, 0, 1);
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
    <header class="community-layout-header">
        <div class="community-layout-header-main">
            <button type="button" class="community-layout-icon-button" aria-label="<?php echo sr_e('메뉴'); ?>">
                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>menu</span>
            </button>
            <a class="community-layout-brand-link" href="<?php echo sr_e($layoutBrandLinkUrl); ?>">
                <?php if ($layoutBrandLogoHtml !== '' || $layoutMobileBrandLogoHtml !== '') { ?>
                    <?php echo $layoutMobileBrandLogoHtml; ?>
                    <?php echo $layoutBrandLogoHtml; ?>
                <?php } else { ?>
                    <span class="community-layout-brand-text"><?php echo sr_e($layoutSiteName !== '' ? $layoutSiteName : 'Saanraan'); ?></span>
                <?php } ?>
            </a>
            <div class="community-layout-search" role="search" aria-label="<?php echo sr_e('검색'); ?>">
                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>search</span>
                <span><?php echo sr_e('Search'); ?></span>
            </div>
        </div>
        <div class="community-layout-header-actions">
            <?php if ($layoutPrimaryNavigationHtml !== '') { ?>
                <div class="community-layout-primary-navigation">
                    <?php echo $layoutPrimaryNavigationHtml; ?>
                </div>
            <?php } ?>
            <a class="community-layout-get-app" href="<?php echo sr_e(sr_url('/community')); ?>">
                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>apps</span>
                <?php echo sr_e('Get app'); ?>
            </a>
            <a class="community-layout-action-link" href="<?php echo sr_e(sr_url('/community/message/write')); ?>">
                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>edit_square</span>
                <span><?php echo sr_e('Write'); ?></span>
            </a>
            <a class="community-layout-icon-button" href="<?php echo sr_e(sr_url('/account/notifications')); ?>" aria-label="<?php echo sr_e('알림'); ?>">
                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>notifications</span>
            </a>
            <a class="community-layout-avatar-link" href="<?php echo sr_e(sr_url('/account')); ?>" aria-label="<?php echo sr_e('내 계정'); ?>">
                <span><?php echo sr_e($layoutBrandInitial); ?></span>
            </a>
        </div>
    </header>
    <?php echo $layoutContent; ?>
    <?php if ($layoutFooterHtml !== '') { ?>
        <footer class="community-layout-footer">
            <?php echo $layoutFooterHtml; ?>
        </footer>
    <?php } ?>
    <script src="<?php echo sr_e(sr_asset_url('/assets/common-ui.js')); ?>" defer></script>
</body>
</html>
