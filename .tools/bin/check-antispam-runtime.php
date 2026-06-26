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

function sr_antispam_check_session_answer(string $surface, string $formKey): string
{
    $stored = $_SESSION[sr_antispam_session_key($surface, $formKey)] ?? null;
    if (!is_array($stored)) {
        sr_antispam_check_error('Antispam math fixture session challenge is missing: ' . $formKey);
        return '';
    }

    $token = (string) ($stored['token'] ?? '');
    $expected = (string) ($stored['answer_hash'] ?? '');
    if ($token === '' || $expected === '') {
        sr_antispam_check_error('Antispam math fixture session challenge must store token and answer hash only.');
        return '';
    }

    for ($answer = 0; $answer <= 160; $answer++) {
        if (hash_equals($expected, hash_hmac('sha256', (string) $answer, $token))) {
            return (string) $answer;
        }
    }

    sr_antispam_check_error('Antispam math fixture could not derive bounded answer for: ' . $formKey);
    return '';
}

function sr_antispam_check_provider_result(array $codes): array
{
    return ['ok' => false, 'codes' => $codes];
}

function sr_antispam_check_pdo(array $settings, bool $providersEnabled = false): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL,
            name TEXT NOT NULL,
            version TEXT NOT NULL,
            status TEXT NOT NULL,
            is_bundled INTEGER NOT NULL,
            installed_at TEXT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_module_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NULL,
            value_type TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $insertModule = $pdo->prepare('INSERT INTO sr_modules (module_key, name, version, status, is_bundled, installed_at, updated_at) VALUES (:module_key, :name, :version, :status, 1, :installed_at, :updated_at)');
    $insertModule->execute([
        'module_key' => 'antispam',
        'name' => 'Antispam',
        'version' => '2026.06.001',
        'status' => 'enabled',
        'installed_at' => '',
        'updated_at' => '',
    ]);
    $moduleId = (int) $pdo->lastInsertId();
    if ($providersEnabled) {
        $insertModule->execute([
            'module_key' => 'antispam_captcha_providers',
            'name' => 'Antispam CAPTCHA Providers',
            'version' => '2026.06.001',
            'status' => 'enabled',
            'installed_at' => '',
            'updated_at' => '',
        ]);
    }

    $insertSetting = $pdo->prepare('INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at) VALUES (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)');
    foreach ($settings as $key => $value) {
        $valueType = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string');
        $insertSetting->execute([
            'module_id' => $moduleId,
            'setting_key' => (string) $key,
            'setting_value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            'value_type' => $valueType,
            'created_at' => '',
            'updated_at' => '',
        ]);
    }
    sr_clear_module_settings_cache('antispam');

    return $pdo;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$module = include SR_ROOT . '/modules/antispam/module.php';
sr_antispam_check_assert(is_array($module), 'Antispam module metadata must return an array.');
sr_antispam_check_assert(($module['admin']['settings_path'] ?? '') === '/admin/antispam/settings', 'Antispam module must expose admin settings path.');
sr_antispam_check_assert(in_array('paths.php', (array) ($module['contracts']['provides'] ?? []), true), 'Antispam module must declare paths.php contract.');
sr_antispam_check_assert(in_array('admin-menu.php', (array) ($module['contracts']['provides'] ?? []), true), 'Antispam module must declare admin-menu.php contract.');
sr_antispam_check_assert(in_array('antispam-providers.php', (array) ($module['contracts']['consumes'] ?? []), true), 'Antispam module must consume provider contract.');

$providerPlugin = include SR_ROOT . '/modules/antispam_captcha_providers/module.php';
sr_antispam_check_assert(is_array($providerPlugin), 'Antispam provider plugin metadata must return an array.');
sr_antispam_check_assert(($providerPlugin['type'] ?? '') === 'plugin', 'Antispam CAPTCHA providers must be packaged as a plugin.');
sr_antispam_check_assert(in_array('antispam', (array) ($providerPlugin['requires']['modules'] ?? []), true), 'Antispam provider plugin must require antispam module.');
sr_antispam_check_assert(in_array('antispam-providers.php', (array) ($providerPlugin['contracts']['provides'] ?? []), true), 'Antispam provider plugin must provide antispam-providers.php contract.');

