<?php

$pageTitle = '새 비밀번호 설정';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
toy_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo toy_e($pageTitle); ?></h1>

        <?php if ($notice !== '') { ?>
            <p><?php echo toy_e($notice); ?></p>
            <p><a href="<?php echo toy_e(toy_url('/login')); ?>">로그인</a></p>
        <?php } else { ?>
            <?php if ($errors !== []) { ?>
                <ul>
                    <?php foreach ($errors as $error) { ?>
                        <li><?php echo toy_e($error); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>

            <form method="post" action="<?php echo toy_e(toy_url('/password/reset/confirm')); ?>">
                <?php echo toy_csrf_field(); ?>
                <p>
                    <label>새 비밀번호<br>
                        <input type="password" name="password" required>
                    </label>
                </p>
                <p>
                    <label>새 비밀번호 확인<br>
                        <input type="password" name="password_confirm" required>
                    </label>
                </p>
                <button type="submit">비밀번호 재설정</button>
            </form>
        <?php } ?>
    </main>
<?php toy_public_layout_end(); ?>
