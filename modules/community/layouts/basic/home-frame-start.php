<?php
$communityLayoutSettings = isset($communityLayoutSettings) && is_array($communityLayoutSettings) ? $communityLayoutSettings : (isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo));
$communityFrameAccount = isset($account) && is_array($account) ? $account : null;
$communityFrameChromeReady = isset($homeSidebarMenuHtml, $homeMemberSummary, $popularPosts, $popularPostReactionCounts, $latestComments, $recentSeries, $communitySeriesSupported, $homeExcerptAllowedByBoardId);
if (!$communityFrameChromeReady) {
    $communityFrameChromeData = sr_community_home_chrome_data($pdo, $communityFrameAccount, $communityLayoutSettings, $site ?? null);
    $homeSidebarMenuHtml = (string) ($communityFrameChromeData['homeSidebarMenuHtml'] ?? '');
    $homeMemberSummary = is_array($communityFrameChromeData['homeMemberSummary'] ?? null) ? $communityFrameChromeData['homeMemberSummary'] : null;
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
$homeMemberSummary = isset($homeMemberSummary) && is_array($homeMemberSummary) ? $homeMemberSummary : null;
$popularPostReactionCounts = isset($popularPostReactionCounts) && is_array($popularPostReactionCounts) ? $popularPostReactionCounts : [];
$config = isset($config) && is_array($config) ? $config : sr_runtime_config();
$memberSettings = isset($memberSettings) && is_array($memberSettings) ? $memberSettings : sr_member_settings($pdo);
$communityMainLabel = isset($communityMainLabel) && is_string($communityMainLabel) && $communityMainLabel !== '' ? $communityMainLabel : '커뮤니티 본문';
?>
<main class="community-screen">
    <div class="community-home-layout">
        <?php include SR_ROOT . '/modules/community/layouts/basic/home-sidebar.php'; ?>
        <section class="community-home-main" aria-label="<?php echo sr_e($communityMainLabel); ?>">
