<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
$scraps = toy_community_account_scraps($pdo, (int) $account['id'], $account, 50);
$notice = '';
if (isset($_SESSION['toy_community_scrap_notice']) && is_string($_SESSION['toy_community_scrap_notice'])) {
    $notice = $_SESSION['toy_community_scrap_notice'];
}
unset($_SESSION['toy_community_scrap_notice']);

include TOY_ROOT . '/modules/community/views/scraps.php';
