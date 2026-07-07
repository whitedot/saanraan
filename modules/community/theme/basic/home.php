<?php

$seo = sr_community_home_seo_meta();
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$communityLayoutContext = sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.home',
    'layout_key' => (string) ($communityLayoutKey ?? ''),
    'stylesheets' => sr_enabled_module_asset_paths($pdo ?? null, [
        'banner' => '/modules/banner/assets/module.css',
        'popup_layer' => '/modules/popup_layer/assets/module.css',
    ]),
]);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $communityLayoutContext);
$communityMainLabel = '새 글';
$communityFrameModifier = 'home';
?>
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-start.php'; ?>
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
                            : (string) ($post['home_excerpt'] ?? sr_community_body_excerpt((string) ($post['body_text'] ?? ''), sr_community_post_body_format($pdo, $post, $settings), 160));
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
                                <h2 class="community-post-title community-home-post-title">
                                    <?php if ((int) ($post['is_notice'] ?? 0) === 1) { ?>
                                        <span class="badge badge-soft-info community-post-notice-label"><?php echo sr_e('공지'); ?></span>
                                    <?php } ?>
                                    <a href="<?php echo sr_e($postUrl); ?>"><?php echo sr_e($postTitle); ?></a><?php echo sr_community_post_comment_count_html($post); ?>
                                </h2>
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
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-end.php'; ?>
<?php sr_public_layout_end(); ?>
