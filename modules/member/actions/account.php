<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
if (sr_module_enabled($pdo, 'identity_verification') && is_file(SR_ROOT . '/modules/identity_verification/helpers.php')) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_member_group_evaluate_account($pdo, (int) $account['id']);
$memberAccountActionRows = function_exists('sr_public_layout_member_action_rows')
    ? sr_public_layout_member_action_rows($pdo, (int) $account['id'])
    : [];

$errors = [];
$notice = '';
$emailVerificationUrl = '';
$submittedProfile = null;
$submittedBasics = null;
$memberMfaSetup = [];
$memberMfaRecoveryCodes = [];
$memberSettings = sr_member_settings($pdo);
$memberMfaLoginMode = sr_member_mfa_login_mode($memberSettings['mfa_login_mode'] ?? null, $memberSettings['mfa_login_enabled'] ?? null);
$memberMfaLoginProviderKeys = sr_member_mfa_enabled_login_provider_keys($pdo, $memberSettings);
$memberMfaTotpLoginAllowed = in_array('totp', $memberMfaLoginProviderKeys, true);
$memberMfaTotpSetupAllowed = $memberMfaLoginMode !== 'disabled' && $memberMfaTotpLoginAllowed;
$memberMfaDisableAllowed = $memberMfaLoginMode !== 'required';
$memberSecurityIdentityPurpose = 'member.account_security';
$memberSecurityIdentityRequired = !empty($memberSettings['identity_account_security_required']);
$memberSecurityIdentitySatisfied = $memberSecurityIdentityRequired
    && function_exists('sr_identity_verification_session_result')
    && sr_identity_verification_session_result($pdo, $memberSecurityIdentityPurpose, (int) $account['id'], null) !== null;
$memberSecurityIdentityAvailable = function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, $memberSecurityIdentityPurpose);
$memberSecurityIdentityStartUrl = $memberSecurityIdentityAvailable && function_exists('sr_identity_verification_start_url')
    ? sr_identity_verification_start_url($memberSecurityIdentityPurpose, '/mypage/security')
    : '';
$memberAccountBasePath = '/mypage';
$memberAccountPage = 'overview';
$memberAccountRoutePages = [
    '/mypage' => 'overview',
    '/account' => 'overview',
    '/mypage/account' => 'account',
    '/mypage/profile' => 'profile',
    '/mypage/security' => 'security',
    '/mypage/privacy' => 'privacy',
];
$memberAccountCurrentPath = sr_request_path();
if (isset($memberAccountRoutePages[$memberAccountCurrentPath])) {
    $memberAccountPage = $memberAccountRoutePages[$memberAccountCurrentPath];
}
$emailVerificationEnabled = (bool) $memberSettings['email_verification_enabled'];
$profilePolicies = sr_member_profile_field_policies($memberSettings);
$profileExtraFieldDefinitions = sr_member_profile_extra_field_definitions($memberSettings);
$profileFieldsEnabled = sr_member_profile_has_visible_fields($profilePolicies) || $profileExtraFieldDefinitions !== [];
$profileExtraFieldValues = [];
$memberLocaleOptions = sr_supported_locales($site ?? null);
unset($_SESSION['sr_member_account_reauth']);

if (
    $emailVerificationEnabled
    && !empty($config['debug'])
    && sr_is_local_host((string) ($site['base_url'] ?? ''))
    && !empty($_SESSION['sr_debug_email_verification_url'])
    && is_string($_SESSION['sr_debug_email_verification_url'])
) {
    $emailVerificationUrl = $_SESSION['sr_debug_email_verification_url'];
}

