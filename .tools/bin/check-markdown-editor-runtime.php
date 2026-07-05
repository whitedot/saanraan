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
require_once $root . '/modules/content/helpers.php';
require_once $root . '/modules/community/helpers/levels.php';
require_once $root . '/modules/community/helpers/posts.php';

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
$rendererContract = require SR_ROOT . '/modules/markdown_editor/markdown-renderer.php';
sr_markdown_editor_check_assert(
    is_array($rendererContract) && (string) ($rendererContract['format_key'] ?? '') === 'markdown',
    'markdown renderer contract should declare format_key=markdown.'
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
    sr_content_body_format_for_editor($enabledPdo, 'markdown', '') === 'markdown',
    'Content item Markdown editor selection should save markdown body_format when renderer is enabled.'
);
sr_markdown_editor_check_assert(
    sr_content_body_format_for_editor($disabledPdo, 'markdown', 'markdown') === 'plain',
    'Content item Markdown editor selection should fall back to plain when renderer is disabled.'
);
sr_markdown_editor_check_assert(
    sr_content_body_format_for_editor($enabledPdo, 'html', '') === 'html',
    'Content item direct HTML editor selection should save html body_format.'
);
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
$css = sr_markdown_editor_css($enabledPdo);
sr_markdown_editor_check_assert(
    str_contains($css, '.markdown-editor-body h1{font-size:')
        && str_contains($css, '.markdown-editor-body th{font-weight:')
        && str_contains($css, '.markdown-editor-body li+li{margin-top:')
        && str_contains($css, 'max-width:980px')
        && str_contains($css, 'margin-inline:auto')
        && str_contains($css, 'text-underline-offset:'),
    'Markdown style profile should emit expanded element controls for headings, lists, and tables.'
);
$resetCss = file_get_contents(SR_ROOT . '/assets/editor-md.css');
sr_markdown_editor_check_assert(
    is_string($resetCss)
        && str_contains($resetCss, '.markdown-editor-body :where(ul)')
        && str_contains($resetCss, 'list-style: disc outside')
        && str_contains($resetCss, '.markdown-editor-body :where(table)')
        && str_contains($resetCss, 'display: table')
        && str_contains($resetCss, '-webkit-appearance: auto')
        && str_contains(sr_markdown_editor_preview_css($enabledPdo), '.markdown-editor-body :where(input[type="checkbox"])'),
    'Markdown editor-md.css should include a scoped body reset and admin preview should load it before the profile CSS.'
);
$settingsView = file_get_contents(SR_ROOT . '/modules/markdown_editor/views/admin-settings.php');
sr_markdown_editor_check_assert(
    is_string($settingsView)
        && str_contains($settingsView, "'title' => '제목'")
        && str_contains($settingsView, "'title' => '코드'")
        && str_contains($settingsView, "'title' => '표'")
        && str_contains($settingsView, 'markdown-editor-style-section'),
    'Markdown settings view should group style controls into element sections.'
);
sr_markdown_editor_check_assert(
    sr_content_body_embed_stylesheets(['id' => 1, 'body_text' => '# Title', 'body_format' => 'markdown', 'embed_enabled' => false], ['embed_enabled' => false], $enabledPdo) !== [],
    'Content markdown body stylesheets should be returned even when URL embeds are disabled.'
);
$contentMarkdownStylesheets = sr_content_body_embed_stylesheets(['id' => 1, 'body_text' => '# Title', 'body_format' => 'markdown', 'embed_enabled' => false], ['embed_enabled' => false], $enabledPdo);
sr_markdown_editor_check_assert(
    ($contentMarkdownStylesheets[0] ?? '') === '/assets/editor-md.css'
        &&
    count(array_filter($contentMarkdownStylesheets, static fn (string $stylesheet): bool => str_contains($stylesheet, '/markdown-editor/style.css?v='))) === 1,
    'Content public markdown bodies should include editor-md.css before the markdown editor stylesheet URL.'
);
sr_markdown_editor_check_assert(
    sr_community_post_body_embed_stylesheets(['id' => 1, 'body_text' => '# Title', 'embed_enabled' => false], ['post_editor' => 'markdown', 'embed_enabled' => false], $enabledPdo) !== [],
    'Community markdown body stylesheets should be returned even when URL embeds are disabled.'
);
$communityMarkdownStylesheets = sr_community_post_body_embed_stylesheets(['id' => 1, 'body_text' => '# Title', 'embed_enabled' => false], ['post_editor' => 'markdown', 'embed_enabled' => false], $enabledPdo);
sr_markdown_editor_check_assert(
    ($communityMarkdownStylesheets[0] ?? '') === '/assets/editor-md.css'
        &&
    count(array_filter($communityMarkdownStylesheets, static fn (string $stylesheet): bool => str_contains($stylesheet, '/markdown-editor/style.css?v='))) === 1,
    'Community public markdown bodies should include editor-md.css before the markdown editor stylesheet URL.'
);

$inline = sr_markdown_render($enabledPdo, "# Title\n\nSecond", 'inline');
sr_markdown_editor_check_assert(is_array($inline) && !str_contains((string) ($inline['html'] ?? ''), '<h1>'), 'Inline mode should not emit block tags.');
sr_markdown_editor_check_assert((array) ($inline['stylesheets'] ?? []) === [], 'Inline mode should not request block stylesheet assets.');

$plain = sr_markdown_render($enabledPdo, "# Title\n\n[Link](https://example.com)", 'plain');
sr_markdown_editor_check_assert(is_array($plain) && (string) ($plain['plain_text'] ?? '') === 'Title Link', 'Plain mode should return markdown plain text.');

require_once $root . '/modules/markdown_editor/helpers.php';
$declarations = sr_markdown_editor_normalize_custom_declarations([
    'paragraph' => 'color: var(--sr-text); background-image: url(https://example.com/x); margin-bottom: 12px;',
    'heading_h2' => 'text-align: center; text-decoration: underline;',
    'code' => 'background-color: var(--sr-surface-muted);',
    'unknown' => 'color: red',
]);
sr_markdown_editor_check_assert(
    ($declarations['paragraph'] ?? '') === 'color: var(--sr-text); margin-bottom: 12px',
    'Custom declarations should keep allowed declarations and drop unsafe url() values.'
);
sr_markdown_editor_check_assert(
    ($declarations['heading_h2'] ?? '') === 'text-align: center; text-decoration: underline',
    'Custom declarations should support expanded selector-specific declarations.'
);
sr_markdown_editor_check_assert(
    ($declarations['code'] ?? '') === 'background-color: var(--sr-surface-muted)',
    'Custom declarations should keep the legacy code selector key.'
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
