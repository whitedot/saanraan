<?php

declare(strict_types=1);

function sr_markdown_editor_default_settings(): array
{
    return [
        'tables_enabled' => true,
        'task_lists_enabled' => true,
        'code_blocks_enabled' => true,
        'raw_html_enabled' => false,
        'style_source_mode' => 'custom',
        'style_profile_json' => sr_markdown_editor_default_style_profile(),
        'custom_declarations_json' => [],
        'stylesheet_css' => '',
    ];
}

function sr_markdown_editor_box_target_definitions(): array
{
    return [
        'paragraph' => ['selector' => '.markdown-editor-body p', 'margin' => [0, 0, 16, 0], 'padding' => [0, 0, 0, 0], 'border' => [0, 0, 0, 0], 'radius' => 0],
        'h1' => ['selector' => '.markdown-editor-body h1', 'margin' => [24, 0, 16, 0], 'padding' => [0, 0, 5, 0], 'border' => [0, 0, 1, 0], 'radius' => 0],
        'h2' => ['selector' => '.markdown-editor-body h2', 'margin' => [24, 0, 16, 0], 'padding' => [0, 0, 5, 0], 'border' => [0, 0, 1, 0], 'radius' => 0],
        'h3' => ['selector' => '.markdown-editor-body h3', 'margin' => [24, 0, 16, 0], 'padding' => [0, 0, 0, 0], 'border' => [0, 0, 0, 0], 'radius' => 0],
        'h4' => ['selector' => '.markdown-editor-body h4', 'margin' => [24, 0, 16, 0], 'padding' => [0, 0, 0, 0], 'border' => [0, 0, 0, 0], 'radius' => 0],
        'h5' => ['selector' => '.markdown-editor-body h5', 'margin' => [24, 0, 16, 0], 'padding' => [0, 0, 0, 0], 'border' => [0, 0, 0, 0], 'radius' => 0],
        'h6' => ['selector' => '.markdown-editor-body h6', 'margin' => [24, 0, 16, 0], 'padding' => [0, 0, 0, 0], 'border' => [0, 0, 0, 0], 'radius' => 0],
        'link' => ['selector' => '.markdown-editor-body a', 'margin' => [0, 0, 0, 0], 'padding' => [0, 0, 0, 0], 'border' => [0, 0, 0, 0], 'radius' => 0],
        'list' => ['selector' => '.markdown-editor-body ul, .markdown-editor-body ol', 'margin' => [0, 0, 16, 0], 'padding' => [0, 0, 0, 32], 'border' => [0, 0, 0, 0], 'radius' => 0],
        'blockquote' => ['selector' => '.markdown-editor-body blockquote', 'margin' => [16, 0, 16, 0], 'padding' => [0, 16, 0, 16], 'border' => [0, 0, 0, 4], 'radius' => 0],
        'inline_code' => ['selector' => '.markdown-editor-body :not(pre) > code, .markdown-editor-body tt', 'margin' => [0, 0, 0, 0], 'padding' => [0, 4, 0, 4], 'border' => [0, 0, 0, 0], 'radius' => 6],
        'code_block' => ['selector' => '.markdown-editor-body pre', 'margin' => [0, 0, 16, 0], 'padding' => [16, 16, 16, 16], 'border' => [1, 1, 1, 1], 'radius' => 6],
        'table' => ['selector' => '.markdown-editor-body table', 'margin' => [0, 0, 16, 0], 'padding' => [0, 0, 0, 0], 'border' => [0, 0, 0, 0], 'radius' => 0],
        'hr' => ['selector' => '.markdown-editor-body hr', 'margin' => [24, 0, 24, 0], 'padding' => [0, 0, 0, 0], 'border' => [1, 0, 0, 0], 'radius' => 0],
    ];
}

function sr_markdown_editor_box_control_keys(string $target): array
{
    $keys = [];
    foreach (['margin', 'padding', 'border'] as $property) {
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $keys[] = 'box_' . $target . '_' . $property . '_' . $side;
        }
    }
    $keys[] = 'box_' . $target . '_radius';
    return $keys;
}

function sr_markdown_editor_text_target_definitions(): array
{
    $base = ['size' => 16, 'weight' => 400, 'line_height' => 1.5, 'letter_spacing' => 0.0, 'word_spacing' => 0, 'align' => 'left', 'style' => 'normal', 'decoration' => 'none', 'transform' => 'none', 'token' => '--md-text'];
    return [
        'paragraph' => ['selector' => '.markdown-editor-body p'] + $base,
        'h1' => ['selector' => '.markdown-editor-body h1', 'size' => 32, 'weight' => 700, 'line_height' => 1.25] + $base,
        'h2' => ['selector' => '.markdown-editor-body h2', 'size' => 26, 'weight' => 700, 'line_height' => 1.25] + $base,
        'h3' => ['selector' => '.markdown-editor-body h3', 'size' => 24, 'weight' => 700, 'line_height' => 1.25] + $base,
        'h4' => ['selector' => '.markdown-editor-body h4', 'size' => 20, 'weight' => 700, 'line_height' => 1.25] + $base,
        'h5' => ['selector' => '.markdown-editor-body h5', 'size' => 18, 'weight' => 700, 'line_height' => 1.25] + $base,
        'h6' => ['selector' => '.markdown-editor-body h6', 'size' => 16, 'weight' => 700, 'line_height' => 1.25, 'token' => '--md-muted'] + $base,
        'link' => ['selector' => '.markdown-editor-body a', 'decoration' => 'underline', 'token' => '--md-info'] + $base,
        'list' => ['selector' => '.markdown-editor-body ul, .markdown-editor-body ol'] + $base,
        'blockquote' => ['selector' => '.markdown-editor-body blockquote, .markdown-editor-body blockquote > p', 'token' => '--md-muted'] + $base,
        'inline_code' => ['selector' => '.markdown-editor-body :not(pre) > code, .markdown-editor-body tt', 'size' => 14] + $base,
        'code_block' => ['selector' => '.markdown-editor-body pre', 'size' => 14, 'line_height' => 1.45] + $base,
        'table' => ['selector' => '.markdown-editor-body table'] + $base,
    ];
}

