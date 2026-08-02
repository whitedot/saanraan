<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function sr_public_style_profile_paths(string $profile): array
{
    $profile = strtolower(trim($profile));
    $profile = sr_public_style_profile_key($profile);

    if ($profile === 'module') {
        return [];
    }

    $paths = [
        '/assets/reset.css',
    ];

    if ($profile === 'kit') {
        $paths[] = '/assets/common.css';
    }

    return $paths;
}

function sr_public_style_profile_key(string $profile): string
{
    $profile = strtolower(trim($profile));

    return in_array($profile, ['minimal', 'kit', 'install', 'module'], true) ? $profile : 'kit';
}

function sr_stylesheet_tag(array $stylesheets = [], ?PDO $pdo = null, array $options = []): string
{
    $tags = [
        '<link rel="preload" as="style" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" crossorigin>',
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" crossorigin>',
    ];

    $profile = is_string($options['style_profile'] ?? null) ? sr_public_style_profile_key((string) $options['style_profile']) : 'kit';
    $stylesheetPaths = [];

    foreach (sr_public_style_profile_paths($profile) as $stylesheet) {
        $stylesheetPaths[$stylesheet] = $stylesheet;
    }

    foreach ($stylesheets as $stylesheet) {
        if (!is_string($stylesheet) || !sr_is_safe_relative_url($stylesheet)) {
            continue;
        }

        $stylesheetPaths[$stylesheet] = $stylesheet;
    }

    foreach ($stylesheetPaths as $stylesheet) {
        $tags[] = '<link rel="stylesheet" href="' . sr_e(sr_asset_url($stylesheet)) . '">';
    }

    return implode(PHP_EOL, array_values(array_filter($tags, 'strlen')));
}

function sr_script_tags(array $scripts = []): string
{
    $tags = [];
    $scriptPaths = [];

    foreach ($scripts as $script) {
        if (!is_string($script) || !sr_is_safe_relative_url($script)) {
            continue;
        }

        $scriptPaths[$script] = $script;
    }

    foreach ($scriptPaths as $script) {
        $tags[] = '<script src="' . sr_e(sr_asset_url($script)) . '" defer></script>';
    }

    return implode(PHP_EOL, $tags);
}

function sr_asset_url(string $path): string
{
    $url = sr_url($path);
    if (!str_starts_with($path, '/')) {
        return $url;
    }

    $file = SR_ROOT . $path;
    if (!is_file($file)) {
        return $url;
    }

    return $url . '?v=' . rawurlencode((string) filemtime($file));
}

function sr_color_scheme_options(): array
{
    return [
        'light' => '라이트',
        'dark' => '다크',
        'system' => '시스템 설정',
    ];
}

function sr_color_scheme(?array $site = null): string
{
    $colorScheme = (string) (($site ?? [])['ui_color_scheme'] ?? 'light');

    return isset(sr_color_scheme_options()[$colorScheme]) ? $colorScheme : 'light';
}
