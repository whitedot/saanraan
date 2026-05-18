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
            <?php echo sr_admin_menu_symbol_sprite_html(); ?>

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
