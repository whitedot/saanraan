<?php

$adminPageTitle = 'Markdown Editor 설정';
$adminPageSubtitle = '';
$styleProfile = is_array($settings['style_profile_json'] ?? null) ? $settings['style_profile_json'] : sr_markdown_editor_default_style_profile();
$styleDefaults = sr_markdown_editor_default_style_profile();
$styleBindings = sr_markdown_editor_style_binding_map();
$tokenOptions = sr_markdown_editor_token_options();
$choiceOptions = sr_markdown_editor_style_choice_options();
$stylesheetCss = (string) ($settings['stylesheet_css'] ?? sr_markdown_editor_default_stylesheet_css($styleProfile));
$defaultStylesheetCss = sr_markdown_editor_default_stylesheet_css();
$styleSourceMode = (string) ($settings['style_source_mode'] ?? '') === 'default' ? 'default' : 'custom';
$previewSettings = $settings;
if (sr_markdown_editor_stylesheet_validation_errors($stylesheetCss) !== []) {
    $previewSettings['stylesheet_css'] = sr_markdown_editor_default_stylesheet_css($styleProfile);
}
$sampleMarkdown = sr_markdown_editor_sample_markdown();
$sampleResult = sr_markdown_editor_render($pdo, $sampleMarkdown, 'full', ['settings_override' => $previewSettings]);
$sampleCss = sr_markdown_editor_preview_css($pdo, $previewSettings);

