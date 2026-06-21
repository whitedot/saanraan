<?php

$pageTitle = sr_t('member::ui.text.13b28045');
$memberAccountPath = isset($memberAccountPath) && is_string($memberAccountPath) && $memberAccountPath === '/mypage' ? '/mypage' : '/account';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_member_skin_layout_context($memberSkinKey));
?>
    <main class="member-skin-basic-page member-skin-basic-page-wide">
        <?php echo sr_member_feedback_toasts($notice, $errors); ?>
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>
            <div class="card-body">
                <dl class="member-skin-basic-description">
                    <dt><?php echo sr_e(sr_t('member::ui.email.3b7dbc4c')); ?></dt>
                    <dd><?php echo sr_e((string) $account['email']); ?></dd>
                    <dt><?php echo sr_e(sr_t('member::ui.name.be0cd9bd')); ?></dt>
                    <dd><?php echo sr_e((string) $account['display_name']); ?></dd>
                    <?php if (!empty($memberSettings['nickname_enabled'])) { ?>
                        <dt><?php echo sr_e(sr_t('member::ui.nickname')); ?></dt>
                        <dd><?php echo sr_e((string) ($account['nickname'] ?? '')); ?></dd>
                    <?php } ?>
                    <dt><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></dt>
                    <dd><?php echo sr_e(sr_member_account_status_label((string) $account['status'])); ?></dd>
                    <dt><?php echo sr_e(sr_t('member::ui.email.2f905abd')); ?></dt>
                    <dd><?php echo sr_e($account['email_verified_at'] === null ? sr_t('member::ui.text.a7800e5d') : (string) $account['email_verified_at']); ?></dd>
                </dl>
            </div>
        </section>

        <section class="card member-skin-basic-stack member-skin-basic-padded-card">
            <h2 class="card-title member-skin-basic-card-title"><?php echo sr_e(sr_t('member::ui.text.25914f73')); ?></h2>
            <form method="post" action="<?php echo sr_e(sr_url($memberAccountPath)); ?>" class="member-skin-basic-form" data-sr-validate-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="basics">
                <p>
                    <label for="modules_member_account_display_name">
                    <span><?php echo sr_e(sr_t('member::ui.name.be0cd9bd')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input class="form-input" id="modules_member_account_display_name" type="text" name="display_name" value="<?php echo sr_e((string) $account['display_name']); ?>" maxlength="120" required>
                    </label>
                </p>
                <?php if (!empty($memberSettings['nickname_enabled'])) { ?>
                    <p>
                        <label for="modules_member_account_nickname">
                        <span><?php echo sr_e(sr_t('member::ui.nickname')); ?><?php echo !empty($memberSettings['nickname_required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                            <input class="form-input" id="modules_member_account_nickname" type="text" name="nickname" value="<?php echo sr_e((string) ($account['nickname'] ?? '')); ?>" maxlength="80"<?php echo !empty($memberSettings['nickname_required']) ? ' required' : ''; ?>>
                        </label>
                        <small><?php echo sr_e(sr_t('member::ui.nickname.help')); ?></small>
                    </p>
                <?php } ?>
                <p>
                    <label for="modules_member_account_locale">
                    <span><?php echo sr_e(sr_t('member::ui.locale.2deb1d6f')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <select class="form-select" id="modules_member_account_locale" name="locale" required>
                            <?php foreach ($memberLocaleOptions as $localeOption) { ?>
                                <option value="<?php echo sr_e($localeOption); ?>"<?php echo (string) $account['locale'] === $localeOption ? ' selected' : ''; ?>>
                                    <?php echo sr_e($localeOption); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                </p>
                <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.save.be6da0db')); ?></button>
            </form>
        </section>

        <?php if ($emailVerificationEnabled) { ?>
            <section class="card member-skin-basic-stack member-skin-basic-padded-card">
                <h2 class="card-title member-skin-basic-card-title"><?php echo sr_e(sr_t('member::ui.email.2f905abd')); ?></h2>
                <?php if ($account['email_verified_at'] === null) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/account/email-verification')); ?>" class="member-skin-basic-form" data-sr-validate-form>
                        <?php echo sr_csrf_field(); ?>
                        <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.text.9938eea0')); ?></button>
                    </form>
                    <?php if ($emailVerificationUrl !== '') { ?>
                        <p><a href="<?php echo sr_e($emailVerificationUrl); ?>"><?php echo sr_e(sr_t('member::ui.email.849a4197')); ?></a></p>
                    <?php } ?>
                <?php } else { ?>
                    <p><?php echo sr_e(sr_t('member::ui.email.f038feee')); ?></p>
                <?php } ?>
            </section>
        <?php } ?>

        <section class="card member-skin-basic-stack member-skin-basic-padded-card">
            <h2 class="card-title member-skin-basic-card-title"><?php echo sr_e(sr_t('member::ui.password.bf1d4719')); ?></h2>
            <form method="post" action="<?php echo sr_e(sr_url($memberAccountPath)); ?>" class="member-skin-basic-form" data-sr-validate-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="password">
                <p>
                    <label for="modules_member_account_current_password">
                    <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input class="form-input" id="modules_member_account_current_password" type="password" name="current_password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_account_new_password">
                    <span><?php echo sr_e(sr_t('member::ui.password.04ea6283')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input class="form-input" id="modules_member_account_new_password" type="password" name="new_password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_account_new_password_confirm">
                    <span><?php echo sr_e(sr_t('member::ui.password.b1d91625')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input class="form-input" id="modules_member_account_new_password_confirm" type="password" name="new_password_confirm" required>
                    </label>
                </p>
                <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.password.bf1d4719')); ?></button>
            </form>
        </section>

        <?php if (!empty($oauthProviders)) { ?>
            <section class="card member-skin-basic-stack member-skin-basic-padded-card">
                <h2 class="card-title member-skin-basic-card-title">소셜 로그인</h2>
                <?php if (!empty($oauthAccounts)) { ?>
                    <dl>
                        <?php foreach ($oauthAccounts as $oauthAccount) { ?>
                            <dt><?php echo sr_e((string) $oauthAccount['provider_key']); ?></dt>
                            <dd>
                                <?php echo sr_e((string) $oauthAccount['provider_subject_display']); ?>
                                <?php if (!empty($oauthAccount['last_login_at'])) { ?>
                                    <span><?php echo sr_e((string) $oauthAccount['last_login_at']); ?></span>
                                <?php } ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/account/oauth/unlink')); ?>" class="member-skin-basic-form" data-sr-validate-form>
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="oauth_account_id" value="<?php echo sr_e((string) $oauthAccount['id']); ?>">
                                    <button class="btn btn-solid-primary" type="submit"<?php echo $oauthCanUnlink ? '' : ' disabled'; ?>>연결 해제</button>
                                </form>
                            </dd>
                        <?php } ?>
                    </dl>
                <?php } ?>
                <p>
                    <?php foreach ($oauthProviders as $oauthProvider) { ?>
                        <a href="<?php echo sr_e(sr_url('/oauth/start?provider=' . rawurlencode((string) $oauthProvider['provider_key']) . '&flow=link&next=' . rawurlencode($memberAccountPath))); ?>">
                            <?php echo sr_e((string) $oauthProvider['label']); ?> 연결
                        </a>
                    <?php } ?>
                </p>
            </section>
        <?php } ?>

        <?php if ($profileFieldsEnabled) { ?>
            <section class="card member-skin-basic-stack member-skin-basic-padded-card">
                <h2 class="card-title member-skin-basic-card-title"><?php echo sr_e(sr_t('member::ui.select.2ea79f04')); ?></h2>
                <form method="post" action="<?php echo sr_e(sr_url($memberAccountPath)); ?>" class="member-skin-basic-form" data-sr-validate-form<?php echo !empty($profilePolicies['avatar_path']['visible']) ? ' enctype="multipart/form-data"' : ''; ?>>
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="profile">
                    <?php if (!empty($profilePolicies['phone']['visible'])) { ?>
                        <p>
                            <label for="modules_member_account_phone">
                                <span><?php echo sr_e(sr_t('member::ui.text.4edc9439')); ?><?php echo !empty($profilePolicies['phone']['required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                <input class="form-input" id="modules_member_account_phone" type="text" name="phone" value="<?php echo sr_e($profile['phone']); ?>" maxlength="40"<?php echo !empty($profilePolicies['phone']['required']) ? ' required' : ''; ?>>
                            </label>
                        </p>
                    <?php } ?>
                    <?php if (!empty($profilePolicies['birth_date']['visible'])) { ?>
                        <p>
                            <label for="modules_member_account_birth_date">
                                <span><?php echo sr_e(sr_t('member::ui.text.f7ea9e33')); ?><?php echo !empty($profilePolicies['birth_date']['required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                <input class="form-input" id="modules_member_account_birth_date" type="date" name="birth_date" value="<?php echo sr_e($profile['birth_date']); ?>"<?php echo !empty($profilePolicies['birth_date']['required']) ? ' required' : ''; ?>>
                            </label>
                        </p>
                    <?php } ?>
                    <?php if (!empty($profilePolicies['avatar_path']['visible'])) { ?>
                        <?php $avatarSrc = sr_member_avatar_src((string) $profile['avatar_path']); ?>
                        <p>
                            <label for="modules_member_account_avatar_file">
                                <span><?php echo sr_e(sr_t('member::ui.text.8ec77a49')); ?><?php echo !empty($profilePolicies['avatar_path']['required']) && $avatarSrc === '' ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                <input class="form-input" id="modules_member_account_avatar_file" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp"<?php echo !empty($profilePolicies['avatar_path']['required']) && $avatarSrc === '' ? ' required' : ''; ?>>
                            </label>
                            <small><?php echo sr_e(sr_t('member::ui.jpg.png.webp.2fd448bf')); ?> <?php echo sr_e(sr_member_format_bytes(sr_member_avatar_upload_max_bytes())); ?></small>
                        </p>
                        <?php if ($avatarSrc !== '') { ?>
                            <p>
                                <img src="<?php echo sr_e($avatarSrc); ?>" alt="<?php echo sr_e(sr_t('member::ui.text.8ec77a49')); ?>" width="96" height="96">
                            </p>
                            <?php if (empty($profilePolicies['avatar_path']['required'])) { ?>
                                <p>
                                    <label class="member-skin-basic-choice-label" for="modules_member_account_avatar_delete">
                                        <input id="modules_member_account_avatar_delete" type="checkbox" name="avatar_delete" value="1" class="form-checkbox member-skin-basic-choice-input">
                                        <?php echo sr_e(sr_t('member::ui.delete.c94ee577')); ?>
                                    </label>
                                </p>
                            <?php } ?>
                        <?php } ?>
                    <?php } ?>
                    <?php if (!empty($profilePolicies['profile_text']['visible'])) { ?>
                        <p>
                            <label for="modules_member_account_profile_text">
                                <span><?php echo sr_e(sr_t('member::ui.text.7367283c')); ?><?php echo !empty($profilePolicies['profile_text']['required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                <textarea class="form-textarea" id="modules_member_account_profile_text" name="profile_text" maxlength="1000"<?php echo !empty($profilePolicies['profile_text']['required']) ? ' required' : ''; ?>><?php echo sr_e($profile['profile_text']); ?></textarea>
                            </label>
                        </p>
                    <?php } ?>
                    <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.save.ff4a5952')); ?></button>
                </form>
            </section>
        <?php } ?>

        <section class="card member-skin-basic-stack member-skin-basic-padded-card">
            <h2 class="card-title member-skin-basic-card-title"><?php echo sr_e(sr_t('member::ui.text.b6238465')); ?></h2>
            <?php if ($consents === []) { ?>
                <p><?php echo sr_e(sr_t('member::ui.text.91a1276f')); ?></p>
            <?php } else { ?>
                <dl>
                    <?php foreach ($consents as $consent) { ?>
                        <dt><?php echo sr_e((string) $consent['consent_key']); ?></dt>
                        <dd>
                            <?php echo sr_e(!empty($consent['consented']) ? sr_t('member::ui.text.051a33c2') : sr_t('member::ui.text.1c15cddb')); ?>
                            <?php echo sr_e((string) $consent['consent_version']); ?>
                            <?php echo sr_e((string) $consent['created_at']); ?>
                        </dd>
                    <?php } ?>
                </dl>
            <?php } ?>
        </section>

        <form method="post" action="<?php echo sr_e(sr_url('/logout')); ?>" class="member-skin-basic-form" data-sr-validate-form>
            <?php echo sr_csrf_field(); ?>
            <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.text.919c1b32')); ?></button>
        </form>
        <?php if (isset($pdo) && $pdo instanceof PDO && sr_module_enabled($pdo, 'notification')) { ?>
            <p><a href="<?php echo sr_e(sr_url('/account/notifications')); ?>"><?php echo sr_e(sr_t('member::ui.notification.12ddd6ca')); ?></a></p>
        <?php } ?>
        <form method="post" action="<?php echo sr_e(sr_url('/account/privacy-export')); ?>" class="member-skin-basic-form" data-sr-validate-form>
            <?php echo sr_csrf_field(); ?>
            <label for="modules_member_account_current_password_2">
                    <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                <input class="form-input" id="modules_member_account_current_password_2" type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.privacy.2df1446d')); ?></button>
        </form>
        <p><a href="<?php echo sr_e(sr_url('/account/withdraw')); ?>"><?php echo sr_e(sr_t('member::ui.member.4406c379')); ?></a></p>
    </main>
<?php sr_public_layout_end(); ?>
