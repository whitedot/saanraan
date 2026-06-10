<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/seo/helpers.php';

header('Content-Type: application/xml; charset=UTF-8');
if (sr_site_member_only_enabled($site ?? null)) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"></urlset>\n";
    sr_finish_response();
}

echo sr_seo_sitemap_xml(sr_seo_sitemap_entries($pdo, $site));
