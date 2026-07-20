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
$memberAccountAccessState = isset($memberAccountAccessState) && is_array($memberAccountAccessState) ? $memberAccountAccessState : [];
$memberAccountAccessCredentialVerified = sr_member_account_access_credential_verified($memberAccountAccessState);

if ($memberAccountPage === 'verify') {
    $seo = [
        'title' => '마이페이지 확인 - ' . $pageTitle,
        'robots' => 'noindex, nofollow',
    ];
    $memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
    sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_member_skin_layout_context($memberSkinKey));
    ?>
    <main class="member-skin-basic-page member-skin-basic-page-narrow">
        <?php echo sr_member_feedback_toasts($notice, $errors); ?>
        <section class="card member-skin-basic-access-card">
            <div class="member-skin-basic-access-icon" aria-hidden="true">
                <span class="material-symbols-outlined" data-sr-material-icon>shield_lock</span>
            </div>
            <div class="member-skin-basic-stack">
                <div class="member-skin-basic-access-heading">
                    <p class="member-skin-basic-eyebrow">계정 보호</p>
                    <h1 class="card-title member-skin-basic-card-title"><?php echo sr_e(sr_t('member::ui.account.reauth_title')); ?></h1>
                    <p class="member-skin-basic-muted">마이페이지에는 개인정보와 보안 설정이 포함되어 있어 로그인 후 처음 들어올 때 한 번 더 확인합니다.</p>
                </div>

                <ol class="member-skin-basic-access-steps" aria-label="마이페이지 확인 단계">
                    <li class="is-active<?php echo $memberAccountAccessCredentialVerified ? ' is-complete' : ''; ?>">
                        <span>1</span>
                        <strong><?php echo sr_e(!empty($memberAccountHasPassword) ? '비밀번호 확인' : (!empty($memberAccountHasMfaReauth) ? '2차 인증 확인' : '로그인 확인')); ?></strong>
                    </li>
                    <?php if (!empty($memberSecurityIdentityRequired)) { ?>
                        <li class="<?php echo $memberAccountAccessCredentialVerified ? 'is-active' : ''; ?><?php echo !empty($memberSecurityIdentitySatisfied) ? ' is-complete' : ''; ?>">
                            <span>2</span>
                            <strong>본인확인</strong>
                        </li>
                    <?php } ?>
                </ol>

                <?php if (!$memberAccountAccessCredentialVerified) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/mypage/verify')); ?>" class="member-skin-basic-form" data-sr-validate-form data-member-autofocus-form>
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="account_access_verify">
                        <?php if (!empty($memberAccountHasPassword)) { ?>
                            <p>
                                <label for="modules_member_account_access_password">
                                    <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                    <input class="form-input" id="modules_member_account_access_password" type="password" name="current_password" autocomplete="current-password" required>
                                </label>
                                <small>현재 로그인한 계정의 비밀번호를 입력하세요.</small>
                            </p>
                        <?php } elseif (!empty($memberAccountHasMfaReauth)) { ?>
                            <p>
                                <label for="modules_member_account_access_mfa_code">
                                    <span>인증 앱 또는 복구 코드 <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                    <input class="form-input" id="modules_member_account_access_mfa_code" type="text" name="mfa_code" autocomplete="one-time-code" required>
                                </label>
                                <small>비밀번호가 없는 계정은 현재 인증 앱 코드나 사용하지 않은 복구 코드로 확인합니다.</small>
                            </p>
                        <?php } else { ?>
                            <div class="alert alert-info">
                                <p>이 계정에는 확인할 비밀번호나 등록된 인증 앱이 없습니다. 현재 소셜 로그인 세션으로 계속합니다.</p>
                            </div>
                        <?php } ?>
                        <button class="btn btn-solid-primary member-skin-basic-access-submit" type="submit">
                            <?php echo sr_e(!empty($memberSecurityIdentityRequired) ? '확인하고 다음' : (!empty($memberAccountHasPassword) || !empty($memberAccountHasMfaReauth) ? sr_t('member::ui.password.61081c91') : '마이페이지 계속하기')); ?>
                        </button>
                    </form>
                <?php } elseif (!empty($memberSecurityIdentityRequired) && empty($memberSecurityIdentitySatisfied)) { ?>
                    <div class="alert <?php echo !empty($memberAccountIdentityStartUrl) ? 'alert-info' : 'alert-danger'; ?>">
                        <p><?php echo sr_e(!empty($memberAccountIdentityStartUrl) ? '환경설정에 따라 본인확인을 한 번 더 완료해 주세요.' : '본인확인 기능이 준비되지 않아 마이페이지에 진입할 수 없습니다.'); ?></p>
                    </div>
                    <?php if (!empty($memberAccountIdentityStartUrl)) { ?>
                        <a class="btn btn-solid-primary member-skin-basic-access-submit" href="<?php echo sr_e((string) $memberAccountIdentityStartUrl); ?>">본인확인 계속하기</a>
                    <?php } ?>
                <?php } ?>

                <a class="member-skin-basic-access-back" href="<?php echo sr_e(sr_url('/')); ?>">
                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>arrow_back</span>
                    사이트로 돌아가기
                </a>
            </div>
        </section>
    </main>
    <?php sr_public_layout_end(); ?>
    <?php return; ?>
    <?php
}

