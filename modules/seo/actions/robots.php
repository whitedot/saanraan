<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/seo/helpers.php';

header('Content-Type: text/plain; charset=UTF-8');
echo sr_seo_robots_txt($site, sr_seo_settings($pdo));
