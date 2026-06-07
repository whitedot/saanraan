<?php

$layoutSite = is_array($site ?? null) ? $site : null;
$layoutSeo = is_array($seo ?? null) ? $seo : [];
$layoutContent = is_string($contentHtml ?? null) ? $contentHtml : '';
$layoutPdo = $pdo instanceof PDO ? $pdo : null;
$layoutContext = is_array($layoutContext ?? null) ? $layoutContext : [];
$layoutStylesheets = is_array($layoutContext['stylesheets'] ?? null) ? $layoutContext['stylesheets'] : [];
$layoutStyleProfile = is_string($layoutContext['style_profile'] ?? null) ? (string) $layoutContext['style_profile'] : 'minimal';
$layoutStylesheets[] = '/modules/content/assets/layout.css';
$layoutSiteMenus = is_array($layoutContext['site_menus'] ?? null) ? $layoutContext['site_menus'] : [];
$layoutCleanMenuKey = static function (string $value): string {
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
};
$layoutPrimaryMenuKey = array_key_exists('primary', $layoutSiteMenus) ? $layoutCleanMenuKey((string) $layoutSiteMenus['primary']) : '';
$layoutFooterMenuSlots = [
    'secondary' => ['slot_key' => 'secondary_navigation', 'label' => '보조 메뉴'],
    'tertiary' => ['slot_key' => 'tertiary_navigation', 'label' => '추가 메뉴 1'],
    'quaternary' => ['slot_key' => 'quaternary_navigation', 'label' => '추가 메뉴 2'],
    'quinary' => ['slot_key' => 'quinary_navigation', 'label' => '추가 메뉴 3'],
];
$layoutSiteName = trim((string) ($layoutSite['name'] ?? $layoutSite['site_name'] ?? 'Saanraan'));
$layoutBrandLogoHtml = '';
$layoutMobileBrandLogoHtml = '';
$layoutBrandLinkUrl = sr_url('/content');
$layoutFaviconHtml = '';
$layoutPrimaryNavigationHtml = '';
$layoutFooterNavigationHtml = [];
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
if ($layoutPdo instanceof PDO && $layoutPrimaryMenuKey !== '') {
    $layoutPrimaryNavigationHtml = sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.header', 'slot_key' => 'primary_navigation', 'menu_key' => $layoutPrimaryMenuKey]);
}
if ($layoutPdo instanceof PDO && $layoutPrimaryMenuKey === '' && function_exists('sr_content_primary_menu_fallback_links')) {
    $layoutFallbackLinks = sr_content_primary_menu_fallback_links($layoutPdo);
    if ($layoutFallbackLinks !== []) {
        $layoutPrimaryNavigationHtml = '<div class="sr-site-menu sr-site-menu-fallback" data-site-menu-fallback="primary"><ul class="sr-site-menu-list sr-site-menu-list-depth-1">';
        foreach ($layoutFallbackLinks as $layoutFallbackLink) {
            $layoutFallbackLabel = (string) ($layoutFallbackLink['label'] ?? '');
            $layoutFallbackUrl = (string) ($layoutFallbackLink['url'] ?? '');
            if ($layoutFallbackLabel === '' || $layoutFallbackUrl === '') {
                continue;
            }
            $layoutPrimaryNavigationHtml .= '<li class="sr-site-menu-item"><a class="sr-site-menu-link" href="' . sr_e(sr_url($layoutFallbackUrl)) . '">' . sr_e($layoutFallbackLabel) . '</a></li>';
        }
        $layoutPrimaryNavigationHtml .= '</ul></div>';
    }
}
if ($layoutPdo instanceof PDO) {
    foreach ($layoutFooterMenuSlots as $layoutFooterMenuContextKey => $layoutFooterMenuSlot) {
        $layoutFooterMenuKey = array_key_exists($layoutFooterMenuContextKey, $layoutSiteMenus) ? $layoutCleanMenuKey((string) $layoutSiteMenus[$layoutFooterMenuContextKey]) : '';
        if ($layoutFooterMenuKey === '') {
            continue;
        }
        $layoutFooterMenuSlotKey = (string) ($layoutFooterMenuSlot['slot_key'] ?? '');
        $layoutFooterMenuHtml = sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.footer', 'slot_key' => $layoutFooterMenuSlotKey, 'menu_key' => $layoutFooterMenuKey]);
        if ($layoutFooterMenuHtml !== '') {
            $layoutFooterNavigationHtml[$layoutFooterMenuSlotKey] = [
                'html' => $layoutFooterMenuHtml,
                'label' => (string) ($layoutFooterMenuSlot['label'] ?? '하단 메뉴'),
            ];
        }
    }
}
if ($layoutPrimaryNavigationHtml !== '' || $layoutFooterNavigationHtml !== []) {
    $layoutStylesheets[] = '/modules/site_menu/assets/public.css';
}
$layoutNotificationEnabled = false;
$layoutNotificationHasAccount = false;
$layoutNotificationSummary = ['unread' => 0, 'items' => []];
if (
    $layoutPdo instanceof PDO
    && sr_module_enabled($layoutPdo, 'notification')
    && is_file(SR_ROOT . '/modules/member/helpers.php')
    && is_file(SR_ROOT . '/modules/notification/helpers.php')
) {
    $layoutNotificationEnabled = true;
    require_once SR_ROOT . '/modules/member/helpers.php';
    require_once SR_ROOT . '/modules/notification/helpers.php';
    $layoutCurrentAccount = sr_member_current_account($layoutPdo);
    if (is_array($layoutCurrentAccount)) {
        $layoutNotificationSummary = sr_notification_public_header_summary($layoutPdo, (int) $layoutCurrentAccount['id'], 5);
        $layoutNotificationHasAccount = true;
    }
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
<body class="content-layout-body">
    <header class="content-layout-header" data-content-scroll-header>
        <a class="content-layout-brand-link" href="<?php echo sr_e($layoutBrandLinkUrl); ?>">
            <?php if ($layoutBrandLogoHtml !== '' || $layoutMobileBrandLogoHtml !== '') { ?>
                <?php echo $layoutMobileBrandLogoHtml; ?>
                <?php echo $layoutBrandLogoHtml; ?>
            <?php } else { ?>
                <span class="content-layout-brand-text"><?php echo sr_e($layoutSiteName !== '' ? $layoutSiteName : 'Saanraan'); ?></span>
            <?php } ?>
        </a>
        <nav class="content-layout-nav" aria-label="<?php echo sr_e('콘텐츠 메뉴'); ?>">
            <?php echo $layoutPrimaryNavigationHtml; ?>
        </nav>
        <div class="content-layout-actions">
            <?php if ($layoutNotificationEnabled) { ?>
                <details class="content-layout-notification-menu">
                    <summary class="content-layout-icon-button content-layout-notification-button" aria-label="<?php echo sr_e('알림'); ?>">
                        <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>notifications</span>
                        <?php if ((int) $layoutNotificationSummary['unread'] > 0) { ?>
                            <span class="content-layout-notification-badge"><?php echo sr_e((int) $layoutNotificationSummary['unread'] > 99 ? '99+' : (string) (int) $layoutNotificationSummary['unread']); ?></span>
                        <?php } ?>
                    </summary>
                    <div class="content-layout-notification-dropdown">
                        <div class="content-layout-notification-header">
                            <strong><?php echo sr_e('알림'); ?></strong>
                            <?php if ($layoutNotificationHasAccount) { ?>
                                <a href="<?php echo sr_e(sr_url('/account/notifications')); ?>"><?php echo sr_e('전체'); ?></a>
                            <?php } else { ?>
                                <a href="<?php echo sr_e(sr_url('/login')); ?>"><?php echo sr_e('로그인'); ?></a>
                            <?php } ?>
                        </div>
                        <?php if (!$layoutNotificationHasAccount) { ?>
                            <p class="content-layout-notification-empty"><?php echo sr_e('로그인 후 알림을 확인할 수 있습니다.'); ?></p>
                        <?php } elseif ($layoutNotificationSummary['items'] === []) { ?>
                            <p class="content-layout-notification-empty"><?php echo sr_e('새 알림이 없습니다.'); ?></p>
                        <?php } else { ?>
                            <div class="content-layout-notification-list">
                                <?php foreach ($layoutNotificationSummary['items'] as $layoutNotification) { ?>
                                    <?php
                                    $layoutNotificationLinkAttributes = sr_notification_item_link_attributes($layoutNotification, (int) ($layoutCurrentAccount['id'] ?? 0), true);
                                    $layoutNotificationBody = trim(strip_tags((string) ($layoutNotification['body_text'] ?? '')));
                                    $layoutNotificationClass = (string) ($layoutNotification['status'] ?? '') === 'unread'
                                        ? 'content-layout-notification-item is-unread'
                                        : 'content-layout-notification-item';
                                    ?>
                                    <?php if ($layoutNotificationLinkAttributes !== '') { ?>
                                        <a class="<?php echo sr_e($layoutNotificationClass); ?>"<?php echo $layoutNotificationLinkAttributes; ?>>
                                    <?php } else { ?>
                                        <div class="<?php echo sr_e($layoutNotificationClass); ?>">
                                    <?php } ?>
                                        <span class="content-layout-notification-title"><?php echo sr_e((string) ($layoutNotification['title'] ?? '알림')); ?></span>
                                        <?php if ($layoutNotificationBody !== '') { ?>
                                            <span class="content-layout-notification-text"><?php echo sr_e(sr_notification_clean_single_line($layoutNotificationBody, 80)); ?></span>
                                        <?php } ?>
                                        <span class="content-layout-notification-date"><?php echo sr_e((string) ($layoutNotification['created_at'] ?? '')); ?></span>
                                    <?php if ($layoutNotificationLinkAttributes !== '') { ?>
                                        </a>
                                    <?php } else { ?>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </details>
            <?php } ?>
            <a class="content-layout-icon-button" href="<?php echo sr_e(sr_url('/account')); ?>" aria-label="<?php echo sr_e('회원'); ?>">
                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>person</span>
            </a>
        </div>
    </header>
    <div class="content-layout-main">
        <?php echo $layoutContent; ?>
    </div>
    <footer class="content-layout-footer">
        <?php foreach ($layoutFooterNavigationHtml as $layoutFooterNavigationSlotKey => $layoutFooterNavigation) { ?>
            <nav class="content-layout-footer-nav content-layout-footer-nav-<?php echo sr_e($layoutFooterNavigationSlotKey); ?>" aria-label="<?php echo sr_e((string) ($layoutFooterNavigation['label'] ?? '하단 메뉴')); ?>">
                <?php echo (string) ($layoutFooterNavigation['html'] ?? ''); ?>
            </nav>
        <?php } ?>
        <p>Copyright <?php echo sr_e($layoutCopyrightYear); ?> <?php echo sr_e($layoutSiteName !== '' ? $layoutSiteName : 'Saanraan'); ?>.</p>
    </footer>
    <script src="<?php echo sr_e(sr_asset_url('/assets/common-ui.js')); ?>" defer></script>
    <script src="<?php echo sr_e(sr_asset_url('/assets/mention-input.js')); ?>" defer></script>
    <script src="<?php echo sr_e(sr_asset_url('/modules/content/assets/layout.js')); ?>" defer></script>
</body>
</html>
