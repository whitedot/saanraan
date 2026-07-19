<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/member-settings', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_member_settings($pdo);
$adminFormDraftKey = 'member.settings';
$adminFormDraftContext = 'default';
$adminFormDraftFingerprint = sr_admin_form_draft_fingerprint($settings);
$adminFormDraft = null;
$integerSettingKeys = sr_member_integer_setting_keys();
$memberMfaProviderDefinitions = sr_member_mfa_provider_definitions($pdo);
$memberIdentityVerificationModuleAvailable = sr_module_enabled($pdo, 'identity_verification')
    && is_file(SR_ROOT . '/modules/identity_verification/helpers.php');
if ($memberIdentityVerificationModuleAvailable) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}
$memberIdentityRegistrationAvailable = $memberIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'member.registration');
$memberIdentityWithdrawalAvailable = $memberIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'member.withdrawal');
$memberIdentityAccountSecurityAvailable = $memberIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'member.account_security');

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/member-settings', 'edit');

    $adminFormAction = sr_post_string('admin_form_action', 30);
    if ($adminFormAction === 'save_draft') {
        try {
            sr_admin_form_draft_save($pdo, (int) $account['id'], $adminFormDraftKey, $adminFormDraftContext, $_POST, $adminFormDraftFingerprint);
            sr_admin_redirect_with_result(sr_admin_action_result([], '회원 환경설정 입력값을 임시저장했습니다.'), '/admin/member-settings');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'member_settings_draft_save_failed');
            sr_admin_redirect_with_result(sr_admin_action_result(['임시저장 중 오류가 발생했습니다.'], ''), '/admin/member-settings');
        }
    }
    if ($adminFormAction === 'discard_draft') {
        sr_admin_form_draft_delete($pdo, (int) $account['id'], $adminFormDraftKey, $adminFormDraftContext);
        sr_admin_redirect_with_result(sr_admin_action_result([], '회원 환경설정 임시저장본을 삭제했습니다.'), '/admin/member-settings');
    }

    $previousProfileExtraFieldDefinitions = sr_member_profile_extra_field_definitions($settings);
    $previousProfileExtraFieldKeys = [];
    foreach ($previousProfileExtraFieldDefinitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        if ($key !== '') {
            $previousProfileExtraFieldKeys[$key] = true;
        }
    }
    $removedProfileExtraFieldKeys = [];
    $removedProfileExtraFieldValueCount = 0;

    $settings['allow_registration'] = ($_POST['allow_registration'] ?? '') === '1';
    $settings['email_verification_enabled'] = ($_POST['email_verification_enabled'] ?? '') === '1';
    $settings['identity_registration_mode'] = sr_member_identity_requirement_mode($_POST['identity_registration_mode'] ?? null);
    $settings['identity_withdrawal_required'] = ($_POST['identity_withdrawal_required'] ?? '') === '1';
    $settings['identity_account_security_required'] = ($_POST['identity_account_security_required'] ?? '') === '1';
    if (!$memberIdentityRegistrationAvailable && $settings['identity_registration_mode'] !== 'disabled') {
        $errors[] = '회원가입 본인확인을 사용하려면 본인확인 사용을 켜고 회원가입 목적을 지원하는 제공자를 설정하세요.';
        $settings['identity_registration_mode'] = 'disabled';
    }
    if (!$memberIdentityWithdrawalAvailable && $settings['identity_withdrawal_required']) {
        $errors[] = '회원탈퇴 본인확인을 사용하려면 본인확인 사용을 켜고 회원탈퇴 목적을 지원하는 제공자를 설정하세요.';
        $settings['identity_withdrawal_required'] = false;
    }
    if (!$memberIdentityAccountSecurityAvailable && $settings['identity_account_security_required']) {
        $errors[] = '계정보안작업 본인확인을 사용하려면 본인확인 사용을 켜고 계정 보안 목적을 지원하는 제공자를 설정하세요.';
        $settings['identity_account_security_required'] = false;
    }
    $settings['mfa_login_mode'] = sr_member_mfa_login_mode($_POST['mfa_login_mode'] ?? null);
    $settings['mfa_login_enabled'] = $settings['mfa_login_mode'] !== 'disabled';
    $postedMfaProviderKeys = isset($_POST['mfa_login_providers']) && is_array($_POST['mfa_login_providers'])
        ? $_POST['mfa_login_providers']
        : [];
    $mfaLoginProviderKeys = [];
    foreach (sr_member_mfa_valid_provider_keys($postedMfaProviderKeys) as $providerKey) {
        if (isset($memberMfaProviderDefinitions[$providerKey]) && !empty($memberMfaProviderDefinitions[$providerKey]['login_supported'])) {
            $mfaLoginProviderKeys[] = $providerKey;
        }
    }
    if ($settings['mfa_login_mode'] !== 'disabled' && $mfaLoginProviderKeys === []) {
        $errors[] = sr_t('member::action.admin_settings.mfa_provider_required');
    }
    if ($settings['mfa_login_mode'] === 'required') {
        $mfaSetupProviderKeys = [];
        $mfaNoSetupProviderKeys = [];
        foreach ($mfaLoginProviderKeys as $providerKey) {
            if (!empty($memberMfaProviderDefinitions[$providerKey]['account_setup_supported'])) {
                $mfaSetupProviderKeys[] = $providerKey;
            }
            if (in_array((string) ($memberMfaProviderDefinitions[$providerKey]['method'] ?? ''), ['email', 'identity'], true)) {
                $mfaNoSetupProviderKeys[] = $providerKey;
            }
        }
        if ($mfaSetupProviderKeys === [] && $mfaNoSetupProviderKeys === []) {
            $errors[] = sr_t('member::action.admin_settings.mfa_setup_provider_required');
        }
    }
    $settings['mfa_login_providers_json'] = sr_member_mfa_provider_keys_json($mfaLoginProviderKeys);
    $settings['nickname_enabled'] = ($_POST['nickname_enabled'] ?? '') === '1';
    $settings['nickname_required'] = $settings['nickname_enabled'];
    $settings['registration_terms_document_key'] = sr_member_registration_policy_document_clean_key(sr_post_string('registration_terms_document_key', 80));
    $settings['registration_privacy_document_key'] = sr_member_registration_policy_document_clean_key(sr_post_string('registration_privacy_document_key', 80));
    $settings['registration_marketing_document_key'] = sr_member_registration_policy_document_clean_key(sr_post_string('registration_marketing_document_key', 80));
    $registrationPolicyDocumentLabels = [
        'registration_terms_document_key' => sr_t('member::settings.registration_terms_document'),
        'registration_privacy_document_key' => sr_t('member::settings.registration_privacy_document'),
        'registration_marketing_document_key' => sr_t('member::settings.registration_marketing_document'),
    ];
    foreach (['registration_terms_document_key', 'registration_privacy_document_key'] as $registrationPolicyDocumentSettingKey) {
        if ((string) $settings[$registrationPolicyDocumentSettingKey] === '') {
            $errors[] = sr_t('member::action.admin_settings.policy_document_required', [
                'label' => $registrationPolicyDocumentLabels[$registrationPolicyDocumentSettingKey],
            ]);
            continue;
        }
        if (!is_array(sr_member_registration_policy_document_snapshot($pdo, (string) $settings[$registrationPolicyDocumentSettingKey]))) {
            $errors[] = sr_t('member::action.admin_settings.policy_document_invalid', [
                'label' => $registrationPolicyDocumentLabels[$registrationPolicyDocumentSettingKey],
            ]);
        }
    }
    if (
        (string) $settings['registration_marketing_document_key'] !== ''
        && !is_array(sr_member_registration_policy_document_snapshot($pdo, (string) $settings['registration_marketing_document_key']))
    ) {
        $errors[] = sr_t('member::action.admin_settings.policy_document_invalid', [
            'label' => $registrationPolicyDocumentLabels['registration_marketing_document_key'],
        ]);
    }
    $memberSkinKey = sr_post_string('member_skin_key', 40);
    if (!isset(sr_member_skin_options()[$memberSkinKey])) {
        $errors[] = sr_t('member::action.admin_settings.skin_invalid');
    } else {
        $settings['member_skin_key'] = $memberSkinKey;
    }
    $memberAvatarSizeLimits = sr_member_profile_image_size_limits();
    $memberAvatarSizeValues = [];
    foreach (sr_member_profile_image_size_setting_keys() as $memberAvatarSizeKey => $memberAvatarSizeSettingKey) {
        $memberAvatarSizeValue = sr_admin_post_int_in_range(
            $memberAvatarSizeSettingKey,
            (int) $memberAvatarSizeLimits['min'],
            (int) $memberAvatarSizeLimits['max']
        );
        if ($memberAvatarSizeValue === null) {
            $errors[] = sr_t('member::action.admin_settings.profile_image_size_invalid', [
                'label' => (string) (sr_member_profile_image_size_options()[$memberAvatarSizeKey] ?? $memberAvatarSizeKey),
                'min' => (string) $memberAvatarSizeLimits['min'],
                'max' => (string) $memberAvatarSizeLimits['max'],
            ]);
            continue;
        }
        $settings[$memberAvatarSizeSettingKey] = $memberAvatarSizeValue;
        $memberAvatarSizeValues[$memberAvatarSizeKey] = $memberAvatarSizeValue;
    }
    if (
        count($memberAvatarSizeValues) === 3
        && (
            $memberAvatarSizeValues['small'] > $memberAvatarSizeValues['medium']
            || $memberAvatarSizeValues['medium'] > $memberAvatarSizeValues['large']
        )
    ) {
        $errors[] = sr_t('member::action.admin_settings.profile_image_size_order_invalid');
    }
    foreach (sr_member_profile_field_definitions() as $definition) {
        $enabledKey = (string) $definition['enabled_key'];
        $requiredKey = (string) $definition['required_key'];
        $settings[$enabledKey] = ($_POST[$enabledKey] ?? '') === '1';
        $settings[$requiredKey] = ($_POST[$requiredKey] ?? '') === '1';
        if ($enabledKey === 'profile_image_enabled' && !$settings[$enabledKey]) {
            $settings[$requiredKey] = false;
        } elseif ($settings[$requiredKey] && !$settings[$enabledKey]) {
            $settings[$enabledKey] = true;
        }
    }
    $profileFieldsInput = sr_post_string_without_truncation('profile_fields_json', 20000);
    foreach (sr_member_profile_extra_field_definitions_input_errors($profileFieldsInput) as $profileFieldError) {
        $errors[] = $profileFieldError;
    }
    if (is_string($profileFieldsInput)) {
        $profileFieldsJson = sr_member_profile_extra_field_definitions_json_from_input($profileFieldsInput);
        if ($profileFieldsJson === null) {
            $errors[] = '프로필 추가 항목 설정을 저장할 수 없습니다.';
        } else {
            $settings['profile_fields_json'] = $profileFieldsJson;
        }
    }
    $nextProfileExtraFieldKeys = [];
    foreach (sr_member_profile_extra_field_definitions($settings) as $definition) {
        $key = (string) ($definition['key'] ?? '');
        if ($key !== '') {
            $nextProfileExtraFieldKeys[$key] = true;
        }
    }
    $removedProfileExtraFieldKeys = array_values(array_diff(array_keys($previousProfileExtraFieldKeys), array_keys($nextProfileExtraFieldKeys)));
    if ($removedProfileExtraFieldKeys !== []) {
        $removedProfileExtraFieldValueCount = sr_member_profile_extra_field_value_count_by_keys($pdo, $removedProfileExtraFieldKeys);
        if (($_POST['profile_removed_field_values_confirmed'] ?? '') !== '1') {
            $errors[] = '선택 프로필 항목을 삭제하면 전체 회원의 해당 저장값 '
                . number_format($removedProfileExtraFieldValueCount)
                . '건이 삭제됩니다. 경고를 확인한 뒤 다시 저장하세요.';
        }
    }
    $profileFieldOrderInput = sr_post_string_without_truncation('profile_field_order_json', 20000);
    $profileFieldOrderExtraDefinitions = sr_member_profile_extra_field_definitions($settings);
    if (!is_string($profileFieldOrderInput)) {
        $errors[] = '프로필 항목 순서를 저장할 수 없습니다.';
    } else {
        $settings['profile_field_order_json'] = sr_member_profile_field_order_json_from_input($profileFieldOrderInput, $profileFieldOrderExtraDefinitions);
    }

    foreach ($integerSettingKeys as $key => $limits) {
        $integerValue = sr_admin_post_int_in_range($key, (int) $limits['min'], (int) $limits['max']);
        if ($integerValue === null) {
            $errors[] = sr_t('member::action.admin_settings.integer_range', [
                'key' => $key,
                'min' => (string) $limits['min'],
                'max' => (string) $limits['max'],
            ]);
            continue;
        }

        $settings[$key] = $integerValue;
    }

    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'member' LIMIT 1");
    $stmt->execute();
    $memberModule = $stmt->fetch();
    if (!is_array($memberModule)) {
        $errors[] = sr_t('member::action.admin_settings.module_missing');
    }

    if ($errors === []) {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_module_settings
                (module_id, setting_key, setting_value, value_type, created_at, updated_at)
             VALUES
                (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = VALUES(value_type),
                updated_at = VALUES(updated_at)'
        );

        $rows = [
            ['allow_registration', $settings['allow_registration'] ? '1' : '0', 'bool'],
            ['email_verification_enabled', $settings['email_verification_enabled'] ? '1' : '0', 'bool'],
            ['identity_registration_mode', (string) $settings['identity_registration_mode'], 'string'],
            ['identity_withdrawal_required', $settings['identity_withdrawal_required'] ? '1' : '0', 'bool'],
            ['identity_account_security_required', $settings['identity_account_security_required'] ? '1' : '0', 'bool'],
            ['mfa_login_mode', (string) $settings['mfa_login_mode'], 'string'],
            ['mfa_login_enabled', $settings['mfa_login_enabled'] ? '1' : '0', 'bool'],
            ['mfa_login_providers_json', (string) $settings['mfa_login_providers_json'], 'json'],
            ['nickname_enabled', $settings['nickname_enabled'] ? '1' : '0', 'bool'],
            ['nickname_required', $settings['nickname_required'] ? '1' : '0', 'bool'],
            ['registration_terms_document_key', (string) $settings['registration_terms_document_key'], 'string'],
            ['registration_privacy_document_key', (string) $settings['registration_privacy_document_key'], 'string'],
            ['registration_marketing_document_key', (string) $settings['registration_marketing_document_key'], 'string'],
            ['member_skin_key', (string) $settings['member_skin_key'], 'string'],
            ['profile_image_size_small', (string) $settings['profile_image_size_small'], 'int'],
            ['profile_image_size_medium', (string) $settings['profile_image_size_medium'], 'int'],
            ['profile_image_size_large', (string) $settings['profile_image_size_large'], 'int'],
            ['profile_fields_json', (string) ($settings['profile_fields_json'] ?? '[]'), 'json'],
            ['profile_field_order_json', (string) ($settings['profile_field_order_json'] ?? '[]'), 'json'],
        ];
        foreach (sr_member_profile_field_definitions() as $definition) {
            $enabledKey = (string) $definition['enabled_key'];
            $requiredKey = (string) $definition['required_key'];
            $rows[] = [$enabledKey, !empty($settings[$enabledKey]) ? '1' : '0', 'bool'];
            $rows[] = [$requiredKey, !empty($settings[$requiredKey]) ? '1' : '0', 'bool'];
        }

        foreach ($integerSettingKeys as $key => $limits) {
            $rows[] = [$key, (string) $settings[$key], 'int'];
        }

        foreach ($rows as $row) {
            $stmt->execute([
                'module_id' => (int) $memberModule['id'],
                'setting_key' => $row[0],
                'setting_value' => $row[1],
                'value_type' => $row[2],
                'created_at' => sr_now(),
                'updated_at' => sr_now(),
            ]);
        }
        $deletedProfileExtraFieldValues = $removedProfileExtraFieldKeys !== []
            ? sr_member_delete_profile_extra_field_values_by_keys($pdo, $removedProfileExtraFieldKeys)
            : 0;
        sr_clear_module_settings_cache('member');

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'member.settings.updated',
            'target_type' => 'module',
            'target_id' => 'member',
            'result' => 'success',
            'message' => 'Member settings updated.',
            'metadata' => [
                'allow_registration' => (bool) $settings['allow_registration'],
                'email_verification_enabled' => (bool) $settings['email_verification_enabled'],
                'identity_registration_mode' => (string) $settings['identity_registration_mode'],
                'identity_withdrawal_required' => (bool) $settings['identity_withdrawal_required'],
                'identity_account_security_required' => (bool) $settings['identity_account_security_required'],
                'mfa_login_mode' => (string) $settings['mfa_login_mode'],
                'mfa_login_enabled' => (bool) $settings['mfa_login_enabled'],
                'mfa_login_providers' => sr_member_mfa_setting_provider_keys($settings['mfa_login_providers_json'] ?? '[]'),
                'nickname_enabled' => (bool) $settings['nickname_enabled'],
                'nickname_required' => (bool) $settings['nickname_required'],
                'registration_policy_documents' => [
                    'terms' => (string) $settings['registration_terms_document_key'],
                    'privacy' => (string) $settings['registration_privacy_document_key'],
                    'marketing' => (string) $settings['registration_marketing_document_key'],
                ],
                'login_identifier' => (string) $settings['login_identifier'],
                'member_skin_key' => (string) $settings['member_skin_key'],
                'profile_image_enabled' => (bool) $settings['profile_image_enabled'],
                'profile_image_sizes' => [
                    'small' => (int) $settings['profile_image_size_small'],
                    'medium' => (int) $settings['profile_image_size_medium'],
                    'large' => (int) $settings['profile_image_size_large'],
                ],
                'profile_fields' => sr_member_profile_field_policies($settings),
                'profile_extra_fields' => sr_member_profile_extra_field_definitions($settings),
                'profile_field_order' => sr_member_profile_field_order_items($settings, sr_member_profile_extra_field_definitions($settings)),
                'removed_profile_extra_field_keys' => $removedProfileExtraFieldKeys,
                'removed_profile_extra_field_value_count' => $deletedProfileExtraFieldValues,
                'removed_profile_extra_field_value_count_estimate' => $removedProfileExtraFieldValueCount,
                'integer_settings' => array_reduce(array_keys(sr_member_integer_setting_keys()), static function (array $carry, string $key) use ($settings): array {
                    $carry[$key] = (int) ($settings[$key] ?? 0);
                    return $carry;
                }, []),
            ],
        ]);

        sr_admin_form_draft_delete($pdo, (int) $account['id'], $adminFormDraftKey, $adminFormDraftContext);
        $notice = sr_t('member::action.admin_settings.saved');
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/member-settings');
}
$adminFormDraft = sr_admin_form_draft_with_state(
    sr_admin_form_draft_get($pdo, (int) $account['id'], $adminFormDraftKey, $adminFormDraftContext),
    $adminFormDraftFingerprint
);
if (is_array($adminFormDraft)) {
    $memberDraftBooleanKeys = [
        'allow_registration', 'email_verification_enabled', 'identity_withdrawal_required',
        'identity_account_security_required', 'nickname_enabled',
    ];
    foreach (sr_member_profile_field_definitions() as $memberDraftProfileDefinition) {
        $memberDraftBooleanKeys[] = (string) $memberDraftProfileDefinition['enabled_key'];
        $memberDraftBooleanKeys[] = (string) $memberDraftProfileDefinition['required_key'];
    }
    $memberDraftPayload = (array) $adminFormDraft['payload'];
    $settings = sr_admin_form_draft_apply_settings($settings, $memberDraftPayload, $memberDraftBooleanKeys);
    $settings['mfa_login_providers_json'] = sr_member_mfa_provider_keys_json(
        isset($memberDraftPayload['mfa_login_providers']) && is_array($memberDraftPayload['mfa_login_providers'])
            ? $memberDraftPayload['mfa_login_providers']
            : []
    );
}

include SR_ROOT . '/modules/member/views/admin-settings.php';
