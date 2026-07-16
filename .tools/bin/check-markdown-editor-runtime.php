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
$tableResult = sr_markdown_render(
    $enabledPdo,
    "| 표 제목 | 값 |\n| :--- | ---: |\n| 표 내용 | 10 |\n| 둘째 행 | 20 |",
    'full'
);
$tableHtml = (string) ($tableResult['html'] ?? '');
sr_markdown_editor_check_assert(
    substr_count($tableHtml, '<table>') === 1
        && str_contains($tableHtml, '<thead><tr><th style="text-align: left;">표 제목</th><th style="text-align: right;">값</th></tr></thead>')
        && str_contains($tableHtml, '<tbody><tr><td style="text-align: left;">표 내용</td><td style="text-align: right;">10</td></tr><tr><td style="text-align: left;">둘째 행</td><td style="text-align: right;">20</td></tr></tbody>')
        && !str_contains($tableHtml, '>---</')
        && !str_contains($tableHtml, '>:---</'),
    'Markdown table syntax should render one table with header, alignment, and body rows while consuming the delimiter row.'
);
$pipeTextResult = sr_markdown_render($enabledPdo, "| 표 제목 | 값 |\n| 표 셀 | 값 |", 'full');
sr_markdown_editor_check_assert(
    !str_contains((string) ($pipeTextResult['html'] ?? ''), '<table>')
        && str_contains((string) ($pipeTextResult['html'] ?? ''), '| 표 제목 | 값 |'),
    'Pipe-separated lines without a table delimiter row should remain paragraph text.'
);
sr_markdown_editor_check_assert(
    str_contains(sr_markdown_editor_sample_markdown(), "| 표 제목 | 값 |\n| --- | --- |\n| 표 내용 | 값 |")
        && !str_contains(sr_markdown_editor_sample_markdown(), '| :--- | ---: |'),
    'The default Markdown sample table should use neutral delimiter markers without alignment colons.'
);
$css = sr_markdown_editor_css($enabledPdo);
$sourceStylesheet = file_get_contents(SR_ROOT . '/modules/markdown_editor/assets/github-markdown.css');
$sourceThemeTokens = [];
if (is_string($sourceStylesheet)) {
    preg_match_all('/var\(\s*(--md-[a-z-]+)\s*\)/', $sourceStylesheet, $sourceThemeTokenMatches);
    $sourceThemeTokens = array_values(array_unique((array) ($sourceThemeTokenMatches[1] ?? [])));
}
$requiredMarkdownThemeTokens = [
    '--md-text',
    '--md-muted',
    '--md-muted-strong',
    '--md-surface',
    '--md-surface-soft',
    '--md-surface-muted',
    '--md-border',
    '--md-border-soft',
    '--md-info',
    '--md-success',
    '--md-warning',
    '--md-danger',
];
$missingMarkdownThemeTokenDeclarations = [];
foreach ($requiredMarkdownThemeTokens as $requiredMarkdownThemeToken) {
    if (!is_string($sourceStylesheet) || !str_contains($sourceStylesheet, $requiredMarkdownThemeToken . ':')) {
        $missingMarkdownThemeTokenDeclarations[] = $requiredMarkdownThemeToken;
    }
}
sr_markdown_editor_check_assert(
    str_contains($css, 'Based on github-markdown-css 5.9.0')
        && !str_contains($css, 'sr-control: max_width')
        && !str_contains($css, 'max-width: 980px')
        && str_contains($css, '.markdown-editor-body h1 {')
        && str_contains($css, 'sr-control: h1_size')
        && str_contains($css, "/* sr-control: h1_size */\n    font-size: 32px;")
        && str_contains($css, "/* sr-control: h2_size */\n    font-size: 26px;")
        && str_contains($css, "/* sr-control: h3_size */\n    font-size: 24px;")
        && str_contains($css, "/* sr-control: h4_size */\n    font-size: 20px;")
        && str_contains($css, "/* sr-control: h5_size */\n    font-size: 18px;")
        && str_contains($css, "/* sr-control: h6_size */\n    font-size: 16px;")
        && str_contains($css, "/* sr-control: text_h1_font_size */\n    font-size: 32px;")
        && str_contains($css, "/* sr-control: text_h2_font_size */\n    font-size: 26px;")
        && str_contains($css, "/* sr-control: text_h3_font_size */\n    font-size: 24px;")
        && str_contains($css, "/* sr-control: text_h4_font_size */\n    font-size: 20px;")
        && str_contains($css, "/* sr-control: text_h5_font_size */\n    font-size: 18px;")
        && str_contains($css, "/* sr-control: text_h6_font_size */\n    font-size: 16px;")
        && str_contains($css, '.markdown-editor-body li + li {')
        && str_contains($css, '.markdown-editor-body table th {')
        && str_contains($css, 'text-underline-offset: 2px;')
        && str_contains($css, 'color: var(--md-text);')
        && str_contains($css, '--md-info: var(--color-info);')
        && !str_contains($css, 'var(--sr-')
        && $sourceThemeTokens !== []
        && array_diff($sourceThemeTokens, $requiredMarkdownThemeTokens) === []
        && $missingMarkdownThemeTokenDeclarations === []
        && !str_contains($css, 'font-family:'),
    'Markdown stylesheet tokens should be declared inside its own scope while controls remain bound to actual declarations.'
);
$previewCss = sr_markdown_editor_preview_css($enabledPdo);
sr_markdown_editor_check_assert(
    str_ends_with($previewCss, $css),
    'Admin preview should append the exact stylesheet emitted on public screens.'
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
$pageActionsPosition = is_string($settingsView) ? strpos($settingsView, 'class="markdown-editor-page-actions ') : false;
$cssTriggerPosition = is_string($settingsView) ? strpos($settingsView, 'aria-label="CSS 확인"') : false;
$schemeTogglePosition = is_string($settingsView) ? strpos($settingsView, 'class="btn btn-sm btn-icon markdown-editor-scheme-toggle"') : false;
$renderPanePosition = is_string($settingsView) ? strpos($settingsView, 'class="markdown-editor-render-pane"') : false;
$sourceModePosition = is_string($settingsView) ? strpos($settingsView, 'id="markdown_editor_source_default"') : false;
$saveButtonPosition = is_string($settingsView) ? strpos($settingsView, '>저장</button>') : false;
$cssModalPosition = is_string($settingsView) ? strpos($settingsView, 'id="markdown_editor_css_modal"') : false;
sr_markdown_editor_check_assert(
    is_string($settingsView)
        && str_contains($settingsView, "'title' => '제목 1(H1)'")
        && str_contains($settingsView, "'title' => '문장 안 코드'")
        && str_contains($settingsView, "'title' => '여러 줄 코드'")
        && str_contains($settingsView, "'title' => '표'")
        && str_contains($settingsView, 'markdown-editor-workbench')
        && !str_contains($settingsView, '<h2>Markdown 편집기</h2>')
        && !str_contains($settingsView, '원문을 편집하고 결과 요소를 클릭해 속성을 조정합니다.')
        && !str_contains($settingsView, 'markdown-editor-live-toolbar')
        && str_contains($settingsView, 'data-markdown-editor-surface')
        && str_contains($settingsView, 'data-markdown-source')
        && str_contains($settingsView, 'data-markdown-rendered-preview')
        && str_contains($settingsView, 'class="markdown-editor-render-stage"')
        && str_contains($settingsView, 'class="markdown-editor-preview-status" data-markdown-editor-preview-status aria-live="polite"')
        && strpos($settingsView, 'data-markdown-rendered-preview') < strpos($settingsView, 'data-markdown-editor-preview-status')
        && strpos($settingsView, 'data-markdown-editor-preview-status') < $pageActionsPosition
        && str_contains($settingsView, 'data-markdown-properties-sidebar')
        && !str_contains($settingsView, 'data-markdown-context-toolbar')
        && !str_contains($settingsView, 'data-markdown-toolbar-groups')
        && !str_contains($settingsView, 'data-markdown-toolbar-controls')
        && !str_contains($settingsView, 'data-markdown-selected-label')
        && str_contains($settingsView, 'data-markdown-inspector-target')
        && str_contains($settingsView, 'data-markdown-inspector-panel')
        && str_contains($settingsView, 'data-markdown-control-templates')
        && str_contains($settingsView, 'aria-controls="markdown_editor_css_modal"')
        && str_contains($settingsView, 'data-overlay="#markdown_editor_css_modal"')
        && str_contains($settingsView, 'aria-label="CSS 확인"')
        && str_contains($settingsView, '<h3 id="markdown_editor_css_modal_label" class="modal-title">전체 CSS</h3>')
        && str_contains($settingsView, 'aria-hidden="true" inert')
        && str_contains($settingsView, 'modal-dialog-fluid')
        && str_contains($settingsView, 'modal-content-fullscreen')
        && substr_count($settingsView, 'data-markdown-stylesheet') === 1
        && str_contains($settingsView, 'data-markdown-stylesheet aria-label="전체 CSS"')
        && !str_contains($settingsView, 'data-markdown-reset-all')
        && !str_contains($settingsView, 'data-markdown-default-stylesheet')
        && !str_contains($settingsView, 'data-markdown-css-preview-status')
        && !str_contains($settingsView, '적용할 스타일시트를 확인하고 직접 편집합니다.')
        && !str_contains($settingsView, '<summary>CSS 원문</summary>')
        && str_contains($settingsView, 'markdown-editor-scheme-toggle')
        && str_contains($settingsView, 'markdown-editor-page-actions form-sticky-actions form-actions form-actions-primary')
        && str_contains($settingsView, 'class="form-choice-toggle-input sr-only"')
        && substr_count($settingsView, 'name="style_source_mode"') === 2
        && str_contains($settingsView, 'class="btn btn-choice-light btn-group-start">기본</label>')
        && str_contains($settingsView, 'class="btn btn-choice-light btn-group-end">변경 스타일</label>')
        && !str_contains($settingsView, 'class="btn btn-sm btn-choice-light btn-group-start">기본</label>')
        && str_contains($settingsView, '>기본</label>')
        && str_contains($settingsView, '>변경 스타일</label>')
        && !str_contains($settingsView, '<span>참고:</span>')
        && !str_contains($settingsView, 'class="markdown-editor-github-icon"')
        && !str_contains($settingsView, '<span>github-markdown-css</span>')
        && !str_contains($settingsView, 'href="https://github.com/sindresorhus/github-markdown-css"')
        && !str_contains($settingsView, '>설정 저장</button>')
        && !str_contains($settingsView, '참고 프로젝트:')
        && !str_contains($settingsView, '>참조 원본</strong>')
        && !str_contains($settingsView, '>변경값</strong>')
        && $pageActionsPosition !== false
        && $cssTriggerPosition !== false
        && $schemeTogglePosition !== false
        && $renderPanePosition !== false
        && $sourceModePosition !== false
        && $saveButtonPosition !== false
        && $cssModalPosition !== false
        && $renderPanePosition < $cssTriggerPosition
        && $cssTriggerPosition < $schemeTogglePosition
        && $pageActionsPosition < $sourceModePosition
        && $sourceModePosition < $saveButtonPosition
        && $schemeTogglePosition < $cssModalPosition
        && $pageActionsPosition < $cssModalPosition
        && str_contains($settingsView, '<aside class="markdown-editor-inspector"')
        && str_contains($settingsView, 'aria-label="현재 요소 스타일 초기화"')
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
        && str_contains($settingsView, "'margin' => ['top' => '위 바깥 여백'")
        && str_contains($settingsView, "'padding' => ['top' => '위 안쪽 여백'")
        && str_contains($settingsView, "'border' => ['top' => '위 테두리'")
        && str_contains($settingsView, "'title' => '글자'")
        && str_contains($settingsView, "'title' => '색과 테두리'")
        && str_contains($settingsView, '왼쪽의 Markdown 원문은 스타일을 확인하기 위한 예시입니다.')
        && str_contains($settingsView, '저장 버튼을 눌러도 이 예시 문장은 저장되지 않습니다.')
        && str_contains($settingsView, 'sr_admin_help_modal_html')
        && str_contains($settingsView, "'heading_letter_spacing'")
        && str_contains($settingsView, "'table_cell_padding_inline'")
        && str_contains($settingsView, 'data-markdown-style-key')
        && str_contains($settingsView, 'data-markdown-stylesheet')
        && !str_contains($settingsView, 'data-markdown-specimen-list')
        && !str_contains($settingsView, 'contenteditable=')
        && str_contains($settingsView, 'data-view-mode="split"')
        && str_contains($settingsView, 'data-markdown-pane-toggle="editor"')
        && str_contains($settingsView, 'data-markdown-pane-toggle="preview"')
        && !str_contains($settingsView, 'data-markdown-view=')
        && str_contains($settingsView, 'data-markdown-scheme-toggle aria-label="다크 모드로 전환"')
        && str_contains($settingsView, "sr_material_icon_html('dark_mode')")
        && !str_contains($settingsView, 'id="markdown_editor_dark_mode"')
        && !str_contains($settingsView, 'data-markdown-scheme=')
        && !str_contains($settingsView, 'data-markdown-preview-scale')
        && !str_contains($settingsView, '>배율<'),
    'Markdown settings view should provide a side property inspector, render-pane dark mode button, CSS-only fullscreen modal, and sticky actions.'
);
$adminScript = file_get_contents(SR_ROOT . '/modules/markdown_editor/assets/admin.js');
sr_markdown_editor_check_assert(
    is_string($adminScript)
        && str_contains($adminScript, "source.dataset.markdownStyleKey !== 'text_token'")
        && str_contains($adminScript, "'text_paragraph_token'")
        && str_contains($adminScript, "'text_h5_token'")
        && str_contains($adminScript, "'text_code_block_token'")
        && str_contains($adminScript, 'control.value === globalTextToken')
        && str_contains($adminScript, 'updateStylesheetControl(control);'),
    'Changing the global Markdown text token should update elements that still inherit the previous base text token.'
);
$adminStylesheet = file_get_contents(SR_ROOT . '/modules/markdown_editor/assets/admin.css');
$adminTokenStylesheet = file_get_contents(SR_ROOT . '/modules/admin/assets/tokens.css');
$publicResetStylesheet = file_get_contents(SR_ROOT . '/assets/reset.css');
$cssTokenValuesForSelector = static function (string $css, string $selector, array $tokens): array {
    preg_match_all('/' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/s', $css, $blockMatches);
    $values = [];
    foreach ((array) ($blockMatches[1] ?? []) as $block) {
        foreach ($tokens as $token) {
            if (preg_match('/(?:^|;)\s*' . preg_quote($token, '/') . '\s*:\s*([^;}]+)/', (string) $block, $valueMatch) === 1) {
                $values[$token] = trim((string) $valueMatch[1]);
            }
        }
    }
    return $values;
};
$publicPreviewTokenKeys = [
    '--color-body-color', '--color-card', '--color-default-50', '--color-default-100',
    '--color-default-200', '--color-default-300', '--color-default-600', '--color-default-700',
    '--color-info', '--color-success', '--color-warning', '--color-danger', '--text-muted',
    '--sr-text', '--sr-muted', '--sr-muted-strong', '--sr-surface', '--sr-surface-soft',
    '--sr-surface-muted', '--sr-border', '--sr-border-soft', '--sr-info', '--sr-success',
    '--sr-warning', '--sr-danger',
];
$publicFoundationColorTokenKeys = [
    '--color-body-color', '--color-card', '--color-default-50', '--color-default-100',
    '--color-default-200', '--color-default-300', '--color-default-600', '--color-default-700',
    '--color-info', '--color-success', '--color-warning', '--color-danger', '--text-muted',
];
$publicSemanticTokenKeys = [
    '--sr-text', '--sr-muted', '--sr-muted-strong', '--sr-surface', '--sr-surface-soft',
    '--sr-surface-muted', '--sr-border', '--sr-border-soft', '--sr-info', '--sr-success',
    '--sr-warning', '--sr-danger',
];
$publicPreviewDarkTokenKeys = [
    '--color-body-color', '--color-card', '--color-default-50', '--color-default-100',
    '--color-default-200', '--color-default-300', '--color-default-600', '--color-default-700',
];
$publicLightPreviewTokens = is_string($publicResetStylesheet)
    ? $cssTokenValuesForSelector($publicResetStylesheet, ':root,:host', $publicPreviewTokenKeys)
    : [];
$adminLightPreviewTokens = is_string($adminStylesheet)
    ? $cssTokenValuesForSelector($adminStylesheet, '.markdown-editor-render-pane', $publicPreviewTokenKeys)
    : [];
$adminFoundationTokens = is_string($adminTokenStylesheet)
    ? $cssTokenValuesForSelector($adminTokenStylesheet, ':root,:host', $publicFoundationColorTokenKeys)
    : [];
$publicFoundationTokens = is_string($publicResetStylesheet)
    ? $cssTokenValuesForSelector($publicResetStylesheet, ':root,:host', $publicFoundationColorTokenKeys)
    : [];
$markdownSettingsTokens = is_string($adminStylesheet)
    ? $cssTokenValuesForSelector($adminStylesheet, '.markdown-editor-settings-form', $publicSemanticTokenKeys)
    : [];
$publicSemanticTokens = is_string($publicResetStylesheet)
    ? $cssTokenValuesForSelector($publicResetStylesheet, ':root,:host', $publicSemanticTokenKeys)
    : [];
$publicDarkPreviewTokens = is_string($publicResetStylesheet)
    ? $cssTokenValuesForSelector($publicResetStylesheet, '[data-theme=dark],[data-color-scheme=dark]', $publicPreviewDarkTokenKeys)
    : [];
$adminDarkPreviewTokens = is_string($adminStylesheet)
    ? $cssTokenValuesForSelector($adminStylesheet, '.markdown-editor-render-pane[data-color-scheme="dark"]', $publicPreviewDarkTokenKeys)
    : [];
$adminDarkFoundationTokens = is_string($adminTokenStylesheet)
    ? $cssTokenValuesForSelector($adminTokenStylesheet, '[data-theme=dark]', $publicPreviewDarkTokenKeys)
    : [];
sr_markdown_editor_check_assert(
    is_string($adminScript)
        && str_contains($adminScript, 'updateStylesheetControl')
        && str_contains($adminScript, 'syncControlsFromStylesheet')
        && str_contains($adminScript, 'setViewMode')
        && str_contains($adminScript, '[data-markdown-pane-toggle]')
        && str_contains($adminScript, "icon.textContent = expanded")
        && str_contains($adminScript, "? 'vertical_split'")
        && str_contains($adminScript, 'setScheme')
        && str_contains($adminScript, 'function initialRenderScheme()')
        && str_contains($adminScript, "document.documentElement.getAttribute('data-theme') === 'dark'")
        && str_contains($adminScript, 'setScheme(initialRenderScheme())')
        && !str_contains($adminScript, "setScheme('light')")
        && str_contains($adminScript, "setScheme(currentScheme === 'dark' ? 'light' : 'dark')")
        && str_contains($adminScript, "icon.textContent = darkMode ? 'light_mode' : 'dark_mode'")
        && !str_contains($adminScript, 'resetStyles')
        && str_contains($adminScript, 'selectInspectorTarget')
        && str_contains($adminScript, 'inspectorTargetForElement')
        && str_contains($adminScript, 'resetInspectorTarget')
        && str_contains($adminScript, 'setStyleSourceMode')
        && !str_contains($adminScript, 'setCssPreviewStatus')
        && !str_contains($adminScript, 'stylesheetPreviewPending')
        && str_contains($adminScript, "form.querySelector('[data-markdown-properties-sidebar]')")
        && str_contains($adminScript, 'propertiesSidebar.appendChild(inspectorControls)')
        && !str_contains($adminScript, 'updateContextToolbar')
        && !str_contains($adminScript, 'showContextToolbarGroup')
        && !str_contains($adminScript, 'prepareToolbarControlClone')
        && str_contains($adminScript, 'scheduleServerPreview(90)')
        && str_contains($adminScript, 'scheduleServerPreview(220)')
        && str_contains($adminScript, "previewStyle.textContent = String(payload.css || '')")
        && str_contains($adminScript, "renderedPreview.innerHTML = String(payload.html || '')"),
    'Markdown settings script should validate direct CSS edits, apply returned CSS to preview, and synchronize click-selected controls.'
);
sr_markdown_editor_check_assert(
    is_string($adminStylesheet)
        && str_contains($adminStylesheet, '.markdown-editor-live-surface[data-view-mode="preview"]')
        && str_contains($adminStylesheet, 'grid-template-columns: minmax(0, 1fr) 304px')
        && str_contains($adminStylesheet, 'grid-template-rows: 68vh')
        && !str_contains($adminStylesheet, '.markdown-editor-live-toolbar')
        && str_contains($adminStylesheet, '.markdown-editor-inspector')
        && str_contains($adminStylesheet, 'height: 100%')
        && !str_contains($adminStylesheet, 'max-height: calc(100vh - var(--admin-shell-bar-height')
        && str_contains($adminStylesheet, '.markdown-editor-property-group summary::after')
        && str_contains($adminStylesheet, '.markdown-editor-property-group[open] summary::after')
        && !str_contains($adminStylesheet, '.markdown-editor-context-toolbar')
        && !str_contains($adminStylesheet, '.markdown-editor-context-fields')
        && str_contains($adminStylesheet, '.markdown-editor-source')
        && str_contains($adminStylesheet, '.markdown-editor-render-stage')
        && str_contains($adminStylesheet, '.markdown-editor-preview-status:empty')
        && str_contains($adminStylesheet, 'background: color-mix(in oklab, var(--sr-surface) 88%, transparent)')
        && str_contains($adminStylesheet, 'backdrop-filter: blur(2px)')
        && str_contains($adminStylesheet, '.markdown-editor-pane-toggle')
        && str_contains($adminStylesheet, 'white-space: pre-wrap')
        && str_contains($adminStylesheet, '[data-markdown-style-selected]')
        && str_contains($adminStylesheet, '.markdown-editor-css-modal-body > .markdown-editor-stylesheet')
        && !str_contains($adminStylesheet, '.markdown-editor-css-modal-content')
        && !str_contains($adminStylesheet, '.markdown-editor-stylesheet-section')
        && str_contains($adminStylesheet, '.markdown-editor-scheme-toggle')
        && str_contains($adminStylesheet, '.markdown-editor-scheme-toggle[aria-pressed="true"]')
        && !str_contains($adminStylesheet, 'min-width: 112px')
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
        && count($publicLightPreviewTokens) === count($publicPreviewTokenKeys)
        && $adminFoundationTokens === $publicFoundationTokens
        && $markdownSettingsTokens === $publicSemanticTokens
        && $adminLightPreviewTokens === $publicLightPreviewTokens
        && count($publicDarkPreviewTokens) === count($publicPreviewDarkTokenKeys)
        && $adminDarkFoundationTokens === $publicDarkPreviewTokens
        && $adminDarkPreviewTokens === $publicDarkPreviewTokens
        && is_string($adminTokenStylesheet)
        && !str_contains($adminTokenStylesheet, '--sr-text:')
        && !str_contains($adminStylesheet, '--sr-text: #1f2328')
        && !str_contains($adminStylesheet, '--sr-text: #f0f6fc'),
    'Markdown semantic tokens should stay scoped to its settings form while the live preview matches public light and dark values.'
);
sr_markdown_editor_check_assert(
    sr_content_body_embed_stylesheets(['id' => 1, 'body_text' => '# Title', 'body_format' => 'markdown'], ['external_embed_enabled' => false, 'internal_embed_enabled' => false], $enabledPdo) !== [],
    'Content markdown body stylesheets should be returned even when URL embeds are disabled.'
);
$contentMarkdownStylesheets = sr_content_body_embed_stylesheets(['id' => 1, 'body_text' => '# Title', 'body_format' => 'markdown'], ['external_embed_enabled' => false, 'internal_embed_enabled' => false], $enabledPdo);
sr_markdown_editor_check_assert(
    ($contentMarkdownStylesheets[0] ?? '') === '/assets/editor-md.css'
        &&
    count(array_filter($contentMarkdownStylesheets, static fn (string $stylesheet): bool => str_contains($stylesheet, '/markdown-editor/style.css?v='))) === 1,
    'Content public markdown bodies should include editor-md.css before the markdown editor stylesheet URL.'
);
sr_markdown_editor_check_assert(
    sr_community_post_body_embed_stylesheets(['id' => 1, 'body_text' => '# Title'], ['post_editor' => 'markdown', 'external_embed_enabled' => false, 'internal_embed_enabled' => false], $enabledPdo) !== [],
    'Community markdown body stylesheets should be returned even when URL embeds are disabled.'
);
$communityMarkdownStylesheets = sr_community_post_body_embed_stylesheets(['id' => 1, 'body_text' => '# Title'], ['post_editor' => 'markdown', 'external_embed_enabled' => false, 'internal_embed_enabled' => false], $enabledPdo);
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
    ($declarations['paragraph'] ?? '') === 'color: var(--md-text); margin-bottom: 12px',
    'Custom declarations should keep allowed declarations and drop unsafe url() values.'
);
sr_markdown_editor_check_assert(
    ($declarations['heading_h2'] ?? '') === 'text-align: center; text-decoration: underline',
    'Custom declarations should support expanded selector-specific declarations.'
);
sr_markdown_editor_check_assert(
    ($declarations['code'] ?? '') === 'background-color: var(--md-surface-muted)',
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
        && str_contains($boxProfileStylesheet, "/* sr-control: box_h1_border_token */\n    border-color: var(--md-info);"),
    'A selected element box model should update each side, border style, token, and radius independently.'
);
$textTargets = sr_markdown_editor_text_target_definitions();
$textProfileStylesheet = sr_markdown_editor_apply_profile_to_stylesheet(
    $defaultStylesheet,
    array_merge(sr_markdown_editor_default_style_profile(), [
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
        && !array_key_exists('font_family', sr_markdown_editor_default_style_profile())
        && !array_key_exists('heading_font_family', sr_markdown_editor_default_style_profile())
        && !array_key_exists('code_font_family', sr_markdown_editor_default_style_profile())
        && !array_key_exists('text_h2_font_family', sr_markdown_editor_default_style_profile())
        && str_contains($defaultStylesheet, 'sr-text-controls: common element text style')
        && !str_contains($defaultStylesheet, 'font-family:')
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_font_size */\n    font-size: 38px;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_font_weight */\n    font-weight: 500;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_align */\n    text-align: center;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_font_style */\n    font-style: italic;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_decoration */\n    text-decoration-line: underline;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_transform */\n    text-transform: uppercase;")
        && str_contains($textProfileStylesheet, "/* sr-control: text_h2_token */\n    color: var(--md-info);"),
    'Every text-bearing element should expose text style controls while inheriting the site font family.'
);
sr_markdown_editor_check_assert(
    str_contains($defaultStylesheet, '.markdown-editor-body blockquote, .markdown-editor-body blockquote > p {')
        && str_contains(
            sr_markdown_editor_apply_profile_to_stylesheet(
                $defaultStylesheet,
                array_merge(sr_markdown_editor_default_style_profile(), ['text_blockquote_token' => '--md-success'])
            ),
            "/* sr-control: text_blockquote_token */\n    color: var(--md-success);"
        )
        && str_contains(
            sr_markdown_editor_ensure_box_control_stylesheet(
                str_replace(
                    '.markdown-editor-body blockquote, .markdown-editor-body blockquote > p {',
                    '.markdown-editor-body blockquote {',
                    $defaultStylesheet
                )
            ),
            '.markdown-editor-body blockquote, .markdown-editor-body blockquote > p {'
        ),
    'Blockquote typography and color controls should reach the rendered paragraph and migrate existing custom stylesheets.'
);
$legacyHeadingStylesheet = $defaultStylesheet;
$legacyHeadingSizes = ['h1' => 32, 'h2' => 24, 'h3' => 20, 'h4' => 16, 'h5' => 14, 'h6' => 13];
$currentHeadingSizes = ['h1' => 32, 'h2' => 26, 'h3' => 24, 'h4' => 20, 'h5' => 18, 'h6' => 16];
foreach ($legacyHeadingSizes as $heading => $legacySize) {
    foreach ([$heading . '_size', 'text_' . $heading . '_font_size'] as $controlKey) {
        $legacyHeadingStylesheet = str_replace(
            '/* sr-control: ' . $controlKey . " */\n    font-size: " . $currentHeadingSizes[$heading] . 'px;',
            '/* sr-control: ' . $controlKey . " */\n    font-size: " . $legacySize . 'px;',
            $legacyHeadingStylesheet
        );
    }
}
$migratedHeadingStylesheet = sr_markdown_editor_migrate_default_heading_scale($legacyHeadingStylesheet);
$customLegacyHeadingStylesheet = str_replace(
    "/* sr-control: text_h3_font_size */\n    font-size: 20px;",
    "/* sr-control: text_h3_font_size */\n    font-size: 21px;",
    $legacyHeadingStylesheet
);
sr_markdown_editor_check_assert(
    str_contains($migratedHeadingStylesheet, "/* sr-control: h2_size */\n    font-size: 26px;")
        && str_contains($migratedHeadingStylesheet, "/* sr-control: text_h6_font_size */\n    font-size: 16px;")
        && sr_markdown_editor_migrate_default_heading_scale($customLegacyHeadingStylesheet) === $customLegacyHeadingStylesheet,
    'The complete legacy default heading scale should migrate to the UI kit reference scale without leaving overriding text controls behind.'
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
$fontSpecificCss = ".markdown-editor-body {\n    /* sr-control: font_family */\n    font-family: Georgia, serif;\n    color: var(--sr-text);\n}\n.markdown-editor-body h1 { font-family: Arial, sans-serif; color: var(--sr-info); }";
$siteFontCss = sr_markdown_editor_css($enabledPdo, [
    'style_source_mode' => 'custom',
    'stylesheet_css' => $fontSpecificCss,
]);
sr_markdown_editor_check_assert(
    !str_contains($siteFontCss, 'font-family:')
        && str_contains($siteFontCss, 'color: var(--md-text);')
        && str_contains($siteFontCss, 'color: var(--md-info);')
        && !str_contains($siteFontCss, 'var(--sr-'),
    'Saved Markdown styles should discard legacy font-family declarations and inherit the site font.'
);
$publicBodyThemeStylesheets = [
    SR_ROOT . '/modules/content/theme/basic/assets/module.css' => '.content-body > :is(h1, h2, h3, h4, h5, h6)',
    SR_ROOT . '/modules/content/theme/sample/assets/module.css' => '.content-body > :is(h1, h2, h3, h4, h5, h6)',
    SR_ROOT . '/modules/community/theme/basic/assets/module.css' => '.community-post-body > :is(h1, h2, h3, h4, h5, h6)',
    SR_ROOT . '/modules/community/theme/sample/assets/module.css' => '.community-post-body > :is(h1, h2, h3, h4, h5, h6)',
];
$publicBodyThemeSelectorsValid = true;
foreach ($publicBodyThemeStylesheets as $path => $directHeadingSelector) {
    $themeCss = file_get_contents($path);
    $publicBodyThemeSelectorsValid = $publicBodyThemeSelectorsValid
        && is_string($themeCss)
        && str_contains($themeCss, $directHeadingSelector);
}
sr_markdown_editor_check_assert(
    $publicBodyThemeSelectorsValid,
    'Content and community themes should not override nested Markdown heading color declarations.'
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
