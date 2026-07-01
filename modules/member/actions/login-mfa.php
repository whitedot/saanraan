<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$account = sr_member_current_account($pdo);
if ($account !== null) {
    sr_redirect('/');
}

$challenge = sr_member_mfa_challenge();
if ($challenge === null) {
    sr_redirect('/login');
}

$errors = [];
$notice = '';
$memberSettings = sr_member_settings($pdo);
$next = sr_member_safe_next_path((string) ($challenge['next_path'] ?? ''));

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $errors[] = sr_t('member::action.login_mfa.factor_unavailable');
}

include SR_ROOT . '/modules/member/views/login-mfa.php';
