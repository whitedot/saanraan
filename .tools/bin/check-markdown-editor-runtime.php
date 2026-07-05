#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/version.php';
require_once $root . '/core/helpers/common.php';
require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/settings.php';
require_once $root . '/core/helpers/output.php';

$errors = [];

function sr_markdown_editor_check_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_markdown_editor_check_pdo(string $status): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL UNIQUE,
            version TEXT NOT NULL DEFAULT "fixture",
            status TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_module_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NOT NULL,
            value_type TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $stmt = $pdo->prepare('INSERT INTO sr_modules (module_key, version, status) VALUES ("markdown_editor", "2026.07.001", :status)');
    $stmt->execute(['status' => $status]);

    return $pdo;
}

sr_markdown_editor_check_assert(
    in_array('markdown-renderer.php', sr_module_known_contract_files(), true),
    'markdown-renderer.php should be a known module contract file.'
);

$metadata = sr_module_metadata('markdown_editor');
sr_markdown_editor_check_assert(
    in_array('markdown-renderer.php', (array) ($metadata['contracts']['provides'] ?? []), true),
    'markdown_editor module must declare markdown-renderer.php.'
);

$disabledPdo = sr_markdown_editor_check_pdo('disabled');
sr_markdown_editor_check_assert(!isset(sr_editor_options($disabledPdo)['markdown']), 'Disabled markdown_editor must not expose Markdown editor option.');
sr_markdown_editor_check_assert(!sr_editor_available($disabledPdo, 'markdown'), 'Disabled markdown_editor must not make markdown editor available.');
sr_markdown_editor_check_assert(!sr_markdown_renderer_available($disabledPdo), 'Disabled markdown_editor must not make renderer available.');
sr_markdown_editor_check_assert(
    sr_body_text_html(['body_text' => '# Fallback', 'body_format' => 'markdown'], false, $disabledPdo) === '<h1>Fallback</h1>',
    'Existing markdown bodies should keep core fallback rendering when plugin is disabled.'
);

$enabledPdo = sr_markdown_editor_check_pdo('enabled');
$options = sr_editor_options($enabledPdo);
sr_markdown_editor_check_assert(isset($options['markdown']), 'Enabled markdown_editor should expose Markdown editor option.');
sr_markdown_editor_check_assert(sr_editor_available($enabledPdo, 'markdown'), 'Enabled markdown_editor should make markdown editor available.');
sr_markdown_editor_check_assert(
    str_contains(sr_editor_textarea_attributes($enabledPdo, 'markdown'), 'data-sr-editor-format-value="markdown"'),
    'Markdown editor textarea attributes should set body_format=markdown through contract format_value.'
);
sr_markdown_editor_check_assert(
    str_contains(sr_editor_assets_html($enabledPdo, 'markdown'), '/modules/markdown_editor/assets/editor.js'),
    'Markdown editor assets should be returned through editor contract.'
);

$full = sr_markdown_render($enabledPdo, "# Title\n\n- [x] Done\n\n[j](javascript:alert(1))", 'full');
sr_markdown_editor_check_assert(is_array($full), 'Markdown renderer should return a result array.');
sr_markdown_editor_check_assert(str_contains((string) ($full['html'] ?? ''), 'markdown-editor-body'), 'Full mode should wrap output for scoped styles.');
sr_markdown_editor_check_assert(str_contains((string) ($full['html'] ?? ''), '<input type="checkbox" disabled checked>'), 'Task list syntax should render in full mode.');
sr_markdown_editor_check_assert(!str_contains((string) ($full['html'] ?? ''), 'href="javascript:'), 'Unsafe markdown links should not render as anchors.');
sr_markdown_editor_check_assert((array) ($full['stylesheets'] ?? []) !== [], 'Full mode should return the profile stylesheet.');
sr_markdown_editor_check_assert((string) ($full['profile_hash'] ?? '') !== '', 'Render result should include a profile hash.');

$inline = sr_markdown_render($enabledPdo, "# Title\n\nSecond", 'inline');
sr_markdown_editor_check_assert(is_array($inline) && !str_contains((string) ($inline['html'] ?? ''), '<h1>'), 'Inline mode should not emit block tags.');
sr_markdown_editor_check_assert((array) ($inline['stylesheets'] ?? []) === [], 'Inline mode should not request block stylesheet assets.');

$plain = sr_markdown_render($enabledPdo, "# Title\n\n[Link](https://example.com)", 'plain');
sr_markdown_editor_check_assert(is_array($plain) && (string) ($plain['plain_text'] ?? '') === 'Title Link', 'Plain mode should return markdown plain text.');

require_once $root . '/modules/markdown_editor/helpers.php';
$declarations = sr_markdown_editor_normalize_custom_declarations([
    'paragraph' => 'color: var(--sr-text); background-image: url(https://example.com/x); margin-bottom: 12px;',
    'unknown' => 'color: red',
]);
sr_markdown_editor_check_assert(
    ($declarations['paragraph'] ?? '') === 'color: var(--sr-text); margin-bottom: 12px',
    'Custom declarations should keep allowed declarations and drop unsafe url() values.'
);
sr_markdown_editor_check_assert(!isset($declarations['unknown']), 'Custom declarations should reject unknown selector keys.');

if ($errors !== []) {
    fwrite(STDERR, "markdown editor runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "markdown editor runtime checks completed.\n";
