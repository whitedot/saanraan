<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/site_menu/helpers.php';

return static function (PDO $pdo, array $context): string {
    if (
        (string) ($context['module_key'] ?? '') !== 'core'
        || (string) ($context['point_key'] ?? '') !== 'site.header'
        || (string) ($context['slot_key'] ?? '') !== 'navigation'
    ) {
        return '';
    }

    return sr_site_menu_render($pdo, 'header');
};