$providerContract = include SR_ROOT . '/modules/antispam_captcha_providers/antispam-providers.php';
sr_antispam_check_assert(is_array($providerContract), 'Antispam provider contract must return an array.');
foreach (['turnstile', 'hcaptcha', 'recaptcha'] as $providerKey) {
    sr_antispam_check_assert(isset($providerContract[$providerKey]), 'Antispam provider plugin contract marker missing: ' . $providerKey);
}

$install = sr_antispam_check_read('core/actions/install.php');
sr_antispam_check_assert(str_contains($install, "'antispam' => ["), 'Installer optional module list must include antispam.');
sr_antispam_check_assert(str_contains($install, "'antispam_captcha_providers' => ["), 'Installer optional module list must include antispam provider plugin.');
sr_antispam_check_assert(str_contains($install, 'Turnstile, hCaptcha, reCAPTCHA provider 계약'), 'Installer optional module description must mention external CAPTCHA provider plugin.');

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
sr_antispam_check_assert(str_contains($adminView, 'provider_action_check_enabled'), 'Antispam admin view must expose provider action check setting.');
sr_antispam_check_assert(str_contains($adminView, 'provider_hostname_check_enabled'), 'Antispam admin view must expose provider hostname check setting.');
sr_antispam_check_assert(str_contains($adminView, '$providerOptions'), 'Antispam admin view must render provider settings from provider contracts.');
sr_antispam_check_assert(str_contains($adminView, 'data-antispam-challenge-type-select'), 'Antispam admin view must select challenge type with a select control.');
sr_antispam_check_assert(str_contains($adminView, 'data-antispam-challenge-panel'), 'Antispam admin view must show only the selected challenge panel.');
sr_antispam_check_assert(str_contains($adminView, 'class="form-grid" data-antispam-challenge-panel'), 'Antispam challenge panels must preserve admin form row styling.');
sr_antispam_check_assert(!str_contains($adminView, 'data-antispam-challenge-switch'), 'Antispam admin view must not use switch controls for exclusive challenge type selection.');
sr_antispam_check_assert(str_contains($adminView, "'antispam-section-challenge' => '검증 방식'"), 'Antispam sticky tabs must include a challenge type section.');
sr_antispam_check_assert(str_contains($adminView, "'antispam-section-provider-common' => '프로바이더 공통'"), 'Antispam sticky tabs must include a provider common section.');
sr_antispam_check_assert(!str_contains($adminView, "str_replace('_', '-', (string) \$providerKey)"), 'Antispam sticky tabs must not expose provider-specific sections.');

$helpers = sr_antispam_check_read('modules/antispam/helpers.php');
sr_antispam_check_assert(str_contains($helpers, "sr_enabled_module_contract_files(\$pdo, 'antispam-providers.php'"), 'Antispam helper must read provider plugin contracts.');
sr_antispam_check_assert(str_contains($helpers, "sr_enabled_module_contract_files(\$pdo, 'antispam-targets.php'"), 'Antispam helper must read target module contracts.');
sr_antispam_check_assert(str_contains($helpers, 'function sr_antispam_target_options'), 'Antispam helper must expose target options from module contracts.');
sr_antispam_check_assert(!str_contains($helpers, 'https://challenges.cloudflare.com/turnstile/v0/siteverify'), 'Antispam helper must not inline Turnstile provider endpoint.');
sr_antispam_check_assert(!str_contains($helpers, 'https://hcaptcha.com/siteverify'), 'Antispam helper must not inline hCaptcha provider endpoint.');
sr_antispam_check_assert(!str_contains($helpers, 'https://www.google.com/recaptcha/api/siteverify'), 'Antispam helper must not inline reCAPTCHA provider endpoint.');
foreach (['sr_antispam_hp', 'min_submit_seconds', 'fallback_math', 'provider_timeout_seconds', 'verify_remote_ip_enabled'] as $marker) {
    sr_antispam_check_assert(str_contains($helpers, $marker), 'Antispam helper marker missing: ' . $marker);
}

