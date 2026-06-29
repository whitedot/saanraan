<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';

$contentRawSettings = array_merge(sr_content_default_settings(), sr_module_settings($pdo, 'content'));
$contentLayoutSettings = sr_content_settings($pdo);
$contentLayoutKey = sr_public_layout_normalize_key((string) ($contentRawSettings['layout_key'] ?? 'content.basic'));
$contentLayoutOptions = sr_content_layout_options($pdo, true);
$contentLayoutSettings['layout_key'] = isset($contentLayoutOptions[$contentLayoutKey])
    ? $contentLayoutKey
    : sr_content_default_layout_key($pdo, $site ?? null);

$contentUiKitView = sr_content_theme_view_file($contentLayoutSettings, 'ui-kit.php') ?? SR_ROOT . '/modules/content/theme/basic/ui-kit.php';
include $contentUiKitView;