$numberControls = [
    'content_padding_block' => ['label' => '상하 안쪽 여백', 'min' => 0, 'max' => 96, 'step' => 1],
    'content_padding_inline' => ['label' => '좌우 안쪽 여백', 'min' => 0, 'max' => 96, 'step' => 1],
    'font_size' => ['label' => '본문 크기', 'min' => 12, 'max' => 24, 'step' => 1],
    'line_height' => ['label' => '본문 줄 높이', 'min' => 1.2, 'max' => 2.2, 'step' => 0.05],
    'letter_spacing' => ['label' => '자간', 'min' => -2, 'max' => 10, 'step' => 0.1],
    'word_spacing' => ['label' => '단어 간격', 'min' => -10, 'max' => 30, 'step' => 1],
    'block_gap' => ['label' => '블록 공통 간격', 'min' => 0, 'max' => 48, 'step' => 1],
    'paragraph_margin_top' => ['label' => '문단 위 여백', 'min' => 0, 'max' => 48, 'step' => 1],
    'paragraph_margin' => ['label' => '문단 아래 여백', 'min' => 0, 'max' => 40, 'step' => 1],
    'paragraph_line_height' => ['label' => '문단 줄 높이', 'min' => 1, 'max' => 3, 'step' => 0.05],
    'paragraph_letter_spacing' => ['label' => '문단 자간', 'min' => -2, 'max' => 10, 'step' => 0.1],
    'paragraph_text_indent' => ['label' => '첫 줄 들여쓰기', 'min' => 0, 'max' => 80, 'step' => 1],
    'strong_weight' => ['label' => '강조 굵기', 'min' => 400, 'max' => 900, 'step' => 100],
    'heading_line_height' => ['label' => '제목 줄 높이', 'min' => 1, 'max' => 1.8, 'step' => 0.05],
    'heading_weight' => ['label' => '제목 굵기', 'min' => 400, 'max' => 900, 'step' => 100],
    'heading_margin' => ['label' => '제목 위 여백', 'min' => 0, 'max' => 48, 'step' => 1],
    'heading_margin_bottom' => ['label' => '제목 아래 여백', 'min' => 0, 'max' => 48, 'step' => 1],
    'heading_padding_bottom' => ['label' => '밑줄 안쪽 간격', 'min' => 0, 'max' => 32, 'step' => 1],
    'heading_border_width' => ['label' => 'H1·H2 밑줄 두께', 'min' => 0, 'max' => 8, 'step' => 1],
    'heading_letter_spacing' => ['label' => '제목 자간', 'min' => -3, 'max' => 10, 'step' => 0.1],
    'h1_size' => ['label' => 'H1 크기', 'min' => 18, 'max' => 56, 'step' => 1],
    'h2_size' => ['label' => 'H2 크기', 'min' => 17, 'max' => 48, 'step' => 1],
    'h3_size' => ['label' => 'H3 크기', 'min' => 16, 'max' => 40, 'step' => 1],
    'h4_size' => ['label' => 'H4 크기', 'min' => 15, 'max' => 32, 'step' => 1],
    'h5_size' => ['label' => 'H5 크기', 'min' => 14, 'max' => 28, 'step' => 1],
    'h6_size' => ['label' => 'H6 크기', 'min' => 13, 'max' => 24, 'step' => 1],
    'link_weight' => ['label' => '링크 굵기', 'min' => 400, 'max' => 900, 'step' => 100],
    'link_underline_offset' => ['label' => '밑줄 간격', 'min' => 0, 'max' => 8, 'step' => 1],
    'link_decoration_thickness' => ['label' => '밑줄 두께', 'min' => 1, 'max' => 4, 'step' => 1],
    'list_indent' => ['label' => '목록 들여쓰기', 'min' => 12, 'max' => 48, 'step' => 1],
    'list_line_height' => ['label' => '목록 줄 높이', 'min' => 1, 'max' => 3, 'step' => 0.05],
    'list_item_gap' => ['label' => '항목 간격', 'min' => 0, 'max' => 20, 'step' => 1],
    'task_checkbox_gap' => ['label' => '체크박스 간격', 'min' => 0, 'max' => 16, 'step' => 1],
    'quote_margin_block' => ['label' => '상하 바깥 여백', 'min' => 0, 'max' => 64, 'step' => 1],
    'quote_border_width' => ['label' => '인용선 너비', 'min' => 0, 'max' => 12, 'step' => 1],
    'quote_radius' => ['label' => '둥근 모서리', 'min' => 0, 'max' => 32, 'step' => 1],
    'quote_padding_block' => ['label' => '상하 안쪽 여백', 'min' => 0, 'max' => 32, 'step' => 1],
    'quote_padding_inline' => ['label' => '좌우 안쪽 여백', 'min' => 0, 'max' => 40, 'step' => 1],
    'code_font_size' => ['label' => '코드 크기', 'min' => 11, 'max' => 20, 'step' => 1],
    'code_line_height' => ['label' => '코드 줄 높이', 'min' => 1, 'max' => 2, 'step' => 0.05],
    'inline_code_padding_block' => ['label' => '상하 안쪽 여백', 'min' => 0, 'max' => 12, 'step' => 1],
    'inline_code_padding' => ['label' => '좌우 안쪽 여백', 'min' => 0, 'max' => 12, 'step' => 1],
    'border_radius' => ['label' => '둥근 모서리', 'min' => 0, 'max' => 16, 'step' => 1],
    'code_block_padding_block' => ['label' => '상하 안쪽 여백', 'min' => 0, 'max' => 48, 'step' => 1],
    'code_block_padding_inline' => ['label' => '좌우 안쪽 여백', 'min' => 0, 'max' => 48, 'step' => 1],
    'code_block_border_width' => ['label' => '경계선 두께', 'min' => 0, 'max' => 4, 'step' => 1],
    'code_block_radius' => ['label' => '둥근 모서리', 'min' => 0, 'max' => 32, 'step' => 1],
    'table_font_size' => ['label' => '표 글자 크기', 'min' => 10, 'max' => 24, 'step' => 1],
    'table_line_height' => ['label' => '표 줄 높이', 'min' => 1, 'max' => 3, 'step' => 0.05],
    'table_border_width' => ['label' => '셀 경계선', 'min' => 0, 'max' => 6, 'step' => 1],
    'table_cell_padding_block' => ['label' => '셀 상하 여백', 'min' => 0, 'max' => 32, 'step' => 1],
    'table_cell_padding_inline' => ['label' => '셀 좌우 여백', 'min' => 0, 'max' => 32, 'step' => 1],
    'table_header_weight' => ['label' => '헤더 굵기', 'min' => 400, 'max' => 900, 'step' => 100],
    'hr_margin' => ['label' => '상하 간격', 'min' => 0, 'max' => 64, 'step' => 1],
    'hr_width' => ['label' => '구분선 너비', 'min' => 10, 'max' => 100, 'step' => 1],
    'hr_border_width' => ['label' => '선 두께', 'min' => 0, 'max' => 6, 'step' => 1],
];

