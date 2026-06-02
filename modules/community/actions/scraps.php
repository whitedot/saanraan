<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$settings = sr_community_settings($pdo);
$scraps = sr_community_account_scraps($pdo, (int) $account['id'], $account, 50);
$seriesScraps = sr_community_account_series_scraps($pdo, (int) $account['id'], $account, 50);
$notice = '';
if (isset($_SESSION['sr_community_scrap_notice']) && is_string($_SESSION['sr_community_scrap_notice'])) {
    $notice = $_SESSION['sr_community_scrap_notice'];
}
unset($_SESSION['sr_community_scrap_notice']);

include SR_ROOT . '/modules/community/views/scraps.php';
