#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
require_once $root . '/modules/member/helpers/groups.php';
require_once $root . '/modules/survey/helpers.php';

$errors = [];
$source = file_get_contents($root . '/modules/survey/helpers.php');
if (!is_string($source)
    || !str_contains($source, 'AND INSTR(member_group_keys_json, :target_key_json) > 0')
    || !str_contains($source, "\$stmt->execute(['target_key_json' => '\"' . \$targetKey . '\"']);")
) {
    $errors[] = 'survey member-group references must prefilter the target key before the bounded reference query';
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE sr_survey_forms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_key TEXT NOT NULL,
        title TEXT NOT NULL,
        status TEXT NOT NULL,
        member_group_keys_json TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        deleted_at TEXT NULL
    )'
);
$insert = $pdo->prepare(
    "INSERT INTO sr_survey_forms (survey_key, title, status, member_group_keys_json, updated_at, deleted_at)
     VALUES (:survey_key, :title, 'active', :group_keys, :updated_at, NULL)"
);
$insert->execute([
    'survey_key' => 'old_target',
    'title' => 'Old target survey',
    'group_keys' => '["vip_target"]',
    'updated_at' => '2000-01-01 00:00:00',
]);
for ($rowNumber = 1; $rowNumber <= 500; $rowNumber++) {
    $insert->execute([
        'survey_key' => 'recent_other_' . (string) $rowNumber,
        'title' => 'Recent other survey ' . (string) $rowNumber,
        'group_keys' => '["other_group"]',
        'updated_at' => '2026-01-01 00:00:00',
    ]);
}
$insert->execute([
    'survey_key' => 'similar_key',
    'title' => 'Similar key survey',
    'group_keys' => '["vipXtarget"]',
    'updated_at' => '2027-01-01 00:00:00',
]);

$rows = sr_survey_member_group_reference_rows($pdo, ['target_key' => 'vip_target'], []);
if (count($rows) !== 1 || (string) ($rows[0]['reference_id'] ?? '') !== 'survey_form:1') {
    $errors[] = 'survey member-group reference lookup must find an older exact target beyond 500 unrelated recent surveys';
}
if (sr_survey_member_group_reference_count($pdo, ['target_key' => 'vip_target'], []) !== 1) {
    $errors[] = 'survey member-group reference count must use the same prefiltered exact rows';
}

if ($errors !== []) {
    fwrite(STDERR, "survey member-group reference checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "survey member-group reference checks completed.\n";
