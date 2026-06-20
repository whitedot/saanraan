<?php

$baseListPath = '/community/board?key=' . rawurlencode((string) $board['board_key'])
    . (isset($selectedCategory) && is_array($selectedCategory) ? '&category=' . rawurlencode((string) $selectedCategory['category_key']) : '')
    . ($keyword !== '' ? '&q=' . rawurlencode($keyword) : '');
$seo = sr_community_board_seo_meta($pdo, $board, [
    'category' => isset($selectedCategory) && is_array($selectedCategory) ? $selectedCategory : null,
    'keyword' => $keyword,
    'page' => $page,
    'category_invalid' => !empty($categoryInvalid),
]);
$pageTitle = (string) $seo['title'];
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
if (!empty($communityLayoutSettings['reaction_enabled']) && is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}
$memberSettings = sr_member_settings($pdo);
$communityBoardPaidReadConfig = sr_community_asset_event_config($pdo, $board, $communityLayoutSettings, 'paid_read', 'once');
$communityBoardHomeExcerptAllowed = !sr_community_asset_event_required($communityBoardPaidReadConfig);
$communityListReactionCounts = !empty($communityLayoutSettings['reaction_enabled']) && is_array($posts ?? null)
    ? sr_community_post_reaction_count_map($pdo, array_map(static fn (array $post): int => (int) ($post['id'] ?? 0), $posts))
    : [];
