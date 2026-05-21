<?php

$pageTitle = '새 비밀번호 설정';
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
            <p><a href="<?php echo sr_e(sr_url('/login')); ?>">로그인</a></p>
        <?php } else { ?>
            <?php if ($errors !== []) { ?>
                <ul>
                    <?php foreach ($errors as $error) { ?>
                        <li><?php echo sr_e($error); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>

            <form method="post" action="<?php echo sr_e(sr_url('/password/reset/confirm')); ?>">
                <?php echo sr_csrf_field(); ?>
                <p>
                    <label for="modules_member_password_reset_password">
                    <span>새 비밀번호</span>
                        <input id="modules_member_password_reset_password" type="password" name="password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_password_reset_password_confirm">
                    <span>새 비밀번호 확인</span>
                        <input id="modules_member_password_reset_password_confirm" type="password" name="password_confirm" required>
                    </label>
                </p>
                <button type="submit">비밀번호 재설정</button>
            </form>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
