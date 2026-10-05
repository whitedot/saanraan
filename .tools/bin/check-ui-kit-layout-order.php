#!/usr/bin/env php
<?php

declare(strict_types=1);
define('SR_ROOT', dirname(__DIR__, 2));
require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

// Register the actual provider contracts in a DB-free fixture.
sr_public_layout_options(null, true);
foreach ($GLOBALS['sr_public_layout_options_runtime_cache'] as &$options) {
    foreach (['content', 'community'] as $provider) {
        foreach (require SR_ROOT . '/modules/' . $provider . '/layout-options.php' as $key => $option) {
            $options[$key] = sr_public_layout_normalized_option($key, $option, $provider);
        }
    }
}
unset($options);

$errors = [];
$assert = static function (bool $ok, string $message) use (&$errors): void {
    if (!$ok) {
        $errors[] = $message;
    }
};
foreach (['content', 'community'] as $owner) {
    foreach (['common.basic', 'content.basic', 'community.basic'] as $layout) {
        foreach (['basic', 'missing_theme'] as $theme) {
            $settings = ['layout_key' => $layout, 'theme_key' => $theme];
            $screen = ['stylesheets' => ['/modules/reaction/assets/module.css', '/modules/ckeditor/assets/saanraan-ckeditor.css']];
            $context = $owner === 'content'
                ? sr_content_public_layout_context($settings, $screen)
                : sr_community_public_layout_context($settings, $screen);
            $context = sr_public_layout_context_with_shell_assets($context, $layout, null, true);
            $expected = [
                '/modules/' . $owner . '/theme/basic/assets/reset.css',
                '/modules/' . $owner . '/theme/basic/assets/common.css',
            ];
            if ($layout !== 'common.basic') {
                $provider = explode('.', $layout)[0];
                $expected[] = '/modules/' . $provider . '/theme/basic/assets/layout.css';
            }
            $expected[] = '/modules/' . $owner . '/theme/basic/assets/module.css';
            $expected = array_merge($expected, $screen['stylesheets']);
            $assert($context['stylesheets'] === $expected, "$owner / $layout / $theme: foundation, selected shell, body, screen assets must retain their ownership and order.");
            $assert(count($context['stylesheets']) === count(array_unique($context['stylesheets'])), 'No duplicate stylesheets.');
            $assert($context['style_profile'] === 'module', 'A foreign shell must not replace the owner kit.');
            if ($layout === 'common.basic') {
                $pdo = null;
                $site = [];
                $seo = [];
                $contentHtml = '';
                $layoutContext = $context;
                ob_start();
                include SR_ROOT . '/layouts/public/basic/layout.php';
                $html = (string) ob_get_clean();
                $kitPosition = strpos($html, '/modules/' . $owner . '/theme/basic/assets/common.css');
                $shellPosition = strpos($html, '/assets/layout.css');
                $bodyPosition = strpos($html, '/modules/' . $owner . '/theme/basic/assets/module.css');
                $assert(is_int($kitPosition) && is_int($shellPosition) && is_int($bodyPosition) && $kitPosition < $shellPosition && $shellPosition < $bodyPosition, "$owner common shell must render between its kit and body styles.");
            }
        }
    }
}
$assets = ['/modules/reaction/assets/module.css', '/modules/content/theme/basic/assets/reset.css', '/modules/content/theme/basic/assets/common.css', '/modules/content/theme/basic/assets/module.css'];
$assert(sr_public_layout_insert_before_module_asset($assets, ['/shell.css'], 'content') === [$assets[0], $assets[1], $assets[2], '/shell.css', $assets[3]], 'A foreign module must not become the owner insertion anchor.');
$assert(sr_public_layout_insert_before_module_asset(['/modules/member/skins/basic/skin.css'], ['/shell.css']) === ['/modules/member/skins/basic/skin.css', '/shell.css'], 'A utility screen without a module anchor retains the append contract.');
$assert(sr_public_layout_insert_before_module_asset(['/modules/content/assets/module.js'], ['/theme.js'], 'content') === ['/theme.js', '/modules/content/assets/module.js'], 'Theme scripts precede the consuming module script.');
if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
echo "UI kit cross-layout asset order checks passed.\n";
