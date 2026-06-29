<?php

$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$boardGroup = isset($boardGroup) && is_array($boardGroup) ? $boardGroup : [];
$groupBoards = isset($groupBoards) && is_array($groupBoards) ? $groupBoards : [];
$groupTitle = trim((string) ($boardGroup['title'] ?? ''));
$groupKey = (string) ($boardGroup['group_key'] ?? '');
$pageTitle = $groupTitle !== '' ? $groupTitle : $groupKey;
$groupDescription = (string) ($boardGroup['description'] ?? '');
$seo = [
    'title' => $pageTitle,
    'description' => sr_community_seo_text($groupDescription, 200),
    'canonical' => sr_community_board_group_path($groupKey),
    'robots' => 'index, follow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.group',
]));
?>

<main class="example-community-theme example-community-group" data-example-theme-view="community.group">
    <header class="example-community-hero">
        <p class="example-content-kicker">BOARD GROUP FROM THEME</p>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <?php if ($groupDescription !== '') { ?>
            <p><?php echo nl2br(sr_e($groupDescription)); ?></p>
        <?php } ?>
    </header>

    <section class="example-community-board-mosaic" aria-label="게시판">
        <?php if ($groupBoards === []) { ?>
            <p class="example-community-panel">현재 볼 수 있는 게시판이 없습니다.</p>
        <?php } else { ?>
            <?php foreach ($groupBoards as $board) { ?>
                <?php $boardKey = (string) ($board['board_key'] ?? ''); ?>
                <?php if (!sr_community_board_key_is_valid($boardKey)) { ?>
                    <?php continue; ?>
                <?php } ?>
                <article class="example-community-board-tile">
                    <h2>
                        <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode($boardKey))); ?>">
                            <?php echo sr_e((string) ($board['title'] ?? $boardKey)); ?>
                        </a>
                    </h2>
                    <?php if ((string) ($board['description'] ?? '') !== '') { ?>
                        <p><?php echo sr_e((string) $board['description']); ?></p>
                    <?php } ?>
                </article>
            <?php } ?>
        <?php } ?>
    </section>
</main>

<?php sr_public_layout_end(); ?>
