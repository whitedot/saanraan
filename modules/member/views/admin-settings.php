<?php

$adminPageTitle = sr_t('member::ui.member.settings.6b4c84f7');
$memberSettingsHelpOpenLabel = sr_t('member::help.open');
$memberSettingsHelp = [
    'allow_registration' => [
        'id' => 'member-settings-help-allow-registration-modal',
        'title' => sr_t('member::help.settings.allow_registration.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.allow_registration.body.1',
            'member::help.settings.allow_registration.body.2',
        ]),
    ],
    'email_verification' => [
        'id' => 'member-settings-help-email-verification-modal',
        'title' => sr_t('member::help.settings.email_verification.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.email_verification.body.1',
            'member::help.settings.email_verification.body.2',
        ]),
    ],
    'login_identifier' => [
        'id' => 'member-settings-help-login-identifier-modal',
        'title' => sr_t('member::help.settings.login_identifier.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.login_identifier.body.1',
            'member::help.settings.login_identifier.body.2',
        ]),
    ],
    'member_skin' => [
        'id' => 'member-settings-help-member-skin-modal',
        'title' => sr_t('member::help.settings.member_skin.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.member_skin.body.1',
            'member::help.settings.member_skin.body.2',
        ]),
    ],
    'profile_field' => [
        'id' => 'member-settings-help-profile-field-modal',
        'title' => sr_t('member::help.settings.profile_field.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.profile_field.body.1',
            'member::help.settings.profile_field.body.2',
        ]),
    ],
    'throttle_window' => [
        'id' => 'member-settings-help-throttle-window-modal',
        'title' => sr_t('member::help.settings.throttle_window.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.throttle_window.body.1',
            'member::help.settings.throttle_window.body.2',
        ]),
    ],
    'session_lifetime' => [
        'id' => 'member-settings-help-session-lifetime-modal',
        'title' => '회원 세션 유효시간',
        'body_html' => sr_member_admin_help_body_html([
            '회원 로그인 세션은 마지막 활동 기준이 아니라 로그인 또는 세션 회전 시점부터 계산하는 절대 유효시간입니다.',
            '시간을 줄이면 기존 세션에도 읽기 시점 상한으로 적용되므로 저장한 관리자 본인도 다음 요청에서 로그아웃될 수 있습니다.',
            '브라우저 세션 쿠키와 PHP 런타임 세션 수명은 별도이므로, 브라우저를 닫거나 런타임 세션이 먼저 정리되면 이 시간보다 빨리 재로그인이 필요할 수 있습니다.',
        ]),
    ],
    'throttle_account' => [
        'id' => 'member-settings-help-throttle-account-modal',
        'title' => sr_t('member::help.settings.throttle_account.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.throttle_account.body.1',
            'member::help.settings.throttle_account.body.2',
        ]),
    ],
    'throttle_ip' => [
        'id' => 'member-settings-help-throttle-ip-modal',
        'title' => sr_t('member::help.settings.throttle_ip.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.throttle_ip.body.1',
            'member::help.settings.throttle_ip.body.2',
        ]),
    ],
];
$memberProfileFixedFields = [];
foreach (sr_member_profile_field_definitions() as $memberProfileFixedFieldKey => $memberProfileFixedFieldDefinition) {
    $enabledKey = (string) $memberProfileFixedFieldDefinition['enabled_key'];
    $requiredKey = (string) $memberProfileFixedFieldDefinition['required_key'];
    $memberProfileFixedFields[] = [
        'key' => (string) $memberProfileFixedFieldKey,
        'label' => (string) $memberProfileFixedFieldDefinition['label'],
        'enabled_key' => $enabledKey,
        'required_key' => $requiredKey,
        'enabled' => !empty($settings[$enabledKey]),
        'required' => !empty($settings[$requiredKey]),
    ];
}
$memberProfileExtraFieldDefinitions = sr_member_profile_extra_field_definitions($settings);
$memberProfileOriginalExtraFieldKeys = array_values(array_filter(array_map(static function (array $definition): string {
    return (string) ($definition['key'] ?? '');
}, $memberProfileExtraFieldDefinitions), static function (string $key): bool {
    return $key !== '';
}));
$memberProfileFieldOrderItems = sr_member_profile_field_order_items($settings, $memberProfileExtraFieldDefinitions);
$memberProfileFieldOrderJson = sr_js_json_encode(array_map(static function (array $item): string {
    return (string) ($item['kind'] ?? '') . ':' . (string) ($item['key'] ?? '');
}, $memberProfileFieldOrderItems));
$memberProfileExtraFieldsJson = sr_js_json_encode($memberProfileExtraFieldDefinitions);
$memberRegistrationPolicyDocumentOptions = [];
foreach (['registration_terms_document_key', 'registration_privacy_document_key', 'registration_marketing_document_key'] as $memberRegistrationPolicyDocumentSettingKey) {
    $memberRegistrationPolicyDocumentOptions += sr_member_registration_policy_document_options($pdo, (string) ($settings[$memberRegistrationPolicyDocumentSettingKey] ?? ''));
}
$memberRegistrationPolicyDocumentSelectOptionsHtml = static function (string $currentKey, bool $allowEmpty = false) use ($memberRegistrationPolicyDocumentOptions): string {
    $currentKey = sr_member_registration_policy_document_clean_key($currentKey);
    $html = $allowEmpty ? '<option value="">' . sr_e('선택 안 함') . '</option>' : '<option value="">' . sr_e('정책 문서 선택') . '</option>';
    foreach ($memberRegistrationPolicyDocumentOptions as $documentKey => $documentOption) {
        $label = (string) ($documentOption['title'] ?? $documentKey);
        $html .= '<option value="' . sr_e((string) $documentKey) . '"' . ($currentKey === (string) $documentKey ? ' selected' : '') . '>' . sr_e($label) . '</option>';
    }

    return $html;
};
$memberMfaProviderDefinitions = isset($memberMfaProviderDefinitions) && is_array($memberMfaProviderDefinitions)
    ? $memberMfaProviderDefinitions
    : sr_member_mfa_provider_definitions($pdo);
$memberMfaModuleReferences = [];
foreach ($memberMfaProviderDefinitions as $memberMfaProviderDefinition) {
    $memberMfaProviderModuleKey = is_array($memberMfaProviderDefinition)
        ? (string) ($memberMfaProviderDefinition['provider_module_key'] ?? '')
        : '';
    if ($memberMfaProviderModuleKey !== '' && $memberMfaProviderModuleKey !== 'member') {
        $memberMfaModuleReferences[$memberMfaProviderModuleKey] = ['module_key' => $memberMfaProviderModuleKey];
    }
}
$memberMfaLoginMode = sr_member_mfa_login_mode($settings['mfa_login_mode'] ?? null, $settings['mfa_login_enabled'] ?? null);
$memberMfaLoginProviderKeys = sr_member_mfa_setting_provider_keys($settings['mfa_login_providers_json'] ?? '["email","totp"]');
$memberIdentityRegistrationAvailable = isset($memberIdentityRegistrationAvailable)
    ? (bool) $memberIdentityRegistrationAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'member.registration'));
$memberIdentityWithdrawalAvailable = isset($memberIdentityWithdrawalAvailable)
    ? (bool) $memberIdentityWithdrawalAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'member.withdrawal'));
$memberIdentityAccountSecurityAvailable = isset($memberIdentityAccountSecurityAvailable)
    ? (bool) $memberIdentityAccountSecurityAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'member.account_security'));
$memberIdentityUnavailable = !$memberIdentityRegistrationAvailable
    || !$memberIdentityWithdrawalAvailable
    || !$memberIdentityAccountSecurityAvailable;
$memberIdentityRegistrationInputAttributes = $memberIdentityRegistrationAvailable
    ? ''
    : ' disabled aria-describedby="member-settings-identity-unavailable"';
$memberIdentityWithdrawalInputAttributes = $memberIdentityWithdrawalAvailable
    ? ''
    : ' disabled aria-describedby="member-settings-identity-unavailable"';
$memberIdentityAccountSecurityInputAttributes = $memberIdentityAccountSecurityAvailable
    ? ''
    : ' disabled aria-describedby="member-settings-identity-unavailable"';
$memberIdentityModuleReferences = [['module_key' => 'identity_verification', 'path' => '/admin/identity-providers']];
$memberPolicyDocumentModuleReferences = [['module_key' => 'policy_documents']];
$memberRuntimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$memberRuntimeSessionLifetimeSeconds = (int) ($memberRuntimeConfig['session']['lifetime_seconds'] ?? 86400);
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$memberSettingsSectionNavItems = [
    'member-settings-section-registration' => '가입/인증',
    'member-settings-section-skin' => '스킨',
    'member-settings-section-profile' => '프로필',
    'member-settings-section-policy-consent' => '가입 약관',
    'member-settings-section-mfa' => '2차 인증',
    'member-settings-section-login' => '로그인 제한',
    'member-settings-section-register-limit' => '가입 제한',
    'member-settings-section-password' => '비밀번호',
    'member-settings-section-email' => '이메일',
];
?>
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="회원 설정 섹션">
    <?php $memberSettingsSectionNavIndex = 0; ?>
    <?php foreach ($memberSettingsSectionNavItems as $memberSettingsSectionId => $memberSettingsSectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $memberSettingsSectionId); ?>" class="tab-trigger-underline-justified<?php echo $memberSettingsSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $memberSettingsSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $memberSettingsSectionLabel); ?>
        </a>
        <?php $memberSettingsSectionNavIndex++; ?>
    <?php } ?>
