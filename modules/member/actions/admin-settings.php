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
$integerSettingKeys = sr_member_integer_setting_keys();

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/member-settings', 'edit');

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
    foreach (sr_member_profile_field_definitions() as $definition) {
        $enabledKey = (string) $definition['enabled_key'];
        $requiredKey = (string) $definition['required_key'];
        $settings[$enabledKey] = ($_POST[$enabledKey] ?? '') === '1';
        $settings[$requiredKey] = ($_POST[$requiredKey] ?? '') === '1';
        if ($settings[$requiredKey] && !$settings[$enabledKey]) {
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
            ['nickname_enabled', $settings['nickname_enabled'] ? '1' : '0', 'bool'],
            ['nickname_required', $settings['nickname_required'] ? '1' : '0', 'bool'],
            ['registration_terms_document_key', (string) $settings['registration_terms_document_key'], 'string'],
            ['registration_privacy_document_key', (string) $settings['registration_privacy_document_key'], 'string'],
            ['registration_marketing_document_key', (string) $settings['registration_marketing_document_key'], 'string'],
            ['member_skin_key', (string) $settings['member_skin_key'], 'string'],
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
                'nickname_enabled' => (bool) $settings['nickname_enabled'],
                'nickname_required' => (bool) $settings['nickname_required'],
                'registration_policy_documents' => [
                    'terms' => (string) $settings['registration_terms_document_key'],
                    'privacy' => (string) $settings['registration_privacy_document_key'],
                    'marketing' => (string) $settings['registration_marketing_document_key'],
                ],
                'login_identifier' => (string) $settings['login_identifier'],
                'member_skin_key' => (string) $settings['member_skin_key'],
                'profile_fields' => sr_member_profile_field_policies($settings),
                'profile_extra_fields' => sr_member_profile_extra_field_definitions($settings),
                'profile_field_order' => sr_member_profile_field_order_items($settings, sr_member_profile_extra_field_definitions($settings)),
                'removed_profile_extra_field_keys' => $removedProfileExtraFieldKeys,
                'removed_profile_extra_field_value_count' => $deletedProfileExtraFieldValues,
                'removed_profile_extra_field_value_count_estimate' => $removedProfileExtraFieldValueCount,
            ],
        ]);

    $notice = sr_t('member::action.admin_settings.saved');
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/member-settings');
}

include SR_ROOT . '/modules/member/views/admin-settings.php';
