<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member_oauth/helpers.php';

$stateToken = sr_request_method() === 'POST'
    ? (sr_post_string_without_truncation('state', 255) ?? '')
    : (sr_get_string_without_truncation('state', 255) ?? '');
$completionState = sr_member_oauth_state_by_token($pdo, $stateToken, 'completion');
if (!is_array($completionState)) {
    sr_render_error(400, 'OAuth completion state is invalid.');
}

$memberSettings = sr_member_settings($pdo);
$policyState = sr_member_registration_policy_documents($pdo);
$policyDocuments = $policyState['documents'];
$errors = $policyState['errors'];
$values = [
    'email' => (string) ($completionState['email_snapshot'] ?? ''),
    'display_name' => (string) ($completionState['display_name_snapshot'] ?? ''),
];
$marketingConsent = false;

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $values['email'] = sr_post_string_without_truncation('email', 255) ?? '';
    $values['display_name'] = sr_member_normalize_display_name(sr_post_string('display_name', 120));
    $password = sr_post_string_without_truncation('password', 255) ?? '';
    $passwordConfirm = sr_post_string_without_truncation('password_confirm', 255) ?? '';
    $termsConsent = ($_POST['terms_consent'] ?? '') === '1';
    $privacyConsent = ($_POST['privacy_consent'] ?? '') === '1';
    $marketingConsent = ($_POST['marketing_consent'] ?? '') === '1';

    if (empty($memberSettings['allow_registration'])) {
        $errors[] = sr_t('member::action.register.disabled');
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = sr_t('member::action.register.email_invalid');
    }
    foreach (sr_member_display_name_validation_errors((string) $values['display_name']) as $displayNameError) {
        $errors[] = $displayNameError;
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

    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $usedState = sr_member_oauth_consume_state($pdo, $stateToken, (string) $completionState['provider_key'], 'completion');
            if (!is_array($usedState)) {
                throw new RuntimeException('OAuth completion state is already used.');
            }
            $policyState = sr_member_registration_policy_documents($pdo);
            if ($policyState['errors'] !== []) {
                throw new RuntimeException(implode(' ', $policyState['errors']));
            }
            $policyDocuments = $policyState['documents'];
            $emailVerificationEnabled = (bool) $memberSettings['email_verification_enabled'];
            $accountId = sr_member_create_account($pdo, $config, [
                'email' => $values['email'],
                'login_id' => '',
                'password' => $password,
                'display_name' => $values['display_name'],
                'locale' => (string) ($site['default_locale'] ?? 'ko'),
                'status' => 'active',
                'email_verified_at' => $emailVerificationEnabled ? null : sr_now(),
            ]);
            sr_member_record_consent($pdo, $accountId, 'terms', (string) $policyDocuments['terms']['version_key'], true, $policyDocuments['terms']);
            sr_member_record_consent($pdo, $accountId, 'privacy', (string) $policyDocuments['privacy']['version_key'], true, $policyDocuments['privacy']);
            sr_member_record_consent($pdo, $accountId, 'marketing', (string) $policyDocuments['marketing']['version_key'], $marketingConsent, $policyDocuments['marketing']);
            sr_member_oauth_link_account($pdo, $accountId, (string) $usedState['provider_key'], (string) $usedState['provider_subject_hash'], [
                'subject_display' => (string) $usedState['provider_subject_display'],
                'email' => (string) $usedState['email_snapshot'],
                'email_verified' => !empty($usedState['email_verified_snapshot']),
                'display_name' => (string) $usedState['display_name_snapshot'],
            ]);
            $pdo->commit();
            $account = sr_member_find_by_id($pdo, $accountId);
            if (is_array($account) && sr_member_login($pdo, $account)) {
                sr_member_log_auth($pdo, $accountId, 'oauth_register', 'success');
                sr_redirect((string) $usedState['next_path']);
            }
            sr_redirect('/login');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = sr_t('member::action.register.create_failed');
        }
    }
}

include SR_ROOT . '/modules/member_oauth/views/complete.php';
