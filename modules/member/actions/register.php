<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
if (sr_module_enabled($pdo, 'antispam') && is_file(SR_ROOT . '/modules/antispam/helpers.php')) {
    require_once SR_ROOT . '/modules/antispam/helpers.php';
}
if (sr_module_enabled($pdo, 'identity_verification') && is_file(SR_ROOT . '/modules/identity_verification/helpers.php')) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}

$account = sr_member_current_account($pdo);
if ($account !== null) {
    if (sr_request_method() === 'POST') {
        sr_require_csrf();
    }
    sr_redirect('/account');
}

$memberSettings = sr_member_settings($pdo);
$registrationExtensionContracts = sr_member_registration_extension_contracts($pdo);
$registrationExtensionFields = sr_member_registration_extension_fields($pdo, $registrationExtensionContracts);
$registrationAllowed = (bool) $memberSettings['allow_registration'];
$emailVerificationEnabled = (bool) $memberSettings['email_verification_enabled'];
$profilePolicies = sr_member_profile_field_policies($memberSettings);
$profileExtraFieldDefinitions = sr_member_profile_extra_field_definitions($memberSettings);
$profileFieldsEnabled = sr_member_profile_has_visible_fields($profilePolicies) || $profileExtraFieldDefinitions !== [];
$registrationPolicyDocumentState = $registrationAllowed ? sr_member_registration_policy_documents($pdo) : ['documents' => [], 'errors' => []];
$registrationPolicyDocuments = $registrationPolicyDocumentState['documents'];
$registrationPolicyErrors = $registrationPolicyDocumentState['errors'];
$registrationReady = $registrationAllowed && $registrationPolicyErrors === [];
$registrationIdentityMode = sr_member_identity_requirement_mode($memberSettings['identity_registration_mode'] ?? 'disabled');
$registrationIdentityPurpose = 'member.registration';
$registrationIdentityAvailable = function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, $registrationIdentityPurpose);
$registrationIdentityReturnStatus = function_exists('sr_get_string') ? sr_get_string('identity_verification', 30) : '';
if (!in_array($registrationIdentityReturnStatus, ['success', 'expired', 'failed', 'canceled', 'duplicate'], true)) {
    $registrationIdentityReturnStatus = '';
}
$registrationIdentityReturnToken = sr_request_method() === 'POST'
    ? sr_post_string('identity_verification_token', 160)
    : sr_get_string('identity_verification_token', 160);
$registrationIdentityResult = null;
if ($registrationIdentityReturnToken !== '' && function_exists('sr_identity_verification_result_for_return_token')) {
    $registrationIdentityResult = sr_identity_verification_result_for_return_token($pdo, $config, $registrationIdentityReturnToken, $registrationIdentityPurpose, 0);
}
$registrationIdentitySatisfied = is_array($registrationIdentityResult);
$registrationIdentitySnapshot = [];
$registrationIdentityRequired = $registrationIdentityMode === 'required';
$registrationIdentityFieldsLocked = $registrationIdentityRequired && $registrationIdentitySatisfied;
$registrationIdentityStartUrl = $registrationIdentityAvailable && function_exists('sr_identity_verification_start_url')
    ? sr_identity_verification_start_url($registrationIdentityPurpose, '/register')
    : '';