$memberRegisterAction = sr_antispam_check_read('modules/member/actions/register.php');
$memberRegisterView = sr_antispam_check_read('modules/member/views/register.php');
$memberMetadata = sr_antispam_check_read('modules/member/module.php');
$memberTargets = sr_antispam_check_read('modules/member/antispam-targets.php');
sr_antispam_check_assert(str_contains($memberMetadata, "'antispam-targets.php'"), 'Member module must declare antispam target contract.');
sr_antispam_check_assert(str_contains($memberTargets, "'member.register'"), 'Member module must own member registration antispam target.');
sr_antispam_check_assert(str_contains($memberRegisterAction, "sr_antispam_verify(\$pdo, 'member.register'"), 'Member registration must verify antispam challenge server-side.');
sr_antispam_check_assert(str_contains($memberRegisterView, "sr_antispam_challenge_render(\$pdo, 'member.register'"), 'Member registration view must render antispam challenge.');

$communityWriteAction = sr_antispam_check_read('modules/community/actions/write.php');
$communityWriteView = sr_antispam_check_read('modules/community/skins/basic/form.php');
$communityCommentAction = sr_antispam_check_read('modules/community/actions/comment.php');
$communityCommentView = sr_antispam_check_read('modules/community/skins/basic/view.php');
$communityMetadata = sr_antispam_check_read('modules/community/module.php');
$communityTargets = sr_antispam_check_read('modules/community/antispam-targets.php');
sr_antispam_check_assert(str_contains($communityMetadata, "'antispam-targets.php'"), 'Community module must declare antispam target contract.');
sr_antispam_check_assert(str_contains($communityTargets, "'community.post.guest'"), 'Community module must own guest post antispam target.');
sr_antispam_check_assert(str_contains($communityTargets, "'community.comment.guest'"), 'Community module must own guest comment antispam target.');
sr_antispam_check_assert(str_contains($communityWriteAction, "sr_antispam_verify(\$pdo, 'community.post.guest'"), 'Community guest post action must verify antispam challenge server-side.');
sr_antispam_check_assert(str_contains($communityWriteAction, '$errors = array_merge($errors, sr_community_validate_post_input($values));'), 'Community guest post action must preserve antispam errors when post validation runs.');
sr_antispam_check_assert(str_contains($communityWriteView, "sr_antispam_challenge_render(\$pdo, 'community.post.guest'"), 'Community guest post form must render antispam challenge.');
sr_antispam_check_assert(str_contains($communityWriteView, '!isset($postIdField) && function_exists(\'sr_antispam_challenge_render\')'), 'Community guest post form must not render antispam challenge on post edit forms.');
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
    'provider_action_check_enabled' => 'on',
    'provider_hostname_check_enabled' => 'on',
    'recaptcha_min_score' => '0.7',
]);
sr_antispam_check_assert($settings['enabled'] === true, 'Antispam settings must normalize enabled boolean.');
sr_antispam_check_assert($settings['min_submit_seconds'] === 0, 'Antispam settings must allow zero minimum submit seconds for fixtures.');
sr_antispam_check_assert($settings['recaptcha_min_score'] === 0.7, 'Antispam settings must normalize reCAPTCHA score.');
sr_antispam_check_assert($settings['provider_action_check_enabled'] === true, 'Antispam settings must normalize provider action check boolean.');
sr_antispam_check_assert($settings['provider_hostname_check_enabled'] === true, 'Antispam settings must normalize provider hostname check boolean.');
sr_antispam_check_assert($settings['surface_member_register'] === 'always', 'Antispam surface settings must fall back to default mode.');
sr_antispam_check_assert(str_contains($helpers, "if (\$errors !== []) {\n        unset(\$_SESSION[sr_antispam_session_key(\$surface, \$formKey)]);"), 'Antispam verification must stop before provider calls and discard challenge when local request checks fail.');
sr_antispam_check_assert(str_contains($helpers, 'sr_antispam_provider_result_allows_math_fallback'), 'Antispam provider fallback must use a shared strict fallback policy.');

