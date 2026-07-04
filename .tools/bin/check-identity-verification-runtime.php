#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/identity_verification/helpers.php';

$errors = [];

$identityProvider = [
    'provider_key' => 'identity_fixture',
    'supported_methods' => ['integrated_identity'],
];
$mobileProvider = [
    'provider_key' => 'mobile_fixture',
    'supported_methods' => ['mobile_identity'],
];
$simpleProvider = [
    'provider_key' => 'simple_fixture',
    'supported_methods' => ['simple_auth'],
];

if (!sr_identity_verification_provider_supports_purpose($identityProvider, 'member.registration')) {
    $errors[] = 'integrated identity provider must support registration identity purpose.';
}
if (!sr_identity_verification_provider_supports_purpose($mobileProvider, 'community.adult_board')) {
    $errors[] = 'mobile identity provider must support adult board identity purpose.';
}
if (sr_identity_verification_provider_supports_purpose($simpleProvider, 'member.registration')) {
    $errors[] = 'simple auth provider must not satisfy registration identity purpose.';
}
if (sr_identity_verification_provider_supports_purpose($simpleProvider, 'community.adult_board')) {
    $errors[] = 'simple auth provider must not satisfy adult board identity purpose.';
}
if (!sr_identity_verification_provider_supports_purpose($simpleProvider, 'member.withdrawal')) {
    $errors[] = 'simple auth provider should remain available for one-time withdrawal purpose.';
}

if ($errors !== []) {
    fwrite(STDERR, "identity verification runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "identity verification runtime checks completed.\n";
