<?php

$pageTitle = (string) $board['title'];
$baseListPath = '/community/board?key=' . rawurlencode((string) $board['board_key']) . ($keyword !== '' ? '&q=' . rawurlencode($keyword) : '');
$seo = [
    'title' => $pageTitle,
    'description' => (string) ($board['description'] ?? ''),
    'canonical' => $baseListPath . ($page > 1 ? '&page=' . (string) $page : ''),
    'robots' => (string) ($board['effective_read_policy'] ?? $board['read_policy']) !== 'public'
        ? 'noindex, nofollow'
        : ($keyword === '' ? 'index, follow' : 'noindex, follow'),
];
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'stylesheets' => sr_community_skin_stylesheets($skinKey ?? 'basic'),
]);
?>
    <main>
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

        <p><a href="<?php echo sr_e(sr_url('/community')); ?>">커뮤니티</a></p>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <?php if ((string) ($board['description'] ?? '') !== '') { ?>
            <p><?php echo sr_e((string) $board['description']); ?></p>
        <?php } ?>

        <?php if ($boardNotice !== '') { ?>
            <p><?php echo sr_e($boardNotice); ?></p>
        <?php } ?>

        <?php if ($canWriteBoard) { ?>
            <p>
                <a href="<?php echo sr_e(sr_url('/community/write?key=' . rawurlencode((string) $board['board_key']))); ?>">글쓰기</a>
            </p>
        <?php } ?>

        <form method="get" action="<?php echo sr_e(sr_url('/community/board')); ?>">
            <input type="hidden" name="key" value="<?php echo sr_e((string) $board['board_key']); ?>">
            <p>
                <label for="modules_community_list_q">
                    <span>검색</span>
                    <input id="modules_community_list_q" type="search" name="q" maxlength="100" value="<?php echo sr_e($keyword); ?>">
                </label>
                <button type="submit">검색</button>
                <?php if ($keyword !== '') { ?>
                    <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>">초기화</a>
                <?php } ?>
            </p>
        </form>

        <?php if ($posts === []) { ?>
            <p><?php echo $keyword !== '' ? '검색 결과가 없습니다.' : '게시글이 없습니다.'; ?></p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th>제목</th>
                        <th>작성자</th>
                        <th>작성일</th>
                        <th>댓글</th>
                        <th>첨부</th>
                        <th>조회</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post) { ?>
                        <tr>
                            <td>
                                <a href="<?php echo sr_e(sr_url('/community/post?id=' . (string) $post['id'])); ?>">
                                    <?php echo sr_e((string) $post['title']); ?>
                                </a>
                            </td>
                            <td><?php echo sr_e(sr_community_public_author_label($pdo, (int) $post['author_account_id'], $canViewMemberIdentifiers, $config)); ?></td>
                            <td><?php echo sr_e((string) $post['created_at']); ?></td>
                            <td><?php echo sr_e((string) ($post['published_comment_count'] ?? 0)); ?></td>
                            <td><?php echo sr_e((string) ($post['active_attachment_count'] ?? 0)); ?></td>
                            <td><?php echo sr_e((string) $post['view_count']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>

        <?php if ($totalPages > 1) { ?>
            <nav aria-label="게시글 페이지">
                <p>
                    <?php if ($page > 1) { ?>
                        <a href="<?php echo sr_e(sr_url($baseListPath . '&page=' . (string) ($page - 1))); ?>">이전</a>
                    <?php } ?>
                    <?php echo sr_e((string) $page); ?> / <?php echo sr_e((string) $totalPages); ?>
                    <?php if ($page < $totalPages) { ?>
                        <a href="<?php echo sr_e(sr_url($baseListPath . '&page=' . (string) ($page + 1))); ?>">다음</a>
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
    </main>
<?php sr_public_layout_end(); ?>
