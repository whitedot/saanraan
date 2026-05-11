<?php

$pageTitle = '로그인';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$identifierLabel = ((string) ($memberSettings['login_identifier'] ?? 'email') === 'login_id') ? '아이디 또는 이메일' : '이메일 또는 아이디';
toy_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo toy_e($pageTitle); ?></h1>

        <?php echo toy_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.login', 'slot_key' => 'before_form']); ?>

        <?php if ($notice !== '') { ?>
            <p><?php echo toy_e($notice); ?></p>
        <?php } ?>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo toy_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <form method="post" action="<?php echo toy_e(toy_url('/login')); ?>">
            <?php echo toy_csrf_field(); ?>
            <input type="hidden" name="next" value="<?php echo toy_e($next); ?>">
            <p>
                <label><?php echo toy_e($identifierLabel); ?><br>
                    <input type="text" name="identifier" value="<?php echo toy_e($identifier); ?>" autocomplete="username" required>
                </label>
            </p>
            <p>
                <label>비밀번호<br>
                    <input type="password" name="password" required>
                </label>
            </p>
            <button type="submit">로그인</button>
        </form>
        <?php echo toy_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.login', 'slot_key' => 'after_form']); ?>

        <p><a href="<?php echo toy_e(toy_url('/register')); ?>">회원가입</a></p>
        <p><a href="<?php echo toy_e(toy_url('/password/reset')); ?>">비밀번호 재설정</a></p>
    </main>
<?php toy_public_layout_end(); ?>
