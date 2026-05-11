#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

const TOY_ROOT = __DIR__ . '/../..';

require_once TOY_ROOT . '/core/version.php';
require_once TOY_ROOT . '/core/helpers/settings.php';
require_once TOY_ROOT . '/modules/popup_layer/helpers.php';

final class ToyPopupLayerCheckStatement extends PDOStatement
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

final class ToyPopupLayerCheckPdo extends PDO
{
    private array $moduleRows;

    public function __construct(array $moduleRows)
    {
        $this->moduleRows = $moduleRows;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (!str_contains($query, 'FROM toy_modules')) {
            return false;
        }

        return new ToyPopupLayerCheckStatement($this->moduleRows);
    }
}

$pdo = new ToyPopupLayerCheckPdo([
    ['module_key' => 'admin'],
    ['module_key' => 'member'],
    ['module_key' => 'community'],
    ['module_key' => 'popup_layer'],
]);

$targets = toy_popup_layer_available_targets($pdo);
$targetValues = [];
foreach ($targets as $target) {
    $targetValues[toy_popup_layer_target_option_value($target)] = true;
}

$errors = [];
$expectedTargets = [
    'member|member.login|before_form',
    'member|member.register|after_form',
    'community|community.home|before_content',
    'community|community.post.view|after_comments',
];

foreach ($expectedTargets as $expectedTarget) {
    if (!isset($targetValues[$expectedTarget])) {
        $errors[] = 'missing popup layer target: ' . $expectedTarget;
    }
}

$scriptOnlySlots = toy_popup_layer_normalize_slots([
    [
        'slot_key' => 'after_script',
        'label' => '스크립트 뒤',
        'kind' => 'script',
    ],
]);
if ($scriptOnlySlots !== []) {
    $errors[] = 'popup layer must not accept non-content slots.';
}

if ($errors !== []) {
    fwrite(STDERR, "popup layer target checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "popup layer target checks completed.\n";
