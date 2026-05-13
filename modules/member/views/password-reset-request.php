<?php

$pageTitle = '비밀번호 재설정';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>

        <?php if ($notice !== '') { ?>
            <p><?php echo sr_e($notice); ?></p>
        <?php } ?>

        <?php if ($resetUrl !== '' && $showResetUrl) { ?>
            <p><a href="<?php echo sr_e($resetUrl); ?>">재설정 링크</a></p>
        <?php } ?>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo sr_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <form method="post" action="<?php echo sr_e(sr_url('/password/reset')); ?>">
            <?php echo sr_csrf_field(); ?>
            <p>
                <label>이메일<br>
                    <input type="email" name="email" value="<?php echo sr_e($email); ?>" required>
                </label>
            </p>
            <button type="submit">재설정 요청</button>
        </form>
        <p><a href="<?php echo sr_e(sr_url('/login')); ?>">로그인</a></p>
    </main>
<?php sr_public_layout_end(); ?>
