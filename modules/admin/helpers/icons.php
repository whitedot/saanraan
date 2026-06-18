<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_admin_icon_symbols(): array
{
    return [
        'settings' => [
            'module_menu' => true,
            'paths' => [
                'M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065',
                'M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0',
            ],
        ],
        'admin-mode' => [
            'module_menu' => true,
            'paths' => [
                'M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3',
                'M11 11a1 1 0 1 0 2 0a1 1 0 1 0 -2 0',
                'M12 12l0 2.5',
            ],
        ],
        'users' => [
            'module_menu' => true,
            'paths' => [
                'M5 7a4 4 0 1 0 8 0a4 4 0 1 0 -8 0',
                'M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2',
                'M16 3.13a4 4 0 0 1 0 7.75',
                'M21 21v-2a4 4 0 0 0 -3 -3.85',
            ],
        ],
        'user' => [
            'module_menu' => true,
            'paths' => [
                'M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0',
                'M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2',
            ],
        ],
        'content' => [
            'module_menu' => true,
            'paths' => [
                'M5 4h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1',
                'M5 16h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1',
                'M15 12h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1',
                'M15 4h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1',
            ],
        ],
        'stats' => [
            'module_menu' => true,
            'paths' => [
                'M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -6',
                'M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -10',
                'M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -14',
                'M4 20h14',
            ],
        ],
        'home' => [
            'module_menu' => true,
            'paths' => [
                'M5 12l-2 0l9 -9l9 9l-2 0',
                'M5 12v7a2 2 0 0 0 2 2h3m4 0h3a2 2 0 0 0 2 -2v-7',
                'M10 12h4v9h-4z',
            ],
        ],
        'folder' => [
            'module_menu' => true,
            'paths' => [
                'M5 4h4l3 3h7a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-11a2 2 0 0 1 2 -2',
            ],
        ],
        'image' => [
            'module_menu' => true,
            'paths' => [
                'M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2',
                'M8 11a2 2 0 1 0 0 -4a2 2 0 0 0 0 4',
                'M21 15l-5 -5l-11 9',
            ],
        ],
        'layers' => [
            'module_menu' => true,
            'paths' => [
                'M12 3l9 5l-9 5l-9 -5l9 -5',
                'M3 13l9 5l9 -5',
                'M3 18l9 5l9 -5',
            ],
        ],
        'search' => [
            'module_menu' => true,
            'paths' => [
                'M10 18a8 8 0 1 0 0 -16a8 8 0 0 0 0 16',
                'M21 21l-5.35 -5.35',
            ],
        ],
        'menu-list' => [
            'module_menu' => true,
            'paths' => [
                'M9 6h11',
                'M9 12h11',
                'M9 18h11',
                'M4 6h.01',
                'M4 12h.01',
                'M4 18h.01',
            ],
        ],
        'bell' => [
            'module_menu' => true,
            'paths' => [
                'M10 5a2 2 0 0 1 4 0a7 7 0 0 1 4 6v3l2 3h-16l2 -3v-3a7 7 0 0 1 4 -6',
                'M9 17v1a3 3 0 0 0 6 0v-1',
            ],
        ],
        'shield' => [
            'module_menu' => true,
            'paths' => [
                'M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3',
                'M9 12l2 2l4 -4',
            ],
        ],
        'coins' => [
            'module_menu' => true,
            'paths' => [
                'M9 8c0 2.2 3.13 4 7 4s7 -1.8 7 -4s-3.13 -4 -7 -4s-7 1.8 -7 4',
                'M9 8v4c0 2.2 3.13 4 7 4s7 -1.8 7 -4v-4',
                'M3 12c0 2.2 3.13 4 7 4',
                'M3 12v4c0 2.2 3.13 4 7 4c2.1 0 3.98 -.53 5.26 -1.36',
            ],
        ],
        'point' => [
            'module_menu' => true,
            'paths' => [
                'M12 3l2.47 5.01l5.53 .8l-4 3.9l.94 5.51l-4.94 -2.6l-4.94 2.6l.94 -5.51l-4 -3.9l5.53 -.8l2.47 -5.01',
            ],
        ],
        'database' => [
            'module_menu' => true,
            'paths' => [
                'M4 6c0 2.2 3.58 4 8 4s8 -1.8 8 -4s-3.58 -4 -8 -4s-8 1.8 -8 4',
                'M4 6v6c0 2.2 3.58 4 8 4s8 -1.8 8 -4v-6',
                'M4 12v6c0 2.2 3.58 4 8 4s8 -1.8 8 -4v-6',
            ],
        ],
        'savings' => [
            'module_menu' => true,
            'paths' => [
                'M5 11a7 7 0 0 1 7 -7h3a5 5 0 0 1 5 5v6a4 4 0 0 1 -4 4h-2l-2 3l-2 -3h-2a5 5 0 0 1 -5 -5v-3',
                'M9 9h4',
                'M11 7v4',
                'M4 14h4',
            ],
        ],
        'payments' => [
            'module_menu' => true,
            'paths' => [
                'M4 7h16a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-16a2 2 0 0 1 -2 -2v-8a2 2 0 0 1 2 -2',
                'M6 11h4',
                'M15 15h3',
                'M18 11h.01',
            ],
        ],
        'wallet' => [
            'module_menu' => true,
            'paths' => [
                'M4 7h14a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2',
                'M16 12h4v4h-4a2 2 0 0 1 0 -4',
                'M6 7a3 3 0 0 1 3 -3h8',
            ],
        ],
        'gift' => [
            'module_menu' => true,
            'paths' => [
                'M3 8h18v4h-18z',
                'M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7',
                'M12 8v13',
                'M12 8h-3.5a2.5 2.5 0 1 1 2.5 -2.5v2.5',
                'M12 8h3.5a2.5 2.5 0 1 0 -2.5 -2.5v2.5',
            ],
        ],
        'ticket' => [
            'module_menu' => true,
            'paths' => [
                'M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-3a2 2 0 0 0 0 -4v-3a2 2 0 0 1 2 -2',
                'M13 5v2',
                'M13 11v2',
                'M13 17v2',
            ],
        ],
        'message-circle' => [
            'module_menu' => true,
            'paths' => [
                'M3 20l1.3 -3.9a8.5 8.5 0 1 1 3.6 3.6l-4.9 1.3',
                'M8 12h.01',
                'M12 12h.01',
                'M16 12h.01',
            ],
        ],
        'service' => [
            'module_menu' => true,
            'paths' => [
                'M4 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z',
                'M14 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z',
                'M4 15a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z',
                'M14 15a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z',
            ],
        ],
        'quiz' => [
            'module_menu' => true,
            'paths' => [
                'M8 8a4 4 0 1 1 5.8 3.57c-1.1 .56 -1.8 1.43 -1.8 2.43',
                'M12 18h.01',
                'M4 6a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z',
            ],
        ],
        'poll' => [
            'module_menu' => true,
            'paths' => [
                'M4 19h16',
                'M7 16v-6',
                'M12 16v-10',
                'M17 16v-3',
                'M5 5h14',
            ],
        ],
        'emoji_emotions' => [
            'module_menu' => true,
            'paths' => [
                'M4 12a8 8 0 1 0 16 0a8 8 0 0 0 -16 0',
                'M9 10h.01',
                'M15 10h.01',
                'M9 15c1.1 1 2 1.5 3 1.5s1.9 -.5 3 -1.5',
            ],
        ],
        'integration_instructions' => [
            'module_menu' => true,
            'paths' => [
                'M8 9l-4 3l4 3',
                'M16 9l4 3l-4 3',
                'M13 5l-2 14',
                'M4 5h16',
                'M4 19h16',
            ],
        ],
        'sidebar-toggle' => [
            'module_menu' => false,
            'paths' => [
                'M4 6a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2l0 -12',
                'M9 4v16',
                'M15 10l-2 2l2 2',
            ],
        ],
        'menu' => [
            'module_menu' => false,
            'paths' => [
                'M4 6l16 0',
                'M4 12l16 0',
                'M4 18l16 0',
            ],
        ],
        'moon-stars' => [
            'module_menu' => false,
            'paths' => [
                'M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1 -8.313 -12.454l0 .008',
                'M17 4a2 2 0 0 0 2 2a2 2 0 0 0 -2 2a2 2 0 0 0 -2 -2a2 2 0 0 0 2 -2',
                'M19 11h2m-1 -1v2',
            ],
        ],
        'sun' => [
            'module_menu' => false,
            'paths' => [
                'M8 12a4 4 0 1 0 8 0a4 4 0 1 0 -8 0',
                'M3 12h1m8 -9v1m8 8h1m-9 8v1m-6.4 -15.4l.7 .7m12.1 -.7l-.7 .7m0 11.4l.7 .7m-12.1 -.7l-.7 .7',
            ],
        ],
        'chevron-down' => [
            'module_menu' => false,
            'paths' => [
                'M6 9l6 6l6 -6',
            ],
        ],
    ];
}

