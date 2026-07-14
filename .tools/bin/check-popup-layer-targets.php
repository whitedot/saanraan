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
    ['module_key' => 'quiz'],
    ['module_key' => 'survey'],
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

if (is_file('modules/banner/updates/2026.06.002.sql')) {
    $errors[] = 'banner default target migration-only update must stay removed: modules/banner/updates/2026.06.002.sql';
}

$expectedTargets = [
    'content|content.home|screen',
    'content|content.view|before_content',
    'member|member.login|before_form',
    'member|member.register|before_form',
    'community|community.board.list|before_list',
    'community|community.post.view|before_content',
    'community|community.post.form|before_form',
    'quiz|quiz.home|screen',
    'quiz|quiz.view|screen',
    'survey|survey.home|screen',
    'survey|survey.view|screen',
];

$expectedBannerTargets = [
    'core|site.layout|before_layout',
    'core|site.layout|after_layout',
    'content|content.view|before_content',
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
            content_type TEXT NOT NULL DEFAULT \'text\',
            body_text TEXT,
            html_code TEXT,
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
            (id, title, content_type, body_text, html_code, link_url, image_url, status, skin_key, starts_at, ends_at, sort_order, click_count, created_at, updated_at)
         VALUES
            (:id, :title, \'text\', :body_text, \'\', \'\', \'\', :status, \'basic\', :starts_at, :ends_at, :sort_order, 0, :created_at, :updated_at)'
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
foreach ([sr_popup_layer_public_target_option_value(), 'content', 'community', 'member', 'quiz', 'survey'] as $expectedService) {
    if (!isset($popupServices[$expectedService])) {
        $errors[] = 'missing popup layer target service: ' . $expectedService;
    }
}

$popupCommunityPostTarget = sr_popup_layer_find_target($targets, 'community|community.post.view|before_content');
if (!is_array($popupCommunityPostTarget) || sr_popup_layer_target_detail_label($popupCommunityPostTarget) !== (string) $popupCommunityPostTarget['point_label']) {
    $errors[] = 'popup layer target detail label must use screen label without slot label.';
}

$popupSubjectTargetTypes = sr_popup_layer_subject_target_type_map($pdo, $targets);
foreach ([
    'content|content.view|before_content' => 'content',
    'community|community.board.list|before_list' => 'community_board',
    'community|community.post.view|before_content' => 'community_post',
    'quiz|quiz.view|screen' => 'quiz',
    'survey|survey.view|screen' => 'survey',
] as $targetOption => $expectedType) {
    if (($popupSubjectTargetTypes[$targetOption] ?? '') !== $expectedType) {
        $errors[] = 'popup layer subject target type mismatch: ' . $targetOption;
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
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("CREATE TABLE sr_modules (id INTEGER PRIMARY KEY, module_key TEXT NOT NULL UNIQUE, status TEXT NOT NULL)");
    $pdo->exec("INSERT INTO sr_modules (id, module_key, status) VALUES (1, 'popup_layer', 'enabled'), (2, 'coupon', 'enabled')");
    $pdo->exec(
        'CREATE TABLE sr_popup_layers (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            body_text TEXT,
            body_format TEXT NOT NULL DEFAULT \'plain\',
            coupon_claim_campaign_key TEXT NOT NULL DEFAULT \'\',
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
        'CREATE TABLE sr_coupon_definitions (
            id INTEGER PRIMARY KEY,
            coupon_key TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            description TEXT,
            status TEXT NOT NULL DEFAULT \'active\',
            coupon_type TEXT NOT NULL DEFAULT \'access\',
            target_type TEXT NOT NULL DEFAULT \'all\',
            target_id TEXT NOT NULL DEFAULT \'\',
            refundable_policy TEXT NOT NULL DEFAULT \'none\',
            max_uses_per_issue INTEGER NOT NULL DEFAULT 1,
            valid_from TEXT,
            valid_until TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_coupon_claim_campaigns (
            id INTEGER PRIMARY KEY,
            campaign_key TEXT NOT NULL UNIQUE,
            coupon_definition_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            status TEXT NOT NULL DEFAULT \'draft\',
            claim_type TEXT NOT NULL DEFAULT \'free\',
            price_amount INTEGER,
            price_currency_code TEXT NOT NULL DEFAULT \'\',
            starts_at TEXT,
            ends_at TEXT,
            issue_expires_in_days INTEGER,
            issue_expires_at TEXT,
            total_claim_limit INTEGER,
            per_account_limit INTEGER NOT NULL DEFAULT 1,
            visibility TEXT NOT NULL DEFAULT \'hidden\',
            exposure_surfaces_json TEXT NOT NULL,
            login_required INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_coupon_claim_logs (
            id INTEGER PRIMARY KEY,
            campaign_id INTEGER NOT NULL,
            coupon_definition_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            coupon_issue_id INTEGER,
            claim_source TEXT NOT NULL DEFAULT \'coupon_zone\',
            source_context_json TEXT NOT NULL DEFAULT \'{}\',
            payment_reference_module TEXT NOT NULL DEFAULT \'\',
            payment_reference_type TEXT NOT NULL DEFAULT \'\',
            payment_reference_id TEXT NOT NULL DEFAULT \'\',
            dedupe_key TEXT NOT NULL,
            dedupe_hash TEXT NOT NULL,
            occupying_account_id INTEGER,
            status TEXT NOT NULL DEFAULT \'reserved\',
            reserved_until TEXT,
            failure_code TEXT NOT NULL DEFAULT \'\',
            failure_message TEXT NOT NULL DEFAULT \'\',
            created_at TEXT NOT NULL,
            issued_at TEXT,
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
        [7, 'coupon cta visible', 'enabled', null, null, 1],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO sr_popup_layers
            (id, title, body_text, body_format, coupon_claim_campaign_key, status, skin_key, starts_at, ends_at, dismiss_cookie_days, created_at, updated_at)
         VALUES
            (:id, :title, :body_text, \'plain\', :coupon_claim_campaign_key, :status, \'basic\', :starts_at, :ends_at, :dismiss_cookie_days, :created_at, :updated_at)'
    );
    foreach ($rows as [$id, $title, $status, $startsAt, $endsAt, $dismissDays]) {
        $stmt->execute([
            'id' => $id,
            'title' => $title,
            'body_text' => $title,
            'coupon_claim_campaign_key' => $id === 7 ? 'claim_popup' : '',
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
        [4, 7, 'content', 'content.view', 'before_content', '', 'all'],
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
    $pdo->prepare(
        'INSERT INTO sr_coupon_definitions
            (id, coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            (1, \'popup_coupon\', \'Popup coupon\', \'\', \'active\', \'access\', \'all\', \'\', \'none\', 1, NULL, NULL, :created_at, :updated_at)'
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare(
        'INSERT INTO sr_coupon_claim_campaigns
            (id, campaign_key, coupon_definition_id, title, description, status, claim_type, price_amount, price_currency_code, starts_at, ends_at, issue_expires_in_days, issue_expires_at, total_claim_limit, per_account_limit, visibility, exposure_surfaces_json, login_required, created_at, updated_at)
         VALUES
            (1, \'claim_popup\', 1, \'Popup claim\', \'\', \'active\', \'free\', NULL, \'\', NULL, NULL, NULL, NULL, 10, 1, \'public\', \'["popup_layer"]\', 1, :created_at, :updated_at)'
    )->execute(['created_at' => $now, 'updated_at' => $now]);

    $campaignInsert = $pdo->prepare(
        'INSERT INTO sr_coupon_claim_campaigns
            (id, campaign_key, coupon_definition_id, title, description, status, claim_type, price_amount, price_currency_code, starts_at, ends_at, issue_expires_in_days, issue_expires_at, total_claim_limit, per_account_limit, visibility, exposure_surfaces_json, login_required, created_at, updated_at)
         VALUES
            (:id, :campaign_key, 1, :title, \'\', \'active\', \'free\', NULL, \'\', NULL, NULL, NULL, NULL, 10, 1, :visibility, :surfaces, 1, :created_at, :updated_at)'
    );
    for ($rowNumber = 2; $rowNumber <= 306; $rowNumber++) {
        $campaignInsert->execute([
            'id' => $rowNumber,
            'campaign_key' => 'non_popup_' . (string) $rowNumber,
            'title' => 'Non-popup ' . (string) $rowNumber,
            'visibility' => 'public',
            'surfaces' => '["coupon_zone"]',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    $campaignInsert->execute([
        'id' => 307,
        'campaign_key' => 'stored_hidden',
        'title' => 'Stored hidden campaign',
        'visibility' => 'hidden',
        'surfaces' => '["popup_layer"]',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $campaignOptions = sr_popup_layer_coupon_claim_campaign_options($pdo);
    if (count($campaignOptions) !== 1 || (string) ($campaignOptions[0]['campaign_key'] ?? '') !== 'claim_popup') {
        $errors[] = 'popup layer coupon campaign options must filter eligible campaigns before applying the 300-row bound.';
    }
    $campaignOptionsWithCurrent = sr_popup_layer_coupon_claim_campaign_options($pdo, 'stored_hidden');
    $campaignOptionKeys = array_map(static fn (array $row): string => (string) ($row['campaign_key'] ?? ''), $campaignOptionsWithCurrent);
    if (!in_array('claim_popup', $campaignOptionKeys, true) || !in_array('stored_hidden', $campaignOptionKeys, true)) {
        $errors[] = 'popup layer coupon campaign options must preserve the currently stored campaign outside eligible candidates.';
    }

    $html = sr_popup_layer_render($pdo, [
        'module_key' => 'content',
        'point_key' => 'content.view',
        'slot_key' => 'before_content',
        'subject_id' => 'content-1',
    ]);
    foreach (['all target visible', 'exact visible', 'coupon cta visible'] as $expected) {
        if (!str_contains($html, $expected)) {
            $errors[] = 'popup layer runtime fixture must render expected popup: ' . $expected;
        }
    }
    if (!str_contains($html, 'data-sr-popup-layer-coupon-cta') || !str_contains($html, '/login?return_to=%2Fcoupons%3Fcampaign%3Dclaim_popup')) {
        $errors[] = 'popup layer runtime fixture should render coupon campaign CTA without mixing it with dismiss cookie state.';
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
