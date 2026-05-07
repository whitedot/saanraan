<?php

require_once TOY_ROOT . '/modules/banner/helpers.php';

return static function (PDO $pdo, array $context): string {
    return toy_banner_render_slot($pdo, $context);
};
