<?php

$pageTitle = sr_t('member::ui.text.13b28045');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <dl>
            <dt><?php echo sr_e(sr_t('member::ui.email.3b7dbc4c')); ?></dt>
            <dd><?php echo sr_e((string) $account['email']); ?></dd>
            <dt><?php echo sr_e(sr_t('member::ui.name.be0cd9bd')); ?></dt>
            <dd><?php echo sr_e((string) $account['display_name']); ?></dd>
            <dt><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></dt>
            <dd><?php echo sr_e((string) $account['status']); ?></dd>
            <dt><?php echo sr_e(sr_t('member::ui.email.2f905abd')); ?></dt>
            <dd><?php echo $account['email_verified_at'] === null ? sr_t('member::ui.text.a7800e5d') : sr_e((string) $account['email_verified_at']); ?></dd>
        </dl>

        <?php if ($notice !== '') { ?>
            <p><?php echo sr_e($notice); ?></p>
        <?php } ?>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo sr_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <section>
            <h2><?php echo sr_e(sr_t('member::ui.text.25914f73')); ?></h2>
            <form method="post" action="<?php echo sr_e(sr_url('/account')); ?>">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="basics">
                <p>
                    <label for="modules_member_account_display_name">
                    <span><?php echo sr_e(sr_t('member::ui.name.be0cd9bd')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_member_account_display_name" type="text" name="display_name" value="<?php echo sr_e((string) $account['display_name']); ?>" maxlength="120" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_account_locale">
                    <span><?php echo sr_e(sr_t('member::ui.locale.2deb1d6f')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <select id="modules_member_account_locale" name="locale" required>
                            <?php foreach ($memberLocaleOptions as $localeOption) { ?>
                                <option value="<?php echo sr_e($localeOption); ?>"<?php echo (string) $account['locale'] === $localeOption ? ' selected' : ''; ?>>
                                    <?php echo sr_e($localeOption); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                </p>
                <button type="submit"><?php echo sr_e(sr_t('member::ui.save.be6da0db')); ?></button>
            </form>
        </section>

        <?php if ($emailVerificationEnabled) { ?>
            <section>
                <h2><?php echo sr_e(sr_t('member::ui.email.2f905abd')); ?></h2>
                <?php if ($account['email_verified_at'] === null) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/account/email-verification')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <button type="submit"><?php echo sr_e(sr_t('member::ui.text.9938eea0')); ?></button>
                    </form>
                    <?php if ($emailVerificationUrl !== '') { ?>
                        <p><a href="<?php echo sr_e($emailVerificationUrl); ?>"><?php echo sr_e(sr_t('member::ui.email.849a4197')); ?></a></p>
                    <?php } ?>
                <?php } else { ?>
                    <p><?php echo sr_e(sr_t('member::ui.email.f038feee')); ?></p>
                <?php } ?>
            </section>
        <?php } ?>

        <section>
            <h2><?php echo sr_e(sr_t('member::ui.password.bf1d4719')); ?></h2>
            <form method="post" action="<?php echo sr_e(sr_url('/account')); ?>">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="password">
                <p>
                    <label for="modules_member_account_current_password">
                    <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_member_account_current_password" type="password" name="current_password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_account_new_password">
                    <span><?php echo sr_e(sr_t('member::ui.password.04ea6283')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_member_account_new_password" type="password" name="new_password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_account_new_password_confirm">
                    <span><?php echo sr_e(sr_t('member::ui.password.b1d91625')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_member_account_new_password_confirm" type="password" name="new_password_confirm" required>
                    </label>
                </p>
                <button type="submit"><?php echo sr_e(sr_t('member::ui.password.bf1d4719')); ?></button>
            </form>
        </section>

        <?php if ($profileFieldsEnabled) { ?>
            <section>
                <h2><?php echo sr_e(sr_t('member::ui.select.2ea79f04')); ?></h2>
                <form method="post" action="<?php echo sr_e(sr_url('/account')); ?>"<?php echo !empty($profilePolicies['avatar_path']['visible']) ? ' enctype="multipart/form-data"' : ''; ?>>
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="profile">
                    <?php if (!empty($profilePolicies['nickname']['visible'])) { ?>
                        <p>
                            <label for="modules_member_account_nickname">
                                <span><?php echo sr_e(sr_t('member::ui.text.6211d967')); ?><?php echo !empty($profilePolicies['nickname']['required']) ? sr_t('member::ui.span.class.sr.required.label.07a9346b') : ''; ?></span>
                                <input id="modules_member_account_nickname" type="text" name="nickname" value="<?php echo sr_e($profile['nickname']); ?>" maxlength="80"<?php echo !empty($profilePolicies['nickname']['required']) ? ' required' : ''; ?>>
                            </label>
                        </p>
                    <?php } ?>
                    <?php if (!empty($profilePolicies['phone']['visible'])) { ?>
                        <p>
                            <label for="modules_member_account_phone">
                                <span><?php echo sr_e(sr_t('member::ui.text.4edc9439')); ?><?php echo !empty($profilePolicies['phone']['required']) ? sr_t('member::ui.span.class.sr.required.label.07a9346b') : ''; ?></span>
                                <input id="modules_member_account_phone" type="text" name="phone" value="<?php echo sr_e($profile['phone']); ?>" maxlength="40"<?php echo !empty($profilePolicies['phone']['required']) ? ' required' : ''; ?>>
                            </label>
                        </p>
                    <?php } ?>
                    <?php if (!empty($profilePolicies['birth_date']['visible'])) { ?>
                        <p>
                            <label for="modules_member_account_birth_date">
                                <span><?php echo sr_e(sr_t('member::ui.text.f7ea9e33')); ?><?php echo !empty($profilePolicies['birth_date']['required']) ? sr_t('member::ui.span.class.sr.required.label.07a9346b') : ''; ?></span>
                                <input id="modules_member_account_birth_date" type="date" name="birth_date" value="<?php echo sr_e($profile['birth_date']); ?>"<?php echo !empty($profilePolicies['birth_date']['required']) ? ' required' : ''; ?>>
                            </label>
                        </p>
                    <?php } ?>
                    <?php if (!empty($profilePolicies['avatar_path']['visible'])) { ?>
                        <?php $avatarSrc = sr_member_avatar_src((string) $profile['avatar_path']); ?>
                        <p>
                            <label for="modules_member_account_avatar_file">
                                <span><?php echo sr_e(sr_t('member::ui.text.8ec77a49')); ?><?php echo !empty($profilePolicies['avatar_path']['required']) && $avatarSrc === '' ? sr_t('member::ui.span.class.sr.required.label.07a9346b') : ''; ?></span>
                                <input id="modules_member_account_avatar_file" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp"<?php echo !empty($profilePolicies['avatar_path']['required']) && $avatarSrc === '' ? ' required' : ''; ?>>
                            </label>
                            <small><?php echo sr_e(sr_t('member::ui.jpg.png.webp.2fd448bf')); ?> <?php echo sr_e(sr_member_format_bytes(sr_member_avatar_upload_max_bytes())); ?></small>
                        </p>
                        <?php if ($avatarSrc !== '') { ?>
                            <p>
                                <img src="<?php echo sr_e($avatarSrc); ?>" alt="<?php echo sr_e(sr_t('member::ui.text.8ec77a49')); ?>" width="96" height="96">
                            </p>
                            <?php if (empty($profilePolicies['avatar_path']['required'])) { ?>
                                <p>
                                    <label for="modules_member_account_avatar_delete">
                                        <input id="modules_member_account_avatar_delete" type="checkbox" name="avatar_delete" value="1" class="form-checkbox">
                                        <?php echo sr_e(sr_t('member::ui.delete.c94ee577')); ?>
                                    </label>
                                </p>
                            <?php } ?>
                        <?php } ?>
                    <?php } ?>
                    <?php if (!empty($profilePolicies['profile_text']['visible'])) { ?>
                        <p>
                            <label for="modules_member_account_profile_text">
                                <span><?php echo sr_e(sr_t('member::ui.text.7367283c')); ?><?php echo !empty($profilePolicies['profile_text']['required']) ? sr_t('member::ui.span.class.sr.required.label.07a9346b') : ''; ?></span>
                                <textarea id="modules_member_account_profile_text" name="profile_text" maxlength="1000"<?php echo !empty($profilePolicies['profile_text']['required']) ? ' required' : ''; ?>><?php echo sr_e($profile['profile_text']); ?></textarea>
                            </label>
                        </p>
                    <?php } ?>
                    <button type="submit"><?php echo sr_e(sr_t('member::ui.save.ff4a5952')); ?></button>
                </form>
            </section>
        <?php } ?>

        <section>
            <h2><?php echo sr_e(sr_t('member::ui.text.b6238465')); ?></h2>
            <?php if ($consents === []) { ?>
                <p><?php echo sr_e(sr_t('member::ui.text.91a1276f')); ?></p>
            <?php } else { ?>
                <dl>
                    <?php foreach ($consents as $consent) { ?>
                        <dt><?php echo sr_e((string) $consent['consent_key']); ?></dt>
                        <dd>
                            <?php echo !empty($consent['consented']) ? sr_t('member::ui.text.051a33c2') : sr_t('member::ui.text.1c15cddb'); ?>
                            <?php echo sr_e((string) $consent['consent_version']); ?>
                            <?php echo sr_e((string) $consent['created_at']); ?>
                        </dd>
                    <?php } ?>
                </dl>
            <?php } ?>
        </section>

        <form method="post" action="<?php echo sr_e(sr_url('/logout')); ?>">
            <?php echo sr_csrf_field(); ?>
            <button type="submit"><?php echo sr_e(sr_t('member::ui.text.919c1b32')); ?></button>
        </form>
        <p><a href="<?php echo sr_e(sr_url('/account/privacy-requests')); ?>"><?php echo sr_e(sr_t('member::ui.privacy.216d449a')); ?></a></p>
        <?php if (isset($pdo) && $pdo instanceof PDO && sr_module_enabled($pdo, 'notification')) { ?>
            <p><a href="<?php echo sr_e(sr_url('/account/notifications')); ?>"><?php echo sr_e(sr_t('member::ui.notification.12ddd6ca')); ?></a></p>
        <?php } ?>
        <form method="post" action="<?php echo sr_e(sr_url('/account/privacy-export')); ?>">
            <?php echo sr_csrf_field(); ?>
            <label for="modules_member_account_current_password_2">
                    <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                <input id="modules_member_account_current_password_2" type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <button type="submit"><?php echo sr_e(sr_t('member::ui.privacy.2df1446d')); ?></button>
        </form>
        <p><a href="<?php echo sr_e(sr_url('/account/withdraw')); ?>"><?php echo sr_e(sr_t('member::ui.member.4406c379')); ?></a></p>
    </main>
<?php sr_public_layout_end(); ?>
