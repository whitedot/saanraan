<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/popup_layer/helpers.php';

return [
    'stylesheets' => ['/modules/popup_layer/assets/module.css'],
    'scripts' => ['/modules/popup_layer/assets/saanraan-popup-layer.js'],
    'renderer' => static function (PDO $pdo, array $context): string {
        return sr_popup_layer_render($pdo, $context, false);
    },
];
