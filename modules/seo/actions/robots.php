<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/seo/helpers.php';

header('Content-Type: text/plain; charset=UTF-8');
echo toy_seo_robots_txt($site, toy_seo_settings($pdo));
