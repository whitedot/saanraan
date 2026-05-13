<?php

require_once SR_ROOT . '/modules/banner/helpers.php';

return static function (PDO $pdo, array $context): string {
    return sr_banner_render_slot($pdo, $context);
};
