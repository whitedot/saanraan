<?php

$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$latestPosts = isset($latestPosts) && is_array($latestPosts) ? $latestPosts : [];
$boards = isset($boards) && is_array($boards) ? $boards : [];
$config = isset($config) && is_array($config) ? $config : sr_runtime_config();
$memberSettings = isset($memberSettings) && is_array($memberSettings) ? $memberSettings : sr_member_settings($pdo);
$seo = sr_community_home_seo_meta();
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.home',
    'layout_key' => (string) ($communityLayoutKey ?? ''),
]));
?>

<main class="example-community-theme example-community-home" data-example-theme-view="community.home">
    <section class="example-community-hero" aria-labelledby="example_community_home_title">
        <p class="example-content-kicker">COMMUNITY MODULE VIEW THEME</p>
        <h1 id="example_community_home_title">Community Signal Board</h1>
        <p>커뮤니티 홈은 layout home view나 skin이 아니라 <code>modules/community/theme/sample/home.php</code>에서 렌더링됩니다.</p>
    </section>

    <section class="example-community-columns" aria-label="커뮤니티 홈">
        <aside class="example-community-panel">
            <h2>Boards</h2>
            <?php if ($boards === []) { ?>
                <p>표시할 게시판이 없습니다.</p>
            <?php } else { ?>
                <ol class="example-community-link-list">
                    <?php foreach ($boards as $board) { ?>
                        <?php $boardKey = (string) ($board['board_key'] ?? ''); ?>
                        <?php if (!sr_community_board_key_is_valid($boardKey)) { ?>
                            <?php continue; ?>
                        <?php } ?>
                        <li>
                            <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode($boardKey))); ?>">
                                <?php echo sr_e((string) ($board['title'] ?? $boardKey)); ?>
                            </a>
                        </li>
                    <?php } ?>
                </ol>
            <?php } ?>
        </aside>

        <section class="example-community-stream" aria-label="새 글">
            <?php if ($latestPosts === []) { ?>
                <p class="example-community-panel">새 글이 없습니다.</p>
            <?php } else { ?>
                <?php foreach ($latestPosts as $post) { ?>
                    <?php
                    $postTitle = (string) ($post['title'] ?? '');
                    $postUrl = sr_url('/community/post?id=' . (string) (int) ($post['id'] ?? 0));
                    $postAuthorLabel = sr_community_author_label_from_row($post, $config, false, $memberSettings, $pdo);
                    ?>
                    <article class="example-community-post-card">
                        <p class="example-content-kicker"><?php echo sr_e((string) ($post['board_title'] ?? 'community')); ?></p>
                        <h2>
                            <a href="<?php echo sr_e($postUrl); ?>"><?php echo sr_e($postTitle); ?></a><?php echo sr_community_post_comment_count_html($post); ?>
                        </h2>
                        <?php if (empty($post['is_secret']) && !empty($post['home_excerpt_allowed'])) { ?>
                            <p><?php echo sr_e((string) ($post['home_excerpt'] ?? sr_community_body_excerpt((string) ($post['body_text'] ?? ''), (string) ($post['body_format'] ?? 'plain'), 150))); ?></p>
                        <?php } ?>
                        <footer>
                            <span><?php echo sr_e($postAuthorLabel); ?></span>
                            <?php echo sr_community_time_html((string) ($post['created_at'] ?? '')); ?>
                        </footer>
                    </article>
                <?php } ?>
            <?php } ?>
        </section>
    </section>
</main>

<?php sr_public_layout_end(); ?>
