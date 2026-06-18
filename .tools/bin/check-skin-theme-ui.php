#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

function sr_skin_theme_check_read(string $path): string
{
    global $root, $errors;
    $fullPath = $root . '/' . $path;
    if (!is_file($fullPath)) {
        $errors[] = 'Required skin/layout UI file is missing: ' . $path;
        return '';
    }

    $content = file_get_contents($fullPath);
    if (!is_string($content)) {
        $errors[] = 'Required skin/layout UI file cannot be read: ' . $path;
        return '';
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function sr_skin_theme_check_target_content($path): string
{
    $paths = is_array($path) ? $path : [$path];
    $content = '';
    foreach ($paths as $targetPath) {
        $content .= "\n" . sr_skin_theme_check_read((string) $targetPath);
    }

    return $content;
}

function sr_skin_theme_check_contains($path, array $needles, string $label): void
{
    global $errors;
    $content = sr_skin_theme_check_target_content($path);
    if ($content === '') {
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, (string) $needle)) {
            $errors[] = $label . ' must contain: ' . $needle;
        }
    }
}

function sr_skin_theme_check_not_contains($path, array $needles, string $label): void
{
    global $errors;
    $content = sr_skin_theme_check_target_content($path);
    if ($content === '') {
        return;
    }

    foreach ($needles as $needle) {
        if (str_contains($content, (string) $needle)) {
            $errors[] = $label . ' must not contain: ' . $needle;
        }
    }
}

function sr_skin_theme_check_file_exists(string $path, string $label): void
{
    global $root, $errors;
    if (!is_file($root . '/' . $path)) {
        $errors[] = $label . ' file is missing: ' . $path;
    }
}

function sr_skin_theme_check_admin_skin_material_icons(): void
{
    global $errors;
    $settingsContent = sr_skin_theme_check_read('modules/admin/helpers/settings.php');
    if ($settingsContent === '') {
        return;
    }

    preg_match_all("#'layout-header'\\s*=>\\s*SR_ROOT\\s*\\.\\s*'([^']+)'#", $settingsContent, $matches);
    $layoutHeaders = $matches[1] ?? [];
    if ($layoutHeaders === []) {
        $errors[] = 'Admin skin options must declare layout-header view paths.';
        return;
    }

    foreach ($layoutHeaders as $layoutHeader) {
        $relativePath = ltrim((string) $layoutHeader, '/');
        $content = sr_skin_theme_check_read($relativePath);
        if ($content === '') {
            continue;
        }

        if (!str_contains($content, 'sr_material_icon_html(') && !str_contains($content, 'sr_icon(')) {
            $errors[] = 'Admin skin layout-header must render Material icons through the common helper: ' . $relativePath;
        }

        if (!str_contains($content, 'sr_material_icon_bootstrap_script();') && !str_contains($content, 'sr_icon_bootstrap_script();')) {
            $errors[] = 'Admin skin layout-header must load the Material icon readiness bootstrap: ' . $relativePath;
        }

        if (preg_match('/<(?:svg|use)\b/', $content) === 1 || str_contains($content, 'admin-menu-icon-')) {
            $errors[] = 'Admin skin layout-header must not render legacy SVG icon markup: ' . $relativePath;
        }
    }
}

