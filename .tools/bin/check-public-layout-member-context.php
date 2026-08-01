#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/member/public-identity.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE sr_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL UNIQUE,
        version TEXT NOT NULL,
        status TEXT NOT NULL
    );
    CREATE TABLE sr_module_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_id INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT,
        value_type TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE sr_member_accounts (
        id INTEGER PRIMARY KEY,
        display_name TEXT NOT NULL,
        email TEXT NOT NULL,
        locale TEXT NOT NULL,
        status TEXT NOT NULL
    );
    CREATE TABLE sr_member_nicknames (
        account_id INTEGER PRIMARY KEY,
        nickname TEXT NOT NULL
    );'
);
$pdo->exec(
    "INSERT INTO sr_modules (id, module_key, version, status) VALUES (1, 'member', 'test', 'enabled');
     INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
        VALUES (1, 'nickname_enabled', '1', 'boolean', '2026-08-01 00:00:00', '2026-08-01 00:00:00');
     INSERT INTO sr_member_accounts (id, display_name, email, locale, status)
        VALUES (7, '표시 이름', 'member@example.test', 'ko', 'active');
     INSERT INTO sr_member_nicknames (account_id, nickname) VALUES (7, '공개 별명');"
);

$guestModel = sr_member_public_layout_account_model($pdo, null, ['app_key' => 'fixture-key']);
$assert(
    $guestModel === [
        'account' => null,
        'display_name' => '내 계정',
        'display_label' => '내 계정',
        'email' => '',
        'initial' => 'M',
        'avatar_color_class' => 'member-avatar-color-8',
    ],
    'guest layout account model must keep the provider-owned fallback.'
);

$account = [
    'id' => 7,
    'display_name' => '표시 이름',
    'email' => 'member@example.test',
    'locale' => 'ko',
    'status' => 'active',
];
$memberModel = sr_member_public_layout_account_model($pdo, $account, ['app_key' => 'fixture-key']);
$assert(
    ($memberModel['account']['id'] ?? 0) === 7
        && ($memberModel['display_name'] ?? '') === '공개 별명'
        && ($memberModel['display_label'] ?? '') === '공개 별명 님'
        && ($memberModel['email'] ?? '') === 'member@example.test'
        && ($memberModel['initial'] ?? '') === '공'
        && preg_match('/\Amember-avatar-color-(?:[0-9]|1[01])\z/', (string) ($memberModel['avatar_color_class'] ?? '')) === 1,
    'member layout account model must own public name, label, email, initial, and avatar fallback.'
);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "public layout member context checks completed.\n");
