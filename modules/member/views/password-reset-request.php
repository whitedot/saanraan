<?php

$pageTitle = sr_t('member::ui.password.settings.2e9da95f');
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
            <p><a href="<?php echo sr_e($resetUrl); ?>"><?php echo sr_e(sr_t('member::ui.settings.44dd1586')); ?></a></p>
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
                <label for="modules_member_password_reset_request_email">
                    <span><?php echo sr_e(sr_t('member::ui.email.3b7dbc4c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                    <input id="modules_member_password_reset_request_email" type="email" name="email" value="<?php echo sr_e($email); ?>" required>
                </label>
            </p>
            <button type="submit"><?php echo sr_e(sr_t('member::ui.settings.845064c7')); ?></button>
        </form>
        <p><a href="<?php echo sr_e(sr_url('/login')); ?>"><?php echo sr_e(sr_t('member::ui.login.6d253673')); ?></a></p>
    </main>
<?php sr_public_layout_end(); ?>
