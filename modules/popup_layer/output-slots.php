<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/popup_layer/helpers.php';

return static function (PDO $pdo, array $context): string {
    return toy_popup_layer_render($pdo, $context);
};
