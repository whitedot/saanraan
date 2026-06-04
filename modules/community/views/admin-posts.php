<?php

$communityPostsPage = isset($communityPostsPage) ? (string) $communityPostsPage : 'posts';
$adminPageTitle = $communityPostsPage === 'comments' ? '커뮤니티 댓글 관리' : '커뮤니티 게시글 관리';
$adminPageSubtitle = $communityPostsPage === 'comments' ? sr_t('community::ui.status.6bd8f817') : sr_t('community::ui.status.search.af9eb6e6');
$adminContainerClass = $communityPostsPage === 'comments' ? 'admin-page-community-comment-list admin-ui-scope' : 'admin-page-community-post-list admin-ui-scope';
$postListFilters = isset($postListFilters) && is_array($postListFilters) ? $postListFilters : ['status' => [], 'board_id' => 0, 'category_id' => 0, 'field' => 'all', 'q' => ''];
$postSort = isset($postSort) && is_array($postSort) ? $postSort : sr_community_admin_post_default_sort();
$postStatusCounts = isset($postStatusCounts) && is_array($postStatusCounts) ? $postStatusCounts : [];
$postBoardOptions = isset($postBoardOptions) && is_array($postBoardOptions) ? $postBoardOptions : [];
$totalPosts = (int) ($postStatusCounts['total'] ?? count($posts ?? []));
$commentListFilters = isset($commentListFilters) && is_array($commentListFilters) ? $commentListFilters : ['status' => [], 'board_id' => 0, 'field' => 'all', 'q' => ''];
$commentSort = isset($commentSort) && is_array($commentSort) ? $commentSort : sr_community_admin_comment_default_sort();
$commentStatusCounts = isset($commentStatusCounts) && is_array($commentStatusCounts) ? $commentStatusCounts : [];
$totalComments = (int) ($commentStatusCounts['total'] ?? count($comments ?? []));
$selectedPostStatuses = is_array($postListFilters['status'] ?? null) ? $postListFilters['status'] : [];
$selectedCommentStatuses = is_array($commentListFilters['status'] ?? null) ? $commentListFilters['status'] : [];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($communityPostsPage !== 'comments') { ?>
<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/community/posts')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.all.e078b14a')); ?></a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.97309089')); ?> <strong><?php echo sr_e((string) $totalPosts); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/community/posts?status=published')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.9d1ba9f4')); ?> <?php echo sr_e((string) ($postStatusCounts['published'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/posts?status=pending')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.2a73ed53')); ?> <?php echo sr_e((string) ($postStatusCounts['pending'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/posts?status=hidden')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.0eeb676f')); ?> <?php echo sr_e((string) ($postStatusCounts['hidden'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/posts?status=deleted')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.delete.6139b6c3')); ?> <?php echo sr_e((string) ($postStatusCounts['deleted'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
    </div>
</div>

