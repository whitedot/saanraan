#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/reaction/helpers.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE sr_site_settings (
        setting_key TEXT NOT NULL PRIMARY KEY,
        setting_value TEXT NOT NULL,
        value_type TEXT NOT NULL
    );
    CREATE TABLE sr_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL,
        version TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL
    );
    CREATE TABLE sr_reaction_definitions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reaction_key TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL,
        icon_type TEXT NOT NULL DEFAULT \'emoji\',
        icon_value TEXT NOT NULL DEFAULT \'\',
        color_hex TEXT NOT NULL DEFAULT \'\',
        color_swatch TEXT NOT NULL DEFAULT \'\',
        description TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'active\',
        sort_order INTEGER NOT NULL DEFAULT 100,
        is_seed INTEGER NOT NULL DEFAULT 0,
        created_by_account_id INTEGER NULL,
        updated_by_account_id INTEGER NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE sr_reaction_presets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        preset_key TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'active\',
        selection_policy TEXT NOT NULL DEFAULT \'single\',
        visible_key_limit INTEGER NOT NULL DEFAULT 6,
        sort_order INTEGER NOT NULL DEFAULT 100,
        created_by_account_id INTEGER NULL,
        updated_by_account_id INTEGER NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE sr_reaction_preset_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        preset_key TEXT NOT NULL,
        reaction_key TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 100,
        is_public INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE (preset_key, reaction_key)
    );
    CREATE TABLE sr_reaction_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        target_module TEXT NOT NULL,
        target_type TEXT NOT NULL,
        target_id TEXT NOT NULL,
        reaction_key TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE (account_id, target_module, target_type, target_id)
    );
    CREATE TABLE sr_rate_limits (
        rate_key TEXT NOT NULL PRIMARY KEY,
        bucket TEXT NOT NULL,
        subject_hash TEXT NOT NULL,
        attempt_count INTEGER NOT NULL DEFAULT 0,
        expires_at TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );'
);

$now = sr_now();
$pdo->exec(
    "INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES
        ('reaction_default_preset_key', 'emotions', 'string'),
        ('reaction_write_window_seconds', '60', 'integer'),
        ('reaction_write_account_limit', '2', 'integer');
     INSERT INTO sr_modules (module_key, version, status) VALUES ('reaction', '2026.06.001', 'enabled');
     INSERT INTO sr_reaction_definitions (reaction_key, label, status, created_at, updated_at) VALUES
        ('like', '좋아요', 'active', '$now', '$now'),
        ('sad', '슬퍼요', 'active', '$now', '$now'),
        ('disabled', '중지됨', 'disabled', '$now', '$now');
     INSERT INTO sr_reaction_presets (preset_key, label, status, selection_policy, visible_key_limit, created_at, updated_at)
        VALUES ('emotions', '감정형', 'active', 'single', 6, '$now', '$now');
     INSERT INTO sr_reaction_preset_items (preset_key, reaction_key, sort_order, is_public, created_at, updated_at) VALUES
        ('emotions', 'like', 10, 1, '$now', '$now'),
        ('emotions', 'sad', 20, 1, '$now', '$now'),
        ('emotions', 'disabled', 30, 1, '$now', '$now');"
);

$target = [
    'status' => 'active',
    'can_view' => true,
    'can_write' => true,
    'owner_account_id' => 7,
    'recipient_account_id' => 7,
    'preset_key' => 'emotions',
    'label' => '테스트 글',
    'public_url' => '/test/1',
];

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$first = sr_reaction_write($pdo, 3, 'community', 'post', '1', 'like', 'apply', ['resolved_target' => $target]);
$assert($first['ok'] === true && $first['operation'] === 'apply' && $first['my_reaction_key'] === 'like', 'first apply should create a like reaction.');
$assert((int) ($first['counts']['like'] ?? 0) === 1, 'first apply should count one like.');

$repeat = sr_reaction_write($pdo, 3, 'community', 'post', '1', 'like', 'apply', ['resolved_target' => $target]);
$assert($repeat['ok'] === true && $repeat['operation'] === 'noop' && $repeat['changed'] === false, 'same apply should be idempotent.');