function sr_skin_theme_check_admin_icon_contract_docs(): void
{
    global $root, $errors;

    require_once $root . '/modules/admin/helpers/icons.php';
    $allowedIcons = array_keys(sr_admin_allowed_menu_symbol_icons());
    sort($allowedIcons);

    foreach (['docs/module-guide.md', 'docs/admin-ui-guide.md'] as $path) {
        $content = sr_skin_theme_check_read($path);
        if ($content === '') {
            continue;
        }

        foreach ($allowedIcons as $iconName) {
            if (!str_contains($content, '`' . $iconName . '`')) {
                $errors[] = 'Admin icon contract docs must list allowed symbol `' . $iconName . '` in ' . $path;
            }
        }
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
            'function sr_admin_skin_options(): array',
            'sr_filter_view_options([',
            "'layout-header' => SR_ROOT . '/modules/admin/skins/basic/layout-header.php'",
            "'layout-footer' => SR_ROOT . '/modules/admin/skins/basic/layout-footer.php'",
            "], ['layout-header', 'layout-footer'], 'admin skin')",
            '기본 관리자 스킨 view 파일이 누락되었습니다.',
        ],
        'action_needles' => [
            '$adminSkinOptions = sr_admin_skin_options()',
            "sr_post_string('admin_skin_key', 40)",
            'if (!isset($adminSkinOptions[$postedSkinKey]))',
            'sr_admin_save_skin_key($pdo, $postedSkinKey)',
            "'admin_skin_key' => \$adminSkinKey",
        ],
        'view_needles' => [
            "sr_admin_form_label_help_html('admin_settings_admin_skin_key'",
            '<select id="admin_settings_admin_skin_key" name="admin_skin_key" class="form-select">',
            'foreach ($adminSkinOptions as $skinKey => $skinOption)',
        ],
        'render_needles' => [
            "sr_admin_skin_view(sr_admin_skin_key(\$adminSettings), 'layout-header')",
            "sr_admin_skin_view(sr_admin_skin_key(\$adminSettings), 'layout-footer')",
        ],
    ],
    [
        'label' => 'Banner skin',
        'helper' => 'modules/banner/helpers.php',
        'action' => ['modules/banner/actions/admin-banners.php', 'modules/banner/actions/admin-banner-settings.php'],
        'view' => ['modules/banner/views/admin-banners.php', 'modules/banner/views/admin-banner-settings.php'],
        'render_views' => ['modules/banner/helpers.php'],
        'files' => ['modules/banner/skins/basic/item.php'],
        'helper_needles' => [
            'function sr_banner_skin_options(): array',
            'sr_filter_view_options([',
            "'supports' => ['public', 'inline']",
            "'item' => SR_ROOT . '/modules/banner/skins/basic/item.php'",
            "], ['item'], 'banner skin')",
            'function sr_banner_skin_supports(string $skinKey, string $placementKind): bool',
            'function sr_banner_skin_key_for_placement(string $skinKey, string $placementKind): ?string',
            'function sr_banner_target_placement_kind(?array $target, bool $isPublicBanner = false): string',
            '기본 배너 스킨 view 파일이 누락되었습니다.',
            'function sr_banner_save_skin_key(PDO $pdo, string $skinKey): void',
        ],
        'action_needles' => [
            '$bannerSkinOptions = sr_banner_skin_options()',
            "sr_post_string('banner_skin_key', 40)",
            "sr_post_string('skin_key', 40)",
            'if (!isset($bannerSkinOptions[$postedSkinKey]))',
            'if (!isset($bannerSkinOptions[$skinKey]))',
            'sr_banner_skin_supports($skinKey, sr_banner_target_placement_kind($target, $isPublicBanner))',
            'sr_banner_save_settings($pdo, [',
            "'banner_skin_key' => \$bannerSkinKey",
            "'skin_key' => \$skinKey",
        ],
        'view_needles' => [
            "sr_admin_form_label_help_html('banner_admin_banner_settings_banner_skin_key'",
            '<select id="banner_admin_banner_settings_banner_skin_key" name="banner_skin_key" class="form-select" required>',
            '<select id="banner_admin_banners_skin_key" name="skin_key" class="form-select">',
            'foreach ($bannerSkinOptions as $skinKey => $skinOption)',
        ],
        'render_needles' => [
            'sr_banner_target_for_context($pdo, $context)',
            'sr_banner_skin_key_for_placement($requestedSkinKey, $placementKind)',
            'sr_banner_render_item($banner, $skinKey)',
        ],
    ],
    [
        'label' => 'Popup layer skin',
        'helper' => 'modules/popup_layer/helpers.php',
        'action' => ['modules/popup_layer/actions/admin-popup-layers.php', 'modules/popup_layer/actions/admin-popup-layer-settings.php'],
        'view' => ['modules/popup_layer/views/admin-popup-layers.php', 'modules/popup_layer/views/admin-popup-layer-settings.php'],
        'render_views' => ['modules/popup_layer/helpers.php'],
        'files' => ['modules/popup_layer/skins/basic/layer.php'],
        'helper_needles' => [
            'function sr_popup_layer_skin_options(): array',
            'sr_filter_view_options([',
            "'layer' => SR_ROOT . '/modules/popup_layer/skins/basic/layer.php'",
            "], ['layer'], 'popup layer skin')",
            '기본 팝업레이어 스킨 view 파일이 누락되었습니다.',
            'function sr_popup_layer_save_skin_key(PDO $pdo, string $skinKey): void',
        ],
        'action_needles' => [
            '$popupLayerSkinOptions = sr_popup_layer_skin_options()',
            "sr_post_string('popup_layer_skin_key', 40)",
            "sr_post_string('skin_key', 40)",
            'if (!isset($popupLayerSkinOptions[$postedSkinKey]))',
            'if (!isset($popupLayerSkinOptions[$skinKey]))',
            'sr_popup_layer_save_settings($pdo, [',
            "'popup_layer_skin_key' => \$popupLayerSkinKey",
            "'skin_key' => \$skinKey",
        ],
        'view_needles' => [
            "sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_popup_layer_skin_key'",
            '<select id="popup_layer_admin_popup_layer_settings_popup_layer_skin_key" name="popup_layer_skin_key" class="form-select" required>',
            "sr_admin_form_label_help_html('popup_layer_admin_popup_layers_skin_key'",
            '<select id="popup_layer_admin_popup_layers_skin_key" name="skin_key" class="form-select" required>',
            'foreach ($popupLayerSkinOptions as $skinKey => $skinOption)',
        ],
        'render_needles' => [
            "\$skinKey = sr_popup_layer_skin_key(['popup_layer_skin_key' => (string) (\$row['skin_key'] ?? 'basic')])",
            'sr_popup_layer_render_stack($skinPopups, $skinKey)',
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
            'function sr_member_skin_options(): array',
            'sr_filter_view_options([',
            "'login' => SR_ROOT . '/modules/member/skins/basic/login.php'",
            'sr_member_required_skin_view_keys()',
            'function sr_member_required_skin_view_keys(): array',
            "sr_t('member::settings.skin.default_missing')",
        ],
        'action_needles' => [
            "sr_post_string('member_skin_key', 40)",
            'if (!isset(sr_member_skin_options()[$memberSkinKey]))',
            "['member_skin_key', (string) \$settings['member_skin_key'], 'string']",
        ],
        'view_needles' => [
            "sr_admin_form_label_help_html('member_admin_settings_member_skin_key'",
            '<select id="member_admin_settings_member_skin_key" name="member_skin_key" class="form-select">',
            'foreach (sr_member_skin_options() as $skinKey => $skinOption)',
        ],
        'render_needles' => [
            'sr_member_skin_view(sr_member_skin_key($memberSettings),',
        ],
    ],
    [
        'label' => 'Community layout',
        'helper' => 'modules/community/helpers/presentation.php',
        'action' => 'modules/community/actions/admin-settings.php',
        'view' => 'modules/community/views/admin-settings.php',
        'render_views' => ['modules/community/actions/home.php'],
        'files' => ['modules/community/layouts/basic/home.php', 'modules/community/layout-options.php'],
        'helper_needles' => [
            'function sr_community_layout_key(array $settings',
            'function sr_community_layout_home_view(string $layoutKey',
            "SR_ROOT . '/modules/community/layouts/basic/home.php'",
            "sr_t('community::runtime.layout_home_view_missing')",
        ],
        'action_needles' => [
            '$communityLayoutOptions = sr_public_layout_options($pdo)',
            "sr_post_string('layout_key', 80)",
            'if (!isset($communityLayoutOptions[$layoutKey]))',
            "['layout_key', \$layoutKey, 'string']",
        ],
        'view_needles' => [
            "sr_admin_form_label_help_html('community_admin_settings_layout_key'",
            '<select id="community_admin_settings_layout_key" name="layout_key" class="form-select">',
            'foreach ($communityLayoutOptions as $layoutKey => $layoutOption)',
        ],
        'render_needles' => [
            '$communityLayoutKey = sr_community_layout_key($settings',
            'sr_community_layout_home_view($communityLayoutKey',
        ],
    ],
    [
        'label' => 'Community board skin',
        'helper' => 'modules/community/helpers/presentation.php',
        'action' => 'modules/community/actions/admin-boards.php',
        'view' => 'modules/community/views/admin-boards.php',
        'render_views' => ['modules/community/actions/list.php', 'modules/community/actions/view.php', 'modules/community/actions/write.php', 'modules/community/actions/edit.php'],
        'files' => [
            'modules/community/skins/basic/skin.php',
            'modules/community/skins/basic/list.php',
            'modules/community/skins/basic/view.php',
            'modules/community/skins/basic/form.php',
            'modules/community/actions/skin-action.php',
        ],
        'helper_needles' => [
            'function sr_community_skin_files(): array',
            'function sr_community_skin_options(): array',
            "'basic' => SR_ROOT . '/modules/community/skins/basic/skin.php'",
            'function sr_community_skin_definition_is_valid(string $skinKey, array $definition): bool',
            'function sr_community_required_skin_view_keys(): array',
            "return ['list', 'post', 'form'];",
            'function sr_community_skin_action(string $skinKey, string $actionKey, string $method): ?array',
        ],
        'action_needles' => [
            '$communitySkinOptions = sr_community_skin_options()',
            "sr_post_string('skin_key', 40)",
            'if (!isset($communitySkinOptions[$skinKey]))',
            "sr_community_set_board_setting(\$pdo, \$boardId, 'skin_key', \$skinKey, 'string')",
        ],
        'view_needles' => [
            "sr_admin_form_label_help_html('community_admin_boards_skin_key'",
            'name="skin_key" class="form-select"',
            'foreach ($communitySkinOptions as $skinKey => $skinOption)',
        ],
        'render_needles' => [
            '$skinKey = sr_community_board_skin_key($pdo,',
            'sr_community_skin_view($skinKey,',
        ],
    ],
];

