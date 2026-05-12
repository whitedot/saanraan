<?php

declare(strict_types=1);

function toy_community_theme_key(array $settings): string
{
    $themeKey = (string) ($settings['theme_key'] ?? 'basic');
    return isset(toy_community_theme_options()[$themeKey]) ? $themeKey : 'basic';
}

function toy_community_theme_options(): array
{
    return [
        'basic' => [
            'label' => '기본',
            'views' => [
                'home' => TOY_ROOT . '/modules/community/themes/basic/home.php',
            ],
        ],
    ];
}

function toy_community_theme_view(string $themeKey, string $viewKey): string
{
    $options = toy_community_theme_options();
    $view = (string) ($options[$themeKey]['views'][$viewKey] ?? $options['basic']['views'][$viewKey] ?? '');

    return is_file($view) ? $view : (string) ($options['basic']['views'][$viewKey] ?? '');
}

function toy_community_skin_key(array $boardSettings = []): string
{
    $skinKey = (string) ($boardSettings['skin_key'] ?? 'basic');
    return isset(toy_community_skin_options()[$skinKey]) ? $skinKey : 'basic';
}

function toy_community_skin_options(): array
{
    return [
        'basic' => [
            'label' => '기본',
            'views' => [
                'list' => TOY_ROOT . '/modules/community/skins/basic/list.php',
                'post' => TOY_ROOT . '/modules/community/skins/basic/view.php',
                'form' => TOY_ROOT . '/modules/community/skins/basic/form.php',
            ],
        ],
    ];
}

function toy_community_board_skin_key(PDO $pdo, array $board): string
{
    $boardId = isset($board['board_id']) ? (int) $board['board_id'] : (int) ($board['id'] ?? 0);
    $skinKey = $boardId > 0 ? toy_community_board_setting_value($pdo, $boardId, 'skin_key') : null;
    return toy_community_skin_key(['skin_key' => is_string($skinKey) ? $skinKey : 'basic']);
}

function toy_community_skin_view(string $skinKey, string $viewKey): string
{
    $options = toy_community_skin_options();
    $view = (string) ($options[$skinKey]['views'][$viewKey] ?? $options['basic']['views'][$viewKey] ?? '');

    return is_file($view) ? $view : (string) ($options['basic']['views'][$viewKey] ?? '');
}
