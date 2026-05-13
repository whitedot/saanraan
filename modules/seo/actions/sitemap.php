<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/seo/helpers.php';

header('Content-Type: application/xml; charset=UTF-8');
echo sr_seo_sitemap_xml(sr_seo_sitemap_entries($pdo, $site));