$providerOptions = sr_antispam_provider_options();
foreach (['turnstile', 'hcaptcha', 'recaptcha'] as $providerKey) {
    sr_antispam_check_assert(isset($providerOptions[$providerKey]), 'Antispam provider options must load bundled plugin contract without PDO: ' . $providerKey);
}
sr_antispam_check_assert((string) ($providerOptions['turnstile']['widget_class'] ?? '') === 'cf-turnstile', 'Antispam provider options must keep widget class from plugin contract.');

$variants = [];
for ($i = 1; $i <= 6; $i++) {
    $generated = sr_antispam_math_challenge_generate($i);
    $question = (string) ($generated['question'] ?? '');
    $answer = (int) ($generated['answer'] ?? -1);
    $variant = (string) ($generated['variant'] ?? '');
    $variants[$variant] = true;
    sr_antispam_check_assert($question !== '', 'Antispam strengthened math challenge must include a question.');
    sr_antispam_check_assert($answer >= 0 && $answer <= 160, 'Antispam strengthened math challenge answer must stay mobile-friendly and non-negative.');
    sr_antispam_check_assert(preg_match('/\A[2-9] [+-] [1-9]\z/', $question) !== 1, 'Antispam strengthened math challenge must not use legacy one-digit add/subtract only.');
}
foreach (['two_digit_add', 'two_digit_subtract', 'three_term_mixed', 'multiply_plus_sentence', 'add_sentence', 'subtract_sentence'] as $variant) {
    sr_antispam_check_assert(isset($variants[$variant]), 'Antispam strengthened math challenge variant missing from fixture run: ' . $variant);
}

$challenge = sr_antispam_challenge_create('member.register', 'fixture', ['ttl_seconds' => 60]);
$answer = sr_antispam_check_session_answer('member.register', 'fixture');
$challengeKey = sr_antispam_session_key((string) $challenge['surface'], (string) $challenge['form_key']);
sr_antispam_check_assert(!isset($_SESSION[$challengeKey]['answer']), 'Antispam challenge must not store the raw answer in the session.');
$mathErrors = sr_antispam_verify_math('member.register', 'fixture', [
    'sr_antispam_form_key' => 'fixture',
    'sr_antispam_answer' => $answer,
], $settings);
sr_antispam_check_assert($mathErrors === [], 'Antispam math challenge must accept the correct answer.');
sr_antispam_check_assert(!isset($_SESSION[$challengeKey]), 'Antispam math challenge must be discarded after success.');
$reuseErrors = sr_antispam_verify_math('member.register', 'fixture', [
    'sr_antispam_form_key' => 'fixture',
    'sr_antispam_answer' => $answer,
], $settings);
sr_antispam_check_assert($reuseErrors !== [], 'Antispam math challenge must reject reuse after success.');

$wrong = sr_antispam_challenge_create('member.register', 'fixture_wrong', ['ttl_seconds' => 60]);
$wrongKey = sr_antispam_session_key((string) $wrong['surface'], (string) $wrong['form_key']);
$wrongErrors = sr_antispam_verify_math('member.register', 'fixture_wrong', [
    'sr_antispam_form_key' => 'fixture_wrong',
    'sr_antispam_answer' => '999',
], $settings);
sr_antispam_check_assert($wrongErrors !== [], 'Antispam math challenge must reject wrong answers.');
sr_antispam_check_assert(!isset($_SESSION[$wrongKey]), 'Antispam math challenge must be discarded after failure.');

