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
    $stmt = $pdo->prepare('INSERT INTO sr_modules (module_key, version, status) VALUES ("markdown_editor", "2026.07.003", :status)');
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
sr_markdown_editor_check_assert(
    array_key_exists('stylesheet_css', (array) ($metadata['settings'] ?? []))
        && array_key_exists('style_source_mode', (array) ($metadata['settings'] ?? []))
        && is_file(SR_ROOT . '/modules/markdown_editor/DEPENDENCY.md')
        && is_file(SR_ROOT . '/modules/markdown_editor/LICENSE.github-markdown-css'),
    'markdown_editor module should declare editable stylesheet storage and ship source/license metadata.'
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
$alwaysEnabledSyntax = sr_markdown_editor_settings($enabledPdo, [
    'tables_enabled' => false,
    'task_lists_enabled' => false,
    'code_blocks_enabled' => false,
]);
sr_markdown_editor_check_assert(
    $alwaysEnabledSyntax['tables_enabled'] === true
        && $alwaysEnabledSyntax['task_lists_enabled'] === true
        && $alwaysEnabledSyntax['code_blocks_enabled'] === true,
    'Tables, task lists, and code blocks should remain enabled without operator switches.'
);
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
    str_contains($css, 'Based on github-markdown-css 5.9.0')
        && !str_contains($css, 'sr-control: max_width')
        && !str_contains($css, 'max-width: 980px')
        && str_contains($css, '.markdown-editor-body h1 {')
        && str_contains($css, 'sr-control: h1_size')
        && str_contains($css, 'font-size: 32px;')
        && str_contains($css, '.markdown-editor-body li + li {')
        && str_contains($css, '.markdown-editor-body table th {')
        && str_contains($css, 'text-underline-offset: 2px;'),
    'Markdown stylesheet should expose GitHub Markdown-derived rules with controls bound to actual declarations.'
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
$pageActionsPosition = is_string($settingsView) ? strpos($settingsView, 'class="markdown-editor-page-actions"') : false;
$previewControlsPosition = is_string($settingsView) ? strpos($settingsView, 'class="markdown-editor-preview-controls"') : false;
$cssModalPosition = is_string($settingsView) ? strpos($settingsView, 'id="markdown_editor_css_modal"') : false;
sr_markdown_editor_check_assert(
    is_string($settingsView)
        && str_contains($settingsView, "'title' => '제목 H1'")
        && str_contains($settingsView, "'title' => '인라인 코드'")
        && str_contains($settingsView, "'title' => '코드 블록'")
        && str_contains($settingsView, "'title' => '표'")
        && str_contains($settingsView, 'markdown-editor-workbench')
        && str_contains($settingsView, 'data-markdown-editor-surface')
        && str_contains($settingsView, 'data-markdown-source')
        && str_contains($settingsView, 'data-markdown-rendered-preview')
        && str_contains($settingsView, 'data-markdown-context-toolbar')
        && str_contains($settingsView, 'data-markdown-toolbar-groups')
        && str_contains($settingsView, 'data-markdown-toolbar-controls')
        && str_contains($settingsView, 'data-markdown-inspector-target')
        && str_contains($settingsView, 'data-markdown-inspector-panel')
        && str_contains($settingsView, 'data-markdown-control-templates')
        && str_contains($settingsView, 'markdown-editor-sidebar-section')
        && str_contains($settingsView, 'aria-controls="markdown_editor_css_modal"')
        && str_contains($settingsView, 'data-overlay="#markdown_editor_css_modal"')
        && str_contains($settingsView, 'aria-label="CSS 확인"')
        && str_contains($settingsView, '<h3 id="markdown_editor_css_modal_label" class="modal-title">CSS 확인</h3>')
        && str_contains($settingsView, 'aria-hidden="true" inert')
        && str_contains($settingsView, 'modal-dialog-fluid')
        && str_contains($settingsView, 'modal-content-fullscreen')
        && str_contains($settingsView, 'markdown-editor-css-modal-content')
        && str_contains($settingsView, 'markdown-editor-preview-controls')
        && str_contains($settingsView, 'markdown-editor-page-actions')
        && $pageActionsPosition !== false
        && $previewControlsPosition !== false
        && $cssModalPosition !== false
        && $previewControlsPosition < $cssModalPosition
        && $pageActionsPosition < $cssModalPosition
        && !str_contains($settingsView, '<aside class="markdown-editor-inspector"')
        && !str_contains($settingsView, 'data-markdown-sidebar-tab=')
        && !str_contains($settingsView, '<summary>마크다운 기능</summary>')
        && !str_contains($settingsView, 'name="tables_enabled"')
        && !str_contains($settingsView, 'name="task_lists_enabled"')
        && !str_contains($settingsView, 'name="code_blocks_enabled"')
        && str_contains($settingsView, 'data-markdown-style-source-mode')
        && str_contains($settingsView, 'markdown-editor-property-group')
        && str_contains($settingsView, 'markdown-editor-box-group')
        && str_contains($settingsView, 'markdown-editor-text-group')
        && str_contains($settingsView, 'sr_markdown_editor_box_target_definitions')
        && str_contains($settingsView, 'sr_markdown_editor_text_target_definitions')
        && str_contains($settingsView, "'margin' => ['top' => '위 마진'")
        && str_contains($settingsView, "'padding' => ['top' => '위 패딩'")
        && str_contains($settingsView, "'border' => ['top' => '위 테두리'")
        && str_contains($settingsView, "'title' => 'Typography'")
        && str_contains($settingsView, "'title' => 'Fill & Stroke'")
        && str_contains($settingsView, "'heading_letter_spacing'")
        && str_contains($settingsView, "'table_cell_padding_inline'")
        && str_contains($settingsView, 'data-markdown-style-key')
        && str_contains($settingsView, 'data-markdown-stylesheet')
        && str_contains($settingsView, 'data-markdown-css-preview-status')
        && str_contains($settingsView, 'CSS를 편집하면 서버 검증 후 미리보기에 자동 반영됩니다.')
        && !str_contains($settingsView, 'data-markdown-specimen-list')
        && !str_contains($settingsView, 'contenteditable=')
        && str_contains($settingsView, 'data-view-mode="split"')
        && str_contains($settingsView, 'data-markdown-pane-toggle="editor"')
        && str_contains($settingsView, 'data-markdown-pane-toggle="preview"')
        && !str_contains($settingsView, 'data-markdown-view=')
        && str_contains($settingsView, 'data-markdown-scheme="dark"')
        && !str_contains($settingsView, 'data-markdown-preview-scale')
        && !str_contains($settingsView, '>배율<'),
    'Markdown settings view should provide external preview controls, a CSS-only fullscreen modal, and bottom save/reference actions.'
);
$adminScript = file_get_contents(SR_ROOT . '/modules/markdown_editor/assets/admin.js');
$adminStylesheet = file_get_contents(SR_ROOT . '/modules/markdown_editor/assets/admin.css');
sr_markdown_editor_check_assert(
    is_string($adminScript)
        && str_contains($adminScript, 'updateStylesheetControl')
        && str_contains($adminScript, 'syncControlsFromStylesheet')
        && str_contains($adminScript, 'setViewMode')
        && str_contains($adminScript, '[data-markdown-pane-toggle]')
        && str_contains($adminScript, "icon.textContent = expanded")
        && str_contains($adminScript, "? 'vertical_split'")
        && str_contains($adminScript, 'setScheme')
        && str_contains($adminScript, 'resetStyles')
        && str_contains($adminScript, 'selectInspectorTarget')
        && str_contains($adminScript, 'inspectorTargetForElement')
        && str_contains($adminScript, 'resetInspectorTarget')
        && str_contains($adminScript, 'setStyleSourceMode')
        && str_contains($adminScript, 'setCssPreviewStatus')
        && str_contains($adminScript, 'stylesheetPreviewPending = true')
        && str_contains($adminScript, 'updateContextToolbar')
        && str_contains($adminScript, 'showContextToolbarGroup')
        && str_contains($adminScript, 'prepareToolbarControlClone')
        && str_contains($adminScript, 'toolbarIconForGroup')
        && str_contains($adminScript, 'createToolbarIcon')
        && str_contains($adminScript, 'data-markdown-style-key^="content_padding_"')
        && str_contains($adminScript, "Margin: 'margin'")
        && str_contains($adminScript, "Typography: 'text_fields'")
        && str_contains($adminScript, "Spacing: 'format_line_spacing'")
        && str_contains($adminScript, "'Fill & Stroke': 'palette'")
        && str_contains($adminScript, "button.className = 'btn btn-sm btn-icon'")
        && str_contains($adminScript, "createToolbarIcon('restart_alt')")
        && str_contains($adminScript, 'scheduleServerPreview(90)')
        && str_contains($adminScript, 'scheduleServerPreview(220)')
        && str_contains($adminScript, "previewStyle.textContent = String(payload.css || '')")
        && str_contains($adminScript, "renderedPreview.innerHTML = String(payload.html || '')"),
    'Markdown settings script should validate direct CSS edits, apply returned CSS to preview, and synchronize click-selected controls.'
);
sr_markdown_editor_check_assert(
    is_string($adminStylesheet)
        && str_contains($adminStylesheet, '.markdown-editor-live-surface[data-view-mode="preview"]')
        && str_contains($adminStylesheet, '.markdown-editor-context-toolbar')
        && str_contains($adminStylesheet, '.markdown-editor-toolbar-separator')
        && str_contains($adminStylesheet, '.markdown-editor-toolbar-reset')
        && str_contains($adminStylesheet, '.markdown-editor-context-fields')
        && str_contains($adminStylesheet, '.markdown-editor-source')
        && str_contains($adminStylesheet, '.markdown-editor-pane-toggle')
        && str_contains($adminStylesheet, 'white-space: pre-wrap')
        && str_contains($adminStylesheet, '[data-markdown-style-selected]')
        && str_contains($adminStylesheet, '.markdown-editor-css-modal-content')
        && str_contains($adminStylesheet, '.markdown-editor-preview-controls')
        && !str_contains($adminStylesheet, '--markdown-preview-scale')
        && str_contains($adminStylesheet, '#markdown_editor_css_modal.overlay-closed')
        && str_contains($adminStylesheet, '#markdown_editor_css_modal:is(.overlay-open, .open).overlay-closed')
        && str_contains($adminStylesheet, '.markdown-editor-page-actions'),
    'Markdown workbench should preserve source line breaks, highlight selected output, hide the initial modal overlay, and keep bottom actions.'
);
sr_markdown_editor_check_assert(
    is_string($adminStylesheet)
        && str_contains($adminStylesheet, '.markdown-editor-box-group > .markdown-editor-inspector-fields')
        && str_contains($adminStylesheet, 'grid-template-columns: repeat(2, minmax(0, 1fr))')
        && str_contains($adminStylesheet, '.markdown-editor-render-pane[data-color-scheme="dark"]')
        && str_contains($adminStylesheet, '--sr-text: #1f2328')
        && str_contains($adminStylesheet, '--sr-text: #f0f6fc')
        && str_contains($adminStylesheet, '--sr-surface: #0d1117'),
    'Markdown live preview should define independent light and dark theme token sets.'
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

$defaultStylesheet = sr_markdown_editor_default_stylesheet_css();
sr_markdown_editor_check_assert(
    sr_markdown_editor_stylesheet_validation_errors($defaultStylesheet) === [],
    'Bundled GitHub Markdown-derived stylesheet should pass scoped stylesheet validation.'
);
$boxTargets = sr_markdown_editor_box_target_definitions();
sr_markdown_editor_check_assert(
    count($boxTargets) === 14
        && isset($boxTargets['paragraph'], $boxTargets['h1'], $boxTargets['h6'], $boxTargets['link'], $boxTargets['list'], $boxTargets['blockquote'], $boxTargets['inline_code'], $boxTargets['code_block'], $boxTargets['table'], $boxTargets['hr'])
        && str_contains($defaultStylesheet, 'sr-box-controls: common element box model')
        && str_contains($defaultStylesheet, 'sr-control: box_h1_margin_top')
        && str_contains($defaultStylesheet, 'sr-control: box_h1_padding_bottom')
        && str_contains($defaultStylesheet, 'sr-control: box_blockquote_border_left')
        && str_contains($defaultStylesheet, 'sr-control: box_table_radius'),
    'Every selectable Markdown element should receive the same side-specific box model controls.'
);
$boxProfileStylesheet = sr_markdown_editor_apply_profile_to_stylesheet(
    $defaultStylesheet,
    array_merge(sr_markdown_editor_default_style_profile(), [
        'box_h1_margin_left' => 18,
        'box_h1_padding_right' => 9,
        'box_h1_border_top' => 3,
        'box_h1_radius' => 12,
        'box_h1_border_style' => 'dashed',
        'box_h1_border_token' => '--sr-info',
    ])
);
sr_markdown_editor_check_assert(
    str_contains($boxProfileStylesheet, "/* sr-control: box_h1_margin_left */\n    margin-left: 18px;")
        && str_contains($boxProfileStylesheet, "/* sr-control: box_h1_padding_right */\n    padding-right: 9px;")
        && str_contains($boxProfileStylesheet, "/* sr-control: box_h1_border_top */\n    border-top-width: 3px;")
        && str_contains($boxProfileStylesheet, "/* sr-control: box_h1_radius */\n    border-radius: 12px;")
        && str_contains($boxProfileStylesheet, "/* sr-control: box_h1_border_style */\n    border-style: dashed;")
        && str_contains($boxProfileStylesheet, "/* sr-control: box_h1_border_token */\n    border-color: var(--sr-info);"),
    'A selected element box model should update each side, border style, token, and radius independently.'
);
$textTargets = sr_markdown_editor_text_target_definitions();
$textProfileStylesheet = sr_markdown_editor_apply_profile_to_stylesheet(
    $defaultStylesheet,
    array_merge(sr_markdown_editor_default_style_profile(), [
        'text_h2_font_family' => 'Georgia, "Times New Roman", serif',
        'text_h2_font_size' => 38,
        'text_h2_font_weight' => 500,
        'text_h2_line_height' => 1.6,
        'text_h2_letter_spacing' => 1.2,
        'text_h2_word_spacing' => 4,
        'text_h2_align' => 'center',
        'text_h2_font_style' => 'italic',
        'text_h2_decoration' => 'underline',
        'text_h2_transform' => 'uppercase',
        'text_h2_token' => '--sr-info',
    ])
);
sr_markdown_editor_check_assert(
    count($textTargets) === 13
        && str_contains($defaultStylesheet, 'sr-text-controls: common element text style')
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_font_size */\n    font-size: 38px;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_font_weight */\n    font-weight: 500;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_align */\n    text-align: center;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_font_style */\n    font-style: italic;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_decoration */\n    text-decoration-line: underline;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_transform */\n    text-transform: uppercase;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_token */\n    color: var(--sr-info);"),
    'Every text-bearing element should expose the same complete text style controls.'
);
$missingControlMarkers = [];
foreach (sr_markdown_editor_style_binding_map() as $controlKey => $_binding) {
    if (!str_contains($defaultStylesheet, 'sr-control: ' . $controlKey)) {
        $missingControlMarkers[] = $controlKey;
    }
}
sr_markdown_editor_check_assert(
    $missingControlMarkers === [],
    'Every visual style control should bind to an actual declaration marker in the source stylesheet.'
);
sr_markdown_editor_check_assert(
    sr_markdown_editor_stylesheet_validation_errors('.markdown-editor-body p { margin-bottom: 2rem; }') === [],
    'Direct stylesheet editing should allow declarations scoped to the Markdown wrapper.'
);
$directStylesheet = $defaultStylesheet . "\n\n.markdown-editor-body p { letter-spacing: 0.02em; }";
sr_markdown_editor_check_assert(
    str_contains(sr_markdown_editor_css($enabledPdo, ['stylesheet_css' => $directStylesheet]), 'letter-spacing: 0.02em;')
        && sr_markdown_editor_profile_hash($enabledPdo, ['stylesheet_css' => $defaultStylesheet])
            !== sr_markdown_editor_profile_hash($enabledPdo, ['stylesheet_css' => $directStylesheet]),
    'Direct stylesheet edits should be emitted and included in the public stylesheet profile hash.'
);
$defaultModeCss = sr_markdown_editor_css($enabledPdo, [
    'style_source_mode' => 'default',
    'stylesheet_css' => $directStylesheet,
]);
sr_markdown_editor_check_assert(
    !str_contains($defaultModeCss, 'letter-spacing: 0.02em;')
        && str_contains($defaultModeCss, 'Based on github-markdown-css 5.9.0')
        && sr_markdown_editor_profile_hash($enabledPdo, ['style_source_mode' => 'default', 'stylesheet_css' => $defaultStylesheet])
            === sr_markdown_editor_profile_hash($enabledPdo, ['style_source_mode' => 'default', 'stylesheet_css' => $directStylesheet])
        && sr_markdown_editor_profile_hash($enabledPdo, ['style_source_mode' => 'custom', 'stylesheet_css' => $directStylesheet])
            !== sr_markdown_editor_profile_hash($enabledPdo, ['style_source_mode' => 'default', 'stylesheet_css' => $directStylesheet]),
    'Reference default mode should emit the bundled stylesheet while preserving custom source independently.'
);
sr_markdown_editor_check_assert(
    sr_markdown_editor_stylesheet_validation_errors('body { display: none; }') !== []
        && sr_markdown_editor_stylesheet_validation_errors('.markdown-editor-body { background: url(https://example.com/x); }') !== []
        && sr_markdown_editor_stylesheet_validation_errors('@import "https://example.com/x.css"; .markdown-editor-body { color: red; }') !== []
        && sr_markdown_editor_stylesheet_validation_errors('.markdown-editor-body { color: red; } /* </style> */') !== [],
    'Editable stylesheets should reject selector escape, external URLs, at-rules, and style element termination.'
);
$profileStylesheet = sr_markdown_editor_apply_profile_to_stylesheet(
    $defaultStylesheet,
    array_merge(sr_markdown_editor_default_style_profile(), ['font_size' => 19])
);
sr_markdown_editor_check_assert(
    str_contains($profileStylesheet, "/* sr-control: font_size */\n    font-size: 19px;"),
    'Profile property changes should update the corresponding actual declarations in the editable stylesheet.'
);
$legacyMaxWidthCss = ".markdown-editor-body {\n    /* sr-control: max_width */\n    max-width: 980px;\n}\n.markdown-editor-body p { max-width: 42rem; }";
$withoutLegacyMaxWidth = sr_markdown_editor_remove_legacy_max_width_control($legacyMaxWidthCss);
sr_markdown_editor_check_assert(
    !str_contains($withoutLegacyMaxWidth, 'sr-control: max_width')
        && !str_contains($withoutLegacyMaxWidth, 'max-width: 980px')
        && str_contains($withoutLegacyMaxWidth, 'max-width: 42rem'),
    'Legacy content maximum-width control should be removed without deleting direct custom max-width declarations.'
);
$originalPost = $_POST;
$directSourceStylesheet = str_replace(
    "/* sr-control: font_size */\n    font-size: 16px;",
    "/* sr-control: font_size */\n    font-size: 21px;",
    $defaultStylesheet
);
$_POST = [
    'style_profile' => array_merge(sr_markdown_editor_default_style_profile(), ['font_size' => 18]),
    'stylesheet_css' => $directSourceStylesheet,
];
$postedSettings = sr_markdown_editor_settings_from_post();
$_POST = $originalPost;
sr_markdown_editor_check_assert(
    str_contains((string) ($postedSettings['stylesheet_css'] ?? ''), "/* sr-control: font_size */\n    font-size: 21px;")
        && (int) (($postedSettings['style_profile_json']['font_size'] ?? 0)) === 18
        && str_contains(
            sr_markdown_editor_preview_css($enabledPdo, [
                'style_source_mode' => 'custom',
                'stylesheet_css' => (string) ($postedSettings['stylesheet_css'] ?? ''),
            ]),
            "/* sr-control: font_size */\n    font-size: 21px;"
        ),
    'The submitted stylesheet should remain authoritative and appear in the generated preview CSS.'
);

if ($errors !== []) {
    fwrite(STDERR, "markdown editor runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "markdown editor runtime checks completed.\n";
