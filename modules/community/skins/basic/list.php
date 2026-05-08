<?php

$pageTitle = (string) $board['title'];
$baseListPath = '/community/board?key=' . rawurlencode((string) $board['board_key']) . ($keyword !== '' ? '&q=' . rawurlencode($keyword) : '');
$seo = [
    'title' => $pageTitle,
    'description' => (string) ($board['description'] ?? ''),
    'canonical' => $baseListPath . ($page > 1 ? '&page=' . (string) $page : ''),
    'robots' => (string) $board['read_policy'] !== 'public'
        ? 'noindex, nofollow'
        : ($keyword === '' ? 'index, follow' : 'noindex, follow'),
];
?>
<!doctype html>
<html lang="<?php echo toy_e(toy_locale()); ?>">
<head>
    <meta charset="utf-8">
    <?php echo toy_seo_tags($seo, $site ?? null); ?>
    <?php echo toy_stylesheet_tag(); ?>
</head>
<body>
    <main>
        <?php echo toy_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.board.list',
            'slot_key' => 'before_list',
            'subject_id' => (string) $board['id'],
        ]); ?>

        <p><a href="<?php echo toy_e(toy_url('/community')); ?>">커뮤니티</a></p>
        <h1><?php echo toy_e($pageTitle); ?></h1>
        <?php if ((string) ($board['description'] ?? '') !== '') { ?>
            <p><?php echo toy_e((string) $board['description']); ?></p>
        <?php } ?>

        <?php if ($boardNotice !== '') { ?>
            <p><?php echo toy_e($boardNotice); ?></p>
        <?php } ?>

        <p>
            <a href="<?php echo toy_e(toy_url('/community/write?key=' . rawurlencode((string) $board['board_key']))); ?>">글쓰기</a>
        </p>

        <form method="get" action="<?php echo toy_e(toy_url('/community/board')); ?>">
            <input type="hidden" name="key" value="<?php echo toy_e((string) $board['board_key']); ?>">
            <p>
                <label>검색<br>
                    <input type="search" name="q" maxlength="100" value="<?php echo toy_e($keyword); ?>">
                </label>
                <button type="submit">검색</button>
                <?php if ($keyword !== '') { ?>
                    <a href="<?php echo toy_e(toy_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>">초기화</a>
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
                        <th>조회</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post) { ?>
                        <tr>
                            <td>
                                <a href="<?php echo toy_e(toy_url('/community/post?id=' . (string) $post['id'])); ?>">
                                    <?php echo toy_e((string) $post['title']); ?>
                                </a>
                            </td>
                            <td><?php echo toy_e(toy_community_public_author_label($pdo, (int) $post['author_account_id'])); ?></td>
                            <td><?php echo toy_e((string) $post['created_at']); ?></td>
                            <td><?php echo toy_e((string) ($post['published_comment_count'] ?? 0)); ?></td>
                            <td><?php echo toy_e((string) $post['view_count']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>

        <?php if ($totalPages > 1) { ?>
            <nav aria-label="게시글 페이지">
                <p>
                    <?php if ($page > 1) { ?>
                        <a href="<?php echo toy_e(toy_url($baseListPath . '&page=' . (string) ($page - 1))); ?>">이전</a>
                    <?php } ?>
                    <?php echo toy_e((string) $page); ?> / <?php echo toy_e((string) $totalPages); ?>
                    <?php if ($page < $totalPages) { ?>
                        <a href="<?php echo toy_e(toy_url($baseListPath . '&page=' . (string) ($page + 1))); ?>">다음</a>
                    <?php } ?>
                </p>
            </nav>
        <?php } ?>

        <?php echo toy_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.board.list',
            'slot_key' => 'after_list',
            'subject_id' => (string) $board['id'],
        ]); ?>
    </main>
</body>
</html>
