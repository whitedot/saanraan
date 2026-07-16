<?php

require_once SR_ROOT . '/modules/banner/helpers.php';

return [
    'assets_function' => static function (PDO $pdo, array $context): array {
        return sr_banner_render_slot($pdo, $context) === ''
            ? []
            : ['stylesheets' => ['/modules/banner/assets/module.css']];
    },
    'renderer' => static function (PDO $pdo, array $context): string {
        return sr_banner_render_slot($pdo, $context);
    },
];
