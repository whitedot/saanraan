#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

$errors = [];

function sr_community_draft_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_community_draft_check_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_community_draft_check_error($message);
    }
}

function sr_now(): string
{
    return date('Y-m-d H:i:s');
}

function sr_community_draft_check_file(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($content)) {
        sr_community_draft_check_error('Cannot read required file: ' . $path);
        return '';
    }

    return $content;
}

require_once SR_ROOT . '/modules/community/helpers/drafts.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE sr_community_post_drafts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        board_id INTEGER NOT NULL,
        draft_mode TEXT NOT NULL,
        post_id INTEGER NULL,
        context_hash TEXT NOT NULL UNIQUE,
        base_content_hash TEXT NULL,
        title TEXT NOT NULL DEFAULT "",
        body_format TEXT NOT NULL DEFAULT "plain",
        body_text TEXT NOT NULL,
        form_state_json TEXT NULL,
        body_tmp_ref_count INTEGER NOT NULL DEFAULT 0,
        last_saved_at TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);

$settings = [
    'draft_autosave_enabled' => true,
    'draft_autosave_interval_seconds' => 60,
    'draft_retention_days' => 7,
    'draft_max_count_per_account' => 2,
];

$first = sr_community_draft_upsert($pdo, [
    'account_id' => 10,
    'board_id' => 20,
    'draft_mode' => 'create',
    'post_id' => 0,
    'title' => '첫 초안',
    'body_format' => 'plain',
    'body_text' => '본문 1',
    'form_state_json' => sr_community_draft_form_state_json([
        'category_id' => 3,
        'is_secret' => 1,
        'extra_field_values' => ['mood' => 'calm'],
        'series_values' => ['series_mode' => 'new', 'new_series_title' => '연재'],
    ]),
], $settings);
sr_community_draft_check_assert(!empty($first['saved']), 'Draft upsert should save a create draft.');
$sameContext = sr_community_draft_upsert($pdo, [
    'account_id' => 10,
    'board_id' => 20,
    'draft_mode' => 'create',
    'post_id' => 0,
    'title' => '첫 초안 수정',
    'body_format' => 'plain',
    'body_text' => '본문 2',
    'form_state_json' => sr_community_draft_form_state_json(['category_id' => 4]),
], $settings);
sr_community_draft_check_assert(!empty($sameContext['saved']), 'Draft upsert should update an existing context.');
$count = (int) $pdo->query('SELECT COUNT(*) FROM sr_community_post_drafts')->fetchColumn();
sr_community_draft_check_assert($count === 1, 'Context hash UNIQUE upsert should keep one row for create mode without a post id.');
$row = sr_community_draft_fetch($pdo, 10, 20, 'create');
sr_community_draft_check_assert(is_array($row) && (string) ($row['title'] ?? '') === '첫 초안 수정', 'Last write should win for the same draft context.');
$payload = sr_community_draft_restore_payload($row);
sr_community_draft_check_assert((int) ($payload['category_id'] ?? 0) === 4, 'Restore payload should include form_state_json category values.');

sr_community_draft_upsert($pdo, ['account_id' => 10, 'board_id' => 21, 'draft_mode' => 'create', 'title' => '둘', 'body_text' => 'two'], $settings);
usleep(1000);
sr_community_draft_upsert($pdo, ['account_id' => 10, 'board_id' => 22, 'draft_mode' => 'edit', 'post_id' => 7, 'title' => '셋', 'body_text' => 'three'], $settings);
$countAfterTrim = (int) $pdo->query('SELECT COUNT(*) FROM sr_community_post_drafts WHERE account_id = 10')->fetchColumn();
sr_community_draft_check_assert($countAfterTrim === 2, 'Account draft trim should enforce the configured maximum after upsert.');

$old = date('Y-m-d H:i:s', time() - 10 * 86400);
$stmt = $pdo->prepare(
    'INSERT INTO sr_community_post_drafts
        (account_id, board_id, draft_mode, post_id, context_hash, title, body_format, body_text, form_state_json, body_tmp_ref_count, last_saved_at, created_at, updated_at)
     VALUES
        (11, 20, "create", NULL, :context_hash, "old", "plain", "old", "{}", 0, :old_time, :old_time, :old_time)'
);
$stmt->execute([
    'context_hash' => sr_community_draft_context_hash(11, 20, 'create'),
    'old_time' => $old,
]);
$deleted = sr_community_draft_cleanup($pdo, $settings, 10);
sr_community_draft_check_assert($deleted > 0, 'Draft cleanup should delete rows older than retention days.');

$hashA = sr_community_draft_content_hash([
    'title' => 'A',
    'body_format' => 'plain',
    'body_text' => 'Body',
    'category_id' => 1,
    'extra_values_json' => '{"x":"1"}',
]);
$hashB = sr_community_draft_content_hash([
    'title' => 'A',
    'body_format' => 'plain',
    'body_text' => 'Body',
    'category_id' => 1,
    'extra_values_json' => '{"x":"1"}',
    'series_values' => ['series_id' => 99],
]);
sr_community_draft_check_assert(hash_equals($hashA, $hashB), 'Draft content hash should exclude series-only form state.');

$paths = sr_community_draft_check_file(SR_ROOT . '/modules/community/paths.php');
sr_community_draft_check_assert(str_contains($paths, "'POST /community/draft/autosave' => 'actions/draft-autosave.php'"), 'Community paths should expose the autosave JSON action.');

$action = sr_community_draft_check_file(SR_ROOT . '/modules/community/actions/draft-autosave.php');
foreach ([
    'sr_member_require_login_json($pdo)',
    'sr_require_csrf()',
    'sr_json_response',
    'draft_autosave_disabled',
] as $marker) {
    sr_community_draft_check_assert(str_contains($action, $marker), 'Autosave action is missing marker: ' . $marker);
}
sr_community_draft_check_assert(!str_contains($action, 'header(') && !str_contains($action, 'json_encode('), 'Autosave action should use sr_json_response instead of direct JSON output.');

$form = sr_community_draft_check_file(SR_ROOT . '/modules/community/theme/basic/form.php');
sr_community_draft_check_assert(str_contains($form, 'data-community-draft-form') && str_contains($form, 'data-community-draft-payload'), 'Community form should expose draft config and restore payload.');

$js = sr_community_draft_check_file(SR_ROOT . '/modules/community/assets/module.js');
foreach (['sessionStorage', 'data-community-draft-restore', 'data-community-draft-discard', 'fetch(config.endpoint'] as $marker) {
    sr_community_draft_check_assert(str_contains($js, $marker), 'Community module JS is missing draft marker: ' . $marker);
}
sr_community_draft_check_assert(!str_contains($js, 'localStorage'), 'Community draft buffer should not use localStorage.');

$privacyExport = sr_community_draft_check_file(SR_ROOT . '/modules/community/privacy-export.php');
$privacyCleanup = sr_community_draft_check_file(SR_ROOT . '/modules/community/privacy-cleanup.php');
sr_community_draft_check_assert(str_contains($privacyExport, "'post_drafts'") && str_contains($privacyExport, 'sr_community_post_drafts'), 'Community privacy export should include post drafts.');
sr_community_draft_check_assert(str_contains($privacyCleanup, 'DELETE FROM sr_community_post_drafts'), 'Community privacy cleanup should delete account post drafts.');

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "community draft runtime checks completed.\n";
