<?php

declare(strict_types=1);

function toy_community_theme_key(array $settings): string
{
    $themeKey = (string) ($settings['theme_key'] ?? 'basic');
    return $themeKey === 'basic' ? 'basic' : 'basic';
}

function toy_community_theme_view(string $themeKey, string $viewKey): string
{
    $views = [
        'basic' => [
            'home' => TOY_ROOT . '/modules/community/themes/basic/home.php',
        ],
    ];

    return (string) ($views[$themeKey][$viewKey] ?? $views['basic']['home']);
}

function toy_community_skin_key(array $boardSettings = []): string
{
    $skinKey = (string) ($boardSettings['skin_key'] ?? 'basic');
    return $skinKey === 'basic' ? 'basic' : 'basic';
}

function toy_community_skin_view(string $skinKey, string $viewKey): string
{
    $views = [
        'basic' => [
            'list' => TOY_ROOT . '/modules/community/skins/basic/list.php',
            'post' => TOY_ROOT . '/modules/community/skins/basic/view.php',
            'form' => TOY_ROOT . '/modules/community/skins/basic/form.php',
        ],
    ];

    return (string) ($views[$skinKey][$viewKey] ?? $views['basic']['list']);
}
