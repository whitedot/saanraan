<?php

$pageTitle = '내 스크랩';
$seo = [
    'title' => $pageTitle,
    'canonical' => '/community/scraps',
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <p><a href="<?php echo sr_e(sr_url('/community')); ?>">커뮤니티</a></p>
        <h1><?php echo sr_e($pageTitle); ?></h1>

        <?php if ($notice !== '') { ?>
            <p><?php echo sr_e($notice); ?></p>
        <?php } ?>

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
                            <td>
                                <?php if (sr_community_scrap_row_can_view($scrap)) { ?>
                                    <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $scrap['board_key']))); ?>">
                                        <?php echo sr_e((string) ($scrap['board_title'] ?? '')); ?>
                                    </a>
                                <?php } else { ?>
                                    비공개 또는 삭제된 게시판
                                <?php } ?>
                            </td>
                            <td>
                                <?php if (sr_community_scrap_row_can_view($scrap)) { ?>
                                    <a href="<?php echo sr_e(sr_url('/community/post?id=' . (string) $scrap['post_id'])); ?>">
                                        <?php echo sr_e((string) $scrap['title']); ?>
                                    </a>
                                <?php } else { ?>
                                    비공개 또는 삭제된 게시글
                                <?php } ?>
                            </td>
                            <td><?php echo sr_e((string) $scrap['created_at']); ?></td>
                            <td>
                                <form method="post" action="<?php echo sr_e(sr_url('/community/scrap')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $scrap['post_id']); ?>">
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
<?php sr_public_layout_end(); ?>
