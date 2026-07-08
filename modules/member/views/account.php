<?php

$pageTitle = sr_t('member::ui.text.13b28045');
$memberAccountBasePath = isset($memberAccountBasePath) && is_string($memberAccountBasePath) ? $memberAccountBasePath : '/mypage';
$memberAccountPage = isset($memberAccountPage) && is_string($memberAccountPage) ? $memberAccountPage : 'overview';
$memberAccountHasPassword = trim((string) ($account['password_hash'] ?? '')) !== '';
$memberMfaActiveFactor = isset($memberMfaActiveFactor) && is_array($memberMfaActiveFactor) ? $memberMfaActiveFactor : null;
$memberMfaPendingFactor = isset($memberMfaPendingFactor) && is_array($memberMfaPendingFactor) ? $memberMfaPendingFactor : null;
$memberMfaSetup = isset($memberMfaSetup) && is_array($memberMfaSetup) ? $memberMfaSetup : [];
$memberMfaRecoveryCodes = isset($memberMfaRecoveryCodes) && is_array($memberMfaRecoveryCodes) ? array_values(array_filter(array_map('strval', $memberMfaRecoveryCodes))) : [];
$memberMfaRecoveryCodeCounts = isset($memberMfaRecoveryCodeCounts) && is_array($memberMfaRecoveryCodeCounts) ? $memberMfaRecoveryCodeCounts : [];
$memberAccountActionRows = isset($memberAccountActionRows) && is_array($memberAccountActionRows) ? $memberAccountActionRows : [];
$memberMfaLoginMode = isset($memberMfaLoginMode) && is_string($memberMfaLoginMode) ? $memberMfaLoginMode : 'optional';
$memberMfaTotpLoginAllowed = isset($memberMfaTotpLoginAllowed) ? (bool) $memberMfaTotpLoginAllowed : true;
$memberMfaTotpSetupAllowed = isset($memberMfaTotpSetupAllowed) ? (bool) $memberMfaTotpSetupAllowed : $memberMfaTotpLoginAllowed;
$memberMfaDisableAllowed = isset($memberMfaDisableAllowed) ? (bool) $memberMfaDisableAllowed : $memberMfaLoginMode !== 'required';
$memberAccountPages = [
    'overview' => [
        'label' => '요약',
        'url' => $memberAccountBasePath,
    ],
    'account' => [
        'label' => sr_t('member::ui.text.25914f73'),
        'url' => $memberAccountBasePath . '/account',
    ],
    'security' => [
        'label' => sr_t('member::ui.password.bf1d4719'),
        'url' => $memberAccountBasePath . '/security',
    ],
    'privacy' => [
        'label' => sr_t('member::ui.text.b6238465'),
        'url' => $memberAccountBasePath . '/privacy',
    ],
];
if (isset($pdo) && $pdo instanceof PDO && sr_module_enabled($pdo, 'notification')) {
    $memberAccountPages['notifications'] = [
        'label' => sr_t('member::ui.notification.12ddd6ca'),
        'url' => '/account/notifications',
    ];
}
if ($profileFieldsEnabled) {
    $memberAccountPages['profile'] = [
        'label' => sr_t('member::ui.select.2ea79f04'),
        'url' => $memberAccountBasePath . '/profile',
    ];
} else {
    unset($memberAccountPages['profile']);
    if ($memberAccountPage === 'profile') {
        $memberAccountPage = 'overview';
    }
}
if (!isset($memberAccountPages[$memberAccountPage])) {
    $memberAccountPage = 'overview';
}
$seo = [
    'title' => $memberAccountPage === 'overview' ? $pageTitle : (string) $memberAccountPages[$memberAccountPage]['label'] . ' - ' . $pageTitle,
    'robots' => 'noindex, nofollow',
];
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_member_skin_layout_context($memberSkinKey));
?>
    <main class="member-skin-basic-page member-skin-basic-page-wide">
        <?php echo sr_member_feedback_toasts($notice, $errors); ?>
        <div class="member-skin-basic-layout">
            <nav class="member-skin-basic-side-nav" aria-label="<?php echo sr_e($pageTitle); ?>">
                <?php foreach ($memberAccountPages as $memberAccountPageKey => $memberAccountPageItem) { ?>
                    <a class="member-skin-basic-side-nav-link<?php echo $memberAccountPageKey === $memberAccountPage ? ' is-active' : ''; ?>" href="<?php echo sr_e(sr_url((string) $memberAccountPageItem['url'])); ?>"<?php echo $memberAccountPageKey === $memberAccountPage ? ' aria-current="page"' : ''; ?>>
                        <?php echo sr_e((string) $memberAccountPageItem['label']); ?>
                    </a>
                <?php } ?>
                <?php foreach ($memberAccountActionRows as $memberAccountActionRow) { ?>
                    <?php
                    $memberAccountActionLabel = trim((string) ($memberAccountActionRow['label'] ?? ''));
                    $memberAccountActionUrl = trim((string) ($memberAccountActionRow['url'] ?? ''));
                    ?>
                    <?php if ($memberAccountActionLabel !== '' && $memberAccountActionUrl !== '') { ?>
                        <a class="member-skin-basic-side-nav-link" href="<?php echo sr_e($memberAccountActionUrl); ?>">
                            <?php echo sr_e($memberAccountActionLabel); ?>
                        </a>
                    <?php } ?>
                <?php } ?>
                <a class="member-skin-basic-side-nav-link" href="<?php echo sr_e(sr_url('/account/withdraw')); ?>"><?php echo sr_e(sr_t('member::ui.member.4406c379')); ?></a>
            </nav>

            <div class="member-skin-basic-main-panel">
                <?php if ($memberAccountPage === 'overview') { ?>
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
                                <dd><?php echo $account['email_verified_at'] === null ? sr_e(sr_t('member::ui.text.a7800e5d')) : sr_relative_time_html((string) $account['email_verified_at']); ?></dd>
                            </dl>
                        </div>
                    </section>
                <?php } elseif ($memberAccountPage === 'account') { ?>
                    <section class="card member-skin-basic-stack member-skin-basic-padded-card">
                        <h1 class="card-title member-skin-basic-card-title"><?php echo sr_e(sr_t('member::ui.text.25914f73')); ?></h1>
                        <form method="post" action="<?php echo sr_e(sr_url($memberAccountBasePath . '/account')); ?>" class="member-skin-basic-form" data-sr-validate-form>
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
                                                <span><?php echo sr_relative_time_html((string) $oauthAccount['last_login_at']); ?></span>
                                            <?php } ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/account/oauth/unlink')); ?>" class="member-skin-basic-form" data-sr-validate-form>
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="oauth_account_id" value="<?php echo sr_e((string) $oauthAccount['id']); ?>">
                                                <button class="btn btn-solid-primary" type="submit"<?php echo $oauthCanUnlink ? '' : ' disabled'; ?>>연결 해제</button>
                                            </form>
                                            <?php if (!$oauthCanUnlink) { ?>
                                                <small>비밀번호를 설정하거나 다른 소셜 로그인을 연결한 뒤 해제할 수 있습니다.</small>
                                            <?php } ?>
                                        </dd>
                                    <?php } ?>
                                </dl>
                            <?php } ?>
                            <p>
                                <?php foreach ($oauthProviders as $oauthProvider) { ?>
                                    <a href="<?php echo sr_e(sr_url('/oauth/start?provider=' . rawurlencode((string) $oauthProvider['provider_key']) . '&flow=link&next=' . rawurlencode($memberAccountBasePath . '/account'))); ?>">
                                        <?php echo sr_e((string) $oauthProvider['label']); ?> 연결
                                    </a>
                                <?php } ?>
                            </p>
                        </section>
                    <?php } ?>
                <?php } elseif ($memberAccountPage === 'profile') { ?>
                    <section class="card member-skin-basic-stack member-skin-basic-padded-card">
                        <h1 class="card-title member-skin-basic-card-title"><?php echo sr_e(sr_t('member::ui.select.2ea79f04')); ?></h1>
                        <?php
                        $memberAccountProfileExtraDefinitions = is_array($profileExtraFieldDefinitions ?? null) ? $profileExtraFieldDefinitions : [];
                        $memberAccountProfileOrderItems = sr_member_profile_field_order_items($memberSettings, $memberAccountProfileExtraDefinitions);
                        $memberAccountProfileExtraByKey = [];
                        foreach ($memberAccountProfileExtraDefinitions as $memberAccountProfileExtraDefinition) {
                            $memberAccountProfileExtraByKey[(string) ($memberAccountProfileExtraDefinition['key'] ?? '')] = $memberAccountProfileExtraDefinition;
                        }
                        ?>
                        <form method="post" action="<?php echo sr_e(sr_url($memberAccountBasePath . '/profile')); ?>" class="member-skin-basic-form" data-sr-validate-form<?php echo !empty($profilePolicies['avatar_path']['visible']) ? ' enctype="multipart/form-data"' : ''; ?>>
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="profile">
                            <?php foreach ($memberAccountProfileOrderItems as $memberAccountProfileOrderItem) { ?>
                                <?php if ((string) ($memberAccountProfileOrderItem['kind'] ?? '') === 'fixed' && (string) ($memberAccountProfileOrderItem['key'] ?? '') === 'birth_date') { ?>
                                    <?php if (!empty($profilePolicies['birth_date']['visible'])) { ?>
                                        <p>
                                            <label for="modules_member_account_birth_date">
                                                <span><?php echo sr_e(sr_t('member::ui.text.f7ea9e33')); ?><?php echo !empty($profilePolicies['birth_date']['required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                                <input class="form-input" id="modules_member_account_birth_date" type="date" name="birth_date" value="<?php echo sr_e($profile['birth_date']); ?>"<?php echo !empty($profilePolicies['birth_date']['required']) ? ' required' : ''; ?>>
                                            </label>
                                        </p>
                                    <?php } ?>
                                <?php } elseif ((string) ($memberAccountProfileOrderItem['kind'] ?? '') === 'fixed' && (string) ($memberAccountProfileOrderItem['key'] ?? '') === 'is_adult') { ?>
                                    <?php if (!empty($profilePolicies['is_adult']['visible'])) { ?>
                                        <p>
                                            <label for="modules_member_account_is_adult">
                                                <span><?php echo sr_e(sr_t('member::ui.is_adult')); ?><?php echo !empty($profilePolicies['is_adult']['required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                                <select class="form-select" id="modules_member_account_is_adult" name="is_adult"<?php echo !empty($profilePolicies['is_adult']['required']) ? ' required' : ''; ?>>
                                                    <option value=""><?php echo sr_e(sr_t('member::ui.select.default')); ?></option>
                                                    <option value="1"<?php echo (string) ($profile['is_adult'] ?? '') === '1' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('member::ui.yes')); ?></option>
                                                    <option value="0"<?php echo (string) ($profile['is_adult'] ?? '') === '0' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('member::ui.no')); ?></option>
                                                </select>
                                            </label>
                                        </p>
                                    <?php } ?>
                                <?php } elseif ((string) ($memberAccountProfileOrderItem['kind'] ?? '') === 'fixed' && (string) ($memberAccountProfileOrderItem['key'] ?? '') === 'avatar_path') { ?>
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
                                <?php } elseif ((string) ($memberAccountProfileOrderItem['kind'] ?? '') === 'extra') { ?>
                                    <?php echo sr_member_profile_extra_fields_form_html([$memberAccountProfileExtraByKey[(string) ($memberAccountProfileOrderItem['key'] ?? '')] ?? []], is_array($profileExtraValues ?? null) ? $profileExtraValues : [], 'modules_member_account_profile_extra', false); ?>
                                <?php } ?>
                            <?php } ?>
                            <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.save.ff4a5952')); ?></button>
                        </form>
                    </section>
                <?php } elseif ($memberAccountPage === 'security') { ?>
                    <?php if (!empty($memberSecurityIdentityRequired)) { ?>
                        <section class="card member-skin-basic-stack member-skin-basic-padded-card">
                            <div class="alert <?php echo !empty($memberSecurityIdentitySatisfied) ? 'alert-success' : 'alert-warning'; ?>">
                                <p><?php echo !empty($memberSecurityIdentitySatisfied) ? sr_e('계정보안작업 본인확인이 완료되었습니다. 현재 세션 동안 적용됩니다.') : sr_e('비밀번호 변경과 2차 인증 관리에는 본인확인이 필요합니다.'); ?></p>
                                <?php if (empty($memberSecurityIdentitySatisfied) && !empty($memberSecurityIdentityStartUrl)) { ?>
                                    <p><a class="btn btn-sm btn-solid-primary" href="<?php echo sr_e((string) $memberSecurityIdentityStartUrl); ?>"><?php echo sr_e('본인확인'); ?></a></p>
                                <?php } ?>
                            </div>
                        </section>
                    <?php } ?>
                    <section class="card member-skin-basic-stack member-skin-basic-padded-card">
                        <h1 class="card-title member-skin-basic-card-title"><?php echo sr_e(sr_t('member::ui.password.bf1d4719')); ?></h1>
                        <form method="post" action="<?php echo sr_e(sr_url($memberAccountBasePath . '/security')); ?>" class="member-skin-basic-form" data-sr-validate-form>
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="password">
                            <?php if ($memberAccountHasPassword) { ?>
                                <p>
                                    <label for="modules_member_account_current_password">
                                    <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                        <input class="form-input" id="modules_member_account_current_password" type="password" name="current_password" required>
                                    </label>
                                </p>
                            <?php } else { ?>
                                <p>현재 비밀번호가 없습니다. 새 비밀번호를 설정하면 소셜 로그인 연결 상태와 관계없이 이메일과 비밀번호로 로그인할 수 있습니다.</p>
                            <?php } ?>
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
                            <button class="btn btn-solid-primary" type="submit"><?php echo sr_e($memberAccountHasPassword ? sr_t('member::ui.password.bf1d4719') : '비밀번호 설정'); ?></button>
                        </form>
                    </section>

                    <section class="card member-skin-basic-stack member-skin-basic-padded-card">
                        <h2 class="card-title member-skin-basic-card-title"><?php echo sr_e(sr_t('member::ui.mfa_totp.title')); ?></h2>
                        <?php if ($memberMfaActiveFactor !== null) { ?>
                            <dl class="member-skin-basic-description">
                                <dt><?php echo sr_e(sr_t('member::ui.mfa_totp.status')); ?></dt>
                                <dd><?php echo sr_e(sr_t('member::ui.mfa_totp.status_active')); ?></dd>
                                <dt><?php echo sr_e(sr_t('member::ui.mfa_totp.activated_at')); ?></dt>
                                <dd><?php echo sr_relative_time_html((string) ($memberMfaActiveFactor['activated_at'] ?? '')); ?></dd>
                                <dt><?php echo sr_e(sr_t('member::ui.mfa_recovery.remaining')); ?></dt>
                                <dd><?php echo sr_e((string) ((int) ($memberMfaRecoveryCodeCounts['unused'] ?? 0))); ?></dd>
                            </dl>
                            <?php if ($memberMfaLoginMode === 'required') { ?>
                                <p class="member-skin-basic-muted type-small">운영자 설정에서 로그인 2차 인증이 필수로 지정되어 있습니다.</p>
                            <?php } elseif (!$memberMfaTotpLoginAllowed) { ?>
                                <p class="member-skin-basic-muted type-small">운영자 설정에서 인증 앱 OTP 로그인이 비활성화되어 있어 현재 등록된 2차 인증은 로그인 때 요구되지 않습니다.</p>
                            <?php } ?>
                            <?php if ($memberMfaRecoveryCodes !== []) { ?>
                                <div class="member-skin-basic-stack">
                                    <p><?php echo sr_e(sr_t('member::ui.mfa_recovery.once_help')); ?></p>
                                    <ol>
                                        <?php foreach ($memberMfaRecoveryCodes as $memberMfaRecoveryCode) { ?>
                                            <li><code><?php echo sr_e($memberMfaRecoveryCode); ?></code></li>
                                        <?php } ?>
                                    </ol>
                                </div>
                            <?php } ?>
                            <form method="post" action="<?php echo sr_e(sr_url($memberAccountBasePath . '/security')); ?>" class="member-skin-basic-form" data-sr-validate-form>
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="mfa_recovery_rotate">
                                <?php if ($memberAccountHasPassword) { ?>
                                    <p>
                                        <label for="modules_member_account_mfa_rotate_current_password">
                                            <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                            <input class="form-input" id="modules_member_account_mfa_rotate_current_password" type="password" name="current_password" autocomplete="current-password" required>
                                        </label>
                                    </p>
                                <?php } else { ?>
                                    <p>
                                        <label for="modules_member_account_mfa_rotate_code">
                                            <span><?php echo sr_e(sr_t('member::ui.mfa_totp.reauth_code')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                            <input class="form-input" id="modules_member_account_mfa_rotate_code" type="text" name="mfa_code" autocomplete="one-time-code" required>
                                        </label>
                                    </p>
                                <?php } ?>
                                <button class="btn btn-outline-primary" type="submit"><?php echo sr_e(sr_t('member::ui.mfa_recovery.rotate')); ?></button>
                            </form>
                            <?php if ($memberMfaDisableAllowed) { ?>
                                <form method="post" action="<?php echo sr_e(sr_url($memberAccountBasePath . '/security')); ?>" class="member-skin-basic-form" data-sr-validate-form>
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="mfa_disable">
                                    <?php if ($memberAccountHasPassword) { ?>
                                        <p>
                                            <label for="modules_member_account_mfa_disable_current_password">
                                                <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                                <input class="form-input" id="modules_member_account_mfa_disable_current_password" type="password" name="current_password" autocomplete="current-password" required>
                                            </label>
                                        </p>
                                    <?php } else { ?>
                                        <p>
                                            <label for="modules_member_account_mfa_disable_code">
                                                <span><?php echo sr_e(sr_t('member::ui.mfa_totp.reauth_code')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                                <input class="form-input" id="modules_member_account_mfa_disable_code" type="text" name="mfa_code" autocomplete="one-time-code" required>
                                            </label>
                                        </p>
                                    <?php } ?>
                                    <button class="btn btn-outline-danger" type="submit"><?php echo sr_e(sr_t('member::ui.mfa_totp.disable')); ?></button>
                                </form>
                            <?php } ?>
                        <?php } else { ?>
                            <p><?php echo sr_e(sr_t('member::ui.mfa_totp.help')); ?></p>
                            <?php if ($memberMfaLoginMode === 'required') { ?>
                                <p class="member-skin-basic-muted type-small">운영자 설정에서 로그인 2차 인증이 필수입니다. 아래에서 인증 앱 OTP를 등록하세요.</p>
                            <?php } ?>
                            <?php if (!$memberMfaTotpSetupAllowed) { ?>
                                <p class="member-skin-basic-muted type-small">운영자 설정에서 인증 앱 OTP 로그인이 비활성화되어 있어 새 2차 인증 등록을 시작할 수 없습니다.</p>
                            <?php } else { ?>
                                <form method="post" action="<?php echo sr_e(sr_url($memberAccountBasePath . '/security')); ?>" class="member-skin-basic-form" data-sr-validate-form>
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="mfa_totp_prepare">
                                    <?php if ($memberAccountHasPassword) { ?>
                                        <p>
                                            <label for="modules_member_account_mfa_current_password">
                                                <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                                <input class="form-input" id="modules_member_account_mfa_current_password" type="password" name="current_password" autocomplete="current-password" required>
                                            </label>
                                        </p>
                                    <?php } ?>
                                    <button class="btn btn-solid-primary" type="submit"><?php echo sr_e($memberMfaPendingFactor === null ? sr_t('member::ui.mfa_totp.prepare') : sr_t('member::ui.mfa_totp.prepare_again')); ?></button>
                                </form>
                            <?php } ?>

                            <?php if ((string) ($memberMfaSetup['secret_base32'] ?? '') !== '') { ?>
                                <div class="member-skin-basic-stack">
                                    <p><?php echo sr_e(sr_t('member::ui.mfa_totp.secret_help')); ?></p>
                                    <?php if ((string) ($memberMfaSetup['otpauth_qr_svg_data_uri'] ?? '') !== '') { ?>
                                        <p class="member-skin-basic-mfa-qr">
                                            <img src="<?php echo sr_e((string) $memberMfaSetup['otpauth_qr_svg_data_uri']); ?>" alt="<?php echo sr_e(sr_t('member::ui.mfa_totp.qr_alt')); ?>" width="260" height="260">
                                        </p>
                                    <?php } ?>
                                    <p>
                                        <label for="modules_member_account_mfa_secret">
                                            <span><?php echo sr_e(sr_t('member::ui.mfa_totp.secret')); ?></span>
                                            <input class="form-input" id="modules_member_account_mfa_secret" type="text" value="<?php echo sr_e((string) $memberMfaSetup['secret_base32']); ?>" readonly>
                                        </label>
                                    </p>
                                    <p>
                                        <label for="modules_member_account_mfa_otpauth_uri">
                                            <span><?php echo sr_e(sr_t('member::ui.mfa_totp.otpauth_uri')); ?></span>
                                            <textarea class="form-textarea" id="modules_member_account_mfa_otpauth_uri" rows="3" readonly><?php echo sr_e((string) ($memberMfaSetup['otpauth_uri'] ?? '')); ?></textarea>
                                        </label>
                                    </p>
                                </div>
                            <?php } elseif ($memberMfaPendingFactor !== null) { ?>
                                <p><?php echo sr_e(sr_t('member::ui.mfa_totp.pending_help')); ?></p>
                            <?php } ?>

                            <?php if ($memberMfaPendingFactor !== null && $memberMfaTotpSetupAllowed) { ?>
                                <form method="post" action="<?php echo sr_e(sr_url($memberAccountBasePath . '/security')); ?>" class="member-skin-basic-form" data-sr-validate-form>
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="mfa_totp_activate">
                                    <input type="hidden" name="factor_id" value="<?php echo sr_e((string) ($memberMfaPendingFactor['id'] ?? '0')); ?>">
                                    <p>
                                        <label for="modules_member_account_mfa_code">
                                            <span><?php echo sr_e(sr_t('member::ui.login_mfa.code')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                            <input class="form-input" id="modules_member_account_mfa_code" type="text" name="mfa_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9\\s-]{6,20}" required>
                                        </label>
                                    </p>
                                    <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.mfa_totp.activate')); ?></button>
                                </form>
                            <?php } ?>
                        <?php } ?>
                    </section>
                <?php } elseif ($memberAccountPage === 'privacy') { ?>
                    <section class="card member-skin-basic-stack member-skin-basic-padded-card">
                        <h1 class="card-title member-skin-basic-card-title"><?php echo sr_e(sr_t('member::ui.text.b6238465')); ?></h1>
                        <?php if ($consents === []) { ?>
                            <p><?php echo sr_e(sr_t('member::ui.text.91a1276f')); ?></p>
                        <?php } else { ?>
                            <dl>
                                <?php foreach ($consents as $consent) { ?>
                                    <dt><?php echo sr_e((string) $consent['consent_key']); ?></dt>
                                    <dd>
                                        <?php echo sr_e(!empty($consent['consented']) ? sr_t('member::ui.text.051a33c2') : sr_t('member::ui.text.1c15cddb')); ?>
                                        <?php echo sr_e((string) $consent['consent_version']); ?>
                                        <?php echo sr_relative_time_html((string) $consent['created_at']); ?>
                                    </dd>
                                <?php } ?>
                            </dl>
                        <?php } ?>
                    </section>
                    <form method="post" action="<?php echo sr_e(sr_url('/account/privacy-export')); ?>" class="member-skin-basic-form" data-sr-validate-form>
                        <?php echo sr_csrf_field(); ?>
                        <label for="modules_member_account_current_password_2">
                                <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                            <input class="form-input" id="modules_member_account_current_password_2" type="password" name="current_password" autocomplete="current-password" required>
                        </label>
                        <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.privacy.2df1446d')); ?></button>
                    </form>
                <?php } ?>

                <form method="post" action="<?php echo sr_e(sr_url('/logout')); ?>" class="member-skin-basic-form" data-sr-validate-form>
                    <?php echo sr_csrf_field(); ?>
                    <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.text.919c1b32')); ?></button>
                </form>
            </div>
        </div>
    </main>
<?php sr_public_layout_end(); ?>
