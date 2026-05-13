#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

function toy_skin_theme_check_read(string $path): string
{
    global $root, $errors;
    $fullPath = $root . '/' . $path;
    if (!is_file($fullPath)) {
        $errors[] = 'Required skin/theme UI file is missing: ' . $path;
        return '';
    }

    $content = file_get_contents($fullPath);
    if (!is_string($content)) {
        $errors[] = 'Required skin/theme UI file cannot be read: ' . $path;
        return '';
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function toy_skin_theme_check_contains(string $path, array $needles, string $label): void
{
    global $errors;
    $content = toy_skin_theme_check_read($path);
    if ($content === '') {
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, (string) $needle)) {
            $errors[] = $label . ' must contain: ' . $needle;
        }
    }
}

function toy_skin_theme_check_file_exists(string $path, string $label): void
{
    global $root, $errors;
    if (!is_file($root . '/' . $path)) {
        $errors[] = $label . ' file is missing: ' . $path;
    }
}

$targets = [
    [
        'label' => 'Admin skin',
        'helper' => 'modules/admin/helpers/settings.php',
        'action' => 'modules/admin/actions/settings.php',
        'view' => 'modules/admin/views/settings.php',
        'render_views' => ['modules/admin/views/layout-header.php', 'modules/admin/views/layout-footer.php'],
        'files' => ['modules/admin/skins/basic/layout-header.php', 'modules/admin/skins/basic/layout-footer.php'],
        'helper_needles' => [
            'function toy_admin_skin_options(): array',
            "'layout-header' => TOY_ROOT . '/modules/admin/skins/basic/layout-header.php'",
            "'layout-footer' => TOY_ROOT . '/modules/admin/skins/basic/layout-footer.php'",
            'return is_file($view) ? $view',
        ],
        'action_needles' => [
            '$adminSkinOptions = toy_admin_skin_options()',
            "toy_post_string('admin_skin_key', 40)",
            'if (!isset($adminSkinOptions[$postedSkinKey]))',
            'toy_admin_save_skin_key($pdo, $postedSkinKey)',
            "'admin_skin_key' => \$adminSkinKey",
        ],
        'view_needles' => [
            '<label>관리자 스킨<br>',
            '<select name="admin_skin_key">',
            'foreach ($adminSkinOptions as $skinKey => $skinOption)',
        ],
        'render_needles' => [
            "toy_admin_skin_view(toy_admin_skin_key(\$adminSettings), 'layout-header')",
            "toy_admin_skin_view(toy_admin_skin_key(\$adminSettings), 'layout-footer')",
        ],
    ],
    [
        'label' => 'Banner skin',
        'helper' => 'modules/banner/helpers.php',
        'action' => 'modules/banner/actions/admin-banners.php',
        'view' => 'modules/banner/views/admin-banners.php',
        'render_views' => ['modules/banner/helpers.php'],
        'files' => ['modules/banner/skins/basic/item.php'],
        'helper_needles' => [
            'function toy_banner_skin_options(): array',
            "'supports' => ['public', 'inline']",
            "'item' => TOY_ROOT . '/modules/banner/skins/basic/item.php'",
            'function toy_banner_skin_supports(string $skinKey, string $placementKind): bool',
            'function toy_banner_skin_key_for_placement(string $skinKey, string $placementKind): ?string',
            'function toy_banner_target_placement_kind(?array $target, bool $isPublicBanner = false): string',
            'return is_file($view) ? $view',
            'function toy_banner_save_skin_key(PDO $pdo, string $skinKey): void',
        ],
        'action_needles' => [
            '$bannerSkinOptions = toy_banner_skin_options()',
            "toy_post_string('banner_skin_key', 40)",
            "toy_post_string('skin_key', 40)",
            'if (!isset($bannerSkinOptions[$postedSkinKey]))',
            'if (!isset($bannerSkinOptions[$skinKey]))',
            'toy_banner_skin_supports($skinKey, toy_banner_target_placement_kind($target, $isPublicBanner))',
            'toy_banner_save_skin_key($pdo, $postedSkinKey)',
            "'banner_skin_key' => \$bannerSkinKey",
            "'skin_key' => \$skinKey",
        ],
        'view_needles' => [
            '<label>배너 스킨<br>',
            '<select name="banner_skin_key">',
            '<select name="skin_key">',
            'foreach ($bannerSkinOptions as $skinKey => $skinOption)',
        ],
        'render_needles' => [
            'toy_banner_target_for_context($pdo, $context)',
            'toy_banner_skin_key_for_placement($requestedSkinKey, $placementKind)',
            'toy_banner_render_item($banner, $skinKey)',
        ],
    ],
    [
        'label' => 'Popup layer skin',
        'helper' => 'modules/popup_layer/helpers.php',
        'action' => 'modules/popup_layer/actions/admin-popup-layers.php',
        'view' => 'modules/popup_layer/views/admin-popup-layers.php',
        'render_views' => ['modules/popup_layer/helpers.php'],
        'files' => ['modules/popup_layer/skins/basic/layer.php'],
        'helper_needles' => [
            'function toy_popup_layer_skin_options(): array',
            "'layer' => TOY_ROOT . '/modules/popup_layer/skins/basic/layer.php'",
            'return is_file($view) ? $view',
            'function toy_popup_layer_save_skin_key(PDO $pdo, string $skinKey): void',
        ],
        'action_needles' => [
            '$popupLayerSkinOptions = toy_popup_layer_skin_options()',
            "toy_post_string('popup_layer_skin_key', 40)",
            "toy_post_string('skin_key', 40)",
            'if (!isset($popupLayerSkinOptions[$postedSkinKey]))',
            'if (!isset($popupLayerSkinOptions[$skinKey]))',
            'toy_popup_layer_save_skin_key($pdo, $postedSkinKey)',
            "'popup_layer_skin_key' => \$popupLayerSkinKey",
            "'skin_key' => \$skinKey",
        ],
        'view_needles' => [
            '<label>팝업레이어 스킨<br>',
            '<select name="popup_layer_skin_key">',
            '<label>팝업 스킨<br>',
            '<select name="skin_key">',
            'foreach ($popupLayerSkinOptions as $skinKey => $skinOption)',
        ],
        'render_needles' => [
            "\$skinKey = toy_popup_layer_skin_key(['popup_layer_skin_key' => (string) (\$row['skin_key'] ?? 'basic')])",
            'toy_popup_layer_render_stack($skinPopups, $skinKey)',
        ],
    ],
    [
        'label' => 'Member skin',
        'helper' => 'modules/member/helpers/settings.php',
        'action' => 'modules/member/actions/admin-settings.php',
        'view' => 'modules/member/views/admin-settings.php',
        'render_views' => [
            'modules/member/actions/login.php',
            'modules/member/actions/register.php',
            'modules/member/actions/account.php',
            'modules/member/actions/password-reset-request.php',
            'modules/member/actions/password-reset.php',
            'modules/member/actions/withdraw.php',
            'modules/member/actions/email-verified.php',
        ],
        'files' => [
            'modules/member/skins/basic/login.php',
            'modules/member/skins/basic/register.php',
            'modules/member/skins/basic/account.php',
            'modules/member/skins/basic/password-reset-request.php',
            'modules/member/skins/basic/password-reset.php',
            'modules/member/skins/basic/privacy-requests.php',
            'modules/member/skins/basic/withdraw.php',
            'modules/member/skins/basic/email-verified.php',
        ],
        'helper_needles' => [
            'function toy_member_skin_options(): array',
            "'login' => TOY_ROOT . '/modules/member/skins/basic/login.php'",
            'return is_file($view) ? $view',
        ],
        'action_needles' => [
            "toy_post_string('member_skin_key', 40)",
            'if (!isset(toy_member_skin_options()[$memberSkinKey]))',
            "['member_skin_key', (string) \$settings['member_skin_key'], 'string']",
        ],
        'view_needles' => [
            '<label>회원 스킨<br>',
            '<select name="member_skin_key">',
            'foreach (toy_member_skin_options() as $skinKey => $skinOption)',
        ],
        'render_needles' => [
            'toy_member_skin_view(toy_member_skin_key($memberSettings),',
        ],
    ],
    [
        'label' => 'Community theme',
        'helper' => 'modules/community/helpers/themes.php',
        'action' => 'modules/community/actions/admin-settings.php',
        'view' => 'modules/community/views/admin-settings.php',
        'render_views' => ['modules/community/actions/home.php'],
        'files' => ['modules/community/themes/basic/home.php'],
        'helper_needles' => [
            'function toy_community_theme_options(): array',
            "'home' => TOY_ROOT . '/modules/community/themes/basic/home.php'",
            'return is_file($view) ? $view',
        ],
        'action_needles' => [
            '$communityThemeOptions = toy_community_theme_options()',
            "toy_post_string('theme_key', 40)",
            'if (!isset($communityThemeOptions[$themeKey]))',
            "['theme_key', \$themeKey, 'string']",
        ],
        'view_needles' => [
            '<label>커뮤니티 테마<br>',
            '<select name="theme_key">',
            'foreach ($communityThemeOptions as $themeKey => $themeOption)',
        ],
        'render_needles' => [
            '$themeKey = toy_community_theme_key($settings)',
            'toy_community_theme_view($themeKey, \'home\')',
        ],
    ],
    [
        'label' => 'Community board skin',
        'helper' => 'modules/community/helpers/themes.php',
        'action' => 'modules/community/actions/admin-boards.php',
        'view' => 'modules/community/views/admin-boards.php',
        'render_views' => ['modules/community/actions/list.php', 'modules/community/actions/view.php', 'modules/community/actions/write.php', 'modules/community/actions/edit.php'],
        'files' => ['modules/community/skins/basic/list.php', 'modules/community/skins/basic/view.php', 'modules/community/skins/basic/form.php'],
        'helper_needles' => [
            'function toy_community_skin_options(): array',
            "'list' => TOY_ROOT . '/modules/community/skins/basic/list.php'",
            "'post' => TOY_ROOT . '/modules/community/skins/basic/view.php'",
            "'form' => TOY_ROOT . '/modules/community/skins/basic/form.php'",
        ],
        'action_needles' => [
            '$communitySkinOptions = toy_community_skin_options()',
            '$intent === \'update_skin\'',
            "toy_post_string('skin_key', 40)",
            'if (!isset($communitySkinOptions[$skinKey]))',
            "toy_community_set_board_setting(\$pdo, \$boardId, 'skin_key', \$skinKey, 'string')",
        ],
        'view_needles' => [
            '<label>게시판 스킨<br>',
            '<input type="hidden" name="intent" value="update_skin">',
            '<select name="skin_key">',
            'foreach ($communitySkinOptions as $skinKey => $skinOption)',
        ],
        'render_needles' => [
            '$skinKey = toy_community_board_skin_key($pdo,',
            'toy_community_skin_view($skinKey,',
        ],
    ],
];

