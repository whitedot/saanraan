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
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$GLOBALS['pdo'] = $pdo;
$pdo->exec(
    'CREATE TABLE sr_site_settings (
        setting_key TEXT NOT NULL PRIMARY KEY,
        setting_value TEXT NOT NULL,
        value_type TEXT NOT NULL
    );
    CREATE TABLE sr_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL UNIQUE,
        version TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL
    );
    CREATE TABLE sr_module_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_id INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT,
        value_type TEXT NOT NULL DEFAULT \'string\',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE (module_id, setting_key)
    );
    CREATE TABLE sr_reaction_presets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        preset_key TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT \'active\',
        selection_policy TEXT NOT NULL DEFAULT \'single\',
        visible_key_limit INTEGER NOT NULL DEFAULT 6,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE sr_quiz_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER NOT NULL,
        account_id INTEGER,
        submitted_at TEXT,
        total_score INTEGER,
        passed INTEGER,
        scoring_snapshot_json TEXT,
        result_snapshot_json TEXT
    );
    CREATE TABLE sr_survey_responses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_id INTEGER NOT NULL,
        account_id INTEGER,
        submitted_at TEXT
    );'
);

$now = sr_now();
$pdo->exec(
    "INSERT INTO sr_modules (module_key, version, status) VALUES
        ('reaction', 'test', 'enabled'),
        ('quiz', 'test', 'enabled'),
        ('survey', 'test', 'enabled');
     INSERT INTO sr_reaction_presets (preset_key, label, status, selection_policy, visible_key_limit, created_at, updated_at)
        VALUES ('emotions', '감정형', 'active', 'single', 6, '$now', '$now');
     INSERT INTO sr_quiz_attempts (quiz_id, account_id, submitted_at, total_score, passed, scoring_snapshot_json, result_snapshot_json)
        VALUES (1, 9, '$now', 10, 1, '{}', '{}');
     INSERT INTO sr_survey_responses (survey_id, account_id, submitted_at)
        VALUES (1, 9, '$now');"
);

