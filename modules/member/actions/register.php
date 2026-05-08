<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';

$account = toy_member_current_account($pdo);
if ($account !== null) {
    if (toy_request_method() === 'POST') {
        toy_require_csrf();
    }
    toy_redirect('/account');
}

$memberSettings = toy_member_settings($pdo);
$registrationAllowed = (bool) $memberSettings['allow_registration'];
$emailVerificationEnabled = (bool) $memberSettings['email_verification_enabled'];
$loginIdentifierMode = (string) $memberSettings['login_identifier'];
$errors = [];
$marketingConsent = false;
$values = [
    'email' => '',
    'login_id' => '',
    'display_name' => '',
];

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    if (!$registrationAllowed) {
        $errors[] = '현재 회원가입이 비활성화되어 있습니다.';
    }

    $email = toy_post_string_without_truncation('email', 255);
    if ($email === null) {
        $errors[] = '이메일은 255자 이하로 입력하세요.';
        $email = '';
    }

    $loginId = toy_post_string_without_truncation('login_id', 40);
    if ($loginId === null) {
        $loginId = '';
        if ($loginIdentifierMode === 'login_id') {
            $errors[] = '로그인 아이디는 40자 이하로 입력하세요.';
        }
    }

    $values = [
        'email' => $email,
        'login_id' => toy_member_normalize_login_id($loginId),
        'display_name' => toy_post_string('display_name', 120),
    ];
    $password = toy_post_string_without_truncation('password', 255);
    $passwordConfirm = toy_post_string_without_truncation('password_confirm', 255);
    $termsConsent = ($_POST['terms_consent'] ?? '') === '1';
    $privacyConsent = ($_POST['privacy_consent'] ?? '') === '1';
    $marketingConsent = ($_POST['marketing_consent'] ?? '') === '1';

    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = '이메일 형식이 올바르지 않습니다.';
    }

    if ($loginIdentifierMode === 'login_id' && !toy_member_is_valid_login_id($values['login_id'])) {
        $errors[] = '로그인 아이디는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄을 포함한 4~40자여야 합니다.';
    }

    if ($values['display_name'] === '') {
        $errors[] = '표시 이름을 입력하세요.';
    }

    if ($password === null || $passwordConfirm === null) {
        $errors[] = '비밀번호는 255자 이하로 입력하세요.';
        $password = '';
        $passwordConfirm = '';
    }

    if (strlen($password) < 8) {
        $errors[] = '비밀번호는 8자 이상이어야 합니다.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = '비밀번호 확인이 일치하지 않습니다.';
    }

    if (!$termsConsent || !$privacyConsent) {
        $errors[] = '필수 약관과 개인정보 처리방침에 동의하세요.';
    }

    if ($errors === []) {
        $throttle = toy_member_register_throttle_status($pdo);
        if (!empty($throttle['limited'])) {
            toy_member_log_auth($pdo, null, 'register_blocked', 'failure');
            toy_audit_log($pdo, [
                'actor_account_id' => null,
                'actor_type' => 'member',
                'event_type' => 'member.register.blocked',
                'target_type' => 'member_account',
                'target_id' => '',
                'result' => 'failure',
                'message' => 'Member registration blocked by throttle.',
            ]);
            $errors[] = '가입 요청이 많습니다. 잠시 후 다시 시도하세요.';
        }
    }

    if ($errors === []) {
        $accountId = null;
        $verificationMailSent = null;
        $verificationUrl = '';

        try {
            $pdo->beginTransaction();

            $accountId = toy_member_create_account($pdo, $config, [
                'email' => $values['email'],
                'login_id' => $loginIdentifierMode === 'login_id' ? $values['login_id'] : '',
                'password' => $password,
                'display_name' => $values['display_name'],
                'locale' => (string) ($site['default_locale'] ?? 'ko'),
                'status' => 'active',
                'email_verified_at' => $emailVerificationEnabled ? null : toy_now(),
            ]);

            if ($emailVerificationEnabled) {
                $verificationToken = toy_member_create_email_verification($pdo, $config, $accountId, $values['email']);
                $verificationUrl = toy_absolute_url($site, '/email/verify?token=' . rawurlencode($verificationToken));
            }
            toy_member_record_consent($pdo, $accountId, 'terms', '2026.04.001', true);
            toy_member_record_consent($pdo, $accountId, 'privacy', '2026.04.001', true);
            toy_member_record_consent($pdo, $accountId, 'marketing', '2026.04.001', $marketingConsent);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            toy_member_log_auth($pdo, null, 'register', 'failure');
            $errors[] = '이미 사용 중인 이메일이거나 가입을 처리할 수 없습니다.';
        }

        if ($errors === [] && $accountId !== null) {
            if ($emailVerificationEnabled) {
                $verificationMailSent = toy_send_mail(
                    $site,
                    $values['email'],
                    '이메일 인증 안내',
                    "아래 링크를 열어 이메일 인증을 완료하세요.\n\n" . $verificationUrl
                );
                $showVerificationUrl = !empty($config['debug']) && toy_is_local_host((string) ($site['base_url'] ?? ''));
                if ($showVerificationUrl) {
                    $_SESSION['toy_debug_email_verification_url'] = $verificationUrl;
                } else {
                    unset($_SESSION['toy_debug_email_verification_url']);
                }
                if (!$verificationMailSent) {
                    toy_member_log_auth($pdo, $accountId, 'email_verification_mail_failed', 'failure');
                }
            }

            toy_member_log_auth($pdo, $accountId, 'register', 'success');
            toy_audit_log($pdo, [
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

            $newAccount = toy_member_find_by_identifier($pdo, $config, $loginIdentifierMode === 'login_id' ? $values['login_id'] : $values['email']);
            if ($emailVerificationEnabled) {
                $_SESSION['toy_member_login_notice'] = '가입을 접수했습니다. 이메일 인증을 완료한 뒤 로그인하세요.';
                toy_redirect('/login');
            }

            if ($newAccount !== null && toy_member_login($pdo, $newAccount)) {
                toy_redirect('/account');
            }

            $_SESSION['toy_member_login_notice'] = '가입은 완료됐지만 로그인 세션을 만들 수 없습니다. 로그인 화면에서 다시 시도하세요.';
            toy_redirect('/login');
        }
    }
}

include TOY_ROOT . '/modules/member/views/register.php';