function sr_admin_icon_symbol_exists(string $name): bool
{
    $symbols = sr_admin_icon_symbols();

    return isset($symbols[$name]);
}

function sr_admin_menu_symbol_allowed(string $name): bool
{
    $symbols = sr_admin_icon_symbols();

    return !empty($symbols[$name]['module_menu']);
}

function sr_admin_allowed_menu_symbol_icons(): array
{
    $allowed = [];
    foreach (sr_admin_icon_symbols() as $name => $symbol) {
        if (!empty($symbol['module_menu'])) {
            $allowed[$name] = true;
        }
    }

    return $allowed;
}

function sr_admin_menu_custom_icon_keys(PDO $pdo): array
{
    $allowed = [];
    foreach (sr_admin_icon_custom_map($pdo) as $name => $custom) {
        $name = trim((string) $name);
        if (!sr_admin_custom_icon_key_is_valid($name) || !is_array($custom)) {
            continue;
        }

        $type = (string) ($custom['type'] ?? 'material');
        if (in_array($type, ['material', 'image'], true)) {
            $allowed[$name] = true;
        }
    }

    return $allowed;
}

function sr_admin_menu_icon_allowed(PDO $pdo, string $name): bool
{
    $name = trim($name);
    if (sr_admin_menu_symbol_allowed($name) || sr_admin_material_icon_key_allowed($name)) {
        return true;
    }

    $customKeys = sr_admin_menu_custom_icon_keys($pdo);

    return !empty($customKeys[$name]);
}

