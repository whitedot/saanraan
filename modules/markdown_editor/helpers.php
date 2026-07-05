<?php

declare(strict_types=1);

function sr_markdown_editor_default_settings(): array
{
    return [
        'tables_enabled' => true,
        'task_lists_enabled' => true,
        'code_blocks_enabled' => true,
        'raw_html_enabled' => false,
        'style_profile_json' => sr_markdown_editor_default_style_profile(),
        'custom_declarations_json' => [],
    ];
}

function sr_markdown_editor_default_style_profile(): array
{
    return [
        'max_width' => 980,
        'font_size' => 16,
        'line_height' => 1.5,
        'block_gap' => 0,
        'paragraph_margin' => 16,
        'heading_line_height' => 1.25,
        'heading_weight' => 700,
        'heading_margin' => 24,
        'h1_size' => 32,
        'h2_size' => 24,
        'h3_size' => 20,
        'h4_size' => 16,
        'h5_size' => 14,
        'h6_size' => 13,
        'strong_weight' => 600,
        'link_weight' => 400,
        'link_underline_offset' => 2,
        'link_decoration_thickness' => 1,
        'list_indent' => 32,
        'list_item_gap' => 6,
        'quote_border_width' => 4,
        'quote_padding_block' => 0,
        'quote_padding_inline' => 16,
        'code_font_size' => 14,
        'code_line_height' => 1.45,
        'inline_code_padding' => 4,
        'code_block_padding' => 16,
        'code_block_border_width' => 1,
        'table_border_width' => 1,
        'table_cell_padding' => 6,
        'table_header_weight' => 600,
        'hr_margin' => 24,
        'hr_border_width' => 1,
        'border_radius' => 6,
        'text_token' => '--sr-text',
        'muted_token' => '--sr-muted',
        'border_token' => '--sr-border',
        'surface_token' => '--sr-surface-muted',
        'accent_token' => '--sr-info',
        'heading_token' => '--sr-text',
        'quote_token' => '--sr-muted',
        'code_token' => '--sr-text',
        'code_surface_token' => '--sr-surface-muted',
        'table_header_surface_token' => '--sr-surface-muted',
    ];
}

function sr_markdown_editor_json_encode(mixed $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : '{}';
}

function sr_markdown_editor_token_options(): array
{
    return [
        '--sr-text' => '본문',
        '--sr-muted' => '보조',
        '--sr-border' => '경계선',
        '--sr-surface-muted' => '보조 표면',
        '--sr-info' => '정보',
        '--sr-success' => '성공',
        '--sr-warning' => '주의',
        '--sr-danger' => '위험',
    ];
}

function sr_markdown_editor_settings(PDO $pdo, ?array $override = null): array
{
    $settings = array_merge(sr_markdown_editor_default_settings(), $override ?? sr_module_settings($pdo, 'markdown_editor'));
    $settings['tables_enabled'] = !empty($settings['tables_enabled']);
    $settings['task_lists_enabled'] = !empty($settings['task_lists_enabled']);
    $settings['code_blocks_enabled'] = !empty($settings['code_blocks_enabled']);
    $settings['raw_html_enabled'] = !empty($settings['raw_html_enabled']);
    $settings['style_profile_json'] = sr_markdown_editor_normalize_style_profile($settings['style_profile_json'] ?? []);
    $settings['custom_declarations_json'] = sr_markdown_editor_normalize_custom_declarations($settings['custom_declarations_json'] ?? []);

    return $settings;
}