$communityLayoutContext = sr_community_public_layout_context($communityLayoutSettings, [
    'stylesheets' => array_merge(sr_community_skin_stylesheets($skinKey ?? 'basic'), [
        '/modules/banner/assets/module.css',
        '/modules/popup_layer/assets/module.css',
    ]),
]);
$communityLayoutContext['site_menus'] = array_merge(is_array($communityLayoutContext['site_menus'] ?? null) ? $communityLayoutContext['site_menus'] : [], [
    'secondary' => '',
    'tertiary' => '',
    'quaternary' => '',
    'quinary' => '',
]);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $communityLayoutContext);
$communityMainLabel = (string) ($board['title'] ?? '게시판');
$communityFrameModifier = 'list';
?>
    <?php include SR_ROOT . '/modules/community/layouts/basic/home-frame-start.php'; ?>
        <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
            <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($board['popup_layer_list_id'] ?? 0)); ?>
        <?php } ?>

        <?php echo sr_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.board.list',
            'slot_key' => 'before_list',
            'subject_id' => (string) $board['id'],
        ]); ?>
        <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
            <?php echo sr_banner_render_public_banner($pdo, (int) ($board['banner_before_list_id'] ?? 0)); ?>
        <?php } ?>

        <h1><?php echo sr_e($pageTitle); ?></h1>
        <?php if ((string) ($board['description'] ?? '') !== '') { ?>
            <p><?php echo sr_e((string) $board['description']); ?></p>
        <?php } ?>

        <?php if ($boardNotice !== '') { ?>
            <p><?php echo sr_e($boardNotice); ?></p>
        <?php } ?>

        <?php if ($canWriteBoard) { ?>
            <p>
                <a href="<?php echo sr_e(sr_url('/community/write?key=' . rawurlencode((string) $board['board_key']))); ?>"><?php echo sr_e(sr_t('community::ui.text.1f1955dd')); ?></a>
            </p>
        <?php } ?>

        <?php $communityListCategoryEnabled = !empty($categoryEnabled); ?>
        <?php if ($communityListCategoryEnabled && isset($categories) && is_array($categories) && $categories !== []) { ?>
            <nav aria-label="카테고리">
                <p>
                    <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']) . ($keyword !== '' ? '&q=' . rawurlencode($keyword) : ''))); ?>"><?php echo sr_e('전체'); ?></a>
                    <?php foreach ($categories as $category) { ?>
                        <?php $categoryUrl = '/community/board?key=' . rawurlencode((string) $board['board_key']) . '&category=' . rawurlencode((string) $category['category_key']) . ($keyword !== '' ? '&q=' . rawurlencode($keyword) : ''); ?>
                        /
                        <a href="<?php echo sr_e(sr_url($categoryUrl)); ?>"<?php echo isset($selectedCategory) && is_array($selectedCategory) && (int) $selectedCategory['id'] === (int) $category['id'] ? ' aria-current="page"' : ''; ?>>
                            <?php echo sr_e((string) $category['title']); ?>
                        </a>
                    <?php } ?>
                </p>
            </nav>
        <?php } ?>

        <form method="get" action="<?php echo sr_e(sr_url('/community/board')); ?>">
            <input type="hidden" name="key" value="<?php echo sr_e((string) $board['board_key']); ?>">
            <p>
                <label for="modules_community_list_q">
                    <span><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></span>
                    <input id="modules_community_list_q" type="search" name="q" maxlength="100" value="<?php echo sr_e($keyword); ?>">
                </label>
                <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
                <?php if ($keyword !== '') { ?>
                    <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>"><?php echo sr_e(sr_t('community::ui.text.893f3d94')); ?></a>
                <?php } ?>
            </p>
        </form>

        <?php if (!empty($categoryInvalid)) { ?>
            <p>카테고리를 찾을 수 없거나 현재 사용할 수 없습니다.</p>
        <?php } elseif ($posts === []) { ?>
            <p><?php echo sr_e($keyword !== '' ? sr_t('community::ui.search.58726bf2') : sr_t('community::ui.text.6a3d84bd')); ?></p>
        <?php } else { ?>
            <div class="community-board-post-list">
                <?php foreach ($posts as $post) { ?>
                    <?php
                    $postUrl = sr_url('/community/post?id=' . (string) (int) ($post['id'] ?? 0));
                    $thumbnailUrl = sr_community_post_list_thumbnail_url($pdo, $post, $board, $communityLayoutSettings);
                    $postExcerpt = !empty($post['is_secret']) || !$communityBoardHomeExcerptAllowed
                        ? ''
                        : sr_community_body_excerpt((string) ($post['body_text'] ?? ''), (string) ($post['body_format'] ?? 'plain'), 160);
                    $postAuthorLabel = sr_community_author_label_from_row($post, $config, $canViewMemberIdentifiers, $memberSettings, $pdo);
                    $postAuthorInitial = $postAuthorLabel !== ''
                        ? (function_exists('mb_substr') ? mb_substr($postAuthorLabel, 0, 1) : substr($postAuthorLabel, 0, 1))
                        : '?';
                    $postAuthorAccountId = (int) ($post['author_account_id'] ?? 0);
                    $postAuthorAvatarClass = $postAuthorAccountId > 0
                        ? sr_member_default_avatar_color_class(sr_member_public_account_hash($config, $postAuthorAccountId))
                        : sr_member_default_avatar_color_class($postAuthorLabel);
                    ?>
                    <article class="community-home-post community-board-post-list-item">
                        <?php if ($thumbnailUrl !== '') { ?>
                            <a class="community-home-post-image-link" href="<?php echo sr_e($postUrl); ?>" aria-hidden="true" tabindex="-1">
                                <img class="community-home-post-image" src="<?php echo sr_e($thumbnailUrl); ?>" alt="" loading="lazy">
                            </a>
                        <?php } ?>
                        <div class="community-home-post-body">
                            <h2 class="community-post-title community-home-post-title">
                                <a href="<?php echo sr_e($postUrl); ?>"><?php echo sr_e((string) ($post['title'] ?? '')); ?></a><?php echo sr_community_post_comment_count_html($post); ?>
                            </h2>
                            <?php if ($communityListCategoryEnabled && (string) ($post['category_title'] ?? '') !== '') { ?>
                                <p class="community-board-post-category">
                                    <?php if ((string) ($post['category_status'] ?? '') === 'enabled' && (string) ($post['category_key'] ?? '') !== '') { ?>
                                        <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']) . '&category=' . rawurlencode((string) $post['category_key']))); ?>"><?php echo sr_e((string) $post['category_title']); ?></a>
                                    <?php } else { ?>
                                        <?php echo sr_e((string) $post['category_title']); ?>
                                    <?php } ?>
                                </p>
                            <?php } ?>
                            <?php if ($postExcerpt !== '') { ?>
                                <p><?php echo sr_e($postExcerpt); ?></p>
                            <?php } ?>
                            <p class="community-home-post-meta">
                                <span class="member-default-avatar community-home-post-avatar <?php echo sr_e($postAuthorAvatarClass); ?>" aria-hidden="true"><?php echo sr_e($postAuthorInitial); ?></span>
                                <span><?php echo sr_e($postAuthorLabel); ?></span>
                                <span aria-hidden="true">&middot;</span>
                                <?php echo sr_community_time_html((string) ($post['created_at'] ?? '')); ?>
                                <?php if ((int) ($post['active_attachment_count'] ?? 0) > 0) { ?>
                                    <span aria-hidden="true">&middot;</span>
                                    <span><?php echo sr_e('첨부 ' . number_format((int) ($post['active_attachment_count'] ?? 0))); ?></span>
                                <?php } ?>
                                <?php if ((int) ($post['view_count'] ?? 0) > 0) { ?>
                                    <span aria-hidden="true">&middot;</span>
                                    <span><?php echo sr_e('조회 ' . number_format((int) ($post['view_count'] ?? 0))); ?></span>
                                <?php } ?>
                                <?php $postReactionCount = (int) ($communityListReactionCounts[(int) ($post['id'] ?? 0)] ?? 0); ?>
                                <?php if ($postReactionCount > 0) { ?>
                                    <span aria-hidden="true">&middot;</span>
                                    <span><?php echo sr_e('반응 ' . number_format($postReactionCount)); ?></span>
                                <?php } ?>
                            </p>
                        </div>
                    </article>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($totalPages > 1) { ?>
            <nav aria-label="<?php echo sr_e(sr_t('community::ui.page.13726597')); ?>">
                <p>
                    <?php if ($page > 1) { ?>
                        <a href="<?php echo sr_e(sr_url($baseListPath . '&page=' . (string) ($page - 1))); ?>"><?php echo sr_e(sr_t('community::ui.text.da7e61c6')); ?></a>
                    <?php } ?>
                    <?php echo sr_e((string) $page); ?> / <?php echo sr_e((string) $totalPages); ?>
                    <?php if ($page < $totalPages) { ?>
                        <a href="<?php echo sr_e(sr_url($baseListPath . '&page=' . (string) ($page + 1))); ?>"><?php echo sr_e(sr_t('community::ui.text.aef613c6')); ?></a>
                    <?php } ?>
                </p>
            </nav>
        <?php } ?>

        <?php echo sr_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.board.list',
            'slot_key' => 'after_list',
            'subject_id' => (string) $board['id'],
        ]); ?>
        <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
            <?php echo sr_banner_render_public_banner($pdo, (int) ($board['banner_after_list_id'] ?? 0)); ?>
        <?php } ?>
    <?php include SR_ROOT . '/modules/community/layouts/basic/home-frame-end.php'; ?>
<?php sr_public_layout_end(); ?>
