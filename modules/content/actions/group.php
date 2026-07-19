<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/member/public-identity.php';

$groupKey = sr_content_clean_slug(sr_get_string('key', 60));
$pageGroup = sr_content_enabled_group_by_key($pdo, $groupKey);
if (!is_array($pageGroup)) {
    sr_render_error(404, sr_t('content::action.error.content_group_not_found'));
}

$groupContents = sr_content_published_contents_for_group($pdo, (int) $pageGroup['id']);
$contentLayoutSettings = sr_content_settings($pdo);
$pageGroupLayoutKey = sr_content_default_layout_key($pdo, $site ?? null);
$pageTitle = (string) ($pageGroup['title'] ?? '콘텐츠 그룹');
$pageDescription = (string) ($pageGroup['description'] ?? '');
$contentGroupAccount = sr_member_current_account($pdo);
$contentGroupAuthorAccountIds = array_map(
    static fn (array $content): int => (int) ($content['author_account_id'] ?? $content['created_by'] ?? 0),
    $groupContents
);
$contentGroupPublicIdentityContext = sr_member_public_identity_context($pdo, $contentGroupAccount, $contentGroupAuthorAccountIds);
$contentGroupPublicIdentityAssets = sr_member_public_identity_assets();

$contentThemeFallbackViewFile = SR_ROOT . '/modules/content/views/group.php';
include sr_content_public_view_file($pdo, $contentLayoutSettings, 'group.php');
