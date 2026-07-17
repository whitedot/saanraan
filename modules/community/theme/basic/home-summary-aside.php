<aside class="community-home-aside" aria-label="커뮤니티 요약">
    <?php include SR_ROOT . '/modules/community/theme/basic/board-sidebar-menu.php'; ?>
    <section class="card community-home-aside-section" aria-labelledby="community_home_popular_posts_title">
        <div class="card-header">
            <h2 id="community_home_popular_posts_title" class="community-home-aside-title">인기글</h2>
        </div>
        <div class="card-body community-home-aside-body">
            <?php if (empty($popularPosts)) { ?>
                <p>인기글이 없습니다.</p>
            <?php } else { ?>
                <ul>
                    <?php foreach ($popularPosts as $post) { ?>
                        <?php
                        $popularPostImageUrl = (string) ($post['home_image_url'] ?? '');
                        $popularPostAuthorLabel = sr_community_author_label_from_row($post, $config, false, $memberSettings, $pdo);
                        $popularPostReactionCount = (int) ($popularPostReactionCounts[(int) ($post['id'] ?? 0)] ?? 0);
                        ?>
                        <li class="community-home-summary-item">
                            <?php if ($popularPostImageUrl !== '') { ?>
                                <a class="community-home-summary-image-link" href="<?php echo sr_e(sr_url('/community/post?id=' . (string) (int) ($post['id'] ?? 0))); ?>" aria-hidden="true" tabindex="-1">
                                    <img class="community-home-summary-image" src="<?php echo sr_e($popularPostImageUrl); ?>" alt="" loading="lazy">
                                </a>
                            <?php } ?>
                            <span class="community-home-summary-title-line">
                                <a class="community-post-title community-home-summary-title" href="<?php echo sr_e(sr_url('/community/post?id=' . (string) (int) ($post['id'] ?? 0))); ?>"><?php echo sr_e((string) ($post['title'] ?? '')); ?></a><?php echo sr_community_post_comment_count_html($post); ?>
                            </span>
                            <span class="community-home-summary-meta">
                                <?php echo sr_e($popularPostAuthorLabel); ?>
                                <?php if ($popularPostAuthorLabel !== '' && (string) ($post['created_at'] ?? '') !== '') { ?>
                                    <span aria-hidden="true">&middot;</span>
                                <?php } ?>
                                <?php echo sr_community_time_html((string) ($post['created_at'] ?? '')); ?>
                                <?php if ($popularPostReactionCount > 0) { ?>
                                    <span aria-hidden="true">&middot;</span>
                                    <span><?php echo sr_e('반응 ' . number_format($popularPostReactionCount)); ?></span>
                                <?php } ?>
                            </span>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </section>

    <section class="card community-home-aside-section" aria-labelledby="community_home_latest_comments_title">
        <div class="card-header">
            <h2 id="community_home_latest_comments_title" class="community-home-aside-title">최신댓글</h2>
        </div>
        <div class="card-body community-home-aside-body">
            <?php if (empty($latestComments)) { ?>
                <p>최신댓글이 없습니다.</p>
            <?php } else { ?>
                <ul>
                    <?php foreach ($latestComments as $comment) { ?>
                        <?php
                        $commentPostBaseUrl = sr_url('/community/post?id=' . (string) (int) ($comment['post_id'] ?? 0));
                        $commentPostUrl = $commentPostBaseUrl . '#community-comment-' . (string) (int) ($comment['id'] ?? 0);
                        $commentExcerptAllowed = empty($comment['is_secret'])
                            && empty($comment['post_is_secret'])
                            && !empty($homeExcerptAllowedByBoardId[(int) ($comment['board_id'] ?? 0)]);
                        $commentExcerpt = $commentExcerptAllowed ? sr_community_body_excerpt((string) ($comment['body_text'] ?? ''), 'plain', 50) : '';
                        $commentAuthorLabel = sr_community_author_label_from_row($comment, $config, false, $memberSettings, $pdo);
                        $commentPostTitle = trim((string) ($comment['post_title'] ?? ''));
                        ?>
                        <li class="community-home-comment-item">
                            <?php if ($commentExcerpt !== '') { ?>
                                <a class="community-post-title community-home-comment-excerpt" href="<?php echo sr_e($commentPostUrl); ?>"><?php echo sr_e($commentExcerpt); ?></a>
                            <?php } else { ?>
                                <a class="community-post-title community-home-comment-excerpt" href="<?php echo sr_e($commentPostUrl); ?>"><?php echo sr_e('댓글을 확인하세요.'); ?></a>
                            <?php } ?>
                            <span class="community-home-comment-meta">
                                <?php echo sr_e($commentAuthorLabel); ?>
                                <?php if ($commentAuthorLabel !== '') { ?>
                                    <span aria-hidden="true">&middot;</span>
                                <?php } ?>
                                <?php echo sr_community_time_html((string) ($comment['created_at'] ?? '')); ?>
                                <span aria-hidden="true">&middot;</span>
                                <a class="community-home-comment-post-title" href="<?php echo sr_e($commentPostBaseUrl); ?>">
                                    <?php echo sr_e($commentPostTitle !== '' ? $commentPostTitle : '게시글 보기'); ?>
                                </a>
                            </span>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </section>

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'community',
        'point_key' => 'community.sidebar.summary',
        'slot_key' => 'after_latest_comments',
    ]); ?>

    <?php if (!empty($communitySeriesSupported)) { ?>
        <section class="community-home-aside-section" aria-labelledby="community_home_series_title">
            <div class="community-home-aside-body">
                <h2 id="community_home_series_title" class="community-home-aside-title">시리즈</h2>
                <?php if (empty($recentSeries)) { ?>
                    <p>시리즈가 없습니다.</p>
                <?php } else { ?>
                    <ul>
                        <?php foreach ($recentSeries as $series) { ?>
                            <?php
                            $seriesOwnerLabel = sr_community_author_label_from_row([
                                'author_account_id' => (int) ($series['owner_account_id'] ?? 0),
                                'author_display_name' => (string) ($series['owner_display_name'] ?? ''),
                                'author_nickname' => (string) ($series['owner_nickname'] ?? ''),
                                'author_account_status' => (string) ($series['owner_account_status'] ?? ''),
                            ], $config, false, $memberSettings, $pdo);
                            ?>
                            <li class="community-home-series-item">
                                <?php if ((int) ($series['first_post_id'] ?? 0) > 0) { ?>
                                    <a class="community-home-series-title" href="<?php echo sr_e(sr_url('/community/post?id=' . (string) (int) $series['first_post_id'])); ?>"><?php echo sr_e((string) ($series['title'] ?? '')); ?></a>
                                <?php } else { ?>
                                    <strong class="community-home-series-title"><?php echo sr_e((string) ($series['title'] ?? '')); ?></strong>
                                <?php } ?>
                                <span class="community-home-series-meta">
                                    <?php echo sr_e($seriesOwnerLabel); ?>
                                    <?php if ($seriesOwnerLabel !== '' && (string) ($series['updated_at'] ?? '') !== '') { ?>
                                        <span aria-hidden="true">&middot;</span>
                                    <?php } ?>
                                    <?php echo sr_community_time_html((string) ($series['updated_at'] ?? '')); ?>
                                </span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </section>
    <?php } ?>
</aside>
