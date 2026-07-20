#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/modules/member/helpers/account-access.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$_SESSION = [];
$assert(sr_member_account_access_state(17, 'session-a') === [], 'Fresh sessions must not satisfy member account access.');

sr_member_account_access_remember_credential(17, 'session-a', 'password');
$state = sr_member_account_access_state(17, 'session-a');
$assert(sr_member_account_access_credential_verified($state), 'Credential verification must be remembered for the current account session.');
$assert(!sr_member_account_access_completed($state), 'Credential verification alone must not complete a layered account gate.');

sr_member_account_access_complete(17, 'session-a');
$state = sr_member_account_access_state(17, 'session-a');
$assert(sr_member_account_access_completed($state), 'Completed account access must be reusable in the same login session.');
$assert(sr_member_account_access_state(17, 'session-b') === [], 'A rotated login session token must invalidate previous account access.');

sr_member_account_access_remember_next_path('/mypage/security');
$assert(sr_member_account_access_take_next_path() === '/mypage/security', 'Account access must restore an allowlisted mypage destination.');
sr_member_account_access_remember_next_path('https://example.com/escape');
$assert(sr_member_account_access_take_next_path() === '/mypage', 'Account access must reject non-mypage redirect destinations.');

$paths = file_get_contents($root . '/modules/member/paths.php');
$action = file_get_contents($root . '/modules/member/actions/account.php');
$view = file_get_contents($root . '/modules/member/views/account.php');
$skin = file_get_contents($root . '/modules/member/skins/basic/skin.css');
$settingsView = file_get_contents($root . '/modules/member/views/admin-settings.php');
$accountHelper = file_get_contents($root . '/modules/member/helpers/accounts.php');
$registrationHelper = file_get_contents($root . '/modules/member/helpers/registration.php');