function sr_markdown_editor_normalize_style_profile(mixed $profile): array
{
    $profile = is_array($profile) ? $profile : [];
    $defaults = sr_markdown_editor_default_style_profile();
    $tokens = sr_markdown_editor_token_options();
    $normalized = [];
    foreach ($defaults as $key => $default) {
        if (is_int($default)) {
            $value = (int) ($profile[$key] ?? $default);
            $limits = [
                'font_size' => [12, 24],
                'max_width' => [0, 1200],
                'block_gap' => [0, 48],
                'paragraph_margin' => [0, 40],
                'heading_weight' => [400, 900],
                'heading_margin' => [0, 48],
                'h1_size' => [18, 56],
                'h2_size' => [17, 48],
                'h3_size' => [16, 40],
                'h4_size' => [15, 32],
                'h5_size' => [14, 28],
                'h6_size' => [13, 24],
                'strong_weight' => [400, 900],
                'link_weight' => [400, 900],
                'link_underline_offset' => [0, 8],
                'link_decoration_thickness' => [1, 4],
                'list_indent' => [12, 48],
                'list_item_gap' => [0, 20],
                'quote_border_width' => [0, 12],
                'quote_padding_block' => [0, 32],
                'quote_padding_inline' => [0, 40],
                'code_font_size' => [11, 20],
                'inline_code_padding' => [0, 12],
                'code_block_padding' => [0, 32],
                'code_block_border_width' => [0, 4],
                'table_border_width' => [0, 6],
                'table_cell_padding' => [4, 24],
                'table_header_weight' => [400, 900],
                'hr_margin' => [0, 64],
                'hr_border_width' => [0, 6],
                'border_radius' => [0, 16],
            ];
            $limit = $limits[$key] ?? [0, 100];
            $normalized[$key] = min($limit[1], max($limit[0], $value));
            continue;
        }

        if (is_float($default)) {
            $value = (float) ($profile[$key] ?? $default);
            $limits = [
                'heading_line_height' => [1.0, 1.8],
                'code_line_height' => [1.0, 2.0],
            ];
            $limit = $limits[$key] ?? [1.2, 2.2];
            $normalized[$key] = min($limit[1], max($limit[0], $value));
            continue;
        }

        $token = (string) ($profile[$key] ?? $default);
        $normalized[$key] = isset($tokens[$token]) ? $token : (string) $default;
    }

    return $normalized;
}

function sr_markdown_editor_allowed_custom_properties(): array
{
    return [
        'margin-top' => true,
        'margin-bottom' => true,
        'padding' => true,
        'padding-inline-start' => true,
        'border-width' => true,
        'border-radius' => true,
        'font-size' => true,
        'font-weight' => true,
        'line-height' => true,
        'color' => true,
        'background-color' => true,
        'border-color' => true,
        'text-align' => true,
        'font-style' => true,
        'text-decoration' => true,
        'opacity' => true,
    ];
}

function sr_markdown_editor_normalize_custom_declarations(mixed $declarations): array
{
    $declarations = is_array($declarations) ? $declarations : [];
    $allowedProperties = sr_markdown_editor_allowed_custom_properties();
    $allowedSelectors = sr_markdown_editor_style_selector_options();
    $normalized = [];

    foreach ($declarations as $selectorKey => $rawDeclaration) {
        $selectorKey = (string) $selectorKey;
        if (!isset($allowedSelectors[$selectorKey]) || !is_string($rawDeclaration)) {
            continue;
        }

        $rawDeclaration = str_replace(["\r\n", "\r"], "\n", $rawDeclaration);
        $rawDeclaration = trim(substr($rawDeclaration, 0, 1000));
        if ($rawDeclaration === '') {
            continue;
        }

        $lines = preg_split('/[;\n]+/', $rawDeclaration) ?: [];
        $safeLines = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $line, 2));
            $property = strtolower($property);
            if (!isset($allowedProperties[$property])) {
                continue;
            }
            if (preg_match('/[{}@\\\\]|url\s*\(|expression\s*\(|javascript\s*:/i', $value) === 1) {
                continue;
            }
            if (preg_match('/\A(?:[-0-9.]+(?:px|rem|em|%)?|[0-9.]+|[a-zA-Z-]+|var\(--sr-[a-z-]+\))(?:\s+[-0-9.]+(?:px|rem|em|%)?)*\z/', $value) !== 1) {
                continue;
            }

            $safeLines[] = $property . ': ' . $value;
        }

        if ($safeLines !== []) {
            $normalized[$selectorKey] = implode('; ', array_slice($safeLines, 0, 12));
        }
    }

    return $normalized;
}

