<?php

$groupTitle = trim((string) ($boardGroup['title'] ?? ''));
$groupKey = (string) ($boardGroup['group_key'] ?? '');
$pageTitle = $groupTitle !== '' ? $groupTitle : $groupKey;
$groupDescription = (string) ($boardGroup['description'] ?? '');
$seo = [
    'title' => $pageTitle,
    'description' => sr_community_seo_text($groupDescription, 200),
    'canonical' => sr_community_board_group_path($groupKey),
    'robots' => 'index, follow',
    'og' => [
        'title' => $pageTitle,
        'description' => sr_community_seo_text($groupDescription, 200),
        'type' => 'website',
    ],
];
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$communityLayoutContext = sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.group',
    'output_slots' => [
        ['module_key' => 'community', 'point_key' => 'community.sidebar.summary', 'slot_key' => 'after_latest_comments'],
    ],
]);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $communityLayoutContext);
$communityMainLabel = $pageTitle;
$communityFrameModifier = 'group';
?>
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-start.php'; ?>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <?php if ($groupDescription !== '') { ?>
            <p><?php echo nl2br(sr_e($groupDescription)); ?></p>
        <?php } ?>

        <?php if ($groupBoards === []) { ?>
            <p>현재 볼 수 있는 게시판이 없습니다.</p>
        <?php } else { ?>
            <div class="community-board-grid">
                <?php foreach ($groupBoards as $board) { ?>
                    <?php $boardKey = (string) ($board['board_key'] ?? ''); ?>
                    <?php if (!sr_community_board_key_is_valid($boardKey)) { ?>
                        <?php continue; ?>
                    <?php } ?>
                    <article class="card community-board-card">
                        <div class="card-body community-board-card-body">
                            <h2 class="community-board-card-title">
                                <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode($boardKey))); ?>">
                                    <?php echo sr_e((string) ($board['title'] ?? $boardKey)); ?>
                                </a>
                            </h2>
                            <?php if ((string) ($board['description'] ?? '') !== '') { ?>
                                <div class="community-board-card-description"><?php echo sr_community_board_description_html((string) $board['description']); ?></div>
                            <?php } ?>
                        </div>
                    </article>
                <?php } ?>
            </div>
        <?php } ?>
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-end.php'; ?>
<?php sr_public_layout_end(); ?>
