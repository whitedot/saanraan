<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';

$contentHomeGroups = sr_content_enabled_groups($pdo);
$contentHomeSections = sr_content_home_sections($pdo, $contentHomeGroups, 6);
$contentLayoutSettings = sr_content_settings($pdo);
$contentHomeLayoutKey = sr_content_default_layout_key($pdo, $site ?? null);

$contentThemeFallbackViewFile = SR_ROOT . '/modules/content/views/home.php';
include sr_content_public_view_file($pdo, $contentLayoutSettings, 'home.php');
