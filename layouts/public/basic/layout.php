<?php

$layoutSite = is_array($site ?? null) ? $site : null;
$layoutSeo = is_array($seo ?? null) ? $seo : [];
$layoutContent = is_string($contentHtml ?? null) ? $contentHtml : '';
$layoutPdo = $pdo instanceof PDO ? $pdo : null;
$layoutContext = is_array($layoutContext ?? null) ? $layoutContext : [];
$layoutContextStylesheets = is_array($layoutContext['stylesheets'] ?? null) ? $layoutContext['stylesheets'] : [];
$layoutContextScripts = is_array($layoutContext['scripts'] ?? null) ? $layoutContext['scripts'] : [];
$layoutStylesheets = ['/assets/layout.css'];
$layoutScripts = ['/assets/common-ui.js', '/assets/mention-input.js', '/assets/public-layout.js'];
$layoutStyleProfile = is_string($layoutContext['style_profile'] ?? null) ? (string) $layoutContext['style_profile'] : 'minimal';
$layoutBodyClass = sr_ui_icon_class_attr((string) ($layoutContext['body_class'] ?? ''));
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
$layoutPrimaryMenuKey = array_key_exists('primary', $layoutSiteMenus) ? $layoutCleanMenuKey((string) $layoutSiteMenus['primary']) : 'header';
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
$layoutFaviconHtml = '';
$layoutPrimaryNavigationHtml = '';
$layoutFooterNavigationHtml = [];
if ($layoutPdo instanceof PDO && sr_module_enabled($layoutPdo, 'logo_manager') && is_file(SR_ROOT . '/modules/logo_manager/helpers.php')) {
    require_once SR_ROOT . '/modules/logo_manager/helpers.php';
    $layoutBrandLogoHtml = sr_logo_manager_render_logo($layoutPdo, 'public.header.desktop', $layoutSite, [
        'class' => 'public-layout-brand-logo public-layout-brand-logo-desktop',
    ]);
    $layoutMobileBrandLogoHtml = sr_logo_manager_render_logo($layoutPdo, 'public.header.mobile', $layoutSite, [
        'class' => $layoutBrandLogoHtml !== ''
            ? 'public-layout-brand-logo public-layout-brand-logo-mobile'
            : 'public-layout-brand-logo',
    ]);
    $layoutPublicSymbolLogo = null;
    if ($layoutBrandLogoHtml === '' && $layoutMobileBrandLogoHtml === '') {
        $layoutBrandLogoHtml = sr_logo_manager_render_public_symbol_logo($layoutPdo, $layoutSite, [
            'class' => 'public-layout-brand-logo public-layout-brand-symbol',
        ]);
        if ($layoutBrandLogoHtml !== '') {
            $layoutBrandUsesPublicSymbol = true;
            $layoutPublicSymbolLogo = sr_logo_manager_public_symbol_logo($layoutPdo);
        }
    }
    $layoutBrandLogo = sr_logo_manager_active_logo($layoutPdo, 'public.header.desktop');
    if (is_array($layoutBrandLogo)) {
        $layoutBrandLink = sr_logo_manager_clean_url((string) ($layoutBrandLogo['link_url'] ?? ''));
        if ($layoutBrandLink !== '') {
            $layoutBrandLinkUrl = sr_logo_manager_url_for_output($layoutBrandLink);
        }
    } elseif (is_array($layoutPublicSymbolLogo)) {
        $layoutBrandLink = sr_logo_manager_clean_url((string) ($layoutPublicSymbolLogo['link_url'] ?? ''));
        if ($layoutBrandLink !== '') {
            $layoutBrandLinkUrl = sr_logo_manager_url_for_output($layoutBrandLink);
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
<body class="<?php echo sr_e(trim('public-layout-body ' . $layoutBodyClass)); ?>">
    <header class="public-layout-header" data-public-scroll-header>
        <a class="public-layout-brand-link" href="<?php echo sr_e($layoutBrandLinkUrl); ?>">
            <?php if ($layoutBrandLogoHtml !== '' || $layoutMobileBrandLogoHtml !== '') { ?>
                <?php echo $layoutMobileBrandLogoHtml; ?>
                <?php echo $layoutBrandLogoHtml; ?>
                <?php if ($layoutBrandUsesPublicSymbol) { ?>
                    <span class="public-layout-brand-text"><?php echo sr_e($layoutSiteName); ?></span>
                <?php } ?>
            <?php } else { ?>
                <span class="public-layout-brand-text"><?php echo sr_e($layoutSiteName); ?></span>
            <?php } ?>
        </a>
        <nav class="public-layout-nav" aria-label="<?php echo sr_e('공개 메뉴'); ?>">
            <?php echo $layoutPrimaryNavigationHtml; ?>
        </nav>
        <div class="public-layout-actions">
            <?php if ($layoutNotificationEnabled) { ?>
                <details class="public-layout-notification-menu">
                    <summary class="public-layout-icon-button public-layout-notification-button" aria-label="<?php echo sr_e('알림'); ?>">
                        <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>notifications</span>
                        <?php if ((int) $layoutNotificationSummary['unread'] > 0) { ?>
                            <span class="public-layout-notification-badge"><?php echo sr_e((int) $layoutNotificationSummary['unread'] > 99 ? '99+' : (string) (int) $layoutNotificationSummary['unread']); ?></span>
                        <?php } ?>
                    </summary>
                    <div class="public-layout-notification-dropdown">
                        <div class="public-layout-notification-header">
                            <strong><?php echo sr_e('알림'); ?></strong>
                            <?php if ($layoutNotificationHasAccount) { ?>
                                <a href="<?php echo sr_e(sr_url('/account/notifications')); ?>"><?php echo sr_e('전체'); ?></a>
                            <?php } else { ?>
                                <a href="<?php echo sr_e(sr_url('/login')); ?>"><?php echo sr_e('로그인'); ?></a>
                            <?php } ?>
                        </div>
                        <?php if (!$layoutNotificationHasAccount) { ?>
                            <p class="public-layout-notification-empty"><?php echo sr_e('로그인 후 알림을 확인할 수 있습니다.'); ?></p>
                        <?php } elseif ($layoutNotificationSummary['items'] === []) { ?>
                            <p class="public-layout-notification-empty"><?php echo sr_e('새 알림이 없습니다.'); ?></p>
                        <?php } else { ?>
                            <div class="public-layout-notification-list">
                                <?php foreach ($layoutNotificationSummary['items'] as $layoutNotification) { ?>
                                    <?php
                                    $layoutNotificationLinkAttributes = sr_notification_item_link_attributes($layoutNotification, (int) ($layoutCurrentAccount['id'] ?? 0), true);
                                    $layoutNotificationBody = trim(strip_tags((string) ($layoutNotification['body_text'] ?? '')));
                                    $layoutNotificationClass = (string) ($layoutNotification['status'] ?? '') === 'unread'
                                        ? 'public-layout-notification-item is-unread'
                                        : 'public-layout-notification-item';
                                    ?>
                                    <?php if ($layoutNotificationLinkAttributes !== '') { ?>
                                        <a class="<?php echo sr_e($layoutNotificationClass); ?>"<?php echo $layoutNotificationLinkAttributes; ?>>
                                    <?php } else { ?>
                                        <div class="<?php echo sr_e($layoutNotificationClass); ?>">
                                    <?php } ?>
                                        <span class="public-layout-notification-title"><?php echo sr_e((string) ($layoutNotification['title'] ?? '알림')); ?></span>
                                        <?php if ($layoutNotificationBody !== '') { ?>
                                            <span class="public-layout-notification-text"><?php echo sr_e(sr_notification_clean_single_line($layoutNotificationBody, 80)); ?></span>
                                        <?php } ?>
                                        <span class="public-layout-notification-date"><?php echo sr_notification_time_html((string) ($layoutNotification['created_at'] ?? '')); ?></span>
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
                        <a class="public-layout-icon-button public-layout-admin-link" href="<?php echo sr_e($layoutAdminUrl); ?>" aria-label="<?php echo sr_e('관리자 모드'); ?>" title="<?php echo sr_e('관리자 모드'); ?>">
                            <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>settings</span>
                        </a>
                    <?php } ?>
                    <details class="public-layout-member-menu">
                        <summary class="public-layout-icon-button public-layout-member-link public-layout-member-link-account" aria-label="<?php echo sr_e($layoutMemberDisplayLabel . ' 회원 메뉴'); ?>">
                            <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>person</span>
                            <span><?php echo sr_e($layoutMemberDisplayLabel); ?></span>
                            <span class="material-symbols-outlined public-layout-member-menu-arrow" aria-hidden="true" data-sr-material-icon>expand_more</span>
                        </summary>
                        <div class="public-layout-member-dropdown dropdown-menu-profile" role="menu" aria-orientation="vertical">
                            <div class="public-layout-member-dropdown-header dropdown-profile-header">
                                <span class="public-layout-member-avatar dropdown-profile-avatar <?php echo sr_e($layoutMemberAvatarColorClass); ?>" aria-hidden="true"><?php echo sr_e($layoutMemberInitial); ?></span>
                                <span class="public-layout-member-identity dropdown-profile-identity">
                                    <strong class="dropdown-profile-name"><?php echo sr_e($layoutMemberDisplayName); ?></strong>
                                    <?php if ($layoutMemberEmail !== '') { ?>
                                        <span class="dropdown-profile-email"><?php echo sr_e($layoutMemberEmail); ?></span>
                                    <?php } ?>
                                </span>
                                <span class="public-layout-member-badge"><?php echo sr_e($layoutMemberBadgeLabel); ?></span>
                            </div>
                            <a class="public-layout-member-dropdown-link dropdown-profile-item" href="<?php echo sr_e(sr_url('/account')); ?>" role="menuitem">
                                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>manage_accounts</span>
                                <span><?php echo sr_e('정보수정'); ?></span>
                                <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>chevron_right</span>
                            </a>
                            <?php if ($layoutCommunityMemberMenuEnabled) { ?>
                                <hr class="public-layout-member-divider dropdown-profile-divider">
                                <a class="public-layout-member-dropdown-link dropdown-profile-item" href="<?php echo sr_e(sr_url('/community/messages')); ?>" role="menuitem">
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>mail</span>
                                    <span><?php echo sr_e('쪽지'); ?></span>
                                    <strong><?php echo sr_e(number_format($layoutUnreadCommunityMessageCount) . '개'); ?></strong>
                                </a>
                                <a class="public-layout-member-dropdown-link dropdown-profile-item" href="<?php echo sr_e(sr_url('/community/scraps')); ?>" role="menuitem">
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>bookmark</span>
                                    <span><?php echo sr_e('스크랩'); ?></span>
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>chevron_right</span>
                                </a>
                                <hr class="public-layout-member-divider dropdown-profile-divider">
                            <?php } ?>
                            <?php foreach ($layoutMemberAssetRows as $layoutMemberAssetRow) { ?>
                                <?php
                                $layoutMemberAssetIcon = trim((string) ($layoutMemberAssetRow['icon'] ?? 'account_balance_wallet'));
                                $layoutMemberAssetIcon = function_exists('sr_material_icon_name') ? sr_material_icon_name($layoutMemberAssetIcon) : $layoutMemberAssetIcon;
                                ?>
                                <a class="public-layout-member-asset-row dropdown-profile-item" href="<?php echo sr_e((string) ($layoutMemberAssetRow['url'] ?? '#')); ?>" role="menuitem">
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon><?php echo sr_e($layoutMemberAssetIcon); ?></span>
                                    <span><?php echo sr_e((string) ($layoutMemberAssetRow['label'] ?? '')); ?></span>
                                    <strong><?php echo sr_e((string) ($layoutMemberAssetRow['value'] ?? '0')); ?></strong>
                                </a>
                            <?php } ?>
                            <hr class="public-layout-member-divider dropdown-profile-divider">
                            <form class="public-layout-member-logout-form" method="post" action="<?php echo sr_e(sr_url('/logout')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <button class="public-layout-member-logout-button dropdown-profile-item" type="submit" role="menuitem">
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>logout</span>
                                    <span><?php echo sr_e('로그아웃'); ?></span>
                                    <span></span>
                                </button>
                            </form>
                        </div>
                    </details>
                <?php } else { ?>
                    <a class="public-layout-icon-button public-layout-member-link public-layout-member-link-login" href="<?php echo sr_e(sr_url('/login')); ?>" aria-label="<?php echo sr_e('로그인'); ?>">
                        <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>login</span>
                    </a>
                <?php } ?>
            <?php } ?>
        </div>
    </header>
    <div class="public-layout-main">
        <?php echo $layoutContent; ?>
    </div>
    <footer class="public-layout-footer">
        <?php foreach ($layoutFooterNavigationHtml as $layoutFooterNavigationSlotKey => $layoutFooterNavigation) { ?>
            <nav class="public-layout-footer-nav public-layout-footer-nav-<?php echo sr_e($layoutFooterNavigationSlotKey); ?>" aria-label="<?php echo sr_e((string) ($layoutFooterNavigation['label'] ?? '하단 메뉴')); ?>">
                <?php echo (string) ($layoutFooterNavigation['html'] ?? ''); ?>
            </nav>
        <?php } ?>
        <div class="public-layout-footer-row">
            <p>&copy; <?php echo sr_e($layoutSiteName); ?></p>
            <div class="public-theme-dropdown dropdown" data-dropdown-placement="top-end">
                <button class="public-theme-toggle dropdown-toggle" type="button" aria-label="<?php echo sr_e('화면 모드 설정'); ?>" data-sr-color-scheme-toggle>
                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>dark_mode</span>
                    <span data-sr-color-scheme-current><?php echo sr_e($layoutColorSchemeOptions[$layoutColorScheme] ?? '라이트'); ?></span>
                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>expand_less</span>
                </button>
                <div class="public-theme-menu dropdown-menu" role="menu" aria-label="<?php echo sr_e('화면 모드'); ?>">
                    <?php foreach ($layoutColorSchemeOptions as $layoutColorSchemeKey => $layoutColorSchemeLabel) { ?>
                        <button class="public-theme-option dropdown-item" type="button" role="menuitemradio" aria-checked="<?php echo $layoutColorSchemeKey === $layoutColorScheme ? 'true' : 'false'; ?>" data-sr-color-scheme-option="<?php echo sr_e($layoutColorSchemeKey); ?>">
                            <span class="material-symbols-outlined public-theme-option-check" aria-hidden="true" data-sr-material-icon>check</span>
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
