<?php

$communityPostsPage = isset($communityPostsPage) ? (string) $communityPostsPage : 'posts';
$adminPageTitle = $communityPostsPage === 'comments' ? '커뮤니티 댓글' : '커뮤니티 게시글';
$adminPageSubtitle = $communityPostsPage === 'comments' ? '댓글 상태를 확인하고 관리 작업을 이어가세요.' : '게시글 상태와 게시판을 확인하고 조건 검색과 관리 작업을 이어가세요.';
$adminContainerClass = $communityPostsPage === 'comments' ? 'admin-page-community-comment-list admin-ui-scope' : 'admin-page-community-post-list admin-ui-scope';
$postListFilters = isset($postListFilters) && is_array($postListFilters) ? $postListFilters : ['status' => '', 'board_id' => 0, 'field' => 'all', 'q' => ''];
$postStatusCounts = isset($postStatusCounts) && is_array($postStatusCounts) ? $postStatusCounts : [];
$postBoardOptions = isset($postBoardOptions) && is_array($postBoardOptions) ? $postBoardOptions : [];
$totalPosts = (int) ($postStatusCounts['total'] ?? count($posts ?? []));
$commentListFilters = isset($commentListFilters) && is_array($commentListFilters) ? $commentListFilters : ['status' => '', 'board_id' => 0, 'field' => 'all', 'q' => ''];
$commentStatusCounts = isset($commentStatusCounts) && is_array($commentStatusCounts) ? $commentStatusCounts : [];
$totalComments = (int) ($commentStatusCounts['total'] ?? count($comments ?? []));
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($communityPostsPage !== 'comments') { ?>
<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/community/posts')); ?>" class="btn btn-solid-light">전체 보기</a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">총게시글 <strong><?php echo sr_e((string) $totalPosts); ?>개</strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/community/posts?status=published')); ?>" class="admin-summary-meta">공개 <?php echo sr_e((string) ($postStatusCounts['published'] ?? 0)); ?>개</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/posts?status=pending')); ?>" class="admin-summary-meta">대기 <?php echo sr_e((string) ($postStatusCounts['pending'] ?? 0)); ?>개</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/posts?status=hidden')); ?>" class="admin-summary-meta">숨김 <?php echo sr_e((string) ($postStatusCounts['hidden'] ?? 0)); ?>개</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/posts?status=deleted')); ?>" class="admin-summary-meta">삭제 <?php echo sr_e((string) ($postStatusCounts['deleted'] ?? 0)); ?>개</a>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/community/posts')); ?>" class="admin-filter admin-community-post-filter ui-form-theme">
    <div class="admin-filter-grid admin-community-post-search-grid">
        <div class="admin-filter-field admin-community-post-filter-status">
            <label for="community_admin_posts_status_filter" class="admin-filter-label">상태</label>
            <select id="community_admin_posts_status_filter" name="status" class="form-select admin-filter-input">
                <option value=""<?php echo (string) ($postListFilters['status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                <?php foreach ($allowedPostStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($postListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-post-filter-board">
            <label for="community_admin_posts_board_filter" class="admin-filter-label">게시판</label>
            <select id="community_admin_posts_board_filter" name="board_id" class="form-select admin-filter-input">
                <option value="0"<?php echo (int) ($postListFilters['board_id'] ?? 0) === 0 ? ' selected' : ''; ?>>전체</option>
                <?php foreach ($postBoardOptions as $postBoardOption) { ?>
                    <option value="<?php echo sr_e((string) $postBoardOption['id']); ?>"<?php echo (int) ($postListFilters['board_id'] ?? 0) === (int) $postBoardOption['id'] ? ' selected' : ''; ?>>
                        <?php echo sr_e((string) ($postBoardOption['board_group_title'] ?? '') !== '' ? (string) $postBoardOption['board_group_title'] . ' / ' . (string) $postBoardOption['title'] : (string) $postBoardOption['title']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-post-filter-field">
            <label for="community_admin_posts_field" class="admin-filter-label">검색 조건</label>
            <select id="community_admin_posts_field" name="field" class="form-select admin-filter-input">
                <?php foreach (['all' => '전체', 'title' => '제목', 'author' => '작성자', 'board' => '게시판'] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($postListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-post-filter-keyword">
            <label for="community_admin_posts_q" class="admin-filter-label">검색어</label>
            <input id="community_admin_posts_q" type="search" name="q" value="<?php echo sr_e((string) ($postListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="제목, 작성자, 게시판">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title">게시글 목록</h2></div>
    <div class="table-wrapper">
    <table class="table admin-community-post-table">
        <caption class="sr-only">커뮤니티 게시글 목록</caption>
        <thead class="ui-table-head">
            <tr>
                <th>ID</th>
                <th>게시판</th>
                <th>제목</th>
                <th>작성자</th>
                <th>상태</th>
                <th>댓글</th>
                <th>첨부</th>
                <th>작성일</th>
                <th class="text-end">처리</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($posts === []) { ?>
                <tr>
                    <td colspan="9" class="admin-empty-state">게시글이 없습니다.</td>
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
                        <td class="admin-table-nowrap community-id"><?php echo sr_e((string) $post['id']); ?></td>
                        <td class="admin-table-break admin-community-post-board-cell"><?php echo sr_e((string) $post['board_title']); ?></td>
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
                            is_string($post['author_display_name'] ?? null) ? $post['author_display_name'] : null,
                            (int) $post['author_account_id']
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
                                <label for="<?php echo sr_e($postStatusSelectId); ?>" class="sr-only">상태</label>
                                    <select id="<?php echo sr_e($postStatusSelectId); ?>" name="status" class="form-select">
                                        <?php foreach ($allowedPostStatuses as $status) { ?>
                                            <option value="<?php echo sr_e($status); ?>"<?php echo $status === (string) $post['status'] ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                        <?php } ?>
                                    </select>
                                <button type="submit" class="btn btn-sm btn-solid-light">변경</button>
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
<?php } ?>

<?php if ($communityPostsPage === 'comments') { ?>
<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/community/comments')); ?>" class="btn btn-solid-light">전체 보기</a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">총댓글 <strong><?php echo sr_e((string) $totalComments); ?>개</strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/community/comments?status=published')); ?>" class="admin-summary-meta">공개 <?php echo sr_e((string) ($commentStatusCounts['published'] ?? 0)); ?>개</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/comments?status=hidden')); ?>" class="admin-summary-meta">숨김 <?php echo sr_e((string) ($commentStatusCounts['hidden'] ?? 0)); ?>개</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/comments?status=deleted')); ?>" class="admin-summary-meta">삭제 <?php echo sr_e((string) ($commentStatusCounts['deleted'] ?? 0)); ?>개</a>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/community/comments')); ?>" class="admin-filter admin-community-comment-filter ui-form-theme">
    <div class="admin-filter-grid admin-community-comment-search-grid">
        <div class="admin-filter-field admin-community-comment-filter-status">
            <label for="community_admin_comments_status_filter" class="admin-filter-label">상태</label>
            <select id="community_admin_comments_status_filter" name="status" class="form-select admin-filter-input">
                <option value=""<?php echo (string) ($commentListFilters['status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                <?php foreach ($allowedCommentStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($commentListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-comment-filter-board">
            <label for="community_admin_comments_board_filter" class="admin-filter-label">게시판</label>
            <select id="community_admin_comments_board_filter" name="board_id" class="form-select admin-filter-input">
                <option value="0"<?php echo (int) ($commentListFilters['board_id'] ?? 0) === 0 ? ' selected' : ''; ?>>전체</option>
                <?php foreach ($postBoardOptions as $postBoardOption) { ?>
                    <option value="<?php echo sr_e((string) $postBoardOption['id']); ?>"<?php echo (int) ($commentListFilters['board_id'] ?? 0) === (int) $postBoardOption['id'] ? ' selected' : ''; ?>>
                        <?php echo sr_e((string) ($postBoardOption['board_group_title'] ?? '') !== '' ? (string) $postBoardOption['board_group_title'] . ' / ' . (string) $postBoardOption['title'] : (string) $postBoardOption['title']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-comment-filter-field">
            <label for="community_admin_comments_field" class="admin-filter-label">검색 조건</label>
            <select id="community_admin_comments_field" name="field" class="form-select admin-filter-input">
                <?php foreach (['all' => '전체', 'body' => '본문', 'author' => '작성자', 'post' => '게시글', 'board' => '게시판'] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($commentListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-comment-filter-keyword">
            <label for="community_admin_comments_q" class="admin-filter-label">검색어</label>
            <input id="community_admin_comments_q" type="search" name="q" value="<?php echo sr_e((string) ($commentListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="본문, 작성자, 게시글, 게시판">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title">댓글 목록</h2></div>
    <div class="table-wrapper">
    <table class="table admin-community-comment-table">
        <caption class="sr-only">커뮤니티 댓글 목록</caption>
        <thead class="ui-table-head">
            <tr>
                <th>ID</th>
                <th>게시글</th>
                <th>작성자</th>
                <th>본문</th>
                <th>상태</th>
                <th>작성일</th>
                <th class="text-end">처리</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($comments === []) { ?>
                <tr>
                    <td colspan="7" class="admin-empty-state">댓글이 없습니다.</td>
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
                        <td class="admin-table-nowrap community-id"><?php echo sr_e((string) $comment['id']); ?></td>
                        <td class="admin-table-break admin-community-comment-post-cell">
                            <a href="<?php echo sr_e(sr_url('/community/post?id=' . (string) $comment['post_id'])); ?>">
                                <?php echo sr_e((string) $comment['post_title']); ?>
                            </a>
                        </td>
                        <td class="admin-table-break admin-community-comment-author-cell"><?php echo sr_e(sr_community_report_account_label(
                            is_string($comment['author_display_name'] ?? null) ? $comment['author_display_name'] : null,
                            (int) $comment['author_account_id']
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
                                    <label for="<?php echo sr_e($commentStatusSelectId); ?>" class="sr-only">상태</label>
                                    <select id="<?php echo sr_e($commentStatusSelectId); ?>" name="status" class="form-select">
                                        <?php foreach ($allowedCommentStatuses as $status) { ?>
                                            <option value="<?php echo sr_e($status); ?>"<?php echo $status === (string) $comment['status'] ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-solid-light">변경</button>
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
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