function sr_markdown_editor_default_style_profile(): array
{
    $profile = [
        'content_padding_block' => 0,
        'content_padding_inline' => 0,
        'font_size' => 16,
        'line_height' => 1.5,
        'letter_spacing' => 0.0,
        'word_spacing' => 0,
        'text_align' => 'left',
        'block_gap' => 0,
        'paragraph_margin_top' => 0,
        'paragraph_margin' => 16,
        'paragraph_line_height' => 1.5,
        'paragraph_letter_spacing' => 0.0,
        'paragraph_text_indent' => 0,
        'heading_line_height' => 1.25,
        'heading_weight' => 700,
        'heading_margin' => 24,
        'heading_margin_bottom' => 16,
        'heading_padding_bottom' => 5,
        'heading_border_width' => 1,
        'heading_letter_spacing' => 0.0,
        'heading_text_align' => 'left',
        'heading_text_transform' => 'none',
        'h1_size' => 32,
        'h2_size' => 26,
        'h3_size' => 24,
        'h4_size' => 20,
        'h5_size' => 18,
        'h6_size' => 16,
        'strong_weight' => 600,
        'link_weight' => 400,
        'link_decoration' => 'underline',
        'link_underline_offset' => 2,
        'link_decoration_thickness' => 1,
        'list_indent' => 32,
        'list_line_height' => 1.5,
        'unordered_list_style' => 'disc',
        'ordered_list_style' => 'decimal',
        'list_item_gap' => 6,
        'task_checkbox_gap' => 6,
        'quote_margin_block' => 16,
        'quote_border_width' => 4,
        'quote_border_style' => 'solid',
        'quote_radius' => 0,
        'quote_padding_block' => 0,
        'quote_padding_inline' => 16,
        'code_font_size' => 14,
        'code_line_height' => 1.45,
        'inline_code_padding_block' => 0,
        'inline_code_padding' => 4,
        'code_block_padding_block' => 16,
        'code_block_padding_inline' => 16,
        'code_block_border_width' => 1,
        'code_block_radius' => 6,
        'table_width' => 'max-content',
        'table_font_size' => 16,
        'table_line_height' => 1.5,
        'table_border_width' => 1,
        'table_cell_padding_block' => 6,
        'table_cell_padding_inline' => 6,
        'table_header_weight' => 600,
        'hr_margin' => 24,
        'hr_width' => 100,
        'hr_border_width' => 1,
        'border_radius' => 6,
        'image_max_width' => 100,
        'caption_font_size' => 14,
        'details_padding' => 12,
        'footnote_font_size' => 12,
        'alert_border_width' => 4,
        'alert_padding_block' => 8,
        'alert_padding_inline' => 16,
        'text_token' => '--md-text',
        'muted_token' => '--md-muted',
        'border_token' => '--md-border',
        'surface_token' => '--md-surface-muted',
        'accent_token' => '--md-info',
        'heading_token' => '--md-text',
        'heading_border_token' => '--md-border',
        'quote_token' => '--md-muted',
        'quote_surface_token' => '--md-surface-muted',
        'quote_border_token' => '--md-border',
        'code_token' => '--md-text',
        'code_surface_token' => '--md-surface-muted',
        'code_border_token' => '--md-border',
        'table_header_surface_token' => '--md-surface-muted',
        'table_border_token' => '--md-border',
        'hr_border_token' => '--md-border',
    ];

    foreach (sr_markdown_editor_box_target_definitions() as $target => $definition) {
        foreach (['margin', 'padding', 'border'] as $property) {
            foreach (['top', 'right', 'bottom', 'left'] as $index => $side) {
                $profile['box_' . $target . '_' . $property . '_' . $side] = (int) $definition[$property][$index];
            }
        }
        $profile['box_' . $target . '_radius'] = (int) $definition['radius'];
        $profile['box_' . $target . '_border_style'] = 'solid';
        $profile['box_' . $target . '_border_token'] = '--md-border';
    }

    foreach (sr_markdown_editor_text_target_definitions() as $target => $definition) {
        $profile['text_' . $target . '_font_size'] = (int) $definition['size'];
        $profile['text_' . $target . '_font_weight'] = (int) $definition['weight'];
        $profile['text_' . $target . '_line_height'] = (float) $definition['line_height'];
        $profile['text_' . $target . '_letter_spacing'] = (float) $definition['letter_spacing'];
        $profile['text_' . $target . '_word_spacing'] = (int) $definition['word_spacing'];
        $profile['text_' . $target . '_align'] = (string) $definition['align'];
        $profile['text_' . $target . '_font_style'] = (string) $definition['style'];
        $profile['text_' . $target . '_decoration'] = (string) $definition['decoration'];
        $profile['text_' . $target . '_transform'] = (string) $definition['transform'];
        $profile['text_' . $target . '_token'] = (string) $definition['token'];
    }

    return $profile;
}

function sr_markdown_editor_json_encode(mixed $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : '{}';
}

function sr_markdown_editor_token_options(): array
{
    return [
        '--md-text' => '본문',
        '--md-muted' => '보조',
        '--md-border' => '경계선',
        '--md-surface-muted' => '보조 배경',
        '--md-info' => '정보',
        '--md-success' => '성공',
        '--md-warning' => '주의',
        '--md-danger' => '위험',
    ];
}

function sr_markdown_editor_migrate_token_namespace(string $value): string
{
    return str_replace(
        [
            '--sr-muted-strong', '--sr-surface-muted', '--sr-surface-soft', '--sr-border-soft',
            '--sr-text', '--sr-muted', '--sr-surface', '--sr-border',
            '--sr-info', '--sr-success', '--sr-warning', '--sr-danger',
        ],
        [
            '--md-muted-strong', '--md-surface-muted', '--md-surface-soft', '--md-border-soft',
            '--md-text', '--md-muted', '--md-surface', '--md-border',
            '--md-info', '--md-success', '--md-warning', '--md-danger',
        ],
        $value
    );
}

function sr_markdown_editor_migrate_default_heading_scale(string $css): string
{
    $oldSizes = ['h1' => 32, 'h2' => 24, 'h3' => 20, 'h4' => 16, 'h5' => 14, 'h6' => 13];
    $newSizes = ['h1' => 32, 'h2' => 26, 'h3' => 24, 'h4' => 20, 'h5' => 18, 'h6' => 16];
    $replacements = [];
    foreach ($oldSizes as $heading => $oldSize) {
        foreach ([$heading . '_size', 'text_' . $heading . '_font_size'] as $controlKey) {
            $oldDeclaration = '/* sr-control: ' . $controlKey . " */\n    font-size: " . $oldSize . 'px;';
            if (!str_contains($css, $oldDeclaration)) {
                return $css;
            }
            $replacements[$oldDeclaration] = '/* sr-control: ' . $controlKey . " */\n    font-size: " . $newSizes[$heading] . 'px;';
        }
    }

    return str_replace(array_keys($replacements), array_values($replacements), $css);
}

function sr_markdown_editor_token_foundation_stylesheet(): string
{
    return <<<'CSS'
.markdown-editor-body {
    --md-text: var(--color-body-color);
    --md-muted: var(--text-muted);
    --md-muted-strong: var(--color-default-700);
    --md-surface: var(--color-card);
    --md-surface-soft: var(--color-default-50);
    --md-surface-muted: var(--color-default-100);
    --md-border: var(--color-default-300);
    --md-border-soft: var(--color-default-200);
    --md-info: var(--color-info);
    --md-success: var(--color-success);
    --md-warning: var(--color-warning);
    --md-danger: var(--color-danger);
}
CSS;
}