function sr_admin_allowed_menu_icon_options(PDO $pdo): array
{
    $allowed = sr_admin_allowed_menu_symbol_icons();
    foreach (sr_admin_material_icon_names() as $name => $_materialName) {
        $allowed[(string) $name] = true;
    }

    $customKeys = sr_admin_menu_custom_icon_keys($pdo);
    ksort($customKeys, SORT_STRING);
    foreach ($customKeys as $name => $_enabled) {
        $allowed[$name] = true;
    }

    return $allowed;
}

function sr_admin_menu_icon(PDO $pdo, string $name): array
{
    $name = trim($name);

    return sr_admin_menu_icon_allowed($pdo, $name) ? ['type' => 'symbol', 'name' => $name] : [];
}

function sr_admin_menu_symbol_icon(string $name): array
{
    $name = trim($name);

    return sr_admin_menu_symbol_allowed($name) ? ['type' => 'symbol', 'name' => $name] : [];
}

function sr_admin_material_icon_names(): array
{
    static $icons = null;
    if (is_array($icons)) {
        return $icons;
    }

    $icons = sr_admin_builtin_material_icon_names();
    foreach (sr_admin_common_material_icon_names() as $name => $_enabled) {
        if (!isset($icons[$name])) {
            $icons[$name] = $name;
        }
    }
    ksort($icons, SORT_STRING);

    return $icons;
}

function sr_admin_builtin_material_icon_names(): array
{
    return [
        'settings' => 'settings',
        'admin-mode' => 'admin_panel_settings',
        'users' => 'group',
        'user' => 'person',
        'content' => 'dashboard_customize',
        'stats' => 'monitoring',
        'home' => 'home',
        'folder' => 'folder',
        'image' => 'image',
        'layers' => 'layers',
        'search' => 'search',
        'menu-list' => 'format_list_bulleted',
        'bell' => 'notifications',
        'shield' => 'shield',
        'point' => 'paid',
        'database' => 'database',
        'savings' => 'savings',
        'payments' => 'payments',
        'coins' => 'payments',
        'wallet' => 'account_balance_wallet',
        'gift' => 'redeem',
        'ticket' => 'confirmation_number',
        'message-circle' => 'forum',
        'service' => 'apps',
        'quiz' => 'quiz',
        'poll' => 'poll',
        'emoji_emotions' => 'emoji_emotions',
        'integration_instructions' => 'integration_instructions',
        'sidebar-toggle' => 'keyboard_double_arrow_left',
        'menu' => 'menu',
        'moon-stars' => 'dark_mode',
        'sun' => 'light_mode',
        'chevron-down' => 'keyboard_arrow_down',
    ];
}

