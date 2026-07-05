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

$errors = [];

function sr_reaction_check_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_reaction_check_read(string $path): string
{
    $content = file_get_contents(SR_ROOT . '/' . $path);
    if (!is_string($content)) {
        sr_reaction_check_assert(false, 'cannot read ' . $path);
        return '';
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function sr_reaction_check_php_files(array $roots): array
{
    $files = [];
    foreach ($roots as $root) {
        $directory = SR_ROOT . '/' . $root;
        if (!is_dir($directory)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $files[] = $file->getPathname();
        }
    }

    sort($files);
    return $files;
}

$install = sr_reaction_check_read('core/actions/install.php');
sr_reaction_check_assert(str_contains($install, "'reaction' => ["), 'Installer optional module list must include reaction.');

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
    );
    CREATE TABLE sr_notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NULL,
        audience TEXT NOT NULL DEFAULT \'account\',
        title TEXT NOT NULL,
        body_text TEXT NULL,
        body_format TEXT NOT NULL DEFAULT \'plain\',
        link_url TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'active\',
        read_at TEXT NULL,
        created_by_account_id INTEGER NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE sr_notification_deliveries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        notification_id INTEGER NOT NULL,
        channel TEXT NOT NULL,
        recipient TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'queued\',
        provider_message_id TEXT NOT NULL DEFAULT \'\',
        error_message TEXT NOT NULL DEFAULT \'\',
        attempted_at TEXT NULL,
        locked_at TEXT NULL,
        locked_by TEXT NOT NULL DEFAULT \'\',
        attempt_count INTEGER NOT NULL DEFAULT 0,
        next_attempt_at TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE sr_notification_event_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL,
        event_key TEXT NOT NULL,
        title_template TEXT NOT NULL,
        body_template TEXT NULL,
        link_template TEXT NOT NULL DEFAULT \'\',
        channels_json TEXT NULL,
        status TEXT NOT NULL DEFAULT \'active\',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE (module_key, event_key)
    );'
);