function sr_markdown_editor_style_selector_options(): array
{
    return [
        'wrapper' => '전체 본문',
        'paragraph' => '문단',
        'heading' => '제목 전체',
        'heading_h1' => '제목 1',
        'heading_h2' => '제목 2',
        'heading_h3' => '제목 3',
        'heading_h4' => '제목 4',
        'heading_h5' => '제목 5',
        'heading_h6' => '제목 6',
        'strong' => '굵은 글자',
        'emphasis' => '기울임 글자',
        'link' => '링크',
        'list' => '목록',
        'list_item' => '목록 항목',
        'task_list' => '작업 목록',
        'blockquote' => '인용',
        'code' => '코드 전체',
        'inline_code' => '인라인 코드',
        'code_block' => '코드 블록',
        'table' => '표 전체',
        'table_head' => '표 헤더',
        'table_cell' => '표 셀',
        'hr' => '구분선',
    ];
}

function sr_markdown_editor_settings_from_post(): array
{
    $style = [];
    foreach (sr_markdown_editor_default_style_profile() as $key => $_default) {
        $style[$key] = $_POST['style_profile'][$key] ?? null;
    }

    return [
        'tables_enabled' => ($_POST['tables_enabled'] ?? '') === '1',
        'task_lists_enabled' => ($_POST['task_lists_enabled'] ?? '') === '1',
        'code_blocks_enabled' => ($_POST['code_blocks_enabled'] ?? '') === '1',
        'raw_html_enabled' => ($_POST['raw_html_enabled'] ?? '') === '1',
        'style_profile_json' => sr_markdown_editor_normalize_style_profile($style),
        'custom_declarations_json' => sr_markdown_editor_normalize_custom_declarations($_POST['custom_declarations'] ?? []),
    ];
}

function sr_markdown_editor_validate_settings(array $settings): array
{
    $errors = [];
    if (!empty($settings['raw_html_enabled'])) {
        $errors[] = 'v1에서는 raw HTML 허용을 켤 수 없습니다.';
    }

    return $errors;
}

function sr_markdown_editor_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'markdown_editor' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('Markdown Editor 플러그인이 등록되어 있지 않습니다.');
    }

    $rows = [
        ['tables_enabled', !empty($settings['tables_enabled']) ? '1' : '0', 'bool'],
        ['task_lists_enabled', !empty($settings['task_lists_enabled']) ? '1' : '0', 'bool'],
        ['code_blocks_enabled', !empty($settings['code_blocks_enabled']) ? '1' : '0', 'bool'],
        ['raw_html_enabled', '0', 'bool'],
        ['style_profile_json', sr_markdown_editor_json_encode(sr_markdown_editor_normalize_style_profile($settings['style_profile_json'] ?? [])), 'json'],
        ['custom_declarations_json', sr_markdown_editor_json_encode(sr_markdown_editor_normalize_custom_declarations($settings['custom_declarations_json'] ?? [])), 'json'],
    ];
    $now = sr_now();
    $save = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    foreach ($rows as $row) {
        $save->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => (string) $row[0],
            'setting_value' => (string) $row[1],
            'value_type' => (string) $row[2],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    sr_clear_module_settings_cache('markdown_editor');
}

function sr_markdown_editor_profile_hash(PDO $pdo, ?array $overrideSettings = null): string
{
    $settings = sr_markdown_editor_settings($pdo, $overrideSettings);
    $payload = sr_markdown_editor_json_encode([
        'parser' => [
            'tables' => $settings['tables_enabled'],
            'tasks' => $settings['task_lists_enabled'],
            'code' => $settings['code_blocks_enabled'],
            'raw_html' => false,
        ],
        'style' => $settings['style_profile_json'],
        'custom' => $settings['custom_declarations_json'],
    ]);

    return substr(hash('sha256', $payload), 0, 16);
}

function sr_markdown_editor_stylesheet_url(PDO $pdo): string
{
    return sr_url('/markdown-editor/style.css?v=' . rawurlencode(sr_markdown_editor_profile_hash($pdo)));
}

