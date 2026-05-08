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
