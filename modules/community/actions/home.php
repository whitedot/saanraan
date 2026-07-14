<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_current_account($pdo);
$settings = sr_community_settings($pdo);
$config = isset($config) && is_array($config) ? $config : sr_runtime_config();
$memberSettings = sr_member_settings($pdo);
$homeData = sr_community_home_chrome_data($pdo, is_array($account) ? $account : null, $settings, $site ?? null, $memberSettings);
$boards = is_array($homeData['boards'] ?? null) ? $homeData['boards'] : [];
$summaryFeedBoards = is_array($homeData['summaryFeedBoards'] ?? null) ? $homeData['summaryFeedBoards'] : [];
$latestPosts = is_array($homeData['latestPosts'] ?? null) ? $homeData['latestPosts'] : [];
$latestPostSections = sr_community_home_latest_post_sections(
    $pdo,
    $summaryFeedBoards,
    $settings,
    is_array($homeData['homeExcerptAllowedByBoardId'] ?? null) ? $homeData['homeExcerptAllowedByBoardId'] : [],
    5
);
$popularPosts = is_array($homeData['popularPosts'] ?? null) ? $homeData['popularPosts'] : [];
$popularPostReactionCounts = is_array($homeData['popularPostReactionCounts'] ?? null) ? $homeData['popularPostReactionCounts'] : [];
$latestComments = is_array($homeData['latestComments'] ?? null) ? $homeData['latestComments'] : [];
$recentSeries = is_array($homeData['recentSeries'] ?? null) ? $homeData['recentSeries'] : [];
$communitySeriesSupported = !empty($homeData['communitySeriesSupported']);
$homeExcerptAllowedByBoardId = is_array($homeData['homeExcerptAllowedByBoardId'] ?? null) ? $homeData['homeExcerptAllowedByBoardId'] : [];
$communityLayoutKey = (string) ($homeData['communityLayoutKey'] ?? sr_community_layout_key($settings, $site ?? null, $pdo));
$layoutHomeView = sr_community_layout_home_view($communityLayoutKey, $pdo);

$communityThemeFallbackViewFile = $layoutHomeView;
include sr_community_public_view_file($pdo, $settings, 'home.php', $layoutHomeView);
