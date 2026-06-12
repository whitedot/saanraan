<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';
require_once SR_ROOT . '/modules/embed_manager/helpers.php';

$account = sr_member_require_login($pdo);

$target = sr_get_string('target', 40);
$targets = sr_get_string('targets', 160);
$keyword = sr_get_string('q', 120);
$limitInput = sr_get_string('limit', 10);
$limit = preg_match('/\A[1-9][0-9]*\z/', $limitInput) === 1 ? (int) $limitInput : 10;
$items = [];
$linkCardSite = isset($site) && is_array($site) ? $site : ['base_url' => sr_current_base_url()];
$linkCardAbsoluteUrl = static function (string $url) use ($linkCardSite): string {
    return sr_is_http_url($url) ? $url : sr_absolute_url($linkCardSite, $url);
};

$searchTargets = $targets !== '' ? $targets : ($target !== '' ? $target : 'content,quiz_set,survey_form');
$items = sr_embed_manager_search_targets($pdo, $keyword, $limit, [
    'context' => 'public',
    'targets' => $searchTargets,
    'owner_module' => 'community',
    'owner_type' => 'post',
    'viewer_account_id' => (int) ($account['id'] ?? 0),
]);
foreach ($items as $itemIndex => $item) {
    $items[$itemIndex]['url'] = $linkCardAbsoluteUrl((string) ($item['url'] ?? ''));
}

sr_json_response([
    'items' => $items,
]);