$change = sr_reaction_write($pdo, 3, 'community', 'post', '1', 'sad', 'apply', ['resolved_target' => $target]);
$assert($change['ok'] === true && $change['operation'] === 'change' && $change['my_reaction_key'] === 'sad', 'different apply should replace the row.');
$assert((int) ($change['counts']['sad'] ?? 0) === 1 && (int) ($change['counts']['like'] ?? 0) === 0, 'change should move the count.');

$toggle = sr_reaction_write($pdo, 3, 'community', 'post', '1', 'sad', 'toggle', ['resolved_target' => $target]);
$assert($toggle['ok'] === true && $toggle['operation'] === 'cancel' && $toggle['my_reaction_key'] === '', 'same toggle should cancel.');

$self = sr_reaction_write($pdo, 7, 'community', 'post', '1', 'like', 'apply', ['resolved_target' => $target]);
$assert($self['ok'] === false && $self['error'] === 'self_reaction_not_allowed', 'owner should not react to own target.');

$privateTarget = array_merge($target, ['status' => 'private', 'can_view' => false, 'can_write' => false]);
$blocked = sr_reaction_write($pdo, 3, 'community', 'post', '2', 'like', 'apply', ['resolved_target' => $privateTarget]);
$assert($blocked['ok'] === false && $blocked['error'] === 'target_not_writable', 'private target should block new write.');
$assert(
    sr_reaction_create_account_event($pdo, 7, 3, $privateTarget, 'like') === false,
    'non-writable target should not create a reaction notification.'
);

$notificationDisabledTarget = array_merge($target, ['notification_enabled' => false]);
$assert(
    sr_reaction_create_account_event($pdo, 7, 3, $notificationDisabledTarget, 'like') === false,
    'target contract should be able to disable reaction notifications.'
);

$disabled = sr_reaction_write($pdo, 3, 'community', 'post', '3', 'disabled', 'apply', ['resolved_target' => $target]);
$assert($disabled['ok'] === false && $disabled['error'] === 'reaction_not_allowed', 'disabled reaction key should block new write.');

$widgetHtml = sr_reaction_render_widget($pdo, 'community', 'post', '1', ['id' => 3], ['resolved_target' => $target]);
$assert(
    str_contains($widgetHtml, 'data-sr-reaction-widget')
        && str_contains($widgetHtml, 'data-reaction-key="like"')
        && str_contains($widgetHtml, 'data-reaction-count="sad"'),
    'public widget should render active reaction buttons and counts.'
);
$selfWidgetHtml = sr_reaction_render_widget($pdo, 'community', 'post', '1', ['id' => 7], ['resolved_target' => $target]);
$assert(str_contains($selfWidgetHtml, '내가 작성한 대상에는 반응할 수 없습니다.'), 'public widget should show self-reaction block note.');
$privateWidgetHtml = sr_reaction_render_widget($pdo, 'community', 'post', '2', ['id' => 3], ['resolved_target' => $privateTarget]);
$assert($privateWidgetHtml === '', 'public widget should hide non-viewable targets.');

$assert(sr_reaction_write_rate_limited($pdo, 9) === false, 'write rate limit should start open.');
sr_reaction_record_write_rate_limit($pdo, 9);
sr_reaction_record_write_rate_limit($pdo, 9);
$assert(sr_reaction_write_rate_limited($pdo, 9) === true, 'write rate limit should close after configured attempts.');

$definitionCreate = sr_reaction_save_definition($pdo, [
    'reaction_key' => 'fun',
    'label' => '재밌어요',
    'icon_type' => 'emoji',
    'icon_value' => 'ha',
    'color_hex' => '#123abc',
    'description' => '테스트 정의',
    'status' => 'active',
    'sort_order' => 40,
], 1);
$assert(!empty($definitionCreate['ok']), 'admin definition create should pass with a new key.');
$definitionDuplicate = sr_reaction_save_definition($pdo, [
    'reaction_key' => 'fun',
    'label' => '중복',
    'status' => 'active',
], 1);
$assert(empty($definitionDuplicate['ok']), 'admin definition create should reject duplicate keys.');
$presetCreate = sr_reaction_save_preset($pdo, [
    'preset_key' => 'funny',
    'label' => '재미형',
    'description' => '테스트 preset',
    'status' => 'active',
    'visible_key_limit' => 6,
    'sort_order' => 50,
    'reaction_keys' => ['like', 'fun'],
], 1);
$assert(!empty($presetCreate['ok']), 'admin preset create should save selected keys.');
$presetItemCount = (int) $pdo->query("SELECT COUNT(*) FROM sr_reaction_preset_items WHERE preset_key = 'funny'")->fetchColumn();
$assert($presetItemCount === 2, 'admin preset create should replace preset items.');

