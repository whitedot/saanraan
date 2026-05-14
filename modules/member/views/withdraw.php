<?php

$pageTitle = '회원 탈퇴';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo sr_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <form method="post" action="<?php echo sr_e(sr_url('/account/withdraw')); ?>">
            <?php echo sr_csrf_field(); ?>
            <p>
                <label>
                    <span>비밀번호</span>
                    <input type="password" name="password" required>
                </label>
            </p>
            <p>
                <label>
                    <span>확인 문구</span>
                    <input type="text" name="confirm_text" required>
                </label>
            </p>
            <button type="submit">탈퇴</button>
        </form>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>">내 계정</a></p>
    </main>
<?php sr_public_layout_end(); ?>
