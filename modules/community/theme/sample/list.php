<?php

$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$posts = isset($posts) && is_array($posts) ? $posts : [];
$keyword = isset($keyword) && is_string($keyword) ? $keyword : '';
$page = isset($page) ? max(1, (int) $page) : 1;
$totalPages = isset($totalPages) ? max(1, (int) $totalPages) : 1;
$config = isset($config) && is_array($config) ? $config : sr_runtime_config();
$baseListPath = '/community/board?key=' . rawurlencode((string) $board['board_key'])
    . (isset($selectedCategory) && is_array($selectedCategory) ? '&category=' . rawurlencode((string) $selectedCategory['category_key']) : '')
    . ($keyword !== '' ? '&q=' . rawurlencode($keyword) : '');
$seo = sr_community_board_seo_meta($pdo, $board, [
    'category' => isset($selectedCategory) && is_array($selectedCategory) ? $selectedCategory : null,
    'keyword' => $keyword,
    'page' => $page,
    'category_invalid' => !empty($categoryInvalid),
]);
$memberSettings = sr_member_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.list',
    'output_slots' => [
        ['module_key' => 'community', 'point_key' => 'community.board.list', 'slot_key' => 'before_list'],
        ['module_key' => 'community', 'point_key' => 'community.board.list', 'slot_key' => 'after_list'],
    ],
]));
?>

<main class="example-community-theme example-community-board" data-example-theme-view="community.list">
    <header class="example-community-hero">
        <p class="example-content-kicker">BOARD LIST FROM THEME</p>
        <h1 class="type-page-title"><?php echo sr_e((string) ($seo['title'] ?? $board['title'] ?? '게시판')); ?></h1>
        <?php if ((string) ($board['description'] ?? '') !== '') { ?>
            <p><?php echo sr_e((string) $board['description']); ?></p>
        <?php } ?>
        <?php if (!empty($canManageBoard) || !empty($canWriteBoard)) { ?>
            <p>
                <?php if (!empty($canManageBoard)) { ?>
                    <a class="btn btn-icon btn-soft-danger community-admin-button" href="<?php echo sr_e(sr_url('/admin/community/boards/edit?id=' . rawurlencode((string) $board['id']))); ?>" aria-label="<?php echo sr_e('게시판 관리'); ?>" title="<?php echo sr_e('게시판 관리'); ?>"><?php echo sr_material_icon_html('settings'); ?></a>
                <?php } ?>
                <?php if (!empty($canWriteBoard)) { ?>
                    <a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/community/write?key=' . rawurlencode((string) $board['board_key']))); ?>">글쓰기</a>
                <?php } ?>
            </p>
        <?php } ?>
    </header>

    <?php echo sr_public_feedback_toasts('community', (string) ($boardNotice ?? ''), []); ?>
    <?php echo sr_public_feedback_toasts('member', (string) ($memberFollowFeedback['notice'] ?? ''), is_array($memberFollowFeedback['errors'] ?? null) ? $memberFollowFeedback['errors'] : []); ?>

    <form class="example-community-searchbar" method="get" action="<?php echo sr_e(sr_url('/community/board')); ?>">
        <input type="hidden" name="key" value="<?php echo sr_e((string) $board['board_key']); ?>">
        <label for="example_community_board_q">검색</label>
        <input id="example_community_board_q" type="search" name="q" maxlength="100" value="<?php echo sr_e($keyword); ?>">
        <button type="submit" class="btn btn-solid-light">찾기</button>
    </form>

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'community',
        'point_key' => 'community.board.list',
        'slot_key' => 'before_list',
        'subject_id' => (string) $board['id'],
    ]); ?>

    <section class="example-community-stream" aria-label="게시글">
        <?php if (!empty($categoryInvalid)) { ?>
            <p class="example-community-panel">카테고리를 찾을 수 없거나 현재 사용할 수 없습니다.</p>
        <?php } elseif ($posts === []) { ?>
            <p class="example-community-panel"><?php echo sr_e($keyword !== '' ? sr_t('community::ui.search.58726bf2') : sr_t('community::ui.text.6a3d84bd')); ?></p>
        <?php } else { ?>
            <?php foreach ($posts as $post) { ?>
                <?php
                $postUrl = sr_url('/community/post?id=' . (string) (int) ($post['id'] ?? 0));
                $postExcerpt = !empty($post['is_secret']) || empty($listExcerptEnabled)
                    ? ''
                    : sr_community_body_excerpt((string) ($post['body_text'] ?? ''), sr_community_post_body_format($pdo, $post, $settings), (int) $listExcerptLength);
                $postAuthorLabel = sr_community_author_label_from_row($post, $config, $canViewMemberIdentifiers, $memberSettings, $pdo);
                ?>
                <article class="example-community-post-card">
                    <?php if ((string) ($post['category_title'] ?? '') !== '') { ?>
                        <p class="example-content-kicker"><?php echo sr_e((string) $post['category_title']); ?></p>
                    <?php } ?>
                    <h2>
                        <?php if ((int) ($post['is_notice'] ?? 0) === 1) { ?>
                            <span class="badge badge-soft-info community-post-notice-label"><?php echo sr_e('공지'); ?></span>
                        <?php } ?>
                        <a href="<?php echo sr_e($postUrl); ?>"><?php echo sr_e((string) ($post['title'] ?? '')); ?></a><?php echo sr_community_post_comment_count_html($post); ?>
                    </h2>
                    <?php if ($postExcerpt !== '') { ?>
                        <p><?php echo sr_e($postExcerpt); ?></p>
                    <?php } ?>
                    <footer>
                        <span><?php echo sr_e($postAuthorLabel); ?></span>
                        <?php echo sr_community_time_html((string) ($post['created_at'] ?? '')); ?>
                        <span>조회 <?php echo sr_e(number_format((int) ($post['view_count'] ?? 0))); ?></span>
                    </footer>
                </article>
            <?php } ?>
        <?php } ?>
    </section>

    <?php if ($totalPages > 1) { ?>
        <nav class="example-community-pagination" aria-label="게시글 페이지">
            <?php if ($page > 1) { ?>
                <a href="<?php echo sr_e(sr_url($baseListPath . '&page=' . (string) ($page - 1))); ?>">이전</a>
            <?php } ?>
            <span><?php echo sr_e((string) $page); ?> / <?php echo sr_e((string) $totalPages); ?></span>
            <?php if ($page < $totalPages) { ?>
                <a href="<?php echo sr_e(sr_url($baseListPath . '&page=' . (string) ($page + 1))); ?>">다음</a>
            <?php } ?>
        </nav>
    <?php } ?>

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'community',
        'point_key' => 'community.board.list',
        'slot_key' => 'after_list',
        'subject_id' => (string) $board['id'],
    ]); ?>
</main>

<?php sr_public_layout_end(); ?>