function sr_markdown_editor_ensure_token_foundation_stylesheet(string $css): string
{
    $requiredTokens = [
        '--md-text', '--md-muted', '--md-muted-strong', '--md-surface', '--md-surface-soft',
        '--md-surface-muted', '--md-border', '--md-border-soft', '--md-info', '--md-success',
        '--md-warning', '--md-danger',
    ];
    foreach ($requiredTokens as $token) {
        if (!str_contains($css, $token . ':')) {
            return sr_markdown_editor_token_foundation_stylesheet() . "\n\n" . trim($css);
        }
    }

    return trim($css);
}

function sr_markdown_editor_style_choice_options(): array
{
    $choices = [
        'text_align' => ['left' => '왼쪽', 'center' => '가운데', 'right' => '오른쪽', 'justify' => '양쪽'],
        'heading_text_align' => ['left' => '왼쪽', 'center' => '가운데', 'right' => '오른쪽'],
        'heading_text_transform' => ['none' => '원문 유지', 'uppercase' => '대문자', 'lowercase' => '소문자', 'capitalize' => '단어 첫 글자'],
        'link_decoration' => ['underline' => '밑줄', 'none' => '없음'],
        'unordered_list_style' => ['disc' => '채운 원', 'circle' => '빈 원', 'square' => '사각형', 'none' => '없음'],
        'ordered_list_style' => ['decimal' => '숫자', 'lower-alpha' => '소문자 알파벳', 'upper-alpha' => '대문자 알파벳', 'lower-roman' => '소문자 로마자', 'upper-roman' => '대문자 로마자', 'none' => '없음'],
        'quote_border_style' => ['solid' => '실선', 'dashed' => '파선', 'dotted' => '점선', 'double' => '이중선'],
        'table_width' => ['max-content' => '내용에 맞춤', '100%' => '사용할 수 있는 너비 채움'],
    ];

    foreach (array_keys(sr_markdown_editor_box_target_definitions()) as $target) {
        $choices['box_' . $target . '_border_style'] = [
            'solid' => '실선',
            'dashed' => '파선',
            'dotted' => '점선',
            'double' => '이중선',
            'none' => '없음',
        ];
    }
    foreach (array_keys(sr_markdown_editor_text_target_definitions()) as $target) {
        $choices['text_' . $target . '_align'] = $choices['text_align'];
        $choices['text_' . $target . '_font_style'] = ['normal' => '기본', 'italic' => '기울임', 'oblique' => '비스듬히'];
        $choices['text_' . $target . '_decoration'] = ['none' => '없음', 'underline' => '밑줄', 'line-through' => '취소선', 'overline' => '윗줄'];
        $choices['text_' . $target . '_transform'] = $choices['heading_text_transform'];
    }

    return $choices;
}