$now = sr_now();
$pdo->exec(
    "INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES
        ('reaction_default_preset_key', 'emotions', 'string'),
        ('reaction_write_window_seconds', '60', 'integer'),
        ('reaction_write_account_limit', '2', 'integer');
     INSERT INTO sr_modules (module_key, version, status) VALUES ('reaction', '2026.06.001', 'enabled'), ('notification', '2026.06.006', 'enabled');
     INSERT INTO sr_reaction_definitions (reaction_key, label, icon_value, status, created_at, updated_at) VALUES
        ('like', '좋아요', '좋아', 'active', '$now', '$now'),
        ('sad', '슬퍼요', '슬퍼', 'active', '$now', '$now'),
        ('disabled', '중지됨', '중지', 'disabled', '$now', '$now');
     INSERT INTO sr_notification_event_templates (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
        VALUES ('reaction', 'target.reacted', '새 리액션이 등록되었습니다.', '{member_name}님이 {target_label}에 {reaction_label} 리액션을 남겼습니다.', '{link_url}', '[\"site\"]', 'active', '$now', '$now');
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

$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$first = sr_reaction_write($pdo, 3, 'community', 'post', '1', 'like', 'apply', ['resolved_target' => $target]);
$assert($first['ok'] === true && $first['operation'] === 'apply' && $first['my_reaction_key'] === 'like', 'first apply should create a like reaction.');
$assert((int) ($first['counts']['like'] ?? 0) === 1, 'first apply should count one like.');
$assert($first['notification_created'] === true, 'first apply should create a target owner notification when notification module is available.');
$assert(
    sr_reaction_create_account_event($pdo, 7, 3, $target, 'like') === false,
    'same actor target reaction notification should be deduped.'
);
$assert((int) $pdo->query('SELECT COUNT(*) FROM sr_notifications')->fetchColumn() === 1, 'deduped reaction notification should not insert another notification.');

$repeat = sr_reaction_write($pdo, 3, 'community', 'post', '1', 'like', 'apply', ['resolved_target' => $target]);
$assert($repeat['ok'] === true && $repeat['operation'] === 'noop' && $repeat['changed'] === false, 'same apply should be idempotent.');

$change = sr_reaction_write($pdo, 3, 'community', 'post', '1', 'sad', 'apply', ['resolved_target' => $target]);
$assert($change['ok'] === true && $change['operation'] === 'change' && $change['my_reaction_key'] === 'sad', 'different apply should replace the row.');
$assert((int) ($change['counts']['sad'] ?? 0) === 1 && (int) ($change['counts']['like'] ?? 0) === 0, 'change should move the count.');
$assert($change['notification_created'] === true, 'changed reaction key should create a distinct target owner notification.');

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
        && str_contains($widgetHtml, 'data-reaction-count="sad"')
        && str_contains($widgetHtml, 'btn sr-reaction-button btn-ghost-default')
        && str_contains($widgetHtml, 'sr-reaction-body-label'),
    'public widget should render active reaction buttons and counts.'
);
$bodyOrderIcon = strpos($widgetHtml, 'sr-reaction-emoji');
$bodyOrderLabel = strpos($widgetHtml, 'sr-reaction-button-label');
$bodyOrderCount = strpos($widgetHtml, 'data-reaction-count="like"');
$assert(
    is_int($bodyOrderIcon)
        && is_int($bodyOrderLabel)
        && is_int($bodyOrderCount)
        && $bodyOrderIcon < $bodyOrderLabel
        && $bodyOrderLabel < $bodyOrderCount,
    'public body widget should render icon, label, count in that order.'
);
$commentTarget = array_merge($target, ['owner_account_id' => 8, 'recipient_account_id' => 8]);
$commentWidgetHtml = sr_reaction_render_widget($pdo, 'community', 'comment', '1', ['id' => 3], ['resolved_target' => $commentTarget]);
$commentOrderIcon = strpos($commentWidgetHtml, 'sr-reaction-emoji');
$commentOrderLabel = strpos($commentWidgetHtml, 'sr-reaction-button-label');
$commentOrderCount = strpos($commentWidgetHtml, 'data-reaction-count="like"');
$assert(
    is_int($commentOrderIcon)
        && is_int($commentOrderLabel)
        && is_int($commentOrderCount)
        && $commentOrderIcon < $commentOrderLabel
        && $commentOrderLabel < $commentOrderCount,
    'public comment widget should render icon, label, count in that order.'
);
$ownerEmptyWidgetHtml = sr_reaction_render_widget($pdo, 'community', 'post', '1', ['id' => 7], ['resolved_target' => $target]);
$assert($ownerEmptyWidgetHtml === '', 'public owner widget should hide when every reaction count is zero.');

$ownerReaction = sr_reaction_write($pdo, 4, 'community', 'post', '1', 'like', 'apply', ['resolved_target' => $target]);
$assert($ownerReaction['ok'] === true && (int) ($ownerReaction['counts']['like'] ?? 0) === 1, 'non-owner reaction should create a visible owner summary count.');
$selfWidgetHtml = sr_reaction_render_widget($pdo, 'community', 'post', '1', ['id' => 7], ['resolved_target' => $target]);
$assert(
    str_contains($selfWidgetHtml, 'data-sr-reaction-widget')
        && str_contains($selfWidgetHtml, 'btn sr-reaction-button sr-reaction-summary btn-ghost-default')
        && str_contains($selfWidgetHtml, 'sr-reaction-summary')
        && str_contains($selfWidgetHtml, 'data-reaction-count="like"')
        && !str_contains($selfWidgetHtml, 'data-reaction-key=')
        && !str_contains($selfWidgetHtml, 'data-reaction-count="sad"')
        && !str_contains($selfWidgetHtml, 'sr-reaction-label')
        && !str_contains($selfWidgetHtml, 'sr-reaction-note')
        && !str_contains($selfWidgetHtml, 'sr-reaction-login')
        && !str_contains($selfWidgetHtml, 'sr-reaction-message'),
    'public owner widget should render only counted ghost-style text summaries.'
);
$ownerCommentReaction = sr_reaction_write($pdo, 4, 'community', 'comment', '1', 'like', 'apply', ['resolved_target' => $commentTarget]);
$ownerCommentWidgetHtml = sr_reaction_render_widget($pdo, 'community', 'comment', '1', ['id' => 8], ['resolved_target' => $commentTarget]);
$assert(
    $ownerCommentReaction['ok'] === true
        && str_contains($ownerCommentWidgetHtml, 'sr-reaction-summary')
        && str_contains($ownerCommentWidgetHtml, 'data-reaction-count="like"')
        && !str_contains($ownerCommentWidgetHtml, 'data-reaction-key='),
    'public owner comment widget should render counted ghost-style text summaries without buttons.'
);
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
$imageStorageReference = 'local:reaction/icons/2026/06/' . str_repeat('a', 32) . '.webp';
$imageDefinitionCreate = sr_reaction_save_definition($pdo, [
    'reaction_key' => 'image_fun',
    'label' => '이미지 리액션',
    'icon_type' => 'image',
    'icon_value' => $imageStorageReference,
    'status' => 'active',
], 1);
$assert(!empty($imageDefinitionCreate['ok']), 'admin definition create should accept a valid uploaded image storage reference.');
$imageIconHtml = sr_reaction_public_icon_html([
    'icon_type' => 'image',
    'icon_value' => $imageStorageReference,
]);
$assert(
    str_contains($imageIconHtml, 'class="sr-reaction-image"') && str_contains($imageIconHtml, '/reaction/icon?file='),
    'public image icon renderer should use the reaction icon endpoint.'
);
$invalidImageIconHtml = sr_reaction_public_icon_html([
    'icon_type' => 'image',
    'icon_value' => 'local:reaction/icons/bad.webp',
]);
$assert($invalidImageIconHtml === '', 'public image icon renderer should reject invalid storage references.');
$likeDefinitionId = (int) $pdo->query("SELECT id FROM sr_reaction_definitions WHERE reaction_key = 'like'")->fetchColumn();
$definitionUpdate = sr_reaction_save_definition($pdo, [
    'id' => $likeDefinitionId,
    'label' => '좋아요 수정',
    'icon_type' => 'emoji',
    'icon_value' => 'ok',
    'color_hex' => '#111111',
    'description' => '표시 수정',
    'status' => 'active',
    'sort_order' => 15,
], 1);
$assert(!empty($definitionUpdate['ok']) && (string) ($definitionUpdate['reaction_key'] ?? '') === 'like', 'admin definition update should preserve and return the stable key.');
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
$limitedPreset = sr_reaction_save_preset($pdo, [
    'preset_key' => 'limited',
    'label' => '제한형',
    'description' => '표시 수 제한 테스트',
    'status' => 'active',
    'visible_key_limit' => 1,
    'sort_order' => 60,
    'reaction_keys' => ['like', 'fun', 'sad'],
], 1);
$assert(!empty($limitedPreset['ok']), 'admin preset create should save visible key limit.');
$assert(sr_reaction_allowed_keys($pdo, ['reaction_keys' => ['sad', 'like']]) === ['sad', 'like'], 'explicit reaction keys should be used when no preset key is supplied.');
$assert(sr_reaction_allowed_keys($pdo, ['preset_key' => 'limited', 'reaction_keys' => ['sad']]) === ['like'], 'preset key should take priority over explicit keys and enforce visible limit.');
$fallbackKeys = sr_reaction_allowed_keys($pdo, ['preset_key' => 'missing_preset', 'reaction_keys' => ['fun']]);
$assert(in_array('like', $fallbackKeys, true) && in_array('sad', $fallbackKeys, true), 'missing preset should fall back to the default preset.');
$pdo->exec("INSERT INTO sr_reaction_records (account_id, target_module, target_type, target_id, reaction_key, created_at, updated_at) VALUES (3, 'community', 'post', '1', 'like', '$now', '$now')");
$adminRecordFilters = sr_reaction_admin_record_filters([
    'account_id' => '3',
    'target_module' => 'community',
    'target_type' => 'post',
    'target_id' => '1',
    'reaction_key' => 'like',
]);
$assert($adminRecordFilters['account_id'] === 3 && $adminRecordFilters['target_id'] === '1', 'admin record filters should normalize query input.');
$adminRecords = sr_reaction_admin_records($pdo, ['account_id' => '3', 'target_module' => 'community', 'target_type' => 'post'], 20);
$assert(count($adminRecords) === 1 && (string) ($adminRecords[0]['reaction_key'] ?? '') === 'like', 'admin record lookup should filter reaction records.');

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
$pdo->exec(
    "INSERT INTO sr_reaction_records (account_id, target_module, target_type, target_id, reaction_key, created_at, updated_at) VALUES
        (21, 'content', 'content', '9001', 'like', '$now', '$now'),
        (22, 'content', 'comment', '9002', 'like', '$now', '$now'),
        (23, 'content', 'comment', '9003', 'like', '$now', '$now'),
        (24, 'quiz', 'quiz_set', '9001', 'like', '$now', '$now');"
);
$deletedContentReactions = sr_reaction_delete_target_records($pdo, 'content', 'content', [9001]);
$deletedCommentReactions = sr_reaction_delete_target_records($pdo, 'content', 'comment', [9002, '9002', 'bad', 0]);
$assert($deletedContentReactions === 1 && $deletedCommentReactions === 1, 'target reaction delete helper should delete valid unique target ids.');
$assert((int) $pdo->query("SELECT COUNT(*) FROM sr_reaction_records WHERE target_module = 'content' AND target_type = 'comment' AND target_id = '9003'")->fetchColumn() === 1, 'target reaction delete helper should keep unrelated comment targets.');
$assert((int) $pdo->query("SELECT COUNT(*) FROM sr_reaction_records WHERE target_module = 'quiz' AND target_type = 'quiz_set' AND target_id = '9001'")->fetchColumn() === 1, 'target reaction delete helper should keep other module targets.');

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
        $assert((string) ($target['target_module'] ?? '') === $moduleKey, $moduleKey . ' reaction target_module should match provider module.');
        $assert(is_callable($target['resolve'] ?? null), $moduleKey . ' reaction target should provide resolve.');
        $assert(is_callable($target['batch_resolve'] ?? null), $moduleKey . ' reaction target should provide batch_resolve.');
    }
}
foreach ($expectedTargets as $targetKey) {
    $assert(isset($contractTargets[$targetKey]), $targetKey . ' should be provided by reaction target contracts.');
}