if (sr_request_method() === 'GET') {
    $accountFlash = isset($_SESSION['sr_member_account_flash']) && is_array($_SESSION['sr_member_account_flash'])
        ? $_SESSION['sr_member_account_flash']
        : [];
    unset($_SESSION['sr_member_account_flash']);
    if ($accountFlash !== []) {
        $notice = (string) ($accountFlash['notice'] ?? '');
        $flashErrors = $accountFlash['errors'] ?? [];
        $errors = is_array($flashErrors) ? array_values(array_filter(array_map('strval', $flashErrors))) : [];
    }

    $mfaSetupFlash = isset($_SESSION['sr_member_mfa_setup_flash']) && is_array($_SESSION['sr_member_mfa_setup_flash'])
        ? $_SESSION['sr_member_mfa_setup_flash']
        : [];
    unset($_SESSION['sr_member_mfa_setup_flash']);
    if ($mfaSetupFlash !== []) {
        $memberMfaSetup = [
            'factor_id' => (int) ($mfaSetupFlash['factor_id'] ?? 0),
            'issuer' => (string) ($mfaSetupFlash['issuer'] ?? ''),
            'label' => (string) ($mfaSetupFlash['label'] ?? ''),
            'secret_base32' => (string) ($mfaSetupFlash['secret_base32'] ?? ''),
            'otpauth_uri' => (string) ($mfaSetupFlash['otpauth_uri'] ?? ''),
            'otpauth_qr_svg_data_uri' => (string) ($mfaSetupFlash['otpauth_qr_svg_data_uri'] ?? ''),
        ];
    }

    $mfaRecoveryCodesFlash = isset($_SESSION['sr_member_mfa_recovery_codes_flash']) && is_array($_SESSION['sr_member_mfa_recovery_codes_flash'])
        ? $_SESSION['sr_member_mfa_recovery_codes_flash']
        : [];
    unset($_SESSION['sr_member_mfa_recovery_codes_flash']);
    if ($mfaRecoveryCodesFlash !== []) {
        $memberMfaRecoveryCodes = array_values(array_filter(array_map('strval', $mfaRecoveryCodesFlash)));
    }
}