function sr_markdown_editor_assets_html(PDO $pdo, string $presetKey = 'default'): string
{
    return '<link rel="stylesheet" href="' . sr_e(sr_asset_url('/modules/markdown_editor/assets/editor.css')) . '">' . PHP_EOL
        . '<script src="' . sr_e(sr_asset_url('/modules/markdown_editor/assets/editor.js')) . '" defer></script>';
}

function sr_markdown_editor_reset_css(): string
{
    if (!is_file(SR_ROOT . '/assets/editor-md.css')) {
        return '';
    }

    $resetCss = file_get_contents(SR_ROOT . '/assets/editor-md.css');
    return is_string($resetCss) ? trim($resetCss) . "\n" : '';
}

function sr_markdown_editor_preview_css(PDO $pdo, ?array $overrideSettings = null): string
{
    return sr_markdown_editor_reset_css() . sr_markdown_editor_css($pdo, $overrideSettings);
}

function sr_markdown_editor_render(PDO $pdo, string $markdown, string $mode = 'full', array $context = []): array
{
    static $memo = [];
    $mode = in_array($mode, ['full', 'inline', 'plain'], true) ? $mode : 'full';
    $overrideSettings = is_array($context['settings_override'] ?? null) ? $context['settings_override'] : null;
    $profileHash = sr_markdown_editor_profile_hash($pdo, $overrideSettings);
    $cacheKey = (string) spl_object_id($pdo) . ':' . $mode . ':' . $profileHash . ':' . hash('sha256', $markdown);
    if (isset($memo[$cacheKey])) {
        return $memo[$cacheKey];
    }

    $settings = sr_markdown_editor_settings($pdo, $overrideSettings);
    $bodyHtml = sr_markdown_editor_markdown_to_html($markdown, $settings, $mode);
    $plainText = sr_markdown_editor_plain_text($markdown, $settings);
    $html = $mode === 'plain' ? sr_e($plainText) : $bodyHtml;
    if ($mode === 'full' && $html !== '') {
        $html = '<div class="markdown-editor-body" data-markdown-profile="' . sr_e($profileHash) . '">' . $html . '</div>';
    }

    $memo[$cacheKey] = [
        'html' => $html,
        'plain_text' => $plainText,
        'stylesheets' => $mode === 'full' ? [sr_markdown_editor_stylesheet_url($pdo)] : [],
        'profile_hash' => $profileHash,
    ];

    return $memo[$cacheKey];
}