$memberAvatarSizeKey = 'large';
$memberAvatarSizePixels = sr_member_profile_image_size_pixels($memberAvatarSizeKey, $memberSettings);
$memberAccountPages = [
    'overview' => [
        'label' => '요약',
        'url' => $memberAccountBasePath,
        'icon' => 'space_dashboard',
    ],
    'account' => [
        'label' => sr_t('member::ui.text.25914f73'),
        'url' => $memberAccountBasePath . '/account',
        'icon' => 'manage_accounts',
    ],
    'security' => [
        'label' => sr_t('member::ui.password.bf1d4719'),
        'url' => $memberAccountBasePath . '/security',
        'icon' => 'shield_lock',
    ],
    'privacy' => [
        'label' => sr_t('member::ui.text.b6238465'),
        'url' => $memberAccountBasePath . '/privacy',
        'icon' => 'policy',
    ],
];
if (!isset($memberAccountPages[$memberAccountPage])) {
    $memberAccountPage = 'overview';
}
$seo = [
    'title' => $memberAccountPage === 'overview' ? $pageTitle : (string) $memberAccountPages[$memberAccountPage]['label'] . ' - ' . $pageTitle,
    'robots' => 'noindex, nofollow',
];
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
$memberAccountAvatarSrc = sr_member_profile_image_src((string) ($profile['profile_image_path'] ?? ''));
$memberAccountPublicName = trim((string) ($account['nickname'] ?? ''));
if ($memberAccountPublicName === '') {
    $memberAccountPublicName = trim((string) ($account['display_name'] ?? ''));
}
$memberAccountInitial = function_exists('mb_substr')
    ? mb_substr($memberAccountPublicName !== '' ? $memberAccountPublicName : (string) ($account['email'] ?? 'M'), 0, 1, 'UTF-8')
    : substr($memberAccountPublicName !== '' ? $memberAccountPublicName : (string) ($account['email'] ?? 'M'), 0, 1);
$memberAccountBasicValues = [
    'email' => (string) ($account['email'] ?? ''),
    'login_id' => '',
    'display_name' => (string) ($account['display_name'] ?? ''),
    'nickname' => (string) ($account['nickname'] ?? ''),
    'locale' => (string) ($account['locale'] ?? ''),
];
if (is_array($submittedBasics ?? null) && $errors !== []) {
    $memberAccountBasicValues = array_merge($memberAccountBasicValues, $submittedBasics);
}
$memberAccountHasStoredLoginId = trim((string) ($account['login_id_hash'] ?? '')) !== ''
    || (
        trim((string) ($account['account_identifier_hash'] ?? '')) !== ''
        && trim((string) ($account['email_hash'] ?? '')) !== ''
        && !hash_equals((string) $account['email_hash'], (string) $account['account_identifier_hash'])
    );
