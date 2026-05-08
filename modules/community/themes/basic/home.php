<?php

$pageTitle = '커뮤니티';
$seo = [
    'title' => $pageTitle,
    'canonical' => '/community',
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
            'point_key' => 'community.home',
            'slot_key' => 'before_content',
        ]); ?>

        <h1><?php echo toy_e($pageTitle); ?></h1>

        <?php if (is_array($account)) { ?>
            <p>
                <a href="<?php echo toy_e(toy_url('/community/scraps')); ?>">내 스크랩</a>
                /
                <a href="<?php echo toy_e(toy_url('/community/messages')); ?>">쪽지함</a>
            </p>
        <?php } ?>

        <?php if ($boards === []) { ?>
            <p>게시판이 없습니다.</p>
        <?php } else { ?>
            <ul>
                <?php foreach ($boards as $board) { ?>
                    <li>
                        <a href="<?php echo toy_e(toy_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>">
                            <?php echo toy_e((string) $board['title']); ?>
                        </a>
                        <?php if ((string) ($board['description'] ?? '') !== '') { ?>
                            <br><?php echo toy_e((string) $board['description']); ?>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>

        <?php echo toy_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.home',
            'slot_key' => 'after_content',
        ]); ?>
    </main>
</body>
</html>
