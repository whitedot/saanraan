<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/community/helpers.php';

$communityRawSettings = sr_module_settings($pdo, 'community');
$communityLayoutSettings = sr_community_normalize_settings($communityRawSettings, $site ?? null, $pdo);
$communityLayoutKey = sr_public_layout_normalize_key((string) ($communityRawSettings['layout_key'] ?? ''));
if ($communityLayoutKey === '') {
    $communityLayoutKey = sr_community_layout_default_key();
}
$communityLayoutOptions = sr_community_layout_options($pdo, true);
if (isset($communityLayoutOptions[$communityLayoutKey])) {
    $communityLayoutSettings['layout_key'] = $communityLayoutKey;
}

$communityUiKitView = sr_community_theme_view_file($communityLayoutSettings, 'ui-kit.php') ?? SR_ROOT . '/modules/community/theme/basic/ui-kit.php';
include $communityUiKitView;
