#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

const SR_ROOT = __DIR__ . '/../..';

require_once SR_ROOT . '/core/version.php';
require_once SR_ROOT . '/core/helpers/runtime.php';
require_once SR_ROOT . '/core/helpers/settings.php';
require_once SR_ROOT . '/core/helpers/output.php';
require_once SR_ROOT . '/modules/banner/helpers.php';
require_once SR_ROOT . '/modules/popup_layer/helpers.php';

final class SrPopupLayerCheckStatement extends PDOStatement
{
    private array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }
}

final class SrPopupLayerCheckPdo extends PDO
{
    private array $moduleRows;

    public function __construct(array $moduleRows)
    {
        $this->moduleRows = $moduleRows;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (!str_contains($query, 'FROM sr_modules')) {
            return false;
        }

        return new SrPopupLayerCheckStatement($this->moduleRows);
    }
}

$pdo = new SrPopupLayerCheckPdo([
    ['module_key' => 'admin'],
    ['module_key' => 'content'],
    ['module_key' => 'member'],
    ['module_key' => 'community'],
    ['module_key' => 'banner'],
    ['module_key' => 'popup_layer'],
]);

$bannerTargets = sr_banner_available_targets($pdo);
$bannerTargetValues = [];
foreach ($bannerTargets as $target) {
    $bannerTargetValues[sr_banner_target_option_value($target)] = $target;
}

$targets = sr_popup_layer_available_targets($pdo);
$targetValues = [];
foreach ($targets as $target) {
    $targetValues[sr_popup_layer_target_option_value($target)] = true;
}

$errors = [];
$expectedTargets = [
    'content|content.view|before_content',
    'member|member.login|before_form',
    'member|member.register|after_form',
    'community|community.home|after_secondary_navigation',
    'community|community.post.view|after_comments',
    'community|community.post.form|before_form',
    'community|community.post.form|after_form',
];

$expectedBannerTargets = [
    'core|site.layout|before_layout',
    'core|site.layout|after_layout',
    'content|content.view|before_content',
    'community|community.home|after_secondary_navigation',
    'community|community.post.view|after_comments',
];

foreach ($expectedBannerTargets as $expectedTarget) {
    if (!isset($bannerTargetValues[$expectedTarget])) {
        $errors[] = 'missing banner target: ' . $expectedTarget;
    }
}

$bannerServices = sr_banner_target_service_options($bannerTargets, true);
foreach ([sr_banner_public_target_option_value(), 'core', 'content', 'community'] as $expectedService) {
    if (!isset($bannerServices[$expectedService])) {
        $errors[] = 'missing banner target service: ' . $expectedService;
    }
}

$bannerContentTarget = $bannerTargetValues['content|content.view|before_content'] ?? null;
if (!is_array($bannerContentTarget) || sr_banner_target_service_key($bannerContentTarget) !== 'content') {
    $errors[] = 'banner content target must map to content service.';
}

$bannerNormalized = sr_banner_normalize_posted_target_option(
    $bannerTargets,
    'content',
    'content|content.view|before_content',
    ''
);
if (($bannerNormalized['option'] ?? '') !== 'content|content.view|before_content' || (bool) ($bannerNormalized['is_public'] ?? true)) {
    $errors[] = 'banner target service/detail normalization failed.';
}

$bannerMismatch = sr_banner_normalize_posted_target_option(
    $bannerTargets,
    'community',
    'content|content.view|before_content',
    ''
);
if (($bannerMismatch['error'] ?? '') === '') {
    $errors[] = 'banner target normalization must reject service/detail mismatch.';
}