$reactionHelperSource = sr_reaction_check_read('modules/reaction/helpers.php');
$assert(
    str_contains($reactionHelperSource, '$contractTargetModule !== $providerModuleKey'),
    'reaction target loading should ignore targets whose target_module is owned by another provider module.'
);

$reactionConsumerFiles = sr_reaction_check_php_files([
    'modules/content',
    'modules/community',
    'modules/quiz',
    'modules/survey',
]);
$reactionRuntimeFunctions = [
    "function_exists('sr_reaction_render_widget')",
    "function_exists('sr_reaction_public_script_html')",
    "function_exists('sr_reaction_resolve_targets')",
    "function_exists('sr_reaction_setting_preset_key')",
    "function_exists('sr_reaction_preset_option_html')",
];
foreach ($reactionConsumerFiles as $file) {
    $relativePath = substr($file, strlen(SR_ROOT) + 1);
    $source = file_get_contents($file);
    if (!is_string($source)) {
        $assert(false, 'cannot read reaction consumer ' . $relativePath);
        continue;
    }
    $source = str_replace(["\r\n", "\r"], "\n", $source);
    $lines = explode("\n", $source);

    $assert(
        !str_contains($source, "sr_module_enabled(, 'reaction')"),
        $relativePath . ' should pass a PDO when checking the reaction module.'
    );

    foreach ($lines as $lineNumber => $line) {
        $lineLabel = $relativePath . ':' . (string) ($lineNumber + 1);
        $context = implode("\n", array_slice($lines, max(0, $lineNumber - 3), 7));
        $hasReactionEnabledGuard = str_contains($context, 'sr_module_enabled($pdo, \'reaction\')')
            || str_contains($context, 'sr_module_enabled($GLOBALS[\'pdo\'], \'reaction\')')
            || str_contains($context, '$reactionModuleEnabled')
            || str_contains($context, '$contentReactionAvailable')
            || str_contains($context, '$communityReactionAvailable')
            || str_contains($context, '$quizReactionAvailable')
            || str_contains($context, '$surveyReactionAvailable');

        if (str_contains($line, '/modules/reaction/helpers.php')) {
            $assert($hasReactionEnabledGuard, $lineLabel . ' should only load reaction helpers when the reaction module is enabled.');
        }

        foreach ($reactionRuntimeFunctions as $functionGuard) {
            if (str_contains($line, $functionGuard)) {
                $assert($hasReactionEnabledGuard, $lineLabel . ' should only use reaction runtime helpers when the reaction module is enabled.');
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "reaction runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "reaction runtime checks completed.\n";
