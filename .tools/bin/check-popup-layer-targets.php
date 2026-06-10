#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

const SR_ROOT = __DIR__ . '/../..';

require_once SR_ROOT . '/core/version.php';
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
    'community|community.home|before_content',
    'community|community.post.view|after_comments',
    'community|community.post.form|before_form',
    'community|community.post.form|after_form',
];

$expectedBannerTargets = [
    'core|site.home|before_content',
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

if ($errors !== []) {
    fwrite(STDERR, "popup layer target checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "popup layer target checks completed.\n";
