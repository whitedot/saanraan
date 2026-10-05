<?php

$seo = sr_community_home_seo_meta();
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$communityLayoutContext = sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.home',
    'layout_key' => (string) ($communityLayoutKey ?? ''),
    'output_slots' => [
        ['module_key' => 'community', 'point_key' => 'community.sidebar.summary', 'slot_key' => 'after_latest_comments'],
    ],
], $pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $communityLayoutContext);
$communityMainLabel = '새 글';
$communityFrameModifier = 'home';
$communityHomeEditorial = true;
$latestPostSections = isset($latestPostSections) && is_array($latestPostSections) ? $latestPostSections : [];
?>
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-start.php'; ?>
                <header class="community-home-intro">
                    <h1 class="type-page-title">커뮤니티</h1>
                    <p>게시판별 새로운 이야기와 대화를 둘러보세요.</p>
                </header>
                <?php if ($latestPostSections === []) { ?>
                    <section class="card"><div class="card-body"><h2 class="card-title">새 글이 없습니다.</h2><p>공개된 게시판의 이야기가 이곳에 표시됩니다.</p></div></section>
                <?php } else { ?>
                    <div class="community-home-latest-sections">
                    <?php foreach ($latestPostSections as $latestSectionIndex => $latestSection) { ?>
                        <?php
                        $latestGroupKey = (string) ($latestSection['group_key'] ?? '');
                        $latestGroupTitle = trim((string) ($latestSection['group_title'] ?? ''));
                        $latestSectionGrouped = !empty($latestSection['is_grouped']);
                        $latestSectionBoards = is_array($latestSection['boards'] ?? null) ? $latestSection['boards'] : [];
                        $latestSectionTitleId = 'community_home_latest_section_' . (string) (int) $latestSectionIndex;
                        $latestSectionSize = count($latestSectionBoards) > 1 ? 'full' : 'compact';
                        $latestSectionTreatment = 'standard';
                        $latestSectionClass = $latestSectionGrouped
                            ? 'community-home-latest-section community-home-latest-section-grouped'
                            : 'community-home-latest-section community-home-latest-section-ungrouped';
                        ?>
                        <section class="<?php echo sr_e($latestSectionClass); ?>" data-treatment="<?php echo sr_e($latestSectionTreatment); ?>" data-composition="<?php echo sr_e($latestSectionSize); ?>"<?php if ($latestSectionGrouped) { ?> aria-labelledby="<?php echo sr_e($latestSectionTitleId); ?>"<?php } ?>>
                            <?php if ($latestSectionGrouped) { ?>
                                <header class="community-home-latest-group-header">
                                    <h2 id="<?php echo sr_e($latestSectionTitleId); ?>" class="community-home-latest-group-title">
                                        <?php if (sr_community_board_group_key_is_valid($latestGroupKey)) { ?>
                                            <a href="<?php echo sr_e(sr_url(sr_community_board_group_path($latestGroupKey))); ?>">
                                                <?php echo sr_e($latestGroupTitle !== '' ? $latestGroupTitle : $latestGroupKey); ?>
                                            </a>
                                        <?php } else { ?>
                                            <?php echo sr_e($latestGroupTitle !== '' ? $latestGroupTitle : '게시판 그룹'); ?>
                                        <?php } ?>
                                    </h2>
                                    <?php if (sr_community_board_group_key_is_valid($latestGroupKey)) { ?>
                                        <a class="community-home-latest-more" href="<?php echo sr_e(sr_url(sr_community_board_group_path($latestGroupKey))); ?>">전체 보기 <span aria-hidden="true">↗</span></a>
                                    <?php } ?>
                                </header>
                            <?php } ?>

                            <div class="community-home-latest-board-grid">
                                <?php foreach ($latestSectionBoards as $latestBoardIndex => $latestBoard) { ?>
                                    <?php
                                    $latestBoardKey = (string) ($latestBoard['board_key'] ?? '');
                                    if (!sr_community_board_key_is_valid($latestBoardKey)) {
                                        continue;
                                    }
                                    $latestBoardTitle = trim((string) ($latestBoard['title'] ?? ''));
                                    $latestBoardPosts = is_array($latestBoard['posts'] ?? null) ? $latestBoard['posts'] : [];
                                    $latestBoardUrl = sr_url('/community/board?key=' . rawurlencode($latestBoardKey));
                                    $latestBoardTitleId = $latestSectionTitleId . '_board_' . (string) (int) $latestBoardIndex;
                                    ?>
                                    <section class="card community-home-latest-board" aria-labelledby="<?php echo sr_e($latestBoardTitleId); ?>">
                                        <header class="card-header">
                                            <?php if (!$latestSectionGrouped) { ?>
                                                <h2 id="<?php echo sr_e($latestBoardTitleId); ?>" class="card-title">
                                                    <a href="<?php echo sr_e($latestBoardUrl); ?>"><?php echo sr_e($latestBoardTitle !== '' ? $latestBoardTitle : $latestBoardKey); ?></a>
                                                </h2>
                                            <?php } else { ?>
                                                <h3 id="<?php echo sr_e($latestBoardTitleId); ?>" class="card-title">
                                                    <a href="<?php echo sr_e($latestBoardUrl); ?>"><?php echo sr_e($latestBoardTitle !== '' ? $latestBoardTitle : $latestBoardKey); ?></a>
                                                </h3>
                                            <?php } ?>
                                            <a class="community-home-latest-more" href="<?php echo sr_e($latestBoardUrl); ?>">더 보기 <span aria-hidden="true">↗</span></a>
                                        </header>

                                        <div class="card-body">
                                            <?php if ($latestBoardPosts === []) { ?>
                                                <p class="community-home-latest-empty">새 글이 없습니다.</p>
                                            <?php } else { ?>
                                                <ul class="community-home-latest-list">
                                                    <?php foreach ($latestBoardPosts as $latestPostIndex => $post) { ?>
                                                        <?php
                                                        $postTitle = trim((string) ($post['title'] ?? ''));
                                                        $postUrl = sr_url('/community/post?id=' . (string) (int) ($post['id'] ?? 0));
                                                        $postAuthorLabel = sr_community_author_label_from_row($post, $config, false, $memberSettings, $pdo);
                                                        $postCreatedAt = (string) ($post['created_at'] ?? '');
                                                        // The feed owns preview visibility, including secret and paid posts.
                                                        $postPreviewAllowed = !empty($post['home_excerpt_allowed']) && empty($post['is_secret']);
                                                        $postPreviewImage = $postPreviewAllowed ? (string) ($post['home_image_url'] ?? '') : '';
                                                        $postPreviewExcerpt = $postPreviewAllowed ? (string) ($post['home_list_excerpt'] ?? '') : '';
                                                        ?>
                                                        <li class="community-home-latest-item">
                                                            <?php if ($latestPostIndex === 0 && $postPreviewImage !== '') { ?>
                                                                <a class="community-home-story-image" href="<?php echo sr_e($postUrl); ?>" aria-hidden="true" tabindex="-1">
                                                                    <img src="<?php echo sr_e($postPreviewImage); ?>" alt="" loading="lazy">
                                                                </a>
                                                            <?php } ?>
                                                            <span class="community-home-summary-title-line">
                                                                <?php if ((int) ($post['is_notice'] ?? 0) === 1) { ?>
                                                                    <span class="badge badge-soft-info community-post-notice-label"><?php echo sr_e('공지'); ?></span>
                                                                <?php } ?>
                                                                <a class="community-post-title community-home-summary-title" href="<?php echo sr_e($postUrl); ?>"><?php echo sr_e($postTitle !== '' ? $postTitle : '제목 없음'); ?></a><?php echo sr_community_post_comment_count_html($post); ?>
                                                            </span>
                                                            <?php if ($postPreviewExcerpt !== '') { ?>
                                                                <p class="community-home-story-excerpt"><?php echo sr_e($postPreviewExcerpt); ?></p>
                                                            <?php } ?>
                                                            <span class="community-home-summary-meta">
                                                                <?php echo sr_e($postAuthorLabel); ?>
                                                                <?php if ($postAuthorLabel !== '' && $postCreatedAt !== '') { ?>
                                                                    <span aria-hidden="true">&middot;</span>
                                                                <?php } ?>
                                                                <?php echo sr_community_time_html($postCreatedAt); ?>
                                                            </span>
                                                        </li>
                                                    <?php } ?>
                                                </ul>
                                            <?php } ?>
                                        </div>
                                    </section>
                                <?php } ?>
                            </div>
                        </section>
                    <?php } ?>
                    </div>
                <?php } ?>
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-end.php'; ?>
<?php sr_public_layout_end(); ?>
