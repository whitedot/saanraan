<?php

$pageTitle = '회원가입';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
toy_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo toy_e($pageTitle); ?></h1>

        <?php echo toy_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.register', 'slot_key' => 'before_form']); ?>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo toy_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <?php if ($registrationAllowed) { ?>
            <form method="post" action="<?php echo toy_e(toy_url('/register')); ?>">
                <?php echo toy_csrf_field(); ?>
                <p>
                    <label>이메일<br>
                        <input type="email" name="email" value="<?php echo toy_e($values['email']); ?>" required>
                    </label>
                </p>
                <?php if ($loginIdentifierMode === 'login_id') { ?>
                    <p>
                        <label>로그인 아이디<br>
                            <input type="text" name="login_id" value="<?php echo toy_e($values['login_id']); ?>" maxlength="40" pattern="[a-z][a-z0-9_]{3,39}" autocomplete="username" required>
                        </label>
                    </p>
                <?php } ?>
                <p>
                    <label>표시 이름<br>
                        <input type="text" name="display_name" value="<?php echo toy_e($values['display_name']); ?>" maxlength="120" required>
                    </label>
                </p>
                <p>
                    <label>비밀번호<br>
                        <input type="password" name="password" required>
                    </label>
                </p>
                <p>
                    <label>비밀번호 확인<br>
                        <input type="password" name="password_confirm" required>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="terms_consent" value="1" required>
                        필수 약관에 동의합니다.
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="privacy_consent" value="1" required>
                        개인정보 처리방침에 동의합니다.
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="marketing_consent" value="1"<?php echo $marketingConsent ? ' checked' : ''; ?>>
                        마케팅 수신에 동의합니다.
                    </label>
                </p>
                <button type="submit">가입</button>
            </form>
        <?php } else { ?>
            <p>현재 회원가입을 사용할 수 없습니다.</p>
        <?php } ?>
        <?php echo toy_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.register', 'slot_key' => 'after_form']); ?>

        <p><a href="<?php echo toy_e(toy_url('/login')); ?>">로그인</a></p>
    </main>
<?php toy_public_layout_end(); ?>
