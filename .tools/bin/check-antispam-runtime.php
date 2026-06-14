#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
chdir(SR_ROOT);

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/antispam/helpers.php';

$errors = [];

function sr_antispam_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_antispam_check_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_antispam_check_error($message);
    }
}

function sr_antispam_check_read(string $path): string
{
    $content = file_get_contents(SR_ROOT . '/' . $path);
    if (!is_string($content)) {
        sr_antispam_check_error('Antispam check cannot read file: ' . $path);
        return '';
    }

    return $content;
}

function sr_antispam_check_answer(string $question): string
{
    if (preg_match('/\A(\d+) ([+-]) (\d+)\z/', $question, $matches) !== 1) {
        sr_antispam_check_error('Antispam math fixture question format is invalid: ' . $question);
        return '';
    }

    $left = (int) $matches[1];
    $right = (int) $matches[3];

    return (string) ((string) $matches[2] === '+' ? $left + $right : $left - $right);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$module = include SR_ROOT . '/modules/antispam/module.php';
sr_antispam_check_assert(is_array($module), 'Antispam module metadata must return an array.');
sr_antispam_check_assert(($module['admin']['settings_path'] ?? '') === '/admin/antispam/settings', 'Antispam module must expose admin settings path.');
sr_antispam_check_assert(in_array('paths.php', (array) ($module['contracts']['provides'] ?? []), true), 'Antispam module must declare paths.php contract.');
sr_antispam_check_assert(in_array('admin-menu.php', (array) ($module['contracts']['provides'] ?? []), true), 'Antispam module must declare admin-menu.php contract.');

$install = sr_antispam_check_read('core/actions/install.php');
sr_antispam_check_assert(str_contains($install, "'antispam' => ["), 'Installer optional module list must include antispam.');
sr_antispam_check_assert(str_contains($install, '외부 CAPTCHA provider'), 'Installer optional module description must mention external CAPTCHA provider.');

$adminAction = sr_antispam_check_read('modules/antispam/actions/admin-settings.php');
sr_antispam_check_assert(str_contains($adminAction, 'sr_admin_require_permission'), 'Antispam admin settings must require admin permissions.');
sr_antispam_check_assert(str_contains($adminAction, 'sr_require_csrf'), 'Antispam admin settings POST must require CSRF.');
sr_antispam_check_assert(str_contains($adminAction, 'sr_post_string_without_truncation'), 'Antispam secret key input must reject overlong values without truncation.');
sr_antispam_check_assert(!str_contains($adminAction, "'turnstile_secret_key' =>"), 'Antispam audit metadata must not include provider secrets.');
sr_antispam_check_assert(!str_contains($adminAction, "'hcaptcha_secret_key' =>"), 'Antispam audit metadata must not include provider secrets.');
sr_antispam_check_assert(!str_contains($adminAction, "'recaptcha_secret_key' =>"), 'Antispam audit metadata must not include provider secrets.');

$adminView = sr_antispam_check_read('modules/antispam/views/admin-settings.php');
sr_antispam_check_assert(str_contains($adminView, 'type="password"'), 'Antispam admin view must mask provider secret fields.');
sr_antispam_check_assert(str_contains($adminView, 'sr_antispam_secret_display'), 'Antispam admin view must display stored secrets as masked placeholders.');
sr_antispam_check_assert(str_contains($adminView, 'provider_failure_policy'), 'Antispam admin view must expose provider failure policy.');

$helpers = sr_antispam_check_read('modules/antispam/helpers.php');
foreach (['turnstile', 'hcaptcha', 'recaptcha'] as $providerKey) {
    sr_antispam_check_assert(str_contains($helpers, $providerKey), 'Antispam helper must include provider marker: ' . $providerKey);
}
foreach (['sr_antispam_hp', 'min_submit_seconds', 'fallback_math', 'provider_timeout_seconds', 'verify_remote_ip_enabled'] as $marker) {
    sr_antispam_check_assert(str_contains($helpers, $marker), 'Antispam helper marker missing: ' . $marker);
}

$memberRegisterAction = sr_antispam_check_read('modules/member/actions/register.php');
$memberRegisterView = sr_antispam_check_read('modules/member/views/register.php');
sr_antispam_check_assert(str_contains($memberRegisterAction, "sr_antispam_verify(\$pdo, 'member.register'"), 'Member registration must verify antispam challenge server-side.');
sr_antispam_check_assert(str_contains($memberRegisterView, "sr_antispam_challenge_render(\$pdo, 'member.register'"), 'Member registration view must render antispam challenge.');

$communityWriteAction = sr_antispam_check_read('modules/community/actions/write.php');
$communityWriteView = sr_antispam_check_read('modules/community/skins/basic/form.php');
$communityCommentAction = sr_antispam_check_read('modules/community/actions/comment.php');
$communityCommentView = sr_antispam_check_read('modules/community/skins/basic/view.php');
sr_antispam_check_assert(str_contains($communityWriteAction, "sr_antispam_verify(\$pdo, 'community.post.guest'"), 'Community guest post action must verify antispam challenge server-side.');
sr_antispam_check_assert(str_contains($communityWriteView, "sr_antispam_challenge_render(\$pdo, 'community.post.guest'"), 'Community guest post form must render antispam challenge.');
sr_antispam_check_assert(str_contains($communityCommentAction, "sr_antispam_verify(\$pdo, 'community.comment.guest'"), 'Community guest comment action must verify antispam challenge server-side.');
sr_antispam_check_assert(str_contains($communityCommentView, "sr_antispam_challenge_render(\$pdo, 'community.comment.guest'"), 'Community guest comment form must render antispam challenge.');

$settings = sr_antispam_normalize_settings([
    'enabled' => '1',
    'default_mode' => 'always',
    'challenge_type' => 'math',
    'ttl_seconds' => '60',
    'min_submit_seconds' => '0',
    'provider_timeout_seconds' => '2',
    'provider_failure_policy' => 'fallback_math',
    'verify_remote_ip_enabled' => 'on',
    'recaptcha_min_score' => '0.7',
]);
sr_antispam_check_assert($settings['enabled'] === true, 'Antispam settings must normalize enabled boolean.');
sr_antispam_check_assert($settings['min_submit_seconds'] === 0, 'Antispam settings must allow zero minimum submit seconds for fixtures.');
sr_antispam_check_assert($settings['recaptcha_min_score'] === 0.7, 'Antispam settings must normalize reCAPTCHA score.');
sr_antispam_check_assert($settings['surface_member_register'] === 'always', 'Antispam surface settings must fall back to default mode.');

$challenge = sr_antispam_challenge_create('member.register', 'fixture', ['ttl_seconds' => 60]);
$answer = sr_antispam_check_answer((string) $challenge['question']);
$mathErrors = sr_antispam_verify_math('member.register', 'fixture', [
    'sr_antispam_form_key' => 'fixture',
    'sr_antispam_answer' => $answer,
], $settings);
sr_antispam_check_assert($mathErrors === [], 'Antispam math challenge must accept the correct answer.');

sr_antispam_challenge_create('member.register', 'fixture_wrong', ['ttl_seconds' => 60]);
$wrongErrors = sr_antispam_verify_math('member.register', 'fixture_wrong', [
    'sr_antispam_form_key' => 'fixture_wrong',
    'sr_antispam_answer' => '999',
], $settings);
sr_antispam_check_assert($wrongErrors !== [], 'Antispam math challenge must reject wrong answers.');

$expired = sr_antispam_challenge_create('member.register', 'fixture_expired', ['ttl_seconds' => 60]);
$expiredKey = sr_antispam_session_key((string) $expired['surface'], (string) $expired['form_key']);
$_SESSION[$expiredKey]['expires_at'] = time() - 1;
$expiredErrors = sr_antispam_verify_math('member.register', 'fixture_expired', [
    'sr_antispam_form_key' => 'fixture_expired',
    'sr_antispam_answer' => sr_antispam_check_answer((string) $expired['question']),
], $settings);
sr_antispam_check_assert($expiredErrors !== [], 'Antispam math challenge must reject expired answers.');

sr_antispam_check_assert(sr_antispam_secret_display('secret') === '********', 'Antispam secret display must mask stored values.');
sr_antispam_check_assert(sr_antispam_secret_display('') === '', 'Antispam secret display must keep empty values empty.');

$providerSuccess = sr_antispam_provider_response_result('turnstile', ['success' => true], []);
sr_antispam_check_assert(!empty($providerSuccess['ok']), 'Antispam provider fixture must accept a successful response.');
$providerFailure = sr_antispam_provider_response_result('hcaptcha', ['success' => false, 'error-codes' => ['bad/input', 'bad input']], []);
sr_antispam_check_assert(empty($providerFailure['ok']), 'Antispam provider fixture must reject failed responses.');
sr_antispam_check_assert(($providerFailure['codes'] ?? []) === ['badinput'], 'Antispam provider fixture must sanitize error codes.');
$recaptchaLow = sr_antispam_provider_response_result('recaptcha', ['success' => true, 'score' => 0.3], ['settings' => ['recaptcha_min_score' => 0.7]]);
sr_antispam_check_assert(empty($recaptchaLow['ok']) && in_array('score_low', (array) ($recaptchaLow['codes'] ?? []), true), 'Antispam reCAPTCHA fixture must enforce minimum score.');

if ($errors !== []) {
    fwrite(STDERR, "antispam checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "antispam runtime checks completed.\n";
