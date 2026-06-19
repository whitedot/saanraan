<aside class="community-home-aside" aria-label="커뮤니티 요약">
    <section class="card" aria-labelledby="community_home_popular_posts_title">
        <div class="card-body">
            <h2 id="community_home_popular_posts_title" class="card-title community-home-aside-title">인기글</h2>
            <?php if (empty($popularPosts)) { ?>
                <p>인기글이 없습니다.</p>
            <?php } else { ?>
                <ul>
                    <?php foreach ($popularPosts as $post) { ?>
                        <?php
                        $popularPostImageUrl = (string) ($post['home_image_url'] ?? '');
                        $popularPostAuthorLabel = sr_community_author_label_from_row($post, $config, false, $memberSettings, $pdo);
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
                            </span>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </section>

    <section class="card" aria-labelledby="community_home_latest_comments_title">
        <div class="card-body">
            <h2 id="community_home_latest_comments_title" class="card-title community-home-aside-title">최신댓글</h2>
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

    <?php if (!empty($communitySeriesSupported)) { ?>
        <section class="card" aria-labelledby="community_home_series_title">
            <div class="card-body">
                <h2 id="community_home_series_title" class="card-title community-home-aside-title">시리즈</h2>
                <?php if (empty($recentSeries)) { ?>
                    <p>시리즈가 없습니다.</p>
                <?php } else { ?>
                    <ul>
                        <?php foreach ($recentSeries as $series) { ?>
                            <li>
                                <?php if ((int) ($series['first_post_id'] ?? 0) > 0) { ?>
                                    <a href="<?php echo sr_e(sr_url('/community/post?id=' . (string) (int) $series['first_post_id'])); ?>"><?php echo sr_e((string) ($series['title'] ?? '')); ?></a>
                                <?php } else { ?>
                                    <?php echo sr_e((string) ($series['title'] ?? '')); ?>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </section>
    <?php } ?>
</aside>