<?php $postDetailFilterOpen = $selectedPostStatuses !== [] || (int) ($postListFilters['board_id'] ?? 0) !== 0 || (int) ($postListFilters['category_id'] ?? 0) !== 0; ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/community/posts')); ?>" class="filtering-form admin-community-post-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $postDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-community-post-search-grid">
            <div class="filtering-field admin-community-post-filter-field">
            <label for="community_admin_posts_field" class="filtering-label">검색조건</label>
            <select id="community_admin_posts_field" name="field" class="form-select filtering-input">
                <?php foreach (['all' => sr_t('community::ui.all.a4b69faf'), 'title' => sr_t('community::ui.text.08b17e43'), 'author' => sr_t('community::ui.text.f2ee20a7'), 'board' => sr_t('community::ui.text.4732a58f')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($postListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
            </div>
            <div class="filtering-field filtering-field-fill admin-community-post-filter-keyword">
            <label for="community_admin_posts_q" class="filtering-label"><?php echo sr_e(sr_t('community::ui.search.bda397fc')); ?></label>
            <input id="community_admin_posts_q" type="text" name="q" value="<?php echo sr_e((string) ($postListFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('community::ui.text.f2044028')); ?>">
            </div>
        </div>
        <div id="community_post_detail_filters" class="filtering-body" data-filtering-body<?php echo $postDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field admin-community-post-filter-status">
                <span class="filtering-label"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></span>
                <?php echo sr_admin_filter_toggle_group_html('community_admin_posts_status_filter', 'status', sr_admin_code_label_options($allowedPostStatuses, 'content_status'), $selectedPostStatuses, sr_t('community::ui.all.a4b69faf')); ?>
            </div>
            <div class="filtering-field admin-community-post-filter-board">
            <label for="community_admin_posts_board_filter" class="filtering-label"><?php echo sr_e(sr_t('community::ui.text.4732a58f')); ?></label>
            <select id="community_admin_posts_board_filter" name="board_id" class="form-select filtering-input">
                <option value="0"<?php echo (int) ($postListFilters['board_id'] ?? 0) === 0 ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.all.a4b69faf')); ?></option>
                <?php foreach ($postBoardOptions as $postBoardOption) { ?>
                    <option value="<?php echo sr_e((string) $postBoardOption['id']); ?>"<?php echo (int) ($postListFilters['board_id'] ?? 0) === (int) $postBoardOption['id'] ? ' selected' : ''; ?>>
                        <?php echo sr_e((string) ($postBoardOption['board_group_title'] ?? '') !== '' ? (string) $postBoardOption['board_group_title'] . ' / ' . (string) $postBoardOption['title'] : (string) $postBoardOption['title']); ?>
                    </option>
                <?php } ?>
            </select>
            </div>
            <div class="filtering-field admin-community-post-filter-category">
            <label for="community_admin_posts_category_id" class="filtering-label">카테고리</label>
            <select id="community_admin_posts_category_id" name="category_id" class="form-select filtering-input">
                <option value="0"<?php echo (int) ($postListFilters['category_id'] ?? 0) === 0 ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.all.a4b69faf')); ?></option>
                <?php foreach (($postCategoryOptions ?? []) as $postCategoryOption) { ?>
                    <option value="<?php echo sr_e((string) $postCategoryOption['id']); ?>"<?php echo (int) ($postListFilters['category_id'] ?? 0) === (int) $postCategoryOption['id'] ? ' selected' : ''; ?>>
                        <?php echo sr_e((string) $postCategoryOption['title']); ?>
                    </option>
                <?php } ?>
            </select>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $postDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="community_post_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
            <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
        </div>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title"><?php echo sr_e(sr_t('community::ui.list.3956e082')); ?></h2></div>
    <div class="admin-list-summary-row">
        <?php if (empty($postSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url(sr_community_admin_post_sort_options(), sr_community_admin_post_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="게시글 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($postPagination); ?>
    </div>
    <div class="table-wrapper">
    <table class="table admin-community-post-table">
        <caption class="sr-only"><?php echo sr_e(sr_t('community::ui.community.list.f0e443a9')); ?></caption>
        <thead class="ui-table-head">
            <tr>
                <th<?php echo sr_admin_sort_aria('board', $postSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.4732a58f'), 'board', $postSort, sr_community_admin_post_sort_options(), sr_community_admin_post_default_sort()); ?></th>
                <th>카테고리</th>
                <th<?php echo sr_admin_sort_aria('title', $postSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.08b17e43'), 'title', $postSort, sr_community_admin_post_sort_options(), sr_community_admin_post_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('author', $postSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.f2ee20a7'), 'author', $postSort, sr_community_admin_post_sort_options(), sr_community_admin_post_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('status', $postSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.status.e10195a1'), 'status', $postSort, sr_community_admin_post_sort_options(), sr_community_admin_post_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('published_comment_count', $postSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.c9fff683'), 'published_comment_count', $postSort, sr_community_admin_post_sort_options(), sr_community_admin_post_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('active_attachment_count', $postSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.353b76cf'), 'active_attachment_count', $postSort, sr_community_admin_post_sort_options(), sr_community_admin_post_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('created_at', $postSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.26c8f2fa'), 'created_at', $postSort, sr_community_admin_post_sort_options(), sr_community_admin_post_default_sort()); ?></th>
                <th class="text-end"><?php echo sr_e(sr_t('community::ui.text.460f7d7a')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($posts === []) { ?>
                <tr>
                    <td colspan="8" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.text.6a3d84bd')); ?></td>
                </tr>
            <?php } else { ?>
                <?php foreach ($posts as $post) { ?>
                    <?php
                    $postStatus = (string) $post['status'];
                    $statusClass = match ($postStatus) {
                        'published' => 'is-normal',
                        'pending' => 'is-blocked',
                        'hidden' => 'is-left',
                        default => 'is-left',
                    };
                    $postStatusSelectId = 'community_admin_post_status_' . (string) $post['id'];
                    ?>
                    <tr>
                        <td class="admin-table-break admin-community-post-board-cell"><?php echo sr_e((string) $post['board_title']); ?></td>
                        <td class="admin-table-break"><?php echo sr_e((string) ($post['category_title'] ?? '')); ?></td>
                        <td class="admin-table-break admin-community-post-title-cell">
                            <?php if ((string) $post['status'] === 'published') { ?>
                                <a href="<?php echo sr_e(sr_url('/community/post?id=' . (string) $post['id'])); ?>">
                                    <?php echo sr_e((string) $post['title']); ?>
                                </a>
                            <?php } else { ?>
                                <?php echo sr_e((string) $post['title']); ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-break admin-community-post-author-cell"><?php echo sr_e(sr_community_report_account_label(
                            sr_community_author_display_name_from_row($post, isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null),
                            (int) $post['author_account_id'],
                            is_string($post['author_account_status'] ?? null) ? $post['author_account_status'] : null
                        )); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($postStatus, 'content_status')); ?></span></td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e((string) $post['published_comment_count']); ?></td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e((string) ($post['active_attachment_count'] ?? 0)); ?></td>
                        <td class="admin-table-nowrap admin-community-post-date-cell"><?php echo sr_e((string) $post['created_at']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/posts')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="post_status">
                                <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                                <label for="<?php echo sr_e($postStatusSelectId); ?>" class="sr-only"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                    <select id="<?php echo sr_e($postStatusSelectId); ?>" name="status" class="form-select">
                                        <?php foreach ($allowedPostStatuses as $status) { ?>
                                            <option value="<?php echo sr_e($status); ?>"<?php echo $status === (string) $post['status'] ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                        <?php } ?>
                                    </select>
                                <button type="submit" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('community::ui.text.16f64fe4')); ?></button>
                            </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>
<?php echo sr_admin_pagination_html($postPagination, '게시글 목록 페이지'); ?>
<?php } ?>

<?php if ($communityPostsPage === 'comments') { ?>
<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/community/comments')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.all.e078b14a')); ?></a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.39ea7be6')); ?> <strong><?php echo sr_e((string) $totalComments); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/community/comments?status=published')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.9d1ba9f4')); ?> <?php echo sr_e((string) ($commentStatusCounts['published'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/comments?status=hidden')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.0eeb676f')); ?> <?php echo sr_e((string) ($commentStatusCounts['hidden'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/comments?status=deleted')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.delete.6139b6c3')); ?> <?php echo sr_e((string) ($commentStatusCounts['deleted'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
    </div>
</div>

<?php $commentDetailFilterOpen = $selectedCommentStatuses !== [] || (int) ($commentListFilters['board_id'] ?? 0) !== 0; ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/community/comments')); ?>" class="filtering-form admin-community-comment-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $commentDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-community-comment-search-grid">
            <div class="filtering-field admin-community-comment-filter-field">
            <label for="community_admin_comments_field" class="filtering-label">검색조건</label>
            <select id="community_admin_comments_field" name="field" class="form-select filtering-input">
                <?php foreach (['all' => sr_t('community::ui.all.a4b69faf'), 'body' => sr_t('community::ui.text.9118bb57'), 'author' => sr_t('community::ui.text.f2ee20a7'), 'post' => sr_t('community::ui.text.0b138cfe'), 'board' => sr_t('community::ui.text.4732a58f')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($commentListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
            </div>
            <div class="filtering-field filtering-field-fill admin-community-comment-filter-keyword">
            <label for="community_admin_comments_q" class="filtering-label"><?php echo sr_e(sr_t('community::ui.search.bda397fc')); ?></label>
            <input id="community_admin_comments_q" type="text" name="q" value="<?php echo sr_e((string) ($commentListFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('community::ui.text.92b4a17e')); ?>">
            </div>
        </div>
        <div id="community_comment_detail_filters" class="filtering-body" data-filtering-body<?php echo $commentDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field admin-community-comment-filter-status">
                <span class="filtering-label"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></span>
                <?php echo sr_admin_filter_toggle_group_html('community_admin_comments_status_filter', 'status', sr_admin_code_label_options($allowedCommentStatuses, 'content_status'), $selectedCommentStatuses, sr_t('community::ui.all.a4b69faf')); ?>
            </div>
            <div class="filtering-field admin-community-comment-filter-board">
            <label for="community_admin_comments_board_filter" class="filtering-label"><?php echo sr_e(sr_t('community::ui.text.4732a58f')); ?></label>
            <select id="community_admin_comments_board_filter" name="board_id" class="form-select filtering-input">
                <option value="0"<?php echo (int) ($commentListFilters['board_id'] ?? 0) === 0 ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.all.a4b69faf')); ?></option>
                <?php foreach ($postBoardOptions as $postBoardOption) { ?>
                    <option value="<?php echo sr_e((string) $postBoardOption['id']); ?>"<?php echo (int) ($commentListFilters['board_id'] ?? 0) === (int) $postBoardOption['id'] ? ' selected' : ''; ?>>
                        <?php echo sr_e((string) ($postBoardOption['board_group_title'] ?? '') !== '' ? (string) $postBoardOption['board_group_title'] . ' / ' . (string) $postBoardOption['title'] : (string) $postBoardOption['title']); ?>
                    </option>
                <?php } ?>
            </select>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $commentDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="community_comment_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
            <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
        </div>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title"><?php echo sr_e(sr_t('community::ui.list.78c1708d')); ?></h2></div>
    <div class="admin-list-summary-row">
        <?php if (empty($commentSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url(sr_community_admin_comment_sort_options(), sr_community_admin_comment_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="댓글 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($commentPagination); ?>
    </div>
    <div class="table-wrapper">
    <table class="table admin-community-comment-table">
        <caption class="sr-only"><?php echo sr_e(sr_t('community::ui.community.list.bf0539a8')); ?></caption>
        <thead class="ui-table-head">
            <tr>
                <th<?php echo sr_admin_sort_aria('post', $commentSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.0b138cfe'), 'post', $commentSort, sr_community_admin_comment_sort_options(), sr_community_admin_comment_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('author', $commentSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.f2ee20a7'), 'author', $commentSort, sr_community_admin_comment_sort_options(), sr_community_admin_comment_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('body', $commentSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.9118bb57'), 'body', $commentSort, sr_community_admin_comment_sort_options(), sr_community_admin_comment_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('status', $commentSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.status.e10195a1'), 'status', $commentSort, sr_community_admin_comment_sort_options(), sr_community_admin_comment_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('created_at', $commentSort); ?>><?php echo sr_admin_sort_header_html(sr_t('community::ui.text.26c8f2fa'), 'created_at', $commentSort, sr_community_admin_comment_sort_options(), sr_community_admin_comment_default_sort()); ?></th>
                <th class="text-end"><?php echo sr_e(sr_t('community::ui.text.460f7d7a')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($comments === []) { ?>
                <tr>
                    <td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.text.ff4a5d06')); ?></td>
                </tr>
            <?php } else { ?>
                <?php foreach ($comments as $comment) { ?>
                    <?php
                    $commentStatus = (string) $comment['status'];
                    $statusClass = match ($commentStatus) {
                        'published' => 'is-normal',
                        'hidden' => 'is-left',
                        default => 'is-left',
                    };
                    $commentStatusSelectId = 'community_admin_comment_status_' . (string) $comment['id'];
                    ?>
                    <tr>
                        <td class="admin-table-break admin-community-comment-post-cell">
                            <a href="<?php echo sr_e(sr_url('/community/post?id=' . (string) $comment['post_id'])); ?>">
                                <?php echo sr_e((string) $comment['post_title']); ?>
                            </a>
                        </td>
                        <td class="admin-table-break admin-community-comment-author-cell"><?php echo sr_e(sr_community_report_account_label(
                            sr_community_author_display_name_from_row($comment, isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null),
                            (int) $comment['author_account_id'],
                            is_string($comment['author_account_status'] ?? null) ? $comment['author_account_status'] : null
                        )); ?></td>
                        <td class="admin-table-break admin-community-comment-body-cell"><?php echo sr_community_plain_text_html((string) $comment['body_text']); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($commentStatus, 'content_status')); ?></span></td>
                        <td class="admin-table-nowrap admin-community-comment-date-cell"><?php echo sr_e((string) $comment['created_at']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/comments')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="comment_status">
                                    <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                    <label for="<?php echo sr_e($commentStatusSelectId); ?>" class="sr-only"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                    <select id="<?php echo sr_e($commentStatusSelectId); ?>" name="status" class="form-select">
                                        <?php foreach ($allowedCommentStatuses as $status) { ?>
                                            <option value="<?php echo sr_e($status); ?>"<?php echo $status === (string) $comment['status'] ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('community::ui.text.16f64fe4')); ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>
<?php echo sr_admin_pagination_html($commentPagination, '댓글 목록 페이지'); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