function sr_markdown_editor_markdown_to_html(string $markdown, array $settings, string $mode = 'full'): string
{
    $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown));
    if ($markdown === '') {
        return '';
    }
    if ($mode === 'inline') {
        return sr_markdown_editor_inline_html(preg_replace('/\s+/', ' ', $markdown) ?? $markdown, false);
    }

    $html = [];
    $paragraph = [];
    $listType = '';
    $listItems = [];
    $inCode = false;
    $codeLines = [];

    $flushParagraph = static function () use (&$html, &$paragraph): void {
        if ($paragraph !== []) {
            $html[] = '<p>' . sr_markdown_editor_inline_html(implode("\n", $paragraph), true) . '</p>';
            $paragraph = [];
        }
    };
    $flushList = static function () use (&$html, &$listType, &$listItems): void {
        if ($listType === '' || $listItems === []) {
            return;
        }
        $items = [];
        foreach ($listItems as $item) {
            $items[] = '<li>' . $item . '</li>';
        }
        $html[] = '<' . $listType . '>' . implode('', $items) . '</' . $listType . '>';
        $listType = '';
        $listItems = [];
    };
    $flushCode = static function () use (&$html, &$inCode, &$codeLines): void {
        if ($inCode) {
            $html[] = '<pre><code>' . sr_e(implode("\n", $codeLines)) . '</code></pre>';
            $inCode = false;
            $codeLines = [];
        }
    };

    foreach (explode("\n", $markdown) as $line) {
        $trimmed = trim($line);
        if (!empty($settings['code_blocks_enabled']) && str_starts_with($trimmed, '```')) {
            if ($inCode) {
                $flushCode();
            } else {
                $flushParagraph();
                $flushList();
                $inCode = true;
                $codeLines = [];
            }
            continue;
        }
        if ($inCode) {
            $codeLines[] = $line;
            continue;
        }
        if ($trimmed === '') {
            $flushParagraph();
            $flushList();
            continue;
        }
        if (preg_match('/\A(#{1,6})\s+(.+)\z/', $trimmed, $matches) === 1) {
            $flushParagraph();
            $flushList();
            $level = strlen($matches[1]);
            $html[] = '<h' . $level . '>' . sr_markdown_editor_inline_html((string) $matches[2], false) . '</h' . $level . '>';
            continue;
        }
        if ($trimmed === '---' || $trimmed === '***') {
            $flushParagraph();
            $flushList();
            $html[] = '<hr>';
            continue;
        }
        if (str_starts_with($trimmed, '> ')) {
            $flushParagraph();
            $flushList();
            $html[] = '<blockquote><p>' . sr_markdown_editor_inline_html(substr($trimmed, 2), false) . '</p></blockquote>';
            continue;
        }
        if (!empty($settings['tables_enabled']) && str_contains($trimmed, '|')) {
            $table = sr_markdown_editor_table_html($markdown, $trimmed);
            if ($table !== '') {
                $flushParagraph();
                $flushList();
                $html[] = $table;
                continue;
            }
        }
        if (preg_match('/\A[-*+]\s+(.+)\z/', $trimmed, $matches) === 1) {
            $flushParagraph();
            if ($listType !== 'ul') {
                $flushList();
                $listType = 'ul';
            }
            $item = (string) $matches[1];
            if (!empty($settings['task_lists_enabled']) && preg_match('/\A\[(x|X| )\]\s+(.+)\z/', $item, $task) === 1) {
                $checked = strtolower((string) $task[1]) === 'x' ? ' checked' : '';
                $item = '<input type="checkbox" disabled' . $checked . '> ' . sr_markdown_editor_inline_html((string) $task[2], false);
            } else {
                $item = sr_markdown_editor_inline_html($item, false);
            }
            $listItems[] = $item;
            continue;
        }
        if (preg_match('/\A[0-9]+\.\s+(.+)\z/', $trimmed, $matches) === 1) {
            $flushParagraph();
            if ($listType !== 'ol') {
                $flushList();
                $listType = 'ol';
            }
            $listItems[] = sr_markdown_editor_inline_html((string) $matches[1], false);
            continue;
        }

        $flushList();
        $paragraph[] = $trimmed;
    }
    $flushCode();
    $flushParagraph();
    $flushList();

    return implode("\n", $html);
}

function sr_markdown_editor_table_html(string $markdown, string $currentLine): string
{
    if (preg_match('/\A\|?(.+\|.+)\|?\z/', $currentLine) !== 1) {
        return '';
    }
    $cells = array_map('trim', explode('|', trim($currentLine, '| ')));
    if (count($cells) < 2) {
        return '';
    }
    $head = [];
    foreach ($cells as $cell) {
        $head[] = '<th>' . sr_markdown_editor_inline_html($cell, false) . '</th>';
    }

    return '<table><thead><tr>' . implode('', $head) . '</tr></thead></table>';
}

function sr_markdown_editor_inline_html(string $text, bool $allowLineBreaks): string
{
    $placeholders = [];
    $text = preg_replace_callback('/`([^`]+)`/', static function (array $matches) use (&$placeholders): string {
        $token = "\x1A" . count($placeholders) . "\x1A";
        $placeholders[$token] = '<code>' . sr_e((string) $matches[1]) . '</code>';
        return $token;
    }, $text) ?? $text;
    $text = preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/', static function (array $matches) use (&$placeholders): string {
        $url = trim((string) $matches[2]);
        if (!sr_is_safe_relative_url($url) && !sr_is_http_url($url)) {
            return (string) $matches[0];
        }
        $token = "\x1A" . count($placeholders) . "\x1A";
        $placeholders[$token] = '<a href="' . sr_e($url) . '" rel="nofollow noopener noreferrer">' . sr_e((string) $matches[1]) . '</a>';
        return $token;
    }, $text) ?? $text;

    $html = sr_e($text);
    $html = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/__([^_\n]+)__/', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $html) ?? $html;
    $html = preg_replace('/(?<!_)_([^_\n]+)_(?!_)/', '<em>$1</em>', $html) ?? $html;
    if ($allowLineBreaks) {
        $html = nl2br($html, false);
    }

    return strtr($html, $placeholders);
}

