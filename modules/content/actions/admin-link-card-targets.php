<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'view');

$target = sr_get_string('target', 40);
$keyword = sr_get_string('q', 120);
$limitInput = sr_get_string('limit', 10);
$limit = preg_match('/\A[1-9][0-9]*\z/', $limitInput) === 1 ? (int) $limitInput : 10;
$items = [];

if ($target === 'community_post' && sr_module_enabled($pdo, 'community') && is_file(SR_ROOT . '/modules/community/helpers.php')) {
    require_once SR_ROOT . '/modules/community/helpers.php';
    $items = sr_community_link_card_search_post_targets($pdo, $keyword, $limit);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'items' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
sr_finish_response();
