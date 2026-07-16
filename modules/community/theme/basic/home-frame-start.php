<?php
$communityLayoutSettings = isset($communityLayoutSettings) && is_array($communityLayoutSettings) ? $communityLayoutSettings : (isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo));
$communityFrameAccount = isset($account) && is_array($account) ? $account : null;
$communityFrameBoard = isset($board) && is_array($board)
    ? $board
    : (isset($postBoard) && is_array($postBoard) ? $postBoard : null);
$communityBoardSidebarMenu = is_array($communityFrameBoard)
    ? sr_community_board_sidebar_menu_context($pdo, $communityFrameBoard, $communityFrameAccount, $communityLayoutSettings)
    : ['type' => '', 'title' => '', 'html' => ''];
$communityFrameSummaryEnabled = !isset($communityFrameSummaryEnabled) || $communityFrameSummaryEnabled !== false;
$communityFrameSummaryDeferred = !empty($communityFrameSummaryDeferred);
$communityFrameChromeReady = !$communityFrameSummaryEnabled || $communityFrameSummaryDeferred || isset($popularPosts, $popularPostReactionCounts, $latestComments, $recentSeries, $communitySeriesSupported, $homeExcerptAllowedByBoardId);
if (!$communityFrameChromeReady) {
    $communityFrameChromeData = sr_community_home_chrome_data($pdo, $communityFrameAccount, $communityLayoutSettings, $site ?? null);
    $popularPosts = is_array($communityFrameChromeData['popularPosts'] ?? null) ? $communityFrameChromeData['popularPosts'] : [];
    $popularPostReactionCounts = is_array($communityFrameChromeData['popularPostReactionCounts'] ?? null) ? $communityFrameChromeData['popularPostReactionCounts'] : [];
    $latestComments = is_array($communityFrameChromeData['latestComments'] ?? null) ? $communityFrameChromeData['latestComments'] : [];
    $recentSeries = is_array($communityFrameChromeData['recentSeries'] ?? null) ? $communityFrameChromeData['recentSeries'] : [];
    $communitySeriesSupported = !empty($communityFrameChromeData['communitySeriesSupported']);
    $homeExcerptAllowedByBoardId = is_array($communityFrameChromeData['homeExcerptAllowedByBoardId'] ?? null) ? $communityFrameChromeData['homeExcerptAllowedByBoardId'] : [];
    if (!isset($latestPosts) || !is_array($latestPosts)) {
        $latestPosts = is_array($communityFrameChromeData['latestPosts'] ?? null) ? $communityFrameChromeData['latestPosts'] : [];
    }
}
$popularPosts = isset($popularPosts) && is_array($popularPosts) ? $popularPosts : [];
$latestComments = isset($latestComments) && is_array($latestComments) ? $latestComments : [];
$recentSeries = isset($recentSeries) && is_array($recentSeries) ? $recentSeries : [];
$communitySeriesSupported = !empty($communitySeriesSupported);
$popularPostReactionCounts = isset($popularPostReactionCounts) && is_array($popularPostReactionCounts) ? $popularPostReactionCounts : [];
$homeExcerptAllowedByBoardId = isset($homeExcerptAllowedByBoardId) && is_array($homeExcerptAllowedByBoardId) ? $homeExcerptAllowedByBoardId : [];
if (function_exists('sr_community_home_filter_rows_by_board_ids')) {
    $communityFrameHomeBoardIds = array_map('intval', array_keys($homeExcerptAllowedByBoardId));
    $latestPosts = isset($latestPosts) && is_array($latestPosts) ? sr_community_home_filter_rows_by_board_ids($latestPosts, $communityFrameHomeBoardIds) : [];
    $popularPosts = isset($popularPosts) && is_array($popularPosts) ? sr_community_home_filter_rows_by_board_ids($popularPosts, $communityFrameHomeBoardIds) : [];
    $latestComments = isset($latestComments) && is_array($latestComments) ? sr_community_home_filter_rows_by_board_ids($latestComments, $communityFrameHomeBoardIds) : [];
    $recentSeries = isset($recentSeries) && is_array($recentSeries) ? sr_community_home_filter_rows_by_board_ids($recentSeries, $communityFrameHomeBoardIds) : [];
}
$config = isset($config) && is_array($config) ? $config : sr_runtime_config();
$memberSettings = isset($memberSettings) && is_array($memberSettings) ? $memberSettings : sr_member_settings($pdo);
$communityMainLabel = isset($communityMainLabel) && is_string($communityMainLabel) && $communityMainLabel !== '' ? $communityMainLabel : '커뮤니티 본문';
$communityFrameModifier = isset($communityFrameModifier) && is_string($communityFrameModifier) && preg_match('/\A[a-z0-9_-]+\z/', $communityFrameModifier) === 1 ? $communityFrameModifier : 'home';
$communityMainClass = 'community-home-main community-frame-main community-frame-' . $communityFrameModifier;
$communityHomeLayoutClass = 'community-home-layout' . ($communityFrameSummaryEnabled ? '' : ' community-home-layout-main-only');
?>
<main class="community-screen">
    <div class="<?php echo sr_e($communityHomeLayoutClass); ?>">
        <section class="<?php echo sr_e($communityMainClass); ?>" aria-label="<?php echo sr_e($communityMainLabel); ?>">
