<?php

$pageTitle = sr_t('member::ui.login.6d253673');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$identifierLabel = sr_t('member::ui.email.95b727cb');
$loginSiteName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'style_profile' => 'kit',
]);
?>
    <main class="public-ui-scope member-login-public">
        <section class="public-ui-form-panel">
            <h1 class="public-ui-title type-section-title"><?php echo sr_e($pageTitle); ?></h1>
            <p class="public-ui-copy type-body"><?php echo sr_e($loginSiteName . ' 계정으로 계속 진행합니다.'); ?></p>

            <?php echo sr_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.login', 'slot_key' => 'before_form']); ?>

            <?php if ($notice !== '') { ?>
                <p class="public-ui-feedback type-small"><?php echo sr_e($notice); ?></p>
            <?php } ?>

            <?php if ($errors !== []) { ?>
                <ul class="public-ui-feedback public-ui-feedback-error type-small">
                    <?php foreach ($errors as $error) { ?>
                        <li><?php echo sr_e($error); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>

            <form method="post" action="<?php echo sr_e(sr_url('/login')); ?>" class="public-ui-content-list">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="next" value="<?php echo sr_e($next); ?>">
                <label class="public-ui-field" for="modules_member_login_identifier">
                    <span><?php echo sr_e($identifierLabel); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                    <input id="modules_member_login_identifier" type="text" name="identifier" value="<?php echo sr_e($identifier); ?>" autocomplete="username" required class="public-ui-input">
                </label>
                <label class="public-ui-field" for="modules_member_login_password">
                    <span><?php echo sr_e(sr_t('member::ui.password.4fa210a0')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                    <input id="modules_member_login_password" type="password" name="password" required class="public-ui-input">
                </label>
                <button type="submit" class="public-ui-button"><?php echo sr_e(sr_t('member::ui.login.6d253673')); ?></button>
            </form>
            <?php
            $oauthProviders = [];
            if (isset($pdo) && $pdo instanceof PDO && sr_module_enabled($pdo, 'member_oauth') && is_file(SR_ROOT . '/modules/member_oauth/helpers.php')) {
                require_once SR_ROOT . '/modules/member_oauth/helpers.php';
                $oauthProviders = sr_member_oauth_public_providers($pdo);
            }
            ?>
            <?php if ($oauthProviders !== []) { ?>
                <div class="public-ui-content-list">
                    <?php foreach ($oauthProviders as $oauthProvider) { ?>
                        <a class="public-ui-button public-ui-button-secondary" href="<?php echo sr_e(sr_url('/oauth/start?provider=' . rawurlencode((string) $oauthProvider['provider_key']) . '&next=' . rawurlencode($next))); ?>">
                            <?php echo sr_e((string) $oauthProvider['label']); ?>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php echo sr_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.login', 'slot_key' => 'after_form']); ?>

            <div class="public-ui-link-row">
                <a href="<?php echo sr_e(sr_url('/register')); ?>"><?php echo sr_e(sr_t('member::ui.member.e668cc2b')); ?></a>
                <a href="<?php echo sr_e(sr_url('/password/reset')); ?>"><?php echo sr_e(sr_t('member::ui.password.settings.2e9da95f')); ?></a>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
