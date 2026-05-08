<?php

$pageTitle = '쪽지 쓰기';
$seo = [
    'title' => $pageTitle,
    'canonical' => '/community/message/write',
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
        <p>
            <a href="<?php echo toy_e(toy_url('/community')); ?>">커뮤니티</a>
            /
            <a href="<?php echo toy_e(toy_url('/community/messages')); ?>">쪽지함</a>
        </p>
        <h1><?php echo toy_e($pageTitle); ?></h1>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo toy_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <form method="post" action="<?php echo toy_e(toy_url('/community/message/write')); ?>">
            <?php echo toy_csrf_field(); ?>
            <p>
                <label>받는 회원 이메일 또는 아이디<br>
                    <input type="text" name="recipient_identifier" value="<?php echo toy_e(is_string($values['recipient_identifier']) ? $values['recipient_identifier'] : ''); ?>" maxlength="255" required>
                </label>
            </p>
            <p>
                <label>내용<br>
                    <textarea name="body_text" rows="10" cols="80" required><?php echo toy_e(is_string($values['body_text']) ? $values['body_text'] : ''); ?></textarea>
                </label>
            </p>
            <button type="submit">보내기</button>
        </form>
    </main>
</body>
</html>