function sr_markdown_editor_style_binding_map(): array
{
    $bindings = [
        'content_padding_block' => ['property' => 'padding-block', 'unit' => 'px', 'kind' => 'number'],
        'content_padding_inline' => ['property' => 'padding-inline', 'unit' => 'px', 'kind' => 'number'],
        'font_size' => ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'],
        'line_height' => ['property' => 'line-height', 'unit' => '', 'kind' => 'number'],
        'letter_spacing' => ['property' => 'letter-spacing', 'unit' => 'px', 'kind' => 'number'],
        'word_spacing' => ['property' => 'word-spacing', 'unit' => 'px', 'kind' => 'number'],
        'text_align' => ['property' => 'text-align', 'unit' => '', 'kind' => 'choice'],
        'block_gap' => ['property' => 'margin-top', 'unit' => 'px', 'kind' => 'number'],
        'paragraph_margin_top' => ['property' => 'margin-top', 'unit' => 'px', 'kind' => 'number'],
        'paragraph_margin' => ['property' => 'margin-bottom', 'unit' => 'px', 'kind' => 'number'],
        'paragraph_line_height' => ['property' => 'line-height', 'unit' => '', 'kind' => 'number'],
        'paragraph_letter_spacing' => ['property' => 'letter-spacing', 'unit' => 'px', 'kind' => 'number'],
        'paragraph_text_indent' => ['property' => 'text-indent', 'unit' => 'px', 'kind' => 'number'],
        'heading_line_height' => ['property' => 'line-height', 'unit' => '', 'kind' => 'number'],
        'heading_weight' => ['property' => 'font-weight', 'unit' => '', 'kind' => 'number'],
        'heading_margin' => ['property' => 'margin-top', 'unit' => 'px', 'kind' => 'number'],
        'heading_margin_bottom' => ['property' => 'margin-bottom', 'unit' => 'px', 'kind' => 'number'],
        'heading_padding_bottom' => ['property' => 'padding-bottom', 'unit' => 'px', 'kind' => 'number'],
        'heading_border_width' => ['property' => 'border-bottom-width', 'unit' => 'px', 'kind' => 'number'],
        'heading_letter_spacing' => ['property' => 'letter-spacing', 'unit' => 'px', 'kind' => 'number'],
        'heading_text_align' => ['property' => 'text-align', 'unit' => '', 'kind' => 'choice'],
        'heading_text_transform' => ['property' => 'text-transform', 'unit' => '', 'kind' => 'choice'],
        'h1_size' => ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'],
        'h2_size' => ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'],
        'h3_size' => ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'],
        'h4_size' => ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'],
        'h5_size' => ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'],
        'h6_size' => ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'],
        'strong_weight' => ['property' => 'font-weight', 'unit' => '', 'kind' => 'number'],
        'link_weight' => ['property' => 'font-weight', 'unit' => '', 'kind' => 'number'],
        'link_decoration' => ['property' => 'text-decoration-line', 'unit' => '', 'kind' => 'choice'],
        'link_underline_offset' => ['property' => 'text-underline-offset', 'unit' => 'px', 'kind' => 'number'],
        'link_decoration_thickness' => ['property' => 'text-decoration-thickness', 'unit' => 'px', 'kind' => 'number'],
        'list_indent' => ['property' => 'padding-inline-start', 'unit' => 'px', 'kind' => 'number'],
        'list_line_height' => ['property' => 'line-height', 'unit' => '', 'kind' => 'number'],
        'unordered_list_style' => ['property' => 'list-style-type', 'unit' => '', 'kind' => 'choice'],
        'ordered_list_style' => ['property' => 'list-style-type', 'unit' => '', 'kind' => 'choice'],
        'list_item_gap' => ['property' => 'margin-top', 'unit' => 'px', 'kind' => 'number'],
        'task_checkbox_gap' => ['property' => 'margin-right', 'unit' => 'px', 'kind' => 'number'],
        'quote_margin_block' => ['property' => 'margin-block', 'unit' => 'px', 'kind' => 'number'],
        'quote_border_width' => ['property' => 'border-left-width', 'unit' => 'px', 'kind' => 'number'],
        'quote_border_style' => ['property' => 'border-left-style', 'unit' => '', 'kind' => 'choice'],
        'quote_radius' => ['property' => 'border-radius', 'unit' => 'px', 'kind' => 'number'],
        'quote_padding_block' => ['property' => 'padding-block', 'unit' => 'px', 'kind' => 'number'],
        'quote_padding_inline' => ['property' => 'padding-inline', 'unit' => 'px', 'kind' => 'number'],
        'code_font_size' => ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'],
        'code_line_height' => ['property' => 'line-height', 'unit' => '', 'kind' => 'number'],
        'inline_code_padding_block' => ['property' => 'padding-block', 'unit' => 'px', 'kind' => 'number'],
        'inline_code_padding' => ['property' => 'padding-inline', 'unit' => 'px', 'kind' => 'number'],
        'code_block_padding_block' => ['property' => 'padding-block', 'unit' => 'px', 'kind' => 'number'],
        'code_block_padding_inline' => ['property' => 'padding-inline', 'unit' => 'px', 'kind' => 'number'],
        'code_block_border_width' => ['property' => 'border-width', 'unit' => 'px', 'kind' => 'number'],
        'code_block_radius' => ['property' => 'border-radius', 'unit' => 'px', 'kind' => 'number'],
        'table_width' => ['property' => 'width', 'unit' => '', 'kind' => 'choice'],
        'table_font_size' => ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'],
        'table_line_height' => ['property' => 'line-height', 'unit' => '', 'kind' => 'number'],
        'table_border_width' => ['property' => 'border-width', 'unit' => 'px', 'kind' => 'number'],
        'table_cell_padding_block' => ['property' => 'padding-block', 'unit' => 'px', 'kind' => 'number'],
        'table_cell_padding_inline' => ['property' => 'padding-inline', 'unit' => 'px', 'kind' => 'number'],
        'table_header_weight' => ['property' => 'font-weight', 'unit' => '', 'kind' => 'number'],
        'hr_margin' => ['property' => 'margin-block', 'unit' => 'px', 'kind' => 'number'],
        'hr_width' => ['property' => 'width', 'unit' => '%', 'kind' => 'number'],
        'hr_border_width' => ['property' => 'border-top-width', 'unit' => 'px', 'kind' => 'number'],
        'border_radius' => ['property' => 'border-radius', 'unit' => 'px', 'kind' => 'number'],
        'image_max_width' => ['property' => 'max-width', 'unit' => '%', 'kind' => 'number'],
        'caption_font_size' => ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'],
        'details_padding' => ['property' => 'padding', 'unit' => 'px', 'kind' => 'number'],
        'footnote_font_size' => ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'],
        'alert_border_width' => ['property' => 'border-left-width', 'unit' => 'px', 'kind' => 'number'],
        'alert_padding_block' => ['property' => 'padding-block', 'unit' => 'px', 'kind' => 'number'],
        'alert_padding_inline' => ['property' => 'padding-inline', 'unit' => 'px', 'kind' => 'number'],
        'text_token' => ['property' => 'color', 'unit' => '', 'kind' => 'token'],
        'muted_token' => ['property' => 'color', 'unit' => '', 'kind' => 'token'],
        'border_token' => ['property' => 'border-color', 'unit' => '', 'kind' => 'token'],
        'surface_token' => ['property' => 'background-color', 'unit' => '', 'kind' => 'token'],
        'accent_token' => ['property' => 'color', 'unit' => '', 'kind' => 'token'],
        'heading_token' => ['property' => 'color', 'unit' => '', 'kind' => 'token'],
        'heading_border_token' => ['property' => 'border-color', 'unit' => '', 'kind' => 'token'],
        'quote_token' => ['property' => 'color', 'unit' => '', 'kind' => 'token'],
        'quote_surface_token' => ['property' => 'background-color', 'unit' => '', 'kind' => 'token'],
        'quote_border_token' => ['property' => 'border-color', 'unit' => '', 'kind' => 'token'],
        'code_token' => ['property' => 'color', 'unit' => '', 'kind' => 'token'],
        'code_surface_token' => ['property' => 'background-color', 'unit' => '', 'kind' => 'token'],
        'code_border_token' => ['property' => 'border-color', 'unit' => '', 'kind' => 'token'],
        'table_header_surface_token' => ['property' => 'background-color', 'unit' => '', 'kind' => 'token'],
        'table_border_token' => ['property' => 'border-color', 'unit' => '', 'kind' => 'token'],
        'hr_border_token' => ['property' => 'border-color', 'unit' => '', 'kind' => 'token'],
    ];

    foreach (array_keys(sr_markdown_editor_box_target_definitions()) as $target) {
        foreach (['margin', 'padding', 'border'] as $propertyPrefix) {
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $key = 'box_' . $target . '_' . $propertyPrefix . '_' . $side;
                $property = $propertyPrefix . '-' . $side . ($propertyPrefix === 'border' ? '-width' : '');
                $bindings[$key] = ['property' => $property, 'unit' => 'px', 'kind' => 'number'];
            }
        }
        $bindings['box_' . $target . '_radius'] = ['property' => 'border-radius', 'unit' => 'px', 'kind' => 'number'];
        $bindings['box_' . $target . '_border_style'] = ['property' => 'border-style', 'unit' => '', 'kind' => 'choice'];
        $bindings['box_' . $target . '_border_token'] = ['property' => 'border-color', 'unit' => '', 'kind' => 'token'];
    }
    foreach (array_keys(sr_markdown_editor_text_target_definitions()) as $target) {
        $prefix = 'text_' . $target . '_';
        $bindings[$prefix . 'font_size'] = ['property' => 'font-size', 'unit' => 'px', 'kind' => 'number'];
        $bindings[$prefix . 'font_weight'] = ['property' => 'font-weight', 'unit' => '', 'kind' => 'number'];
        $bindings[$prefix . 'line_height'] = ['property' => 'line-height', 'unit' => '', 'kind' => 'number'];
        $bindings[$prefix . 'letter_spacing'] = ['property' => 'letter-spacing', 'unit' => 'px', 'kind' => 'number'];
        $bindings[$prefix . 'word_spacing'] = ['property' => 'word-spacing', 'unit' => 'px', 'kind' => 'number'];
        $bindings[$prefix . 'align'] = ['property' => 'text-align', 'unit' => '', 'kind' => 'choice'];
        $bindings[$prefix . 'font_style'] = ['property' => 'font-style', 'unit' => '', 'kind' => 'choice'];
        $bindings[$prefix . 'decoration'] = ['property' => 'text-decoration-line', 'unit' => '', 'kind' => 'choice'];
        $bindings[$prefix . 'transform'] = ['property' => 'text-transform', 'unit' => '', 'kind' => 'choice'];
        $bindings[$prefix . 'token'] = ['property' => 'color', 'unit' => '', 'kind' => 'token'];
    }

    return $bindings;
}