</nav>
<form id="member-settings-form" method="post" action="<?php echo sr_e(sr_url('/admin/member-settings')); ?>" class="admin-form ui-form-theme" data-sr-validate-form>
    <?php echo sr_csrf_field(); ?>
    <?php echo sr_admin_form_draft_status_html($adminFormDraft ?? null, 'member-settings-form'); ?>

    <section id="member-settings-section-registration" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.text.564c3c84')); ?></h2>
        <div class="form-grid">
            <div class="form-row">
                <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.member.8df81cb2'), $memberSettingsHelp['allow_registration']['id'], $memberSettingsHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.member.8df81cb2')); ?></span></span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('modules_member_admin_settings_allow_registration', 'allow_registration', '1', !empty($settings['allow_registration']), '사용'); ?>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.email.active.f166bfe8'), $memberSettingsHelp['email_verification']['id'], $memberSettingsHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.email.active.f166bfe8')); ?></span></span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('modules_member_admin_settings_email_verification_enabled', 'email_verification_enabled', '1', !empty($settings['email_verification_enabled']), '사용'); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="modules_member_admin_settings_identity_registration_mode"><?php echo sr_e('회원가입 전 본인확인'); ?><?php echo $memberIdentityRegistrationAvailable ? ' <span class="sr-required-label">(필수)</span>' : ''; ?></label>
                <div class="form-field">
                    <?php echo sr_admin_radio_toggle_group_html('modules_member_admin_settings_identity_registration_mode', 'identity_registration_mode', sr_member_identity_registration_mode_options(), $memberIdentityRegistrationAvailable ? (string) ($settings['identity_registration_mode'] ?? 'disabled') : 'disabled', true, $memberIdentityRegistrationInputAttributes); ?>
                    <small class="form-help">필수는 가입 전 본인확인을 완료해야 가입할 수 있고, 선택은 가입 화면에 본인확인 버튼만 표시합니다.</small>
                    <?php echo sr_admin_module_reference_list_html($pdo, $memberIdentityModuleReferences); ?>
                    <?php if ($memberIdentityUnavailable) { ?>
                        <p id="member-settings-identity-unavailable" class="form-help form-help-warning">
                            <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 본인확인 사용이 꺼져 있거나 목적에 맞는 제공자가 준비되지 않은 항목은 사용할 수 없습니다.
                        </p>
                    <?php } ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="modules_member_admin_settings_identity_withdrawal_required"><?php echo sr_e('회원탈퇴 본인확인'); ?></label>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('modules_member_admin_settings_identity_withdrawal_required', 'identity_withdrawal_required', '1', $memberIdentityWithdrawalAvailable && !empty($settings['identity_withdrawal_required']), '사용', '', $memberIdentityWithdrawalInputAttributes); ?>
                    <small class="form-help">회원탈퇴를 제출하기 전에 본인확인을 요구합니다.</small>
                    <?php echo sr_admin_module_reference_list_html($pdo, $memberIdentityModuleReferences); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="modules_member_admin_settings_identity_account_security_required"><?php echo sr_e('계정보안작업 본인확인'); ?></label>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('modules_member_admin_settings_identity_account_security_required', 'identity_account_security_required', '1', $memberIdentityAccountSecurityAvailable && !empty($settings['identity_account_security_required']), '사용', '', $memberIdentityAccountSecurityInputAttributes); ?>
                    <small class="form-help">비밀번호 변경, 2차 인증 설정/해제 같은 보안 작업을 실행할 때마다 본인확인을 요구합니다.</small>
                    <?php echo sr_admin_module_reference_list_html($pdo, $memberIdentityModuleReferences); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="modules_member_admin_settings_nickname_enabled"><?php echo sr_e(sr_t('member::ui.nickname')); ?></label>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('modules_member_admin_settings_nickname_enabled', 'nickname_enabled', '1', !empty($settings['nickname_enabled']), '사용'); ?>
                    <small class="form-help"><?php echo sr_e(sr_t('member::settings.nickname.help')); ?></small>
                </div>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.login.ab1cc2ca'), $memberSettingsHelp['login_identifier']['id'], $memberSettingsHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.login.ab1cc2ca')); ?></span></span>
            <div class="form-field">
                <strong><?php echo sr_e((string) (sr_member_login_identifier_options()[(string) $settings['login_identifier']] ?? sr_t('member::ui.email.login.d1f22b60'))); ?></strong>
                <small class="form-help"><?php echo sr_e(sr_t('member::ui.email.login.login.login.44f3662f')); ?></small>
            </div>
        </div>
    </section>

    <section id="member-settings-section-skin" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.text.b5361f64')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_member_skin_key', sr_t('member::ui.member.3b335eb1'), $memberSettingsHelp['member_skin']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="member_admin_settings_member_skin_key" name="member_skin_key" class="form-select">
                                    <?php foreach (sr_member_skin_options() as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo (string) $settings['member_skin_key'] === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e(sr_t('member::settings.profile_image_enabled.label')); ?></span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('member_admin_settings_profile_image_enabled', 'profile_image_enabled', '1', !empty($settings['profile_image_enabled']), sr_t('member::settings.profile_image_enabled.choice'), '0', ' data-member-profile-image-enabled-toggle'); ?>
                <small class="form-help"><?php echo sr_e(sr_t('member::settings.profile_image_enabled.help')); ?></small>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e(sr_t('member::settings.profile_image_size.label')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
            <div class="form-field">
                <div class="member-admin-profile-image-size-fields">
                    <?php $memberAvatarSizeLimits = sr_member_profile_image_size_limits(); ?>
                    <?php foreach (sr_member_profile_image_size_setting_keys() as $memberAvatarSizeKey => $memberAvatarSizeSettingKey) { ?>
                        <label class="member-admin-profile-image-size-field" for="member_admin_settings_<?php echo sr_e($memberAvatarSizeSettingKey); ?>">
                            <span><?php echo sr_e((string) (sr_member_profile_image_size_options()[$memberAvatarSizeKey] ?? $memberAvatarSizeKey)); ?></span>
                            <span class="input-group admin-input-unit">
                                <input id="member_admin_settings_<?php echo sr_e($memberAvatarSizeSettingKey); ?>" class="form-input" type="number" name="<?php echo sr_e($memberAvatarSizeSettingKey); ?>" value="<?php echo sr_e((string) sr_member_profile_image_size_pixels($memberAvatarSizeKey, $settings)); ?>" min="<?php echo sr_e((string) $memberAvatarSizeLimits['min']); ?>" max="<?php echo sr_e((string) $memberAvatarSizeLimits['max']); ?>" required>
                                <span class="input-group-text">px</span>
                            </span>
                        </label>
                    <?php } ?>
                </div>
                <small class="form-help"><?php echo sr_e(sr_t('member::settings.profile_image_size.help')); ?></small>
            </div>
        </div>
    </section>

    <section id="member-settings-section-profile" class="card admin-list-card admin-list-form" data-admin-section-anchor data-member-profile-extra-fields-builder>
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.select.da5d4203')); ?></h2>
            <div class="admin-row-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="member-profile-extra-field-modal" data-overlay="#member-profile-extra-field-modal" data-member-profile-extra-field-add>항목 추가</button>
            </div>
        </div>
        <div class="table-wrapper" data-member-profile-extra-field-table-wrap>
            <table class="table table-list" data-member-profile-extra-field-table>
                <caption class="sr-only">선택 프로필 항목 목록</caption>
                <thead>
                    <tr>
                        <th class="member-profile-extra-field-order-cell">순서</th>
                        <th>라벨</th>
                        <th>유형</th>
                        <th>표시</th>
                        <th>개인정보</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody data-member-profile-field-list></tbody>
            </table>
        </div>
        <p class="form-help">
            항목을 추가하거나 숨기거나 필수로 바꾸면 회원가입, 내 프로필 수정, 관리자 회원 수정 화면이 바로 달라집니다.<br>
            필수로 바꾸기 전에는 기존 회원에게 빈 값이 있어도 괜찮은지 확인하세요. 값이 없는 회원은 다음 저장 때 입력이 필요할 수 있습니다.<br>
            항목을 삭제하면 전체 회원의 해당 저장값도 저장 시 함께 삭제됩니다. 삭제 전 경고를 확인해야 저장할 수 있습니다.<br>
            기본 항목은 삭제할 수 없습니다. 사용 여부와 순서만 바꿀 수 있습니다.
        </p>
        <p class="admin-empty-state" data-member-profile-extra-field-empty hidden>추가 프로필 항목이 없습니다.</p>
        <textarea id="member_admin_settings_profile_fields_json" name="profile_fields_json" hidden data-member-profile-extra-fields-json><?php echo sr_e($memberProfileExtraFieldsJson); ?></textarea>
        <textarea id="member_admin_settings_profile_field_order_json" name="profile_field_order_json" hidden data-member-profile-field-order-json><?php echo sr_e($memberProfileFieldOrderJson); ?></textarea>
        <input type="hidden" name="profile_removed_field_values_confirmed" value="0" data-member-profile-removed-field-values-confirmed>
        <div data-member-profile-fixed-field-inputs></div>
        <script type="application/json" data-member-profile-fixed-fields-json><?php echo sr_js_json_encode($memberProfileFixedFields); ?></script>
        <script type="application/json" data-member-profile-original-extra-field-keys-json><?php echo sr_js_json_encode($memberProfileOriginalExtraFieldKeys); ?></script>
    </section>

    <section id="member-settings-section-policy-consent" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::settings.registration_policy_documents')); ?></h2>
        <div class="form-grid">
            <div class="form-row">
                <label class="form-label" for="member_admin_settings_registration_terms_document_key"><?php echo sr_e(sr_t('member::settings.registration_terms_document')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="form-field">
                    <select id="member_admin_settings_registration_terms_document_key" name="registration_terms_document_key" class="form-select" required>
                        <?php echo $memberRegistrationPolicyDocumentSelectOptionsHtml((string) ($settings['registration_terms_document_key'] ?? 'member_terms'), false); ?>
                    </select>
                    <p class="form-help"><?php echo sr_e(sr_t('member::settings.registration_terms_document.help')); ?></p>
                    <?php echo sr_admin_module_reference_list_html($pdo, $memberPolicyDocumentModuleReferences); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="member_admin_settings_registration_privacy_document_key"><?php echo sr_e(sr_t('member::settings.registration_privacy_document')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="form-field">
                    <select id="member_admin_settings_registration_privacy_document_key" name="registration_privacy_document_key" class="form-select" required>
                        <?php echo $memberRegistrationPolicyDocumentSelectOptionsHtml((string) ($settings['registration_privacy_document_key'] ?? 'member_privacy_collection'), false); ?>
                    </select>
                    <p class="form-help"><?php echo sr_e(sr_t('member::settings.registration_privacy_document.help')); ?></p>
                    <?php echo sr_admin_module_reference_list_html($pdo, $memberPolicyDocumentModuleReferences); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="member_admin_settings_registration_marketing_document_key"><?php echo sr_e(sr_t('member::settings.registration_marketing_document')); ?></label>
                <div class="form-field">
                    <select id="member_admin_settings_registration_marketing_document_key" name="registration_marketing_document_key" class="form-select">
                        <?php echo $memberRegistrationPolicyDocumentSelectOptionsHtml((string) ($settings['registration_marketing_document_key'] ?? 'member_marketing'), true); ?>
                    </select>
                    <p class="form-help"><?php echo sr_e(sr_t('member::settings.registration_marketing_document.help')); ?></p>
                    <?php echo sr_admin_module_reference_list_html($pdo, $memberPolicyDocumentModuleReferences); ?>
                </div>
            </div>
        </div>
        <p class="form-help"><?php echo sr_e(sr_t('member::settings.registration_policy_documents.help')); ?></p>
    </section>

    <section id="member-settings-section-mfa" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.mfa_totp.title')); ?></h2>
        <p class="form-help form-help-info">운영자가 허용한 방식으로만 로그인 2차 인증을 진행합니다. 이메일 인증 코드는 바로 사용할 수 있고, SMS나 다른 인증 방식은 <a href="<?php echo sr_e(sr_url('/admin/modules')); ?>" target="_blank" rel="noopener noreferrer">모듈 관리</a>에서 해당 기능을 제공하는 모듈을 켜면 선택 항목에 표시됩니다.</p>
        <div class="form-row">
            <span class="form-label">로그인 2차 인증 정책</span>
            <div class="form-field">
                <div class="btn-group" role="radiogroup" aria-label="로그인 2차 인증 정책">
                    <?php
                    $memberMfaModeLabels = sr_member_mfa_login_mode_options();
                    $memberMfaModeKeys = array_keys($memberMfaModeLabels);
                    ?>
                    <?php foreach ($memberMfaModeLabels as $memberMfaModeKey => $memberMfaModeLabel) { ?>
                        <?php
                        $memberMfaModeInputId = 'modules_member_admin_settings_mfa_login_mode_' . (string) $memberMfaModeKey;
                        $memberMfaModeGroupClass = 'btn-group-middle';
                        if ((string) $memberMfaModeKey === (string) ($memberMfaModeKeys[0] ?? '')) {
                            $memberMfaModeGroupClass = 'btn-group-start';
                        } elseif ((string) $memberMfaModeKey === (string) ($memberMfaModeKeys[count($memberMfaModeKeys) - 1] ?? '')) {
                            $memberMfaModeGroupClass = 'btn-group-end';
                        }
                        ?>
                        <input
                            id="<?php echo sr_e($memberMfaModeInputId); ?>"
                            type="radio"
                            name="mfa_login_mode"
                            value="<?php echo sr_e((string) $memberMfaModeKey); ?>"
                            class="form-choice-toggle-input sr-only"
                            required
                            <?php echo $memberMfaLoginMode === (string) $memberMfaModeKey ? ' checked' : ''; ?>
                        >
                        <label
                            for="<?php echo sr_e($memberMfaModeInputId); ?>"
                            class="btn btn-choice-light <?php echo sr_e($memberMfaModeGroupClass); ?>"
                        ><?php echo sr_admin_choice_label_html((string) ($memberMfaModeLabels[$memberMfaModeKey] ?? $memberMfaModeLabel)); ?></label>
                    <?php } ?>
                </div>
                <p class="form-help">필수는 허용된 방식으로 로그인 2차 인증을 요구하고, 인증 앱 OTP처럼 등록이 필요한 방식이 아직 없는 회원은 보안 화면으로 이동시킵니다. 선택은 사용할 수 있는 방식이 있는 회원에게만 2차 인증을 요구합니다. 사용안함은 로그인 2차 인증과 신규 등록을 중지합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">인증 방식</span>
            <div class="form-field">
                <?php if ($memberMfaProviderDefinitions === []) { ?>
                    <p class="form-help">사용 가능한 2차 인증 provider 계약이 없습니다.</p>
                <?php } else { ?>
                    <?php
                    $memberMfaLoginProviderDefinitionKeys = [];
                    foreach ($memberMfaProviderDefinitions as $memberMfaProviderDefinitionKey => $memberMfaProviderDefinition) {
                        if (!empty($memberMfaProviderDefinition['login_supported'])) {
                            $memberMfaLoginProviderDefinitionKeys[] = (string) $memberMfaProviderDefinitionKey;
                        }
                    }
                    ?>
                    <div class="btn-group" role="group" aria-label="로그인 2차 인증 방식">
                        <?php foreach ($memberMfaProviderDefinitions as $memberMfaProviderKey => $memberMfaProvider) { ?>
                            <?php if (empty($memberMfaProvider['login_supported'])) { continue; } ?>
                            <?php
                            $memberMfaProviderInputId = 'modules_member_admin_settings_mfa_provider_' . (string) $memberMfaProviderKey;
                            $memberMfaProviderGroupClass = count($memberMfaLoginProviderDefinitionKeys) > 1 ? 'btn-group-middle' : '';
                            if (count($memberMfaLoginProviderDefinitionKeys) > 1 && (string) $memberMfaProviderKey === (string) ($memberMfaLoginProviderDefinitionKeys[0] ?? '')) {
                                $memberMfaProviderGroupClass = 'btn-group-start';
                            } elseif (count($memberMfaLoginProviderDefinitionKeys) > 1 && (string) $memberMfaProviderKey === (string) ($memberMfaLoginProviderDefinitionKeys[count($memberMfaLoginProviderDefinitionKeys) - 1] ?? '')) {
                                $memberMfaProviderGroupClass = 'btn-group-end';
                            }
                            ?>
                            <input
                                id="<?php echo sr_e($memberMfaProviderInputId); ?>"
                                type="checkbox"
                                name="mfa_login_providers[]"
                                value="<?php echo sr_e((string) $memberMfaProviderKey); ?>"
                                class="form-choice-toggle-input sr-only"
                                <?php echo in_array((string) $memberMfaProviderKey, $memberMfaLoginProviderKeys, true) ? ' checked' : ''; ?>
                            >
                            <label
                                for="<?php echo sr_e($memberMfaProviderInputId); ?>"
                                class="btn btn-choice-light <?php echo sr_e($memberMfaProviderGroupClass); ?>"
                                title="<?php echo sr_e((string) ($memberMfaProvider['description'] ?? '')); ?>"
                            ><?php echo sr_admin_choice_label_html((string) ($memberMfaProvider['label'] ?? $memberMfaProviderKey)); ?></label>
                        <?php } ?>
                    </div>
                    <?php echo sr_admin_module_reference_list_html($pdo, $memberMfaModuleReferences); ?>
                <?php } ?>
            </div>
        </div>
    </section>

    <section id="member-settings-section-login" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.login.b726ae4b')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_session_lifetime_seconds', '회원 세션 유효시간', $memberSettingsHelp['session_lifetime']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_admin_settings_session_lifetime_seconds" type="number" name="session_lifetime_seconds" value="<?php echo sr_e((string) $settings['session_lifetime_seconds']); ?>" required class="form-input" min="1800" max="2592000">
                    <span class="input-group-text">초</span>
                </div>
                <small class="form-help">1800초부터 2592000초까지 설정할 수 있습니다. 현재 PHP 런타임 세션 저장소 수명은 <?php echo sr_e(number_format($memberRuntimeSessionLifetimeSeconds)); ?>초이며, 브라우저 세션 쿠키나 런타임 세션이 먼저 끝나면 회원 세션 만료 전에도 재로그인이 필요할 수 있습니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_login_throttle_window_seconds', sr_t('member::ui.text.c7f70c10'), $memberSettingsHelp['throttle_window']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_admin_settings_login_throttle_window_seconds" type="number" name="login_throttle_window_seconds" value="<?php echo sr_e((string) $settings['login_throttle_window_seconds']); ?>" required class="form-input" min="0" max="86400">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_login_throttle_account_limit', sr_t('member::ui.text.d78a4171'), $memberSettingsHelp['throttle_account']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_login_throttle_account_limit" type="number" name="login_throttle_account_limit" value="<?php echo sr_e((string) $settings['login_throttle_account_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_login_throttle_ip_limit', sr_t('member::ui.ip.62b8799d'), $memberSettingsHelp['throttle_ip']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_login_throttle_ip_limit" type="number" name="login_throttle_ip_limit" value="<?php echo sr_e((string) $settings['login_throttle_ip_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section id="member-settings-section-register-limit" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.member.52394c42')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_register_throttle_window_seconds', sr_t('member::ui.text.c7f70c10'), $memberSettingsHelp['throttle_window']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_admin_settings_register_throttle_window_seconds" type="number" name="register_throttle_window_seconds" value="<?php echo sr_e((string) $settings['register_throttle_window_seconds']); ?>" required class="form-input" min="0" max="86400">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_register_throttle_ip_limit', sr_t('member::ui.ip.62b8799d'), $memberSettingsHelp['throttle_ip']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_register_throttle_ip_limit" type="number" name="register_throttle_ip_limit" value="<?php echo sr_e((string) $settings['register_throttle_ip_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section id="member-settings-section-password" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.password.settings.be683f9d')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_password_reset_throttle_window_seconds', sr_t('member::ui.text.c7f70c10'), $memberSettingsHelp['throttle_window']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_admin_settings_password_reset_throttle_window_seconds" type="number" name="password_reset_throttle_window_seconds" value="<?php echo sr_e((string) $settings['password_reset_throttle_window_seconds']); ?>" required class="form-input" min="0" max="86400">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_password_reset_throttle_account_limit', sr_t('member::ui.text.d78a4171'), $memberSettingsHelp['throttle_account']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_password_reset_throttle_account_limit" type="number" name="password_reset_throttle_account_limit" value="<?php echo sr_e((string) $settings['password_reset_throttle_account_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_password_reset_throttle_ip_limit', sr_t('member::ui.ip.62b8799d'), $memberSettingsHelp['throttle_ip']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_password_reset_throttle_ip_limit" type="number" name="password_reset_throttle_ip_limit" value="<?php echo sr_e((string) $settings['password_reset_throttle_ip_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section id="member-settings-section-email" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.email.2fbad242')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_email_verification_throttle_window_seconds', sr_t('member::ui.text.c7f70c10'), $memberSettingsHelp['throttle_window']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_admin_settings_email_verification_throttle_window_seconds" type="number" name="email_verification_throttle_window_seconds" value="<?php echo sr_e((string) $settings['email_verification_throttle_window_seconds']); ?>" required class="form-input" min="0" max="86400">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_email_verification_throttle_account_limit', sr_t('member::ui.text.d78a4171'), $memberSettingsHelp['throttle_account']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_email_verification_throttle_account_limit" type="number" name="email_verification_throttle_account_limit" value="<?php echo sr_e((string) $settings['email_verification_throttle_account_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_email_verification_throttle_ip_limit', sr_t('member::ui.ip.62b8799d'), $memberSettingsHelp['throttle_ip']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_email_verification_throttle_ip_limit" type="number" name="email_verification_throttle_ip_limit" value="<?php echo sr_e((string) $settings['email_verification_throttle_ip_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary admin-form-final-save"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
        <button type="submit" name="admin_form_action" value="save_draft" class="btn btn-solid-light admin-form-draft-save" formnovalidate>임시저장</button>
        <?php if (is_array($adminFormDraft ?? null)) { ?>
            <button type="submit" name="admin_form_action" value="discard_draft" class="btn btn-outline-danger admin-form-draft-delete" formnovalidate>임시저장 삭제</button>
        <?php } ?>
    </div>
</form>
<?php echo sr_admin_form_draft_restore_script($adminFormDraft ?? null, 'member-settings-form'); ?>

<div id="member-profile-extra-field-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="member-profile-extra-field-modal-label" aria-hidden="true" inert data-member-profile-extra-field-modal>
    <div class="modal-dialog modal-dialog-lg">
        <div class="modal-content ui-form-theme">
            <div class="modal-header">
                <h3 id="member-profile-extra-field-modal-label" class="modal-title" data-member-profile-extra-field-modal-title>추가 프로필 항목</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#member-profile-extra-field-modal"><?php echo sr_material_icon_html('close'); ?></button>
            </div>
            <div class="modal-body">
                <input type="hidden" value="" data-member-profile-extra-field-index>
                <input type="hidden" value="extra" data-member-profile-extra-field-kind>
                <input type="hidden" value="" data-member-profile-extra-field-original-key>
                <div class="form-row" data-member-profile-extra-field-extra-only>
                    <label class="form-label" for="member_profile_extra_field_key">Key <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="member_profile_extra_field_key" type="text" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input data-member-profile-extra-field-input="key" data-overlay-focus class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="member_profile_extra_field_label">라벨 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="member_profile_extra_field_label" type="text" maxlength="120" required data-member-profile-extra-field-input="label" class="form-input form-control-full">
                    </div>
                </div>
                <div class="form-row" data-member-profile-extra-field-extra-only>
                    <label class="form-label" for="member_profile_extra_field_type">유형 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <select id="member_profile_extra_field_type" required data-member-profile-extra-field-input="type" class="form-select">
                            <option value="text">텍스트</option>
                            <option value="textarea">긴 텍스트</option>
                            <option value="select">선택</option>
                            <option value="checkbox">체크박스</option>
                        </select>
                    </div>
                </div>
                <div class="form-row" data-member-profile-extra-field-options-row data-member-profile-extra-field-extra-only>
                    <label class="form-label" for="member_profile_extra_field_options">선택지 <span class="sr-required-label" data-member-profile-extra-field-options-required hidden>(필수)</span></label>
                    <div class="form-field">
                        <textarea id="member_profile_extra_field_options" rows="4" maxlength="6000" data-member-profile-extra-field-input="options" class="form-textarea form-control-full"></textarea>
                    </div>
                </div>
                <div class="form-row" data-member-profile-extra-field-fixed-only hidden>
                    <span class="form-label">사용 여부</span>
                    <div class="form-field">
                        <label class="form-check form-label" for="member_profile_extra_field_enabled">
                            <input id="member_profile_extra_field_enabled" type="checkbox" value="1" class="form-switch form-switch-light" data-member-profile-extra-field-input="enabled">
                            <?php echo sr_admin_choice_label_html('사용'); ?>
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <span class="form-label">필수 여부</span>
                    <div class="form-field">
                        <label class="form-check form-label" for="member_profile_extra_field_required">
                            <input id="member_profile_extra_field_required" type="checkbox" value="1" class="form-switch form-switch-light" data-member-profile-extra-field-input="required">
                            <?php echo sr_admin_choice_label_html('필수 입력'); ?>
                        </label>
                    </div>
                </div>
                <div class="form-row" data-member-profile-extra-field-extra-only>
                    <label class="form-label" for="member_profile_extra_field_visibility">공개 범위</label>
                    <div class="form-field">
                        <select id="member_profile_extra_field_visibility" data-member-profile-extra-field-input="visibility" class="form-select">
                            <option value="public">공개</option>
                            <option value="admin">관리자 전용</option>
                        </select>
                    </div>
                </div>
                <div class="form-row" data-member-profile-extra-field-extra-only>
                    <span class="form-label">표시 위치</span>
                    <div class="form-field">
                        <label class="form-check form-label" for="member_profile_extra_field_show_on_profile">
                            <input id="member_profile_extra_field_show_on_profile" type="checkbox" value="1" class="form-switch form-switch-light" data-member-profile-extra-field-input="show_on_profile">
                            <?php echo sr_admin_choice_label_html('프로필 화면 표시'); ?>
                        </label>
                        <label class="form-check form-label" for="member_profile_extra_field_show_in_admin">
                            <input id="member_profile_extra_field_show_in_admin" type="checkbox" value="1" class="form-switch form-switch-light" data-member-profile-extra-field-input="show_in_admin">
                            <?php echo sr_admin_choice_label_html('관리자 수정 화면 표시'); ?>
                        </label>
                    </div>
                </div>
                <div class="form-row" data-member-profile-extra-field-extra-only>
                    <label class="form-label" for="member_profile_extra_field_privacy_purpose">수집·이용 목적</label>
                    <div class="form-field">
                        <input id="member_profile_extra_field_privacy_purpose" type="text" maxlength="255" data-member-profile-extra-field-input="privacy_purpose" class="form-input form-control-full">
                        <p class="form-help">이 정보를 왜 받는지 적습니다. 예: 회원 연락을 위한 휴대폰 번호 확인</p>
                    </div>
                </div>
                <div class="form-row" data-member-profile-extra-field-extra-only>
                    <label class="form-label" for="member_profile_extra_field_export_policy">내 정보 사본에 포함</label>
                    <div class="form-field">
                        <select id="member_profile_extra_field_export_policy" data-member-profile-extra-field-input="export_policy" class="form-select">
                            <option value="include">포함함</option>
                            <option value="exclude">포함하지 않음</option>
                        </select>
                        <p class="form-help">회원이 자신의 개인정보 사본을 요청할 때 이 항목의 값을 함께 제공할지 정합니다.</p>
                    </div>
                </div>
                <div class="form-row" data-member-profile-extra-field-extra-only>
                    <label class="form-label" for="member_profile_extra_field_cleanup_policy">계정 정리 시 처리</label>
                    <div class="form-field">
                        <select id="member_profile_extra_field_cleanup_policy" data-member-profile-extra-field-input="cleanup_policy" class="form-select">
                            <option value="anonymize">개인정보 제거</option>
                            <option value="retain">그대로 보관</option>
                        </select>
                        <p class="form-help">회원 탈퇴 등으로 계정을 정리할 때 이 항목의 값을 지울지 정합니다.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#member-profile-extra-field-modal">닫기</button>
                <button type="button" class="btn btn-solid-primary modal-action" data-member-profile-extra-field-save>적용</button>
            </div>
        </div>
    </div>
</div>

<?php foreach ($memberSettingsHelp as $memberSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $memberSettingsHelpModal['id'], (string) $memberSettingsHelpModal['title'], (string) $memberSettingsHelpModal['body_html']); ?>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function memberProfileExtraFieldAllowedType(value) {
        return ['text', 'textarea', 'select', 'checkbox'].indexOf(value) !== -1 ? value : 'text';
    }

    function memberProfileExtraFieldNormalize(raw) {
        var definitions = [];
        var seen = {};
        if (!Array.isArray(raw)) {
            return definitions;
        }
        raw.forEach(function (item) {
            if (!item || typeof item !== 'object' || definitions.length >= 20) {
                return;
            }
            var key = String(item.key || '').trim().toLowerCase();
            if (!/^[a-z][a-z0-9_]{1,59}$/.test(key) || seen[key]) {
                return;
            }
            var label = String(item.label || '').replace(/\s+/g, ' ').trim().slice(0, 120);
            if (label === '') {
                return;
            }
            var type = memberProfileExtraFieldAllowedType(String(item.type || 'text'));
            var options = [];
            if (type === 'select') {
                if (Array.isArray(item.options)) {
                    item.options.forEach(function (option) {
                        var value = String(option || '').replace(/\s+/g, ' ').trim().slice(0, 120);
                        if (value !== '' && options.indexOf(value) === -1 && options.length < 50) {
                            options.push(value);
                        }
                    });
                }
                if (options.length === 0) {
                    return;
                }
            }
            definitions.push({
                key: key,
                label: label,
                type: type,
                required: !!item.required,
                options: options,
                visibility: String(item.visibility || 'public') === 'admin' ? 'admin' : 'public',
                show_on_profile: Object.prototype.hasOwnProperty.call(item, 'show_on_profile') ? !!item.show_on_profile : true,
                show_in_admin: !!item.show_in_admin,
                privacy_purpose: String(item.privacy_purpose || '').trim(),
                export_policy: String(item.export_policy || 'include') === 'exclude' ? 'exclude' : 'include',
                cleanup_policy: String(item.cleanup_policy || 'anonymize') === 'retain' ? 'retain' : 'anonymize'
            });
            seen[key] = true;
        });
        return definitions;
    }

    function memberProfileExtraFieldParse(textarea) {
        if (!textarea) {
            return [];
        }
        try {
            return memberProfileExtraFieldNormalize(JSON.parse(textarea.value || '[]'));
        } catch (error) {
            return [];
        }
    }

    function memberProfileExtraFieldTypeLabel(type) {
        var labels = {
            text: '텍스트',
            textarea: '긴 텍스트',
            select: '선택',
            checkbox: '체크박스'
        };
        return labels[type] || labels.text;
    }

    function memberProfileExtraFieldPolicyLabel(field) {
        var exportLabel = field.export_policy === 'exclude' ? '사본에 포함하지 않음' : '사본에 포함';
        var cleanupLabel = field.cleanup_policy === 'retain' ? '계정 정리 후에도 보관' : '계정 정리 시 제거';
        return exportLabel + ' / ' + cleanupLabel;
    }

    function memberProfileExtraFieldRandomKey(definitions) {
        var existing = {};
        memberProfileExtraFieldNormalize(definitions).forEach(function (field) {
            existing[field.key] = true;
        });
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var randomPart = function () {
            var value = '';
            var bytes = null;
            if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
                bytes = new Uint8Array(10);
                window.crypto.getRandomValues(bytes);
            }
            for (var i = 0; i < 10; i++) {
                var index = bytes ? bytes[i] % chars.length : Math.floor(Math.random() * chars.length);
                value += chars.charAt(index);
            }
            return value;
        };
        for (var attempt = 0; attempt < 20; attempt++) {
            var key = 'field_' + randomPart();
            if (!existing[key]) {
                return key;
            }
        }

        return 'field_' + String(Date.now()).slice(-10);
    }

    function memberProfileExtraFieldParseJson(text) {
        try {
            var decoded = JSON.parse(text || '[]');
            return Array.isArray(decoded) ? decoded : [];
        } catch (error) {
            return [];
        }
    }

    function memberProfileFixedFieldParse(root) {
        var script = root ? root.querySelector('[data-member-profile-fixed-fields-json]') : null;
        var raw = script ? memberProfileExtraFieldParseJson(script.textContent || '[]') : [];
        return raw.map(function (field) {
            return {
                key: String(field.key || ''),
                label: String(field.label || ''),
                enabled_key: String(field.enabled_key || ''),
                required_key: String(field.required_key || ''),
                enabled: !!field.enabled,
                required: !!field.required
            };
        }).filter(function (field) {
            return field.key !== '' && field.label !== '' && field.enabled_key !== '' && field.required_key !== '';
        });
    }

    function memberProfileFieldOrderParse(root, fixedFields, definitions) {
        var textarea = root ? root.querySelector('[data-member-profile-field-order-json]') : null;
        var raw = textarea ? memberProfileExtraFieldParseJson(textarea.value || '[]') : [];
        var fixedMap = {};
        var extraMap = {};
        var seen = {};
        var order = [];
        fixedFields.forEach(function (field) {
            fixedMap[field.key] = true;
        });
        definitions.forEach(function (field) {
            extraMap[field.key] = true;
        });
        raw.forEach(function (item) {
            if (typeof item !== 'string') {
                return;
            }
            item = item.trim();
            var parts = item.split(':');
            if (parts.length !== 2) {
                return;
            }
            var kind = parts[0];
            var key = parts[1];
            var id = kind + ':' + key;
            if (seen[id]) {
                return;
            }
            if (kind === 'fixed' && fixedMap[key]) {
                order.push({ kind: 'fixed', key: key });
                seen[id] = true;
            } else if (kind === 'extra' && extraMap[key]) {
                order.push({ kind: 'extra', key: key });
                seen[id] = true;
            }
        });
        fixedFields.forEach(function (field) {
            var id = 'fixed:' + field.key;
            if (!seen[id]) {
                order.push({ kind: 'fixed', key: field.key });
                seen[id] = true;
            }
        });
        definitions.forEach(function (field) {
            var id = 'extra:' + field.key;
            if (!seen[id]) {
                order.push({ kind: 'extra', key: field.key });
                seen[id] = true;
            }
        });
        return order;
    }

    function memberProfileFieldOrderWrite(root, order) {
        var textarea = root ? root.querySelector('[data-member-profile-field-order-json]') : null;
        if (!textarea) {
            return;
        }
        textarea.value = JSON.stringify(order.map(function (item) {
            return item.kind + ':' + item.key;
        }), null, 2);
    }

    function memberProfileOriginalExtraFieldKeys(root) {
        var script = root ? root.querySelector('[data-member-profile-original-extra-field-keys-json]') : null;
        return (script ? memberProfileExtraFieldParseJson(script.textContent || '[]') : []).map(function (key) {
            return String(key || '');
        }).filter(function (key, index, keys) {
            return key !== '' && keys.indexOf(key) === index;
        });
    }

    function memberProfileRemovedExtraFieldKeys(root) {
        var textarea = root ? root.querySelector('[data-member-profile-extra-fields-json]') : null;
        var current = {};
        memberProfileExtraFieldParse(textarea).forEach(function (field) {
            if (field.key) {
                current[field.key] = true;
            }
        });
        return memberProfileOriginalExtraFieldKeys(root).filter(function (key) {
            return !current[key];
        });
    }

    function memberProfileFixedFieldWrite(root, fixedFields) {
        var holder = root ? root.querySelector('[data-member-profile-fixed-field-inputs]') : null;
        var script = root ? root.querySelector('[data-member-profile-fixed-fields-json]') : null;
        var avatarEnabledToggle = document.querySelector('[data-member-profile-image-enabled-toggle]');
        if (!holder) {
            return;
        }
        if (script) {
            script.textContent = JSON.stringify(fixedFields);
        }
        holder.innerHTML = '';
        fixedFields.forEach(function (field) {
            [
                [field.enabled_key, field.enabled ? '1' : '0'],
                [field.required_key, field.required ? '1' : '0']
            ].forEach(function (item) {
                if (item[0] === 'profile_image_enabled' && avatarEnabledToggle) {
                    avatarEnabledToggle.checked = item[1] === '1';
                    return;
                }
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = item[0];
                input.value = item[1];
                holder.appendChild(input);
            });
        });
    }

    function memberProfileExtraFieldWrite(root, definitions, order, fixedFields) {
        var textarea = root ? root.querySelector('[data-member-profile-extra-fields-json]') : null;
        if (!textarea) {
            return;
        }
        textarea.value = JSON.stringify(memberProfileExtraFieldNormalize(definitions), null, 2);
        memberProfileFieldOrderWrite(root, order || memberProfileFieldOrderParse(root, fixedFields || [], definitions));
        memberProfileFixedFieldWrite(root, fixedFields || memberProfileFixedFieldParse(root));
    }

    function memberProfileFieldAnimateOrder(root, layout, movedKey) {
        if (!root || !layout || !window.AdminShell || typeof window.AdminShell.animateReorderLayout !== 'function') {
            return;
        }
        var rows = Array.prototype.slice.call(root.querySelectorAll('[data-member-profile-field-row]'));
        var movedRow = rows.find(function (row) {
            return row.getAttribute('data-admin-reorder-key') === movedKey;
        }) || null;
        window.AdminShell.animateReorderLayout(rows, layout, movedRow ? [movedRow] : []);
    }

    function memberProfileFieldOrderMove(root, fromIndex, toIndex) {
        var textarea = root ? root.querySelector('[data-member-profile-extra-fields-json]') : null;
        var definitions = memberProfileExtraFieldParse(textarea);
        var fixedFields = memberProfileFixedFieldParse(root);
        var order = memberProfileFieldOrderParse(root, fixedFields, definitions);
        if (fromIndex < 0 || fromIndex >= order.length || toIndex < 0 || toIndex > order.length || fromIndex === toIndex) {
            return false;
        }
        var moved = order.splice(fromIndex, 1)[0];
        var movedKey = moved.kind + ':' + moved.key;
        var layout = window.AdminShell && typeof window.AdminShell.captureReorderLayout === 'function'
            ? window.AdminShell.captureReorderLayout(root.querySelectorAll('[data-member-profile-field-row]'))
            : null;
        if (toIndex > fromIndex) {
            toIndex -= 1;
        }
        order.splice(toIndex, 0, moved);
        memberProfileExtraFieldWrite(root, definitions, order, fixedFields);
        memberProfileExtraFieldRender(root);
        memberProfileFieldAnimateOrder(root, layout, movedKey);
        return true;
    }

    function memberProfileExtraFieldClearDropState(root) {
        if (!root) {
            return;
        }
        root.querySelectorAll('[data-member-profile-field-row]').forEach(function (row) {
            row.classList.remove('is-dragging', 'is-drop-before', 'is-drop-after');
        });
    }

    function memberProfileExtraFieldRender(root) {
        var textarea = root ? root.querySelector('[data-member-profile-extra-fields-json]') : null;
        var list = root ? root.querySelector('[data-member-profile-field-list]') : null;
        var empty = root ? root.querySelector('[data-member-profile-extra-field-empty]') : null;
        var tableWrap = root ? root.querySelector('[data-member-profile-extra-field-table-wrap]') : null;
        if (!textarea || !list) {
            return;
        }
        var definitions = memberProfileExtraFieldParse(textarea);
        var fixedFields = memberProfileFixedFieldParse(root);
        var order = memberProfileFieldOrderParse(root, fixedFields, definitions);
        var fixedByKey = {};
        var extraByKey = {};
        fixedFields.forEach(function (field) {
            fixedByKey[field.key] = field;
        });
        definitions.forEach(function (field) {
            extraByKey[field.key] = field;
        });
        list.innerHTML = '';
        if (empty) {
            empty.hidden = order.length > 0;
        }
        if (tableWrap) {
            tableWrap.hidden = order.length === 0;
        }
        memberProfileExtraFieldWrite(root, definitions, order, fixedFields);
        order.forEach(function (item, index) {
            var field = item.kind === 'fixed' ? fixedByKey[item.key] : extraByKey[item.key];
            if (!field) {
                return;
            }
            var row = document.createElement('tr');
            row.setAttribute('data-member-profile-field-row', '1');
            row.setAttribute('data-member-profile-extra-field-index-value', String(index));
            row.setAttribute('data-admin-reorder-key', item.kind + ':' + item.key);

            var orderCell = document.createElement('td');
            orderCell.className = 'member-profile-extra-field-order-cell';
            var orderGroup = document.createElement('div');
            orderGroup.className = 'admin-row-actions';
            var dragHandle = document.createElement('span');
            dragHandle.className = 'admin-drag-handle';
            dragHandle.draggable = true;
            dragHandle.setAttribute('aria-label', '드래그해서 순서 변경');
            dragHandle.setAttribute('title', '드래그해서 순서 변경');
            dragHandle.setAttribute('data-member-profile-extra-field-drag-handle', '1');
            dragHandle.setAttribute('data-member-profile-extra-field-index-value', String(index));
            dragHandle.innerHTML = '<span class="material-symbols-outlined admin-drag-handle-icon" aria-hidden="true">apps</span>';
            orderGroup.appendChild(dragHandle);
            [
                ['up', '위로', 'arrow_upward'],
                ['down', '아래로', 'arrow_downward']
            ].forEach(function (action) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm btn-icon btn-solid-light';
                button.setAttribute('aria-label', action[1]);
                button.setAttribute('title', action[1]);
                button.setAttribute('data-member-profile-extra-field-action', action[0]);
                button.setAttribute('data-member-profile-extra-field-index-value', String(index));
                button.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">' + action[2] + '</span>';
                button.disabled = (action[0] === 'up' && index === 0) || (action[0] === 'down' && index === order.length - 1);
                orderGroup.appendChild(button);
            });
            orderCell.appendChild(orderGroup);
            row.appendChild(orderCell);

            var labelCell = document.createElement('td');
            labelCell.textContent = field.label + (field.required ? ' (필수)' : '');
            row.appendChild(labelCell);

            var typeCell = document.createElement('td');
            typeCell.textContent = item.kind === 'fixed' || field.key === 'phone' ? '기본 항목' : memberProfileExtraFieldTypeLabel(field.type);
            row.appendChild(typeCell);

            var displayCell = document.createElement('td');
            var display = [];
            if (item.kind === 'fixed') {
                display.push(field.enabled ? '사용' : '사용안함');
                if (field.required) {
                    display.push('필수');
                }
            } else {
                display.push(field.visibility === 'admin' ? '관리자 전용' : '공개');
                if (field.show_on_profile) {
                    display.push('프로필');
                }
                if (field.show_in_admin) {
                    display.push('관리자 수정');
                }
            }
            displayCell.textContent = display.join(' / ');
            row.appendChild(displayCell);

            var privacyCell = document.createElement('td');
            privacyCell.textContent = item.kind === 'fixed' ? '기본 프로필 / 사본에 포함 / 계정 정리 시 제거' : (field.privacy_purpose || '수집·이용 목적 미입력') + ' / ' + memberProfileExtraFieldPolicyLabel(field);
            row.appendChild(privacyCell);

            var actionCell = document.createElement('td');
            actionCell.className = 'admin-table-actions-cell';
            var actionGroup = document.createElement('div');
            actionGroup.className = 'admin-row-actions';
            var actions = [['edit', '수정', 'edit'], ['remove', '제거', 'delete']];
            actions.forEach(function (action) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = action[0] === 'remove' ? 'btn btn-sm btn-icon btn-outline-danger' : 'btn btn-sm btn-icon btn-solid-light';
                button.setAttribute('aria-label', action[1]);
                button.setAttribute('title', action[1]);
                button.setAttribute('data-member-profile-extra-field-action', action[0]);
                button.setAttribute('data-member-profile-extra-field-index-value', String(index));
                if ((item.kind === 'fixed' || field.key === 'phone') && action[0] === 'remove') {
                    button.disabled = true;
                    button.setAttribute('aria-disabled', 'true');
                }
                button.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">' + action[2] + '</span>';
                actionGroup.appendChild(button);
            });
            actionCell.appendChild(actionGroup);
            row.appendChild(actionCell);

            list.appendChild(row);
        });
    }

    function memberProfileExtraFieldInput(modal, name) {
        return modal ? modal.querySelector('[data-member-profile-extra-field-input="' + name + '"]') : null;
    }

    function memberProfileExtraFieldSetModalMode(modal, isFixed) {
        if (!modal) {
            return;
        }
        modal.querySelectorAll('[data-member-profile-extra-field-extra-only]').forEach(function (row) {
            row.hidden = !!isFixed;
            row.querySelectorAll('input, select, textarea').forEach(function (control) {
                control.disabled = !!isFixed;
            });
        });
        modal.querySelectorAll('[data-member-profile-extra-field-fixed-only]').forEach(function (row) {
            row.hidden = !isFixed;
            row.querySelectorAll('input, select, textarea').forEach(function (control) {
                control.disabled = !isFixed;
            });
        });
    }

    function memberProfileExtraFieldSetModal(modal, field, index, kind) {
        if (!modal) {
            return;
        }
        kind = kind === 'fixed' ? 'fixed' : 'extra';
        field = field || {
            key: '',
            label: '',
            enabled: true,
            type: 'text',
            required: false,
            options: [],
            visibility: 'public',
            show_on_profile: true,
            show_in_admin: false,
            privacy_purpose: '',
            export_policy: 'include',
            cleanup_policy: 'anonymize'
        };
        var indexInput = modal.querySelector('[data-member-profile-extra-field-index]');
        var kindInput = modal.querySelector('[data-member-profile-extra-field-kind]');
        var originalKeyInput = modal.querySelector('[data-member-profile-extra-field-original-key]');
        var title = modal.querySelector('[data-member-profile-extra-field-modal-title]');
        if (indexInput) {
            indexInput.value = typeof index === 'number' && index >= 0 ? String(index) : '';
        }
        if (kindInput) {
            kindInput.value = kind;
        }
        if (originalKeyInput) {
            originalKeyInput.value = field.key || '';
        }
        if (title) {
            title.textContent = kind === 'fixed' ? '기본 프로필 항목 설정' : (typeof index === 'number' && index >= 0 ? '추가 프로필 항목 수정' : '추가 프로필 항목 추가');
        }
        memberProfileExtraFieldSetModalMode(modal, kind === 'fixed');
        memberProfileExtraFieldInput(modal, 'key').value = field.key || '';
        memberProfileExtraFieldInput(modal, 'label').value = field.label || '';
        memberProfileExtraFieldInput(modal, 'key').readOnly = kind === 'extra' && field.key === 'phone';
        memberProfileExtraFieldInput(modal, 'label').readOnly = kind === 'fixed' || (kind === 'extra' && field.key === 'phone');
        memberProfileExtraFieldInput(modal, 'enabled').checked = Object.prototype.hasOwnProperty.call(field, 'enabled') ? !!field.enabled : true;
        memberProfileExtraFieldInput(modal, 'type').value = memberProfileExtraFieldAllowedType(field.type || 'text');
        if (kind === 'extra') {
            memberProfileExtraFieldInput(modal, 'type').disabled = field.key === 'phone';
        }
        memberProfileExtraFieldInput(modal, 'options').value = Array.isArray(field.options) ? field.options.join("\n") : '';
        memberProfileExtraFieldInput(modal, 'required').checked = !!field.required;
        memberProfileExtraFieldInput(modal, 'visibility').value = field.visibility === 'admin' ? 'admin' : 'public';
        memberProfileExtraFieldInput(modal, 'show_on_profile').checked = Object.prototype.hasOwnProperty.call(field, 'show_on_profile') ? !!field.show_on_profile : true;
        memberProfileExtraFieldInput(modal, 'show_in_admin').checked = !!field.show_in_admin;
        memberProfileExtraFieldInput(modal, 'privacy_purpose').value = field.privacy_purpose || '';
        memberProfileExtraFieldInput(modal, 'export_policy').value = field.export_policy === 'exclude' ? 'exclude' : 'include';
        memberProfileExtraFieldInput(modal, 'cleanup_policy').value = field.cleanup_policy === 'retain' ? 'retain' : 'anonymize';
        memberProfileExtraFieldSyncOptions(modal);
    }

    function memberProfileExtraFieldSyncOptions(modal) {
        var type = memberProfileExtraFieldInput(modal, 'type');
        var options = memberProfileExtraFieldInput(modal, 'options');
        var requiredLabel = modal ? modal.querySelector('[data-member-profile-extra-field-options-required]') : null;
        var isSelect = type && type.value === 'select';
        if (options) {
            options.required = !!isSelect;
            options.closest('.form-row').hidden = !isSelect;
        }
        if (requiredLabel) {
            requiredLabel.hidden = !isSelect;
        }
    }

    function memberProfileExtraFieldCollect(modal, definitions) {
        var keyInput = memberProfileExtraFieldInput(modal, 'key');
        var labelInput = memberProfileExtraFieldInput(modal, 'label');
        var typeInput = memberProfileExtraFieldInput(modal, 'type');
        var optionsInput = memberProfileExtraFieldInput(modal, 'options');
        var indexInput = modal ? modal.querySelector('[data-member-profile-extra-field-index]') : null;
        var kindInput = modal ? modal.querySelector('[data-member-profile-extra-field-kind]') : null;
        var originalKeyInput = modal ? modal.querySelector('[data-member-profile-extra-field-original-key]') : null;
        var index = indexInput && indexInput.value !== '' ? parseInt(indexInput.value, 10) : -1;
        var kind = kindInput && kindInput.value === 'fixed' ? 'fixed' : 'extra';
        var originalKey = originalKeyInput ? String(originalKeyInput.value || '') : '';
        [keyInput, labelInput, typeInput, optionsInput].forEach(function (input) {
            if (input && typeof input.setCustomValidity === 'function') {
                input.setCustomValidity('');
            }
        });
        if (!labelInput || !memberProfileExtraFieldInput(modal, 'required')) {
            return null;
        }
        if (kind === 'fixed') {
            var enabled = !!memberProfileExtraFieldInput(modal, 'enabled').checked;
            var required = !!memberProfileExtraFieldInput(modal, 'required').checked;
            return {
                kind: 'fixed',
                index: index,
                field: {
                    enabled: enabled || required,
                    required: required
                }
            };
        }
        if (!keyInput || !typeInput || !optionsInput) {
            return null;
        }
        keyInput.value = keyInput.value.trim().toLowerCase();
        labelInput.value = labelInput.value.replace(/\s+/g, ' ').trim();
        var duplicate = definitions.some(function (field) {
            return field.key === keyInput.value && field.key !== originalKey;
        });
        if (duplicate) {
            keyInput.setCustomValidity('이미 사용 중인 Key입니다.');
        }
        var type = memberProfileExtraFieldAllowedType(typeInput.value);
        var options = optionsInput.value.split(/\r?\n/).map(function (value) {
            return value.replace(/\s+/g, ' ').trim().slice(0, 120);
        }).filter(function (value, optionIndex, values) {
            return value !== '' && values.indexOf(value) === optionIndex;
        }).slice(0, 50);
        if (type === 'select' && options.length === 0) {
            optionsInput.setCustomValidity('선택지는 하나 이상 입력해 주세요.');
        }
        var controls = [keyInput, labelInput, typeInput, optionsInput];
        for (var i = 0; i < controls.length; i++) {
            if (controls[i] && typeof controls[i].checkValidity === 'function' && !controls[i].checkValidity()) {
                controls[i].reportValidity();
                return null;
            }
        }
        return {
            kind: 'extra',
            index: index,
            field: {
                key: keyInput.value,
                label: labelInput.value,
                type: type,
                required: !!memberProfileExtraFieldInput(modal, 'required').checked,
                options: type === 'select' ? options : [],
                visibility: memberProfileExtraFieldInput(modal, 'visibility').value === 'admin' ? 'admin' : 'public',
                show_on_profile: !!memberProfileExtraFieldInput(modal, 'show_on_profile').checked,
                show_in_admin: !!memberProfileExtraFieldInput(modal, 'show_in_admin').checked,
                privacy_purpose: memberProfileExtraFieldInput(modal, 'privacy_purpose').value.trim(),
                export_policy: memberProfileExtraFieldInput(modal, 'export_policy').value === 'exclude' ? 'exclude' : 'include',
                cleanup_policy: memberProfileExtraFieldInput(modal, 'cleanup_policy').value === 'retain' ? 'retain' : 'anonymize'
            }
        };
    }

    function memberProfileExtraFieldInit() {
        var root = document.querySelector('[data-member-profile-extra-fields-builder]');
        var modal = document.querySelector('[data-member-profile-extra-field-modal]');
        var textarea = root ? root.querySelector('[data-member-profile-extra-fields-json]') : null;
        if (!root || !modal || !textarea) {
            return;
        }
        memberProfileExtraFieldRender(root);
        textarea.addEventListener('change', function () {
            memberProfileExtraFieldRender(root);
        });
        var orderTextarea = root.querySelector('[data-member-profile-field-order-json]');
        if (orderTextarea) {
            orderTextarea.addEventListener('change', function () {
                memberProfileExtraFieldRender(root);
            });
        }
        var draggedProfileFieldIndex = -1;
        root.addEventListener('dragstart', function (event) {
            var handle = event.target && event.target.closest ? event.target.closest('[data-member-profile-extra-field-drag-handle]') : null;
            if (!handle || !root.contains(handle)) {
                return;
            }
            draggedProfileFieldIndex = parseInt(handle.getAttribute('data-member-profile-extra-field-index-value') || '-1', 10);
            var row = handle.closest('[data-member-profile-field-row]');
            if (row) {
                row.classList.add('is-dragging');
            }
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(draggedProfileFieldIndex));
            }
        });
        root.addEventListener('dragover', function (event) {
            if (draggedProfileFieldIndex < 0) {
                return;
            }
            var row = event.target && event.target.closest ? event.target.closest('[data-member-profile-field-row]') : null;
            if (!row || !root.contains(row)) {
                return;
            }
            event.preventDefault();
            memberProfileExtraFieldClearDropState(root);
            var rect = row.getBoundingClientRect();
            row.classList.add(event.clientY > rect.top + (rect.height / 2) ? 'is-drop-after' : 'is-drop-before');
        });
        root.addEventListener('drop', function (event) {
            if (draggedProfileFieldIndex < 0) {
                return;
            }
            var row = event.target && event.target.closest ? event.target.closest('[data-member-profile-field-row]') : null;
            if (!row || !root.contains(row)) {
                return;
            }
            event.preventDefault();
            var targetIndex = parseInt(row.getAttribute('data-member-profile-extra-field-index-value') || '-1', 10);
            var rect = row.getBoundingClientRect();
            var insertIndex = targetIndex + (event.clientY > rect.top + (rect.height / 2) ? 1 : 0);
            memberProfileExtraFieldClearDropState(root);
            memberProfileFieldOrderMove(root, draggedProfileFieldIndex, insertIndex);
            draggedProfileFieldIndex = -1;
        });
        root.addEventListener('dragend', function () {
            draggedProfileFieldIndex = -1;
            memberProfileExtraFieldClearDropState(root);
        });
        var type = memberProfileExtraFieldInput(modal, 'type');
        if (type) {
            type.addEventListener('change', function () {
                memberProfileExtraFieldSyncOptions(modal);
            });
        }
        var enabled = memberProfileExtraFieldInput(modal, 'enabled');
        var required = memberProfileExtraFieldInput(modal, 'required');
        if (enabled && required) {
            required.addEventListener('change', function () {
                if (required.checked) {
                    enabled.checked = true;
                }
            });
            enabled.addEventListener('change', function () {
                if (!enabled.checked) {
                    required.checked = false;
                }
            });
        }
        var avatarEnabledToggle = document.querySelector('[data-member-profile-image-enabled-toggle]');
        if (avatarEnabledToggle) {
            avatarEnabledToggle.addEventListener('change', function () {
                var fixedFields = memberProfileFixedFieldParse(root);
                fixedFields.forEach(function (field) {
                    if (field.key === 'profile_image_path') {
                        field.enabled = avatarEnabledToggle.checked;
                        if (!field.enabled) {
                            field.required = false;
                        }
                    }
                });
                memberProfileExtraFieldWrite(root, memberProfileExtraFieldParse(textarea), memberProfileFieldOrderParse(root, fixedFields, memberProfileExtraFieldParse(textarea)), fixedFields);
                memberProfileExtraFieldRender(root);
            });
        }
        var form = root.closest('form');
        if (form) {
            form.addEventListener('submit', function (event) {
                var confirmed = root.querySelector('[data-member-profile-removed-field-values-confirmed]');
                if (confirmed) {
                    confirmed.value = '0';
                }
                var removedKeys = memberProfileRemovedExtraFieldKeys(root);
                if (removedKeys.length === 0) {
                    return;
                }
                var message = '선택 프로필 항목 ' + removedKeys.join(', ')
                    + ' 을(를) 삭제하면 전체 회원의 해당 저장값도 DB에서 삭제됩니다.\n\n'
                    + '삭제된 저장값은 복구할 수 없습니다. 계속 저장할까요?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                    return;
                }
                if (confirmed) {
                    confirmed.value = '1';
                }
            });
        }
    }

    memberProfileExtraFieldInit();

    document.addEventListener('click', function (event) {
        var extraFieldAdd = event.target.closest && event.target.closest('[data-member-profile-extra-field-add]');
        if (extraFieldAdd) {
            var extraFieldModal = document.querySelector('[data-member-profile-extra-field-modal]');
            var extraFieldRootForAdd = document.querySelector('[data-member-profile-extra-fields-builder]');
            var extraFieldTextareaForAdd = extraFieldRootForAdd ? extraFieldRootForAdd.querySelector('[data-member-profile-extra-fields-json]') : null;
            var extraFieldDefinitionsForAdd = memberProfileExtraFieldParse(extraFieldTextareaForAdd);
            memberProfileExtraFieldSetModal(extraFieldModal, {
                key: memberProfileExtraFieldRandomKey(extraFieldDefinitionsForAdd),
                label: '',
                enabled: true,
                type: 'text',
                required: false,
                options: [],
                visibility: 'public',
                show_on_profile: true,
                show_in_admin: false,
                privacy_purpose: '',
                export_policy: 'include',
                cleanup_policy: 'anonymize'
            }, -1, 'extra');
            return;
        }

        var extraFieldAction = event.target.closest && event.target.closest('[data-member-profile-extra-field-action]');
        if (extraFieldAction) {
            event.preventDefault();
            var extraFieldRoot = document.querySelector('[data-member-profile-extra-fields-builder]');
            var extraFieldTextarea = extraFieldRoot ? extraFieldRoot.querySelector('[data-member-profile-extra-fields-json]') : null;
            var extraFieldDefinitions = memberProfileExtraFieldParse(extraFieldTextarea);
            var extraFieldFixedFields = memberProfileFixedFieldParse(extraFieldRoot);
            var extraFieldOrder = memberProfileFieldOrderParse(extraFieldRoot, extraFieldFixedFields, extraFieldDefinitions);
            var extraFieldIndex = parseInt(extraFieldAction.getAttribute('data-member-profile-extra-field-index-value') || '-1', 10);
            var extraFieldActionName = extraFieldAction.getAttribute('data-member-profile-extra-field-action') || '';
            if (extraFieldIndex < 0 || extraFieldIndex >= extraFieldOrder.length) {
                return;
            }
            var orderItem = extraFieldOrder[extraFieldIndex];
            var reorderLayout = null;
            var reorderMovedKey = orderItem.kind + ':' + orderItem.key;
            if (extraFieldActionName === 'edit') {
                var fieldForEdit = null;
                if (orderItem.kind === 'fixed') {
                    fieldForEdit = extraFieldFixedFields.find(function (field) {
                        return field.key === orderItem.key;
                    }) || null;
                } else {
                    fieldForEdit = extraFieldDefinitions.find(function (field) {
                        return field.key === orderItem.key;
                    }) || null;
                }
                if (!fieldForEdit) {
                    return;
                }
                var openButton = document.querySelector('[data-member-profile-extra-field-add]');
                if (openButton) {
                    openButton.click();
                }
                memberProfileExtraFieldSetModal(document.querySelector('[data-member-profile-extra-field-modal]'), fieldForEdit, extraFieldIndex, orderItem.kind);
                return;
            }
            if (extraFieldActionName === 'up' && extraFieldIndex > 0) {
                if (window.AdminShell && typeof window.AdminShell.captureReorderLayout === 'function') {
                    reorderLayout = window.AdminShell.captureReorderLayout(extraFieldRoot.querySelectorAll('[data-member-profile-field-row]'));
                }
                var previous = extraFieldOrder[extraFieldIndex - 1];
                extraFieldOrder[extraFieldIndex - 1] = extraFieldOrder[extraFieldIndex];
                extraFieldOrder[extraFieldIndex] = previous;
            } else if (extraFieldActionName === 'down' && extraFieldIndex < extraFieldOrder.length - 1) {
                if (window.AdminShell && typeof window.AdminShell.captureReorderLayout === 'function') {
                    reorderLayout = window.AdminShell.captureReorderLayout(extraFieldRoot.querySelectorAll('[data-member-profile-field-row]'));
                }
                var next = extraFieldOrder[extraFieldIndex + 1];
                extraFieldOrder[extraFieldIndex + 1] = extraFieldOrder[extraFieldIndex];
                extraFieldOrder[extraFieldIndex] = next;
            } else if (extraFieldActionName === 'remove' && orderItem.kind === 'extra') {
                extraFieldDefinitions = extraFieldDefinitions.filter(function (field) {
                    return field.key !== orderItem.key;
                });
                extraFieldOrder.splice(extraFieldIndex, 1);
            }
            memberProfileExtraFieldWrite(extraFieldRoot, extraFieldDefinitions, extraFieldOrder, extraFieldFixedFields);
            memberProfileExtraFieldRender(extraFieldRoot);
            memberProfileFieldAnimateOrder(extraFieldRoot, reorderLayout, reorderMovedKey);
            return;
        }

        var extraFieldSave = event.target.closest && event.target.closest('[data-member-profile-extra-field-save]');
        if (extraFieldSave) {
            event.preventDefault();
            var saveRoot = document.querySelector('[data-member-profile-extra-fields-builder]');
            var saveTextarea = saveRoot ? saveRoot.querySelector('[data-member-profile-extra-fields-json]') : null;
            var saveModal = document.querySelector('[data-member-profile-extra-field-modal]');
            var saveDefinitions = memberProfileExtraFieldParse(saveTextarea);
            var saveFixedFields = memberProfileFixedFieldParse(saveRoot);
            var saveOrder = memberProfileFieldOrderParse(saveRoot, saveFixedFields, saveDefinitions);
            var collected = memberProfileExtraFieldCollect(saveModal, saveDefinitions);
            if (!collected) {
                return;
            }
            if (collected.kind === 'fixed') {
                if (collected.index < 0 || collected.index >= saveOrder.length || saveOrder[collected.index].kind !== 'fixed') {
                    return;
                }
                saveFixedFields = saveFixedFields.map(function (field) {
                    if (field.key !== saveOrder[collected.index].key) {
                        return field;
                    }
                    field.enabled = collected.field.enabled;
                    field.required = collected.field.required;
                    return field;
                });
            } else {
                if (collected.index >= 0 && collected.index < saveOrder.length && saveOrder[collected.index].kind === 'extra') {
                    var previousKey = saveOrder[collected.index].key;
                    saveDefinitions = saveDefinitions.map(function (field) {
                        return field.key === previousKey ? collected.field : field;
                    });
                    saveOrder[collected.index].key = collected.field.key;
                } else {
                    saveDefinitions.push(collected.field);
                    saveOrder.push({ kind: 'extra', key: collected.field.key });
                }
            }
            memberProfileExtraFieldWrite(saveRoot, saveDefinitions, saveOrder, saveFixedFields);
            memberProfileExtraFieldRender(saveRoot);
            var closeButton = saveModal ? saveModal.querySelector('.modal-close') : null;
            if (closeButton) {
                closeButton.click();
            }
        }
    });
});
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
