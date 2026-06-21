<?php

$seriesTitle = (string) ($series['title'] ?? '');
$seriesPageTitle = $seriesTitle !== '' ? $seriesTitle : '시리즈';
$seriesCanonical = '/community/series?id=' . rawurlencode((string) (int) ($series['id'] ?? 0));
$seriesBoardKey = is_array($seriesBoard ?? null) ? (string) ($seriesBoard['board_key'] ?? '') : '';
$seriesBoardUrl = '/community/board?key=' . rawurlencode($seriesBoardKey);
$seo = [
    'title' => $seriesPageTitle,
    'canonical' => $seriesCanonical,
    'robots' => 'noindex, follow',
];
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings));
?>
    <main class="community-screen community-series-detail-screen">
        <nav class="community-series-detail-path" aria-label="<?php echo sr_e('시리즈 위치'); ?>">
            <?php if ($seriesBoardKey !== '') { ?>
                <a href="<?php echo sr_e(sr_url($seriesBoardUrl)); ?>"><?php echo sr_e((string) ($seriesBoard['title'] ?? '게시판')); ?></a>
            <?php } ?>
        </nav>

        <header class="community-series-detail-header">
            <h1><?php echo sr_e($seriesPageTitle); ?></h1>
            <p><?php echo sr_e(sr_community_series_visibility_label((string) ($series['visibility'] ?? ''))); ?></p>
            <?php if ((string) ($series['description'] ?? '') !== '') { ?>
                <p class="community-series-detail-description"><?php echo sr_e((string) $series['description']); ?></p>
            <?php } ?>
        </header>

        <section class="community-series-detail-section" aria-labelledby="community_series_detail_posts_title">
            <h2 id="community_series_detail_posts_title"><?php echo sr_e('시리즈 글'); ?></h2>
            <?php if ($seriesItems === []) { ?>
                <p class="community-series-detail-empty"><?php echo sr_e('열람할 수 있는 시리즈 글이 없습니다.'); ?></p>
            <?php } else { ?>
                <ol class="community-series-detail-list">
                    <?php foreach ($seriesItems as $seriesItem) { ?>
                        <?php
                        $seriesItemTitle = (string) ($seriesItem['post_title'] ?? '');
                        $seriesItemEpisode = (string) ($seriesItem['episode_label'] ?? '');
                        $seriesItemLabel = ($seriesItemEpisode !== '' ? $seriesItemEpisode . ' - ' : '') . $seriesItemTitle;
                        ?>
                        <li>
                            <a href="<?php echo sr_e(sr_url('/community/post?id=' . rawurlencode((string) (int) ($seriesItem['post_id'] ?? 0)))); ?>">
                                <?php echo sr_e($seriesItemLabel); ?>
                            </a>
                            <?php if ((string) ($seriesItem['board_title'] ?? '') !== '') { ?>
                                <span><?php echo sr_e((string) $seriesItem['board_title']); ?></span>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
            <?php } ?>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
