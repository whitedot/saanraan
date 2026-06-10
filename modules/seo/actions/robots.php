<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/seo/helpers.php';

header('Content-Type: text/plain; charset=UTF-8');
if (sr_site_member_only_enabled($site ?? null)) {
    echo "User-agent: *\nDisallow: /\n";
    sr_finish_response();
}

echo sr_seo_robots_txt($site, sr_seo_settings($pdo));
