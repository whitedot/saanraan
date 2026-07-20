<?php

$pageTitle = sr_t('member::ui.login_mfa.title');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_member_skin_layout_context($memberSkinKey));
?>
    <main class="member-skin-basic-page member-skin-basic-page-narrow">
        <?php echo sr_member_feedback_toasts($notice, $errors); ?>
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>
            <div class="card-body member-skin-basic-stack">
                <p class="member-skin-basic-muted type-small"><?php echo sr_e(sr_t('member::ui.login_mfa.pending_help')); ?></p>
                <?php if (!empty($identityMfaStartUrl)) { ?>
                    <div class="alert alert-info">
                        <p><?php echo sr_e('본인인증으로 2차 인증을 완료할 수 있습니다.'); ?></p>
                        <p><a class="btn btn-sm btn-solid-primary" href="<?php echo sr_e((string) $identityMfaStartUrl); ?>"><?php echo sr_e('본인인증'); ?></a></p>
                    </div>
                <?php } ?>
                <form method="post" action="<?php echo sr_e(sr_url('/login/mfa')); ?>" class="member-skin-basic-form" data-sr-validate-form data-member-autofocus-form>
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="next" value="<?php echo sr_e($next); ?>">
                    <div class="member-skin-basic-field">
                        <label class="form-label" for="modules_member_login_mfa_code"><span><?php echo sr_e(sr_t('member::ui.login_mfa.code_or_backup')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span></label>
                        <input id="modules_member_login_mfa_code" type="text" name="code" value="" inputmode="text" autocomplete="one-time-code" required class="form-input form-control-compact">
                    </div>
                    <button type="submit" class="btn btn-solid-primary btn-block"><?php echo sr_e(sr_t('member::ui.login_mfa.submit')); ?></button>
                </form>
                <div class="member-skin-basic-link-row type-small">
                    <form method="post" action="<?php echo sr_e(sr_url('/logout')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <button type="submit" class="btn btn-outline-default btn-sm"><?php echo sr_e(sr_t('member::ui.logout')); ?></button>
                    </form>
                </div>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
