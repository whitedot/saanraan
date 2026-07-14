#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
$errors = [];
$source = static function (string $file) use ($root, &$errors): string {
    $contents = file_get_contents($root . '/' . $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read admin domain pagination source: ' . $file;
        return '';
    }

    return $contents;
};
$assertContains = static function (string $file, array $markers) use ($source, &$errors): void {
    $contents = $source($file);
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = $file . ' missing admin domain pagination marker: ' . $marker;
        }
    }
};

$assertContains('modules/survey/helpers/admin-surveys.php', [
    'function sr_survey_admin_list_count(',
    'function sr_survey_admin_list_rows(',
    'sr_admin_pagination_from_total(',
    'LIMIT :limit_value OFFSET :offset_value',
]);
$assertContains('modules/survey/actions/admin-surveys.php', [
    'sr_admin_pagination_summary_html($surveyPagination)',
    'sr_admin_pagination_html($surveyPagination',
    'name="return_to"',
    "sr_admin_post_return_url('/admin/surveys')",
]);
$assertContains('modules/reaction/helpers/admin.php', [
    'function sr_reaction_admin_record_count(',
    'int $limit = 100, int $offset = 0',
    'LIMIT :limit_value OFFSET :offset_value',
]);
$assertContains('modules/reaction/actions/admin-reactions.php', [
    'sr_admin_pagination_from_total(',
    'sr_reaction_admin_record_count(',
    'sr_admin_pagination_offset($reactionRecordPagination)',
]);
$assertContains('modules/reaction/views/admin-reactions.php', [
    'sr_admin_pagination_summary_html($reactionRecordPagination)',
    'sr_admin_pagination_html($reactionRecordPagination',
]);

require_once $root . '/modules/survey/helpers/admin-surveys.php';
$surveyPdo = new PDO('sqlite::memory:');
$surveyPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$surveyPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$surveyPdo->exec(
    'CREATE TABLE sr_survey_forms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_key TEXT NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        status TEXT NOT NULL,
        starts_at TEXT NULL,
        ends_at TEXT NULL,
        qa_status TEXT NOT NULL,
        member_group_keys_json TEXT NOT NULL,
        view_count INTEGER NOT NULL,
        reward_enabled INTEGER NOT NULL,
        updated_at TEXT NOT NULL,
        deleted_at TEXT NULL
    )'
);
$surveyPdo->exec('CREATE TABLE sr_survey_responses (id INTEGER PRIMARY KEY AUTOINCREMENT, survey_id INTEGER NOT NULL)');
$surveyPdo->exec('CREATE TABLE sr_survey_reward_grants (id INTEGER PRIMARY KEY AUTOINCREMENT, survey_id INTEGER NOT NULL)');
$surveyPdo->exec('CREATE TABLE sr_survey_storage_cleanup_failures (id INTEGER PRIMARY KEY AUTOINCREMENT, source_id INTEGER NOT NULL, status TEXT NOT NULL)');
$insertSurvey = $surveyPdo->prepare(
    "INSERT INTO sr_survey_forms
     (survey_key, title, description, status, starts_at, ends_at, qa_status, member_group_keys_json, view_count, reward_enabled, updated_at, deleted_at)
     VALUES (:survey_key, :title, '', :status, NULL, NULL, 'unchecked', '[]', 0, 0, :updated_at, NULL)"
);
for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
    $insertSurvey->execute([
        'survey_key' => 'survey_' . (string) $rowNumber,
        'title' => 'Survey ' . (string) $rowNumber,
        'status' => $rowNumber % 2 === 0 ? 'active' : 'draft',
        'updated_at' => sprintf('2026-07-14 00:%02d:00', $rowNumber),
    ]);
}
if (sr_survey_admin_list_count($surveyPdo, ['s.deleted_at IS NULL'], []) !== 45) {
    $errors[] = 'survey admin count must include every matching form';
}
if (sr_survey_admin_list_count($surveyPdo, ['s.deleted_at IS NULL', 's.status = :status'], ['status' => 'active']) !== 22) {
    $errors[] = 'survey admin count must apply list filters';
}
$surveyFinalPage = sr_survey_admin_list_rows(
    $surveyPdo,
    ['s.deleted_at IS NULL'],
    [],
    'ORDER BY s.updated_at DESC, s.id DESC',
    20,
    40
);
if (count($surveyFinalPage) !== 5 || (int) ($surveyFinalPage[0]['id'] ?? 0) !== 5 || (int) ($surveyFinalPage[4]['id'] ?? 0) !== 1) {
    $errors[] = 'survey admin list must expose the final ordered partial page';
}

require_once $root . '/modules/reaction/helpers.php';
require_once $root . '/modules/reaction/helpers/admin.php';
$reactionPdo = new PDO('sqlite::memory:');
$reactionPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$reactionPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$reactionPdo->exec('CREATE TABLE sr_reaction_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, reaction_key TEXT NOT NULL, label TEXT NOT NULL, status TEXT NOT NULL)');
$reactionPdo->exec('CREATE TABLE sr_reaction_presets (id INTEGER PRIMARY KEY AUTOINCREMENT)');
$reactionPdo->exec('CREATE TABLE sr_reaction_preset_items (id INTEGER PRIMARY KEY AUTOINCREMENT)');
$reactionPdo->exec(
    'CREATE TABLE sr_reaction_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        target_module TEXT NOT NULL,
        target_type TEXT NOT NULL,
        target_id TEXT NOT NULL,
        reaction_key TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);
$reactionPdo->exec("INSERT INTO sr_reaction_definitions (reaction_key, label, status) VALUES ('like', 'Like', 'active')");
$insertReaction = $reactionPdo->prepare(
    "INSERT INTO sr_reaction_records
     (account_id, target_module, target_type, target_id, reaction_key, updated_at)
     VALUES (:account_id, 'survey', 'survey_form', :target_id, 'like', :updated_at)"
);
for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
    $insertReaction->execute([
        'account_id' => $rowNumber % 2 === 0 ? 2 : 1,
        'target_id' => (string) $rowNumber,
        'updated_at' => sprintf('2026-07-14 00:%02d:00', $rowNumber),
    ]);
}
if (sr_reaction_admin_record_count($reactionPdo) !== 45) {
    $errors[] = 'reaction record count must include every row';
}
if (sr_reaction_admin_record_count($reactionPdo, ['account_id' => 1, 'target_module' => 'survey']) !== 23) {
    $errors[] = 'reaction record count must apply combined filters';
}
$reactionFinalPage = sr_reaction_admin_records($reactionPdo, [], 20, 40);
if (count($reactionFinalPage) !== 5 || (int) ($reactionFinalPage[0]['id'] ?? 0) !== 5 || (int) ($reactionFinalPage[4]['id'] ?? 0) !== 1) {
    $errors[] = 'reaction record list must expose the final ordered partial page';
}

if ($errors !== []) {
    fwrite(STDERR, "admin domain list pagination checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "admin domain list pagination checks completed.\n";
