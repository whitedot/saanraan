<?php

$communityListSearchField = isset($searchField) ? sr_community_board_search_field((string) $searchField) : 'title_body';
$communityListSearchQuery = $keyword !== '' ? '&search_field=' . rawurlencode($communityListSearchField) . '&q=' . rawurlencode($keyword) : '';
$baseListPath = '/community/board?key=' . rawurlencode((string) $board['board_key'])
    . (isset($selectedCategory) && is_array($selectedCategory) ? '&category=' . rawurlencode((string) $selectedCategory['category_key']) : '')
    . (!empty($authorHash) && !empty($authorFilterAccountId) ? '&author=' . rawurlencode((string) $authorHash) : '')
    . $communityListSearchQuery;
$authorQuery = !empty($authorHash) && !empty($authorFilterAccountId) ? '&author=' . rawurlencode((string) $authorHash) : '';
$categoryQuery = isset($selectedCategory) && is_array($selectedCategory) ? '&category=' . rawurlencode((string) $selectedCategory['category_key']) : '';
$seo = sr_community_board_seo_meta($pdo, $board, [
    'category' => isset($selectedCategory) && is_array($selectedCategory) ? $selectedCategory : null,
    'keyword' => $keyword,
    'page' => $page,
    'category_invalid' => !empty($categoryInvalid),
]);
$pageTitle = (string) $seo['title'];
if (sr_module_enabled($pdo, 'banner') && is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (sr_module_enabled($pdo, 'popup_layer') && is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$communityBoardReactionsEnabled = sr_community_effective_board_reaction_enabled($pdo, $board, $communityLayoutSettings);
$communityListReactionVisible = $communityBoardReactionsEnabled && sr_module_enabled($pdo, 'reaction');
if ($communityListReactionVisible && is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}
$memberSettings = sr_member_settings($pdo);
$communityBoardPaidReadConfig = sr_community_asset_event_config($pdo, $board, $communityLayoutSettings, 'paid_read', 'once');
$communityBoardHomeExcerptAllowed = !sr_community_asset_event_required($communityBoardPaidReadConfig);
$communityListReactionCounts = $communityListReactionVisible && is_array($posts ?? null)
    ? sr_community_post_reaction_count_map($pdo, array_map(static fn (array $post): int => (int) ($post['id'] ?? 0), $posts))
    : [];
$communityLayoutContext = sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.list',
    'stylesheets' => sr_community_skin_stylesheets($skinKey ?? 'basic'),
    'output_slots' => [
        ['module_key' => 'community', 'point_key' => 'community.board.list', 'slot_key' => 'before_list'],
        ['module_key' => 'community', 'point_key' => 'community.board.list', 'slot_key' => 'after_list'],
    ],
]);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $communityLayoutContext);
$communityMainLabel = (string) ($board['title'] ?? '게시판');
$communityFrameModifier = 'list';
?>
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-start.php'; ?>
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

        <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
        <?php if ((string) ($board['description'] ?? '') !== '') { ?>
            <p><?php echo sr_e((string) $board['description']); ?></p>
        <?php } ?>

        <?php echo sr_public_feedback_toasts('community', $boardNotice, []); ?>
        <?php echo sr_public_feedback_toasts('member', (string) ($memberFollowFeedback['notice'] ?? ''), is_array($memberFollowFeedback['errors'] ?? null) ? $memberFollowFeedback['errors'] : []); ?>

        <?php if (!empty($authorFilterAccountId) && is_array($authorFilterAccount)) { ?>
            <div class="alert alert-info community-board-author-filter-alert" role="status">
                <span><?php echo sr_e((string) ($authorFilterAccount['public_name'] ?? $authorFilterAccount['display_name'] ?? '회원')); ?>님의 게시글을 보고 있습니다.</span>
                <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>">전체 보기</a>
            </div>
        <?php } ?>

        <?php $communityListCategoryEnabled = !empty($categoryEnabled); ?>
        <?php if ($communityListCategoryEnabled && isset($categories) && is_array($categories) && $categories !== []) { ?>
            <nav aria-label="카테고리">
                <p>
                    <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']) . $authorQuery . $communityListSearchQuery)); ?>"><?php echo sr_e('전체'); ?></a>
                    <?php foreach ($categories as $category) { ?>
                        <?php $categoryUrl = '/community/board?key=' . rawurlencode((string) $board['board_key']) . '&category=' . rawurlencode((string) $category['category_key']) . $authorQuery . $communityListSearchQuery; ?>
                        /
                        <a href="<?php echo sr_e(sr_url($categoryUrl)); ?>"<?php echo isset($selectedCategory) && is_array($selectedCategory) && (int) $selectedCategory['id'] === (int) $category['id'] ? ' aria-current="page"' : ''; ?>>
                            <?php echo sr_e((string) $category['title']); ?>
                        </a>
                    <?php } ?>
                </p>
            </nav>
        <?php } ?>

        <div class="community-board-list-actions">
        <form class="community-board-search" method="get" action="<?php echo sr_e(sr_url('/community/board')); ?>" role="search">
            <input type="hidden" name="key" value="<?php echo sr_e((string) $board['board_key']); ?>">
            <?php if ($categoryQuery !== '') { ?>
                <input type="hidden" name="category" value="<?php echo sr_e((string) $selectedCategory['category_key']); ?>">
            <?php } ?>
            <?php if ($authorQuery !== '') { ?>
                <input type="hidden" name="author" value="<?php echo sr_e((string) $authorHash); ?>">
            <?php } ?>
            <label class="sr-only" for="modules_community_list_q"><?php echo sr_e('게시판 검색'); ?></label>
            <label class="sr-only" for="modules_community_list_search_field"><?php echo sr_e('검색 조건'); ?></label>
            <div class="community-board-search-controls">
                <select id="modules_community_list_search_field" name="search_field" class="form-select">
                    <option value="title_body"<?php echo $communityListSearchField === 'title_body' ? ' selected' : ''; ?>><?php echo sr_e('제목+내용'); ?></option>
                    <option value="title"<?php echo $communityListSearchField === 'title' ? ' selected' : ''; ?>><?php echo sr_e('제목'); ?></option>
                    <option value="body"<?php echo $communityListSearchField === 'body' ? ' selected' : ''; ?>><?php echo sr_e('내용'); ?></option>
                    <option value="author"<?php echo $communityListSearchField === 'author' ? ' selected' : ''; ?>><?php echo sr_e('작성자'); ?></option>
                </select>
                <input id="modules_community_list_q" type="search" name="q" maxlength="100" value="<?php echo sr_e($keyword); ?>" class="form-input" placeholder="<?php echo sr_e('검색어를 입력하세요'); ?>" autocomplete="off">
                <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
                <?php if ($keyword !== '') { ?>
                    <a class="btn btn-outline-default" href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']) . $categoryQuery . $authorQuery)); ?>"><?php echo sr_e(sr_t('community::ui.text.893f3d94')); ?></a>
                <?php } ?>
            </div>
        </form>
        <?php if (!empty($canManageBoard) || $canWriteBoard) { ?>
            <div class="community-action-group community-action-group-trailing">
                <?php if (!empty($canManageBoard)) { ?>
                    <a class="btn btn-icon btn-soft-danger community-admin-button" href="<?php echo sr_e(sr_url('/admin/community/boards/edit?id=' . rawurlencode((string) $board['id']))); ?>" aria-label="<?php echo sr_e('게시판 관리'); ?>" title="<?php echo sr_e('게시판 관리'); ?>"><?php echo sr_material_icon_html('settings'); ?></a>
                <?php } ?>
                <?php if ($canWriteBoard) { ?>
                    <a class="btn btn-outline-default" href="<?php echo sr_e(sr_url('/community/write?key=' . rawurlencode((string) $board['board_key']))); ?>"><?php echo sr_e(sr_t('community::ui.text.1f1955dd')); ?></a>
                <?php } ?>
            </div>
        <?php } ?>
        </div>

        <?php if (!empty($categoryInvalid)) { ?>
            <p>카테고리를 찾을 수 없거나 현재 사용할 수 없습니다.</p>
        <?php } elseif ($posts === []) { ?>
            <p><?php echo sr_e($keyword !== '' ? sr_t('community::ui.search.58726bf2') : sr_t('community::ui.text.6a3d84bd')); ?></p>
        <?php } else { ?>
            <div class="card table-card community-board-table-card">
                <div class="table-wrapper">
                    <table class="table table-list community-board-table">
                        <thead>
                            <tr>
                                <th class="community-board-table-number" scope="col">번호</th>
                                <th class="community-board-table-title" scope="col">제목</th>
                                <th class="community-board-table-author" scope="col">작성자</th>
                                <th class="community-board-table-date" scope="col">작성일</th>
                                <th class="community-board-table-count" scope="col">조회</th>
                                <?php if ($communityListReactionVisible) { ?>
                                    <th class="community-board-table-count" scope="col">반응</th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($posts as $post) { ?>
                                <?php
                                $postId = (int) ($post['id'] ?? 0);
                                $postUrl = sr_url('/community/post?id=' . (string) $postId);
                                $postAuthorAccountId = (int) ($post['author_account_id'] ?? 0);
                                $postAuthorLabel = sr_community_author_label_from_row($post, $config, $canViewMemberIdentifiers, $memberSettings, $pdo);
                                $postExcerpt = !empty($post['is_secret']) || !$communityBoardHomeExcerptAllowed || empty($listExcerptEnabled)
                                    ? ''
                                    : sr_community_body_excerpt((string) ($post['body_text'] ?? ''), sr_community_post_body_format($pdo, $post, $settings), (int) $listExcerptLength);
                                $postReactionCount = (int) ($communityListReactionCounts[$postId] ?? 0);
                                ?>
                                <tr class="community-board-table-row">
                                    <td class="community-board-table-number"><?php echo sr_e(number_format($postId)); ?></td>
                                    <td class="community-board-table-title">
                                        <div class="community-board-table-title-line">
                                            <?php if ((int) ($post['is_notice'] ?? 0) === 1) { ?>
                                                <span class="badge badge-soft-info community-post-notice-label"><?php echo sr_e('공지'); ?></span>
                                            <?php } ?>
                                            <?php if ($communityListCategoryEnabled && (string) ($post['category_title'] ?? '') !== '') { ?>
                                                <span class="community-board-table-category">
                                                    <?php if ((string) ($post['category_status'] ?? '') === 'enabled' && (string) ($post['category_key'] ?? '') !== '') { ?>
                                                        <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']) . '&category=' . rawurlencode((string) $post['category_key']))); ?>"><?php echo sr_e((string) $post['category_title']); ?></a>
                                                    <?php } else { ?>
                                                        <?php echo sr_e((string) $post['category_title']); ?>
                                                    <?php } ?>
                                                </span>
                                            <?php } ?>
                                            <a class="community-board-table-title-link" href="<?php echo sr_e($postUrl); ?>"><?php echo sr_e((string) ($post['title'] ?? '')); ?></a><?php echo sr_community_post_comment_count_html($post); ?>
                                            <?php if ((int) ($post['active_attachment_count'] ?? 0) > 0) { ?>
                                                <span class="community-board-table-attachment"><?php echo sr_e('첨부 ' . number_format((int) ($post['active_attachment_count'] ?? 0))); ?></span>
                                            <?php } ?>
                                        </div>
                                        <?php if ($postExcerpt !== '') { ?>
                                            <p class="community-board-table-excerpt"><?php echo sr_e($postExcerpt); ?></p>
                                        <?php } ?>
                                        <div class="community-board-table-mobile-meta">
                                            <span><?php echo sr_e($postAuthorLabel); ?></span>
                                            <span aria-hidden="true">&middot;</span>
                                            <?php echo sr_community_time_html((string) ($post['created_at'] ?? '')); ?>
                                            <span aria-hidden="true">&middot;</span>
                                            <span><?php echo sr_e('조회 ' . number_format((int) ($post['view_count'] ?? 0))); ?></span>
                                            <?php if ($postReactionCount > 0) { ?>
                                                <span aria-hidden="true">&middot;</span>
                                                <span><?php echo sr_e('반응 ' . number_format($postReactionCount)); ?></span>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td class="community-board-table-author">
                                        <?php echo sr_member_public_name_menu_html($pdo, is_array($account ?? null) ? $account : null, $postAuthorAccountId, $postAuthorLabel, [
                                            'community_board_key' => (string) $board['board_key'],
                                            'community_board_accessible' => true,
                                            'return_to' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
                                        ]); ?>
                                    </td>
                                    <td class="community-board-table-date"><?php echo sr_community_time_html((string) ($post['created_at'] ?? '')); ?></td>
                                    <td class="community-board-table-count"><?php echo sr_e(number_format((int) ($post['view_count'] ?? 0))); ?></td>
                                    <?php if ($communityListReactionVisible) { ?>
                                        <td class="community-board-table-count"><?php echo sr_e(number_format($postReactionCount)); ?></td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
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
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-end.php'; ?>
<?php sr_public_layout_end(); ?>