$intent = '';
if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);

    if (!in_array($intent, ['basics', 'profile', 'password', 'mfa_totp_prepare', 'mfa_totp_activate', 'mfa_recovery_rotate', 'mfa_disable'], true)) {
        $errors[] = sr_t('member::action.account.intent_invalid');
    }
    if (
        $errors === []
        && $memberSecurityIdentityRequired
        && in_array($intent, ['password', 'mfa_totp_prepare', 'mfa_recovery_rotate', 'mfa_disable'], true)
        && !$memberSecurityIdentitySatisfied
    ) {
        $errors[] = $memberSecurityIdentityStartUrl !== ''
            ? '계정보안작업 전 본인확인을 완료해 주세요.'
            : '본인확인 기능이 준비되지 않아 계정보안작업을 진행할 수 없습니다.';
    }

    if ($errors === [] && $intent === 'basics') {
        $memberAccountPage = 'account';
        $basics = [
            'display_name' => sr_member_normalize_display_name(sr_post_string('display_name', 120)),
            'nickname' => sr_member_normalize_nickname(sr_post_string('nickname', 80)),
            'locale' => sr_post_string('locale', 20),
        ];
        $submittedBasics = $basics;

        foreach (sr_member_display_name_validation_errors((string) $basics['display_name']) as $displayNameError) {
            $errors[] = $displayNameError;
        }

        foreach (sr_member_nickname_validation_errors($pdo, (string) $basics['nickname'], $memberSettings, (int) $account['id']) as $nicknameError) {
            $errors[] = $nicknameError;
        }

        if (!in_array($basics['locale'], $memberLocaleOptions, true)) {
            $errors[] = sr_t('member::action.account.locale_invalid');
        }

        if ($errors === []) {
            $pdo->beginTransaction();
            try {
                sr_member_update_account_basics($pdo, (int) $account['id'], $basics['display_name'], $basics['locale']);
                if (!empty($memberSettings['nickname_enabled'])) {
                    sr_member_set_nickname($pdo, (int) $account['id'], (string) $basics['nickname']);
                } else {
                    sr_member_delete_nickname($pdo, (int) $account['id']);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'member.account.updated',
                'target_type' => 'member_account',
                'target_id' => (string) $account['id'],
                'result' => 'success',
                'message' => 'Member account basics updated.',
                'metadata' => [
                    'locale' => $basics['locale'],
                    'nickname_set' => !empty($memberSettings['nickname_enabled']) && (string) $basics['nickname'] !== '',
                ],
            ]);

            $account = sr_member_current_account($pdo);
            if (is_array($account)) {
                sr_set_locale((string) $account['locale']);
            }
            $notice = sr_t('member::action.account.basics_saved');
        }
    } elseif ($errors === [] && $intent === 'profile') {
        $memberAccountPage = 'profile';
        if (!$profileFieldsEnabled) {
            $errors[] = sr_t('member::action.account.profile_unavailable');
        }

        $profile = sr_member_profile($pdo, (int) $account['id']);
        if (!sr_member_avatar_reference_is_valid((string) $profile['avatar_path'])) {
            $profile['avatar_path'] = '';
        }
        $previousAvatarPath = (string) $profile['avatar_path'];
        $profile = sr_member_profile_values_from_post($profilePolicies, $profile);
        $profileExtraFieldValues = sr_member_profile_extra_field_input_values($profileExtraFieldDefinitions);
        $submittedProfile = $profile;

        foreach (sr_member_profile_validation_errors($profile, $profilePolicies, ['validate_avatar' => false]) as $profileError) {
            $errors[] = $profileError;
        }
        foreach (sr_member_validate_profile_extra_field_values($profileExtraFieldDefinitions, $profileExtraFieldValues) as $profileError) {
            $errors[] = $profileError;
        }

        $uploadedAvatarReference = '';
        $avatarReferenceToDelete = '';
        if ($errors === [] && !empty($profilePolicies['avatar_path']['visible'])) {
            $deleteAvatar = ($_POST['avatar_delete'] ?? '') === '1';
            if ($deleteAvatar && empty($profilePolicies['avatar_path']['required'])) {
                $profile['avatar_path'] = '';
                $avatarReferenceToDelete = $previousAvatarPath;
            }

            if (sr_member_avatar_upload_was_provided($_FILES['avatar_file'] ?? null)) {
                try {
                    $uploadedAvatar = sr_member_upload_avatar($_FILES['avatar_file']);
                    if (is_array($uploadedAvatar)) {
                        $uploadedAvatarReference = (string) $uploadedAvatar['reference'];
                        $profile['avatar_path'] = $uploadedAvatarReference;
                        $avatarReferenceToDelete = $previousAvatarPath;
                    }
                } catch (Throwable $exception) {
                    sr_log_exception($exception, 'member_account_avatar_upload');
                    $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : sr_t('member::profile.error.avatar_upload_failed');
                }
            }
        }

        if ($errors === []) {
            foreach (sr_member_profile_validation_errors($profile, $profilePolicies) as $profileError) {
                $errors[] = $profileError;
            }
        }

        if ($errors !== [] && $uploadedAvatarReference !== '') {
            sr_member_delete_avatar_reference($uploadedAvatarReference);
            $profile['avatar_path'] = $previousAvatarPath;
            $submittedProfile = $profile;
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();
                sr_member_save_profile($pdo, (int) $account['id'], $profile);
                sr_member_save_profile_extra_field_values($pdo, (int) $account['id'], $profileExtraFieldDefinitions, $profileExtraFieldValues);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($uploadedAvatarReference !== '') {
                    sr_member_delete_avatar_reference($uploadedAvatarReference);
                }
                throw $exception;
            }
            if ($avatarReferenceToDelete !== '' && $avatarReferenceToDelete !== (string) $profile['avatar_path']) {
                sr_member_delete_avatar_reference($avatarReferenceToDelete);
            }
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'member.profile.updated',
                'target_type' => 'member_account',
                'target_id' => (string) $account['id'],
                'result' => 'success',
                'message' => 'Member profile updated.',
            ]);
            $notice = sr_t('member::action.account.profile_saved');
        }
    } elseif ($errors === [] && $intent === 'password') {
        $memberAccountPage = 'security';
        $hasPasswordLogin = trim((string) ($account['password_hash'] ?? '')) !== '';
        $currentPassword = sr_post_string('current_password', 255);
        $newPassword = sr_post_string_without_truncation('new_password', 255);
        $newPasswordConfirm = sr_post_string_without_truncation('new_password_confirm', 255);
        $reauthFailureLogged = false;
        $passwordAuthEvent = $hasPasswordLogin ? 'password_change' : 'password_set';
        $passwordAuditEvent = $hasPasswordLogin ? 'member.password.changed' : 'member.password.set';
        $passwordAuditMessage = $hasPasswordLogin ? 'Member password changed.' : 'Member password set.';

        $reauthThrottle = sr_member_reauth_throttle_status($pdo, (int) $account['id']);
        if ($hasPasswordLogin && !empty($reauthThrottle['limited'])) {
            $errors[] = sr_t('member::action.reauth.throttled');
            sr_member_log_auth($pdo, (int) $account['id'], 'reauth_blocked', 'failure');
            $reauthFailureLogged = true;
        } elseif ($hasPasswordLogin && !password_verify($currentPassword, (string) $account['password_hash'])) {
            $errors[] = sr_t('member::action.account.current_password_invalid');
            sr_member_log_auth($pdo, (int) $account['id'], 'password_change_reauth', 'failure');
            $reauthFailureLogged = true;
        }

        if ($newPassword === null || $newPasswordConfirm === null) {
            $errors[] = sr_t('member::action.password.new_too_long');
            $newPassword = '';
            $newPasswordConfirm = '';
        }

        if (strlen($newPassword) < 8) {
            $errors[] = sr_t('member::action.password.new_too_short');
        }

        if ($newPassword !== $newPasswordConfirm) {
            $errors[] = sr_t('member::action.password.new_confirm_mismatch');
        }

        if ($errors === []) {
            $pdo->beginTransaction();
            try {
                sr_member_update_password($pdo, (int) $account['id'], $newPassword);
                $revokedSessions = sr_member_revoke_other_sessions($pdo, (int) $account['id']);
                if ($revokedSessions < 0) {
                    throw new RuntimeException('Other member sessions could not be revoked after password change.');
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $exception;
            }

            $rotatedSession = sr_member_rotate_current_session($pdo, (int) $account['id']);
            if (!$rotatedSession) {
                sr_member_log_auth($pdo, (int) $account['id'], 'password_change_session_failed', 'failure');
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'member',
                    'event_type' => 'member.password.change.session_failed',
                    'target_type' => 'member_account',
                    'target_id' => (string) $account['id'],
                    'result' => 'failure',
                    'message' => 'Member password was changed but current session could not be rotated.',
                    'metadata' => [
                        'revoked_sessions' => $revokedSessions,
                    ],
                ]);

                sr_member_logout($pdo);
                sr_redirect('/login');
            }

            sr_member_log_auth($pdo, (int) $account['id'], $passwordAuthEvent, 'success');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => $passwordAuditEvent,
                'target_type' => 'member_account',
                'target_id' => (string) $account['id'],
                'result' => 'success',
                'message' => $passwordAuditMessage,
                'metadata' => [
                    'revoked_sessions' => $revokedSessions,
                    'rotated_session' => $rotatedSession,
                ],
            ]);
            sr_member_create_security_notification($pdo, (int) $account['id'], 'security.password_changed');

            $account = sr_member_current_account($pdo);
            $notice = sr_t('member::action.account.password_changed');
        } elseif (!$reauthFailureLogged) {
            sr_member_log_auth($pdo, (int) $account['id'], $passwordAuthEvent, 'failure');
        }
    } elseif ($errors === [] && $intent === 'mfa_totp_prepare') {
        $memberAccountPage = 'security';
        $hasPasswordLogin = trim((string) ($account['password_hash'] ?? '')) !== '';
        $currentPassword = sr_post_string('current_password', 255);
        $reauthFailureLogged = false;

        $reauthThrottle = sr_member_reauth_throttle_status($pdo, (int) $account['id']);
        if (!$memberMfaTotpSetupAllowed) {
            $errors[] = sr_t('member::action.account.mfa_provider_disabled');
        } elseif ($hasPasswordLogin && !empty($reauthThrottle['limited'])) {
            $errors[] = sr_t('member::action.reauth.throttled');
            sr_member_log_auth($pdo, (int) $account['id'], 'reauth_blocked', 'failure');
            $reauthFailureLogged = true;
        } elseif ($hasPasswordLogin && !password_verify($currentPassword, (string) $account['password_hash'])) {
            $errors[] = sr_t('member::action.account.current_password_invalid');
            sr_member_log_auth($pdo, (int) $account['id'], 'mfa_setup_reauth', 'failure');
            $reauthFailureLogged = true;
        }

        if ($errors === []) {
            try {
                $mfaSetup = sr_member_mfa_create_pending_totp_factor(
                    $pdo,
                    (int) $account['id'],
                    sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo),
                    (string) ($account['email'] ?? ('member' . (string) $account['id']))
                );
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'member_mfa_totp_prepare');
                $mfaSetup = [
                    'created' => false,
                    'reason' => 'secret_unavailable',
                ];
            }

            if (!empty($mfaSetup['created'])) {
                $_SESSION['sr_member_mfa_setup_flash'] = [
                    'factor_id' => (int) ($mfaSetup['factor_id'] ?? 0),
                    'issuer' => (string) ($mfaSetup['issuer'] ?? ''),
                    'label' => (string) ($mfaSetup['label'] ?? ''),
                    'secret_base32' => (string) ($mfaSetup['secret_base32'] ?? ''),
                    'otpauth_uri' => (string) ($mfaSetup['otpauth_uri'] ?? ''),
                    'otpauth_qr_svg_data_uri' => (string) ($mfaSetup['otpauth_qr_svg_data_uri'] ?? ''),
                ];
                $notice = sr_t('member::action.account.mfa_totp_prepared');
                sr_member_log_auth($pdo, (int) $account['id'], 'mfa_setup_prepare', 'success');
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'member',
                    'event_type' => 'member.mfa.totp.prepared',
                    'target_type' => 'member_account',
                    'target_id' => (string) $account['id'],
                    'result' => 'success',
                    'message' => 'Member TOTP MFA setup was prepared.',
                    'metadata' => [
                        'factor_id' => (int) ($mfaSetup['factor_id'] ?? 0),
                    ],
                ]);
            } elseif ((string) ($mfaSetup['reason'] ?? '') === 'active_exists') {
                $errors[] = sr_t('member::action.account.mfa_totp_active_exists');
            } else {
                $errors[] = sr_t('member::action.account.mfa_secret_unavailable');
            }
        } elseif (!$reauthFailureLogged) {
            sr_member_log_auth($pdo, (int) $account['id'], 'mfa_setup_prepare', 'failure');
        }

        $_SESSION['sr_member_account_flash'] = [
            'notice' => $notice,
            'errors' => $errors,
        ];
        sr_redirect($memberAccountBasePath . '/security');
    } elseif ($errors === [] && $intent === 'mfa_totp_activate') {
        $memberAccountPage = 'security';
        $factorId = (int) sr_post_string('factor_id', 20);
        $code = sr_post_string('mfa_code', 40);
        $recoveryCodeSetup = [];
        if (!$memberMfaTotpSetupAllowed) {
            $mfaResult = [
                'activated' => false,
                'reason' => 'provider_disabled',
                'factor_id' => 0,
            ];
        } else {
            $pdo->beginTransaction();
            try {
                $mfaResult = sr_member_mfa_activate_pending_totp_factor($pdo, (int) $account['id'], $factorId, $code);
                if (!empty($mfaResult['activated'])) {
                    $recoveryCodeSetup = sr_member_mfa_rotate_recovery_codes($pdo, (int) $account['id'], (int) ($mfaResult['factor_id'] ?? 0));
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }

        if (!empty($mfaResult['activated'])) {
            $notice = sr_t('member::action.account.mfa_totp_activated');
            $_SESSION['sr_member_mfa_recovery_codes_flash'] = is_array($recoveryCodeSetup['codes'] ?? null)
                ? array_values(array_filter(array_map('strval', $recoveryCodeSetup['codes'])))
                : [];
            sr_member_log_auth($pdo, (int) $account['id'], 'mfa_setup', 'success');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'member.mfa.totp.activated',
                'target_type' => 'member_account',
                'target_id' => (string) $account['id'],
                'result' => 'success',
                'message' => 'Member TOTP MFA setup was activated.',
                'metadata' => [
                    'factor_id' => (int) ($mfaResult['factor_id'] ?? 0),
                    'recovery_codes_created' => count($_SESSION['sr_member_mfa_recovery_codes_flash']),
                ],
            ]);
            sr_member_create_security_notification($pdo, (int) $account['id'], 'security.mfa_enabled');
        } else {
            $reason = (string) ($mfaResult['reason'] ?? '');
            if ($reason === 'active_exists') {
                $errors[] = sr_t('member::action.account.mfa_totp_active_exists');
            } elseif ($reason === 'provider_disabled') {
                $errors[] = sr_t('member::action.account.mfa_provider_disabled');
            } elseif ($reason === 'factor_unavailable') {
                $errors[] = sr_t('member::action.account.mfa_totp_pending_missing');
            } elseif ($reason === 'secret_unavailable') {
                $errors[] = sr_t('member::action.account.mfa_secret_unavailable');
            } else {
                $errors[] = sr_t('member::action.account.mfa_code_invalid');
            }
            sr_member_log_auth($pdo, (int) $account['id'], 'mfa_totp_failure', 'failure');
        }

        $_SESSION['sr_member_account_flash'] = [
            'notice' => $notice,
            'errors' => $errors,
        ];
        sr_redirect($memberAccountBasePath . '/security');
    } elseif ($errors === [] && in_array($intent, ['mfa_recovery_rotate', 'mfa_disable'], true)) {
        $memberAccountPage = 'security';
        $hasPasswordLogin = trim((string) ($account['password_hash'] ?? '')) !== '';
        $currentPassword = sr_post_string('current_password', 255);
        $mfaCode = sr_post_string('mfa_code', 80);
        $activeFactor = sr_member_mfa_active_totp_factor($pdo, (int) $account['id']);
        $reauthResult = [
            'verified' => false,
            'method' => '',
            'reason' => 'invalid_code',
        ];
        $recoveryCodeSetup = [];
        $disableResult = [];

        if ($activeFactor === null) {
            $errors[] = sr_t('member::action.account.mfa_not_active');
        }
        if ($errors === [] && $intent === 'mfa_disable' && !$memberMfaDisableAllowed) {
            $errors[] = sr_t('member::action.account.mfa_required_by_policy');
        }

        if ($errors === []) {
            if ($hasPasswordLogin) {
                $reauthThrottle = sr_member_reauth_throttle_status($pdo, (int) $account['id']);
                if (!empty($reauthThrottle['limited'])) {
                    $errors[] = sr_t('member::action.reauth.throttled');
                    sr_member_log_auth($pdo, (int) $account['id'], 'reauth_blocked', 'failure');
                }
            } else {
                $mfaThrottle = sr_member_mfa_throttle_status($pdo, (int) $account['id']);
                if (!empty($mfaThrottle['limited'])) {
                    $errors[] = sr_t('member::action.login_mfa.throttled');
                    sr_member_log_auth($pdo, (int) $account['id'], 'mfa_rate_limited', 'failure');
                }
            }
        }

        if ($errors === []) {
            $pdo->beginTransaction();
            try {
                $reauthResult = sr_member_mfa_management_reauth($pdo, $account, $currentPassword, $mfaCode);
                if (empty($reauthResult['verified'])) {
                    $reason = (string) ($reauthResult['reason'] ?? '');
                    if ($reason === 'invalid_password') {
                        $errors[] = sr_t('member::action.account.current_password_invalid');
                    } elseif ($reason === 'secret_unavailable') {
                        $errors[] = sr_t('member::action.account.mfa_secret_unavailable');
                    } else {
                        $errors[] = sr_t('member::action.account.mfa_reauth_invalid');
                    }
                } elseif ($intent === 'mfa_recovery_rotate') {
                    $recoveryCodeSetup = sr_member_mfa_rotate_recovery_codes($pdo, (int) $account['id'], (int) ($activeFactor['id'] ?? 0));
                    if (empty($recoveryCodeSetup['rotated'])) {
                        $errors[] = sr_t('member::action.account.mfa_secret_unavailable');
                    }
                } else {
                    $disableResult = sr_member_mfa_disable($pdo, (int) $account['id']);
                    if (empty($disableResult['disabled'])) {
                        $errors[] = sr_t('member::action.account.mfa_not_active');
                    }
                }

                if ($errors === []) {
                    $pdo->commit();
                } else {
                    $pdo->rollBack();
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }

        if ($errors === [] && $intent === 'mfa_recovery_rotate') {
            $_SESSION['sr_member_mfa_recovery_codes_flash'] = is_array($recoveryCodeSetup['codes'] ?? null)
                ? array_values(array_filter(array_map('strval', $recoveryCodeSetup['codes'])))
                : [];
            $notice = sr_t('member::action.account.mfa_recovery_rotated');
            sr_member_log_auth($pdo, (int) $account['id'], 'mfa_backup_rotated', 'success');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'member.mfa.recovery.rotated',
                'target_type' => 'member_account',
                'target_id' => (string) $account['id'],
                'result' => 'success',
                'message' => 'Member MFA recovery codes were rotated.',
                'metadata' => [
                    'reauth_method' => (string) ($reauthResult['method'] ?? ''),
                    'recovery_codes_created' => count($_SESSION['sr_member_mfa_recovery_codes_flash']),
                ],
            ]);
            sr_member_create_security_notification($pdo, (int) $account['id'], 'security.mfa_recovery_rotated');
        } elseif ($errors === [] && $intent === 'mfa_disable') {
            $notice = sr_t('member::action.account.mfa_disabled');
            sr_member_log_auth($pdo, (int) $account['id'], 'mfa_disabled', 'success');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'member.mfa.disabled',
                'target_type' => 'member_account',
                'target_id' => (string) $account['id'],
                'result' => 'success',
                'message' => 'Member MFA was disabled.',
                'metadata' => [
                    'reauth_method' => (string) ($reauthResult['method'] ?? ''),
                    'factors_disabled' => (int) ($disableResult['factors_disabled'] ?? 0),
                    'recovery_codes_revoked' => (int) ($disableResult['recovery_codes_revoked'] ?? 0),
                ],
            ]);
            sr_member_create_security_notification($pdo, (int) $account['id'], 'security.mfa_disabled');
        } elseif ($errors !== [] && empty($reauthThrottle['limited']) && empty($mfaThrottle['limited'])) {
            $reauthMethod = (string) ($reauthResult['method'] ?? '');
            if ($reauthMethod === 'password' || $hasPasswordLogin) {
                sr_member_log_auth($pdo, (int) $account['id'], 'mfa_manage_reauth', 'failure');
            } elseif ($reauthMethod === 'backup') {
                sr_member_log_auth($pdo, (int) $account['id'], 'mfa_backup_failure', 'failure');
            } else {
                sr_member_log_auth($pdo, (int) $account['id'], 'mfa_totp_failure', 'failure');
            }
        }

        $_SESSION['sr_member_account_flash'] = [
            'notice' => $notice,
            'errors' => $errors,
        ];
        sr_redirect($memberAccountBasePath . '/security');
    }
}

