<?php

declare(strict_types=1);

function sr_community_layout_default_key(): string
{
    return 'community.basic';
}

function sr_community_layout_key(array $settings, ?array $site = null, ?PDO $pdo = null): string
{
    $layoutKey = (string) ($settings['layout_key'] ?? '');
    if ($layoutKey === '') {
        $layoutKey = sr_community_layout_default_key();
    }

    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    if ($layoutKey === sr_community_layout_default_key()) {
        return $layoutKey;
    }

    if ($pdo instanceof PDO) {
        $options = sr_public_layout_options($pdo);
        if (isset($options[$layoutKey])) {
            return $layoutKey;
        }

        $siteLayoutKey = sr_public_layout_key($site, $pdo);
        return isset($options[$siteLayoutKey]) ? $siteLayoutKey : sr_public_layout_default_key();
    }

    return preg_match('/\A[a-z0-9][a-z0-9_]{0,39}\.[a-z0-9][a-z0-9_]{0,39}\z/', $layoutKey) === 1
        ? $layoutKey
        : sr_public_layout_default_key();
}

function sr_community_layout_home_view(string $layoutKey, ?PDO $pdo = null): string
{
    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    $options = sr_public_layout_options($pdo);
    $view = (string) ($options[$layoutKey]['views']['community_home'] ?? '');
    if ($view !== '' && is_file($view)) {
        return $view;
    }

    $fallback = SR_ROOT . '/modules/community/layouts/basic/home.php';
    if (is_file($fallback)) {
        return $fallback;
    }

    throw new RuntimeException(sr_t('community::runtime.layout_home_view_missing'));
}

function sr_community_skin_key(array $boardSettings = []): string
{
    $skinKey = (string) ($boardSettings['skin_key'] ?? 'basic');
    return isset(sr_community_skin_options()[$skinKey]) ? $skinKey : 'basic';
}

function sr_community_skin_files(): array
{
    return [
        'basic' => SR_ROOT . '/modules/community/skins/basic/skin.php',
    ];
}

function sr_community_skin_options(): array
{
    $options = [];
    foreach (sr_community_skin_files() as $skinKey => $file) {
        $definition = sr_community_skin_definition((string) $skinKey);
        if (!sr_community_skin_definition_is_valid((string) $skinKey, $definition)) {
            continue;
        }

        $options[(string) $skinKey] = $definition;
    }

    return $options;
}

function sr_community_board_skin_key(PDO $pdo, array $board): string
{
    $boardId = isset($board['board_id']) ? (int) $board['board_id'] : (int) ($board['id'] ?? 0);
    $skinKey = $boardId > 0 ? sr_community_board_setting_value($pdo, $boardId, 'skin_key') : null;
    return sr_community_skin_key(['skin_key' => is_string($skinKey) ? $skinKey : 'basic']);
}

function sr_community_skin_view(string $skinKey, string $viewKey): string
{
    $options = sr_community_skin_options();
    $view = (string) ($options[$skinKey]['views'][$viewKey] ?? $options['basic']['views'][$viewKey] ?? '');

    if (is_file($view)) {
        return $view;
    }

    $fallback = (string) ($options['basic']['views'][$viewKey] ?? '');
    if (is_file($fallback)) {
        return $fallback;
    }

    throw new RuntimeException(sr_t('community::runtime.skin_view_missing'));
}

function sr_community_skin_definition(string $skinKey): array
{
    $files = sr_community_skin_files();
    $file = (string) ($files[$skinKey] ?? '');
    if ($file === '' || !is_file($file)) {
        return [];
    }

    $definition = include $file;
    if (!is_array($definition)) {
        return [];
    }

    $definition['skin_key'] = $skinKey;
    $definition['skin_dir'] = dirname($file);

    return $definition;
}

function sr_community_skin_definition_is_valid(string $skinKey, array $definition): bool
{
    if ($skinKey === '' || $definition === []) {
        return false;
    }

    $skinDir = (string) ($definition['skin_dir'] ?? '');
    $views = isset($definition['views']) && is_array($definition['views']) ? $definition['views'] : [];
    foreach (sr_community_required_skin_view_keys() as $viewKey) {
        $view = (string) ($views[$viewKey] ?? '');
        if (!sr_community_skin_file_is_inside($view, $skinDir)) {
            error_log('[saanraan] community skin required view is missing or outside skin dir: skin=' . $skinKey . ' view=' . $viewKey);
            return false;
        }
    }

    $actions = isset($definition['actions']) && is_array($definition['actions']) ? $definition['actions'] : [];
    foreach ($actions as $actionKey => $action) {
        if (!is_string($actionKey) || preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $actionKey) !== 1 || !is_array($action)) {
            error_log('[saanraan] community skin action key is invalid: skin=' . $skinKey);
            return false;
        }

        $method = strtoupper((string) ($action['method'] ?? 'POST'));
        $file = (string) ($action['file'] ?? '');
        if (!in_array($method, ['POST'], true) || !sr_community_skin_file_is_inside($file, $skinDir)) {
            error_log('[saanraan] community skin action is invalid: skin=' . $skinKey . ' action=' . $actionKey);
            return false;
        }
    }

    return true;
}

function sr_community_required_skin_view_keys(): array
{
    return ['list', 'post', 'form'];
}

function sr_community_skin_file_is_inside(string $file, string $skinDir): bool
{
    if ($file === '' || $skinDir === '') {
        return false;
    }

    $realFile = realpath($file);
    $realDir = realpath($skinDir);

    return is_string($realFile)
        && is_string($realDir)
        && is_file($realFile)
        && str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR);
}

function sr_community_skin_stylesheets(string $skinKey): array
{
    $options = sr_community_skin_options();
    $stylesheets = $options[$skinKey]['stylesheets'] ?? [];
    if (!is_array($stylesheets)) {
        return [];
    }

    $valid = [];
    foreach ($stylesheets as $stylesheet) {
        if (is_string($stylesheet) && sr_is_safe_relative_url($stylesheet)) {
            $valid[] = $stylesheet;
        }
    }

    return $valid;
}

function sr_community_skin_action(string $skinKey, string $actionKey, string $method): ?array
{
    $options = sr_community_skin_options();
    $actions = isset($options[$skinKey]['actions']) && is_array($options[$skinKey]['actions']) ? $options[$skinKey]['actions'] : [];
    $action = isset($actions[$actionKey]) && is_array($actions[$actionKey]) ? $actions[$actionKey] : null;
    if ($action === null) {
        return null;
    }

    $expectedMethod = strtoupper((string) ($action['method'] ?? 'POST'));
    if ($expectedMethod !== strtoupper($method)) {
        return null;
    }

    return $action;
}