$pdo->exec(
    "INSERT INTO sr_reaction_records (account_id, target_module, target_type, target_id, reaction_key, created_at, updated_at) VALUES
        (11, 'community', 'post', '11', 'disabled', '$now', '$now'),
        (12, 'community', 'post', '12', 'disabled', '$now', '$now');"
);
$impact = sr_reaction_record_impact($pdo, 'disabled');
$assert($impact['record_count'] === 2 && $impact['target_count'] === 2 && $impact['account_count'] === 2, 'disabled key impact summary should count records, targets, and accounts.');
$mergeCleanup = sr_reaction_cleanup_disabled_records($pdo, 'disabled', 'merge', 'like', 'disabled', 1);
$assert(!empty($mergeCleanup['ok']) && (int) ($mergeCleanup['merged_count'] ?? 0) === 2, 'disabled key merge cleanup should update existing records.');
$mergedDisabledCount = (int) $pdo->query("SELECT COUNT(*) FROM sr_reaction_records WHERE reaction_key = 'disabled'")->fetchColumn();
$mergedLikeCount = (int) $pdo->query("SELECT COUNT(*) FROM sr_reaction_records WHERE reaction_key = 'like' AND account_id IN (11, 12)")->fetchColumn();
$assert($mergedDisabledCount === 0 && $mergedLikeCount === 2, 'disabled key merge cleanup should remove source key usage.');

$module = include SR_ROOT . '/modules/reaction/module.php';
$paths = include SR_ROOT . '/modules/reaction/paths.php';
$adminMenu = include SR_ROOT . '/modules/reaction/admin-menu.php';
$assert(
    is_array($module)
        && in_array('paths.php', array_map('strval', (array) ($module['contracts']['provides'] ?? [])), true)
        && in_array('admin-menu.php', array_map('strval', (array) ($module['contracts']['provides'] ?? [])), true)
        && is_array($paths)
        && (string) ($paths['POST /reaction/write'] ?? '') === 'actions/write.php'
        && (string) ($paths['GET /admin/reactions'] ?? '') === 'actions/admin-reactions.php'
        && is_array($adminMenu)
        && is_array($adminMenu['items'] ?? null),
    'reaction module should provide public and admin routes.'
);

$targetContractFiles = [
    'content' => SR_ROOT . '/modules/content/reaction-targets.php',
    'community' => SR_ROOT . '/modules/community/reaction-targets.php',
    'quiz' => SR_ROOT . '/modules/quiz/reaction-targets.php',
    'survey' => SR_ROOT . '/modules/survey/reaction-targets.php',
];
$expectedTargets = [
    'content/content',
    'content/comment',
    'community/post',
    'community/comment',
    'quiz/quiz_set',
    'quiz/comment',
    'survey/survey_form',
    'survey/comment',
];
$contractTargets = [];
foreach ($targetContractFiles as $moduleKey => $file) {
    $contract = include $file;
    foreach ((array) ($contract['targets'] ?? []) as $target) {
        if (!is_array($target)) {
            continue;
        }
        $targetKey = (string) ($target['target_module'] ?? '') . '/' . (string) ($target['target_type'] ?? '');
        $contractTargets[$targetKey] = $target;
        $assert(is_callable($target['resolve'] ?? null), $moduleKey . ' reaction target should provide resolve.');
        $assert(is_callable($target['batch_resolve'] ?? null), $moduleKey . ' reaction target should provide batch_resolve.');
    }
}
foreach ($expectedTargets as $targetKey) {
    $assert(isset($contractTargets[$targetKey]), $targetKey . ' should be provided by reaction target contracts.');
}

if ($errors !== []) {
    fwrite(STDERR, "reaction runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "reaction runtime checks completed.\n";