$moduleIds = [];
foreach ($pdo->query('SELECT id, module_key FROM sr_modules')->fetchAll() as $moduleRow) {
    $moduleIds[(string) $moduleRow['module_key']] = (int) $moduleRow['id'];
}
$settingStmt = $pdo->prepare(
    'INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
     VALUES (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)'
);
foreach (['quiz', 'survey'] as $moduleKey) {
    foreach ([
        'reaction_enabled' => ['1', 'boolean'],
        'reaction_preset_key' => ['emotions', 'string'],
        'reaction_comment_preset_key' => ['emotions', 'string'],
    ] as $settingKey => [$settingValue, $valueType]) {
        $settingStmt->execute([
            'module_id' => $moduleIds[$moduleKey],
            'setting_key' => $settingKey,
            'setting_value' => $settingValue,
            'value_type' => $valueType,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

require_once SR_ROOT . '/modules/quiz/reaction-targets.php';
require_once SR_ROOT . '/modules/survey/reaction-targets.php';

$quiz = [
    'id' => 1,
    'quiz_key' => 'fixture_quiz',
    'title' => 'Fixture quiz',
    'status' => 'active',
    'starts_at' => null,
    'ends_at' => null,
    'member_group_keys_json' => '[]',
    'created_by_account_id' => 2,
    'reaction_preset_key' => sr_reaction_disabled_preset_key(),
];
$quizComment = [
    'id' => 11,
    'quiz_id' => 1,
    'author_account_id' => 3,
    'is_secret' => 0,
    'comment_status' => 'published',
    'quiz_key' => 'fixture_quiz',
    'quiz_title' => 'Fixture quiz',
    'quiz_status' => 'active',
    'starts_at' => null,
    'ends_at' => null,
    'reaction_preset_key' => sr_reaction_disabled_preset_key(),
    'reaction_comment_preset_key' => '',
    'member_group_keys_json' => '[]',
    'quiz_owner_account_id' => 2,
    'quiz_deleted_at' => null,
];
$quizBodyDisabled = sr_quiz_reaction_quiz_result($pdo, $quiz, 9);
$quizCommentEnabled = sr_quiz_reaction_comment_result($pdo, $quizComment, 9);
$assert(!$quizBodyDisabled['can_view'] && $quizBodyDisabled['preset_key'] === '', 'quiz body opt-out must hide the body target.');
$assert($quizCommentEnabled['can_view'] && $quizCommentEnabled['can_write'], 'quiz comment reactions must remain available when only the body target opts out.');

$quiz['reaction_preset_key'] = '';
$quizComment['reaction_preset_key'] = '';
$quizComment['reaction_comment_preset_key'] = sr_reaction_disabled_preset_key();
$quizBodyEnabled = sr_quiz_reaction_quiz_result($pdo, $quiz, 9);
$quizCommentDisabled = sr_quiz_reaction_comment_result($pdo, $quizComment, 9);
$assert($quizBodyEnabled['can_view'] && $quizBodyEnabled['can_write'], 'quiz body reactions must remain available when only comments opt out.');
$assert(!$quizCommentDisabled['can_view'] && $quizCommentDisabled['preset_key'] === '', 'quiz comment opt-out must hide the comment target.');

$survey = [
    'id' => 1,
    'survey_key' => 'fixture_survey',
    'title' => 'Fixture survey',
    'status' => 'active',
    'starts_at' => null,
    'ends_at' => null,
    'login_required' => 1,
    'member_group_keys_json' => '[]',
    'created_by_account_id' => 2,
    'reaction_preset_key' => sr_reaction_disabled_preset_key(),
];
$surveyComment = [
    'id' => 21,
    'survey_id' => 1,
    'author_account_id' => 3,
    'is_secret' => 0,
    'comment_status' => 'published',
    'survey_key' => 'fixture_survey',
    'survey_title' => 'Fixture survey',
    'survey_status' => 'active',
    'starts_at' => null,
    'ends_at' => null,
    'login_required' => 1,
    'member_group_keys_json' => '[]',
    'reaction_preset_key' => sr_reaction_disabled_preset_key(),
    'reaction_comment_preset_key' => '',
    'survey_owner_account_id' => 2,
    'survey_deleted_at' => null,
];
$surveyBodyDisabled = sr_survey_reaction_survey_result($pdo, $survey, 9);
$surveyCommentEnabled = sr_survey_reaction_comment_result($pdo, $surveyComment, 9);
$assert(!$surveyBodyDisabled['can_view'] && $surveyBodyDisabled['preset_key'] === '', 'survey body opt-out must hide the body target.');
$assert($surveyCommentEnabled['can_view'] && $surveyCommentEnabled['can_write'], 'survey comment reactions must remain available when only the body target opts out.');

$survey['reaction_preset_key'] = '';
$surveyComment['reaction_preset_key'] = '';
$surveyComment['reaction_comment_preset_key'] = sr_reaction_disabled_preset_key();
$surveyBodyEnabled = sr_survey_reaction_survey_result($pdo, $survey, 9);
$surveyCommentDisabled = sr_survey_reaction_comment_result($pdo, $surveyComment, 9);
$assert($surveyBodyEnabled['can_view'] && $surveyBodyEnabled['can_write'], 'survey body reactions must remain available when only comments opt out.');
$assert(!$surveyCommentDisabled['can_view'] && $surveyCommentDisabled['preset_key'] === '', 'survey comment opt-out must hide the comment target.');

$pdo->exec("UPDATE sr_module_settings SET setting_value = '0' WHERE setting_key = 'reaction_enabled'");
sr_clear_module_settings_cache();
$assert(!sr_quiz_reaction_quiz_result($pdo, $quiz, 9)['can_view'], 'quiz global opt-out must hide reaction targets.');
$assert(!sr_survey_reaction_survey_result($pdo, $survey, 9)['can_view'], 'survey global opt-out must hide reaction targets.');

$pdo->exec("UPDATE sr_module_settings SET setting_value = '1' WHERE setting_key = 'reaction_enabled'");
$pdo->exec("UPDATE sr_modules SET status = 'disabled' WHERE module_key = 'reaction'");
sr_clear_module_settings_cache();
sr_clear_module_registry_cache();
$assert(!sr_quiz_reaction_quiz_result($pdo, $quiz, 9)['can_view'], 'quiz target must fail closed when the reaction provider is disabled.');
$assert(!sr_survey_reaction_survey_result($pdo, $survey, 9)['can_view'], 'survey target must fail closed when the reaction provider is disabled.');

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "reaction consumer state checks completed.\n");
