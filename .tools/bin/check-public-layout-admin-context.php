#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers/public-account-access.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$member = sr_admin_public_account_access_model(false, false);
$owner = sr_admin_public_account_access_model(true, true, '/admin/content');
$staff = sr_admin_public_account_access_model(true, false, '/admin/community');
$staffFallback = sr_admin_public_account_access_model(true, false);

$assert(
    $member['enabled'] === false
        && $member['is_owner'] === false
        && $member['badge_label'] === '회원'
        && $member['badge_class'] === 'badge-soft-secondary'
        && str_ends_with((string) $member['admin_url'], '/admin'),
    'non-admin public layout model must keep the member badge fallback.'
);
$assert(
    $owner['enabled'] === true
        && $owner['is_owner'] === true
        && $owner['badge_label'] === '매니저'
        && $owner['badge_class'] === 'badge-soft-primary'
        && str_ends_with((string) $owner['admin_url'], '/admin'),
    'owner public layout model must expose the manager badge and admin root.'
);
$assert(
    $staff['enabled'] === true
        && $staff['is_owner'] === false
        && $staff['badge_label'] === '스탭'
        && $staff['badge_class'] === 'badge-soft-info'
        && str_ends_with((string) $staff['admin_url'], '/admin/community')
        && str_ends_with((string) $staffFallback['admin_url'], '/admin'),
    'staff public layout model must expose its first permitted path with an admin-root fallback.'
);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "public layout admin context checks completed.\n");