$expired = sr_antispam_challenge_create('member.register', 'fixture_expired', ['ttl_seconds' => 60]);
$expiredKey = sr_antispam_session_key((string) $expired['surface'], (string) $expired['form_key']);
$_SESSION[$expiredKey]['expires_at'] = time() - 1;
$expiredErrors = sr_antispam_verify_math('member.register', 'fixture_expired', [
    'sr_antispam_form_key' => 'fixture_expired',
    'sr_antispam_answer' => sr_antispam_check_session_answer('member.register', 'fixture_expired'),
], $settings);
sr_antispam_check_assert($expiredErrors !== [], 'Antispam math challenge must reject expired answers.');
sr_antispam_check_assert(!isset($_SESSION[$expiredKey]), 'Antispam math challenge must be discarded after expiry failure.');

$localFailurePdo = sr_antispam_check_pdo([
    'enabled' => true,
    'default_mode' => 'always',
    'challenge_type' => 'math',
    'min_submit_seconds' => 0,
]);
$localFailure = sr_antispam_challenge_create('member.register', 'fixture_local_failure', ['ttl_seconds' => 60]);
$localFailureKey = sr_antispam_session_key((string) $localFailure['surface'], (string) $localFailure['form_key']);
$localFailureResult = sr_antispam_verify($localFailurePdo, 'member.register', 'fixture_local_failure', [
    'sr_antispam_hp' => 'filled',
    'sr_antispam_form_key' => 'fixture_local_failure',
    'sr_antispam_answer' => sr_antispam_check_session_answer('member.register', 'fixture_local_failure'),
]);
sr_antispam_check_assert(empty($localFailureResult['ok']), 'Antispam full verify must reject local request failures.');
sr_antispam_check_assert(!isset($_SESSION[$localFailureKey]), 'Antispam full verify must discard challenge after local request failure.');

$providerTimingPdo = sr_antispam_check_pdo([
    'enabled' => true,
    'default_mode' => 'always',
    'challenge_type' => 'turnstile',
    'min_submit_seconds' => 5,
    'provider_failure_policy' => 'fallback_math',
    'turnstile_site_key' => 'site-key',
    'turnstile_secret_key' => 'secret-key',
], true);
$providerTiming = sr_antispam_challenge_create('member.register', 'fixture_provider_timing', ['ttl_seconds' => 60]);
$providerTimingKey = sr_antispam_session_key((string) $providerTiming['surface'], (string) $providerTiming['form_key']);
$_SESSION[$providerTimingKey]['issued_at'] = time();
$providerTimingResult = sr_antispam_verify($providerTimingPdo, 'member.register', 'fixture_provider_timing', [
    'sr_antispam_form_key' => 'fixture_provider_timing',
    'sr_antispam_answer' => sr_antispam_check_session_answer('member.register', 'fixture_provider_timing'),
    'cf-turnstile-response' => 'dummy-token',
]);
sr_antispam_check_assert(empty($providerTimingResult['ok']), 'Antispam provider full verify must reject too-fast submissions before provider calls.');
sr_antispam_check_assert(!isset($_SESSION[$providerTimingKey]), 'Antispam provider full verify must discard challenge after local timing failure.');

$timing = sr_antispam_challenge_create('member.register', 'fixture_timing', ['ttl_seconds' => 60]);
$timingKey = sr_antispam_session_key((string) $timing['surface'], (string) $timing['form_key']);
$_SESSION[$timingKey]['issued_at'] = time();
$timingErrors = sr_antispam_verify_local_timing('member.register', 'fixture_timing', ['min_submit_seconds' => 5]);
sr_antispam_check_assert($timingErrors !== [], 'Antispam provider path must enforce local minimum submit seconds.');
$_SESSION[$timingKey]['issued_at'] = time() - 10;
$timingErrors = sr_antispam_verify_local_timing('member.register', 'fixture_timing', ['min_submit_seconds' => 5]);
sr_antispam_check_assert($timingErrors === [], 'Antispam provider path must allow submissions after local minimum submit seconds.');