$choiceControls = [
    'font_family' => '글꼴',
    'text_align' => '본문 정렬',
    'heading_font_family' => '제목 글꼴',
    'heading_text_align' => '제목 정렬',
    'heading_text_transform' => '대소문자 변환',
    'link_decoration' => '링크 장식',
    'unordered_list_style' => '비순서 기호',
    'ordered_list_style' => '순서 기호',
    'quote_border_style' => '인용선 스타일',
    'code_font_family' => '코드 글꼴',
    'table_width' => '표 너비',
];

$tokenControls = [
    'text_token' => '본문 색',
    'muted_token' => '보조 색',
    'border_token' => '경계선 색',
    'surface_token' => '표면 색',
    'accent_token' => '강조 색',
    'heading_token' => '제목 색',
    'heading_border_token' => '제목 밑줄 색',
    'quote_token' => '인용 글자색',
    'quote_surface_token' => '인용 표면색',
    'quote_border_token' => '인용선 색',
    'code_token' => '코드 글자색',
    'code_surface_token' => '코드 표면색',
    'code_border_token' => '코드 경계선 색',
    'table_header_surface_token' => '헤더 표면색',
    'table_border_token' => '표 경계선 색',
    'hr_border_token' => '구분선 색',
];

$inspectorPanels = [
    'global' => ['title' => '전체 스타일', 'selector' => '.markdown-editor-body', 'description' => '문서 전체가 상속하는 기본 프레임입니다.', 'groups' => [
        ['title' => 'Layout', 'controls' => ['content_padding_block', 'content_padding_inline']],
        ['title' => 'Typography', 'choices' => ['font_family', 'text_align'], 'controls' => ['font_size', 'line_height', 'letter_spacing', 'word_spacing']],
        ['title' => 'Spacing', 'controls' => ['block_gap']],
        ['title' => 'Fill & Stroke', 'tokens' => ['text_token', 'muted_token', 'surface_token', 'border_token']],
    ]],
    'paragraph' => ['title' => '문단과 강조', 'selector' => 'p, strong, em', 'description' => '문단의 활자와 흐름을 독립적으로 조정합니다.', 'groups' => [
        ['title' => 'Paragraph details', 'controls' => ['paragraph_text_indent', 'strong_weight']],
    ]],
    'h1' => ['title' => '제목 H1', 'selector' => 'h1', 'description' => 'H1의 박스와 텍스트를 독립적으로 조정합니다.', 'groups' => []],
    'h2' => ['title' => '제목 H2', 'selector' => 'h2', 'description' => 'H2의 박스와 텍스트를 독립적으로 조정합니다.', 'groups' => []],
    'h3' => ['title' => '제목 H3', 'selector' => 'h3', 'description' => 'H3의 박스와 텍스트를 독립적으로 조정합니다.', 'groups' => []],
    'h4' => ['title' => '제목 H4', 'selector' => 'h4', 'description' => 'H4의 박스와 텍스트를 독립적으로 조정합니다.', 'groups' => []],
    'h5' => ['title' => '제목 H5', 'selector' => 'h5', 'description' => 'H5의 박스와 텍스트를 독립적으로 조정합니다.', 'groups' => []],
    'h6' => ['title' => '제목 H6', 'selector' => 'h6', 'description' => 'H6의 박스와 텍스트를 독립적으로 조정합니다.', 'groups' => []],
    'link' => ['title' => '링크', 'selector' => 'a', 'description' => '링크의 활자, 장식, 색을 조정합니다.', 'groups' => [
        ['title' => 'Link details', 'controls' => ['link_underline_offset', 'link_decoration_thickness']],
    ]],
    'list' => ['title' => '목록과 작업 목록', 'selector' => 'ul, ol, li', 'description' => '기호, 들여쓰기, 행간을 조정합니다.', 'groups' => [
        ['title' => 'Markers', 'choices' => ['unordered_list_style', 'ordered_list_style']],
        ['title' => 'List items', 'controls' => ['list_item_gap', 'task_checkbox_gap']],
    ]],
    'blockquote' => ['title' => '인용', 'selector' => 'blockquote', 'description' => '인용 블록의 박스 모델과 표면입니다.', 'groups' => [
        ['title' => 'Fill', 'tokens' => ['quote_surface_token']],
    ]],
    'inline_code' => ['title' => '인라인 코드', 'selector' => 'code, tt', 'description' => '문장 안 코드의 활자와 칩 표면입니다.', 'groups' => [
        ['title' => 'Fill', 'tokens' => ['code_surface_token']],
    ]],
    'code_block' => ['title' => '코드 블록', 'selector' => 'pre > code', 'description' => '여러 줄 코드의 박스와 활자입니다.', 'groups' => [
        ['title' => 'Fill', 'tokens' => ['code_surface_token']],
    ]],
    'table' => ['title' => '표', 'selector' => 'table, th, td', 'description' => '표의 크기, 셀 밀도, 정렬과 표면입니다.', 'groups' => [
        ['title' => 'Layout', 'choices' => ['table_width']],
        ['title' => 'Header', 'controls' => ['table_header_weight']],
        ['title' => 'Cells', 'controls' => ['table_cell_padding_block', 'table_cell_padding_inline', 'table_border_width'], 'tokens' => ['table_header_surface_token', 'table_border_token']],
    ]],
    'hr' => ['title' => '구분선', 'selector' => 'hr', 'description' => '구분선의 크기와 선 모양입니다.', 'groups' => [
        ['title' => 'Layout', 'controls' => ['hr_width']],
    ]],
];

