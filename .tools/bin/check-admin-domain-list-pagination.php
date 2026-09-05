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

require_once $root . '/modules/reaction/helpers.php';
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
     VALUES (:account_id, 'content', 'content', :target_id, 'like', :updated_at)"
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
if (sr_reaction_admin_record_count($reactionPdo, ['account_id' => 1, 'target_module' => 'content']) !== 23) {
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
