<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_current_account($pdo);
$settings = sr_content_settings($pdo);
$keywordValue = sr_get_string_without_truncation('q', 100);
$keyword = is_string($keywordValue) ? trim(preg_replace('/\s+/u', ' ', $keywordValue) ?? '') : '';
$pageValue = sr_get_string('page', 20);
$page = preg_match('/\A[1-9][0-9]*\z/', $pageValue) === 1 ? (int) $pageValue : 1;
$page = min($page, 50);
$perPage = 20;
$keywordLength = function_exists('mb_strlen') ? mb_strlen($keyword) : strlen($keyword);
$keywordTooShort = $keyword !== '' && $keywordLength < 2;
$bodySearchContentIds = [];

$identityRestricted = !empty($settings['identity_content_view_required'])
    || !empty($settings['identity_content_view_adult_required']);
if (!$identityRestricted && $keyword !== '' && !$keywordTooShort) {
    foreach (sr_content_search_access_rows($pdo) as $searchAccessRow) {
        $contentId = (int) ($searchAccessRow['id'] ?? 0);
        if ($contentId < 1) {
            continue;
        }
        if (
            is_array($account)
            && (string) ($searchAccessRow['asset_charge_policy'] ?? '') === 'once'
            && sr_content_once_access_already_granted(
                $pdo,
                sr_content_asset_module_keys_from_value($searchAccessRow['asset_module'] ?? ''),
                (int) ($account['id'] ?? 0),
                $contentId
            )
        ) {
            $bodySearchContentIds[] = $contentId;
        }
    }
}

$items = [];
$hasNextPage = false;
if ($keyword !== '' && !$keywordTooShort) {
    $items = sr_content_search_items($pdo, $keyword, $bodySearchContentIds, $perPage + 1, ($page - 1) * $perPage, !$identityRestricted);
    $hasNextPage = count($items) > $perPage;
    if ($hasNextPage) {
        $items = array_slice($items, 0, $perPage);
    }
}
$bodySearchContentIdMap = array_fill_keys(array_map('strval', $bodySearchContentIds), true);
foreach ($items as $itemIndex => $item) {
    $items[$itemIndex]['search_body_excerpt_allowed'] = !$identityRestricted
        && (
            !sr_content_asset_access_required($item)
            || isset($bodySearchContentIdMap[(string) (int) ($item['id'] ?? 0)])
        );
}

$contentLayoutSettings = $settings;
$contentSearchLayoutKey = sr_content_default_layout_key($pdo, $site ?? null);
$contentThemeFallbackViewFile = SR_ROOT . '/modules/content/views/search.php';
include sr_content_public_view_file($pdo, $contentLayoutSettings, 'search.php');