foreach ($targets as $target) {
    $label = (string) $target['label'];
    toy_skin_theme_check_contains((string) $target['helper'], $target['helper_needles'], $label . ' helper');
    toy_skin_theme_check_contains((string) $target['action'], $target['action_needles'], $label . ' admin action');
    toy_skin_theme_check_contains((string) $target['view'], $target['view_needles'], $label . ' admin view');

    foreach ($target['files'] as $file) {
        toy_skin_theme_check_file_exists((string) $file, $label);
    }

    $renderNeedles = $target['render_needles'];
    $renderContent = '';
    foreach ($target['render_views'] as $renderView) {
        $renderContent .= "\n" . toy_skin_theme_check_read((string) $renderView);
    }
    foreach ($renderNeedles as $needle) {
        if (!str_contains($renderContent, (string) $needle)) {
            $errors[] = $label . ' render flow must contain: ' . $needle;
        }
    }
}

foreach (['modules', 'core'] as $viewRoot) {
    $directory = $root . '/' . $viewRoot;
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $relativePath = substr($file->getPathname(), strlen($root) + 1);
        $content = toy_skin_theme_check_read($relativePath);
        if ($content === '') {
            continue;
        }

        if (preg_match('/<(?:input|textarea)\b[^>]*\bname="[^"]*(?:skin|theme)[^"]*"/i', $content) === 1) {
            $errors[] = 'Skin/theme keys must be selected, not typed, in view file: ' . $relativePath;
        }
    }
}

toy_skin_theme_check_contains('modules/admin/views/settings.php', [
    '<select name="public_layout_key">',
    'foreach (toy_public_layout_options() as $layoutKey => $layoutOption)',
], 'Public layout setting UI');

if ($errors !== []) {
    fwrite(STDERR, "skin/theme UI checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "skin/theme UI checks completed.\n";