if (is_array($submittedBasics) && $errors !== []) {
    $account['display_name'] = $submittedBasics['display_name'];
    $account['nickname'] = $submittedBasics['nickname'];
    $account['locale'] = $submittedBasics['locale'];
}
$account['nickname'] = is_array($submittedBasics) && $errors !== []
    ? (string) ($submittedBasics['nickname'] ?? '')
    : sr_member_nickname($pdo, (int) $account['id']);
$profile = sr_member_profile($pdo, (int) $account['id']);
if (!sr_member_avatar_reference_is_valid((string) $profile['avatar_path'])) {
    $profile['avatar_path'] = '';
}
if (is_array($submittedProfile) && $errors !== []) {
    $profile = array_merge($profile, $submittedProfile);
}
if ($profileExtraFieldDefinitions !== []) {
    if ($intent === 'profile' && $errors !== []) {
        $profileExtraValues = $profileExtraFieldValues;
    } else {
        $profileExtraValues = sr_member_profile_extra_field_plain_values($pdo, (int) $account['id']);
    }
} else {
    $profileExtraValues = [];
}
$consents = sr_member_latest_consents($pdo, (int) $account['id']);
$memberMfaActiveFactor = sr_member_mfa_active_totp_factor($pdo, (int) $account['id']);
$memberMfaPendingFactor = sr_member_mfa_pending_totp_factor($pdo, (int) $account['id']);
$memberMfaRecoveryCodeCounts = sr_member_mfa_recovery_code_counts($pdo, (int) $account['id']);
$oauthProviders = [];
$oauthAccounts = [];
$oauthCanUnlink = false;
if (sr_module_enabled($pdo, 'member_oauth') && is_file(SR_ROOT . '/modules/member_oauth/helpers.php')) {
    require_once SR_ROOT . '/modules/member_oauth/helpers.php';
    $oauthProviders = sr_member_oauth_public_providers($pdo);
    $oauthAccounts = sr_member_oauth_accounts_for_account($pdo, (int) $account['id']);
    $oauthCanUnlink = sr_member_oauth_can_unlink($account, $oauthAccounts);
}

$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'account');
include $memberSkinView;