if ($registrationIdentityStartUrl !== '') {
    $registrationIdentityStartUrl .= (str_contains($registrationIdentityStartUrl, '?') ? '&' : '?') . 'popup=1';
}
$errors = [];
$registrationConsentValues = [];
$values = [
    'email' => '',
    'login_id' => '',
    'display_name' => !empty($registrationIdentitySnapshot['name']) ? (string) $registrationIdentitySnapshot['name'] : '',
    'nickname' => '',
];
$registrationExtensionValues = sr_member_registration_extension_empty_values($registrationExtensionFields);
$profileValues = sr_member_empty_profile();
if (!empty($registrationIdentityResult['birth_date'])) {
    $profileValues['birth_date'] = (string) $registrationIdentityResult['birth_date'];
}
if (array_key_exists('age_over_19', is_array($registrationIdentityResult) ? $registrationIdentityResult : []) && $registrationIdentityResult['age_over_19'] !== null) {
    $profileValues['is_adult'] = (int) $registrationIdentityResult['age_over_19'] === 1 ? '1' : '0';
}
$profileExtraValues = [];
foreach ($profileExtraFieldDefinitions as $profileExtraFieldDefinition) {
    $profileExtraKey = (string) ($profileExtraFieldDefinition['key'] ?? '');
    if (in_array($profileExtraKey, ['phone', 'mobile', 'mobile_phone', 'phone_number'], true) && !empty($registrationIdentitySnapshot['phone'])) {
        $profileExtraValues[$profileExtraKey] = (string) $registrationIdentitySnapshot['phone'];
    }
}
$antispamRegisterContext = ['account' => null];

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    if (!$registrationAllowed) {
        $errors[] = sr_t('member::action.register.disabled');
    }
    if ($registrationPolicyErrors !== []) {
        $errors = array_merge($errors, $registrationPolicyErrors);
    }
    if ($registrationIdentityRequired && !$registrationIdentitySatisfied) {
        $errors[] = $registrationIdentityAvailable
            ? '회원가입 전 본인확인을 완료해 주세요.'
            : '본인확인 기능이 준비되지 않아 회원가입을 진행할 수 없습니다.';
    }
    if (function_exists('sr_antispam_verify')) {
        $antispamResult = sr_antispam_verify($pdo, 'member.register', 'member_register', $_POST, $antispamRegisterContext);
        $errors = array_merge($errors, (array) ($antispamResult['errors'] ?? []));
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
        'display_name' => sr_member_normalize_display_name(sr_post_string('display_name', 120)),
        'nickname' => sr_member_normalize_nickname(sr_post_string('nickname', 80)),
    ];
    $registrationExtensionValues = sr_member_registration_extension_values_from_post($registrationExtensionFields, $errors);
    $password = sr_post_string_without_truncation('password', 255);
    $passwordConfirm = sr_post_string_without_truncation('password_confirm', 255);
    $registrationConsentValues = sr_member_registration_policy_consent_values_from_post($registrationPolicyDocuments);
    if ($profileFieldsEnabled) {
        $profileValues = sr_member_profile_values_from_post($profilePolicies, sr_member_empty_profile());
        $profileExtraValues = sr_member_profile_extra_field_input_values($profileExtraFieldDefinitions);
    }
    if ($registrationIdentityFieldsLocked && is_array($registrationIdentityResult)) {
        if (!empty($registrationIdentitySnapshot['name'])) {
            $values['display_name'] = (string) $registrationIdentitySnapshot['name'];
        } elseif ((string) ($registrationIdentityResult['name_hash'] ?? '') !== ''
            && function_exists('sr_identity_verification_hmac_field')
            && sr_identity_verification_hmac_field($config, 'name', (string) $values['display_name']) !== (string) $registrationIdentityResult['name_hash']
        ) {
            $errors[] = '본인확인 이름과 가입 이름이 일치하지 않습니다.';
        }
        if (!empty($registrationIdentityResult['birth_date'])) {
            $profileValues['birth_date'] = (string) $registrationIdentityResult['birth_date'];
        }
        if (array_key_exists('age_over_19', $registrationIdentityResult) && $registrationIdentityResult['age_over_19'] !== null) {
            $profileValues['is_adult'] = (int) $registrationIdentityResult['age_over_19'] === 1 ? '1' : '0';
        }
        foreach ($profileExtraFieldDefinitions as $profileExtraFieldDefinition) {
            $profileExtraKey = (string) ($profileExtraFieldDefinition['key'] ?? '');
            if (!in_array($profileExtraKey, ['phone', 'mobile', 'mobile_phone', 'phone_number'], true)) {
                continue;
            }
            if (!empty($registrationIdentitySnapshot['phone'])) {
                $profileExtraValues[$profileExtraKey] = (string) $registrationIdentitySnapshot['phone'];
            } elseif ((string) ($registrationIdentityResult['phone_hash'] ?? '') !== ''
                && function_exists('sr_identity_verification_hmac_field')
                && function_exists('sr_identity_verification_digits')
                && sr_identity_verification_hmac_field($config, 'phone', sr_identity_verification_digits((string) ($profileExtraValues[$profileExtraKey] ?? ''))) !== (string) $registrationIdentityResult['phone_hash']
            ) {
                $errors[] = '본인확인 휴대폰 번호와 가입 휴대폰 번호가 일치하지 않습니다.';
            }
        }
    }

    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = sr_t('member::action.register.email_invalid');
    }

    if ($values['login_id'] !== '' && !sr_member_is_valid_login_id($values['login_id'])) {
        $errors[] = sr_t('member::action.register.login_id_invalid');
    }

    foreach (sr_member_display_name_validation_errors((string) $values['display_name']) as $displayNameError) {
        $errors[] = $displayNameError;
    }

    foreach (sr_member_nickname_validation_errors($pdo, (string) $values['nickname'], $memberSettings) as $nicknameError) {
        $errors[] = $nicknameError;
    }

    $errors = array_merge($errors, sr_member_registration_extension_validation_errors($pdo, $registrationExtensionContracts, $registrationExtensionValues, [
        'site' => $site,
        'values' => $values,
    ]));

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

    $errors = array_merge($errors, sr_member_registration_policy_consent_validation_errors($registrationPolicyDocuments, $registrationConsentValues));

    if ($profileFieldsEnabled) {
        foreach (sr_member_profile_validation_errors($profileValues, $profilePolicies, ['validate_avatar' => false]) as $profileError) {
            $errors[] = $profileError;
        }
        foreach (sr_member_validate_profile_extra_field_values($profileExtraFieldDefinitions, $profileExtraValues) as $profileError) {
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

    if ($errors === [] && $registrationIdentitySatisfied) {
        if (
            is_array($registrationIdentityResult)
            && function_exists('sr_identity_verification_result_duplicate_account')
            && sr_identity_verification_result_duplicate_account($pdo, (int) $registrationIdentityResult['id']) !== null
        ) {
            $registrationIdentitySatisfied = false;
            $registrationIdentityResult = null;
            $registrationIdentityReturnToken = '';
            $registrationIdentityFieldsLocked = false;
            $errors[] = function_exists('sr_identity_verification_duplicate_identity_message')
                ? sr_identity_verification_duplicate_identity_message()
                : '이미 다른 계정에 연결된 본인확인 정보입니다.';
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
            $transactionPolicyDocumentState = sr_member_registration_policy_documents($pdo);
            if ($transactionPolicyDocumentState['errors'] !== []) {
                throw new RuntimeException(implode(' ', $transactionPolicyDocumentState['errors']));
            }
            $transactionPolicyDocuments = $transactionPolicyDocumentState['documents'];
            sr_member_record_registration_policy_consents($pdo, $accountId, $transactionPolicyDocuments, $registrationConsentValues);
            if ($profileFieldsEnabled) {
                sr_member_save_profile($pdo, $accountId, $profileValues);
                sr_member_save_profile_extra_field_values($pdo, $accountId, $profileExtraFieldDefinitions, $profileExtraValues);
            }
            if (!empty($memberSettings['nickname_enabled']) && (string) $values['nickname'] !== '') {
                sr_member_set_nickname($pdo, $accountId, (string) $values['nickname']);
            }
            if (is_array($registrationIdentityResult) && function_exists('sr_identity_verification_link_result_to_account')) {
                sr_identity_verification_link_result_to_account($pdo, (int) $registrationIdentityResult['id'], (int) $accountId, $registrationIdentityPurpose);
            }
            $registrationExtensionMetadata = sr_member_registration_extension_save($pdo, $registrationExtensionContracts, $accountId, $registrationExtensionValues, [
                'site' => $site,
                'values' => $values,
            ]);

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
            $extensionError = sr_member_registration_extension_exception_message($registrationExtensionContracts, $exception);
            if ($exception instanceof SrIdentityVerificationDuplicateException) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = $extensionError !== '' ? $extensionError : sr_t('member::action.register.create_failed');
            }
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
                    'registration_extensions' => $registrationExtensionMetadata ?? [],
                ],
            ]);

            $newAccount = sr_member_find_by_id($pdo, $accountId);
            if ($emailVerificationEnabled) {
                $_SESSION['sr_member_login_notice'] = sr_t('member::action.register.email_verification_notice');
                sr_redirect('/login');
            }

            $loginResult = $newAccount !== null ? sr_member_login_or_start_mfa($pdo, $newAccount, 'register', '/account') : 'session_failed';
            if ($loginResult === 'mfa_required') {
                sr_redirect('/login/mfa');
            }
            if ($loginResult === 'logged_in') {
                if (sr_member_mfa_login_setup_required($pdo, $newAccount)) {
                    sr_member_redirect_mfa_setup_required();
                }
                sr_redirect('/account');
            }

            $_SESSION['sr_member_login_notice'] = sr_t('member::action.register.login_session_failed_notice');
            sr_redirect('/login');
        }
    }
}

$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'register');
include $memberSkinView;