function sr_markdown_editor_plain_text(string $markdown, array $settings): string
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(sr_markdown_editor_markdown_to_html($markdown, $settings, 'full')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '');
}

function sr_markdown_editor_css(PDO $pdo, ?array $overrideSettings = null): string
{
    $settings = sr_markdown_editor_settings($pdo, $overrideSettings);
    $style = $settings['style_profile_json'];
    $custom = $settings['custom_declarations_json'];
    $selectorMap = [
        'wrapper' => '.markdown-editor-body',
        'paragraph' => '.markdown-editor-body p',
        'heading' => '.markdown-editor-body h1, .markdown-editor-body h2, .markdown-editor-body h3, .markdown-editor-body h4, .markdown-editor-body h5, .markdown-editor-body h6',
        'heading_h1' => '.markdown-editor-body h1',
        'heading_h2' => '.markdown-editor-body h2',
        'heading_h3' => '.markdown-editor-body h3',
        'heading_h4' => '.markdown-editor-body h4',
        'heading_h5' => '.markdown-editor-body h5',
        'heading_h6' => '.markdown-editor-body h6',
        'strong' => '.markdown-editor-body strong',
        'emphasis' => '.markdown-editor-body em',
        'link' => '.markdown-editor-body a',
        'list' => '.markdown-editor-body ul, .markdown-editor-body ol',
        'list_item' => '.markdown-editor-body li',
        'task_list' => '.markdown-editor-body li input[type="checkbox"]',
        'blockquote' => '.markdown-editor-body blockquote',
        'code' => '.markdown-editor-body code, .markdown-editor-body pre',
        'inline_code' => '.markdown-editor-body :not(pre)>code',
        'code_block' => '.markdown-editor-body pre',
        'table' => '.markdown-editor-body table, .markdown-editor-body th, .markdown-editor-body td',
        'table_head' => '.markdown-editor-body th',
        'table_cell' => '.markdown-editor-body td',
        'hr' => '.markdown-editor-body hr',
    ];

    $css = [];
    $maxWidth = (int) $style['max_width'];
    $css[] = '.markdown-editor-body{color:var(' . $style['text_token'] . ', var(--sr-text));font-size:' . (int) $style['font_size'] . 'px;line-height:' . (string) $style['line_height'] . ';' . ($maxWidth > 0 ? 'max-width:' . $maxWidth . 'px;margin-inline:auto;' : '') . '}';
    $css[] = '.markdown-editor-body p{margin:0 0 ' . (int) $style['paragraph_margin'] . 'px;}';
    $css[] = '.markdown-editor-body>*+*{margin-top:' . (int) $style['block_gap'] . 'px;}';
    $css[] = '.markdown-editor-body h1,.markdown-editor-body h2,.markdown-editor-body h3,.markdown-editor-body h4,.markdown-editor-body h5,.markdown-editor-body h6{margin:' . (int) $style['heading_margin'] . 'px 0 ' . (int) max(8, (int) $style['paragraph_margin']) . 'px;font-weight:' . (int) $style['heading_weight'] . ';line-height:' . (string) $style['heading_line_height'] . ';color:var(' . $style['heading_token'] . ', var(--sr-text));}';
    $css[] = '.markdown-editor-body h1{font-size:' . (int) $style['h1_size'] . 'px;}';
    $css[] = '.markdown-editor-body h2{font-size:' . (int) $style['h2_size'] . 'px;}';
    $css[] = '.markdown-editor-body h3{font-size:' . (int) $style['h3_size'] . 'px;}';
    $css[] = '.markdown-editor-body h4{font-size:' . (int) $style['h4_size'] . 'px;}';
    $css[] = '.markdown-editor-body h5{font-size:' . (int) $style['h5_size'] . 'px;}';
    $css[] = '.markdown-editor-body h6{font-size:' . (int) $style['h6_size'] . 'px;}';
    $css[] = '.markdown-editor-body strong{font-weight:' . (int) $style['strong_weight'] . ';}';
    $css[] = '.markdown-editor-body a{color:var(' . $style['accent_token'] . ', var(--sr-info));font-weight:' . (int) $style['link_weight'] . ';text-decoration:underline;text-underline-offset:' . (int) $style['link_underline_offset'] . 'px;text-decoration-thickness:' . (int) $style['link_decoration_thickness'] . 'px;}';
    $css[] = '.markdown-editor-body ul,.markdown-editor-body ol{padding-inline-start:' . (int) $style['list_indent'] . 'px;margin:0 0 ' . (int) $style['paragraph_margin'] . 'px;}';
    $css[] = '.markdown-editor-body li+li{margin-top:' . (int) $style['list_item_gap'] . 'px;}';
    $css[] = '.markdown-editor-body li input[type="checkbox"]{margin-right:6px;}';
    $css[] = '.markdown-editor-body blockquote{margin:0 0 ' . (int) $style['paragraph_margin'] . 'px;padding:' . (int) $style['quote_padding_block'] . 'px ' . (int) $style['quote_padding_inline'] . 'px;border-left:' . (int) $style['quote_border_width'] . 'px solid var(' . $style['border_token'] . ', var(--sr-border));color:var(' . $style['quote_token'] . ', var(--sr-muted));background:var(' . $style['surface_token'] . ', var(--sr-surface-muted));}';
    $css[] = '.markdown-editor-body code{font-size:' . (int) $style['code_font_size'] . 'px;color:var(' . $style['code_token'] . ', var(--sr-text));background:var(' . $style['code_surface_token'] . ', var(--sr-surface-muted));border-radius:' . (int) $style['border_radius'] . 'px;padding:0 ' . (int) $style['inline_code_padding'] . 'px;}';
    $css[] = '.markdown-editor-body pre{overflow:auto;padding:' . (int) $style['code_block_padding'] . 'px;line-height:' . (string) $style['code_line_height'] . ';background:var(' . $style['code_surface_token'] . ', var(--sr-surface-muted));border:' . (int) $style['code_block_border_width'] . 'px solid var(' . $style['border_token'] . ', var(--sr-border));border-radius:' . (int) $style['border_radius'] . 'px;}';
    $css[] = '.markdown-editor-body pre code{padding:0;background:transparent;border-radius:0;}';
    $css[] = '.markdown-editor-body table{width:100%;border-collapse:collapse;margin:0 0 ' . (int) $style['paragraph_margin'] . 'px;}';
    $css[] = '.markdown-editor-body th,.markdown-editor-body td{padding:' . (int) $style['table_cell_padding'] . 'px;border:' . (int) $style['table_border_width'] . 'px solid var(' . $style['border_token'] . ', var(--sr-border));text-align:left;}';
    $css[] = '.markdown-editor-body th{font-weight:' . (int) $style['table_header_weight'] . ';background:var(' . $style['table_header_surface_token'] . ', var(--sr-surface-muted));}';
    $css[] = '.markdown-editor-body hr{border:0;border-top:' . (int) $style['hr_border_width'] . 'px solid var(' . $style['border_token'] . ', var(--sr-border));margin:' . (int) $style['hr_margin'] . 'px 0;}';
    foreach ($custom as $selectorKey => $declaration) {
        if (isset($selectorMap[$selectorKey])) {
            $css[] = $selectorMap[$selectorKey] . '{' . $declaration . ';}';
        }
    }

    return implode("\n", $css) . "\n";
}

function sr_markdown_editor_sample_markdown(): string
{
    return "# 제목 1\n\n## 제목 2\n\n### 제목 3\n\n본문 문단과 [링크](https://example.com), `inline code`입니다.\n\n- 목록 항목\n- [x] 완료된 작업\n\n> 인용 문장\n\n---\n\n```\ncode block\n```\n\n| 표 제목 | 값 |\n";
}
