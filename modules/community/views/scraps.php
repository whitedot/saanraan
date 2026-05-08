<?php

$pageTitle = '내 스크랩';
$seo = [
    'title' => $pageTitle,
    'canonical' => '/community/scraps',
    'robots' => 'noindex, nofollow',
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
        <p><a href="<?php echo toy_e(toy_url('/community')); ?>">커뮤니티</a></p>
        <h1><?php echo toy_e($pageTitle); ?></h1>

        <?php if ($scraps === []) { ?>
            <p>스크랩한 게시글이 없습니다.</p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th>게시판</th>
                        <th>제목</th>
                        <th>스크랩일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scraps as $scrap) { ?>
                        <tr>
                            <td><?php echo toy_e((string) ($scrap['board_title'] ?? '')); ?></td>
                            <td>
                                <?php if (toy_community_scrap_row_is_public($scrap)) { ?>
                                    <a href="<?php echo toy_e(toy_url('/community/post?id=' . (string) $scrap['post_id'])); ?>">
                                        <?php echo toy_e((string) $scrap['title']); ?>
                                    </a>
                                <?php } else { ?>
                                    비공개 또는 삭제된 게시글
                                <?php } ?>
                            </td>
                            <td><?php echo toy_e((string) $scrap['created_at']); ?></td>
                            <td>
                                <form method="post" action="<?php echo toy_e(toy_url('/community/scrap')); ?>">
                                    <?php echo toy_csrf_field(); ?>
                                    <input type="hidden" name="post_id" value="<?php echo toy_e((string) $scrap['post_id']); ?>">
                                    <input type="hidden" name="intent" value="remove">
                                    <button type="submit">해제</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </main>
</body>
</html>
