<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$settings = sr_community_settings($pdo);
$scrapPerPage = 20;
$scrapPageInput = sr_get_string('post_page', 20);
$seriesScrapPageInput = sr_get_string('series_page', 20);
$scrapPage = preg_match('/\A[1-9][0-9]*\z/', $scrapPageInput) === 1 ? (int) $scrapPageInput : 1;
$seriesScrapPage = preg_match('/\A[1-9][0-9]*\z/', $seriesScrapPageInput) === 1 ? (int) $seriesScrapPageInput : 1;
$scrapCount = sr_community_account_scrap_count($pdo, (int) $account['id']);
$seriesScrapCount = sr_community_account_series_scrap_count($pdo, (int) $account['id']);
$scrapTotalPages = max(1, (int) ceil($scrapCount / $scrapPerPage));
$seriesScrapTotalPages = max(1, (int) ceil($seriesScrapCount / $scrapPerPage));
$scrapPage = min(max(1, $scrapPage), $scrapTotalPages);
$seriesScrapPage = min(max(1, $seriesScrapPage), $seriesScrapTotalPages);
$scrapPagination = ['page' => $scrapPage, 'total_pages' => $scrapTotalPages];
$seriesScrapPagination = ['page' => $seriesScrapPage, 'total_pages' => $seriesScrapTotalPages];
$scrapPaginationBasePath = '/community/scraps' . ($seriesScrapPage > 1 ? '?series_page=' . (string) $seriesScrapPage : '');
$seriesScrapPaginationBasePath = '/community/scraps' . ($scrapPage > 1 ? '?post_page=' . (string) $scrapPage : '');
$scraps = sr_community_account_scraps($pdo, (int) $account['id'], $account, $scrapPerPage, ($scrapPage - 1) * $scrapPerPage);
$seriesScraps = sr_community_account_series_scraps($pdo, (int) $account['id'], $account, $scrapPerPage, ($seriesScrapPage - 1) * $scrapPerPage);
$notice = '';
if (isset($_SESSION['sr_community_scrap_notice']) && is_string($_SESSION['sr_community_scrap_notice'])) {
    $notice = $_SESSION['sr_community_scrap_notice'];
}
unset($_SESSION['sr_community_scrap_notice']);

include SR_ROOT . '/modules/community/views/scraps.php';