function sr_markdown_editor_settings(PDO $pdo, ?array $override = null): array
{
    $settings = array_merge(sr_markdown_editor_default_settings(), $override ?? sr_module_settings($pdo, 'markdown_editor'));
    $settings['tables_enabled'] = true;
    $settings['task_lists_enabled'] = true;
    $settings['code_blocks_enabled'] = true;
    $settings['raw_html_enabled'] = false;
    $settings['style_source_mode'] = (string) ($settings['style_source_mode'] ?? '') === 'default' ? 'default' : 'custom';
    $settings['style_profile_json'] = sr_markdown_editor_normalize_style_profile($settings['style_profile_json'] ?? []);
    $settings['custom_declarations_json'] = sr_markdown_editor_normalize_custom_declarations($settings['custom_declarations_json'] ?? []);
    $stylesheetCss = is_string($settings['stylesheet_css'] ?? null)
        ? trim(sr_markdown_editor_migrate_default_heading_scale(
            sr_markdown_editor_migrate_token_namespace((string) $settings['stylesheet_css'])
        ))
        : '';
    if ($stylesheetCss === '') {
        $stylesheetCss = sr_markdown_editor_default_stylesheet_css(
            $settings['style_profile_json'],
            $settings['custom_declarations_json']
        );
    } elseif ($override === null && sr_markdown_editor_stylesheet_validation_errors($stylesheetCss) !== []) {
        $stylesheetCss = sr_markdown_editor_default_stylesheet_css($settings['style_profile_json']);
    }
    $stylesheetCss = sr_markdown_editor_ensure_token_foundation_stylesheet(
        sr_markdown_editor_remove_font_family_declarations(
            sr_markdown_editor_ensure_box_control_stylesheet(
                sr_markdown_editor_remove_legacy_max_width_control($stylesheetCss)
            )
        )
    );
    $settings['stylesheet_css'] = $stylesheetCss;

    return $settings;
}

