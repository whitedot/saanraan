<?php

$pageTitle = '회원 탈퇴';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
toy_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo toy_e($pageTitle); ?></h1>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo toy_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <form method="post" action="<?php echo toy_e(toy_url('/account/withdraw')); ?>">
            <?php echo toy_csrf_field(); ?>
            <p>
                <label>비밀번호<br>
                    <input type="password" name="password" required>
                </label>
            </p>
            <p>
                <label>확인 문구<br>
                    <input type="text" name="confirm_text" required>
                </label>
            </p>
            <button type="submit">탈퇴</button>
        </form>
        <p><a href="<?php echo toy_e(toy_url('/account')); ?>">내 계정</a></p>
    </main>
<?php toy_public_layout_end(); ?>
