<?php

$adminPageTitle = $adminPageTitle ?? sr_t('admin::ui.admin.78496a61');
$adminPageSubtitle = $adminPageSubtitle ?? '';
$adminPageSubtitleLines = [];
if (is_array($adminPageSubtitle)) {
    foreach ($adminPageSubtitle as $adminPageSubtitleLine) {
        $adminPageSubtitleLine = trim((string) $adminPageSubtitleLine);
        if ($adminPageSubtitleLine !== '') {
            $adminPageSubtitleLines[] = $adminPageSubtitleLine;
        }
    }
    $adminPageSubtitle = implode(' ', $adminPageSubtitleLines);
} else {
    $adminPageSubtitle = trim((string) $adminPageSubtitle);
    if ($adminPageSubtitle !== '') {
        $adminPageSubtitleLines[] = $adminPageSubtitle;
    }
}
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
    'account_avatar_color_class' => 'member-avatar-color-8',
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
$adminShellAccountEmail = isset($account) && is_array($account) ? trim((string) ($account['email'] ?? '')) : '';
$adminShellAccountInitialSource = $adminShellAccountDisplayName !== '' ? $adminShellAccountDisplayName : ($adminShellAccountEmail !== '' ? $adminShellAccountEmail : 'A');
$adminShellAccountInitial = function_exists('mb_substr') ? mb_substr($adminShellAccountInitialSource, 0, 1) : substr($adminShellAccountInitialSource, 0, 1);
$adminShellAccountAvatarColorClass = preg_match('/\Amember-avatar-color-(?:[0-9]|1[01])\z/', (string) ($adminShell['account_avatar_color_class'] ?? '')) === 1
    ? (string) $adminShell['account_avatar_color_class']
    : 'member-avatar-color-8';
$adminShellProfileBadgeLabel = '스탭';
if (isset($pdo) && $pdo instanceof PDO && isset($account) && is_array($account) && function_exists('sr_admin_is_owner')) {
    $adminShellProfileBadgeLabel = sr_admin_is_owner($pdo, (int) ($account['id'] ?? 0)) ? '매니저' : '스탭';
}
$adminNotificationSummary = isset($adminShell['admin_notification_summary']) && is_array($adminShell['admin_notification_summary'])
    ? $adminShell['admin_notification_summary']
    : ['open_count' => 0, 'items' => [], 'url' => sr_url('/admin/admin-notifications')];
$adminNotificationUnreadCount = max(0, (int) ($adminNotificationSummary['unread_count'] ?? $adminNotificationSummary['open_count'] ?? 0));
$adminNotificationItems = isset($adminNotificationSummary['items']) && is_array($adminNotificationSummary['items']) ? $adminNotificationSummary['items'] : [];
$adminNotificationUrl = (string) ($adminNotificationSummary['url'] ?? sr_url('/admin/admin-notifications'));
$adminNotificationReturnUrl = sr_admin_current_get_url('/admin');
$adminBrandLogoHtml = '';
$adminFaviconHtml = '';
$adminBrandIconUrl = '';
$adminBrandSidebarLogoUrl = '';
if (isset($pdo) && $pdo instanceof PDO && sr_module_enabled($pdo, 'logo_manager') && is_file(SR_ROOT . '/modules/logo_manager/helpers.php')) {
    require_once SR_ROOT . '/modules/logo_manager/helpers.php';
    $adminBrandLogoHtml = sr_logo_manager_render_logo($pdo, 'admin.sidebar', $site ?? null, [
        'class' => 'admin-sidebar-brand-logo',
        'alt' => '',
    ]);
    $adminBrandSidebarLogo = sr_logo_manager_active_logo($pdo, 'admin.sidebar');
    if (is_array($adminBrandSidebarLogo)) {
        $adminBrandSidebarLogoUrl = sr_logo_manager_logo_url($adminBrandSidebarLogo);
    }
    $adminBrandIconUrl = sr_logo_manager_active_url($pdo, 'public.app_icon');
    $adminFaviconHtml = sr_logo_manager_favicon_link_tag($pdo);
}
$adminBrandInitialSource = trim((string) ($adminShell['site_title'] ?? ''));
$adminBrandInitial = $adminBrandInitialSource !== ''
    ? (function_exists('mb_substr') ? mb_substr($adminBrandInitialSource, 0, 1) : substr($adminBrandInitialSource, 0, 1))
    : 'S';