sr_antispam_check_assert(sr_antispam_secret_display('secret') === '********', 'Antispam secret display must mask stored values.');
sr_antispam_check_assert(sr_antispam_secret_display('') === '', 'Antispam secret display must keep empty values empty.');

$providerSuccess = sr_antispam_provider_response_result('turnstile', ['success' => true], []);
sr_antispam_check_assert(!empty($providerSuccess['ok']), 'Antispam provider fixture must accept a successful response.');
$providerFailure = sr_antispam_provider_response_result('hcaptcha', ['success' => false, 'error-codes' => ['bad/input', 'bad input']], []);
sr_antispam_check_assert(empty($providerFailure['ok']), 'Antispam provider fixture must reject failed responses.');
sr_antispam_check_assert(($providerFailure['codes'] ?? []) === ['badinput'], 'Antispam provider fixture must sanitize error codes.');
$recaptchaLow = sr_antispam_provider_response_result('recaptcha', ['success' => true, 'score' => 0.3], ['settings' => ['recaptcha_min_score' => 0.7]]);
sr_antispam_check_assert(empty($recaptchaLow['ok']) && in_array('score_low', (array) ($recaptchaLow['codes'] ?? []), true), 'Antispam reCAPTCHA fixture must enforce minimum score.');
$providerActionMismatch = sr_antispam_provider_response_result('recaptcha', ['success' => true, 'score' => 0.9, 'action' => 'wrong'], ['settings' => ['recaptcha_min_score' => 0.7, 'provider_action_check_enabled' => true], 'form_key' => 'member_register']);
sr_antispam_check_assert(empty($providerActionMismatch['ok']) && in_array('action_mismatch', (array) ($providerActionMismatch['codes'] ?? []), true), 'Antispam provider fixture must reject action mismatch.');
$providerActionIgnored = sr_antispam_provider_response_result('recaptcha', ['success' => true, 'score' => 0.9, 'action' => 'wrong'], ['settings' => ['recaptcha_min_score' => 0.7, 'provider_action_check_enabled' => false], 'form_key' => 'member_register']);
sr_antispam_check_assert(!empty($providerActionIgnored['ok']), 'Antispam provider fixture must allow disabling action mismatch checks.');
$providerHostnameMismatch = sr_antispam_provider_response_result('turnstile', ['success' => true, 'hostname' => 'bad.example'], ['settings' => ['provider_hostname_check_enabled' => true], 'expected_hostname' => 'good.example']);
sr_antispam_check_assert(empty($providerHostnameMismatch['ok']) && in_array('hostname_mismatch', (array) ($providerHostnameMismatch['codes'] ?? []), true), 'Antispam provider fixture must reject hostname mismatch.');
$providerHostnameIgnored = sr_antispam_provider_response_result('turnstile', ['success' => true, 'hostname' => 'bad.example'], ['settings' => ['provider_hostname_check_enabled' => false], 'expected_hostname' => 'good.example']);
sr_antispam_check_assert(!empty($providerHostnameIgnored['ok']), 'Antispam provider fixture must allow disabling hostname mismatch checks.');
sr_antispam_check_assert(sr_antispam_provider_result_allows_math_fallback(sr_antispam_check_provider_result(['provider_unavailable'])), 'Antispam provider fallback must allow provider_unavailable.');
sr_antispam_check_assert(!sr_antispam_provider_result_allows_math_fallback(sr_antispam_check_provider_result(['missing_input'])), 'Antispam provider fallback must not allow missing input.');
sr_antispam_check_assert(!sr_antispam_provider_result_allows_math_fallback(sr_antispam_check_provider_result(['score_low'])), 'Antispam provider fallback must not allow score_low.');
sr_antispam_check_assert(!sr_antispam_provider_result_allows_math_fallback(sr_antispam_check_provider_result(['badinput'])), 'Antispam provider fallback must not allow bad token failures.');

if ($errors !== []) {
    fwrite(STDERR, "antispam checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "antispam runtime checks completed.\n";
