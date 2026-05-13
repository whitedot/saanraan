<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$account = sr_member_require_login($pdo);
sr_member_group_evaluate_account($pdo, (int) $account['id']);

$errors = [];
$notice = '';
$emailVerificationUrl = '';
$submittedProfile = null;
$submittedBasics = null;
$memberSettings = sr_member_settings($pdo);
$emailVerificationEnabled = (bool) $memberSettings['email_verification_enabled'];
$profileFields = sr_member_profile_field_settings($memberSettings);
$profileFieldsEnabled = in_array(true, $profileFields, true);

if (
    $emailVerificationEnabled
    && !empty($config['debug'])
    && sr_is_local_host((string) ($site['base_url'] ?? ''))
    && !empty($_SESSION['sr_debug_email_verification_url'])
    && is_string($_SESSION['sr_debug_email_verification_url'])
) {
    $emailVerificationUrl = $_SESSION['sr_debug_email_verification_url'];
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);

    if (!in_array($intent, ['basics', 'profile', 'password'], true)) {
        $errors[] = '계정 작업 값이 올바르지 않습니다.';
    }

    if ($errors === [] && $intent === 'basics') {
        $basics = [
            'display_name' => sr_post_string('display_name', 120),
            'locale' => sr_post_string('locale', 20),
        ];
        $submittedBasics = $basics;

        if ($basics['display_name'] === '') {
            $errors[] = '표시 이름을 입력하세요.';
        }

        if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $basics['locale']) !== 1) {
            $errors[] = '선호 locale 값이 올바르지 않습니다.';
        }

        if ($errors === []) {
            sr_member_update_account_basics($pdo, (int) $account['id'], $basics['display_name'], $basics['locale']);
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
                ],
            ]);

            $account = sr_member_current_account($pdo);
            if (is_array($account)) {
                sr_set_locale((string) $account['locale']);
            }
            $notice = '계정 정보를 저장했습니다.';
        }
    } elseif ($errors === [] && $intent === 'profile') {
        if (!$profileFieldsEnabled) {
            $errors[] = '수정할 수 있는 프로필 항목이 없습니다.';
        }

        $profile = sr_member_profile($pdo, (int) $account['id']);
        if ($profileFields['nickname']) {
            $profile['nickname'] = sr_post_string('nickname', 80);
        }
        if ($profileFields['phone']) {
            $profile['phone'] = sr_post_string('phone', 40);
        }
        if ($profileFields['birth_date']) {
            $profile['birth_date'] = sr_post_string('birth_date', 10);
        }
        if ($profileFields['avatar_path']) {
            $profile['avatar_path'] = sr_post_string('avatar_path', 255);
        }
        if ($profileFields['profile_text']) {
            $profile['profile_text'] = sr_post_string('profile_text', 1000);
        }
        $submittedProfile = $profile;

        if ($profileFields['birth_date'] && $profile['birth_date'] !== '' && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $profile['birth_date']) !== 1) {
            $errors[] = '생년월일은 YYYY-MM-DD 형식으로 입력하세요.';
        } elseif ($profileFields['birth_date'] && $profile['birth_date'] !== '') {
            $birthParts = explode('-', $profile['birth_date']);
            if (!checkdate((int) $birthParts[1], (int) $birthParts[2], (int) $birthParts[0])) {
                $errors[] = '생년월일이 올바르지 않습니다.';
            }
        }
        if (
            $profileFields['avatar_path']
            && $profile['avatar_path'] !== ''
            && !sr_is_safe_relative_url($profile['avatar_path'])
            && !sr_is_public_http_url($profile['avatar_path'])
        ) {
            $errors[] = '아바타 경로는 /로 시작하는 상대 URL 또는 공개 http(s) URL이어야 합니다.';
        }

        if ($errors === []) {
            sr_member_save_profile($pdo, (int) $account['id'], $profile);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'member.profile.updated',
                'target_type' => 'member_account',
                'target_id' => (string) $account['id'],
                'result' => 'success',
                'message' => 'Member profile updated.',
            ]);
            $notice = '프로필을 저장했습니다.';
        }
    } elseif ($errors === [] && $intent === 'password') {
        $currentPassword = sr_post_string('current_password', 255);
        $newPassword = sr_post_string_without_truncation('new_password', 255);
        $newPasswordConfirm = sr_post_string_without_truncation('new_password_confirm', 255);
        $reauthFailureLogged = false;

        $reauthThrottle = sr_member_reauth_throttle_status($pdo, (int) $account['id']);
        if (!empty($reauthThrottle['limited'])) {
            $errors[] = '비밀번호 확인 시도가 많습니다. 잠시 후 다시 시도하세요.';
            sr_member_log_auth($pdo, (int) $account['id'], 'reauth_blocked', 'failure');
            $reauthFailureLogged = true;
        } elseif (!password_verify($currentPassword, (string) $account['password_hash'])) {
            $errors[] = '현재 비밀번호가 올바르지 않습니다.';
            sr_member_log_auth($pdo, (int) $account['id'], 'password_change_reauth', 'failure');
            $reauthFailureLogged = true;
        }

        if ($newPassword === null || $newPasswordConfirm === null) {
            $errors[] = '새 비밀번호는 255자 이하로 입력하세요.';
            $newPassword = '';
            $newPasswordConfirm = '';
        }

        if (strlen($newPassword) < 8) {
            $errors[] = '새 비밀번호는 8자 이상이어야 합니다.';
        }

        if ($newPassword !== $newPasswordConfirm) {
            $errors[] = '새 비밀번호 확인이 일치하지 않습니다.';
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

            sr_member_log_auth($pdo, (int) $account['id'], 'password_change', 'success');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'member.password.changed',
                'target_type' => 'member_account',
                'target_id' => (string) $account['id'],
                'result' => 'success',
                'message' => 'Member password changed.',
                'metadata' => [
                    'revoked_sessions' => $revokedSessions,
                    'rotated_session' => $rotatedSession,
                ],
            ]);

            $account = sr_member_current_account($pdo);
            $notice = '비밀번호를 변경했습니다.';
        } elseif (!$reauthFailureLogged) {
            sr_member_log_auth($pdo, (int) $account['id'], 'password_change', 'failure');
        }
    }
}

if (is_array($submittedBasics) && $errors !== []) {
    $account['display_name'] = $submittedBasics['display_name'];
    $account['locale'] = $submittedBasics['locale'];
}
$profile = sr_member_profile($pdo, (int) $account['id']);
if (is_array($submittedProfile) && $errors !== []) {
    $profile = array_merge($profile, $submittedProfile);
}
$consents = sr_member_latest_consents($pdo, (int) $account['id']);

$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'account');
include $memberSkinView;