$adminBrandMarkClass = 'admin-sidebar-brand-mark';
$adminBrandMarkClass .= $adminBrandLogoHtml !== '' ? ' has-sidebar-logo' : ' has-no-sidebar-logo';
if ($adminBrandLogoHtml === '') {
    $adminBrandMarkClass .= $adminBrandIconUrl !== '' ? ' has-brand-icon' : ' has-brand-initial';
}
$adminBrandLinkClass = 'admin-sidebar-brand-link';
$adminBrandLinkClass .= $adminBrandLogoHtml !== '' ? ' has-sidebar-logo' : ' has-no-sidebar-logo';
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="<?php echo sr_e(sr_admin_color_scheme(is_array($adminSettings ?? null) ? $adminSettings : [])); ?>">
<head>
    <meta charset="utf-8">
    <script>
    (function () {
        var scheme = document.documentElement.getAttribute('data-color-scheme') || 'light';
        try {
            var storedScheme = localStorage.getItem('sr_public_color_scheme');
            if (storedScheme === 'light' || storedScheme === 'dark' || storedScheme === 'system') {
                scheme = storedScheme;
                document.documentElement.setAttribute('data-color-scheme', scheme);
            }
        } catch (error) {}
        var dark = scheme === 'dark' || (scheme === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo sr_seo_tags($seo, $site ?? null); ?>
    <?php echo $adminFaviconHtml; ?>
    <?php echo sr_admin_stylesheet_tag($pdo ?? null); ?>
    <?php echo sr_icon_bootstrap_script(); ?>
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
                <a class="<?php echo sr_e($adminBrandLinkClass); ?>" href="<?php echo sr_e((string) $adminShell['dashboard_url']); ?>" aria-label="<?php echo sr_e((string) $adminShell['site_title']); ?>">
                    <span class="<?php echo sr_e($adminBrandMarkClass); ?>">
                        <?php if ($adminBrandLogoHtml !== '') { ?>
                            <span class="admin-sidebar-brand-logo-wrap">
                                <?php echo $adminBrandLogoHtml; ?>
                            </span>
                        <?php } ?>
                        <span class="admin-sidebar-brand-compact" aria-hidden="true">
                        <?php if ($adminBrandIconUrl !== '') { ?>
                            <img class="admin-sidebar-brand-icon" src="<?php echo sr_e(sr_logo_manager_url_for_output($adminBrandIconUrl)); ?>" alt="" loading="eager" decoding="async">
                        <?php } elseif ($adminBrandSidebarLogoUrl !== '') { ?>
                            <img class="admin-sidebar-brand-icon admin-sidebar-brand-icon-logo" src="<?php echo sr_e(sr_logo_manager_url_for_output($adminBrandSidebarLogoUrl)); ?>" alt="" loading="eager" decoding="async">
                        <?php } else { ?>
                            <span class="admin-sidebar-brand-initial"><?php echo sr_e($adminBrandInitial); ?></span>
                        <?php } ?>
                        </span>
                    </span>
                    <span class="admin-sidebar-brand-name"><?php echo sr_e((string) $adminShell['site_title']); ?></span>
                </a>
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
                                                <?php echo sr_icon($navIconName, 'admin-nav-icon admin-nav-icon-symbol'); ?>
                                            <?php } ?>
                                            <span class="admin-nav-trigger-label"><?php echo sr_e((string) $navItem['title']); ?></span>
                                        </span>
                                        <?php if ($navHasSubmenu) { ?>
                                            <span class="admin-nav-caret" aria-hidden="true">
                                                <?php echo sr_icon('keyboard_arrow_down', 'admin-nav-caret-icon'); ?>
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
                    <?php echo sr_icon('menu', 'admin-shell-control-icon'); ?>
                </button>
                <button type="button" id="btn_gnb" class="admin-sidebar-toggle" aria-label="<?php echo sr_e(sr_t('admin::ui.text.076c3ee0')); ?>" aria-pressed="false">
                    <span aria-hidden="true">
                        <?php echo sr_icon('keyboard_double_arrow_left', 'admin-shell-control-icon'); ?>
                    </span>
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
                                <?php echo sr_icon('dark_mode', 'admin-shell-control-icon', '', 'admin_theme_toggle_icon'); ?>
                            </button>
                        </li>
                        <li class="tnb_li admin-toolbar-item">
                            <a class="tnb_icon_btn admin-toolbar-icon-button" href="<?php echo sr_e((string) $adminShell['site_home_url']); ?>" target="_blank" title="<?php echo sr_e(sr_t('admin::ui.text.0b39b6a4')); ?>" aria-label="<?php echo sr_e(sr_t('admin::ui.text.0b39b6a4')); ?>">
                                <?php echo sr_icon('home', 'admin-shell-control-icon'); ?>
                            </a>
                        </li>
                        <?php if ($adminNotificationUrl !== '') { ?>
                            <li class="tnb_li admin-toolbar-item relative">
                                <details class="admin-notification-dropdown">
                                    <summary class="tnb_icon_btn admin-toolbar-icon-button" aria-label="운영 알림" title="운영 알림">
                                        <?php echo sr_icon('notifications', 'admin-shell-control-icon'); ?>
                                        <?php if ($adminNotificationUnreadCount > 0) { ?>
                                            <span class="admin-toolbar-badge" data-admin-notification-badge><?php echo sr_e((string) min(99, $adminNotificationUnreadCount)); ?></span>
                                        <?php } ?>
                                    </summary>
                                    <div class="admin-toolbar-menu admin-notification-menu" role="menu">
                                        <div class="admin-notification-menu-header">
                                            <strong>운영 알림</strong>
                                            <span data-admin-notification-count><?php echo sr_e(number_format($adminNotificationUnreadCount)); ?>건</span>
                                        </div>
                                        <ul data-admin-notification-list>
                                            <?php foreach ($adminNotificationItems as $adminNotificationItem) { ?>
                                                <?php if (!is_array($adminNotificationItem)) { continue; } ?>
                                                <?php $adminNotificationItemUrl = function_exists('sr_notification_admin_target_action_url') ? sr_notification_admin_target_action_url((string) ($adminNotificationItem['action_url'] ?? ''), (string) ($adminNotificationItem['target_type'] ?? ''), (string) ($adminNotificationItem['target_id'] ?? '')) : ''; ?>
                                                <?php $adminNotificationItemTargetUrl = $adminNotificationItemUrl !== '' ? $adminNotificationItemUrl : '/admin/admin-notifications'; ?>
                                                <?php $adminNotificationItemId = (int) ($adminNotificationItem['id'] ?? 0); ?>
                                                <li class="admin-notification-menu-item">
                                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>" class="admin-notification-menu-open-form">
                                                        <?php echo sr_csrf_field(); ?>
                                                        <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $adminNotificationItemId); ?>">
                                                        <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationItemTargetUrl); ?>">
                                                        <button type="submit" name="intent" value="mark_read" class="admin-notification-menu-open">
                                                            <span><?php echo sr_e((string) ($adminNotificationItem['title'] ?? '')); ?></span>
                                                            <?php if (function_exists('sr_notification_time_html')) { ?>
                                                                <small><?php echo sr_notification_time_html((string) ($adminNotificationItem['last_occurred_at'] ?? $adminNotificationItem['created_at'] ?? '')); ?></small>
                                                            <?php } ?>
                                                        </button>
                                                    </form>
                                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>" class="admin-notification-menu-read-form" data-admin-notification-read-form>
                                                        <?php echo sr_csrf_field(); ?>
                                                        <input type="hidden" name="intent" value="mark_read">
                                                        <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $adminNotificationItemId); ?>">
                                                        <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationReturnUrl); ?>">
                                                        <button type="submit" class="admin-notification-menu-read-button" aria-label="<?php echo sr_e((string) ($adminNotificationItem['title'] ?? '운영 알림')); ?> 읽음 처리" title="읽음 처리">
                                                            <?php echo sr_icon('close', 'admin-notification-menu-read-icon'); ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php } ?>
                                            <li class="admin-notification-menu-empty"<?php echo $adminNotificationItems === [] ? '' : ' hidden'; ?>>안 읽은 운영 알림이 없습니다.</li>
                                        </ul>
                                        <a class="admin-notification-menu-all" href="<?php echo sr_e($adminNotificationUrl); ?>">전체 보기</a>
                                    </div>
                                </details>
                            </li>
                        <?php } ?>
                        <li class="tnb_li admin-toolbar-item relative">
                            <details class="admin-profile-dropdown">
                                <summary class="tnb_mb_btn tnb_icon_btn admin-toolbar-icon-button" aria-label="<?php echo sr_e(sr_t('admin::ui.admin.menu.c4a18693')); ?>" title="<?php echo sr_e(sr_t('admin::ui.admin.menu.c4a18693')); ?>">
                                    <?php echo sr_icon('person', 'admin-shell-control-icon'); ?>
                                    <?php if ($adminShellAccountDisplayName !== '') { ?>
                                        <span class="admin-profile-name">
                                            <span class="admin-profile-name-text"><?php echo sr_e($adminShellAccountDisplayName); ?></span>
                                            <span class="admin-profile-name-suffix">님</span>
                                        </span>
                                    <?php } ?>
                                </summary>
                                <ul class="tnb_mb_area admin-toolbar-menu" role="menu" aria-orientation="vertical">
                                    <li class="admin-profile-dropdown-header" aria-hidden="true">
                                        <span class="admin-profile-avatar <?php echo sr_e($adminShellAccountAvatarColorClass); ?>"><?php echo sr_e($adminShellAccountInitial); ?></span>
                                        <span class="admin-profile-identity">
                                            <strong><?php echo sr_e($adminShellAccountDisplayName !== '' ? $adminShellAccountDisplayName : '관리자'); ?></strong>
                                            <?php if ($adminShellAccountEmail !== '') { ?>
                                                <span><?php echo sr_e($adminShellAccountEmail); ?></span>
                                            <?php } ?>
                                        </span>
                                        <span class="admin-profile-badge"><?php echo sr_e($adminShellProfileBadgeLabel); ?></span>
                                    </li>
                                    <li>
                                        <a href="<?php echo sr_e((string) $adminShell['profile_url']); ?>">
                                            <?php echo sr_icon('manage_accounts', 'admin-profile-menu-icon'); ?>
                                            <span><?php echo sr_e(sr_t('admin::ui.text.3d4bcbb8')); ?></span>
                                        </a>
                                    </li>
                                    <li>
                                        <form method="post" action="<?php echo sr_e((string) $adminShell['logout_url']); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <button type="submit">
                                                <?php echo sr_icon('logout', 'admin-profile-menu-icon'); ?>
                                                <span><?php echo sr_e(sr_t('admin::ui.logout.2bbdc014')); ?></span>
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
            <?php
            $adminPageTitleUrl = isset($adminPageTitleUrl) && is_string($adminPageTitleUrl) ? trim($adminPageTitleUrl) : '';
            $adminPageTitleHtml = sr_e((string) $adminShell['page_title']);
            if ($adminPageTitleUrl !== '') {
                $adminPageTitleHtml = '<a href="' . sr_e(sr_url($adminPageTitleUrl)) . '">' . $adminPageTitleHtml . '</a>';
            }
            ?>
            <?php if (isset($adminPageTitleActionsHtml) && is_string($adminPageTitleActionsHtml) && $adminPageTitleActionsHtml !== '') { ?>
                <div class="admin-page-titlebar">
                    <h1 id="container_title" class="type-page-title"><?php echo $adminPageTitleHtml; ?></h1>
                    <div class="admin-page-title-actions">
                        <?php echo $adminPageTitleActionsHtml; ?>
                    </div>
                </div>
            <?php } else { ?>
                <h1 id="container_title" class="type-page-title"><?php echo $adminPageTitleHtml; ?></h1>
            <?php } ?>
            <?php if ($adminPageSubtitleLines !== []) { ?>
                <p id="container_subtitle" class="type-small">
                    <?php foreach ($adminPageSubtitleLines as $adminPageSubtitleLine) { ?>
                        <span><?php echo sr_e($adminPageSubtitleLine); ?></span>
                    <?php } ?>
                </p>
            <?php } ?>
            <?php sr_admin_begin_content_capture(); ?>