foreach ($targets as $target) {
    $label = (string) $target['label'];
    sr_skin_theme_check_contains((string) $target['helper'], $target['helper_needles'], $label . ' helper');
    sr_skin_theme_check_contains($target['action'], $target['action_needles'], $label . ' admin action');
    sr_skin_theme_check_contains($target['view'], $target['view_needles'], $label . ' admin view');

    foreach ($target['files'] as $file) {
        sr_skin_theme_check_file_exists((string) $file, $label);
    }

    $renderNeedles = $target['render_needles'];
    $renderContent = '';
    foreach ($target['render_views'] as $renderView) {
        $renderContent .= "\n" . sr_skin_theme_check_read((string) $renderView);
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
        $content = sr_skin_theme_check_read($relativePath);
        if ($content === '') {
            continue;
        }

        if (preg_match('/<(?:input|textarea)\b[^>]*\bname="[^"]*(?:skin|theme)[^"]*"/i', $content) === 1) {
            $errors[] = 'Skin/theme keys must be selected, not typed, in view file: ' . $relativePath;
        }
    }
}

sr_skin_theme_check_contains('modules/admin/views/settings.php', [
    '<select id="admin_settings_public_layout_key" name="public_layout_key" class="form-select">',
    'foreach ($publicLayoutOptions as $layoutKey => $layoutOption)',
], 'Public layout setting UI');

