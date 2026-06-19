<?php

$seo = sr_community_home_seo_meta();
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$communityLayoutContext = sr_community_public_layout_context($communityLayoutSettings, [
    'layout_key' => (string) ($communityLayoutKey ?? ''),
    'stylesheets' => [
        '/modules/banner/assets/module.css',
        '/modules/popup_layer/assets/module.css',
    ],
]);
$communityLayoutContext['site_menus'] = array_merge(is_array($communityLayoutContext['site_menus'] ?? null) ? $communityLayoutContext['site_menus'] : [], [
    'secondary' => '',
    'tertiary' => '',
    'quaternary' => '',
    'quinary' => '',
]);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $communityLayoutContext);
?>
    <main class="community-screen">
        <div class="community-home-layout">
            <aside class="community-home-sidebar" aria-label="커뮤니티 보조 메뉴" data-community-home-accordion>
                <?php if (trim((string) ($homeSidebarMenuHtml ?? '')) !== '') { ?>
                    <?php echo $homeSidebarMenuHtml; ?>
                <?php } else { ?>
                    <p>보조 메뉴가 없습니다.</p>
                <?php } ?>
                <div class="community-home-secondary-banner-slot">
                    <?php echo sr_render_output_slot($pdo, [
                        'module_key' => 'community',
                        'point_key' => 'community.home',
                        'slot_key' => 'after_secondary_navigation',
                    ]); ?>
                </div>
            </aside>

            <section class="community-home-main" aria-label="새 글">
                <?php if (empty($latestPosts)) { ?>
                    <p>새 글이 없습니다.</p>
                <?php } else { ?>
                    <?php foreach ($latestPosts as $post) { ?>
                        <?php
                        $postTitle = (string) ($post['title'] ?? '');
                        $postUrl = sr_url('/community/post?id=' . (string) (int) ($post['id'] ?? 0));
                        $postImageUrl = (string) ($post['home_image_url'] ?? '');
                        $postExcerpt = !empty($post['is_secret']) || empty($post['home_excerpt_allowed'])
                            ? ''
                            : sr_community_body_excerpt((string) ($post['body_text'] ?? ''), (string) ($post['body_format'] ?? 'plain'), 160);
                        $postAuthorLabel = sr_community_author_label_from_row($post, $config, false, $memberSettings, $pdo);
                        $postAuthorInitial = $postAuthorLabel !== ''
                            ? (function_exists('mb_substr') ? mb_substr($postAuthorLabel, 0, 1) : substr($postAuthorLabel, 0, 1))
                            : '?';
                        $postAuthorAccountId = (int) ($post['author_account_id'] ?? 0);
                        $postAuthorAvatarClass = $postAuthorAccountId > 0
                            ? sr_member_default_avatar_color_class(sr_member_public_account_hash($config, $postAuthorAccountId))
                            : sr_member_default_avatar_color_class($postAuthorLabel);
                        ?>
                        <article class="community-home-post">
                            <?php if ($postImageUrl !== '') { ?>
                                <a class="community-home-post-image-link" href="<?php echo sr_e($postUrl); ?>" aria-hidden="true" tabindex="-1">
                                    <img class="community-home-post-image" src="<?php echo sr_e($postImageUrl); ?>" alt="" loading="lazy">
                                </a>
                            <?php } ?>
                            <div class="community-home-post-body">
                                <h2 class="community-post-title community-home-post-title"><a href="<?php echo sr_e($postUrl); ?>"><?php echo sr_e($postTitle); ?></a></h2>
                                <?php if ($postExcerpt !== '') { ?>
                                    <p><?php echo sr_e($postExcerpt); ?></p>
                                <?php } ?>
                                <p class="community-home-post-meta">
                                    <span class="member-default-avatar community-home-post-avatar <?php echo sr_e($postAuthorAvatarClass); ?>" aria-hidden="true"><?php echo sr_e($postAuthorInitial); ?></span>
                                    <span><?php echo sr_e($postAuthorLabel); ?></span>
                                    <span aria-hidden="true">&middot;</span>
                                    <?php echo sr_community_time_html((string) ($post['created_at'] ?? '')); ?>
                                </p>
                            </div>
                        </article>
                    <?php } ?>
                <?php } ?>
            </section>

            <aside class="community-home-aside" aria-label="커뮤니티 요약">
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
                                    <li>
                                        <?php if ($popularPostImageUrl !== '') { ?>
                                            <a class="community-home-summary-image-link" href="<?php echo sr_e(sr_url('/community/post?id=' . (string) (int) ($post['id'] ?? 0))); ?>" aria-hidden="true" tabindex="-1">
                                                <img class="community-home-summary-image" src="<?php echo sr_e($popularPostImageUrl); ?>" alt="" loading="lazy">
                                            </a>
                                        <?php } ?>
                                        <a class="community-post-title community-home-summary-title" href="<?php echo sr_e(sr_url('/community/post?id=' . (string) (int) ($post['id'] ?? 0))); ?>"><?php echo sr_e((string) ($post['title'] ?? '')); ?></a>
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
                                    <li>
                                        <?php if ($commentExcerpt !== '') { ?>
                                            <a class="community-home-comment-excerpt" href="<?php echo sr_e($commentPostUrl); ?>"><?php echo sr_e($commentExcerpt); ?></a>
                                        <?php } else { ?>
                                            <a class="community-home-comment-excerpt" href="<?php echo sr_e($commentPostUrl); ?>"><?php echo sr_e('댓글을 확인하세요.'); ?></a>
                                        <?php } ?>
                                        <a class="community-home-comment-meta" href="<?php echo sr_e($commentPostUrl); ?>">
                                            <?php echo sr_e($commentAuthorLabel); ?>
                                            <?php if ($commentAuthorLabel !== '') { ?>
                                                <span aria-hidden="true">&middot;</span>
                                            <?php } ?>
                                            <?php echo sr_community_time_html((string) ($comment['created_at'] ?? '')); ?>
                                        </a>
                                        <a class="community-post-title community-home-comment-post-title" href="<?php echo sr_e($commentPostBaseUrl); ?>">
                                            <?php echo sr_e($commentPostTitle !== '' ? $commentPostTitle : '게시글 보기'); ?>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>
                </section>
            </aside>
        </div>
    </main>
<?php sr_public_layout_end(); ?>
