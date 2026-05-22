<?php

$pageTitle = sr_t('member::ui.password.settings.a3d420e5');
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
            <p><a href="<?php echo sr_e(sr_url('/login')); ?>"><?php echo sr_e(sr_t('member::ui.login.6d253673')); ?></a></p>
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
                    <span><?php echo sr_e(sr_t('member::ui.password.04ea6283')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_member_password_reset_password" type="password" name="password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_password_reset_password_confirm">
                    <span><?php echo sr_e(sr_t('member::ui.password.b1d91625')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_member_password_reset_password_confirm" type="password" name="password_confirm" required>
                    </label>
                </p>
                <button type="submit"><?php echo sr_e(sr_t('member::ui.password.settings.2e9da95f')); ?></button>
            </form>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