$memberAccountProfileExtraDefinitions = is_array($profileExtraFieldDefinitions ?? null) ? $profileExtraFieldDefinitions : [];
$memberAccountProfileOrderItems = sr_member_profile_field_order_items($memberSettings, $memberAccountProfileExtraDefinitions);
$memberAccountProfileExtraByKey = [];
foreach ($memberAccountProfileExtraDefinitions as $memberAccountProfileExtraDefinition) {
    $memberAccountProfileExtraByKey[(string) ($memberAccountProfileExtraDefinition['key'] ?? '')] = $memberAccountProfileExtraDefinition;
}
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_member_skin_layout_context($memberSkinKey));
?>
    <main class="member-skin-basic-page member-skin-basic-page-wide">
        <?php echo sr_member_feedback_toasts($notice, $errors); ?>
        <section class="card" aria-labelledby="member-account-page-title">
            <div class="card-body member-skin-basic-account-hero">
                <div class="member-skin-basic-account-avatar" aria-hidden="true">
                    <?php if ($memberAccountAvatarSrc !== '') { ?>
                        <img src="<?php echo sr_e($memberAccountAvatarSrc); ?>" alt="" width="72" height="72">
                    <?php } else { ?>
                        <span><?php echo sr_e($memberAccountInitial); ?></span>
                    <?php } ?>
                </div>
                <div class="member-skin-basic-account-hero-copy">
                    <p class="type-caption member-skin-basic-muted">MY PAGE</p>
                    <h1 id="member-account-page-title" class="card-title"><?php echo sr_e($memberAccountPublicName !== '' ? $memberAccountPublicName . '님' : $pageTitle); ?></h1>
                    <p class="member-skin-basic-muted"><?php echo sr_e((string) $account['email']); ?></p>
                    <div class="member-skin-basic-account-badges">
                        <span class="badge badge-soft-success badge-pill"><?php echo sr_e(sr_member_account_status_label((string) $account['status'])); ?></span>
                        <span class="badge <?php echo $account['email_verified_at'] === null ? 'badge-soft-warning' : 'badge-soft-info'; ?> badge-pill">
                            <?php echo sr_e($account['email_verified_at'] === null ? '이메일 미인증' : '이메일 인증 완료'); ?>
                        </span>
                    </div>
                </div>
                <a class="btn btn-outline-primary member-skin-basic-hero-action" href="<?php echo sr_e(sr_url($memberAccountBasePath . '/account')); ?>">내 정보 수정</a>
            </div>
        </section>
        <div class="member-skin-basic-layout">
            <aside class="card member-skin-basic-side-nav" aria-labelledby="member-account-side-nav-title">
                <div class="card-header">
                    <h2 id="member-account-side-nav-title" class="member-skin-basic-side-nav-title">마이페이지</h2>
                </div>
                <nav class="card-body member-skin-basic-side-nav-body" aria-label="<?php echo sr_e($pageTitle); ?>">
                    <?php foreach ($memberAccountPages as $memberAccountPageKey => $memberAccountPageItem) { ?>
                        <a class="member-skin-basic-side-nav-link<?php echo $memberAccountPageKey === $memberAccountPage ? ' is-active' : ''; ?>" href="<?php echo sr_e(sr_url((string) $memberAccountPageItem['url'])); ?>"<?php echo $memberAccountPageKey === $memberAccountPage ? ' aria-current="page"' : ''; ?>>
                            <?php echo sr_material_icon_html((string) ($memberAccountPageItem['icon'] ?? 'chevron_right')); ?>
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
                                <?php echo sr_material_icon_html('open_in_new'); ?>
                                <?php echo sr_e($memberAccountActionLabel); ?>
                            </a>
                        <?php } ?>
                    <?php } ?>
                    <div class="member-skin-basic-side-nav-divider" aria-hidden="true"></div>
                    <a class="member-skin-basic-side-nav-link member-skin-basic-side-nav-danger" href="<?php echo sr_e(sr_url('/account/withdraw')); ?>">
                        <?php echo sr_material_icon_html('person_remove'); ?>
                        <?php echo sr_e(sr_t('member::ui.member.4406c379')); ?>
                    </a>
                    <form method="post" action="<?php echo sr_e(sr_url('/logout')); ?>" class="member-skin-basic-side-nav-form">
                        <?php echo sr_csrf_field(); ?>
                        <button class="member-skin-basic-side-nav-link" type="submit">
                            <?php echo sr_material_icon_html('logout'); ?>
                            <?php echo sr_e(sr_t('member::ui.text.919c1b32')); ?>
                        </button>
                    </form>
                </nav>
            </aside>

            <div class="member-skin-basic-main-panel">
                <?php if ($memberAccountPage === 'overview') { ?>
                    <section class="card" aria-labelledby="member-account-overview-title">
                        <div class="card-header">
                            <h2 id="member-account-overview-title" class="card-title">내 계정 관리</h2>
                            <p class="type-small member-skin-basic-muted">자주 사용하는 설정으로 이동합니다.</p>
                        </div>
                        <div class="card-body">
                            <div class="member-skin-basic-overview-grid">
                                <?php foreach ($memberAccountPages as $memberAccountOverviewKey => $memberAccountOverviewItem) { ?>
                                    <?php if ($memberAccountOverviewKey !== 'overview') { ?>
                                        <a class="btn btn-outline-default member-skin-basic-overview-action" href="<?php echo sr_e(sr_url((string) $memberAccountOverviewItem['url'])); ?>">
                                            <?php echo sr_material_icon_html((string) ($memberAccountOverviewItem['icon'] ?? 'chevron_right')); ?>
                                            <span><?php echo sr_e((string) $memberAccountOverviewItem['label']); ?></span>
                                        </a>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </section>

                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title">계정 상태</h2>
                        </div>
                        <div class="card-body">
                            <dl class="member-skin-basic-description member-skin-basic-description-roomy">
                                <dt><?php echo sr_e(sr_t('member::ui.name.be0cd9bd')); ?></dt>
                                <dd><?php echo sr_e((string) $account['display_name']); ?></dd>
                                <?php if (!empty($memberSettings['nickname_enabled'])) { ?>
                                    <dt><?php echo sr_e(sr_t('member::ui.nickname')); ?></dt>
                                    <dd><?php echo sr_e((string) ($account['nickname'] ?? '')); ?></dd>
                                <?php } ?>
                                <dt>최근 로그인</dt>
                                <dd><?php echo empty($account['last_login_at']) ? '기록 없음' : sr_relative_time_html((string) $account['last_login_at']); ?></dd>
                                <dt>가입일</dt>
                                <dd><?php echo sr_relative_time_html((string) $account['created_at']); ?></dd>
                            </dl>
                        </div>
                    </section>
                <?php } elseif ($memberAccountPage === 'account') { ?>
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.text.25914f73')); ?></h2>
                            <p class="type-small member-skin-basic-muted">가입할 때 입력한 기본 정보와 서비스 설정을 확인하거나 변경합니다.</p>
                        </div>
                        <form method="post" action="<?php echo sr_e(sr_url($memberAccountBasePath . '/account')); ?>" class="card-body member-skin-basic-form" data-sr-validate-form>
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="basics">
                            <p>
                                <label for="modules_member_account_email">
                                    <span><?php echo sr_e(sr_t('member::ui.email.3b7dbc4c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                    <input class="form-input" id="modules_member_account_email" type="email" name="email" value="<?php echo sr_e((string) $memberAccountBasicValues['email']); ?>" maxlength="255" autocomplete="email" required<?php echo $emailVerificationEnabled && !$emailDeliveryAvailable ? ' readonly' : ''; ?>>
                                </label>
                                <?php if ($emailVerificationEnabled && !$emailDeliveryAvailable) { ?>
                                    <small><?php echo sr_e(sr_t('member::action.email_delivery.email_change_unavailable')); ?></small>
                                <?php } elseif ($emailVerificationEnabled) { ?>
                                    <small>이메일을 변경하면 인증 상태가 초기화되며 새 주소로 다시 인증해야 합니다.</small>
                                <?php } ?>
                            </p>
                            <p>
                                <label for="modules_member_account_login_id">
                                    <span>새 로그인 아이디</span>
                                    <input class="form-input" id="modules_member_account_login_id" type="text" name="login_id" value="<?php echo sr_e((string) $memberAccountBasicValues['login_id']); ?>" maxlength="40" pattern="[a-z][a-z0-9_]{3,39}" inputmode="latin" autocapitalize="none" spellcheck="false" autocomplete="off" data-member-login-id-input>
                                </label>
                                <small><?php echo sr_e($memberAccountHasStoredLoginId ? '현재 로그인 아이디는 설정되어 있습니다. 원문은 저장하지 않아 표시할 수 없으며, 변경할 때만 새 값을 입력하세요. 게시물과 권한의 계정 연결은 그대로 유지됩니다.' : '현재 로그인 아이디가 없습니다. 새 값을 입력하면 이메일과 로그인 아이디를 모두 사용할 수 있습니다.'); ?></small>
                            </p>
                            <p>
                                <label for="modules_member_account_display_name">
                                <span><?php echo sr_e(sr_t('member::ui.name.be0cd9bd')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                    <input class="form-input" id="modules_member_account_display_name" type="text" name="display_name" value="<?php echo sr_e((string) $memberAccountBasicValues['display_name']); ?>" maxlength="120" required>
                                </label>
                            </p>
                            <?php if (!empty($memberSettings['nickname_enabled'])) { ?>
                                <p>
                                    <label for="modules_member_account_nickname">
                                    <span><?php echo sr_e(sr_t('member::ui.nickname')); ?><?php echo !empty($memberSettings['nickname_required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                        <input class="form-input" id="modules_member_account_nickname" type="text" name="nickname" value="<?php echo sr_e((string) $memberAccountBasicValues['nickname']); ?>" maxlength="80"<?php echo !empty($memberSettings['nickname_required']) ? ' required' : ''; ?>>
                                    </label>
                                    <small><?php echo sr_e(sr_t('member::ui.nickname.help')); ?></small>
                                </p>
                            <?php } ?>
                            <?php foreach (($registrationAccountExtensionFields ?? []) as $registrationAccountExtensionField) { ?>
                                <?php
                                $registrationAccountExtensionKey = (string) ($registrationAccountExtensionField['key'] ?? '');
                                $registrationAccountExtensionType = (string) ($registrationAccountExtensionField['type'] ?? 'text');
                                $registrationAccountExtensionInputId = 'modules_member_account_extension_' . $registrationAccountExtensionKey;
                                ?>
                                <p>
                                    <?php if ($registrationAccountExtensionType === 'checkbox') { ?>
                                        <label class="member-skin-basic-choice-label" for="<?php echo sr_e($registrationAccountExtensionInputId); ?>">
                                            <input id="<?php echo sr_e($registrationAccountExtensionInputId); ?>" type="checkbox" name="registration_extensions[<?php echo sr_e($registrationAccountExtensionKey); ?>]" value="1" class="form-checkbox member-skin-basic-choice-input"<?php echo (string) (($registrationAccountExtensionValues ?? [])[$registrationAccountExtensionKey] ?? '') === '1' ? ' checked' : ''; ?><?php echo !empty($registrationAccountExtensionField['required']) ? ' required' : ''; ?>>
                                            <?php echo sr_e((string) ($registrationAccountExtensionField['label'] ?? '')); ?><?php echo !empty($registrationAccountExtensionField['required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?>
                                        </label>
                                    <?php } else { ?>
                                        <label for="<?php echo sr_e($registrationAccountExtensionInputId); ?>">
                                            <span><?php echo sr_e((string) ($registrationAccountExtensionField['label'] ?? '')); ?><?php echo !empty($registrationAccountExtensionField['required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                            <input id="<?php echo sr_e($registrationAccountExtensionInputId); ?>" type="text" name="registration_extensions[<?php echo sr_e($registrationAccountExtensionKey); ?>]" value="<?php echo sr_e((string) (($registrationAccountExtensionValues ?? [])[$registrationAccountExtensionKey] ?? '')); ?>" class="form-input" maxlength="<?php echo sr_e((string) (int) ($registrationAccountExtensionField['maxlength'] ?? 120)); ?>"<?php echo !empty($registrationAccountExtensionField['required']) ? ' required' : ''; ?>>
                                        </label>
                                    <?php } ?>
                                    <?php if ((string) ($registrationAccountExtensionField['help'] ?? '') !== '') { ?>
                                        <small><?php echo sr_e((string) $registrationAccountExtensionField['help']); ?></small>
                                    <?php } ?>
                                </p>
                            <?php } ?>
                            <p>
                                <label for="modules_member_account_locale">
                                <span><?php echo sr_e(sr_t('member::ui.locale.2deb1d6f')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                    <select class="form-select" id="modules_member_account_locale" name="locale" required>
                                        <?php foreach ($memberLocaleOptions as $localeOption) { ?>
                                            <option value="<?php echo sr_e($localeOption); ?>"<?php echo (string) $memberAccountBasicValues['locale'] === $localeOption ? ' selected' : ''; ?>>
                                                <?php echo sr_e($localeOption); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </label>
                            </p>
                            <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.save.be6da0db')); ?></button>
                        </form>
                    </section>

                    <?php if ($profileFieldsEnabled) { ?>
                        <section class="card">
                            <div class="card-header">
                                <h2 class="card-title">추가 계정 정보</h2>
                                <p class="type-small member-skin-basic-muted">가입할 때 선택한 프로필 항목을 같은 계정 정보 화면에서 관리합니다.</p>
                            </div>
                            <form method="post" action="<?php echo sr_e(sr_url($memberAccountBasePath . '/account')); ?>" class="card-body member-skin-basic-form" data-sr-validate-form<?php echo !empty($profilePolicies['profile_image_path']['visible']) ? ' enctype="multipart/form-data"' : ''; ?>>
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
                                    <?php } elseif ((string) ($memberAccountProfileOrderItem['kind'] ?? '') === 'fixed' && (string) ($memberAccountProfileOrderItem['key'] ?? '') === 'profile_image_path') { ?>
                                        <?php if (!empty($profilePolicies['profile_image_path']['visible'])) { ?>
                                            <?php $avatarSrc = sr_member_profile_image_src((string) $profile['profile_image_path']); ?>
                                            <p>
                                                <label for="modules_member_account_profile_image_file">
                                                    <span><?php echo sr_e(sr_t('member::ui.text.8ec77a49')); ?><?php echo !empty($profilePolicies['profile_image_path']['required']) && $avatarSrc === '' ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                                    <input class="form-input" id="modules_member_account_profile_image_file" type="file" name="profile_image_file" accept="image/jpeg,image/png,image/webp"<?php echo !empty($profilePolicies['profile_image_path']['required']) && $avatarSrc === '' ? ' required' : ''; ?>>
                                                </label>
                                                <small><?php echo sr_e(sr_t('member::ui.jpg.png.webp.2fd448bf')); ?> <?php echo sr_e(sr_member_format_bytes(sr_member_profile_image_upload_max_bytes())); ?></small>
                                            </p>
                                            <?php if ($avatarSrc !== '') { ?>
                                                <p>
                                                    <img class="member-skin-basic-avatar-preview member-profile-image-size-<?php echo sr_e($memberAvatarSizeKey); ?>" src="<?php echo sr_e($avatarSrc); ?>" alt="<?php echo sr_e(sr_t('member::ui.text.8ec77a49')); ?>" width="<?php echo sr_e((string) $memberAvatarSizePixels); ?>" height="<?php echo sr_e((string) $memberAvatarSizePixels); ?>" style="--member-profile-image-size: <?php echo sr_e((string) $memberAvatarSizePixels); ?>px">
                                                </p>
                                                <?php if (empty($profilePolicies['profile_image_path']['required'])) { ?>
                                                    <p>
                                                        <label class="member-skin-basic-choice-label" for="modules_member_account_profile_image_delete">
                                                            <input id="modules_member_account_profile_image_delete" type="checkbox" name="profile_image_delete" value="1" class="form-checkbox member-skin-basic-choice-input">
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
                                <button class="btn btn-solid-primary" type="submit">추가 정보 저장</button>
                            </form>
                        </section>
                    <?php } ?>

                    <?php if ($emailVerificationEnabled) { ?>
                        <section class="card">
                            <div class="card-header">
                                <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.email.2f905abd')); ?></h2>
                            </div>
                            <div class="card-body member-skin-basic-stack">
                                <?php if ($account['email_verified_at'] === null) { ?>
                                    <?php if (!$emailDeliveryAvailable) { ?>
                                        <div class="alert alert-warning">
                                            <p><?php echo sr_e(sr_t('member::action.email_delivery.verification_unavailable')); ?></p>
                                        </div>
                                    <?php } ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/account/email-verification')); ?>" class="member-skin-basic-form" data-sr-validate-form>
                                        <?php echo sr_csrf_field(); ?>
                                        <button class="btn btn-solid-primary" type="submit"<?php echo !$emailDeliveryAvailable ? ' disabled' : ''; ?>><?php echo sr_e(sr_t('member::ui.text.9938eea0')); ?></button>
                                    </form>
                                    <?php if ($emailVerificationUrl !== '') { ?>
                                        <p><a href="<?php echo sr_e($emailVerificationUrl); ?>"><?php echo sr_e(sr_t('member::ui.email.849a4197')); ?></a></p>
                                    <?php } ?>
                                <?php } else { ?>
                                    <p><?php echo sr_e(sr_t('member::ui.email.f038feee')); ?></p>
                                <?php } ?>
                            </div>
                        </section>
                    <?php } ?>

                    <?php if (!empty($oauthProviders)) { ?>
                        <section class="card">
                            <div class="card-header">
                                <h2 class="card-title">소셜 로그인</h2>
                            </div>
                            <div class="card-body member-skin-basic-stack">
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
                                                    <button class="btn btn-outline-danger" type="submit"<?php echo $oauthCanUnlink ? '' : ' disabled'; ?>>연결 해제</button>
                                                </form>
                                                <?php if (!$oauthCanUnlink) { ?>
                                                    <small>비밀번호를 설정하거나 다른 소셜 로그인을 연결한 뒤 해제할 수 있습니다.</small>
                                                <?php } ?>
                                            </dd>
                                        <?php } ?>
                                    </dl>
                                <?php } ?>
                                <div class="member-skin-basic-actions">
                                    <?php foreach ($oauthProviders as $oauthProvider) { ?>
                                        <a class="btn btn-outline-primary" href="<?php echo sr_e(sr_url('/oauth/start?provider=' . rawurlencode((string) $oauthProvider['provider_key']) . '&flow=link&next=' . rawurlencode($memberAccountBasePath . '/account'))); ?>">
                                            <?php echo sr_e((string) $oauthProvider['label']); ?> 연결
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </section>
                    <?php } ?>
                <?php } elseif ($memberAccountPage === 'security') { ?>
                    <?php if (!empty($memberSecurityIdentityRequired)) { ?>
                        <section aria-label="계정 보안 본인확인">
                            <div class="alert <?php echo !empty($memberSecurityIdentitySatisfied) ? 'alert-success' : 'alert-warning'; ?>">
                                <p><?php echo !empty($memberSecurityIdentitySatisfied) ? sr_e('계정보안작업 본인확인이 완료되었습니다. 현재 세션 동안 적용됩니다.') : sr_e('비밀번호 변경과 2차 인증 관리에는 본인확인이 필요합니다.'); ?></p>
                                <?php if (empty($memberSecurityIdentitySatisfied) && !empty($memberSecurityIdentityStartUrl)) { ?>
                                    <p><a class="btn btn-sm btn-solid-primary" href="<?php echo sr_e((string) $memberSecurityIdentityStartUrl); ?>"><?php echo sr_e('본인확인'); ?></a></p>
                                <?php } ?>
                            </div>
                        </section>
                    <?php } ?>
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.password.bf1d4719')); ?></h2>
                        </div>
                        <form method="post" action="<?php echo sr_e(sr_url($memberAccountBasePath . '/security')); ?>" class="card-body member-skin-basic-form" data-sr-validate-form>
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="password">
                            <?php if ($memberAccountHasPassword) { ?>
                                <p>
                                    <label for="modules_member_account_current_password">
                                    <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                        <input class="form-input" id="modules_member_account_current_password" type="password" name="current_password" autocomplete="current-password" required>
                                    </label>
                                </p>
                            <?php } else { ?>
                                <p>현재 비밀번호가 없습니다. 새 비밀번호를 설정하면 소셜 로그인 연결 상태와 관계없이 이메일과 비밀번호로 로그인할 수 있습니다.</p>
                            <?php } ?>
                            <p>
                                <label for="modules_member_account_new_password">
                                <span><?php echo sr_e(sr_t('member::ui.password.04ea6283')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                    <input class="form-input" id="modules_member_account_new_password" type="password" name="new_password" autocomplete="new-password" required>
                                </label>
                            </p>
                            <p>
                                <label for="modules_member_account_new_password_confirm">
                                <span><?php echo sr_e(sr_t('member::ui.password.b1d91625')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                    <input class="form-input" id="modules_member_account_new_password_confirm" type="password" name="new_password_confirm" autocomplete="new-password" required>
                                </label>
                            </p>
                            <button class="btn btn-solid-primary" type="submit"><?php echo sr_e($memberAccountHasPassword ? sr_t('member::ui.password.bf1d4719') : '비밀번호 설정'); ?></button>
                        </form>
                    </section>

                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.mfa_totp.title')); ?></h2>
                        </div>
                        <div class="card-body member-skin-basic-stack">
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
                        </div>
                    </section>
                <?php } elseif ($memberAccountPage === 'privacy') { ?>
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.text.b6238465')); ?></h2>
                        </div>
                        <div class="card-body">
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
                        </div>
                    </section>
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title">개인정보 사본</h2>
                            <p class="type-small member-skin-basic-muted">계정과 설치된 모듈에 저장된 내 개인정보를 내려받습니다.</p>
                        </div>
                        <form method="post" action="<?php echo sr_e(sr_url('/account/privacy-export')); ?>" class="card-body member-skin-basic-form" data-sr-validate-form>
                            <?php echo sr_csrf_field(); ?>
                            <label for="modules_member_account_current_password_2">
                                    <span><?php echo sr_e(sr_t('member::ui.password.f8762fcc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                <input class="form-input" id="modules_member_account_current_password_2" type="password" name="current_password" autocomplete="current-password" required>
                            </label>
                            <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.privacy.2df1446d')); ?></button>
                        </form>
                    </section>
                <?php } ?>
            </div>
        </div>
    </main>
<?php sr_public_layout_end(); ?>
