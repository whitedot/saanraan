<?php

$adminPageTitle = 'Markdown Editor 설정';
$adminPageSubtitle = '';
$styleProfile = is_array($settings['style_profile_json'] ?? null) ? $settings['style_profile_json'] : sr_markdown_editor_default_style_profile();
$customDeclarations = is_array($settings['custom_declarations_json'] ?? null) ? $settings['custom_declarations_json'] : [];
$tokenOptions = sr_markdown_editor_token_options();
$selectorOptions = sr_markdown_editor_style_selector_options();
$sampleResult = sr_markdown_editor_render($pdo, sr_markdown_editor_sample_markdown(), 'full');
$sampleCss = sr_markdown_editor_css($pdo);
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/markdown-editor/settings')); ?>" class="admin-form ui-form-theme" data-markdown-editor-settings-form>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section class="card">
        <h2>파서 프로파일</h2>
        <div class="form-row">
            <label class="form-label">확장 문법</label>
            <div class="form-field">
                <?php echo sr_admin_switch_html('markdown_editor_tables_enabled', 'tables_enabled', '1', !empty($settings['tables_enabled']), '표'); ?>
                <?php echo sr_admin_switch_html('markdown_editor_task_lists_enabled', 'task_lists_enabled', '1', !empty($settings['task_lists_enabled']), '작업 목록'); ?>
                <?php echo sr_admin_switch_html('markdown_editor_code_blocks_enabled', 'code_blocks_enabled', '1', !empty($settings['code_blocks_enabled']), '코드 블록'); ?>
                <p class="form-help">raw HTML은 v1에서 저장할 수 없으며, 마크다운 원문은 escape 기반 렌더링 뒤 프로파일 스타일만 적용됩니다.</p>
            </div>
        </div>
    </section>

    <?php
    $numberControls = [
        'max_width' => ['label' => '최대 폭', 'min' => 0, 'max' => 1200, 'step' => 10],
        'font_size' => ['label' => '본문 크기', 'min' => 12, 'max' => 24, 'step' => 1],
        'line_height' => ['label' => '줄 높이', 'min' => 1.2, 'max' => 2.2, 'step' => 0.1],
        'block_gap' => ['label' => '블록 간격', 'min' => 0, 'max' => 48, 'step' => 1],
        'paragraph_margin' => ['label' => '문단 간격', 'min' => 0, 'max' => 40, 'step' => 1],
        'heading_line_height' => ['label' => '제목 줄 높이', 'min' => 1, 'max' => 1.8, 'step' => 0.05],
        'heading_weight' => ['label' => '제목 굵기', 'min' => 400, 'max' => 900, 'step' => 100],
        'heading_margin' => ['label' => '제목 간격', 'min' => 0, 'max' => 48, 'step' => 1],
        'h1_size' => ['label' => '제목 1 크기', 'min' => 18, 'max' => 56, 'step' => 1],
        'h2_size' => ['label' => '제목 2 크기', 'min' => 17, 'max' => 48, 'step' => 1],
        'h3_size' => ['label' => '제목 3 크기', 'min' => 16, 'max' => 40, 'step' => 1],
        'h4_size' => ['label' => '제목 4 크기', 'min' => 15, 'max' => 32, 'step' => 1],
        'h5_size' => ['label' => '제목 5 크기', 'min' => 14, 'max' => 28, 'step' => 1],
        'h6_size' => ['label' => '제목 6 크기', 'min' => 13, 'max' => 24, 'step' => 1],
        'strong_weight' => ['label' => '굵은 글자 굵기', 'min' => 400, 'max' => 900, 'step' => 100],
        'link_weight' => ['label' => '링크 굵기', 'min' => 400, 'max' => 900, 'step' => 100],
        'link_underline_offset' => ['label' => '밑줄 간격', 'min' => 0, 'max' => 8, 'step' => 1],
        'link_decoration_thickness' => ['label' => '밑줄 두께', 'min' => 1, 'max' => 4, 'step' => 1],
        'list_indent' => ['label' => '목록 들여쓰기', 'min' => 12, 'max' => 48, 'step' => 1],
        'list_item_gap' => ['label' => '목록 항목 간격', 'min' => 0, 'max' => 20, 'step' => 1],
        'quote_border_width' => ['label' => '인용선 너비', 'min' => 0, 'max' => 12, 'step' => 1],
        'quote_padding_block' => ['label' => '인용 상하 여백', 'min' => 0, 'max' => 32, 'step' => 1],
        'quote_padding_inline' => ['label' => '인용 좌우 여백', 'min' => 0, 'max' => 40, 'step' => 1],
        'code_font_size' => ['label' => '코드 크기', 'min' => 11, 'max' => 20, 'step' => 1],
        'code_line_height' => ['label' => '코드 줄 높이', 'min' => 1, 'max' => 2, 'step' => 0.05],
        'inline_code_padding' => ['label' => '인라인 코드 여백', 'min' => 0, 'max' => 12, 'step' => 1],
        'code_block_padding' => ['label' => '코드 블록 여백', 'min' => 0, 'max' => 32, 'step' => 1],
        'code_block_border_width' => ['label' => '코드 블록 선 너비', 'min' => 0, 'max' => 4, 'step' => 1],
        'table_border_width' => ['label' => '표 경계선 너비', 'min' => 0, 'max' => 6, 'step' => 1],
        'table_cell_padding' => ['label' => '표 셀 여백', 'min' => 4, 'max' => 24, 'step' => 1],
        'table_header_weight' => ['label' => '표 헤더 굵기', 'min' => 400, 'max' => 900, 'step' => 100],
        'hr_margin' => ['label' => '구분선 간격', 'min' => 0, 'max' => 64, 'step' => 1],
        'hr_border_width' => ['label' => '구분선 두께', 'min' => 0, 'max' => 6, 'step' => 1],
        'border_radius' => ['label' => '둥근 모서리', 'min' => 0, 'max' => 16, 'step' => 1],
    ];
    $tokenControls = [
        'text_token' => '본문 색',
        'muted_token' => '보조 색',
        'border_token' => '경계선 색',
        'surface_token' => '표면 색',
        'accent_token' => '강조 색',
        'heading_token' => '제목 색',
        'quote_token' => '인용 색',
        'code_token' => '코드 글자색',
        'code_surface_token' => '코드 표면색',
        'table_header_surface_token' => '표 헤더 표면색',
    ];
    $styleSections = [
        ['title' => '전체와 문단', 'controls' => ['max_width', 'font_size', 'line_height', 'block_gap', 'paragraph_margin', 'border_radius'], 'tokens' => ['text_token', 'muted_token', 'surface_token']],
        ['title' => '제목', 'controls' => ['heading_line_height', 'heading_weight', 'heading_margin', 'h1_size', 'h2_size', 'h3_size', 'h4_size', 'h5_size', 'h6_size'], 'tokens' => ['heading_token']],
        ['title' => '링크와 강조', 'controls' => ['strong_weight', 'link_weight', 'link_underline_offset', 'link_decoration_thickness'], 'tokens' => ['accent_token']],
        ['title' => '목록과 작업 목록', 'controls' => ['list_indent', 'list_item_gap'], 'tokens' => []],
        ['title' => '인용', 'controls' => ['quote_border_width', 'quote_padding_block', 'quote_padding_inline'], 'tokens' => ['quote_token', 'border_token']],
        ['title' => '코드', 'controls' => ['code_font_size', 'code_line_height', 'inline_code_padding', 'code_block_padding', 'code_block_border_width'], 'tokens' => ['code_token', 'code_surface_token']],
        ['title' => '표', 'controls' => ['table_border_width', 'table_cell_padding', 'table_header_weight'], 'tokens' => ['table_header_surface_token']],
        ['title' => '구분선', 'controls' => ['hr_margin', 'hr_border_width'], 'tokens' => []],
    ];
    foreach ($styleSections as $styleSection) {
    ?>
        <section class="card markdown-editor-style-section">
            <h2><?php echo sr_e((string) $styleSection['title']); ?></h2>
            <?php foreach ((array) $styleSection['controls'] as $key) {
                $control = $numberControls[$key];
                $value = (string) ($styleProfile[$key] ?? sr_markdown_editor_default_style_profile()[$key]);
            ?>
                <div class="form-row">
                    <label class="form-label" for="markdown_editor_style_<?php echo sr_e($key); ?>"><?php echo sr_e($control['label']); ?> <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field markdown-editor-control-pair">
                        <input id="markdown_editor_style_<?php echo sr_e($key); ?>" type="range" name="style_profile[<?php echo sr_e($key); ?>]" min="<?php echo sr_e((string) $control['min']); ?>" max="<?php echo sr_e((string) $control['max']); ?>" step="<?php echo sr_e((string) $control['step']); ?>" value="<?php echo sr_e($value); ?>" required>
                        <input type="number" name="style_profile[<?php echo sr_e($key); ?>]" min="<?php echo sr_e((string) $control['min']); ?>" max="<?php echo sr_e((string) $control['max']); ?>" step="<?php echo sr_e((string) $control['step']); ?>" value="<?php echo sr_e($value); ?>" required class="form-input">
                    </div>
                </div>
            <?php } ?>
            <?php if ((array) $styleSection['tokens'] !== []) { ?>
                <div class="markdown-editor-token-grid">
                    <?php foreach ((array) $styleSection['tokens'] as $key) { ?>
                        <div class="form-row">
                            <label class="form-label" for="markdown_editor_<?php echo sr_e($key); ?>"><?php echo sr_e($tokenControls[$key]); ?> <span class="sr-required-label">(필수)</span></label>
                            <div class="form-field">
                                <select id="markdown_editor_<?php echo sr_e($key); ?>" name="style_profile[<?php echo sr_e($key); ?>]" class="form-select" required>
                                    <?php foreach ($tokenOptions as $token => $tokenLabel) { ?>
                                        <option value="<?php echo sr_e($token); ?>"<?php echo (string) ($styleProfile[$key] ?? '') === (string) $token ? ' selected' : ''; ?>><?php echo sr_e($tokenLabel . ' ' . $token); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>
    <?php } ?>

    <section class="card">
        <h2>고급 선언</h2>
        <?php foreach ($selectorOptions as $selectorKey => $selectorLabel) { ?>
            <div class="form-row">
                <label class="form-label" for="markdown_editor_custom_<?php echo sr_e($selectorKey); ?>"><?php echo sr_e($selectorLabel); ?></label>
                <div class="form-field">
                    <textarea id="markdown_editor_custom_<?php echo sr_e($selectorKey); ?>" name="custom_declarations[<?php echo sr_e($selectorKey); ?>]" rows="2" maxlength="1000" class="form-textarea form-control-full"><?php echo sr_e((string) ($customDeclarations[$selectorKey] ?? '')); ?></textarea>
                    <p class="form-help">허용 property:value 선언만 저장됩니다. selector는 서버가 마크다운 wrapper 안에서 생성합니다.</p>
                </div>
            </div>
        <?php } ?>
    </section>

    <section class="card">
        <h2>미리보기</h2>
        <style data-markdown-editor-preview-style><?php echo sr_e($sampleCss); ?></style>
        <div class="markdown-editor-preview-grid">
            <div class="markdown-editor-preview-card" data-color-scheme="light" data-markdown-editor-preview-light>
                <?php echo (string) ($sampleResult['html'] ?? ''); ?>
            </div>
            <div class="markdown-editor-preview-card" data-color-scheme="dark" data-markdown-editor-preview-dark>
                <?php echo (string) ($sampleResult['html'] ?? ''); ?>
            </div>
        </div>
        <p class="form-help">저장 전 미리보기는 같은 renderer와 CSS 생성 로직으로 이 관리자 화면 안에서만 inline style을 사용합니다.</p>
    </section>

    <div class="form-sticky-actions form-actions">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<script src="<?php echo sr_e(sr_asset_url('/modules/markdown_editor/assets/admin.js')); ?>" defer></script>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
