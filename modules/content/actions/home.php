<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';

$contentHomeContents = sr_content_recent_published_contents($pdo, 20);
$contentHomeGroups = sr_content_enabled_groups($pdo);
$contentHomeComments = sr_content_recent_comments($pdo, 8);
$contentLayoutSettings = sr_content_settings($pdo);
$contentHomeLayoutKey = sr_content_default_layout_key($pdo, $site ?? null);

$contentThemeFallbackViewFile = SR_ROOT . '/modules/content/views/home.php';
include sr_content_public_view_file($pdo, $contentLayoutSettings, 'home.php');
