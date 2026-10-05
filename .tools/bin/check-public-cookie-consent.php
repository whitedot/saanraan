#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/privacy/public-cookie-consent.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$assert(
    sr_privacy_cookie_safe_return_path('/community/post?id=7') === '/community/post?id=7'
        && sr_privacy_cookie_safe_return_path('//example.test') === '/'
        && sr_privacy_cookie_safe_return_path('/safe/%2f/escape') === '/'
        && sr_privacy_cookie_safe_return_path('/safe/../escape') === '/'
        && sr_privacy_cookie_safe_return_path('/login') === '/login'
        && sr_privacy_cookie_safe_return_path('/login?next=%2Faccount') === '/login?next=%2Faccount'
        && sr_privacy_cookie_safe_return_path('/login/mfa') === '/login/mfa'
        && sr_privacy_cookie_safe_return_path('/logout') === '/',
    'privacy cookie contract must own safe return-path validation without member helpers.'
);

$_COOKIE = [];
$_SESSION = [];
$_SERVER['REQUEST_URI'] = '/community/post?id=7';
$html = sr_privacy_cookie_consent_public_html();
$assert(
    str_contains($html, 'data-sr-cookie-consent')
        && str_contains($html, 'csrf_token')
        && str_contains($html, 'return_to')
        && str_contains($html, '%2Fcommunity%2Fpost%3Fid%3D7'),
    'privacy public contract must render consent markup, CSRF fields, and its safe settings return path.'
);

$_COOKIE[sr_privacy_cookie_consent_cookie_name()] = 'essential';
$assert(
    sr_privacy_cookie_consent_public_html() === '',
    'privacy public contract must omit the consent prompt after an explicit consent choice.'
);
$assert(
    sr_privacy_cookie_consent_public_assets() === [
        'stylesheets' => ['/modules/privacy/assets/cookie-consent.css'],
        'scripts' => [],
    ],
    'privacy public contract must own its stylesheet list.'
);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "public cookie consent checks completed.\n");