function sr_banner_check_runtime_fixture(): array
{
    $errors = [];
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY, module_key TEXT NOT NULL UNIQUE, version TEXT NOT NULL DEFAULT \'fixture\', status TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_modules (id, module_key, status) VALUES (1, 'content', 'enabled'), (2, 'banner', 'enabled')");
    $pdo->exec(
        'CREATE TABLE sr_banners (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            body_text TEXT,
            link_url TEXT NOT NULL DEFAULT \'\',
            image_url TEXT NOT NULL DEFAULT \'\',
            status TEXT NOT NULL DEFAULT \'draft\',
            skin_key TEXT NOT NULL DEFAULT \'basic\',
            starts_at TEXT NULL,
            ends_at TEXT NULL,
            sort_order INTEGER NOT NULL DEFAULT 100,
            click_count INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_banner_targets (
            id INTEGER PRIMARY KEY,
            banner_id INTEGER NOT NULL,
            module_key TEXT NOT NULL,
            point_key TEXT NOT NULL,
            slot_key TEXT NOT NULL,
            subject_id TEXT NOT NULL DEFAULT \'\',
            match_type TEXT NOT NULL DEFAULT \'all\',
            created_at TEXT NOT NULL
        )'
    );

    $now = sr_now();
    $bannerRows = [
        [1, 'draft hidden', 'draft', null, null, 10],
        [2, 'future hidden', 'enabled', '2999-01-01 00:00:00', null, 10],
        [3, 'expired hidden', 'enabled', null, '2000-01-01 00:00:00', 10],
        [4, 'all visible low sort', 'enabled', null, null, 20],
        [5, 'exact visible first', 'enabled', null, null, 10],
        [6, 'exact hidden', 'enabled', null, null, 10],
        [7, 'wrong slot hidden', 'enabled', null, null, 10],
    ];
    $bannerStmt = $pdo->prepare(
        'INSERT INTO sr_banners
            (id, title, body_text, link_url, image_url, status, skin_key, starts_at, ends_at, sort_order, click_count, created_at, updated_at)
         VALUES
            (:id, :title, :body_text, \'\', \'\', :status, \'basic\', :starts_at, :ends_at, :sort_order, 0, :created_at, :updated_at)'
    );
    foreach ($bannerRows as [$id, $title, $status, $startsAt, $endsAt, $sortOrder]) {
        $bannerStmt->execute([
            'id' => $id,
            'title' => $title,
            'body_text' => $title,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $targetStmt = $pdo->prepare(
        'INSERT INTO sr_banner_targets
            (id, banner_id, module_key, point_key, slot_key, subject_id, match_type, created_at)
         VALUES
            (:id, :banner_id, :module_key, :point_key, :slot_key, :subject_id, :match_type, :created_at)'
    );
    foreach ([
        [1, 4, 'content', 'content.view', 'before_content', '', 'all'],
        [2, 5, 'content', 'content.view', 'before_content', 'content-1', 'exact'],
        [3, 6, 'content', 'content.view', 'before_content', 'content-2', 'exact'],
        [4, 7, 'content', 'content.view', 'after_content', '', 'all'],
    ] as [$id, $bannerId, $moduleKey, $pointKey, $slotKey, $subjectId, $matchType]) {
        $targetStmt->execute([
            'id' => $id,
            'banner_id' => $bannerId,
            'module_key' => $moduleKey,
            'point_key' => $pointKey,
            'slot_key' => $slotKey,
            'subject_id' => $subjectId,
            'match_type' => $matchType,
            'created_at' => $now,
        ]);
    }

    $html = sr_banner_render_slot($pdo, [
        'module_key' => 'content',
        'point_key' => 'content.view',
        'slot_key' => 'before_content',
        'subject_id' => 'content-1',
    ]);
    foreach (['exact visible first', 'all visible low sort'] as $expected) {
        if (!str_contains($html, $expected)) {
            $errors[] = 'banner runtime fixture must render expected banner: ' . $expected;
        }
    }
    foreach (['draft hidden', 'future hidden', 'expired hidden', 'exact hidden', 'wrong slot hidden'] as $unexpected) {
        if (str_contains($html, $unexpected)) {
            $errors[] = 'banner runtime fixture must not render filtered banner: ' . $unexpected;
        }
    }
    if (!(strpos($html, 'exact visible first') < strpos($html, 'all visible low sort'))) {
        $errors[] = 'banner runtime fixture should render by sort_order ASC then id DESC.';
    }

    $invalidHtml = sr_banner_render_slot($pdo, [
        'module_key' => '../content',
        'point_key' => 'content.view',
        'slot_key' => 'before_content',
    ]);
    if ($invalidHtml !== '') {
        $errors[] = 'banner runtime fixture should fail closed for unsafe module key.';
    }

    return $errors;
}

$errors = array_merge($errors, sr_banner_check_runtime_fixture());

foreach ($expectedTargets as $expectedTarget) {
    if (!isset($targetValues[$expectedTarget])) {
        $errors[] = 'missing popup layer target: ' . $expectedTarget;
    }
}

$popupServices = sr_popup_layer_target_service_options($targets, true);
foreach ([sr_popup_layer_public_target_option_value(), 'content', 'community', 'member'] as $expectedService) {
    if (!isset($popupServices[$expectedService])) {
        $errors[] = 'missing popup layer target service: ' . $expectedService;
    }
}

$popupStaleTarget = sr_popup_layer_target_from_row([
    'module_key' => 'content',
    'point_key' => 'content.missing',
    'slot_key' => 'before_content',
]);
if (!is_array($popupStaleTarget) || sr_popup_layer_target_option_value($popupStaleTarget) !== 'content|content.missing|before_content') {
    $errors[] = 'popup layer stale target fallback must preserve stored target keys.';
}

$popupNormalized = sr_popup_layer_normalize_posted_target_option(
    $targets,
    'content',
    'content|content.view|before_content',
    ''
);
if (($popupNormalized['option'] ?? '') !== 'content|content.view|before_content' || (bool) ($popupNormalized['is_public'] ?? true)) {
    $errors[] = 'popup layer target service/detail normalization failed.';
}

$scriptOnlySlots = sr_popup_layer_normalize_slots([
    [
        'slot_key' => 'after_script',
        'label' => '스크립트 뒤',
        'kind' => 'script',
    ],
]);
if ($scriptOnlySlots !== []) {
    $errors[] = 'popup layer must not accept non-content slots.';
}

if (
    !isset(sr_popup_layer_skin_options()['basic'])
    || sr_popup_layer_skin_view('basic', 'layer') === ''
    || !function_exists('sr_popup_layer_render_basic_stack')
) {
    $errors[] = 'popup layer skin helpers must provide a basic layer skin.';
}

function sr_popup_layer_check_runtime_fixture(): array
{
    $errors = [];
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE sr_popup_layers (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            body_text TEXT,
            body_format TEXT NOT NULL DEFAULT \'plain\',
            status TEXT NOT NULL DEFAULT \'draft\',
            skin_key TEXT NOT NULL DEFAULT \'basic\',
            starts_at TEXT NULL,
            ends_at TEXT NULL,
            dismiss_cookie_days INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_popup_layer_targets (
            id INTEGER PRIMARY KEY,
            popup_layer_id INTEGER NOT NULL,
            module_key TEXT NOT NULL,
            point_key TEXT NOT NULL,
            slot_key TEXT NOT NULL,
            subject_id TEXT NOT NULL DEFAULT \'\',
            match_type TEXT NOT NULL DEFAULT \'all\',
            created_at TEXT NOT NULL
        )'
    );

    $now = sr_now();
    $rows = [
        [1, 'draft hidden', 'draft', null, null, 1],
        [2, 'future hidden', 'enabled', '2999-01-01 00:00:00', null, 1],
        [3, 'expired hidden', 'enabled', null, '2000-01-01 00:00:00', 1],
        [4, 'all target visible', 'enabled', null, null, 7],
        [5, 'exact visible', 'enabled', null, null, 1],
        [6, 'exact hidden', 'enabled', null, null, 1],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO sr_popup_layers
            (id, title, body_text, body_format, status, skin_key, starts_at, ends_at, dismiss_cookie_days, created_at, updated_at)
         VALUES
            (:id, :title, :body_text, \'plain\', :status, \'basic\', :starts_at, :ends_at, :dismiss_cookie_days, :created_at, :updated_at)'
    );
    foreach ($rows as [$id, $title, $status, $startsAt, $endsAt, $dismissDays]) {
        $stmt->execute([
            'id' => $id,
            'title' => $title,
            'body_text' => $title,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'dismiss_cookie_days' => $dismissDays,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $targetStmt = $pdo->prepare(
        'INSERT INTO sr_popup_layer_targets
            (id, popup_layer_id, module_key, point_key, slot_key, subject_id, match_type, created_at)
         VALUES
            (:id, :popup_layer_id, :module_key, :point_key, :slot_key, :subject_id, :match_type, :created_at)'
    );
    foreach ([
        [1, 4, 'content', 'content.view', 'before_content', '', 'all'],
        [2, 5, 'content', 'content.view', 'before_content', 'content-1', 'exact'],
        [3, 6, 'content', 'content.view', 'before_content', 'content-2', 'exact'],
    ] as [$id, $popupLayerId, $moduleKey, $pointKey, $slotKey, $subjectId, $matchType]) {
        $targetStmt->execute([
            'id' => $id,
            'popup_layer_id' => $popupLayerId,
            'module_key' => $moduleKey,
            'point_key' => $pointKey,
            'slot_key' => $slotKey,
            'subject_id' => $subjectId,
            'match_type' => $matchType,
            'created_at' => $now,
        ]);
    }

    $html = sr_popup_layer_render($pdo, [
        'module_key' => 'content',
        'point_key' => 'content.view',
        'slot_key' => 'before_content',
        'subject_id' => 'content-1',
    ]);
    foreach (['all target visible', 'exact visible'] as $expected) {
        if (!str_contains($html, $expected)) {
            $errors[] = 'popup layer runtime fixture must render expected popup: ' . $expected;
        }
    }
    foreach (['draft hidden', 'future hidden', 'expired hidden', 'exact hidden'] as $unexpected) {
        if (str_contains($html, $unexpected)) {
            $errors[] = 'popup layer runtime fixture must not render filtered popup: ' . $unexpected;
        }
    }
    if (!str_contains($html, 'data-cookie-days="7"')) {
        $errors[] = 'popup layer runtime fixture should preserve dismiss cookie day metadata.';
    }
    if (!str_contains($html, 'data-cookie-path=')) {
        $errors[] = 'popup layer runtime fixture should expose dismiss cookie path metadata.';
    }

    $_COOKIE[sr_popup_layer_cookie_name(4)] = '1';
    try {
        $cookieHtml = sr_popup_layer_render($pdo, [
            'module_key' => 'content',
            'point_key' => 'content.view',
            'slot_key' => 'before_content',
            'subject_id' => 'content-1',
        ]);
    } finally {
        unset($_COOKIE[sr_popup_layer_cookie_name(4)]);
    }
    if (str_contains($cookieHtml, 'all target visible') || !str_contains($cookieHtml, 'exact visible')) {
        $errors[] = 'popup layer runtime fixture should hide only dismissed popup by cookie.';
    }

    $invalidHtml = sr_popup_layer_render($pdo, [
        'module_key' => '../content',
        'point_key' => 'content.view',
        'slot_key' => 'before_content',
    ]);
    if ($invalidHtml !== '') {
        $errors[] = 'popup layer runtime fixture should fail closed for unsafe module key.';
    }

    return $errors;
}

$errors = array_merge($errors, sr_popup_layer_check_runtime_fixture());

$popupLayerScript = file_get_contents('modules/popup_layer/assets/saanraan-popup-layer.js');
if (!is_string($popupLayerScript) || !str_contains($popupLayerScript, "path=' + cookiePathForPopup(popup) + '; SameSite=Lax")) {
    $errors[] = 'popup layer script must use rendered cookie path metadata for dismiss cookies.';
}

if ($errors !== []) {
    fwrite(STDERR, "popup layer target checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "popup layer target checks completed.\n";