function sr_admin_common_material_icon_names(): array
{
    $names = [
        'add',
        'add_circle',
        'arrow_back',
        'arrow_downward',
        'arrow_forward',
        'arrow_upward',
        'attach_file',
        'build',
        'cached',
        'calendar_month',
        'cancel',
        'category',
        'check',
        'check_circle',
        'chevron_left',
        'chevron_right',
        'close',
        'cloud_upload',
        'code',
        'content_copy',
        'dark_mode',
        'dashboard',
        'dashboard_customize',
        'database',
        'delete',
        'download',
        'drag_indicator',
        'edit',
        'error',
        'expand_less',
        'expand_more',
        'filter_alt',
        'format_list_bulleted',
        'help',
        'history',
        'info',
        'keyboard_arrow_down',
        'keyboard_arrow_left',
        'keyboard_arrow_right',
        'keyboard_arrow_up',
        'language',
        'light_mode',
        'link',
        'lock',
        'login',
        'logout',
        'mail',
        'manage_accounts',
        'menu_open',
        'monitoring',
        'more_horiz',
        'more_vert',
        'notifications',
        'open_in_new',
        'paid',
        'payments',
        'person',
        'play_arrow',
        'preview',
        'print',
        'public',
        'refresh',
        'restart_alt',
        'rule',
        'save',
        'savings',
        'search',
        'security',
        'send',
        'share',
        'star',
        'sync',
        'table_view',
        'tune',
        'undo',
        'upload',
        'visibility',
        'visibility_off',
        'warning',
        'work',
    ];

    $map = [];
    foreach ($names as $name) {
        $map[$name] = true;
    }

    return $map;
}

function sr_admin_material_icon_key_allowed(string $name): bool
{
    $name = trim($name);

    return isset(sr_admin_material_icon_names()[$name]);
}

function sr_admin_material_icon_name(string $symbolName): string
{
    $symbolName = trim($symbolName);
    $builtinIcons = sr_admin_builtin_material_icon_names();
    if (isset($builtinIcons[$symbolName])) {
        return (string) $builtinIcons[$symbolName];
    }

    $materialName = sr_material_icon_name($symbolName);
    if ($materialName === $symbolName && sr_admin_material_icon_key_allowed($materialName)) {
        return $materialName;
    }

    return (string) $builtinIcons['folder'];
}

function sr_admin_icon_custom_map(PDO $pdo): array
{
    $settings = sr_admin_settings($pdo);
    $customMap = $settings['icon_key_overrides'] ?? [];

    return is_array($customMap) ? $customMap : [];
}

function sr_admin_icon_material_name(PDO $pdo, string $symbolName): string
{
    $symbolName = trim($symbolName);
    $custom = sr_admin_icon_custom_map($pdo)[$symbolName] ?? null;
    if (is_array($custom) && (string) ($custom['type'] ?? 'material') === 'material') {
        $materialName = sr_material_icon_name((string) ($custom['material_name'] ?? ''));
        if ($materialName !== 'help' || trim((string) ($custom['material_name'] ?? '')) === 'help') {
            return $materialName;
        }
    }

    return sr_admin_material_icon_name($symbolName);
}

function sr_admin_icon_render_icon(PDO $pdo, string $symbolName): array
{
    $symbolName = trim($symbolName);
    $custom = sr_admin_icon_custom_map($pdo)[$symbolName] ?? null;
    if (is_array($custom) && (string) ($custom['type'] ?? '') === 'image') {
        $storageReference = (string) ($custom['storage_reference'] ?? '');
        if (sr_admin_icon_image_storage_reference($storageReference) !== null) {
            return [
                'type' => 'asset',
                'url' => sr_url('/admin/icon-image?file=' . rawurlencode($storageReference)),
                'alt' => '',
                'symbol_name' => $symbolName,
            ];
        }
    }

    return [
        'type' => 'material',
        'name' => sr_admin_icon_material_name($pdo, $symbolName),
        'symbol_name' => $symbolName,
    ];
}

