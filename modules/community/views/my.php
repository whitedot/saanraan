<?php

$myType = isset($myType) && in_array($myType, ['posts', 'comments'], true) ? $myType : 'posts';
$myPage = isset($myPage) ? max(1, (int) $myPage) : 1;
$myTitle = $myType === 'comments' ? '내 댓글' : '내 글';
$myBasePath = '/community/my?type=' . rawurlencode($myType);
$seo = [
    'title' => $myTitle,
    'canonical' => $myBasePath . ($myPage > 1 ? '&page=' . (string) $myPage : ''),
    'robots' => 'noindex, nofollow',
];
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$communityLayoutContext = sr_community_public_layout_context($communityLayoutSettings, [
    'layout_key' => (string) ($communityLayoutKey ?? ''),
]);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $communityLayoutContext);
$communityMainLabel = $myTitle;
$communityFrameModifier = 'account';
?>
    <?php include SR_ROOT . '/modules/community/layouts/basic/home-frame-start.php'; ?>
        <h1><?php echo sr_e($myTitle); ?></h1>
        <nav class="community-my-tabs" aria-label="<?php echo sr_e('내 커뮤니티 활동'); ?>">
            <a href="<?php echo sr_e(sr_url('/community/my?type=posts')); ?>"<?php echo $myType === 'posts' ? ' aria-current="page"' : ''; ?>>내 글</a>
            <a href="<?php echo sr_e(sr_url('/community/my?type=comments')); ?>"<?php echo $myType === 'comments' ? ' aria-current="page"' : ''; ?>>내 댓글</a>
        </nav>

        <?php if ($myType === 'posts') { ?>
            <?php if (empty($myPosts)) { ?>
                <p>작성한 글이 없습니다.</p>
            <?php } else { ?>
                <div class="community-board-post-list">
                    <?php foreach ($myPosts as $post) { ?>
                        <?php
                        $postUrl = sr_url('/community/post?id=' . (string) (int) ($post['id'] ?? 0));
                        $postExcerpt = !empty($post['is_secret'])
                            ? ''
                            : sr_community_body_excerpt((string) ($post['body_text'] ?? ''), (string) ($post['body_format'] ?? 'plain'), 160);
                        ?>
                        <article class="community-home-post community-board-post-list-item">
                            <div class="community-home-post-body">
                                <p class="community-home-post-meta">
                                    <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) ($post['board_key'] ?? '')))); ?>"><?php echo sr_e((string) ($post['board_title'] ?? '게시판')); ?></a>
                                    <?php if ((string) ($post['category_title'] ?? '') !== '') { ?>
                                        <span aria-hidden="true">&middot;</span>
                                        <span><?php echo sr_e((string) $post['category_title']); ?></span>
                                    <?php } ?>
                                    <span aria-hidden="true">&middot;</span>
                                    <?php echo sr_community_time_html((string) ($post['created_at'] ?? '')); ?>
                                </p>
                                <h2 class="community-post-title community-home-post-title">
                                    <a href="<?php echo sr_e($postUrl); ?>"><?php echo sr_e((string) ($post['title'] ?? '')); ?></a><?php echo sr_community_post_comment_count_html($post); ?>
                                </h2>
                                <?php if ($postExcerpt !== '') { ?>
                                    <p><?php echo sr_e($postExcerpt); ?></p>
                                <?php } elseif (!empty($post['is_secret'])) { ?>
                                    <p>비밀글입니다.</p>
                                <?php } ?>
                                <p class="community-home-post-meta">
                                    <span><?php echo sr_e('조회 ' . number_format((int) ($post['view_count'] ?? 0))); ?></span>
                                </p>
                            </div>
                        </article>
                    <?php } ?>
                </div>
            <?php } ?>
        <?php } else { ?>
            <?php if (empty($myComments)) { ?>
                <p>작성한 댓글이 없습니다.</p>
            <?php } else { ?>
                <ol class="community-my-comment-list">
                    <?php foreach ($myComments as $comment) { ?>
                        <?php
                        $commentUrl = sr_url('/community/post?id=' . (string) (int) ($comment['post_id'] ?? 0) . '#community-comment-' . (string) (int) ($comment['id'] ?? 0));
                        $commentExcerpt = sr_community_body_excerpt((string) ($comment['body_text'] ?? ''), 'plain', 160);
                        ?>
                        <li class="community-my-comment-item">
                            <p class="community-home-post-meta">
                                <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) ($comment['board_key'] ?? '')))); ?>"><?php echo sr_e((string) ($comment['board_title'] ?? '게시판')); ?></a>
                                <span aria-hidden="true">&middot;</span>
                                <?php echo sr_community_time_html((string) ($comment['created_at'] ?? '')); ?>
                            </p>
                            <a class="community-home-comment-excerpt" href="<?php echo sr_e($commentUrl); ?>"><?php echo sr_e($commentExcerpt); ?></a>
                            <a class="community-home-comment-post-title" href="<?php echo sr_e($commentUrl); ?>"><?php echo sr_e((string) ($comment['post_title'] ?? '게시글')); ?></a>
                        </li>
                    <?php } ?>
                </ol>
            <?php } ?>
        <?php } ?>

        <?php if ($myPage > 1 || !empty($myHasNextPage)) { ?>
            <nav class="community-search-pagination" aria-label="<?php echo sr_e($myTitle . ' 페이지'); ?>">
                <?php if ($myPage > 1) { ?>
                    <a href="<?php echo sr_e(sr_url($myBasePath . '&page=' . (string) ($myPage - 1))); ?>">이전</a>
                <?php } ?>
                <span><?php echo sr_e((string) $myPage); ?></span>
                <?php if (!empty($myHasNextPage)) { ?>
                    <a href="<?php echo sr_e(sr_url($myBasePath . '&page=' . (string) ($myPage + 1))); ?>">다음</a>
                <?php } ?>
            </nav>
        <?php } ?>
    <?php include SR_ROOT . '/modules/community/layouts/basic/home-frame-end.php'; ?>
<?php sr_public_layout_end(); ?>
