<?php

$adminPageTitle = $adminPageTitle ?? sr_t('admin::ui.admin.78496a61');
$adminPageSubtitle = $adminPageSubtitle ?? '';
$adminContainerClass = $adminContainerClass ?? '';
$seo = [
    'title' => $adminPageTitle,
    'robots' => 'noindex, nofollow',
];
$adminShell = [
    'site_title' => sr_admin_shell_site_title($site ?? null),
    'page_title' => (string) $adminPageTitle,
    'page_subtitle' => (string) $adminPageSubtitle,
    'container_class' => sr_admin_shell_class_attr((string) $adminContainerClass),
    'dashboard_url' => sr_url('/admin'),
    'site_home_url' => sr_url('/'),
    'profile_url' => sr_url('/account'),
    'logout_url' => sr_url('/logout'),
    'account_display_name' => '',
    'navigation_items' => [],
    'auxiliary_links' => [],
];
if (isset($pdo) && $pdo instanceof PDO) {
    $adminShellAccountId = isset($account) && is_array($account) ? (int) ($account['id'] ?? 0) : 0;
    $adminShell = sr_admin_shell_view($pdo, $site ?? null, (string) $adminPageTitle, (string) $adminPageSubtitle, (string) $adminContainerClass, $adminShellAccountId);
}
$adminShellAccountDisplayName = trim((string) ($adminShell['account_display_name'] ?? ''));
if ($adminShellAccountDisplayName === '' && isset($account) && is_array($account)) {
    $adminShellAccountDisplayName = trim((string) ($account['display_name'] ?? ''));
}
$adminBrandLogoHtml = '';
$adminFaviconHtml = '';
$adminBrandIconUrl = '';
if (isset($pdo) && $pdo instanceof PDO && sr_module_enabled($pdo, 'logo_manager') && is_file(SR_ROOT . '/modules/logo_manager/helpers.php')) {
    require_once SR_ROOT . '/modules/logo_manager/helpers.php';
    $adminBrandLogoHtml = sr_logo_manager_render_logo($pdo, 'admin_sidebar', $site ?? null, [
        'class' => 'admin-sidebar-brand-logo',
        'alt' => '',
    ]);
    $adminBrandIconUrl = sr_logo_manager_active_url($pdo, 'favicon');
    $adminFaviconHtml = sr_logo_manager_favicon_link_tag($pdo);
}
$adminBrandInitialSource = trim((string) ($adminShell['site_title'] ?? ''));
$adminBrandInitial = $adminBrandInitialSource !== ''
    ? (function_exists('mb_substr') ? mb_substr($adminBrandInitialSource, 0, 1) : substr($adminBrandInitialSource, 0, 1))
    : 'S';