function sr_admin_icon_upload_max_bytes(): int
{
    return 1048576;
}

function sr_admin_icon_format_bytes(int $bytes): string
{
    return sr_format_bytes($bytes);
}

function sr_admin_icon_upload_was_provided(mixed $file): bool
{
    return sr_upload_was_provided($file);
}

function sr_admin_custom_icon_key_is_valid(string $key): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) === 1;
}

function sr_admin_icon_upload_array_file(array $files, int $index): ?array
{
    if (!isset($files['error'][$index])) {
        return null;
    }

    return [
        'name' => (string) ($files['name'][$index] ?? ''),
        'type' => (string) ($files['type'][$index] ?? ''),
        'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
        'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
        'size' => (int) ($files['size'][$index] ?? 0),
    ];
}

function sr_admin_icon_image_mime_is_allowed(string $mimeType): bool
{
    return sr_image_mime_is_allowed($mimeType, false, true);
}

function sr_admin_icon_image_format_for_mime(string $mimeType): string
{
    return sr_image_format_for_mime($mimeType, false, true);
}

function sr_admin_icon_upload_image(array $file): array
{
    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_admin_icon_upload_max_bytes(),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    ]);

    $sourcePath = (string) $validated['tmp_name'];
    $targetFormat = sr_admin_icon_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException('허용되지 않은 아이콘 이미지 형식입니다.');
    }

    $dimensions = @getimagesize($sourcePath);
    if (!is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1) {
        throw new RuntimeException('아이콘 이미지 크기를 확인할 수 없습니다.');
    }
    if ((int) $dimensions[0] > 512 || (int) $dimensions[1] > 512) {
        throw new RuntimeException('아이콘 이미지는 가로/세로 512px 이하만 업로드할 수 있습니다.');
    }

    $datePath = date('Y/m');
    $storageKey = 'admin/icons/' . $datePath . '/' . sr_upload_random_filename($targetFormat);
    $stored = sr_storage_put_file($sourcePath, $storageKey, [
        'content_type' => (string) $validated['mime_type'],
    ]);

    return [
        'driver' => (string) $stored['driver'],
        'storage_key' => $storageKey,
        'storage_reference' => sr_storage_reference((string) $stored['driver'], $storageKey),
        'mime_type' => (string) $validated['mime_type'],
    ];
}

function sr_admin_icon_image_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Aadmin/icons/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|gif|webp)\z#', $key) === 1;
}

function sr_admin_icon_image_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_admin_icon_image_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
}

function sr_admin_icon_image_references(array $iconOverrides): array
{
    $references = [];
    foreach ($iconOverrides as $override) {
        if (!is_array($override) || (string) ($override['type'] ?? '') !== 'image') {
            continue;
        }

        $reference = (string) ($override['storage_reference'] ?? '');
        if (sr_admin_icon_image_storage_reference($reference) !== null) {
            $references[$reference] = true;
        }
    }

    return $references;
}

function sr_admin_delete_icon_image_references(array $references): array
{
    $failed = [];
    foreach ($references as $reference => $_enabled) {
        if (is_int($reference)) {
            $reference = (string) $_enabled;
        }

        $storage = sr_admin_icon_image_storage_reference((string) $reference);
        if (!is_array($storage)) {
            continue;
        }

        if (!sr_storage_delete((string) $storage['driver'], (string) $storage['key'])) {
            $failed[(string) $reference] = true;
            error_log('[saanraan] admin icon image delete failed: ' . sr_log_sensitive_text_sanitize(sr_log_line_value((string) $reference, 220)));
        }
    }

    return $failed;
}

function sr_admin_default_menu_icon_id(string $category): string
{
    $icons = [
        'system' => 'settings',
        'member' => 'users',
        'site' => 'content',
        'system_asset' => 'content',
        'content' => 'content',
        'community' => 'message-circle',
        'service' => 'service',
        'operation' => 'stats',
        'other' => 'folder',
    ];

    return (string) ($icons[$category] ?? 'folder');
}

function sr_admin_default_menu_icon(string $category): array
{
    return ['type' => 'symbol', 'name' => sr_admin_default_menu_icon_id($category)];
}
