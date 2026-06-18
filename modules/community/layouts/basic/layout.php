<?php

$layoutSite = is_array($site ?? null) ? $site : null;
$layoutSeo = is_array($seo ?? null) ? $seo : [];
$layoutContent = is_string($contentHtml ?? null) ? $contentHtml : '';
$layoutPdo = $pdo instanceof PDO ? $pdo : null;
$layoutContext = is_array($layoutContext ?? null) ? $layoutContext : [];
$layoutContextStylesheets = is_array($layoutContext['stylesheets'] ?? null) ? $layoutContext['stylesheets'] : [];
$layoutContextScripts = is_array($layoutContext['scripts'] ?? null) ? $layoutContext['scripts'] : [];
$layoutStylesheets = [];
$layoutScripts = ['/assets/common-ui.js', '/assets/mention-input.js', '/modules/community/assets/layout.js'];
$layoutStyleProfile = is_string($layoutContext['style_profile'] ?? null) ? (string) $layoutContext['style_profile'] : 'minimal';
foreach ($layoutContextStylesheets as $layoutContextStylesheet) {
    $layoutStylesheets[] = $layoutContextStylesheet;
}
foreach ($layoutContextScripts as $layoutContextScript) {
    $layoutScripts[] = $layoutContextScript;
}
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
$layoutSiteName = sr_site_display_name($layoutSite, $layoutPdo);
$layoutColorScheme = sr_color_scheme($layoutSite);
$layoutColorSchemeOptions = sr_color_scheme_options();
$layoutBrandLogoHtml = '';
$layoutMobileBrandLogoHtml = '';
$layoutBrandUsesPublicSymbol = false;
$layoutBrandLinkUrl = sr_url('/');
$layoutModuleHomeUrl = sr_url('/community');
$layoutSearchKeywordValue = sr_get_string_without_truncation('q', 100);
$layoutSearchKeyword = is_string($layoutSearchKeywordValue) ? trim(preg_replace('/\s+/', ' ', $layoutSearchKeywordValue) ?? '') : '';
$layoutFaviconHtml = '';
$layoutPrimaryNavigationHtml = '';
$layoutFooterNavigationHtml = [];
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
    if ($layoutBrandLogoHtml === '' && $layoutMobileBrandLogoHtml === '') {
        $layoutBrandLogoHtml = sr_logo_manager_render_public_symbol_logo($layoutPdo, $layoutSite, [
            'class' => 'community-layout-brand-logo community-layout-brand-symbol',
        ]);
        if ($layoutBrandLogoHtml !== '') {
            $layoutBrandUsesPublicSymbol = true;
        }
    }
    $layoutFaviconHtml = sr_logo_manager_favicon_link_tag($layoutPdo);
}
if ($layoutPdo instanceof PDO && $layoutPrimaryMenuKey !== '') {
    $layoutPrimaryNavigationHtml = sr_render_output_slot($layoutPdo, ['module_key' => 'core', 'point_key' => 'site.header', 'slot_key' => 'primary_navigation', 'menu_key' => $layoutPrimaryMenuKey]);
}
$layoutPrivacyCookieConsentHtml = '';
if ($layoutPdo instanceof PDO && sr_module_enabled($layoutPdo, 'privacy') && is_file(SR_ROOT . '/modules/privacy/helpers.php')) {
    require_once SR_ROOT . '/modules/privacy/helpers.php';
    $layoutStylesheets[] = '/modules/privacy/assets/cookie-consent.css';
    $layoutPrivacyCookieConsentHtml = sr_privacy_cookie_consent_public_html($layoutPdo);
}
if ($layoutPdo instanceof PDO && $layoutPrimaryMenuKey === '' && function_exists('sr_community_primary_menu_fallback_links')) {
    $layoutFallbackLinks = sr_community_primary_menu_fallback_links($layoutPdo);
    if ($layoutFallbackLinks !== []) {
        $layoutCurrentPath = '/' . trim(sr_request_path(), '/');
        $layoutCurrentPath = $layoutCurrentPath === '/' ? '/' : rtrim($layoutCurrentPath, '/');
        $layoutCurrentQuery = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
        parse_str(is_string($layoutCurrentQuery) ? $layoutCurrentQuery : '', $layoutCurrentQueryParams);
        $layoutCurrentCommunityBoardKey = '';
        $layoutCurrentCommunityBoardGroupKey = '';
        if (in_array($layoutCurrentPath, ['/community/board', '/community/write'], true)) {
            $layoutCurrentCommunityBoardKey = (string) ($layoutCurrentQueryParams['key'] ?? '');
        } elseif (in_array($layoutCurrentPath, ['/community/post', '/community/edit'], true)) {
            $layoutCurrentPostId = (int) ($layoutCurrentQueryParams['id'] ?? 0);
            if ($layoutCurrentPostId > 0) {
                try {
                    $layoutCurrentPostBoardStmt = $layoutPdo->prepare(
                        'SELECT b.board_key, g.group_key AS board_group_key
                         FROM sr_community_posts p
                         INNER JOIN sr_community_boards b ON b.id = p.board_id
                         LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
                         WHERE p.id = :id
                         LIMIT 1'
                    );
                    $layoutCurrentPostBoardStmt->execute(['id' => $layoutCurrentPostId]);
                    $layoutCurrentPostBoard = $layoutCurrentPostBoardStmt->fetch();
                    $layoutCurrentCommunityBoardKey = is_array($layoutCurrentPostBoard) ? (string) ($layoutCurrentPostBoard['board_key'] ?? '') : '';
                    $layoutCurrentCommunityBoardGroupKey = is_array($layoutCurrentPostBoard) ? (string) ($layoutCurrentPostBoard['board_group_key'] ?? '') : '';
                } catch (Throwable) {
                    $layoutCurrentCommunityBoardKey = '';
                    $layoutCurrentCommunityBoardGroupKey = '';
                }
            }
        }
        if ($layoutCurrentCommunityBoardKey !== '' && $layoutCurrentCommunityBoardGroupKey === '') {
            try {
                $layoutCurrentBoardGroupStmt = $layoutPdo->prepare(
                    'SELECT g.group_key
                     FROM sr_community_boards b
                     LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
                     WHERE b.board_key = :board_key
                       AND b.status = \'enabled\'
                       AND g.status = \'enabled\'
                     LIMIT 1'
                );
                $layoutCurrentBoardGroupStmt->execute(['board_key' => $layoutCurrentCommunityBoardKey]);
                $layoutCurrentBoardGroup = $layoutCurrentBoardGroupStmt->fetch();
                $layoutCurrentCommunityBoardGroupKey = is_array($layoutCurrentBoardGroup) ? (string) ($layoutCurrentBoardGroup['group_key'] ?? '') : '';
            } catch (Throwable) {
                $layoutCurrentCommunityBoardGroupKey = '';
            }
        }
        $layoutPrimaryNavigationHtml = '<div class="sr-site-menu sr-site-menu-fallback" data-site-menu-fallback="primary"><ul class="sr-site-menu-list sr-site-menu-list-depth-1">';
        foreach ($layoutFallbackLinks as $layoutFallbackLink) {
            $layoutFallbackLabel = (string) ($layoutFallbackLink['label'] ?? '');
            $layoutFallbackUrl = (string) ($layoutFallbackLink['url'] ?? '');
            if ($layoutFallbackLabel === '' || $layoutFallbackUrl === '') {
                continue;
            }
            $layoutFallbackPath = parse_url($layoutFallbackUrl, PHP_URL_PATH);
            $layoutFallbackPath = is_string($layoutFallbackPath) && $layoutFallbackPath !== '' ? '/' . trim($layoutFallbackPath, '/') : '/';
            $layoutFallbackPath = $layoutFallbackPath === '/' ? '/' : rtrim($layoutFallbackPath, '/');
            $layoutFallbackQuery = parse_url($layoutFallbackUrl, PHP_URL_QUERY);
            parse_str(is_string($layoutFallbackQuery) ? $layoutFallbackQuery : '', $layoutFallbackQueryParams);
            $layoutFallbackBoardKey = (string) ($layoutFallbackQueryParams['key'] ?? '');
            $layoutFallbackGroupKey = (string) ($layoutFallbackLink['group_key'] ?? '');
            $layoutFallbackFragment = parse_url($layoutFallbackUrl, PHP_URL_FRAGMENT);
            $layoutFallbackFragment = is_string($layoutFallbackFragment) ? $layoutFallbackFragment : '';
            if ($layoutFallbackGroupKey === '') {
                if (str_starts_with($layoutFallbackFragment, 'group-')) {
                    $layoutFallbackGroupKey = rawurldecode(substr($layoutFallbackFragment, 6));
                }
            }
            $layoutFallbackQueryMatches = $layoutFallbackQueryParams === [];
            if (!$layoutFallbackQueryMatches && is_array($layoutCurrentQueryParams)) {
                $layoutFallbackQueryMatches = true;
                foreach ($layoutFallbackQueryParams as $layoutFallbackQueryKey => $layoutFallbackQueryValue) {
                    if (!array_key_exists((string) $layoutFallbackQueryKey, $layoutCurrentQueryParams) || $layoutCurrentQueryParams[(string) $layoutFallbackQueryKey] != $layoutFallbackQueryValue) {
                        $layoutFallbackQueryMatches = false;
                        break;
                    }
                }
            }
            $layoutCurrentAttribute = ($layoutFallbackFragment === '' && $layoutFallbackPath === $layoutCurrentPath && $layoutFallbackQueryMatches) || (
                $layoutFallbackPath === '/community/board'
                && $layoutFallbackBoardKey !== ''
                && $layoutFallbackBoardKey === $layoutCurrentCommunityBoardKey
            ) || (
                $layoutFallbackGroupKey !== ''
                && $layoutFallbackGroupKey === $layoutCurrentCommunityBoardGroupKey
            ) ? ' aria-current="page"' : '';
            $layoutPrimaryNavigationHtml .= '<li class="sr-site-menu-item"><a class="sr-site-menu-link" href="' . sr_e(sr_url($layoutFallbackUrl)) . '"' . $layoutCurrentAttribute . '>' . sr_e($layoutFallbackLabel) . '</a></li>';
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
    $layoutStylesheets[] = '/modules/site_menu/assets/module.css';
}
$layoutNotificationEnabled = false;
$layoutNotificationHasAccount = false;
$layoutNotificationSummary = ['unread' => 0, 'items' => []];
$layoutMemberEnabled = false;
$layoutCurrentAccount = null;
$layoutMemberDisplayName = '내 계정';
$layoutMemberDisplayLabel = '내 계정';
$layoutMemberEmail = '';
$layoutMemberInitial = 'M';
$layoutMemberAvatarColorClass = 'member-avatar-color-8';
$layoutMemberBadgeLabel = '회원';
$layoutCommunityMemberMenuEnabled = false;
$layoutUnreadCommunityMessageCount = 0;
$layoutMemberAssetRows = [];
$layoutMemberActionRows = [];
$layoutAdminEnabled = false;
$layoutAdminUrl = sr_url('/admin');
if (
    $layoutPdo instanceof PDO
    && sr_module_enabled($layoutPdo, 'member')
    && is_file(SR_ROOT . '/modules/member/helpers.php')
) {
    $layoutMemberEnabled = true;
    require_once SR_ROOT . '/modules/member/helpers.php';
    $layoutRuntimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
    $layoutCurrentAccount = sr_member_current_account($layoutPdo);
    if (is_array($layoutCurrentAccount)) {
        $layoutCurrentAccountId = (int) ($layoutCurrentAccount['id'] ?? 0);
        $layoutMemberDisplayName = sr_member_public_name_for_account_id($layoutPdo, (int) ($layoutCurrentAccount['id'] ?? 0), '내 계정');
        $layoutMemberDisplayLabel = $layoutMemberDisplayName . ' 님';
        $layoutMemberEmail = trim((string) ($layoutCurrentAccount['email'] ?? ''));
        $layoutMemberInitialSource = $layoutMemberDisplayName !== '' ? $layoutMemberDisplayName : ($layoutMemberEmail !== '' ? $layoutMemberEmail : 'M');
        $layoutMemberInitial = function_exists('mb_substr') ? mb_substr($layoutMemberInitialSource, 0, 1) : substr($layoutMemberInitialSource, 0, 1);
        $layoutMemberAvatarColorClass = sr_member_default_avatar_color_class(sr_member_public_account_hash($layoutRuntimeConfig, $layoutCurrentAccountId));
        if (sr_module_enabled($layoutPdo, 'community') && is_file(SR_ROOT . '/modules/community/helpers/messages.php')) {
            require_once SR_ROOT . '/modules/community/helpers/messages.php';
            $layoutCommunityMemberMenuEnabled = true;
            try {
                $layoutUnreadCommunityMessageCount = function_exists('sr_community_unread_message_count') ? sr_community_unread_message_count($layoutPdo, $layoutCurrentAccountId) : 0;
            } catch (Throwable) {
                $layoutUnreadCommunityMessageCount = 0;
            }
        }
        if (sr_module_enabled($layoutPdo, 'point') && is_file(SR_ROOT . '/modules/point/helpers.php')) {
            require_once SR_ROOT . '/modules/point/helpers.php';
            try {
                $layoutMemberAssetRows[] = [
                    'label' => function_exists('sr_point_display_name') ? sr_point_display_name($layoutPdo) : '포인트',
                    'value' => number_format(function_exists('sr_point_balance') ? sr_point_balance($layoutPdo, $layoutCurrentAccountId) : 0) . (function_exists('sr_point_unit_label') ? sr_point_unit_label($layoutPdo) : 'P'),
                    'url' => sr_url('/account/points'),
                    'icon' => 'database',
                ];
            } catch (Throwable) {
                $layoutMemberAssetRows[] = ['label' => '포인트', 'value' => '0P', 'url' => sr_url('/account/points'), 'icon' => 'database'];
            }
        }
        if (sr_module_enabled($layoutPdo, 'reward') && is_file(SR_ROOT . '/modules/reward/helpers.php')) {
            require_once SR_ROOT . '/modules/reward/helpers.php';
            try {
                $layoutMemberAssetRows[] = [
                    'label' => '적립금',
                    'value' => number_format(function_exists('sr_reward_balance') ? sr_reward_balance($layoutPdo, $layoutCurrentAccountId) : 0) . '원',
                    'url' => sr_url('/account/rewards'),
                    'icon' => 'savings',
                ];
            } catch (Throwable) {
                $layoutMemberAssetRows[] = ['label' => '적립금', 'value' => '0원', 'url' => sr_url('/account/rewards'), 'icon' => 'savings'];
            }
        }
        if (sr_module_enabled($layoutPdo, 'deposit') && is_file(SR_ROOT . '/modules/deposit/helpers.php')) {
            require_once SR_ROOT . '/modules/deposit/helpers.php';
            try {
                $layoutMemberAssetRows[] = [
                    'label' => '예치금',
                    'value' => number_format(function_exists('sr_deposit_balance') ? sr_deposit_balance($layoutPdo, $layoutCurrentAccountId) : 0) . '원',
                    'url' => sr_url('/account/deposits'),
                    'icon' => 'payments',
                ];
            } catch (Throwable) {
                $layoutMemberAssetRows[] = ['label' => '예치금', 'value' => '0원', 'url' => sr_url('/account/deposits'), 'icon' => 'payments'];
            }
        }
        if (sr_module_enabled($layoutPdo, 'coupon') && is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
            require_once SR_ROOT . '/modules/coupon/helpers.php';
            try {
                $layoutMemberAssetRows[] = [
                    'label' => '쿠폰·이용권',
                    'value' => number_format(function_exists('sr_coupon_active_account_issue_count') ? sr_coupon_active_account_issue_count($layoutPdo, $layoutCurrentAccountId) : 0) . '개',
                    'url' => sr_url('/account/coupons'),
                    'icon' => 'confirmation_number',
                ];
            } catch (Throwable) {
                $layoutMemberAssetRows[] = ['label' => '쿠폰·이용권', 'value' => '0개', 'url' => sr_url('/account/coupons'), 'icon' => 'confirmation_number'];
            }
        }
        $layoutMemberActionRows = sr_public_layout_member_action_rows($layoutPdo, $layoutCurrentAccountId);
    }
}
if (
    $layoutPdo instanceof PDO
    && is_array($layoutCurrentAccount)
    && sr_module_enabled($layoutPdo, 'admin')
    && is_file(SR_ROOT . '/modules/admin/helpers.php')
) {
    require_once SR_ROOT . '/modules/admin/helpers.php';
    $layoutAccountId = (int) ($layoutCurrentAccount['id'] ?? 0);
    $layoutAdminEnabled = sr_admin_has_admin_access($layoutPdo, $layoutAccountId);
    $layoutAdminIsOwner = $layoutAdminEnabled && sr_admin_is_owner($layoutPdo, $layoutAccountId);
    if ($layoutAdminIsOwner) {
        $layoutMemberBadgeLabel = '매니저';
    } elseif ($layoutAdminEnabled) {
        $layoutMemberBadgeLabel = '스탭';
        $layoutFirstAdminPath = sr_admin_first_permitted_menu_path($layoutPdo, $layoutAccountId);
        $layoutAdminUrl = sr_url($layoutFirstAdminPath !== '' ? $layoutFirstAdminPath : '/admin');
    }
}
if (
    $layoutPdo instanceof PDO
    && sr_module_enabled($layoutPdo, 'notification')
    && $layoutMemberEnabled
    && is_file(SR_ROOT . '/modules/notification/helpers.php')
) {
    $layoutNotificationEnabled = true;
    require_once SR_ROOT . '/modules/notification/helpers.php';
    if (is_array($layoutCurrentAccount)) {
        $layoutNotificationSummary = sr_notification_public_header_summary($layoutPdo, (int) $layoutCurrentAccount['id'], 5);
        $layoutNotificationHasAccount = true;
    }
}
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="<?php echo sr_e($layoutColorScheme); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo sr_seo_tags($layoutSeo, $layoutSite); ?>
    <?php echo $layoutFaviconHtml; ?>
    <?php echo sr_pwa_head_tags($layoutPdo, $layoutSite); ?>
    <script>(function(){try{var s=localStorage.getItem("sr_public_color_scheme");if(s==="light"||s==="dark"||s==="system"){document.documentElement.setAttribute("data-color-scheme",s);}}catch(e){}})();</script>
    <?php echo sr_stylesheet_tag($layoutStylesheets, $layoutPdo, ['style_profile' => $layoutStyleProfile]); ?>
    <?php echo sr_icon_bootstrap_script(); ?>
</head>
<body class="community-layout-body">
    <header class="community-layout-header">
        <div class="community-layout-topbar">
            <div class="community-layout-brand-link">
                <a class="community-layout-site-link" href="<?php echo sr_e($layoutBrandLinkUrl); ?>">
                    <?php if ($layoutBrandLogoHtml !== '' || $layoutMobileBrandLogoHtml !== '') { ?>
                        <?php echo $layoutMobileBrandLogoHtml; ?>
                        <?php echo $layoutBrandLogoHtml; ?>
                        <?php if ($layoutBrandUsesPublicSymbol) { ?>
                            <span class="community-layout-brand-text"><?php echo sr_e($layoutSiteName); ?></span>
                        <?php } ?>
                    <?php } else { ?>
                        <span class="community-layout-brand-text"><?php echo sr_e($layoutSiteName); ?></span>
                    <?php } ?>
                </a>
                <a class="community-layout-module-name" href="<?php echo sr_e($layoutModuleHomeUrl); ?>"><?php echo sr_e('커뮤니티'); ?></a>
            </div>
            <form class="community-layout-search" method="get" action="<?php echo sr_e(sr_url('/community/search')); ?>" role="search" data-community-layout-search-form data-community-layout-search-min-length="2" data-community-layout-search-alert="<?php echo sr_e('검색어는 2글자 이상 입력해 주세요.'); ?>">
                <label for="community_layout_search_q"><?php echo sr_e('커뮤니티 검색'); ?></label>
                <input id="community_layout_search_q" type="search" name="q" maxlength="100" value="<?php echo sr_e($layoutSearchKeyword); ?>" placeholder="<?php echo sr_e('검색'); ?>" autocomplete="off" data-community-layout-search-input>
                <button type="submit" aria-label="<?php echo sr_e('검색'); ?>">
                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>search</span>
                </button>
            </form>
            <div class="community-layout-actions">
            <?php if ($layoutNotificationEnabled) { ?>
                <details class="community-layout-notification-menu">
                    <summary class="community-layout-icon-button community-layout-notification-button" aria-label="<?php echo sr_e('알림'); ?>">
                        <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>notifications</span>
                        <?php if ((int) $layoutNotificationSummary['unread'] > 0) { ?>
                            <span class="community-layout-notification-badge"><?php echo sr_e((int) $layoutNotificationSummary['unread'] > 99 ? '99+' : (string) (int) $layoutNotificationSummary['unread']); ?></span>
                        <?php } ?>
                    </summary>
                    <div class="community-layout-notification-dropdown">
                        <div class="community-layout-notification-header">
                            <strong><?php echo sr_e('알림'); ?></strong>
                            <?php if ($layoutNotificationHasAccount) { ?>
                                <a href="<?php echo sr_e(sr_url('/account/notifications')); ?>"><?php echo sr_e('전체'); ?></a>
                            <?php } else { ?>
                                <a href="<?php echo sr_e(sr_url('/login')); ?>"><?php echo sr_e('로그인'); ?></a>
                            <?php } ?>
                        </div>
                        <?php if (!$layoutNotificationHasAccount) { ?>
                            <p class="community-layout-notification-empty"><?php echo sr_e('로그인 후 알림을 확인할 수 있습니다.'); ?></p>
                        <?php } elseif ($layoutNotificationSummary['items'] === []) { ?>
                            <p class="community-layout-notification-empty"><?php echo sr_e('새 알림이 없습니다.'); ?></p>
                        <?php } else { ?>
                            <div class="community-layout-notification-list">
                                <?php foreach ($layoutNotificationSummary['items'] as $layoutNotification) { ?>
                                    <?php
                                    $layoutNotificationLinkAttributes = sr_notification_item_link_attributes($layoutNotification, (int) ($layoutCurrentAccount['id'] ?? 0), true);
                                    $layoutNotificationBody = trim(strip_tags((string) ($layoutNotification['body_text'] ?? '')));
                                    $layoutNotificationClass = (string) ($layoutNotification['status'] ?? '') === 'unread'
                                        ? 'community-layout-notification-item is-unread'
                                        : 'community-layout-notification-item';
                                    ?>
                                    <?php if ($layoutNotificationLinkAttributes !== '') { ?>
                                        <a class="<?php echo sr_e($layoutNotificationClass); ?>"<?php echo $layoutNotificationLinkAttributes; ?>>
                                    <?php } else { ?>
                                        <div class="<?php echo sr_e($layoutNotificationClass); ?>">
                                    <?php } ?>
                                        <span class="community-layout-notification-title"><?php echo sr_e((string) ($layoutNotification['title'] ?? '알림')); ?></span>
                                        <?php if ($layoutNotificationBody !== '') { ?>
                                            <span class="community-layout-notification-text"><?php echo sr_e(sr_notification_clean_single_line($layoutNotificationBody, 80)); ?></span>
                                        <?php } ?>
                                        <span class="community-layout-notification-date"><?php echo sr_notification_time_html((string) ($layoutNotification['created_at'] ?? '')); ?></span>
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
            <?php if ($layoutMemberEnabled) { ?>
                <?php if (is_array($layoutCurrentAccount)) { ?>
                    <?php if ($layoutAdminEnabled) { ?>
                        <a class="community-layout-icon-button community-layout-admin-link" href="<?php echo sr_e($layoutAdminUrl); ?>" aria-label="<?php echo sr_e('관리자 모드'); ?>" title="<?php echo sr_e('관리자 모드'); ?>">
                            <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>settings</span>
                        </a>
                    <?php } ?>
                    <details class="community-layout-member-menu">
                        <summary class="community-layout-icon-button community-layout-member-link community-layout-member-link-account" aria-label="<?php echo sr_e($layoutMemberDisplayLabel . ' 회원 메뉴'); ?>">
                            <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>person</span>
                            <span><?php echo sr_e($layoutMemberDisplayLabel); ?></span>
                            <span class="material-symbols-outlined community-layout-member-menu-arrow" aria-hidden="true" data-sr-material-icon>expand_more</span>
                        </summary>
                        <div class="community-layout-member-dropdown dropdown-menu-profile" role="menu" aria-orientation="vertical">
                            <div class="community-layout-member-dropdown-header dropdown-profile-header">
                                <span class="community-layout-member-avatar dropdown-profile-avatar <?php echo sr_e($layoutMemberAvatarColorClass); ?>" aria-hidden="true"><?php echo sr_e($layoutMemberInitial); ?></span>
                                <span class="community-layout-member-identity dropdown-profile-identity">
                                    <strong class="dropdown-profile-name"><?php echo sr_e($layoutMemberDisplayName); ?></strong>
                                    <?php if ($layoutMemberEmail !== '') { ?>
                                        <span class="dropdown-profile-email"><?php echo sr_e($layoutMemberEmail); ?></span>
                                    <?php } ?>
                                </span>
                                <span class="community-layout-member-badge"><?php echo sr_e($layoutMemberBadgeLabel); ?></span>
                            </div>
                            <a class="community-layout-member-dropdown-link dropdown-profile-item" href="<?php echo sr_e(sr_url('/account')); ?>" role="menuitem">
                                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>manage_accounts</span>
                                <span><?php echo sr_e('정보수정'); ?></span>
                                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>chevron_right</span>
                            </a>
                            <?php if ($layoutCommunityMemberMenuEnabled) { ?>
                                <hr class="community-layout-member-divider dropdown-profile-divider">
                                <a class="community-layout-member-dropdown-link dropdown-profile-item" href="<?php echo sr_e(sr_url('/community/messages')); ?>" role="menuitem">
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>mail</span>
                                    <span><?php echo sr_e('쪽지'); ?></span>
                                    <strong><?php echo sr_e(number_format($layoutUnreadCommunityMessageCount) . '개'); ?></strong>
                                </a>
                                <a class="community-layout-member-dropdown-link dropdown-profile-item" href="<?php echo sr_e(sr_url('/community/scraps')); ?>" role="menuitem">
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>bookmark</span>
                                    <span><?php echo sr_e('스크랩'); ?></span>
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>chevron_right</span>
                                </a>
                                <hr class="community-layout-member-divider dropdown-profile-divider">
                            <?php } ?>
                            <?php foreach ($layoutMemberAssetRows as $layoutMemberAssetRow) { ?>
                                <?php
                                $layoutMemberAssetIcon = trim((string) ($layoutMemberAssetRow['icon'] ?? 'account_balance_wallet'));
                                $layoutMemberAssetIcon = function_exists('sr_material_icon_name') ? sr_material_icon_name($layoutMemberAssetIcon) : $layoutMemberAssetIcon;
                                ?>
                                <a class="community-layout-member-asset-row dropdown-profile-item" href="<?php echo sr_e((string) ($layoutMemberAssetRow['url'] ?? '#')); ?>" role="menuitem">
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon><?php echo sr_e($layoutMemberAssetIcon); ?></span>
                                    <span><?php echo sr_e((string) ($layoutMemberAssetRow['label'] ?? '')); ?></span>
                                    <strong><?php echo sr_e((string) ($layoutMemberAssetRow['value'] ?? '0')); ?></strong>
                                </a>
                            <?php } ?>
                            <?php foreach ($layoutMemberActionRows as $layoutMemberActionRow) { ?>
                                <a class="community-layout-member-action-row dropdown-profile-item" href="<?php echo sr_e((string) ($layoutMemberActionRow['url'] ?? '#')); ?>" role="menuitem">
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>bolt</span>
                                    <span><?php echo sr_e((string) ($layoutMemberActionRow['label'] ?? '')); ?></span>
                                    <strong><?php echo sr_e((string) ($layoutMemberActionRow['value'] ?? '')); ?></strong>
                                </a>
                            <?php } ?>
                            <hr class="community-layout-member-divider dropdown-profile-divider">
                            <form class="community-layout-member-logout-form" method="post" action="<?php echo sr_e(sr_url('/logout')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <button class="community-layout-member-logout-button dropdown-profile-item" type="submit" role="menuitem">
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>logout</span>
                                    <span><?php echo sr_e('로그아웃'); ?></span>
                                    <span></span>
                                </button>
                            </form>
                        </div>
                    </details>
                <?php } else { ?>
                    <a class="community-layout-icon-button community-layout-member-link community-layout-member-link-login" href="<?php echo sr_e(sr_url('/login')); ?>" aria-label="<?php echo sr_e('로그인'); ?>">
                        <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>login</span>
                    </a>
                <?php } ?>
            <?php } ?>
            </div>
        </div>
    </header>
    <nav class="community-layout-nav" aria-label="<?php echo sr_e('커뮤니티 메뉴'); ?>" data-community-scroll-nav>
        <?php echo $layoutPrimaryNavigationHtml; ?>
    </nav>
    <div class="community-layout-main">
        <?php echo $layoutContent; ?>
    </div>
    <footer class="community-layout-footer">
        <?php foreach ($layoutFooterNavigationHtml as $layoutFooterNavigationSlotKey => $layoutFooterNavigation) { ?>
            <nav class="community-layout-footer-nav community-layout-footer-nav-<?php echo sr_e($layoutFooterNavigationSlotKey); ?>" aria-label="<?php echo sr_e((string) ($layoutFooterNavigation['label'] ?? '하단 메뉴')); ?>">
                <?php echo (string) ($layoutFooterNavigation['html'] ?? ''); ?>
            </nav>
        <?php } ?>
        <div class="community-layout-footer-row">
            <p>&copy; <?php echo sr_e($layoutSiteName); ?></p>
            <div class="community-theme-dropdown dropdown" data-dropdown-placement="top-end">
                <button class="community-theme-toggle dropdown-toggle" type="button" aria-label="<?php echo sr_e('화면 모드 설정'); ?>" data-sr-color-scheme-toggle>
                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>dark_mode</span>
                    <span data-sr-color-scheme-current><?php echo sr_e($layoutColorSchemeOptions[$layoutColorScheme] ?? '라이트'); ?></span>
                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>expand_less</span>
                </button>
                <div class="community-theme-menu dropdown-menu" role="menu" aria-label="<?php echo sr_e('화면 모드'); ?>">
                    <?php foreach ($layoutColorSchemeOptions as $layoutColorSchemeKey => $layoutColorSchemeLabel) { ?>
                        <button class="community-theme-option dropdown-item" type="button" role="menuitemradio" aria-checked="<?php echo $layoutColorSchemeKey === $layoutColorScheme ? 'true' : 'false'; ?>" data-sr-color-scheme-option="<?php echo sr_e($layoutColorSchemeKey); ?>">
                            <span class="material-symbols-outlined community-theme-option-check" aria-hidden="true" data-sr-material-icon>check</span>
                            <span><?php echo sr_e($layoutColorSchemeLabel); ?></span>
                        </button>
                    <?php } ?>
                </div>
            </div>
        </div>
    </footer>
    <?php echo sr_script_tags($layoutScripts); ?>
    <?php echo $layoutPrivacyCookieConsentHtml; ?>
    <?php echo sr_pwa_registration_script(); ?>
</body>
</html>
