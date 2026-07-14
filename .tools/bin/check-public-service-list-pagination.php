#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
require_once $root . '/core/helpers.php';
require_once $root . '/modules/quiz/helpers.php';
require_once $root . '/modules/survey/helpers.php';
require_once $root . '/modules/coupon/helpers.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};
$source = static function (string $file) use ($root, &$errors): string {
    $contents = file_get_contents($root . '/' . $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read public service list source: ' . $file;
        return '';
    }
    return $contents;
};

foreach ([
    'modules/quiz/actions/home.php' => ['sr_quiz_public_quiz_count(', '$quizListPagination', 'sr_quiz_public_quizzes($pdo, $quizListPerPage,'],
    'modules/survey/actions/home.php' => ['sr_survey_public_form_count(', '$surveyListPagination', 'sr_survey_public_forms($pdo, $surveyListPerPage,'],
    'modules/coupon/actions/coupons.php' => ['sr_coupon_public_claim_campaign_count(', '$couponCampaignPagination', 'sr_coupon_public_claim_campaigns($pdo, $accountId, $couponCampaignPerPage,'],
] as $file => $markers) {
    $contents = $source($file);
    foreach ($markers as $marker) {
        $assert(str_contains($contents, $marker), $file . ' missing public full-list marker: ' . $marker);
    }
}
foreach ([
    'modules/quiz/theme/basic/home.php' => "sr_public_pagination_html(\$quizListPagination, '/quiz'",
    'modules/quiz/theme/sample/home.php' => "sr_public_pagination_html(\$quizListPagination, '/quiz'",
    'modules/survey/theme/basic/home.php' => "sr_public_pagination_html(\$surveyListPagination, '/survey'",
    'modules/survey/theme/sample/home.php' => "sr_public_pagination_html(\$surveyListPagination, '/survey'",
    'modules/coupon/views/coupons.php' => "sr_public_pagination_html(\$couponCampaignPagination, '/coupons'",
] as $file => $marker) {
    $assert(str_contains($source($file), $marker), $file . ' must render public full-list navigation.');
}

$quizPdo = new PDO('sqlite::memory:');
$quizPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$quizPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$quizPdo->exec(
    'CREATE TABLE sr_quiz_sets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_key TEXT NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        cover_image_url TEXT NOT NULL,
        status TEXT NOT NULL,
        starts_at TEXT NULL,
        ends_at TEXT NULL,
        created_at TEXT NOT NULL,
        deleted_at TEXT NULL
    )'
);
$insertQuiz = $quizPdo->prepare(
    "INSERT INTO sr_quiz_sets
     (quiz_key, title, description, cover_image_url, status, starts_at, ends_at, created_at, deleted_at)
     VALUES (:quiz_key, :title, '', '', 'active', NULL, NULL, :created_at, NULL)"
);
for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
    $insertQuiz->execute([
        'quiz_key' => 'quiz_' . (string) $rowNumber,
        'title' => 'Quiz ' . (string) $rowNumber,
        'created_at' => sprintf('2026-07-14 00:%02d:00', $rowNumber),
    ]);
}
$assert(sr_quiz_public_quiz_count($quizPdo) === 45, 'quiz public count must include every open quiz.');
$quizFinalPage = sr_quiz_public_quizzes($quizPdo, 20, 40);
$assert(count($quizFinalPage) === 5 && (int) ($quizFinalPage[0]['id'] ?? 0) === 5 && (int) ($quizFinalPage[4]['id'] ?? 0) === 1, 'quiz public list must expose the final partial page.');

