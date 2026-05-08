<?php

$pageTitle = (string) $board['title'];
$seo = [
    'title' => $pageTitle,
    'description' => (string) ($board['description'] ?? ''),
    'canonical' => '/community/board?key=' . rawurlencode((string) $board['board_key']),
    'robots' => (string) $board['read_policy'] === 'public' ? 'index, follow' : 'noindex, nofollow',
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

        <p>
            <a href="<?php echo toy_e(toy_url('/community/write?key=' . rawurlencode((string) $board['board_key']))); ?>">글쓰기</a>
        </p>

        <?php if ($posts === []) { ?>
            <p>게시글이 없습니다.</p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th>제목</th>
                        <th>작성자</th>
                        <th>작성일</th>
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
                            <td><?php echo toy_e((string) $post['view_count']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
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
