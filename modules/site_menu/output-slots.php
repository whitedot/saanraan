<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/site_menu/helpers.php';

return static function (PDO $pdo, array $context): string {
    if (
        (string) ($context['module_key'] ?? '') !== 'core'
    ) {
        return '';
    }

    $pointKey = (string) ($context['point_key'] ?? '');
    $slotKey = (string) ($context['slot_key'] ?? '');
    $allowedSlotKeys = ['navigation', 'primary_navigation', 'secondary_navigation', 'tertiary_navigation', 'quaternary_navigation', 'quinary_navigation'];
    if (
        !str_starts_with($pointKey, 'site.')
        || !in_array($slotKey, $allowedSlotKeys, true)
    ) {
        return '';
    }

    if (array_key_exists('menu_key', $context)) {
        $menuKey = sr_site_menu_clean_key((string) $context['menu_key']);
    } else {
        $menuKey = sr_site_menu_layout_slot_menu_key($slotKey);
    }
    if ($menuKey === '') {
        return '';
    }

    return sr_site_menu_render($pdo, $menuKey, $slotKey);
};