$surveyPdo = new PDO('sqlite::memory:');
$surveyPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$surveyPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$surveyPdo->exec(
    'CREATE TABLE sr_survey_forms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_key TEXT NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        cover_image_url TEXT NOT NULL,
        status TEXT NOT NULL,
        public_listed INTEGER NOT NULL,
        starts_at TEXT NULL,
        ends_at TEXT NULL,
        updated_at TEXT NOT NULL,
        deleted_at TEXT NULL
    )'
);
$insertSurvey = $surveyPdo->prepare(
    "INSERT INTO sr_survey_forms
     (survey_key, title, description, cover_image_url, status, public_listed, starts_at, ends_at, updated_at, deleted_at)
     VALUES (:survey_key, :title, '', '', 'active', 1, NULL, NULL, :updated_at, NULL)"
);
for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
    $insertSurvey->execute([
        'survey_key' => 'survey_' . (string) $rowNumber,
        'title' => 'Survey ' . (string) $rowNumber,
        'updated_at' => sprintf('2026-07-14 00:%02d:00', $rowNumber),
    ]);
}
$assert(sr_survey_public_form_count($surveyPdo) === 45, 'survey public count must include every listed open survey.');
$surveyFinalPage = sr_survey_public_forms($surveyPdo, 20, 40);
$assert(count($surveyFinalPage) === 5 && (int) ($surveyFinalPage[0]['id'] ?? 0) === 5 && (int) ($surveyFinalPage[4]['id'] ?? 0) === 1, 'survey public list must expose the final partial page.');

$couponPdo = new PDO('sqlite::memory:');
$couponPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$couponPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$couponPdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, status TEXT NOT NULL)');
$couponPdo->exec('CREATE TABLE sr_module_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, module_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL)');
$couponPdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('coupon', 'enabled')");
$couponPdo->exec('CREATE TABLE sr_coupon_issues (id INTEGER PRIMARY KEY AUTOINCREMENT)');
$couponPdo->exec('CREATE TABLE sr_coupon_redemptions (id INTEGER PRIMARY KEY AUTOINCREMENT)');
$couponPdo->exec(
    'CREATE TABLE sr_coupon_definitions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        coupon_key TEXT NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        status TEXT NOT NULL,
        target_type TEXT NOT NULL,
        target_id TEXT NOT NULL,
        max_uses_per_issue INTEGER NOT NULL
    )'
);
$couponPdo->exec(
    'CREATE TABLE sr_coupon_claim_campaigns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_key TEXT NOT NULL,
        coupon_definition_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        status TEXT NOT NULL,
        claim_type TEXT NOT NULL,
        price_amount INTEGER NULL,
        price_currency_code TEXT NOT NULL,
        allowed_asset_modules_json TEXT NOT NULL,
        total_claim_limit INTEGER NULL,
        per_account_limit INTEGER NOT NULL,
        visibility TEXT NOT NULL,
        exposure_surfaces_json TEXT NOT NULL,
        starts_at TEXT NULL,
        ends_at TEXT NULL
    )'
);
$couponPdo->exec('CREATE TABLE sr_coupon_claim_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER NOT NULL, status TEXT NOT NULL, reserved_until TEXT NULL)');
$insertDefinition = $couponPdo->prepare(
    "INSERT INTO sr_coupon_definitions
     (coupon_key, title, description, status, target_type, target_id, max_uses_per_issue)
     VALUES (:coupon_key, :title, '', 'active', 'all', '', 1)"
);
$insertCampaign = $couponPdo->prepare(
    "INSERT INTO sr_coupon_claim_campaigns
     (campaign_key, coupon_definition_id, title, description, status, claim_type, price_amount, price_currency_code, allowed_asset_modules_json, total_claim_limit, per_account_limit, visibility, exposure_surfaces_json, starts_at, ends_at)
     VALUES (:campaign_key, :definition_id, :title, '', 'active', 'free', NULL, '', '[]', NULL, 1, 'public', '[\"coupon_zone\"]', NULL, NULL)"
);
for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
    $insertDefinition->execute(['coupon_key' => 'coupon_' . (string) $rowNumber, 'title' => 'Coupon ' . (string) $rowNumber]);
    $insertCampaign->execute([
        'campaign_key' => 'campaign_' . (string) $rowNumber,
        'definition_id' => (int) $couponPdo->lastInsertId(),
        'title' => 'Campaign ' . (string) $rowNumber,
    ]);
}
$assert(sr_coupon_public_claim_campaign_count($couponPdo) === 45, 'coupon zone count must include every open campaign.');
$couponFinalPage = sr_coupon_public_claim_campaigns($couponPdo, 0, 20, 40);
$assert(count($couponFinalPage) === 5 && (int) ($couponFinalPage[0]['id'] ?? 0) === 5 && (int) ($couponFinalPage[4]['id'] ?? 0) === 1, 'coupon zone must expose the final partial page.');

if ($errors !== []) {
    fwrite(STDERR, "public service list pagination checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "public service list pagination checks completed.\n";
