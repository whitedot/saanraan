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

function sr_skin_theme_check_order(string $path, string $firstNeedle, string $secondNeedle, string $label): void
{
    global $errors;
    $content = sr_skin_theme_check_read($path);
    if ($content === '') {
        return;
    }

    $firstPosition = strpos($content, $firstNeedle);
    $secondPosition = strpos($content, $secondNeedle);
    if ($firstPosition === false || $secondPosition === false) {
        $errors[] = $label . ' cannot verify field order.';
        return;
    }

    if ($firstPosition > $secondPosition) {
        $errors[] = $label . ' must place theme before layout.';
    }
}

function sr_skin_theme_check_file_exists(string $path, string $label): void
{
    global $root, $errors;
    if (!is_file($root . '/' . $path)) {
        $errors[] = $label . ' file is missing: ' . $path;
    }
}

function sr_skin_theme_check_file_missing(string $path, string $label): void
{
    global $root, $errors;
    if (is_file($root . '/' . $path)) {
        $errors[] = $label . ' file must not exist: ' . $path;
    }
}

function sr_skin_theme_check_admin_theme_material_icons(): void
{
    global $errors;
    $settingsContent = sr_skin_theme_check_read('modules/admin/helpers/settings.php');
    if ($settingsContent === '') {
        return;
    }

    preg_match_all("#'layout-header'\\s*=>\\s*SR_ROOT\\s*\\.\\s*'([^']+)'#", $settingsContent, $matches);
    $layoutHeaders = $matches[1] ?? [];
    if ($layoutHeaders === []) {
        $errors[] = 'Admin theme options must declare layout-header view paths.';
        return;
    }

    foreach ($layoutHeaders as $layoutHeader) {
        $relativePath = ltrim((string) $layoutHeader, '/');
        $content = sr_skin_theme_check_read($relativePath);
        if ($content === '') {
            continue;
        }

        if (!str_contains($content, 'sr_material_icon_html(') && !str_contains($content, 'sr_icon(')) {
            $errors[] = 'Admin theme layout-header must render Material icons through the common helper: ' . $relativePath;
        }

        if (!str_contains($content, 'sr_icon_bootstrap_script();')) {
            $errors[] = 'Admin theme layout-header must load the Material icon readiness bootstrap: ' . $relativePath;
        }

        if (preg_match('/<(?:svg|use)\b/', $content) === 1 || str_contains($content, 'admin-menu-icon-')) {
            $errors[] = 'Admin theme layout-header must not render legacy SVG icon markup: ' . $relativePath;
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
        'label' => 'Admin theme',
        'helper' => 'modules/admin/helpers/settings.php',
        'action' => 'modules/admin/actions/settings.php',
        'view' => 'modules/admin/views/settings.php',
        'render_views' => ['modules/admin/views/layout-header.php', 'modules/admin/views/layout-footer.php'],
        'files' => ['modules/admin/themes/basic/layout-header.php', 'modules/admin/themes/basic/layout-footer.php'],
        'helper_needles' => [
            'function sr_admin_theme_options(): array',
            'sr_filter_view_options([',
            "'layout-header' => SR_ROOT . '/modules/admin/themes/basic/layout-header.php'",
            "'layout-footer' => SR_ROOT . '/modules/admin/themes/basic/layout-footer.php'",
            "], ['layout-header', 'layout-footer'], 'admin theme')",
            '기본 관리자 테마 view 파일이 누락되었습니다.',
        ],
        'action_needles' => [
            '$adminThemeOptions = sr_admin_theme_options()',
            "sr_post_string('admin_theme_key', 40)",
            'if (!isset($adminThemeOptions[$postedThemeKey]))',
            'sr_admin_save_theme_key($pdo, $postedThemeKey)',
            "'admin_theme_key' => \$adminThemeKey",
        ],
        'view_needles' => [
            "sr_admin_form_label_help_html('admin_settings_admin_theme_key'",
            '<select id="admin_settings_admin_theme_key" name="admin_theme_key" class="form-select">',
            'foreach ($adminThemeOptions as $themeKey => $themeOption)',
        ],
        'render_needles' => [
            "sr_admin_theme_view(sr_admin_theme_key(\$adminSettings), 'layout-header')",
            "sr_admin_theme_view(sr_admin_theme_key(\$adminSettings), 'layout-footer')",
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
        'helper_forbidden' => [
            "'/assets/ui-kit-layout.css'",
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
        'files' => ['modules/community/theme/basic/home.php', 'modules/community/layout-options.php'],
        'helper_needles' => [
            'function sr_community_layout_key(array $settings',
            'function sr_community_layout_home_view(string $layoutKey',
            "SR_ROOT . '/modules/community/theme/basic/home.php'",
            "sr_t('community::runtime.layout_home_view_missing')",
        ],
        'action_needles' => [
            '$communityLayoutOptions = sr_community_layout_options($pdo)',
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
            'sr_community_layout_key($settings,',
            'sr_community_layout_home_view($communityLayoutKey',
        ],
    ],
    [
        'label' => 'Community board skin',
        'helper' => 'modules/community/helpers/presentation.php',
        'action' => ['modules/community/actions/admin-boards.php', 'modules/community/helpers/admin-boards.php'],
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
    if (isset($target['helper_forbidden']) && is_array($target['helper_forbidden'])) {
        sr_skin_theme_check_not_contains((string) $target['helper'], $target['helper_forbidden'], $label . ' helper');
    }
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
    '<select id="admin_settings_public_theme_key" name="public_theme_key" class="form-select">',
    'foreach ($publicLayoutOptions as $layoutKey => $layoutOption)',
    'foreach ($publicThemeOptions as $themeKey => $themeOption)',
    '$publicLayoutHealthWarnings',
], 'Public layout setting UI');

sr_skin_theme_check_contains('core/helpers/output.php', [
    'function sr_public_layout_normalized_option(string $layoutKey, array $layoutOption, string $fallbackProviderKey = \'\'): array',
    'function sr_public_layout_support_targets(array $supports): array',
    'function sr_public_layout_domains(): array',
    'function sr_public_layout_options_for_targets(?PDO $pdo, array $requiredTargets',
    'function sr_public_layout_context_consumer_targets(array $layoutContext',
    'function sr_public_layout_module_setting_targets(string $moduleKey): array',
    'function sr_public_layout_shell_stylesheets(string $layoutKey',
    'function sr_public_layout_theme_view_file(array $layoutOption',
    'function sr_public_layout_module_theme_asset_url(string $moduleKey',
    'function sr_public_theme_options(?PDO $pdo = null',
    'function sr_public_theme_key(?array $site = null',
    'function sr_view_theme_options(string $themeRoot',
    'function sr_view_theme_file(string $themeRoot',
    'function sr_module_view_theme_asset_url(string $moduleKey',
    "'/modules/' . \$moduleKey . '/theme/'",
    "SR_ROOT . '/core/views/theme'",
    'function sr_public_layout_context_with_theme_assets(array $layoutContext',
    'function sr_public_route_domains(PDO $pdo',
    'function sr_public_layout_effective_key(string $layoutKey',
    'function sr_public_layout_health_warnings(PDO $pdo',
    "'unsupported_target' =>",
], 'Public module theme runtime helper');

$publicModuleLayoutTargets = [
    'site',
    'content',
    'content.home',
    'content.group',
    'content.view',
    'community',
    'community.home',
    'community.group',
    'community.list',
    'community.post',
    'community.form',
    'community.search',
    'quiz',
    'quiz.home',
    'quiz.view',
    'quiz.result',
    'survey',
    'survey.home',
    'survey.view',
    'survey.complete',
];

foreach ([
    'content' => ['content.home', 'content.group', 'content.view'],
    'community' => ['community.home', 'community.group', 'community.list', 'community.post', 'community.form', 'community.search'],
    'quiz' => ['quiz.home', 'quiz.view', 'quiz.result'],
    'survey' => ['survey.home', 'survey.view', 'survey.complete'],
] as $moduleKey => $requiredTargets) {
    sr_skin_theme_check_contains('modules/' . $moduleKey . '/helpers' . ($moduleKey === 'community' ? '/presentation' : '') . '.php', [
        'function sr_' . $moduleKey . '_layout_required_targets(): array',
        'function sr_' . $moduleKey . '_layout_options(PDO $pdo, bool $includeInstalledModules = false): array',
        'sr_public_layout_options_for_targets($pdo, sr_' . $moduleKey . '_layout_required_targets(), $includeInstalledModules)',
    ], ucfirst($moduleKey) . ' layout target helper');

    sr_skin_theme_check_contains('modules/' . $moduleKey . '/layout-options.php', $publicModuleLayoutTargets, ucfirst($moduleKey) . ' cross-module layout support targets');
}

sr_skin_theme_check_contains([
    'modules/content/actions/admin-settings.php',
    'modules/community/actions/admin-settings.php',
    'modules/quiz/actions/admin-settings.php',
    'modules/survey/actions/admin-settings.php',
], [
    'layout_options($pdo)',
], 'Public module layout setting target-filtered options');

foreach (['content', 'community', 'quiz', 'survey'] as $moduleKey) {
    sr_skin_theme_check_contains('modules/' . $moduleKey . '/actions/ui-kit.php', [
        'layout_options($pdo, true)',
        'theme_view_file',
        '/theme/basic/ui-kit.php',
    ], ucfirst($moduleKey) . ' UI kit layout/theme preview action');
}

sr_skin_theme_check_contains('modules/content/helpers.php', [
    'function sr_content_theme_options(): array',
    "SR_ROOT . '/modules/content/theme'",
    "['home.php' => true, 'group.php' => true, 'content.php' => true, 'ui-kit.php' => true]",
    "sr_public_layout_module_theme_asset_url('content', \$themeKey, 'ui-kit.css')",
    "sr_public_layout_module_theme_asset_url('content', \$themeKey, 'ui-kit-layout.css')",
    "sr_module_view_theme_stylesheet_url('content'",
], 'Content local view theme helper');

sr_skin_theme_check_contains('modules/community/helpers/presentation.php', [
    'function sr_community_theme_options(): array',
    "SR_ROOT . '/modules/community/theme'",
    "'search.php' => true",
    "'ui-kit.php' => true",
], 'Community local view theme helper');

sr_skin_theme_check_contains('modules/community/helpers/levels.php', [
    "sr_public_layout_module_theme_asset_url('community', \$themeKey, 'ui-kit.css')",
    "sr_public_layout_module_theme_asset_url('community', \$themeKey, 'ui-kit-layout.css')",
    "sr_module_view_theme_stylesheet_url('community'",
], 'Community local view theme asset helper');

sr_skin_theme_check_contains('modules/quiz/helpers.php', [
    'function sr_quiz_theme_options(): array',
    "SR_ROOT . '/modules/quiz/theme'",
    "['home.php', 'view.php', 'result.php', 'ui-kit.php']",
    "array_merge(sr_quiz_skin_views(), ['ui-kit'])",
    "sr_public_layout_module_theme_asset_url('quiz', \$themeKey, 'ui-kit.css')",
    "sr_public_layout_module_theme_asset_url('quiz', \$themeKey, 'ui-kit-layout.css')",
    "sr_module_view_theme_stylesheet_url('quiz'",
], 'Quiz local view theme helper');

sr_skin_theme_check_contains('modules/survey/helpers.php', [
    'function sr_survey_theme_options(): array',
    "SR_ROOT . '/modules/survey/theme'",
    "['home.php', 'view.php', 'complete.php', 'ui-kit.php']",
    "array_merge(sr_survey_skin_views(), ['ui-kit'])",
    "sr_public_layout_module_theme_asset_url('survey', \$themeKey, 'ui-kit.css')",
    "sr_public_layout_module_theme_asset_url('survey', \$themeKey, 'ui-kit-layout.css')",
    "sr_module_view_theme_stylesheet_url('survey'",
], 'Survey local view theme helper');

sr_skin_theme_check_contains([
    'modules/content/actions/ui-kit.php',
    'modules/community/actions/ui-kit.php',
    'modules/quiz/actions/ui-kit.php',
    'modules/survey/actions/ui-kit.php',
], [
    'theme_view_file',
    '/theme/basic/ui-kit.php',
], 'Module UI kit action selected theme view include');

foreach ([
    'core/views/theme/basic/home.php',
    'modules/content/theme/basic/home.php',
    'modules/content/theme/basic/group.php',
    'modules/content/theme/basic/content.php',
    'modules/content/theme/basic/ui-kit.php',
    'modules/community/theme/basic/home.php',
    'modules/community/theme/basic/group.php',
    'modules/community/theme/basic/list.php',
    'modules/community/theme/basic/post.php',
    'modules/community/theme/basic/form.php',
    'modules/community/theme/basic/search.php',
    'modules/community/theme/basic/ui-kit.php',
    'modules/quiz/theme/basic/home.php',
    'modules/quiz/theme/basic/view.php',
    'modules/quiz/theme/basic/result.php',
    'modules/quiz/theme/basic/ui-kit.php',
    'modules/survey/theme/basic/home.php',
    'modules/survey/theme/basic/view.php',
    'modules/survey/theme/basic/complete.php',
    'modules/survey/theme/basic/ui-kit.php',
    'core/views/theme/sample/home.php',
    'modules/content/theme/sample/home.php',
    'modules/content/theme/sample/group.php',
    'modules/content/theme/sample/content.php',
    'modules/content/theme/sample/ui-kit.php',
    'modules/community/theme/sample/home.php',
    'modules/community/theme/sample/group.php',
    'modules/community/theme/sample/list.php',
    'modules/community/theme/sample/post.php',
    'modules/community/theme/sample/form.php',
    'modules/community/theme/sample/search.php',
    'modules/community/theme/sample/ui-kit.php',
    'modules/quiz/theme/sample/home.php',
    'modules/quiz/theme/sample/view.php',
    'modules/quiz/theme/sample/result.php',
    'modules/quiz/theme/sample/ui-kit.php',
    'modules/survey/theme/sample/home.php',
    'modules/survey/theme/sample/view.php',
    'modules/survey/theme/sample/complete.php',
    'modules/survey/theme/sample/ui-kit.php',
    'assets/theme/sample.css',
    'modules/content/theme/basic/layout.php',
    'modules/content/theme/basic/assets/reset.css',
    'modules/content/theme/basic/assets/ui-kit.css',
    'modules/content/theme/basic/assets/ui-kit-layout.css',
    'modules/content/theme/basic/assets/layout.css',
    'modules/content/theme/basic/assets/module.css',
    'modules/content/theme/sample/layout.php',
    'modules/content/theme/sample/assets/reset.css',
    'modules/content/theme/sample/assets/ui-kit.css',
    'modules/content/theme/sample/assets/ui-kit-layout.css',
    'modules/content/theme/sample/assets/layout.css',
    'modules/content/theme/sample/assets/module.css',
    'modules/content/theme/sample/assets/theme.css',
    'modules/community/theme/basic/layout.php',
    'modules/community/theme/basic/home.php',
    'modules/community/theme/basic/assets/reset.css',
    'modules/community/theme/basic/assets/ui-kit.css',
    'modules/community/theme/basic/assets/ui-kit-layout.css',
    'modules/community/theme/basic/assets/layout.css',
    'modules/community/theme/basic/assets/module.css',
    'modules/community/theme/sample/layout.php',
    'modules/community/theme/sample/assets/reset.css',
    'modules/community/theme/sample/assets/ui-kit.css',
    'modules/community/theme/sample/assets/ui-kit-layout.css',
    'modules/community/theme/sample/assets/layout.css',
    'modules/community/theme/sample/assets/module.css',
    'modules/community/theme/sample/assets/theme.css',
    'modules/quiz/theme/basic/layout.php',
    'modules/quiz/theme/basic/assets/reset.css',
    'modules/quiz/theme/basic/assets/ui-kit.css',
    'modules/quiz/theme/basic/assets/ui-kit-layout.css',
    'modules/quiz/theme/basic/assets/layout.css',
    'modules/quiz/theme/basic/assets/module.css',
    'modules/quiz/theme/basic/assets/skin.css',
    'modules/quiz/theme/sample/layout.php',
    'modules/quiz/theme/sample/assets/reset.css',
    'modules/quiz/theme/sample/assets/ui-kit.css',
    'modules/quiz/theme/sample/assets/ui-kit-layout.css',
    'modules/quiz/theme/sample/assets/layout.css',
    'modules/quiz/theme/sample/assets/module.css',
    'modules/quiz/theme/sample/assets/skin.css',
    'modules/quiz/theme/sample/assets/theme.css',
    'modules/survey/theme/basic/layout.php',
    'modules/survey/theme/basic/assets/reset.css',
    'modules/survey/theme/basic/assets/ui-kit.css',
    'modules/survey/theme/basic/assets/ui-kit-layout.css',
    'modules/survey/theme/basic/assets/layout.css',
    'modules/survey/theme/basic/assets/module.css',
    'modules/survey/theme/sample/layout.php',
    'modules/survey/theme/sample/assets/reset.css',
    'modules/survey/theme/sample/assets/ui-kit.css',
    'modules/survey/theme/sample/assets/ui-kit-layout.css',
    'modules/survey/theme/sample/assets/layout.css',
    'modules/survey/theme/sample/assets/module.css',
    'modules/survey/theme/sample/assets/theme.css',
] as $themeFile) {
    sr_skin_theme_check_file_exists($themeFile, 'Local public view theme');
}

sr_skin_theme_check_contains([
    'modules/content/actions/home.php',
    'modules/content/actions/group.php',
    'modules/content/actions/view.php',
    'modules/community/actions/home.php',
    'modules/community/actions/group.php',
    'modules/community/actions/list.php',
    'modules/community/actions/view.php',
    'modules/community/actions/write.php',
    'modules/community/actions/edit.php',
    'modules/community/actions/search.php',
    'modules/quiz/actions/home.php',
    'modules/quiz/actions/view.php',
    'modules/survey/actions/home.php',
    'modules/survey/actions/view.php',
], [
    'public_view_file',
], 'Public module theme view-root include flow');

sr_skin_theme_check_not_contains([
    'modules/quiz/actions/home.php',
    'modules/quiz/actions/view.php',
    'modules/survey/actions/home.php',
    'modules/survey/actions/view.php',
], [
    'render_skin(',
], 'Public theme flow must not call skin renderer directly');

sr_skin_theme_check_not_contains([
    'modules/content/actions/home.php',
    'modules/content/actions/group.php',
    'modules/content/actions/view.php',
    'modules/community/actions/home.php',
    'modules/community/actions/group.php',
    'modules/community/actions/list.php',
    'modules/community/actions/view.php',
    'modules/community/actions/write.php',
    'modules/community/actions/edit.php',
    'modules/community/actions/search.php',
    'modules/quiz/actions/home.php',
    'modules/quiz/actions/view.php',
    'modules/survey/actions/home.php',
    'modules/survey/actions/view.php',
], [
    'sr_content_include_public_view(',
    'sr_community_include_public_view(',
    'sr_quiz_include_public_view(',
    'sr_survey_include_public_view(',
], 'Public theme action include scope');

sr_skin_theme_check_contains([
    'modules/content/views/admin-settings.php',
    'modules/community/views/admin-settings.php',
    'modules/quiz/views/admin-settings.php',
    'modules/survey/views/admin-settings.php',
], [
    'name="theme_key"',
    '공개 테마',
], 'Public module theme setting UI');

foreach ([
    'modules/content/views/admin-settings.php',
    'modules/community/views/admin-settings.php',
    'modules/quiz/views/admin-settings.php',
    'modules/survey/views/admin-settings.php',
] as $settingsViewPath) {
    sr_skin_theme_check_order($settingsViewPath, 'name="theme_key"', 'name="layout_key"', 'Public module theme/layout setting order: ' . $settingsViewPath);
}

sr_skin_theme_check_contains('modules/community/views/admin-settings.php', [
    'community_settings_help_theme',
    "communitySettingsHelp['theme']['id']",
], 'Community theme setting help UI');

sr_skin_theme_check_contains('modules/quiz/views/admin-settings.php', [
    'quiz-settings-help-theme-key',
    "quizSettingsHelp['theme_key']['id']",
], 'Quiz theme setting help UI');

sr_skin_theme_check_contains('modules/survey/views/admin-settings.php', [
    'survey-settings-help-theme-key',
    "surveySettingsHelp['theme_key']['id']",
], 'Survey theme setting help UI');

sr_skin_theme_check_contains('modules/content/views/admin-settings.php', [
    '콘텐츠 공개 레이아웃',
    '필요한 화면 대상을 지원하는 다른 모듈 레이아웃도 선택할 수 있습니다.',
], 'Content public layout setting copy');

sr_skin_theme_check_not_contains([
    'modules/content/views/admin-settings.php',
    'modules/content/lang/ko.php',
    'modules/content/views/content.php',
], [
    '새 콘텐츠를 만들 때 먼저 채울 공개 레이아웃입니다.',
    '기존 콘텐츠 값은 자동 변경되지 않습니다.',
    '콘텐츠 데이터의 호환용 레이아웃 값을 일괄 수정하지 않고 공개 출력에 적용합니다.',
    '기존 데이터 호환을 위해 저장하는 콘텐츠별 레이아웃 값입니다.',
    'revision 호환',
    "\$page['layout_key']",
    "'layout_key' => \$pageLayoutKey",
], 'Content layout setting must be module-setting scoped');

sr_skin_theme_check_file_missing('core/helpers/' . 'packages.php', 'External package helper');
sr_skin_theme_check_file_missing('modules/admin/actions/' . 'packages.php', 'Admin package action');
sr_skin_theme_check_file_missing('modules/admin/views/' . 'packages.php', 'Admin package view');
sr_skin_theme_check_file_missing('docs/skin-theme-' . 'packages.md', 'External package guide');

sr_skin_theme_check_not_contains([
    'index.php',
    'core/helpers.php',
    'core/helpers/output.php',
    'modules/admin/paths.php',
    'modules/admin/helpers/navigation.php',
    'modules/admin/views/settings.php',
    'modules/community/helpers/presentation.php',
    'modules/quiz/helpers.php',
    'modules/survey/helpers.php',
    '.htaccess',
], [
    '/sr-' . 'package-asset',
    'sr_' . 'package_',
    '/admin/' . 'packages',
    'sr-' . 'packages',
    'external_' . 'theme',
], 'External package flow is not used');

sr_skin_theme_check_contains('docs/public-module-themes.md', [
    'modules/{module_key}/theme/{theme_key}/assets/',
    '외부 package 디렉터리, 외부 manifest, 외부 package asset handler는 사용하지 않는다.',
    '관리자 화면은 이 공개 theme 체계의 적용 대상이 아니다.',
], 'Public module theme guide');

sr_skin_theme_check_not_contains([
    'modules/admin/helpers/settings.php',
    'modules/admin/actions/settings.php',
    'modules/admin/views/settings.php',
    'modules/admin/views/layout-header.php',
    'modules/admin/views/layout-footer.php',
    'modules/admin/module.php',
    'docs/admin-ui-guide.md',
    'docs/module-guide.md',
    'docs/core-decisions.md',
    'docs/operator-feature-list.md',
    'core/actions/install.php',
], [
    'admin_' . 'skin_key',
    'admin_' . 'skin',
    'sr_admin_' . 'skin',
    'modules/admin/' . 'skins',
    '관리자 ' . '스킨',
], 'Admin theme legacy naming');

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

sr_skin_theme_check_contains('modules/admin/themes/basic/layout-header.php', [
    'sr_icon(',
    'sr_icon_bootstrap_script();',
], 'Admin theme Material icon rendering');

sr_skin_theme_check_not_contains('modules/admin/themes/basic/layout-header.php', [
    '<svg',
    '<use',
    '<symbol id="admin-menu-icon-settings"',
    '<symbol id="admin-menu-icon-users"',
    'admin-menu-icon-',
], 'Admin theme legacy SVG icon markup');

sr_skin_theme_check_admin_theme_material_icons();
sr_skin_theme_check_admin_icon_contract_docs();

sr_skin_theme_check_contains(['assets/ui-kit.css', 'modules/admin/assets/common.css'], [
    '.form-checkbox:checked',
    'background-image:url("data:image/svg+xml',
], 'Checkbox checked indicator');

sr_skin_theme_check_contains([
    'assets/ui-kit.css',
    'modules/admin/assets/common.css',
    'modules/content/theme/basic/assets/ui-kit.css',
    'modules/content/theme/sample/assets/ui-kit.css',
    'modules/community/theme/basic/assets/ui-kit.css',
    'modules/community/theme/sample/assets/ui-kit.css',
    'modules/quiz/theme/basic/assets/ui-kit.css',
    'modules/quiz/theme/sample/assets/ui-kit.css',
    'modules/survey/theme/basic/assets/ui-kit.css',
    'modules/survey/theme/sample/assets/ui-kit.css',
], [
    '--modal-viewport-gap:clamp(',
    'width:calc(100% - var(--modal-viewport-gap) - var(--modal-viewport-gap))',
    'height:calc(100% - var(--modal-viewport-gap) - var(--modal-viewport-gap))',
], 'Fluid modal viewport margins');

if ($errors !== []) {
    fwrite(STDERR, "skin/layout UI checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "skin/layout UI checks completed.\n";
