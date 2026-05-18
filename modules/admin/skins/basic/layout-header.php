<?php

$adminPageTitle = $adminPageTitle ?? '관리자';
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
    'navigation_items' => [],
];
if (isset($pdo) && $pdo instanceof PDO) {
    $adminShell = sr_admin_shell_view($pdo, $site ?? null, (string) $adminPageTitle, (string) $adminPageSubtitle, (string) $adminContainerClass);
}
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="<?php echo sr_e(sr_color_scheme($site ?? null)); ?>">
<head>
    <meta charset="utf-8">
    <script>
    (function () {
        var key = 'sr_admin_theme';
        var saved = null;
        try {
            saved = localStorage.getItem(key);
        } catch (e) {
            saved = null;
        }
        var dark = saved === 'dark' || (!saved && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo sr_seo_tags($seo, $site ?? null); ?>
    <?php echo sr_admin_stylesheet_tag(); ?>
</head>
<body>
    <div id="to_content" class="admin-skip-link"><a href="#container">본문 바로가기</a></div>

    <header id="hd" class="admin-sidebar-frame">
        <h1 class="sr-only"><?php echo sr_e((string) $adminShell['site_title']); ?></h1>

        <nav id="gnb" class="admin-sidebar" aria-label="관리자 메뉴">
            <svg class="admin-nav-icon-sprite" aria-hidden="true" focusable="false">
                <symbol id="admin-menu-icon-settings" viewBox="0 0 24 24">
                    <path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065"></path>
                    <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"></path>
                </symbol>
                <symbol id="admin-menu-icon-admin-mode" viewBox="0 0 24 24">
                    <path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3"></path>
                    <path d="M11 11a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                    <path d="M12 12l0 2.5"></path>
                </symbol>
                <symbol id="admin-menu-icon-users" viewBox="0 0 24 24">
                    <path d="M5 7a4 4 0 1 0 8 0a4 4 0 1 0 -8 0"></path>
                    <path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    <path d="M21 21v-2a4 4 0 0 0 -3 -3.85"></path>
                </symbol>
                <symbol id="admin-menu-icon-user" viewBox="0 0 24 24">
                    <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"></path>
                    <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"></path>
                </symbol>
                <symbol id="admin-menu-icon-content" viewBox="0 0 24 24">
                    <path d="M5 4h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1"></path>
                    <path d="M5 16h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1"></path>
                    <path d="M15 12h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1"></path>
                    <path d="M15 4h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1"></path>
                </symbol>
                <symbol id="admin-menu-icon-stats" viewBox="0 0 24 24">
                    <path d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -6"></path>
                    <path d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -10"></path>
                    <path d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -14"></path>
                    <path d="M4 20h14"></path>
                </symbol>
                <symbol id="admin-menu-icon-home" viewBox="0 0 24 24">
                    <path d="M5 12l-2 0l9 -9l9 9l-2 0"></path>
                    <path d="M5 12v7a2 2 0 0 0 2 2h3m4 0h3a2 2 0 0 0 2 -2v-7"></path>
                    <path d="M10 12h4v9h-4z"></path>
                </symbol>
                <symbol id="admin-menu-icon-folder" viewBox="0 0 24 24">
                    <path d="M5 4h4l3 3h7a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-11a2 2 0 0 1 2 -2"></path>
                </symbol>
                <symbol id="admin-menu-icon-image" viewBox="0 0 24 24">
                    <path d="M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2"></path>
                    <path d="M8 11a2 2 0 1 0 0 -4a2 2 0 0 0 0 4"></path>
                    <path d="M21 15l-5 -5l-11 9"></path>
                </symbol>
                <symbol id="admin-menu-icon-layers" viewBox="0 0 24 24">
                    <path d="M12 3l9 5l-9 5l-9 -5l9 -5"></path>
                    <path d="M3 13l9 5l9 -5"></path>
                    <path d="M3 18l9 5l9 -5"></path>
                </symbol>
                <symbol id="admin-menu-icon-search" viewBox="0 0 24 24">
                    <path d="M10 18a8 8 0 1 0 0 -16a8 8 0 0 0 0 16"></path>
                    <path d="M21 21l-5.35 -5.35"></path>
                </symbol>
                <symbol id="admin-menu-icon-menu-list" viewBox="0 0 24 24">
                    <path d="M9 6h11"></path>
                    <path d="M9 12h11"></path>
                    <path d="M9 18h11"></path>
                    <path d="M4 6h.01"></path>
                    <path d="M4 12h.01"></path>
                    <path d="M4 18h.01"></path>
                </symbol>
                <symbol id="admin-menu-icon-bell" viewBox="0 0 24 24">
                    <path d="M10 5a2 2 0 0 1 4 0a7 7 0 0 1 4 6v3l2 3h-16l2 -3v-3a7 7 0 0 1 4 -6"></path>
                    <path d="M9 17v1a3 3 0 0 0 6 0v-1"></path>
                </symbol>
                <symbol id="admin-menu-icon-shield" viewBox="0 0 24 24">
                    <path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3"></path>
                    <path d="M9 12l2 2l4 -4"></path>
                </symbol>
                <symbol id="admin-menu-icon-coins" viewBox="0 0 24 24">
                    <path d="M9 8c0 2.2 3.13 4 7 4s7 -1.8 7 -4s-3.13 -4 -7 -4s-7 1.8 -7 4"></path>
                    <path d="M9 8v4c0 2.2 3.13 4 7 4s7 -1.8 7 -4v-4"></path>
                    <path d="M3 12c0 2.2 3.13 4 7 4"></path>
                    <path d="M3 12v4c0 2.2 3.13 4 7 4c2.1 0 3.98 -.53 5.26 -1.36"></path>
                </symbol>
                <symbol id="admin-menu-icon-wallet" viewBox="0 0 24 24">
                    <path d="M4 7h14a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2"></path>
                    <path d="M16 12h4v4h-4a2 2 0 0 1 0 -4"></path>
                    <path d="M6 7a3 3 0 0 1 3 -3h8"></path>
                </symbol>
                <symbol id="admin-menu-icon-gift" viewBox="0 0 24 24">
                    <path d="M3 8h18v4h-18z"></path>
                    <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7"></path>
                    <path d="M12 8v13"></path>
                    <path d="M12 8h-3.5a2.5 2.5 0 1 1 2.5 -2.5v2.5"></path>
                    <path d="M12 8h3.5a2.5 2.5 0 1 0 -2.5 -2.5v2.5"></path>
                </symbol>
                <symbol id="admin-menu-icon-message-circle" viewBox="0 0 24 24">
                    <path d="M3 20l1.3 -3.9a8.5 8.5 0 1 1 3.6 3.6l-4.9 1.3"></path>
                    <path d="M8 12h.01"></path>
                    <path d="M12 12h.01"></path>
                    <path d="M16 12h.01"></path>
                </symbol>
                <symbol id="admin-menu-icon-sidebar-toggle" viewBox="0 0 24 24">
                    <path d="M4 6a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2l0 -12"></path>
                    <path d="M9 4v16"></path>
                    <path d="M15 10l-2 2l2 2"></path>
                </symbol>
                <symbol id="admin-menu-icon-menu" viewBox="0 0 24 24">
                    <path d="M4 6l16 0"></path>
                    <path d="M4 12l16 0"></path>
                    <path d="M4 18l16 0"></path>
                </symbol>
                <symbol id="admin-menu-icon-moon-stars" viewBox="0 0 24 24">
                    <path d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1 -8.313 -12.454l0 .008"></path>
                    <path d="M17 4a2 2 0 0 0 2 2a2 2 0 0 0 -2 2a2 2 0 0 0 -2 -2a2 2 0 0 0 2 -2"></path>
                    <path d="M19 11h2m-1 -1v2"></path>
                </symbol>
                <symbol id="admin-menu-icon-sun" viewBox="0 0 24 24">
                    <path d="M8 12a4 4 0 1 0 8 0a4 4 0 1 0 -8 0"></path>
                    <path d="M3 12h1m8 -9v1m8 8h1m-9 8v1m-6.4 -15.4l.7 .7m12.1 -.7l-.7 .7m0 11.4l.7 .7m-12.1 -.7l-.7 .7"></path>
                </symbol>
                <symbol id="admin-menu-icon-chevron-down" viewBox="0 0 24 24">
                    <path d="M6 9l6 6l6 -6"></path>
                </symbol>
            </svg>

            <h2 class="admin-sidebar-brand">
                <a class="admin-sidebar-brand-link" href="<?php echo sr_e((string) $adminShell['dashboard_url']); ?>">
                    <span class="admin-sidebar-brand-mark" aria-hidden="true">
                        <svg class="admin-shell-control-icon" focusable="false" viewBox="0 0 24 24">
                            <use href="#admin-menu-icon-admin-mode"></use>
                        </svg>
                    </span>
                    <span class="admin-sidebar-brand-name"><?php echo sr_e((string) $adminShell['site_title']); ?></span>
                </a>
                <button type="button" id="btn_gnb" class="admin-sidebar-toggle" aria-label="사이드바 축소/확장" aria-pressed="false">
                    <span aria-hidden="true">
                        <svg class="admin-shell-control-icon" focusable="false" viewBox="0 0 24 24">
                            <use href="#admin-menu-icon-sidebar-toggle"></use>
                        </svg>
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
                                    <button type="button" class="admin-nav-trigger" title="<?php echo sr_e((string) $navItem['title']); ?>" aria-expanded="<?php echo sr_e((string) $navItem['aria_expanded']); ?>">
                                        <span class="admin-nav-trigger-main">
                                            <?php
                                            $navIcon = isset($navItem['icon']) && is_array($navItem['icon'])
                                                ? $navItem['icon']
                                                : ['type' => 'symbol', 'name' => (string) ($navItem['icon_id'] ?? 'folder')];
                                            ?>
                                            <?php if (($navIcon['type'] ?? '') === 'asset') { ?>
                                                <img class="admin-nav-icon admin-nav-icon-image" src="<?php echo sr_e((string) ($navIcon['url'] ?? '')); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async">
                                            <?php } else { ?>
                                                <svg class="admin-nav-icon admin-nav-icon-symbol" aria-hidden="true" focusable="false" viewBox="0 0 24 24">
                                                    <use href="#admin-menu-icon-<?php echo sr_e((string) ($navIcon['name'] ?? $navItem['icon_id'] ?? 'folder')); ?>"></use>
                                                </svg>
                                            <?php } ?>
                                            <span class="admin-nav-trigger-label"><?php echo sr_e((string) $navItem['title']); ?></span>
                                        </span>
                                        <span class="admin-nav-caret" aria-hidden="true">
                                            <svg class="admin-nav-caret-icon" focusable="false" viewBox="0 0 24 24">
                                                <use href="#admin-menu-icon-chevron-down"></use>
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="admin-nav-panel<?php echo sr_e((string) $navItem['panel_class']); ?>">
                                        <ul class="admin-nav-sub-list">
                                            <?php foreach ($navItem['sub_items'] as $subItem) { ?>
                                                <li class="admin-nav-sub-item<?php echo sr_e((string) $subItem['item_class']); ?>" data-menu="<?php echo sr_e((string) $subItem['menu_code']); ?>">
                                                    <a href="<?php echo sr_e((string) $subItem['url']); ?>"><?php echo sr_e((string) $subItem['title']); ?></a>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    </div>
                                </li>
                            <?php } ?>
                        <?php } ?>
                    </ul>
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
                <button type="button" id="btn_gnb_mobile" class="admin-mobile-menu-button" aria-controls="gnb" aria-expanded="false" aria-label="메뉴 열기">
                    <svg class="admin-shell-control-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24">
                        <use href="#admin-menu-icon-menu"></use>
                    </svg>
                </button>
                <div class="hd_breadcrumb admin-breadcrumb">
                    <span>대시보드</span>
                    <span>/</span>
                    <strong><?php echo sr_e((string) $adminShell['page_title']); ?></strong>
                </div>
            </div>

            <div class="hd_top_right admin-topbar-right">
                <div id="tnb" class="admin-toolbar">
                    <ul>
                        <li class="tnb_li admin-toolbar-item">
                            <button type="button" id="admin_theme_toggle" class="tnb_icon_btn admin-toolbar-icon-button" aria-pressed="false" aria-label="다크 모드 전환" title="다크 모드 전환">
                                <svg class="admin-shell-control-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24">
                                    <use id="admin_theme_toggle_icon_use" href="#admin-menu-icon-moon-stars"></use>
                                </svg>
                            </button>
                        </li>
                        <li class="tnb_li admin-toolbar-item">
                            <a class="tnb_icon_btn admin-toolbar-icon-button" href="<?php echo sr_e((string) $adminShell['site_home_url']); ?>" target="_blank" title="메인" aria-label="메인">
                                <svg class="admin-shell-control-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24">
                                    <use href="#admin-menu-icon-home"></use>
                                </svg>
                            </a>
                        </li>
                        <li class="tnb_li admin-toolbar-item relative">
                            <button type="button" class="tnb_mb_btn tnb_icon_btn admin-toolbar-icon-button" aria-label="관리자 메뉴" title="관리자 메뉴">
                                <svg class="admin-shell-control-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24">
                                    <use href="#admin-menu-icon-user"></use>
                                </svg>
                            </button>
                            <ul class="tnb_mb_area admin-toolbar-menu hidden">
                                <li><a href="<?php echo sr_e((string) $adminShell['profile_url']); ?>">계정 정보</a></li>
                                <li>
                                    <form method="post" action="<?php echo sr_e((string) $adminShell['logout_url']); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <button type="submit">로그아웃</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="container" class="admin-content <?php echo sr_e((string) $adminShell['container_class']); ?>">
            <h1 id="container_title" class="admin-content-title"><?php echo sr_e((string) $adminShell['page_title']); ?></h1>
            <?php if ((string) $adminShell['page_subtitle'] !== '') { ?>
                <p id="container_subtitle" class="admin-content-subtitle"><?php echo sr_e((string) $adminShell['page_subtitle']); ?></p>
            <?php } ?>
            <?php sr_admin_begin_content_capture(); ?>