sr_skin_theme_check_contains('core/helpers/output.php', [
    'function sr_filter_view_options(array $options, array $requiredViewKeys, string $label): array',
    'function sr_view_option_has_required_views(array $option, array $requiredViewKeys): bool',
    '기본 공개 레이아웃 파일이 누락되었습니다.',
], 'Shared view option validation');

sr_skin_theme_check_contains('modules/admin/helpers/shell.php', [
    'function sr_admin_choice_label_parts(string $labelText): array',
    'function sr_admin_choice_label_html(string $labelText): string',
    '\'<span class="sr-only">\' . sr_e($hiddenText) . \'</span>\'',
], 'Admin form explicit choice labels');

sr_skin_theme_check_not_contains('modules/admin/helpers/shell.php', [
    'DOMDocument',
    'loadHTML',
    'sr_admin_normalize_content_html',
    'sr_admin_normalize_form_controls',
    'ob_start',
    'ob_get_clean',
], 'Admin shell post-render DOM normalization');

sr_skin_theme_check_contains('modules/admin/helpers.php', [
    "require_once SR_ROOT . '/modules/admin/helpers/icons.php';",
], 'Admin icon contract loading');

sr_skin_theme_check_contains('modules/admin/helpers/icons.php', [
    'function sr_admin_icon_symbols(): array',
    'function sr_admin_menu_symbol_allowed(string $name): bool',
    'function sr_admin_material_icon_names(): array',
    'function sr_admin_material_icon_name(string $symbolName): string',
    "'module_menu' => true",
    "'module_menu' => false",
], 'Admin icon common contract');

sr_skin_theme_check_contains('modules/admin/skins/basic/layout-header.php', [
    'sr_icon(',
    'sr_icon_bootstrap_script();',
], 'Admin skin Material icon rendering');

sr_skin_theme_check_not_contains('modules/admin/skins/basic/layout-header.php', [
    '<svg',
    '<use',
    '<symbol id="admin-menu-icon-settings"',
    '<symbol id="admin-menu-icon-users"',
    'admin-menu-icon-',
], 'Admin skin legacy SVG icon markup');

sr_skin_theme_check_admin_skin_material_icons();
sr_skin_theme_check_admin_icon_contract_docs();

sr_skin_theme_check_contains(['assets/ui-kit.css', 'modules/admin/assets/common.css'], [
    '.form-checkbox:checked',
    'background-image:url("data:image/svg+xml',
], 'Checkbox checked indicator');

if ($errors !== []) {
    fwrite(STDERR, "skin/layout UI checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "skin/layout UI checks completed.\n";
