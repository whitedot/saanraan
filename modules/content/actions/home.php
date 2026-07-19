<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/member/public-identity.php';

$contentHomeGroups = sr_content_enabled_groups($pdo);
$contentHomeSections = sr_content_home_sections($pdo, $contentHomeGroups, 6);
$contentLayoutSettings = sr_content_settings($pdo);
$contentHomeLayoutKey = sr_content_default_layout_key($pdo, $site ?? null);
$contentHomeAccount = sr_member_current_account($pdo);
$contentHomeAuthorAccountIds = [];
foreach ($contentHomeSections as $contentHomeAuthorSection) {
    foreach ((array) ($contentHomeAuthorSection['contents'] ?? []) as $contentHomeAuthorItem) {
        $contentHomeAuthorAccountIds[] = (int) ($contentHomeAuthorItem['author_account_id'] ?? $contentHomeAuthorItem['created_by'] ?? 0);
    }
}
$contentHomePublicIdentityContext = sr_member_public_identity_context($pdo, $contentHomeAccount, $contentHomeAuthorAccountIds);
$contentHomePublicIdentityAssets = sr_member_public_identity_assets();

$contentThemeFallbackViewFile = SR_ROOT . '/modules/content/views/home.php';
include sr_content_public_view_file($pdo, $contentLayoutSettings, 'home.php');