$boxLabels = [
    'margin' => ['top' => '위 마진', 'right' => '오른쪽 마진', 'bottom' => '아래 마진', 'left' => '왼쪽 마진'],
    'padding' => ['top' => '위 패딩', 'right' => '오른쪽 패딩', 'bottom' => '아래 패딩', 'left' => '왼쪽 패딩'],
    'border' => ['top' => '위 테두리', 'right' => '오른쪽 테두리', 'bottom' => '아래 테두리', 'left' => '왼쪽 테두리'],
];
foreach (array_keys(sr_markdown_editor_box_target_definitions()) as $target) {
    if (!isset($inspectorPanels[$target])) {
        continue;
    }
    $boxGroups = [];
    foreach (['margin' => 'Margin', 'padding' => 'Padding', 'border' => 'Border'] as $property => $title) {
        $keys = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $key = 'box_' . $target . '_' . $property . '_' . $side;
            $numberControls[$key] = [
                'label' => $boxLabels[$property][$side] . ($property === 'border' ? ' 두께' : ''),
                'min' => $property === 'margin' ? -64 : 0,
                'max' => $property === 'border' ? 16 : ($property === 'margin' ? 160 : 128),
                'step' => 1,
            ];
            $keys[] = $key;
        }
        $group = ['title' => $title, 'controls' => $keys, 'open' => true, 'box_property' => $property];
        if ($property === 'border') {
            $radiusKey = 'box_' . $target . '_radius';
            $styleKey = 'box_' . $target . '_border_style';
            $tokenKey = 'box_' . $target . '_border_token';
            $numberControls[$radiusKey] = ['label' => '모서리 반경', 'min' => 0, 'max' => 64, 'step' => 1];
            $choiceControls[$styleKey] = '테두리 스타일';
            $tokenControls[$tokenKey] = '테두리 색';
            $group['controls'][] = $radiusKey;
            $group['choices'] = [$styleKey];
            $group['tokens'] = [$tokenKey];
        }
        $boxGroups[] = $group;
    }
    $inspectorPanels[$target]['groups'] = array_merge($boxGroups, (array) $inspectorPanels[$target]['groups']);
}
foreach (array_keys(sr_markdown_editor_text_target_definitions()) as $target) {
    if (!isset($inspectorPanels[$target])) {
        continue;
    }
    $prefix = 'text_' . $target . '_';
    $numberControls[$prefix . 'font_size'] = ['label' => '글자 크기', 'min' => 8, 'max' => 96, 'step' => 1];
    $numberControls[$prefix . 'font_weight'] = ['label' => '글자 굵기', 'min' => 100, 'max' => 900, 'step' => 100];
    $numberControls[$prefix . 'line_height'] = ['label' => '줄 높이', 'min' => 0.8, 'max' => 4, 'step' => 0.05];
    $numberControls[$prefix . 'letter_spacing'] = ['label' => '자간', 'min' => -5, 'max' => 20, 'step' => 0.1];
    $numberControls[$prefix . 'word_spacing'] = ['label' => '단어 간격', 'min' => -20, 'max' => 50, 'step' => 1];
    $choiceControls[$prefix . 'font_family'] = '글꼴';
    $choiceControls[$prefix . 'align'] = '정렬';
    $choiceControls[$prefix . 'font_style'] = '글자 스타일';
    $choiceControls[$prefix . 'decoration'] = '텍스트 장식';
    $choiceControls[$prefix . 'transform'] = '대소문자 변환';
    $tokenControls[$prefix . 'token'] = '글자색';
    $textGroup = [
        'title' => 'Text',
        'open' => true,
        'text_property' => true,
        'choices' => [$prefix . 'font_family', $prefix . 'align', $prefix . 'font_style', $prefix . 'decoration', $prefix . 'transform'],
        'controls' => [$prefix . 'font_size', $prefix . 'font_weight', $prefix . 'line_height', $prefix . 'letter_spacing', $prefix . 'word_spacing'],
        'tokens' => [$prefix . 'token'],
    ];
    array_splice($inspectorPanels[$target]['groups'], 3, 0, [$textGroup]);
}

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/markdown-editor/settings')); ?>" class="admin-form ui-form-theme markdown-editor-settings-form" data-markdown-editor-settings-form>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">
    <style data-markdown-editor-preview-style><?php echo $sampleCss; ?></style>
    <textarea hidden data-markdown-default-stylesheet><?php echo sr_e($defaultStylesheetCss); ?></textarea>

    <div class="markdown-editor-workbench" data-markdown-workbench>
        <main class="markdown-editor-live-main">
            <header class="markdown-editor-live-toolbar">
                <div>
                    <h2>Markdown 편집기</h2>
                    <p class="form-help">원문을 편집하고 결과 요소를 클릭해 속성을 조정합니다.</p>
                </div>
                <div class="markdown-editor-live-actions">
                    <div class="markdown-editor-preview-controls" aria-label="미리보기 표시 설정">
                        <div class="markdown-editor-segmented" aria-label="색상 모드">
                            <button type="button" class="btn btn-sm active" data-markdown-scheme="light" aria-pressed="true">Light</button>
                            <button type="button" class="btn btn-sm" data-markdown-scheme="dark" aria-pressed="false">Dark</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-icon" aria-label="CSS 확인" title="CSS 확인" aria-haspopup="dialog" aria-expanded="false" aria-controls="markdown_editor_css_modal" data-overlay="#markdown_editor_css_modal">
                        <?php echo sr_material_icon_html('css'); ?>
                    </button>
                </div>
            </header>

            <div class="markdown-editor-live-surface" data-markdown-editor-surface data-view-mode="split">
                <section class="markdown-editor-source-pane">
                    <header>
                        <strong>Markdown 원문</strong>
                        <button type="button" class="btn btn-sm btn-icon markdown-editor-pane-toggle" data-markdown-pane-toggle="editor" aria-label="편집 영역만 펼치기" title="편집 영역만 펼치기" aria-pressed="false">
                            <?php echo sr_material_icon_html('right_panel_close'); ?>
                        </button>
                    </header>
                    <textarea name="markdown" maxlength="100000" spellcheck="false" class="form-textarea markdown-editor-source" data-markdown-source><?php echo sr_e($sampleMarkdown); ?></textarea>
                </section>
                <section class="markdown-editor-render-pane" data-color-scheme="light" data-markdown-render-pane>
                    <header>
                        <strong>렌더링 결과</strong>
                        <span class="markdown-editor-pane-header-actions">
                            <span class="form-help" data-markdown-selected-label>전체 스타일</span>
                            <button type="button" class="btn btn-sm btn-icon markdown-editor-pane-toggle" data-markdown-pane-toggle="preview" aria-label="미리보기만 펼치기" title="미리보기만 펼치기" aria-pressed="false">
                                <?php echo sr_material_icon_html('left_panel_close'); ?>
                            </button>
                        </span>
                    </header>
                    <div class="markdown-editor-context-toolbar" data-markdown-context-toolbar>
                        <div class="markdown-editor-context-toolbar-groups" data-markdown-toolbar-groups role="toolbar" aria-label="선택 요소 속성"></div>
                        <div id="markdown_editor_context_toolbar_menu" class="markdown-editor-context-toolbar-controls" data-markdown-toolbar-controls hidden></div>
                    </div>
                    <div class="markdown-editor-render-canvas" data-markdown-rendered-preview><?php echo (string) ($sampleResult['html'] ?? ''); ?></div>
                </section>
            </div>
        </main>

    </div>

    <footer class="markdown-editor-page-actions">
        <div>
            <p class="form-help markdown-editor-preview-status" data-markdown-editor-preview-status aria-live="polite"></p>
            <p class="form-help markdown-editor-reference">참고 프로젝트: <a href="https://github.com/sindresorhus/github-markdown-css" target="_blank" rel="noopener noreferrer">github-markdown-css</a> 5.9.0 (MIT)</p>
        </div>
        <button type="submit" class="btn btn-solid-primary">설정 저장</button>
    </footer>

    <div id="markdown_editor_css_modal" class="modal-overlay modal-overlay-fade overlay overlay-closed" role="dialog" aria-modal="true" aria-hidden="true" inert tabindex="-1" aria-labelledby="markdown_editor_css_modal_label">
        <div class="modal-dialog-fluid">
            <div class="modal-content-fullscreen modal-radius-md markdown-editor-css-modal">
                <div class="modal-header">
                    <div>
                        <h3 id="markdown_editor_css_modal_label" class="modal-title">CSS 확인</h3>
                        <p class="form-help">적용할 스타일시트를 확인하고 직접 편집합니다.</p>
                    </div>
                    <div class="markdown-editor-css-modal-actions">
                        <button type="button" class="btn btn-sm" data-markdown-reset-all>전체 초기화</button>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#markdown_editor_css_modal">
                            <span class="sr-only">닫기</span>
                            <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                        </button>
                    </div>
                </div>

                <div class="modal-body-fill markdown-editor-css-modal-body">
                    <div class="markdown-editor-css-modal-content">

            <div class="markdown-editor-style-source" data-markdown-style-source>
                <span class="form-label">스타일 적용</span>
                <div class="markdown-editor-source-options">
                    <label class="markdown-editor-source-option">
                        <input type="radio" name="style_source_mode" value="default"<?php echo $styleSourceMode === 'default' ? ' checked' : ''; ?> data-markdown-style-source-mode>
                        <span><strong>참조 원본</strong><small>번들 기본 CSS</small></span>
                    </label>
                    <label class="markdown-editor-source-option">
                        <input type="radio" name="style_source_mode" value="custom"<?php echo $styleSourceMode === 'custom' ? ' checked' : ''; ?> data-markdown-style-source-mode>
                        <span><strong>변경값</strong><small>현재 작업 CSS</small></span>
                    </label>
                </div>
                <p class="form-help" data-markdown-style-source-help><?php echo $styleSourceMode === 'default' ? '원본을 출력 중입니다. 속성을 바꾸면 변경값으로 전환됩니다.' : '변경한 스타일시트를 출력 중입니다.'; ?></p>
            </div>

                <div hidden data-markdown-control-templates>
                    <select hidden data-markdown-inspector-target aria-hidden="true" tabindex="-1">
                        <?php foreach ($inspectorPanels as $panelKey => $panel) { ?><option value="<?php echo sr_e($panelKey); ?>"><?php echo sr_e((string) $panel['title']); ?></option><?php } ?>
                    </select>

                    <?php foreach ($inspectorPanels as $panelKey => $panel) { ?>
                    <section class="markdown-editor-inspector-panel" data-markdown-inspector-panel="<?php echo sr_e($panelKey); ?>"<?php echo $panelKey === 'global' ? '' : ' hidden'; ?>>
                        <header><div><h3><?php echo sr_e((string) $panel['title']); ?></h3><code><?php echo sr_e((string) $panel['selector']); ?></code></div><button type="button" class="btn btn-sm" data-markdown-reset-target="<?php echo sr_e($panelKey); ?>">Reset</button></header>
                        <p class="form-help"><?php echo sr_e((string) $panel['description']); ?></p>
                        <?php if (isset($panel['inherits'])) { ?><button type="button" class="btn btn-sm markdown-editor-inheritance" data-markdown-select-target="<?php echo sr_e((string) $panel['inherits']); ?>">제목 공통 속성에서 상속 중</button><?php } ?>

                        <?php foreach ((array) $panel['groups'] as $groupIndex => $group) { ?>
                            <details class="markdown-editor-property-group<?php echo isset($group['box_property']) ? ' markdown-editor-box-group' : ''; ?><?php echo !empty($group['text_property']) ? ' markdown-editor-text-group' : ''; ?>"<?php echo isset($group['box_property']) ? ' data-markdown-box-group="' . sr_e((string) $group['box_property']) . '"' : ''; ?><?php echo !empty($group['open']) || $groupIndex < 2 ? ' open' : ''; ?>>
                                <summary><?php echo sr_e((string) $group['title']); ?></summary>
                                <div class="markdown-editor-inspector-fields">
                                    <?php foreach ((array) ($group['choices'] ?? []) as $key) {
                                        $binding = $styleBindings[$key];
                                        $value = (string) ($styleProfile[$key] ?? $styleDefaults[$key]);
                                        $controlId = 'markdown_editor_choice_' . $panelKey . '_' . $key;
                                    ?>
                                        <div class="markdown-editor-property markdown-editor-choice-property">
                                            <label class="form-label" for="<?php echo sr_e($controlId); ?>"><?php echo sr_e($choiceControls[$key]); ?></label>
                                            <select id="<?php echo sr_e($controlId); ?>" name="style_profile[<?php echo sr_e($key); ?>]" class="form-select" data-markdown-style-key="<?php echo sr_e($key); ?>" data-markdown-style-property="<?php echo sr_e((string) $binding['property']); ?>" data-markdown-style-kind="choice" data-default-value="<?php echo sr_e((string) $styleDefaults[$key]); ?>">
                                                <?php foreach ($choiceOptions[$key] as $choiceValue => $choiceLabel) { ?><option value="<?php echo sr_e((string) $choiceValue); ?>"<?php echo $value === (string) $choiceValue ? ' selected' : ''; ?>><?php echo sr_e((string) $choiceLabel); ?></option><?php } ?>
                                            </select>
                                        </div>
                                    <?php } ?>

                                    <?php foreach ((array) ($group['controls'] ?? []) as $key) {
                                        $control = $numberControls[$key];
                                        $binding = $styleBindings[$key];
                                        $value = (string) ($styleProfile[$key] ?? $styleDefaults[$key]);
                                        $controlId = 'markdown_editor_style_' . $panelKey . '_' . $key;
                                        $unitLabel = (string) $binding['unit'] !== '' ? (string) $binding['unit'] : '×';
                                    ?>
                                        <div class="markdown-editor-property">
                                            <label class="form-label" for="<?php echo sr_e($controlId); ?>"><?php echo sr_e((string) $control['label']); ?></label>
                                            <div class="markdown-editor-control-pair">
                                                <input id="<?php echo sr_e($controlId); ?>" type="range" name="style_profile[<?php echo sr_e($key); ?>]" min="<?php echo sr_e((string) $control['min']); ?>" max="<?php echo sr_e((string) $control['max']); ?>" step="<?php echo sr_e((string) $control['step']); ?>" value="<?php echo sr_e($value); ?>" data-markdown-style-key="<?php echo sr_e($key); ?>" data-markdown-style-property="<?php echo sr_e((string) $binding['property']); ?>" data-markdown-style-unit="<?php echo sr_e((string) $binding['unit']); ?>" data-markdown-style-kind="<?php echo sr_e((string) $binding['kind']); ?>" data-default-value="<?php echo sr_e((string) $styleDefaults[$key]); ?>">
                                                <span class="markdown-editor-number-field"><input type="number" name="style_profile[<?php echo sr_e($key); ?>]" min="<?php echo sr_e((string) $control['min']); ?>" max="<?php echo sr_e((string) $control['max']); ?>" step="<?php echo sr_e((string) $control['step']); ?>" value="<?php echo sr_e($value); ?>" class="form-input" data-markdown-style-key="<?php echo sr_e($key); ?>" data-markdown-style-property="<?php echo sr_e((string) $binding['property']); ?>" data-markdown-style-unit="<?php echo sr_e((string) $binding['unit']); ?>" data-markdown-style-kind="<?php echo sr_e((string) $binding['kind']); ?>" data-default-value="<?php echo sr_e((string) $styleDefaults[$key]); ?>"><small><?php echo sr_e($unitLabel); ?></small></span>
                                            </div>
                                        </div>
                                    <?php } ?>

                                    <?php foreach ((array) ($group['tokens'] ?? []) as $key) {
                                        $binding = $styleBindings[$key];
                                        $controlId = 'markdown_editor_token_' . $panelKey . '_' . $key;
                                    ?>
                                        <div class="markdown-editor-property markdown-editor-token-property">
                                            <label class="form-label" for="<?php echo sr_e($controlId); ?>"><?php echo sr_e($tokenControls[$key]); ?></label>
                                            <select id="<?php echo sr_e($controlId); ?>" name="style_profile[<?php echo sr_e($key); ?>]" class="form-select" data-markdown-style-key="<?php echo sr_e($key); ?>" data-markdown-style-property="<?php echo sr_e((string) $binding['property']); ?>" data-markdown-style-kind="token" data-default-value="<?php echo sr_e((string) $styleDefaults[$key]); ?>">
                                                <?php foreach ($tokenOptions as $token => $tokenLabel) { ?><option value="<?php echo sr_e($token); ?>"<?php echo (string) ($styleProfile[$key] ?? '') === (string) $token ? ' selected' : ''; ?>><?php echo sr_e($tokenLabel . ' · ' . $token); ?></option><?php } ?>
                                            </select>
                                        </div>
                                    <?php } ?>
                                </div>
                            </details>
                        <?php } ?>
                    </section>
                    <?php } ?>
                </div>

                        <details class="markdown-editor-sidebar-section markdown-editor-stylesheet-section" open>
                <summary>CSS 원문</summary>
                <div>
                    <label class="form-label" for="markdown_editor_stylesheet_css">전체 스타일시트 <span class="sr-required-label">(필수)</span></label>
                    <textarea id="markdown_editor_stylesheet_css" name="stylesheet_css" rows="34" maxlength="100000" class="form-textarea form-control-full markdown-editor-stylesheet" spellcheck="false" required data-markdown-stylesheet><?php echo sr_e($stylesheetCss); ?></textarea>
                    <p class="form-help markdown-editor-css-preview-status" data-markdown-css-preview-status aria-live="polite">CSS를 편집하면 서버 검증 후 미리보기에 자동 반영됩니다.</p>
                    <p class="form-help">상단 툴바는 <code>sr-control</code>이 표시된 실제 선언을 변경합니다.</p>
                    <p class="form-help">모든 selector는 <code>.markdown-editor-body</code> 범위 안에 있어야 합니다.</p>
                </div>
            </details>

                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="<?php echo sr_e(sr_asset_url('/modules/markdown_editor/assets/admin.js')); ?>" defer></script>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
