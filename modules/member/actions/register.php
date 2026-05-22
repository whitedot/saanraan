<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$account = sr_member_current_account($pdo);
if ($account !== null) {
    if (sr_request_method() === 'POST') {
        sr_require_csrf();
    }
    sr_redirect('/account');
}

$memberSettings = sr_member_settings($pdo);
$registrationAllowed = (bool) $memberSettings['allow_registration'];
$emailVerificationEnabled = (bool) $memberSettings['email_verification_enabled'];
$profilePolicies = sr_member_profile_field_policies($memberSettings);
$profileFieldsEnabled = sr_member_profile_has_visible_fields($profilePolicies);
$errors = [];
$marketingConsent = false;
$values = [
    'email' => '',
    'login_id' => '',
    'display_name' => '',
];
$profileValues = sr_member_empty_profile();

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    if (!$registrationAllowed) {
        $errors[] = sr_t('member::action.register.disabled');
    }

    $email = sr_post_string_without_truncation('email', 255);
    if ($email === null) {
        $errors[] = sr_t('member::action.register.email_too_long');
        $email = '';
    }

    $loginId = sr_post_string_without_truncation('login_id', 40);
    if ($loginId === null) {
        $loginId = '';
        $errors[] = sr_t('member::action.register.login_id_too_long');
    }

    $values = [
        'email' => $email,
        'login_id' => sr_member_normalize_login_id($loginId),
        'display_name' => sr_post_string('display_name', 120),
    ];
    $password = sr_post_string_without_truncation('password', 255);
    $passwordConfirm = sr_post_string_without_truncation('password_confirm', 255);
    $termsConsent = ($_POST['terms_consent'] ?? '') === '1';
    $privacyConsent = ($_POST['privacy_consent'] ?? '') === '1';
    $marketingConsent = ($_POST['marketing_consent'] ?? '') === '1';
    if ($profileFieldsEnabled) {
        $profileValues = sr_member_profile_values_from_post($profilePolicies, sr_member_empty_profile());
    }

    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = sr_t('member::action.register.email_invalid');
    }

    if ($values['login_id'] !== '' && !sr_member_is_valid_login_id($values['login_id'])) {
        $errors[] = sr_t('member::action.register.login_id_invalid');
    }

    if ($values['display_name'] === '') {
        $errors[] = sr_t('member::action.register.display_name_required');
    }

    if ($password === null || $passwordConfirm === null) {
        $errors[] = sr_t('member::action.register.password_too_long');
        $password = '';
        $passwordConfirm = '';
    }

    if (strlen($password) < 8) {
        $errors[] = sr_t('member::action.register.password_too_short');
    }

    if ($password !== $passwordConfirm) {
        $errors[] = sr_t('member::action.register.password_confirm_mismatch');
    }

    if (!$termsConsent || !$privacyConsent) {
        $errors[] = sr_t('member::action.register.required_consents_missing');
    }

    if ($profileFieldsEnabled) {
        foreach (sr_member_profile_validation_errors($profileValues, $profilePolicies, ['validate_avatar' => false]) as $profileError) {
            $errors[] = $profileError;
        }
        if (
            !empty($profilePolicies['avatar_path']['visible'])
            && !empty($profilePolicies['avatar_path']['required'])
            && !sr_member_avatar_upload_was_provided($_FILES['avatar_file'] ?? null)
        ) {
            $errors[] = sr_t('member::profile.error.avatar_required');
        }
    }

    if ($errors === []) {
        $throttle = sr_member_register_throttle_status($pdo);
        if (!empty($throttle['limited'])) {
            sr_member_log_auth($pdo, null, 'register_blocked', 'failure');
            sr_audit_log($pdo, [
                'actor_account_id' => null,
                'actor_type' => 'member',
                'event_type' => 'member.register.blocked',
                'target_type' => 'member_account',
                'target_id' => '',
                'result' => 'failure',
                'message' => 'Member registration blocked by throttle.',
            ]);
            $errors[] = sr_t('member::action.register.throttled');
        }
    }

    if ($errors === []) {
        $uploadedAvatarReference = '';

        if (!empty($profilePolicies['avatar_path']['visible']) && sr_member_avatar_upload_was_provided($_FILES['avatar_file'] ?? null)) {
            try {
                $uploadedAvatar = sr_member_upload_avatar($_FILES['avatar_file']);
                if (is_array($uploadedAvatar)) {
                    $uploadedAvatarReference = (string) $uploadedAvatar['reference'];
                    $profileValues['avatar_path'] = $uploadedAvatarReference;
                }
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'member_register_avatar_upload');
                $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : sr_t('member::profile.error.avatar_upload_failed');
            }
        }

        if ($errors === [] && $profileFieldsEnabled) {
            foreach (sr_member_profile_validation_errors($profileValues, $profilePolicies) as $profileError) {
                $errors[] = $profileError;
            }
        }

        if ($errors !== [] && $uploadedAvatarReference !== '') {
            sr_member_delete_avatar_reference($uploadedAvatarReference);
            $profileValues['avatar_path'] = '';
        }
    }

    if ($errors === []) {
        $accountId = null;
        $verificationMailSent = null;
        $verificationUrl = '';
        $uploadedAvatarReference = (string) ($profileValues['avatar_path'] ?? '');

        try {
            $pdo->beginTransaction();

            $accountId = sr_member_create_account($pdo, $config, [
                'email' => $values['email'],
                'login_id' => $values['login_id'],
                'password' => $password,
                'display_name' => $values['display_name'],
                'locale' => (string) ($site['default_locale'] ?? 'ko'),
                'status' => 'active',
                'email_verified_at' => $emailVerificationEnabled ? null : sr_now(),
            ]);

            if ($emailVerificationEnabled) {
                $verificationToken = sr_member_create_email_verification($pdo, $config, $accountId, $values['email']);
                $verificationUrl = sr_absolute_url($site, '/email/verify?token=' . rawurlencode($verificationToken));
            }
            sr_member_record_consent($pdo, $accountId, 'terms', '2026.04.001', true);
            sr_member_record_consent($pdo, $accountId, 'privacy', '2026.04.001', true);
            sr_member_record_consent($pdo, $accountId, 'marketing', '2026.04.001', $marketingConsent);
            if ($profileFieldsEnabled) {
                sr_member_save_profile($pdo, $accountId, $profileValues);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            sr_member_log_auth($pdo, null, 'register', 'failure');
            if ($uploadedAvatarReference !== '') {
                sr_member_delete_avatar_reference($uploadedAvatarReference);
                $profileValues['avatar_path'] = '';
            }
            $errors[] = sr_t('member::action.register.create_failed');
        }

        if ($errors === [] && $accountId !== null) {
            if ($emailVerificationEnabled) {
                $verificationMailSent = sr_send_mail(
                    $site,
                    $values['email'],
                    sr_t('member::action.email_verification.subject'),
                    sr_t('member::action.email_verification.body', ['url' => $verificationUrl])
                );
                $showVerificationUrl = !empty($config['debug']) && sr_is_local_host((string) ($site['base_url'] ?? ''));
                if ($showVerificationUrl) {
                    $_SESSION['sr_debug_email_verification_url'] = $verificationUrl;
                } else {
                    unset($_SESSION['sr_debug_email_verification_url']);
                }
                if (!$verificationMailSent) {
                    sr_member_log_auth($pdo, $accountId, 'email_verification_mail_failed', 'failure');
                }
            }

            sr_member_log_auth($pdo, $accountId, 'register', 'success');
            sr_audit_log($pdo, [
                'actor_account_id' => $accountId,
                'actor_type' => 'member',
                'event_type' => 'member.registered',
                'target_type' => 'member_account',
                'target_id' => (string) $accountId,
                'result' => 'success',
                'message' => 'Member registered.',
                'metadata' => [
                    'email_verification_mail_sent' => $verificationMailSent,
                ],
            ]);

            $newAccount = sr_member_find_by_id($pdo, $accountId);
            if ($emailVerificationEnabled) {
                $_SESSION['sr_member_login_notice'] = sr_t('member::action.register.email_verification_notice');
                sr_redirect('/login');
            }

            if ($newAccount !== null && sr_member_login($pdo, $newAccount)) {
                sr_redirect('/account');
            }

            $_SESSION['sr_member_login_notice'] = sr_t('member::action.register.login_session_failed_notice');
            sr_redirect('/login');
        }
    }
}

$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'register');
include $memberSkinView;
