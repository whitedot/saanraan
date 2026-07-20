<?php

$pageTitle = sr_t('member::ui.login.6d253673');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$identifierLabel = sr_t('member::ui.email.95b727cb');
$loginSiteName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
$loginMemberOnlyEnabled = is_array($site ?? null) && !empty($site['member_only_enabled']);
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_member_skin_layout_context($memberSkinKey, [
    'output_slots' => [
        ['module_key' => 'member', 'point_key' => 'member.login', 'slot_key' => 'before_form'],
        ['module_key' => 'member', 'point_key' => 'member.login', 'slot_key' => 'after_form'],
    ],
]));
?>
    <main class="member-skin-basic-page member-skin-basic-page-narrow">
        <?php echo sr_member_feedback_toasts($notice, $errors); ?>
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>
            <div class="card-body member-skin-basic-stack">
                <p class="member-skin-basic-muted type-small"><?php echo sr_e($loginSiteName . ' 계정으로 계속 진행합니다.'); ?></p>
                <?php if ($loginMemberOnlyEnabled) { ?>
                    <div class="alert alert-info">
                        <?php echo sr_e('회원 전용 사이트입니다. 로그인 후 이용할 수 있습니다.'); ?>
                    </div>
                <?php } ?>

                <?php echo sr_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.login', 'slot_key' => 'before_form']); ?>

                <form method="post" action="<?php echo sr_e(sr_url('/login')); ?>" class="member-skin-basic-form" data-sr-validate-form data-member-autofocus-form>
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="next" value="<?php echo sr_e($next); ?>">
                    <div class="member-skin-basic-field">
                        <label class="form-label" for="modules_member_login_identifier"><span><?php echo sr_e($identifierLabel); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span></label>
                        <input id="modules_member_login_identifier" type="text" name="identifier" value="<?php echo sr_e($identifier); ?>" autocomplete="username" required class="form-input form-control-short">
                    </div>
                    <div class="member-skin-basic-field">
                        <label class="form-label" for="modules_member_login_password"><span><?php echo sr_e(sr_t('member::ui.password.4fa210a0')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span></label>
                        <input id="modules_member_login_password" type="password" name="password" required class="form-input form-control-short">
                    </div>
                    <button type="submit" class="btn btn-solid-primary btn-block"><?php echo sr_e(sr_t('member::ui.login.6d253673')); ?></button>
                </form>
                <?php
                $oauthProviders = [];
                if (isset($pdo) && $pdo instanceof PDO && sr_module_enabled($pdo, 'member_oauth') && is_file(SR_ROOT . '/modules/member_oauth/helpers.php')) {
                    require_once SR_ROOT . '/modules/member_oauth/helpers.php';
                    $oauthProviders = sr_member_oauth_public_providers($pdo);
                }
                ?>
                <?php if ($oauthProviders !== []) { ?>
                    <div class="member-skin-basic-form">
                        <?php foreach ($oauthProviders as $oauthProvider) { ?>
                            <a class="btn btn-outline-default btn-block" href="<?php echo sr_e(sr_url('/oauth/start?provider=' . rawurlencode((string) $oauthProvider['provider_key']) . '&next=' . rawurlencode($next))); ?>">
                                <?php echo sr_e((string) $oauthProvider['label']); ?>
                            </a>
                        <?php } ?>
                    </div>
                <?php } ?>
                <?php echo sr_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.login', 'slot_key' => 'after_form']); ?>

                <div class="member-skin-basic-link-row type-small">
                    <a class="member-skin-basic-link-action" href="<?php echo sr_e(sr_url('/register')); ?>"><?php echo sr_e(sr_t('member::ui.member.e668cc2b')); ?></a>
                    <a class="member-skin-basic-link-action" href="<?php echo sr_e(sr_url('/password/reset')); ?>"><?php echo sr_e(sr_t('member::ui.password.settings.2e9da95f')); ?></a>
                </div>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
