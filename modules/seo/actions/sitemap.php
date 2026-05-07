<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/seo/helpers.php';

header('Content-Type: application/xml; charset=UTF-8');
echo toy_seo_sitemap_xml(toy_seo_sitemap_entries($pdo, $site));