function sr_markdown_editor_normalize_style_profile(mixed $profile): array
{
    $profile = is_array($profile) ? $profile : [];
    $defaults = sr_markdown_editor_default_style_profile();
    $tokens = sr_markdown_editor_token_options();
    $choices = sr_markdown_editor_style_choice_options();
    $normalized = [];
    foreach ($defaults as $key => $default) {
        if (is_int($default)) {
            $value = (int) ($profile[$key] ?? $default);
            $limits = [
                'font_size' => [12, 24],
                'content_padding_block' => [0, 96],
                'content_padding_inline' => [0, 96],
                'word_spacing' => [-10, 30],
                'block_gap' => [0, 48],
                'paragraph_margin_top' => [0, 48],
                'paragraph_margin' => [0, 40],
                'paragraph_text_indent' => [0, 80],
                'heading_weight' => [400, 900],
                'heading_margin' => [0, 48],
                'heading_margin_bottom' => [0, 48],
                'heading_padding_bottom' => [0, 32],
                'heading_border_width' => [0, 8],
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
                'task_checkbox_gap' => [0, 16],
                'quote_margin_block' => [0, 64],
                'quote_border_width' => [0, 12],
                'quote_radius' => [0, 32],
                'quote_padding_block' => [0, 32],
                'quote_padding_inline' => [0, 40],
                'code_font_size' => [11, 20],
                'inline_code_padding_block' => [0, 12],
                'inline_code_padding' => [0, 12],
                'code_block_padding_block' => [0, 48],
                'code_block_padding_inline' => [0, 48],
                'code_block_border_width' => [0, 4],
                'code_block_radius' => [0, 32],
                'table_font_size' => [10, 24],
                'table_border_width' => [0, 6],
                'table_cell_padding_block' => [0, 32],
                'table_cell_padding_inline' => [0, 32],
                'table_header_weight' => [400, 900],
                'hr_margin' => [0, 64],
                'hr_width' => [10, 100],
                'hr_border_width' => [0, 6],
                'border_radius' => [0, 16],
                'image_max_width' => [40, 100],
                'caption_font_size' => [10, 20],
                'details_padding' => [0, 32],
                'footnote_font_size' => [10, 18],
                'alert_border_width' => [0, 12],
                'alert_padding_block' => [0, 32],
                'alert_padding_inline' => [0, 40],
            ];
            if (str_starts_with($key, 'text_') && str_ends_with($key, '_font_size')) {
                $limit = [8, 96];
            } elseif (str_starts_with($key, 'text_') && str_ends_with($key, '_font_weight')) {
                $limit = [100, 900];
            } elseif (str_starts_with($key, 'text_') && str_ends_with($key, '_word_spacing')) {
                $limit = [-20, 50];
            } elseif (str_starts_with($key, 'box_') && str_contains($key, '_margin_')) {
                $limit = [-64, 160];
            } elseif (str_starts_with($key, 'box_') && (str_contains($key, '_padding_') || str_contains($key, '_border_') || str_ends_with($key, '_radius'))) {
                $limit = [0, 128];
            } else {
                $limit = $limits[$key] ?? [0, 100];
            }
            $normalized[$key] = min($limit[1], max($limit[0], $value));
            continue;
        }

        if (is_float($default)) {
            $value = (float) ($profile[$key] ?? $default);
            $limits = [
                'heading_line_height' => [1.0, 1.8],
                'code_line_height' => [1.0, 2.0],
                'letter_spacing' => [-2.0, 10.0],
                'paragraph_line_height' => [1.0, 3.0],
                'paragraph_letter_spacing' => [-2.0, 10.0],
                'heading_letter_spacing' => [-3.0, 10.0],
                'list_line_height' => [1.0, 3.0],
                'table_line_height' => [1.0, 3.0],
            ];
            if (str_starts_with($key, 'text_') && str_ends_with($key, '_line_height')) {
                $limit = [0.8, 4.0];
            } elseif (str_starts_with($key, 'text_') && str_ends_with($key, '_letter_spacing')) {
                $limit = [-5.0, 20.0];
            } else {
                $limit = $limits[$key] ?? [1.2, 2.2];
            }
            $normalized[$key] = min($limit[1], max($limit[0], $value));
            continue;
        }

        $candidate = sr_markdown_editor_migrate_token_namespace((string) ($profile[$key] ?? $default));
        if (isset($choices[$key])) {
            $normalized[$key] = isset($choices[$key][$candidate]) ? $candidate : (string) $default;
            continue;
        }
        $normalized[$key] = isset($tokens[$candidate]) ? $candidate : (string) $default;
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

        $rawDeclaration = sr_markdown_editor_migrate_token_namespace(str_replace(["\r\n", "\r"], "\n", $rawDeclaration));
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
            if (preg_match('/\A(?:[-0-9.]+(?:px|rem|em|%)?|[0-9.]+|[a-zA-Z-]+|var\(--md-[a-z-]+\))(?:\s+[-0-9.]+(?:px|rem|em|%)?)*\z/', $value) !== 1) {
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

function sr_markdown_editor_style_selector_map(): array
{
    return [
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
}

function sr_markdown_editor_apply_profile_to_stylesheet(string $css, array $profile): string
{
    $profile = sr_markdown_editor_normalize_style_profile($profile);
    foreach (sr_markdown_editor_style_binding_map() as $key => $binding) {
        if (!array_key_exists($key, $profile)) {
            continue;
        }

        $value = (string) $profile[$key];
        if ((string) ($binding['kind'] ?? '') === 'token') {
            $value = 'var(' . $value . ')';
        } elseif ((string) ($binding['kind'] ?? '') === 'length_or_none' && (int) $profile[$key] === 0) {
            $value = 'none';
        } else {
            $value .= (string) ($binding['unit'] ?? '');
        }

        $pattern = '/(\/\*\s*sr-control:\s*' . preg_quote((string) $key, '/') . '\s*\*\/\s*'
            . preg_quote((string) $binding['property'], '/') . '\s*:\s*)[^;]+;/';
        $updated = preg_replace_callback(
            $pattern,
            static fn (array $matches): string => (string) $matches[1] . $value . ';',
            $css
        );
        if (is_string($updated)) {
            $css = $updated;
        }
    }

    return trim($css);
}

function sr_markdown_editor_remove_legacy_max_width_control(string $css): string
{
    $updated = preg_replace(
        '/\s*\/\*\s*sr-control:\s*max_width\s*\*\/\s*max-width\s*:\s*[^;]+;/',
        '',
        $css
    );
    return is_string($updated) ? trim($updated) : trim($css);
}

function sr_markdown_editor_remove_font_family_declarations(string $css): string
{
    $updated = preg_replace(
        '/\s*\/\*\s*sr-control:\s*[a-z0-9_]*font_family\s*\*\/\s*font-family\s*:\s*[^;{}]+;/i',
        '',
        $css
    );
    $updated = preg_replace('/\s*font-family\s*:\s*[^;{}]+;/i', '', is_string($updated) ? $updated : $css);
    return is_string($updated) ? trim($updated) : trim($css);
}

function sr_markdown_editor_box_control_stylesheet(): string
{
    $lines = ['/* sr-box-controls: common element box model */'];
    $sides = ['top', 'right', 'bottom', 'left'];
    foreach (sr_markdown_editor_box_target_definitions() as $target => $definition) {
        $lines[] = (string) $definition['selector'] . ' {';
        foreach (['margin', 'padding', 'border'] as $property) {
            foreach ($sides as $index => $side) {
                $key = 'box_' . $target . '_' . $property . '_' . $side;
                $cssProperty = $property . '-' . $side . ($property === 'border' ? '-width' : '');
                $lines[] = '    /* sr-control: ' . $key . ' */';
                $lines[] = '    ' . $cssProperty . ': ' . (int) $definition[$property][$index] . 'px;';
            }
        }
        $lines[] = '    /* sr-control: box_' . $target . '_radius */';
        $lines[] = '    border-radius: ' . (int) $definition['radius'] . 'px;';
        $lines[] = '    /* sr-control: box_' . $target . '_border_style */';
        $lines[] = '    border-style: solid;';
        $lines[] = '    /* sr-control: box_' . $target . '_border_token */';
        $lines[] = '    border-color: var(--md-border);';
        $lines[] = '}';
        $lines[] = '';
    }

    return trim(implode("\n", $lines));
}

function sr_markdown_editor_text_control_stylesheet(): string
{
    $lines = ['/* sr-text-controls: common element text style */'];
    foreach (sr_markdown_editor_text_target_definitions() as $target => $definition) {
        $prefix = 'text_' . $target . '_';
        $lines[] = (string) $definition['selector'] . ' {';
        $declarations = [
            'font_size' => ['font-size', (int) $definition['size'] . 'px'],
            'font_weight' => ['font-weight', (int) $definition['weight']],
            'line_height' => ['line-height', (string) $definition['line_height']],
            'letter_spacing' => ['letter-spacing', (string) $definition['letter_spacing'] . 'px'],
            'word_spacing' => ['word-spacing', (int) $definition['word_spacing'] . 'px'],
            'align' => ['text-align', (string) $definition['align']],
            'font_style' => ['font-style', (string) $definition['style']],
            'decoration' => ['text-decoration-line', (string) $definition['decoration']],
            'transform' => ['text-transform', (string) $definition['transform']],
            'token' => ['color', 'var(' . (string) $definition['token'] . ')'],
        ];
        foreach ($declarations as $suffix => $declaration) {
            $lines[] = '    /* sr-control: ' . $prefix . $suffix . ' */';
            $lines[] = '    ' . $declaration[0] . ': ' . $declaration[1] . ';';
        }
        $lines[] = '}';
        $lines[] = '';
    }

    return trim(implode("\n", $lines));
}

function sr_markdown_editor_ensure_box_control_stylesheet(string $css): string
{
    $css = trim($css);
    if (!str_contains($css, 'sr-box-controls: common element box model')) {
        $css .= "\n\n" . sr_markdown_editor_box_control_stylesheet();
    }
    if (!str_contains($css, 'sr-text-controls: common element text style')) {
        $css .= "\n\n" . sr_markdown_editor_text_control_stylesheet();
    }
    $css = preg_replace(
        '/\.markdown-editor-body blockquote\s*\{\s*(\/\*\s*sr-control:\s*text_blockquote_font_size\s*\*\/)/',
        ".markdown-editor-body blockquote, .markdown-editor-body blockquote > p {\n    $1",
        $css
    ) ?? $css;
    return trim($css);
}

function sr_markdown_editor_default_stylesheet_css(?array $profile = null, array $customDeclarations = []): string
{
    $path = SR_ROOT . '/modules/markdown_editor/assets/github-markdown.css';
    $css = is_file($path) ? file_get_contents($path) : '';
    if (!is_string($css) || trim($css) === '') {
        $css = '.markdown-editor-body { --md-text: var(--color-body-color); color: var(--md-text); font-size: 16px; line-height: 1.5; }';
    }

    $css = sr_markdown_editor_apply_profile_to_stylesheet(
        sr_markdown_editor_remove_font_family_declarations(
            sr_markdown_editor_ensure_box_control_stylesheet(sr_markdown_editor_remove_legacy_max_width_control($css))
        ),
        $profile ?? sr_markdown_editor_default_style_profile()
    );
    $selectorMap = sr_markdown_editor_style_selector_map();
    foreach (sr_markdown_editor_normalize_custom_declarations($customDeclarations) as $selectorKey => $declaration) {
        if (isset($selectorMap[$selectorKey])) {
            $css .= "\n\n" . $selectorMap[$selectorKey] . " {\n    " . str_replace('; ', ";\n    ", $declaration) . ";\n}";
        }
    }

    return trim(sr_markdown_editor_ensure_token_foundation_stylesheet(
        sr_markdown_editor_remove_font_family_declarations($css)
    ));
}

function sr_markdown_editor_split_selector_list(string $selectorList): array
{
    $selectors = [];
    $buffer = '';
    $roundDepth = 0;
    $squareDepth = 0;
    $length = strlen($selectorList);
    for ($index = 0; $index < $length; $index++) {
        $character = $selectorList[$index];
        if ($character === '(') {
            $roundDepth++;
        } elseif ($character === ')' && $roundDepth > 0) {
            $roundDepth--;
        } elseif ($character === '[') {
            $squareDepth++;
        } elseif ($character === ']' && $squareDepth > 0) {
            $squareDepth--;
        }

        if ($character === ',' && $roundDepth === 0 && $squareDepth === 0) {
            $selectors[] = trim($buffer);
            $buffer = '';
            continue;
        }
        $buffer .= $character;
    }
    if (trim($buffer) !== '') {
        $selectors[] = trim($buffer);
    }

    return $selectors;
}

function sr_markdown_editor_stylesheet_validation_errors(string $css): array
{
    $errors = [];
    $css = trim($css);
    if ($css === '') {
        return ['CSS 내용을 입력해 주세요.'];
    }
    if (strlen($css) > 100000) {
        return ['CSS 내용은 100,000바이트 이하로 입력해 주세요.'];
    }
    if (preg_match('//u', $css) !== 1) {
        return ['CSS를 올바른 문자 형식(UTF-8)으로 입력해 주세요.'];
    }

    $withoutComments = preg_replace('/\/\*.*?\*\//s', '', $css);
    if (!is_string($withoutComments)) {
        return ['CSS 내용을 확인할 수 없습니다.'];
    }
    if (preg_match('/<\/style/i', $css) === 1
        || preg_match('/@|url\s*\(|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i', $withoutComments) === 1) {
        $errors[] = 'CSS에서는 @로 시작하는 규칙, 외부 파일 주소, 스크립트처럼 실행될 수 있는 표현을 사용할 수 없습니다.';
    }
    if (substr_count($withoutComments, '{') !== substr_count($withoutComments, '}')) {
        $errors[] = 'CSS의 중괄호({ }) 짝이 맞지 않습니다.';
    }
    if (!str_contains($withoutComments, '.markdown-editor-body')) {
        $errors[] = 'CSS 적용 대상은 .markdown-editor-body로 시작해야 합니다.';
    }

    if ($errors === []) {
        preg_match_all('/([^{}]+)\{/', $withoutComments, $matches);
        foreach ((array) ($matches[1] ?? []) as $selectorList) {
            foreach (sr_markdown_editor_split_selector_list(trim((string) $selectorList)) as $selector) {
                if (preg_match('/\A\.markdown-editor-body(?:\b|[:.#\[\s>*+~])/', $selector) !== 1) {
                    $errors[] = '모든 CSS 적용 대상은 .markdown-editor-body로 시작해 Markdown 본문 안에만 적용되어야 합니다: ' . $selector;
                    break 2;
                }
            }
        }
    }

    return $errors;
}

function sr_markdown_editor_settings_from_post(): array
{
    $style = [];
    foreach (sr_markdown_editor_default_style_profile() as $key => $_default) {
        $style[$key] = $_POST['style_profile'][$key] ?? null;
    }

    $normalizedStyle = sr_markdown_editor_normalize_style_profile($style);
    $stylesheetCss = sr_post_string_without_truncation('stylesheet_css', 100000);
    if (is_string($stylesheetCss)) {
        $stylesheetCss = sr_markdown_editor_migrate_token_namespace($stylesheetCss);
        if (trim($stylesheetCss) !== '') {
            $stylesheetCss = sr_markdown_editor_ensure_token_foundation_stylesheet($stylesheetCss);
        }
    }
    return [
        'style_source_mode' => ($_POST['style_source_mode'] ?? '') === 'default' ? 'default' : 'custom',
        'tables_enabled' => true,
        'task_lists_enabled' => true,
        'code_blocks_enabled' => true,
        'raw_html_enabled' => false,
        'style_profile_json' => $normalizedStyle,
        'custom_declarations_json' => [],
        'stylesheet_css' => is_string($stylesheetCss) ? $stylesheetCss : '',
        '_stylesheet_input_valid' => is_string($stylesheetCss),
    ];
}

function sr_markdown_editor_validate_settings(array $settings): array
{
    $errors = [];
    if (!empty($settings['raw_html_enabled'])) {
        $errors[] = 'Markdown 원문에서 HTML 태그를 직접 쓰는 기능은 사용할 수 없습니다.';
    }
    if (!in_array((string) ($settings['style_source_mode'] ?? ''), ['default', 'custom'], true)) {
        $errors[] = '스타일 적용 방식을 확인해 주세요.';
    }
    if (array_key_exists('_stylesheet_input_valid', $settings) && empty($settings['_stylesheet_input_valid'])) {
        $errors[] = 'CSS 내용은 100,000바이트 이하로 입력해 주세요.';
    } else {
        $errors = array_merge($errors, sr_markdown_editor_stylesheet_validation_errors((string) ($settings['stylesheet_css'] ?? '')));
    }

    return $errors;
}

function sr_markdown_editor_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'markdown_editor' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('Markdown 편집기 모듈이 등록되어 있지 않습니다.');
    }

    $rows = [
        ['style_source_mode', (string) ($settings['style_source_mode'] ?? '') === 'default' ? 'default' : 'custom', 'string'],
        ['tables_enabled', '1', 'bool'],
        ['task_lists_enabled', '1', 'bool'],
        ['code_blocks_enabled', '1', 'bool'],
        ['raw_html_enabled', '0', 'bool'],
        ['style_profile_json', sr_markdown_editor_json_encode(sr_markdown_editor_normalize_style_profile($settings['style_profile_json'] ?? [])), 'json'],
        ['custom_declarations_json', '[]', 'json'],
        ['stylesheet_css', trim((string) ($settings['stylesheet_css'] ?? '')), 'text'],
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
    $usesDefaultStyle = (string) ($settings['style_source_mode'] ?? '') === 'default';
    $payload = sr_markdown_editor_json_encode([
        'parser' => [
            'tables' => $settings['tables_enabled'],
            'tasks' => $settings['task_lists_enabled'],
            'code' => $settings['code_blocks_enabled'],
            'raw_html' => false,
        ],
        'style_source_mode' => $settings['style_source_mode'],
        'style' => $usesDefaultStyle ? sr_markdown_editor_default_style_profile() : $settings['style_profile_json'],
        'stylesheet' => $usesDefaultStyle ? sr_markdown_editor_default_stylesheet_css() : $settings['stylesheet_css'],
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
        return sr_markdown_inline_html(preg_replace('/\s+/', ' ', $markdown) ?? $markdown, false);
    }

    $html = [];
    $paragraph = [];
    $listType = '';
    $listItems = [];
    $inCode = false;
    $codeLines = [];

    $flushParagraph = static function () use (&$html, &$paragraph): void {
        if ($paragraph !== []) {
            $html[] = '<p>' . sr_markdown_inline_html(implode("\n", $paragraph), true) . '</p>';
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

    $lines = explode("\n", $markdown);
    $lineCount = count($lines);
    for ($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++) {
        $line = (string) $lines[$lineIndex];
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
            $html[] = '<h' . $level . '>' . sr_markdown_inline_html((string) $matches[2], false) . '</h' . $level . '>';
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
            $html[] = '<blockquote><p>' . sr_markdown_inline_html(substr($trimmed, 2), false) . '</p></blockquote>';
            continue;
        }
        if (!empty($settings['tables_enabled']) && str_contains($trimmed, '|')) {
            $table = sr_markdown_editor_table_html($lines, $lineIndex);
            if (is_array($table)) {
                $flushParagraph();
                $flushList();
                $html[] = (string) $table['html'];
                $lineIndex = (int) $table['last_line_index'];
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
                $item = '<input type="checkbox" disabled' . $checked . '> ' . sr_markdown_inline_html((string) $task[2], false);
            } else {
                $item = sr_markdown_inline_html($item, false);
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
            $listItems[] = sr_markdown_inline_html((string) $matches[1], false);
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

function sr_markdown_editor_table_cells(string $line): ?array
{
    $row = trim($line);
    if (!str_contains($row, '|')) {
        return null;
    }

    if (str_starts_with($row, '|')) {
        $row = substr($row, 1);
    }
    if (str_ends_with($row, '|')) {
        $row = substr($row, 0, -1);
    }

    $cells = array_map('trim', explode('|', $row));
    if (count($cells) < 2) {
        return null;
    }

    return $cells;
}

function sr_markdown_editor_table_delimiter_alignments(array $cells): ?array
{
    $alignments = [];
    foreach ($cells as $cell) {
        $marker = preg_replace('/\s+/', '', (string) $cell) ?? '';
        if (preg_match('/\A:?-{3,}:?\z/', $marker) !== 1) {
            return null;
        }

        $leftAligned = str_starts_with($marker, ':');
        $rightAligned = str_ends_with($marker, ':');
        if ($leftAligned && $rightAligned) {
            $alignments[] = 'center';
        } elseif ($rightAligned) {
            $alignments[] = 'right';
        } elseif ($leftAligned) {
            $alignments[] = 'left';
        } else {
            $alignments[] = '';
        }
    }

    return $alignments;
}

function sr_markdown_editor_table_cell_alignment_attribute(string $alignment): string
{
    return in_array($alignment, ['left', 'center', 'right'], true)
        ? ' style="text-align: ' . $alignment . ';"'
        : '';
}

function sr_markdown_editor_table_html(array $lines, int $startLineIndex): ?array
{
    $headerCells = sr_markdown_editor_table_cells((string) ($lines[$startLineIndex] ?? ''));
    $delimiterCells = sr_markdown_editor_table_cells((string) ($lines[$startLineIndex + 1] ?? ''));
    if ($headerCells === null || $delimiterCells === null || count($headerCells) !== count($delimiterCells)) {
        return null;
    }

    $alignments = sr_markdown_editor_table_delimiter_alignments($delimiterCells);
    if ($alignments === null) {
        return null;
    }

    $head = [];
    foreach ($headerCells as $cellIndex => $cell) {
        $attribute = sr_markdown_editor_table_cell_alignment_attribute((string) ($alignments[$cellIndex] ?? ''));
        $head[] = '<th' . $attribute . '>' . sr_markdown_inline_html((string) $cell, false) . '</th>';
    }

    $body = [];
    $lastLineIndex = $startLineIndex + 1;
    $lineCount = count($lines);
    for ($lineIndex = $startLineIndex + 2; $lineIndex < $lineCount; $lineIndex++) {
        if (trim((string) $lines[$lineIndex]) === '') {
            break;
        }
        $rowCells = sr_markdown_editor_table_cells((string) $lines[$lineIndex]);
        if ($rowCells === null) {
            break;
        }

        $columns = [];
        foreach ($headerCells as $cellIndex => $_headerCell) {
            $attribute = sr_markdown_editor_table_cell_alignment_attribute((string) ($alignments[$cellIndex] ?? ''));
            $columns[] = '<td' . $attribute . '>' . sr_markdown_inline_html((string) ($rowCells[$cellIndex] ?? ''), false) . '</td>';
        }
        $body[] = '<tr>' . implode('', $columns) . '</tr>';
        $lastLineIndex = $lineIndex;
    }

    $bodyHtml = $body === [] ? '' : '<tbody>' . implode('', $body) . '</tbody>';
    return [
        'html' => '<table><thead><tr>' . implode('', $head) . '</tr></thead>' . $bodyHtml . '</table>',
        'last_line_index' => $lastLineIndex,
    ];
}

function sr_markdown_editor_plain_text(string $markdown, array $settings): string
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(sr_markdown_editor_markdown_to_html($markdown, $settings, 'full')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '');
}

function sr_markdown_editor_css(PDO $pdo, ?array $overrideSettings = null): string
{
    $settings = sr_markdown_editor_settings($pdo, $overrideSettings);
    if ((string) ($settings['style_source_mode'] ?? '') === 'default') {
        return sr_markdown_editor_default_stylesheet_css() . "\n";
    }
    return trim((string) ($settings['stylesheet_css'] ?? '')) . "\n";
}

function sr_markdown_editor_sample_markdown(): string
{
    return "# 제목 1\n\n## 제목 2\n\n### 제목 3\n\n#### 제목 4\n\n##### 제목 5\n\n###### 제목 6\n\n본문 문단의 **굵은 글자**, *기울임 글자*, [링크](https://example.com), `inline code`입니다.\n\n- 비순서 목록 항목\n- [x] 완료된 작업\n\n1. 순서 목록 항목\n2. 두 번째 항목\n\n> 인용 문장\n\n---\n\n```\ncode block\n```\n\n| 표 제목 | 값 |\n| --- | --- |\n| 표 내용 | 값 |\n";
}
