#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/admin/helpers.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE sr_admin_form_drafts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        form_key TEXT NOT NULL,
        context_key TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        base_fingerprint TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(account_id, form_key, context_key)
    )'
);

$baseFingerprint = sr_admin_form_draft_fingerprint(['enabled' => true, 'title' => '기존']);
sr_admin_form_draft_save($pdo, 7, 'content.settings', 'default', [
    'csrf_token' => 'secret-token',
    'admin_form_action' => 'save_draft',
    'title' => '임시 제목',
    'enabled' => '1',
    'items' => ['첫째', '둘째'],
], $baseFingerprint);

$draft = sr_admin_form_draft_get($pdo, 7, 'content.settings', 'default');
$assert(is_array($draft), 'Saved admin form draft must be readable.');
$payload = is_array($draft) ? (array) ($draft['payload'] ?? []) : [];
$assert(($payload['title'] ?? '') === '임시 제목', 'Draft payload must preserve scalar values.');
$assert(($payload['items'] ?? []) === ['첫째', '둘째'], 'Draft payload must preserve array values.');
$assert(!array_key_exists('csrf_token', $payload), 'Draft payload must not retain CSRF tokens.');
$assert(!array_key_exists('admin_form_action', $payload), 'Draft payload must not retain action controls.');

sr_admin_form_draft_save($pdo, 7, 'content.settings', 'default', ['title' => '두 번째'], $baseFingerprint);
$rowCount = (int) $pdo->query('SELECT COUNT(*) FROM sr_admin_form_drafts')->fetchColumn();
$assert($rowCount === 1, 'Saving the same account/form/context must update one draft row.');
$updatedDraft = sr_admin_form_draft_get($pdo, 7, 'content.settings', 'default');
$assert((string) (($updatedDraft['payload']['title'] ?? '')) === '두 번째', 'Draft upsert must replace the payload.');

$sameState = sr_admin_form_draft_with_state($updatedDraft, $baseFingerprint);
$changedState = sr_admin_form_draft_with_state($updatedDraft, sr_admin_form_draft_fingerprint(['enabled' => false]));
$assert(empty($sameState['is_stale']), 'Matching source fingerprint must not mark a draft stale.');
$assert(!empty($changedState['is_stale']), 'Changed source fingerprint must mark a draft stale.');

$applied = sr_admin_form_draft_apply_settings(
    ['enabled' => true, 'count' => 3, 'title' => '원본'],
    ['count' => '9', 'title' => '복원'],
    ['enabled']
);
$assert($applied === ['enabled' => false, 'count' => 9, 'title' => '복원'], 'Settings overlay must restore types and unchecked booleans.');
$appliedValues = sr_admin_form_draft_apply_values(
    ['enabled' => 1, 'title' => '원본'],
    ['title' => '입력 중', 'source_status' => 'group'],
    ['enabled']
);
$assert($appliedValues === ['enabled' => 0, 'title' => '입력 중', 'source_status' => 'group'], 'Editor overlay must restore arbitrary scalar controls and unchecked booleans.');
$parallelRows = sr_admin_form_draft_parallel_rows([
    'keys' => ['first', 'second'],
    'labels' => ['첫째'],
    'targets' => ['', 'menu_key'],
], ['area_key' => 'keys', 'label' => 'labels', 'menu_key' => 'targets']);
$assert($parallelRows === [
    ['area_key' => 'first', 'label' => '첫째', 'menu_key' => ''],
    ['area_key' => 'second', 'label' => '', 'menu_key' => 'menu_key'],
], 'Parallel draft rows must preserve incomplete repeated controls.');
if ($errors !== []) {
    fwrite(STDERR, "admin form draft checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "admin form draft checks completed.\n";
