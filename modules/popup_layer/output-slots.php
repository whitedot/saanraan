<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/popup_layer/helpers.php';

return static function (PDO $pdo, array $context): string {
    return sr_popup_layer_render($pdo, $context);
};