$assert(is_string($paths) && str_contains($paths, "'GET /mypage/verify'") && str_contains($paths, "'POST /mypage/verify'"), 'Member paths must expose an explicit account verification route.');
$assert(is_string($action) && str_contains($action, 'sr_member_account_access_state(') && str_contains($action, "sr_redirect('/mypage/verify')"), 'Member account action must gate direct account requests through session-bound verification.');
$assert(is_string($action) && str_contains($action, 'sr_member_reauth_throttle_status(') && str_contains($action, "'account_page_reauth'"), 'Member account password verification failures must use reauth throttling and auth logs.');
$assert(is_string($action) && str_contains($action, '$memberAccountIdentityStartUrl') && str_contains($action, '$memberSecurityIdentityRequired'), 'Member account access must layer configured identity verification after credential verification.');
$assert(is_string($view) && str_contains($view, 'name="intent" value="account_access_verify"') && str_contains($view, 'autocomplete="current-password"'), 'Member account verification view must expose an accessible current-password form.');
$assert(is_string($view) && str_contains($view, 'card member-skin-basic-side-nav') && str_contains($view, 'card-header') && str_contains($view, 'card-body member-skin-basic-side-nav-body'), 'Member account pages must retain the navigation in the shared card sidebar structure.');
$assert(is_string($skin) && str_contains($skin, 'grid-template-areas: "main sidebar";') && str_contains($skin, 'grid-area: sidebar;'), 'Member account pages must place the card sidebar to the right of the main panel.');
$memberSideNavRuleMatched = is_string($skin) && preg_match('/\.member-skin-basic-side-nav\s*\{([^}]*)\}/s', $skin, $memberSideNavRuleMatches) === 1;
$memberSideNavRule = $memberSideNavRuleMatched ? (string) ($memberSideNavRuleMatches[1] ?? '') : '';
$assert($memberSideNavRuleMatched, 'Member account skin must define the side navigation rule.');
$assert(!str_contains($memberSideNavRule, 'position: sticky'), 'Member account side navigation must remain in normal document flow.');
$assert(!preg_match('/\btop\s*:/', $memberSideNavRule), 'Member account side navigation must not keep a sticky top offset.');
$assert(is_string($skin) && str_contains($skin, 'width: min(100%, 1360px);'), 'Member account pages must use the expanded wide container for the main panel and card sidebar.');
$assert(is_string($view) && substr_count($view, 'class="card-header"') >= 8 && str_contains($view, 'class="card-body member-skin-basic-form"'), 'Member account overview and subpages must share the UI kit card header and body structure.');
$assert(is_string($view) && str_contains($view, 'btn btn-outline-default member-skin-basic-overview-action') && !str_contains($view, 'member-skin-basic-overview-link'), 'Member account overview shortcuts must use UI kit buttons instead of custom decorative cards.');
$assert(is_string($skin) && !str_contains($skin, '.member-skin-basic-padded-card') && !str_contains($skin, '.member-skin-basic-overview-link'), 'Member account skin must not recreate UI kit card surfaces for subpages or overview shortcuts.');
$assert(is_string($settingsView) && str_contains($settingsView, '마이페이지·계정보안 본인확인'), 'Member settings must explain that identity verification applies to initial mypage access.');
$assert(
    is_string($action)
        && str_contains($action, "'/mypage/profile' => 'account'")
        && str_contains($action, "sr_redirect('/mypage/account')")
        && str_contains($action, 'sr_member_update_account_details(')
        && str_contains($action, 'sr_member_registration_extension_account_save('),
    'Legacy profile requests must render the merged account page and save all member-owned and contracted registration details there.'
);
$assert(
    is_string($view)
        && !str_contains($view, "memberAccountPage === 'profile'")
        && !str_contains($view, "\$memberAccountPages['profile']")
        && str_contains($view, 'name="email"')
        && str_contains($view, 'name="login_id"')
        && str_contains($view, 'name="intent" value="basics"')
        && str_contains($view, 'name="save_profile" value="1"')
        && str_contains($view, "sr_url(\$memberAccountBasePath . '/account')"),
    'Account information must submit email, login ID replacement, and profile inputs together without a separate profile page or navigation item.'
);
$assert(
    is_string($view)
        && substr_count($view, 'class="member-skin-basic-account-form"') === 1
        && substr_count($view, 'class="member-skin-basic-account-submit"') === 1
        && !str_contains($view, 'name="intent" value="profile"')
        && !str_contains($view, '추가 정보 저장'),
    'Account information management must expose one combined form and one save action for basic and additional fields.'
);
$assert(
    is_string($action)
        && str_contains($action, "\$saveProfileWithBasics = \$profileFieldsEnabled && sr_post_string('save_profile', 1) === '1';")
        && str_contains($action, 'if ($saveProfileWithBasics) {')
        && str_contains($action, 'sr_member_save_profile($pdo, (int) $account[\'id\'], $profile);')
        && str_contains($action, 'sr_member_save_profile_extra_field_values($pdo, (int) $account[\'id\'], $profileExtraFieldDefinitions, $profileExtraFieldValues);'),
    'The combined account save must validate and persist member-owned profile fields in the basic account transaction.'
);
$assert(
    is_string($accountHelper)
        && str_contains($accountHelper, 'function sr_member_update_account_details(')
        && str_contains($accountHelper, 'login_id_hash = :login_id_hash')
        && str_contains($accountHelper, 'account_identifier_hash = :account_identifier_hash')
        && str_contains($accountHelper, 'member_account_login_id_duplicate'),
    'Login ID changes must replace both authentication hashes and reject duplicates while preserving the stable account ID.'
);
$assert(
    is_string($registrationHelper)
        && str_contains($registrationHelper, 'function sr_member_registration_extension_account_fields(')
        && str_contains($registrationHelper, 'function sr_member_registration_extension_account_values(')
        && str_contains($registrationHelper, 'function sr_member_registration_extension_account_save('),
    'Registration extension owners must explicitly provide account read and save functions before their signup fields become editable in account information.'
);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "Member account access checks passed.\n");
