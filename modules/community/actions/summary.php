<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_current_account($pdo);
$settings = sr_community_settings($pdo);
$config = isset($config) && is_array($config) ? $config : sr_runtime_config();
$memberSettings = sr_member_settings($pdo);
$summaryData = sr_community_home_chrome_data($pdo, is_array($account) ? $account : null, $settings, $site ?? null, $memberSettings);
$popularPosts = is_array($summaryData['popularPosts'] ?? null) ? $summaryData['popularPosts'] : [];
$popularPostReactionCounts = is_array($summaryData['popularPostReactionCounts'] ?? null) ? $summaryData['popularPostReactionCounts'] : [];
$latestComments = is_array($summaryData['latestComments'] ?? null) ? $summaryData['latestComments'] : [];
$recentSeries = is_array($summaryData['recentSeries'] ?? null) ? $summaryData['recentSeries'] : [];
$communitySeriesSupported = !empty($summaryData['communitySeriesSupported']);
$homeExcerptAllowedByBoardId = is_array($summaryData['homeExcerptAllowedByBoardId'] ?? null) ? $summaryData['homeExcerptAllowedByBoardId'] : [];

include SR_ROOT . '/modules/community/theme/basic/home-summary-aside.php';
sr_finish_response();
