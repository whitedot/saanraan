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
    if (
        !in_array($pointKey, ['site.header', 'site.footer'], true)
        ||
        ($pointKey === 'site.header' && !in_array($slotKey, ['navigation', 'primary_navigation'], true))
        || ($pointKey === 'site.footer' && !in_array($slotKey, ['secondary_navigation', 'tertiary_navigation'], true))
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
