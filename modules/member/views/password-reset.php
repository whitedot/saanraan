<?php

$pageTitle = sr_t('member::ui.password.settings.a3d420e5');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'stylesheets' => sr_member_skin_stylesheets($memberSkinKey),
]);
?>
    <main class="member-skin-basic-page member-skin-basic-page-narrow">
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>
            <div class="card-body member-skin-basic-stack">

        <?php if ($notice !== '') { ?>
            <p class="member-skin-basic-muted type-small"><?php echo sr_e($notice); ?></p>
            <p><a class="btn btn-outline-default" href="<?php echo sr_e(sr_url('/login')); ?>"><?php echo sr_e(sr_t('member::ui.login.6d253673')); ?></a></p>
        <?php } else { ?>
            <?php if ($errors !== []) { ?>
                <div class="member-skin-basic-feedback badge-soft-danger type-small" role="alert">
                    <ul class="member-skin-basic-feedback-list">
                        <?php foreach ($errors as $error) { ?>
                            <li><?php echo sr_e($error); ?></li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>

            <form method="post" action="<?php echo sr_e(sr_url('/password/reset/confirm')); ?>" class="member-skin-basic-form">
                <?php echo sr_csrf_field(); ?>
                <p>
                    <label for="modules_member_password_reset_password">
                    <span><?php echo sr_e(sr_t('member::ui.password.04ea6283')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input class="form-input" id="modules_member_password_reset_password" type="password" name="password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_password_reset_password_confirm">
                    <span><?php echo sr_e(sr_t('member::ui.password.b1d91625')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input class="form-input" id="modules_member_password_reset_password_confirm" type="password" name="password_confirm" required>
                    </label>
                </p>
                <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.password.settings.2e9da95f')); ?></button>
            </form>
        <?php } ?>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
