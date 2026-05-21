<?php

$pageTitle = '로그인';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$identifierLabel = sr_member_email_login_enabled($memberSettings ?? []) ? '이메일 또는 아이디' : '아이디';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main class="public-ui-scope member-login-public">
        <section class="public-ui-form-panel">
            <h1 class="public-ui-title"><?php echo sr_e($pageTitle); ?></h1>
            <p class="public-ui-copy">Saanraan 계정으로 계속 진행합니다.</p>

            <?php echo sr_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.login', 'slot_key' => 'before_form']); ?>

            <?php if ($notice !== '') { ?>
                <p class="public-ui-feedback"><?php echo sr_e($notice); ?></p>
            <?php } ?>

            <?php if ($errors !== []) { ?>
                <ul class="public-ui-feedback-error">
                    <?php foreach ($errors as $error) { ?>
                        <li><?php echo sr_e($error); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>

            <form method="post" action="<?php echo sr_e(sr_url('/login')); ?>" class="public-ui-content-list">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="next" value="<?php echo sr_e($next); ?>">
                <label class="public-ui-field" for="modules_member_login_identifier">
                    <span><?php echo sr_e($identifierLabel); ?></span>
                    <input id="modules_member_login_identifier" type="text" name="identifier" value="<?php echo sr_e($identifier); ?>" autocomplete="username" required class="public-ui-input">
                </label>
                <label class="public-ui-field" for="modules_member_login_password">
                    <span>비밀번호</span>
                    <input id="modules_member_login_password" type="password" name="password" required class="public-ui-input">
                </label>
                <button type="submit" class="public-ui-button">로그인</button>
            </form>
            <?php echo sr_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.login', 'slot_key' => 'after_form']); ?>

            <div class="public-ui-link-row">
                <a href="<?php echo sr_e(sr_url('/register')); ?>">회원가입</a>
                <a href="<?php echo sr_e(sr_url('/password/reset')); ?>">비밀번호 재설정</a>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
