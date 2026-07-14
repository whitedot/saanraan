#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
require_once $root . '/core/helpers.php';
require_once $root . '/modules/survey/helpers.php';
require_once $root . '/modules/quiz/helpers.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};
$source = static function (string $file) use ($root, &$errors): string {
    $contents = file_get_contents($root . '/' . $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read admin selector source: ' . $file;
        return '';
    }
    return $contents;
};

foreach ([
    'modules/survey/actions/admin-responses.php',
    'modules/survey/actions/admin-statistics.php',
    'modules/survey/actions/admin-reward-logs.php',
] as $file) {
    $assert(str_contains($source($file), 'sr_survey_admin_survey_options($pdo,'), $file . ' must preserve the selected survey outside the bounded option set.');
}
$assert(str_contains($source('modules/survey/actions/admin-surveys.php'), 'sr_survey_coupon_definitions($pdo, is_array($editPolicy)'), 'survey editor must preserve its selected reward coupon.');
$assert(str_contains($source('modules/quiz/actions/admin-settings.php'), "sr_quiz_reward_coupon_definitions(\$pdo, (int) (\$settings['default_reward_coupon_definition_id']"), 'quiz settings must preserve the selected default reward coupon.');
$assert(str_contains($source('modules/quiz/actions/admin-quiz.php'), "sr_quiz_reward_coupon_definitions(\$pdo, (int) (\$values['reward_coupon_definition_id']"), 'quiz editor must preserve the selected reward coupon.');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE sr_module_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, module_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL)');
$pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('coupon', 'enabled')");
$pdo->exec('CREATE TABLE sr_coupon_issues (id INTEGER PRIMARY KEY AUTOINCREMENT)');
$pdo->exec('CREATE TABLE sr_coupon_redemptions (id INTEGER PRIMARY KEY AUTOINCREMENT)');
$pdo->exec(
    'CREATE TABLE sr_coupon_definitions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        coupon_key TEXT NOT NULL,
        title TEXT NOT NULL,
        status TEXT NOT NULL,
        target_type TEXT NOT NULL,
        target_id TEXT NOT NULL,
        max_uses_per_issue INTEGER NOT NULL,
        valid_from TEXT NULL,
        valid_until TEXT NULL
    )'
);
$insertCoupon = $pdo->prepare(
    "INSERT INTO sr_coupon_definitions
     (coupon_key, title, status, target_type, target_id, max_uses_per_issue, valid_from, valid_until)
     VALUES (:coupon_key, :title, 'active', 'all', '', 1, NULL, NULL)"
);
for ($rowNumber = 1; $rowNumber <= 205; $rowNumber++) {
    $insertCoupon->execute([
        'coupon_key' => 'coupon_' . sprintf('%03d', $rowNumber),
        'title' => 'Coupon ' . sprintf('%03d', $rowNumber),
    ]);
}

$surveyCouponOptions = sr_survey_coupon_definitions($pdo, 205);
$assert(count($surveyCouponOptions) === 201, 'survey reward coupon selector must append the selected row beyond 200 candidates.');
$assert(in_array(205, array_map('intval', array_column($surveyCouponOptions, 'id')), true), 'survey reward coupon selector must keep the current selection.');
$quizCouponOptions = sr_quiz_reward_coupon_definitions($pdo, 205);
$assert(count($quizCouponOptions) === 201, 'quiz reward coupon selector must append the selected row beyond 200 candidates.');
$assert(in_array(205, array_map('intval', array_column($quizCouponOptions, 'id')), true), 'quiz reward coupon selector must keep the current selection.');

$pdo->exec(
    'CREATE TABLE sr_survey_forms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_key TEXT NOT NULL,
        title TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        deleted_at TEXT NULL
    )'
);
$insertSurvey = $pdo->prepare(
    "INSERT INTO sr_survey_forms (survey_key, title, updated_at, deleted_at)
     VALUES (:survey_key, :title, :updated_at, NULL)"
);
for ($rowNumber = 1; $rowNumber <= 305; $rowNumber++) {
    $insertSurvey->execute([
        'survey_key' => 'survey_' . (string) $rowNumber,
        'title' => 'Survey ' . (string) $rowNumber,
        'updated_at' => sprintf('2026-07-%02d 00:00:00', (($rowNumber - 1) % 28) + 1),
    ]);
}
$surveyOptions = sr_survey_admin_survey_options($pdo, 1, 300);
$assert(count($surveyOptions) === 301, 'survey selector must append the selected row beyond 300 candidates.');
$assert(in_array(1, array_map('intval', array_column($surveyOptions, 'id')), true), 'survey selector must keep the current selected survey.');

if ($errors !== []) {
    fwrite(STDERR, "admin selector current value checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "admin selector current value checks completed.\n";