$adminBrandMarkClass = 'admin-sidebar-brand-mark';
$adminBrandMarkClass .= $adminBrandLogoHtml !== '' ? ' has-sidebar-logo' : ' has-no-sidebar-logo';
$adminBrandMarkClass .= $adminBrandIconUrl !== '' ? ' has-brand-icon' : ' has-brand-initial';
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="<?php echo sr_e(sr_admin_color_scheme(is_array($adminSettings ?? null) ? $adminSettings : [])); ?>">
<head>
    <meta charset="utf-8">
    <script>
    (function () {
        var scheme = document.documentElement.getAttribute('data-color-scheme') || 'light';
        var dark = scheme === 'dark' || (scheme === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo sr_seo_tags($seo, $site ?? null); ?>
    <?php echo $adminFaviconHtml; ?>
    <?php echo sr_admin_stylesheet_tag($pdo ?? null); ?>
    <?php echo sr_material_icon_bootstrap_script(); ?>
</head>
<body>
    <script>
    (function () {
        try {
            if (window.matchMedia && window.matchMedia('(min-width: 1024px)').matches && localStorage.getItem('sr_admin_sidebar_collapsed') === '1') {
                document.body.classList.add('admin-sidebar-condensed', 'admin-sidebar-restoring');
            }
        } catch (error) {}
    })();
    </script>
    <div id="to_content" class="admin-skip-link"><a href="#container"><?php echo sr_e(sr_t('admin::ui.text.04eb17f4')); ?></a></div>

    <header id="hd" class="admin-sidebar-frame">
        <h1 class="sr-only"><?php echo sr_e((string) $adminShell['site_title']); ?></h1>

        <nav id="gnb" class="admin-sidebar" aria-label="<?php echo sr_e(sr_t('admin::ui.admin.menu.c4a18693')); ?>">
            <h2 class="admin-sidebar-brand">
                <a class="admin-sidebar-brand-link" href="<?php echo sr_e((string) $adminShell['dashboard_url']); ?>" aria-label="<?php echo sr_e((string) $adminShell['site_title']); ?>">
                    <span class="<?php echo sr_e($adminBrandMarkClass); ?>">
                        <?php if ($adminBrandLogoHtml !== '') { ?>
                            <span class="admin-sidebar-brand-logo-wrap">
                                <?php echo $adminBrandLogoHtml; ?>
                            </span>
                        <?php } ?>
                        <span class="admin-sidebar-brand-compact" aria-hidden="true">
                        <?php if ($adminBrandIconUrl !== '') { ?>
                            <img class="admin-sidebar-brand-icon" src="<?php echo sr_e(sr_logo_manager_url_for_output($adminBrandIconUrl)); ?>" alt="" loading="eager" decoding="async">
                        <?php } else { ?>
                            <span class="admin-sidebar-brand-initial"><?php echo sr_e($adminBrandInitial); ?></span>
                        <?php } ?>
                        </span>
                    </span>
                    <span class="admin-sidebar-brand-name"><?php echo sr_e((string) $adminShell['site_title']); ?></span>
                </a>
                <button type="button" id="btn_gnb" class="admin-sidebar-toggle" aria-label="<?php echo sr_e(sr_t('admin::ui.text.076c3ee0')); ?>" aria-pressed="false">
                    <span aria-hidden="true">
                        <?php echo sr_material_icon_html('keyboard_double_arrow_left', 'admin-shell-control-icon'); ?>
                    </span>
                </button>
            </h2>

            <div class="gnb_menu_scroll_wrap admin-sidebar-scroll-wrap">
                <div class="gnb_menu_scroll admin-sidebar-scroll" id="gnbMenuScroll">
                    <ul class="admin-nav-list" id="adminNavList">
                        <?php foreach ($adminShell['navigation_items'] as $navSection) { ?>
                            <li class="admin-nav-section-label-item<?php echo sr_e((string) ($navSection['section_class'] ?? '')); ?>">
                                <span class="gnb_label admin-nav-section-label"><?php echo sr_e((string) $navSection['title']); ?></span>
                            </li>
                            <?php foreach ($navSection['groups'] as $navItem) { ?>
                                <li class="admin-nav-item<?php echo sr_e((string) $navItem['item_class']); ?>" data-menu="<?php echo sr_e((string) $navItem['menu_code']); ?>">
                                    <?php
                                    $navDirectUrl = trim((string) ($navItem['direct_url'] ?? ''));
                                    $navHasSubmenu = !empty($navItem['has_submenu']);
                                    $navTriggerLabelAttr = ' aria-label="' . sr_e((string) $navItem['title']) . '"';
                                    $navTriggerTooltipAttr = $navHasSubmenu ? '' : ' data-admin-sidebar-tooltip="' . sr_e((string) $navItem['title']) . '"';
                                    ?>
                                    <?php if ($navDirectUrl !== '') { ?>
                                        <a class="admin-nav-trigger admin-nav-direct-link" href="<?php echo sr_e($navDirectUrl); ?>"<?php echo $navTriggerLabelAttr; ?><?php echo $navTriggerTooltipAttr; ?><?php echo !empty($navItem['active']) ? ' aria-current="page"' : ''; ?>>
                                    <?php } else { ?>
                                        <button type="button" class="admin-nav-trigger"<?php echo $navTriggerLabelAttr; ?><?php echo $navTriggerTooltipAttr; ?> aria-expanded="<?php echo sr_e((string) $navItem['aria_expanded']); ?>">
                                    <?php } ?>
                                        <span class="admin-nav-trigger-main">
                                            <?php
                                            $navIcon = isset($navItem['icon']) && is_array($navItem['icon'])
                                                ? $navItem['icon']
                                                : ['type' => 'symbol', 'name' => (string) ($navItem['icon_id'] ?? 'folder')];
                                            ?>
                                            <?php if (($navIcon['type'] ?? '') === 'asset') { ?>
                                                <img class="admin-nav-icon admin-nav-icon-image" src="<?php echo sr_e((string) ($navIcon['url'] ?? '')); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async">
                                            <?php } else { ?>
                                                <?php
                                                $navIconName = (string) ($navIcon['name'] ?? '');
                                                if (($navIcon['type'] ?? '') !== 'material') {
                                                    $navIconName = sr_admin_material_icon_name((string) ($navIcon['name'] ?? $navItem['icon_id'] ?? 'folder'));
                                                }
                                                ?>
                                                <?php echo sr_material_icon_html($navIconName, 'admin-nav-icon admin-nav-icon-symbol'); ?>
                                            <?php } ?>
                                            <span class="admin-nav-trigger-label"><?php echo sr_e((string) $navItem['title']); ?></span>
                                        </span>
                                        <?php if ($navHasSubmenu) { ?>
                                            <span class="admin-nav-caret" aria-hidden="true">
                                                <?php echo sr_material_icon_html('keyboard_arrow_down', 'admin-nav-caret-icon'); ?>
                                            </span>
                                        <?php } ?>
                                    <?php if ($navDirectUrl !== '') { ?>
                                        </a>
                                    <?php } else { ?>
                                        </button>
                                    <?php } ?>
                                    <?php if ($navHasSubmenu) { ?>
                                        <div class="admin-nav-panel<?php echo sr_e((string) $navItem['panel_class']); ?>">
                                            <ul class="admin-nav-sub-list">
                                                <li class="admin-nav-sub-heading"><?php echo sr_e((string) $navItem['title']); ?></li>
                                                <?php foreach ($navItem['sub_items'] as $subItem) { ?>
                                                    <li class="admin-nav-sub-item<?php echo sr_e((string) $subItem['item_class']); ?>" data-menu="<?php echo sr_e((string) $subItem['menu_code']); ?>">
                                                        <a href="<?php echo sr_e((string) $subItem['url']); ?>"><?php echo sr_e((string) $subItem['title']); ?></a>
                                                    </li>
                                                <?php } ?>
                                            </ul>
                                        </div>
                                    <?php } ?>
                                </li>
                            <?php } ?>
                        <?php } ?>
                    </ul>
                    <?php if (!empty($adminShell['auxiliary_links']) && is_array($adminShell['auxiliary_links'])) { ?>
                        <div class="admin-sidebar-auxiliary" aria-label="<?php echo sr_e(sr_t('admin::ui.text.9c5432b6')); ?>">
                            <ul class="admin-sidebar-auxiliary-list">
                                <?php foreach ($adminShell['auxiliary_links'] as $auxiliaryLink) { ?>
                                    <?php if (!is_array($auxiliaryLink)) { continue; } ?>
                                    <li>
                                        <a class="admin-sidebar-auxiliary-link<?php echo !empty($auxiliaryLink['active']) ? ' is-current' : ''; ?>" href="<?php echo sr_e((string) ($auxiliaryLink['url'] ?? '#')); ?>"<?php echo !empty($auxiliaryLink['active']) ? ' aria-current="page"' : ''; ?>>
                                            <?php echo sr_e((string) ($auxiliaryLink['title'] ?? '')); ?>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>
                    <?php } ?>
                </div>
                <div class="gnb_scrollbar admin-sidebar-scrollbar" aria-hidden="true">
                    <div class="gnb_scrollbar_thumb admin-sidebar-scrollbar-thumb"></div>
                </div>
            </div>
        </nav>

        <div id="adminSidebarBackdrop" class="admin-sidebar-backdrop hidden"></div>
    </header>

    <div id="wrapper" class="admin-wrapper">
        <div id="hd_top" class="admin-topbar">
            <div class="hd_top_left admin-topbar-left">
                <button type="button" id="btn_gnb_mobile" class="admin-mobile-menu-button" aria-controls="gnb" aria-expanded="false" aria-label="<?php echo sr_e(sr_t('admin::ui.menu.ff7070c7')); ?>">
                    <?php echo sr_material_icon_html('menu', 'admin-shell-control-icon'); ?>
                </button>
                <div class="hd_breadcrumb admin-breadcrumb">
                    <span><?php echo sr_e(sr_t('admin::ui.dashboard.2b1a8070')); ?></span>
                    <span>/</span>
                    <strong><?php echo sr_e((string) $adminShell['page_title']); ?></strong>
                </div>
            </div>

            <div class="hd_top_right admin-topbar-right">
                <div id="tnb" class="admin-toolbar">
                    <ul>
                        <li class="tnb_li admin-toolbar-item">
                            <button type="button" id="admin_theme_toggle" class="tnb_icon_btn admin-toolbar-icon-button" aria-pressed="false" aria-label="<?php echo sr_e(sr_t('admin::ui.text.3d1bf22c')); ?>" title="<?php echo sr_e(sr_t('admin::ui.text.3d1bf22c')); ?>" data-admin-theme-url="<?php echo sr_e(sr_url('/admin/color-scheme')); ?>" data-admin-theme-csrf="<?php echo sr_e(sr_csrf_token()); ?>">
                                <?php echo sr_material_icon_html('dark_mode', 'admin-shell-control-icon', '', 'admin_theme_toggle_icon'); ?>
                            </button>
                        </li>
                        <li class="tnb_li admin-toolbar-item">
                            <a class="tnb_icon_btn admin-toolbar-icon-button" href="<?php echo sr_e((string) $adminShell['site_home_url']); ?>" target="_blank" title="<?php echo sr_e(sr_t('admin::ui.text.0b39b6a4')); ?>" aria-label="<?php echo sr_e(sr_t('admin::ui.text.0b39b6a4')); ?>">
                                <?php echo sr_material_icon_html('home', 'admin-shell-control-icon'); ?>
                            </a>
                        </li>
                        <li class="tnb_li admin-toolbar-item relative">
                            <details class="admin-profile-dropdown">
                                <summary class="tnb_mb_btn tnb_icon_btn admin-toolbar-icon-button" aria-label="<?php echo sr_e(sr_t('admin::ui.admin.menu.c4a18693')); ?>" title="<?php echo sr_e(sr_t('admin::ui.admin.menu.c4a18693')); ?>">
                                    <?php echo sr_material_icon_html('person', 'admin-shell-control-icon'); ?>
                                    <?php if ($adminShellAccountDisplayName !== '') { ?>
                                        <span class="admin-profile-name">
                                            <span class="admin-profile-name-text"><?php echo sr_e($adminShellAccountDisplayName); ?></span>
                                            <span class="admin-profile-name-suffix">님</span>
                                        </span>
                                    <?php } ?>
                                </summary>
                                <ul class="tnb_mb_area admin-toolbar-menu" role="menu" aria-orientation="vertical">
                                    <li>
                                        <a href="<?php echo sr_e((string) $adminShell['profile_url']); ?>">
                                            <?php echo sr_material_icon_html('manage_accounts', 'admin-profile-menu-icon'); ?>
                                            <span><?php echo sr_e(sr_t('admin::ui.text.25914f73')); ?></span>
                                        </a>
                                    </li>
                                    <li>
                                        <form method="post" action="<?php echo sr_e((string) $adminShell['logout_url']); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <button type="submit">
                                                <?php echo sr_material_icon_html('logout', 'admin-profile-menu-icon'); ?>
                                                <span><?php echo sr_e(sr_t('admin::ui.text.919c1b32')); ?></span>
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </details>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="container" class="admin-content <?php echo sr_e((string) $adminShell['container_class']); ?>">
            <h1 id="container_title" class="type-page-title"><?php echo sr_e((string) $adminShell['page_title']); ?></h1>
            <?php if ((string) $adminShell['page_subtitle'] !== '') { ?>
                <p id="container_subtitle" class="type-small"><?php echo sr_e((string) $adminShell['page_subtitle']); ?></p>
            <?php } ?>
            <?php sr_admin_begin_content_capture(); ?>
