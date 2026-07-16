<?php

$policyDocumentAdminPage = isset($policyDocumentAdminPage) ? (string) $policyDocumentAdminPage : 'list';
$policyDocumentFormMode = isset($policyDocumentFormMode) ? (string) $policyDocumentFormMode : 'new';
$editingVersion = $policyDocumentAdminPage === 'form' && $policyDocumentFormMode === 'edit' && is_array($formVersion);
$creatingDocument = $policyDocumentAdminPage === 'form' && $policyDocumentFormMode === 'document_new';
$pageTitle = $policyDocumentAdminPage === 'form'
    ? ($creatingDocument ? sr_t('policy_documents::ui.document.create') : ($editingVersion ? sr_t('policy_documents::ui.version.edit') : sr_t('policy_documents::ui.version.create')))
    : sr_t('policy_documents::ui.policy_documents');
$adminPageTitle = $pageTitle;
$adminPageSubtitle = $policyDocumentAdminPage === 'form'
    ? ($creatingDocument ? '' : sr_t('policy_documents::ui.version.form_description'))
    : '';
$adminContainerClass = $policyDocumentAdminPage === 'form' ? 'admin-page-policy-documents-form admin-ui-scope' : 'admin-page-policy-documents-list admin-ui-scope';

$policyDocumentStatusClass = static function (string $status): string {
    return match ($status) {
        'enabled', 'published', 'sent' => 'is-success',
        'draft', 'queued', 'processing', 'skipped' => 'is-danger',
        'disabled', 'archived', 'failed', 'cancelled' => 'is-warning',
        default => 'is-danger',
    };
};
$policyDocumentMailStatusLabels = [
    'queued' => sr_t('policy_documents::ui.mail_status.queued'),
    'processing' => sr_t('policy_documents::ui.mail_status.processing'),
    'sent' => sr_t('policy_documents::ui.mail_status.sent'),
    'failed' => sr_t('policy_documents::ui.mail_status.failed'),
    'skipped' => sr_t('policy_documents::ui.mail_status.skipped'),
    'cancelled' => sr_t('policy_documents::ui.mail_status.cancelled'),
];
$policyDocumentVersionValue = static function (string $key, string $default = '') use ($editingVersion, $formVersion, $selectedDocument): string {
    if ($editingVersion && is_array($formVersion)) {
        if ($key === 'title') {
            return (string) ($formVersion['title_snapshot'] ?? '');
        }

        return (string) ($formVersion[$key] ?? $default);
    }
    if ($key === 'title' && is_array($selectedDocument)) {
        return (string) ($selectedDocument['title'] ?? '');
    }

    return $default;
};
$policyDocumentBodyHtmlValue = $policyDocumentVersionValue('body_html');
$policyDocumentBodyTextValue = static function (string $bodyHtml): string {
    $bodyHtml = preg_replace('/<\s*br\s*\/?>/i', "\n", $bodyHtml) ?? $bodyHtml;
    $bodyHtml = preg_replace('/<\s*\/\s*(p|div|li|h[1-6])\s*>/i', "\n\n", $bodyHtml) ?? $bodyHtml;
    $bodyHtml = trim(strip_tags($bodyHtml));

    return html_entity_decode($bodyHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
};
$policyDocumentCkeditorAvailable = isset($pdo) && $pdo instanceof PDO && sr_editor_available($pdo, 'ckeditor');
$policyDocumentMarkdownAvailable = isset($pdo) && $pdo instanceof PDO && sr_markdown_renderer_available($pdo);
$policyDocumentBodyEditorMode = $policyDocumentCkeditorAvailable ? 'ckeditor' : 'html';
$policyDocumentBodyEditorOptions = [
    'plain' => sr_t('policy_documents::ui.body_mode.plain'),
    'html' => sr_t('policy_documents::ui.body_mode.html'),
];
if ($policyDocumentMarkdownAvailable) {
    $policyDocumentBodyEditorOptions['markdown'] = sr_t('policy_documents::ui.body_mode.markdown');
}
if ($policyDocumentCkeditorAvailable) {
    $policyDocumentBodyEditorOptions['ckeditor'] = sr_t('policy_documents::ui.body_mode.ckeditor');
}
$policyDocumentCkeditorAttributes = $policyDocumentCkeditorAvailable && isset($pdo) && $pdo instanceof PDO
    ? sr_editor_textarea_attributes($pdo, 'ckeditor', 'admin_basic', 'body_editor_format')
    : '';
$policyDocumentVersionsByDocumentId = [];
foreach ($versions as $policyDocumentVersionRow) {
    $policyDocumentVersionDocumentId = (int) $policyDocumentVersionRow['document_id'];
    $policyDocumentVersionsByDocumentId[$policyDocumentVersionDocumentId][] = $policyDocumentVersionRow;
}
$policyDocumentPreviousHistoryCandidateCount = 0;
if ($selectedDocumentId > 0) {
    $policyDocumentCurrentFormVersionId = $editingVersion && is_array($formVersion) ? (int) ($formVersion['id'] ?? 0) : 0;
    foreach ($policyDocumentVersionsByDocumentId[$selectedDocumentId] ?? [] as $policyDocumentHistoryCandidate) {
        $policyDocumentHistoryCandidateStatus = (string) ($policyDocumentHistoryCandidate['status'] ?? '');
        if (!in_array($policyDocumentHistoryCandidateStatus, ['published', 'archived'], true)) {
            continue;
        }
        if ($policyDocumentCurrentFormVersionId > 0 && (int) ($policyDocumentHistoryCandidate['id'] ?? 0) >= $policyDocumentCurrentFormVersionId) {
            continue;
        }
        $policyDocumentPreviousHistoryCandidateCount++;
    }
}
$policyDocumentStandardTemplateHtml = '';
$policyDocumentStandardTemplateLabel = '';
$policyDocumentStandardTemplateRevisionDateLabel = '';
$policyDocumentStandardTemplateNoticeUrl = '';
$policyDocumentHelpOpenLabel = '도움말 보기';
$policyDocumentHelpButtonHtml = static function (string $label, string $modalId) use ($policyDocumentHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $policyDocumentHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$policyDocumentHelp = [
    'document' => [
        'id' => 'policy-document-help-document',
        'title' => '약관·방침 기본 정보 도움말',
        'body' => '<p>문서 식별값은 회원가입, 동의가 필요한 공개 폼과 약관 안내 화면이 이 문서를 찾을 때 사용하는 내부 고유값입니다. 저장한 뒤 바꾸는 화면이 없으므로 용도를 알아볼 수 있게 입력하세요.</p>'
            . '<p>문서 상태를 사용 안 함으로 저장하면 게시 버전이 있어도 공개 약관과 동의 문서 선택 대상에서 제외됩니다. 정렬 순서는 문서 선택 목록과 관리자 목록의 배치 기준이며 숫자가 작을수록 먼저 표시됩니다.</p>',
    ],
    'body' => [
        'id' => 'policy-document-help-body',
        'title' => '본문 작성 방식 도움말',
        'body' => '<p>일반 텍스트는 줄바꿈을 문단으로 바꾸어 저장합니다. Markdown은 Markdown Editor를 사용할 수 있을 때만 선택할 수 있으며, HTML은 태그를 직접 입력합니다. CKEditor는 편집 도구가 활성화된 경우에만 나타납니다.</p>'
            . '<p>어떤 방식을 선택해도 저장할 때 허용되지 않은 태그와 위험한 코드를 제거합니다. 저장되는 것은 선택한 작성 방식의 내용이므로, 방식을 바꾼 뒤에는 표시된 본문을 다시 확인하세요.</p>',
    ],
    'status' => [
        'id' => 'policy-document-help-version-status',
        'title' => '문서 버전 상태 도움말',
        'body' => '<p>초안은 공개되지 않으며 나중에 수정하거나 별도 공개 작업을 할 수 있습니다. 게시는 시행일 조건이 맞으면 공개 약관에 적용되고 변경 안내메일 작업을 만듭니다. 보관은 현재 공개 버전으로 사용하지 않지만 이전 버전 기록에는 연결할 수 있습니다.</p>'
            . '<p>문서 사용 상태와 버전 상태는 별도입니다. 문서를 사용 안 함으로 저장하면 게시 버전은 공개되지 않지만, 첫 버전을 게시 상태로 저장할 때는 안내메일 작업이 생성됩니다.</p>'
            . '<p>게시 버전의 시행일이 비어 있거나 현재 이전이면 기존의 현재 적용 버전을 보관 상태로 바꿉니다. 미래 시행일인 게시 버전은 그 시각이 될 때까지 기존 버전을 유지하고, 이후 조회부터 새 버전을 자동 선택합니다.</p>',
    ],
    'history' => [
        'id' => 'policy-document-help-history',
        'title' => '이전 버전 기록 도움말',
        'body' => '<p>사용하면 현재 공개 본문 아래에 이전 게시·보관 버전의 시행일 또는 게시일 링크를 자동으로 붙입니다. 이전 본문 전체를 현재 본문 안에 복사하지는 않습니다.</p>'
            . '<p>링크 목록은 공개 화면을 조회할 때 저장된 버전을 기준으로 만듭니다. 현재 본문 원문은 바꾸지 않으며, 문서 자체가 사용 상태여야 이전 버전 링크도 열 수 있습니다.</p>',
    ],
    'effective' => [
        'id' => 'policy-document-help-effective',
        'title' => '시행일과 안내메일 도움말',
        'body' => '<p>게시 상태에서 시행일을 비워 두면 게시 즉시 적용합니다. 미래 시각을 입력하면 그 시각 전까지 기존 공개 버전을 유지하고, 시행일이 지난 뒤 공개 화면 조회부터 새 버전을 적용합니다.</p>'
            . '<p>변경 안내메일 작업은 버전을 게시할 때 바로 생성되며 시행일까지 자동으로 기다렸다가 보내지 않습니다. 시행 전 고지가 필요하면 시행일을 먼저 정하고 원하는 시점에 안내메일 작업을 실행하세요.</p>'
            . '<p>초안의 시행일은 공개 예약이 아닙니다. 초안을 별도로 공개해야 적용과 안내메일 작업 생성이 시작됩니다.</p>',
    ],
];
$policyDocumentSelectedDocumentKey = is_array($selectedDocument) ? (string) ($selectedDocument['document_key'] ?? '') : '';
if ($policyDocumentAdminPage === 'form' && !$creatingDocument && $policyDocumentSelectedDocumentKey !== '' && isset($pdo) && $pdo instanceof PDO) {
    $policyDocumentStandardTemplateHtml = sr_policy_document_standard_template_html($pdo, $policyDocumentSelectedDocumentKey, is_array($site ?? null) ? $site : null);
    $policyDocumentStandardTemplateLabel = sr_policy_document_standard_template_button_label($policyDocumentSelectedDocumentKey);
    $policyDocumentStandardTemplateRevisionDateLabel = sr_policy_document_standard_template_revision_date_label($policyDocumentSelectedDocumentKey);
    $policyDocumentStandardTemplateNoticeUrl = sr_policy_document_standard_template_notice_url($policyDocumentSelectedDocumentKey);
}

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($policyDocumentAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/policy-documents/save')); ?>" class="admin-form ui-form-theme" data-sr-validate-form>
        <?php if ($creatingDocument) { ?>
            <section class="card">
                <h2><?php echo sr_e(sr_t('policy_documents::ui.document.create')); ?></h2>
                <p class="form-help"><?php echo sr_e(sr_t('policy_documents::ui.document.form_description')); ?></p>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="action" value="save_document">
                <input type="hidden" name="form_mode" value="document_new">

                <div class="form-row">
                    <label class="form-label" for="policy_document_document_title"><?php echo sr_e(sr_t('policy_documents::ui.title')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                    <div class="form-field">
                        <input id="policy_document_document_title" class="form-input form-control-full" type="text" name="title" maxlength="190" required>
                    </div>
                </div>

                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('policy_document_document_key', sr_t('policy_documents::ui.document_key'), $policyDocumentHelp['document']['id'], $policyDocumentHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <input id="policy_document_document_key" class="form-input" type="text" name="document_key" maxlength="80" pattern="[a-z][a-z0-9_]{2,79}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input data-validation-message="<?php echo sr_e(sr_t('policy_documents::error.document_key_invalid')); ?>">
                        <p class="form-help"><?php echo sr_e(sr_t('policy_documents::ui.document_key.help')); ?></p>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label" for="policy_document_document_description"><?php echo sr_e(sr_t('policy_documents::ui.document_description')); ?></label>
                    <div class="form-field">
                        <textarea id="policy_document_document_description" class="form-textarea form-control-full" name="description" rows="3" maxlength="2000"></textarea>
                        <p class="form-help">문서의 용도를 운영자가 구분할 수 있도록 적습니다. 공개 약관 본문에는 표시되지 않습니다.</p>
                    </div>
                </div>

                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('policy_document_document_status', '문서 사용 상태', $policyDocumentHelp['document']['id'], $policyDocumentHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="policy_document_document_status" class="form-select" name="status" required>
                            <option value="enabled"><?php echo sr_e(sr_t('policy_documents::ui.status.enabled')); ?></option>
                            <option value="disabled"><?php echo sr_e(sr_t('policy_documents::ui.status.disabled')); ?></option>
                        </select>
                        <p class="form-help">사용 안 함이면 게시 버전이 있어도 공개 화면과 동의 문서 선택에서 제외합니다.</p>
                    </div>
                </div>

                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('policy_document_document_sort_order', sr_t('policy_documents::ui.sort_order'), $policyDocumentHelp['document']['id'], $policyDocumentHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <input id="policy_document_document_sort_order" class="form-input" type="number" name="sort_order" min="0" max="1000000" value="100" required>
                        <p class="form-help">숫자가 작을수록 문서 선택 목록에서 먼저 표시됩니다.</p>
                    </div>
                </div>
            </section>
            <section class="card">
                <h2><?php echo sr_e(sr_t('policy_documents::ui.version.create')); ?></h2>
                <p class="form-help">새 약관/방침의 첫 버전을 함께 저장합니다.</p>

                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('policy_document_body_html', sr_t('policy_documents::ui.body'), $policyDocumentHelp['body']['id'], $policyDocumentHelpOpenLabel, true); ?>
                    <div class="form-field" data-admin-body-editor-mode-group>
                        <div>
                            <?php echo sr_admin_radio_toggle_group_html('policy_document_body_editor_mode', 'body_editor_mode', $policyDocumentBodyEditorOptions, $policyDocumentBodyEditorMode, true, ' data-admin-body-editor-mode'); ?>
                        </div>
                        <div class="btn-space-before" data-admin-body-editor-panel="plain"<?php echo $policyDocumentBodyEditorMode === 'plain' ? '' : ' hidden'; ?>>
                            <textarea id="policy_document_body_plain" class="form-textarea form-control-full" name="body_plain" rows="14"></textarea>
                        </div>
                        <div class="btn-space-before" data-admin-body-editor-panel="markdown"<?php echo $policyDocumentBodyEditorMode === 'markdown' ? '' : ' hidden'; ?>>
                            <textarea id="policy_document_body_markdown" class="form-textarea form-control-full" name="body_markdown" rows="14"></textarea>
                        </div>
                        <div class="btn-space-before" data-admin-body-editor-panel="html"<?php echo $policyDocumentBodyEditorMode === 'html' ? '' : ' hidden'; ?>>
                            <textarea id="policy_document_body_html" class="form-textarea form-control-full" name="body_html" rows="14"></textarea>
                        </div>
                        <?php if ($policyDocumentCkeditorAvailable) { ?>
                            <div class="btn-space-before" data-admin-body-editor-panel="ckeditor"<?php echo $policyDocumentBodyEditorMode === 'ckeditor' ? '' : ' hidden'; ?>>
                                <textarea id="policy_document_body_ckeditor_html" class="form-textarea form-control-full" name="body_ckeditor_html" rows="14"<?php echo $policyDocumentCkeditorAttributes; ?>></textarea>
                            </div>
                        <?php } ?>
                        <p class="form-help"><?php echo sr_e(sr_t('policy_documents::ui.body.help')); ?></p>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label" for="policy_document_summary_text"><?php echo sr_e(sr_t('policy_documents::ui.summary')); ?></label>
                    <div class="form-field">
                        <textarea id="policy_document_summary_text" class="form-textarea form-control-full" name="summary_text" rows="3" maxlength="1000"></textarea>
                        <p class="form-help">변경 내용을 관리하거나 연동 기능에서 사용할 요약입니다. 기본 공개 약관 화면에는 표시되지 않습니다.</p>
                    </div>
                </div>

                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('policy_document_initial_version_status', '첫 버전 상태', $policyDocumentHelp['status']['id'], $policyDocumentHelpOpenLabel, true, true); ?>
                    <div class="form-field">
                        <select id="policy_document_initial_version_status" class="form-select" name="version_status" required>
                            <option value="draft"><?php echo sr_e(sr_t('policy_documents::ui.status.draft')); ?></option>
                            <option value="published"><?php echo sr_e(sr_t('policy_documents::ui.status.published')); ?></option>
                            <option value="archived"><?php echo sr_e(sr_t('policy_documents::ui.status.archived')); ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('policy_document_effective_from', sr_t('policy_documents::ui.effective_from'), $policyDocumentHelp['effective']['id'], $policyDocumentHelpOpenLabel, false, true); ?>
                    <div class="form-field">
                        <input id="policy_document_effective_from" class="form-input" type="datetime-local" name="effective_from">
                        <p class="form-help"><?php echo sr_e(sr_t('policy_documents::ui.effective_from.help')); ?></p>
                    </div>
                </div>
            </section>
        <?php } else { ?>
        <section class="card">
            <h2><?php echo sr_e($pageTitle); ?></h2>
            <p class="form-help"><?php echo sr_e(sr_t('policy_documents::ui.version.form_description')); ?></p>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="action" value="save_version">
            <input type="hidden" name="form_mode" value="<?php echo $editingVersion ? 'edit' : 'new'; ?>">
            <input type="hidden" name="document_id" value="<?php echo sr_e((string) $selectedDocumentId); ?>">
            <?php if ($editingVersion) { ?>
                <input type="hidden" name="version_id" value="<?php echo sr_e((string) (int) $formVersion['id']); ?>">
            <?php } ?>

            <?php if (is_array($selectedDocument)) { ?>
                <div class="form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('policy_documents::ui.document.selected')); ?></span>
                    <div class="form-field">
                        <?php echo sr_e((string) $selectedDocument['title']); ?>
                        <p class="form-help"><?php echo sr_e((string) $selectedDocument['document_key']); ?></p>
                    </div>
                </div>
            <?php } ?>

            <div class="form-row">
                <label class="form-label" for="policy_document_title"><?php echo sr_e(sr_t('policy_documents::ui.title')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                <div class="form-field">
                    <input id="policy_document_title" class="form-input form-control-full" type="text" name="title" maxlength="190" value="<?php echo sr_e($policyDocumentVersionValue('title')); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('policy_document_body_html', sr_t('policy_documents::ui.body'), $policyDocumentHelp['body']['id'], $policyDocumentHelpOpenLabel, true); ?>
                <div class="form-field" data-admin-body-editor-mode-group>
                    <div>
                        <?php echo sr_admin_radio_toggle_group_html('policy_document_body_editor_mode', 'body_editor_mode', $policyDocumentBodyEditorOptions, $policyDocumentBodyEditorMode, true, ' data-admin-body-editor-mode'); ?>
                    </div>
                    <div class="btn-space-before" data-admin-body-editor-panel="plain"<?php echo $policyDocumentBodyEditorMode === 'plain' ? '' : ' hidden'; ?>>
                        <textarea id="policy_document_body_plain" class="form-textarea form-control-full" name="body_plain" rows="14"><?php echo sr_e($policyDocumentBodyTextValue($policyDocumentBodyHtmlValue)); ?></textarea>
                    </div>
                    <div class="btn-space-before" data-admin-body-editor-panel="markdown"<?php echo $policyDocumentBodyEditorMode === 'markdown' ? '' : ' hidden'; ?>>
                        <textarea id="policy_document_body_markdown" class="form-textarea form-control-full" name="body_markdown" rows="14"><?php echo sr_e($policyDocumentBodyTextValue($policyDocumentBodyHtmlValue)); ?></textarea>
                    </div>
                    <div class="btn-space-before" data-admin-body-editor-panel="html"<?php echo $policyDocumentBodyEditorMode === 'html' ? '' : ' hidden'; ?>>
                        <textarea id="policy_document_body_html" class="form-textarea form-control-full" name="body_html" rows="14"><?php echo sr_e($policyDocumentBodyHtmlValue); ?></textarea>
                    </div>
                    <?php if ($policyDocumentCkeditorAvailable) { ?>
                        <div class="btn-space-before" data-admin-body-editor-panel="ckeditor"<?php echo $policyDocumentBodyEditorMode === 'ckeditor' ? '' : ' hidden'; ?>>
                            <textarea id="policy_document_body_ckeditor_html" class="form-textarea form-control-full" name="body_ckeditor_html" rows="14"<?php echo $policyDocumentCkeditorAttributes; ?>><?php echo sr_e($policyDocumentBodyHtmlValue); ?></textarea>
                        </div>
                    <?php } ?>
                    <?php if ($policyDocumentStandardTemplateHtml !== '' && $policyDocumentStandardTemplateLabel !== '') { ?>
                        <div class="form-actions btn-space-before">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-policy-document-standard-template data-policy-document-standard-template-confirm="<?php echo sr_e(sr_t('policy_documents::ui.standard_template.confirm')); ?>">
                                <?php echo sr_material_icon_html('auto_fix_high'); ?><?php echo sr_e($policyDocumentStandardTemplateLabel); ?>
                            </button>
                            <?php if ($policyDocumentStandardTemplateRevisionDateLabel !== '' && $policyDocumentStandardTemplateNoticeUrl !== '') { ?>
                                <a class="badge badge-soft-secondary" href="<?php echo sr_e($policyDocumentStandardTemplateNoticeUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo sr_e($policyDocumentStandardTemplateRevisionDateLabel); ?></a>
                            <?php } elseif ($policyDocumentStandardTemplateRevisionDateLabel !== '') { ?>
                                <span class="badge badge-soft-secondary"><?php echo sr_e($policyDocumentStandardTemplateRevisionDateLabel); ?></span>
                            <?php } ?>
                            <script type="application/json" data-policy-document-standard-template-json><?php echo sr_js_json_encode($policyDocumentStandardTemplateHtml); ?></script>
                        </div>
                    <?php } ?>
                    <p class="form-help"><?php echo sr_e(sr_t('policy_documents::ui.body.help')); ?></p>
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="policy_document_summary_text"><?php echo sr_e(sr_t('policy_documents::ui.summary')); ?></label>
                <div class="form-field">
                    <textarea id="policy_document_summary_text" class="form-textarea form-control-full" name="summary_text" rows="3" maxlength="1000"><?php echo sr_e($policyDocumentVersionValue('summary_text')); ?></textarea>
                    <p class="form-help">변경 내용을 관리하거나 연동 기능에서 사용할 요약입니다. 기본 공개 약관 화면에는 표시되지 않습니다.</p>
                </div>
            </div>

            <div class="form-row">
                <span class="form-label form-label-help">
                    <?php echo $policyDocumentHelpButtonHtml(sr_t('policy_documents::ui.previous_versions.option'), $policyDocumentHelp['history']['id']); ?>
                    <span><?php echo sr_e(sr_t('policy_documents::ui.previous_versions.option')); ?></span>
                </span>
                <div class="form-field">
                    <?php echo sr_admin_checkbox_toggle_html('policy_document_append_previous_versions', 'append_previous_versions', '1', $policyDocumentVersionValue('append_previous_versions') === '1', sr_t('policy_documents::ui.previous_versions.option.enable')); ?>
                    <p class="form-help"><?php echo sr_e(sr_t('policy_documents::ui.previous_versions.option.help', ['count' => number_format($policyDocumentPreviousHistoryCandidateCount)])); ?></p>
                </div>
            </div>

            <?php if (!$editingVersion) { ?>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('policy_document_status', '버전 상태', $policyDocumentHelp['status']['id'], $policyDocumentHelpOpenLabel, true, true); ?>
                    <div class="form-field">
                        <select id="policy_document_status" class="form-select" name="status" required>
                            <option value="draft"><?php echo sr_e(sr_t('policy_documents::ui.status.draft')); ?></option>
                            <option value="published"><?php echo sr_e(sr_t('policy_documents::ui.status.published')); ?></option>
                            <option value="archived"><?php echo sr_e(sr_t('policy_documents::ui.status.archived')); ?></option>
                        </select>
                    </div>
                </div>
            <?php } ?>

            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('policy_document_effective_from', sr_t('policy_documents::ui.effective_from'), $policyDocumentHelp['effective']['id'], $policyDocumentHelpOpenLabel, false, true); ?>
                <div class="form-field">
                    <input id="policy_document_effective_from" class="form-input" type="datetime-local" name="effective_from" value="<?php echo sr_e(sr_datetime_local_value($policyDocumentVersionValue('effective_from'))); ?>">
                    <p class="form-help"><?php echo sr_e(sr_t('policy_documents::ui.effective_from.help')); ?></p>
                </div>
            </div>
        </section>
        <?php } ?>

        <div class="form-sticky-actions form-actions form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/policy-documents?document_id=' . (string) $selectedDocumentId)); ?>" class="btn btn-solid-light"><?php echo sr_e('취소'); ?></a>
            <button class="btn btn-solid-primary" type="submit"><?php echo sr_e($creatingDocument ? sr_t('policy_documents::ui.save_document') : ($editingVersion ? sr_t('policy_documents::ui.save_update') : sr_t('policy_documents::ui.save_new_version'))); ?></button>
        </div>
    </form>
<?php } else { ?>
    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('policy_documents::ui.policy_document_list')); ?></h2>
            <a href="<?php echo sr_e(sr_url('/admin/policy-documents/document-new')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('policy_documents::ui.document.create')); ?></a>
        </div>
        <div class="table-wrapper">
            <table class="table table-list admin-policy-document-table">
                <caption class="sr-only"><?php echo sr_e(sr_t('policy_documents::ui.document.list')); ?></caption>
                <thead>
                    <tr>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.title')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.document_key')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.published_at')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.status')); ?></th>
                        <th class="text-end"><?php echo sr_e(sr_t('policy_documents::ui.action')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($documents === []) { ?>
                        <tr>
                            <td colspan="5" class="admin-empty-state"><?php echo sr_e(sr_t('policy_documents::ui.document.empty')); ?></td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($documents as $document) { ?>
                        <?php $documentStatus = (string) $document['status']; ?>
                        <tr>
                            <td class="admin-table-break"><?php echo sr_e((string) $document['title']); ?></td>
                            <td class="admin-table-nowrap"><a href="<?php echo sr_e(sr_url('/admin/policy-documents?document_id=' . (string) (int) $document['id'])); ?>"><?php echo sr_e((string) $document['document_key']); ?></a></td>
                            <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($document['published_at'] ?? ''), '-'); ?></td>
                            <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e($policyDocumentStatusClass($documentStatus)); ?>"><?php echo sr_e(sr_admin_code_label($documentStatus, 'content_status')); ?></span></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <?php $policyDocumentVersionModalId = 'policy-document-versions-' . (string) (int) $document['id']; ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('policy_documents::ui.version.view')); ?>" title="<?php echo sr_e(sr_t('policy_documents::ui.version.view')); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($policyDocumentVersionModalId); ?>" data-overlay="#<?php echo sr_e($policyDocumentVersionModalId); ?>"><?php echo sr_material_icon_html('history'); ?></button>
                                    <a href="<?php echo sr_e(sr_url('/admin/policy-documents/new?document_id=' . (string) (int) $document['id'])); ?>" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e(sr_t('policy_documents::ui.version.new')); ?>" title="<?php echo sr_e(sr_t('policy_documents::ui.version.new')); ?>"><?php echo sr_material_icon_html('add'); ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php echo sr_admin_status_description_list_html('content_status', sr_admin_code_label_options(['enabled', 'disabled'], 'content_status')); ?>
    </section>

    <?php foreach ($documents as $document) { ?>
        <?php
        $policyDocumentId = (int) $document['id'];
        $policyDocumentVersionModalId = 'policy-document-versions-' . (string) $policyDocumentId;
        $policyDocumentRows = $policyDocumentVersionsByDocumentId[$policyDocumentId] ?? [];
        ?>
        <div id="<?php echo sr_e($policyDocumentVersionModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($policyDocumentVersionModalId); ?>-label" aria-hidden="true" inert data-overlay-stack="true">
            <div class="modal-dialog modal-dialog-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($policyDocumentVersionModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_t('policy_documents::ui.version.list')); ?></h3>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e('닫기'); ?>" data-overlay="#<?php echo sr_e($policyDocumentVersionModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <section class="card admin-list-card admin-list-form">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e((string) $document['title']); ?></h4>
                            </div>
                            <div class="table-wrapper">
                                <table class="table table-list admin-policy-document-version-table">
                                    <caption class="sr-only"><?php echo sr_e(sr_t('policy_documents::ui.version.list')); ?></caption>
                                    <thead>
                                        <tr>
                                            <th><?php echo sr_e(sr_t('policy_documents::ui.title')); ?></th>
                                            <th><?php echo sr_e(sr_t('policy_documents::ui.status')); ?></th>
                                            <th><?php echo sr_e(sr_t('policy_documents::ui.body_hash')); ?></th>
                                            <th><?php echo sr_e(sr_t('policy_documents::ui.effective_from')); ?></th>
                                            <th><?php echo sr_e(sr_t('policy_documents::ui.published_at')); ?></th>
                                            <th class="text-end"><?php echo sr_e(sr_t('policy_documents::ui.action')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($policyDocumentRows === []) { ?>
                                            <tr>
                                                <td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('policy_documents::ui.version.empty')); ?></td>
                                            </tr>
                                        <?php } ?>
                                        <?php foreach ($policyDocumentRows as $version) { ?>
                                            <?php
                                            $versionStatus = (string) $version['status'];
                                            $policyDocumentVersionDetailModalId = 'policy-document-version-detail-' . (string) (int) $version['id'];
                                            ?>
                                            <tr>
                                                <td class="admin-table-break"><?php echo sr_e((string) $version['title_snapshot']); ?></td>
                                                <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e($policyDocumentStatusClass($versionStatus)); ?>"><?php echo sr_e(sr_admin_code_label($versionStatus, 'content_status')); ?></span></td>
                                                <td class="admin-table-nowrap"><?php echo sr_e(substr((string) $version['body_hash'], 0, 16)); ?></td>
                                                <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($version['effective_from'] ?? ''), sr_t('policy_documents::ui.effective_from.empty')); ?></td>
                                                <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($version['published_at'] ?? ''), '-'); ?></td>
                                                <td class="admin-table-actions-cell">
                                                    <div class="admin-row-actions">
                                                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('policy_documents::ui.version.body_view')); ?>" title="<?php echo sr_e(sr_t('policy_documents::ui.version.body_view')); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($policyDocumentVersionDetailModalId); ?>" data-overlay="#<?php echo sr_e($policyDocumentVersionDetailModalId); ?>" data-overlay-stack="true"><?php echo sr_material_icon_html('visibility'); ?></button>
                                                        <?php if ($versionStatus === 'draft') { ?>
                                                            <a href="<?php echo sr_e(sr_url('/admin/policy-documents/edit?id=' . (string) (int) $version['id'])); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('policy_documents::ui.edit')); ?>" title="<?php echo sr_e(sr_t('policy_documents::ui.edit')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                                            <form method="post" action="<?php echo sr_e(sr_url('/admin/policy-documents')); ?>" class="admin-inline-form">
                                                                <?php echo sr_csrf_field(); ?>
                                                                <input type="hidden" name="action" value="publish_version">
                                                                <input type="hidden" name="version_id" value="<?php echo sr_e((string) (int) $version['id']); ?>">
                                                                <input type="hidden" name="document_id" value="<?php echo sr_e((string) (int) $version['document_id']); ?>">
                                                                <button type="submit" class="btn btn-sm btn-icon btn-solid-primary" aria-label="<?php echo sr_e(sr_t('policy_documents::ui.publish')); ?>" title="<?php echo sr_e(sr_t('policy_documents::ui.publish')); ?>" onclick="return confirm('<?php echo sr_e(sr_t('policy_documents::ui.publish.confirm')); ?>');"><?php echo sr_material_icon_html('publish'); ?></button>
                                                            </form>
                                                        <?php } ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php echo sr_admin_status_description_list_html('content_status', sr_admin_code_label_options(['published', 'draft', 'archived'], 'content_status')); ?>
                        </section>
                    </div>
                    <div class="modal-footer">
                        <a href="<?php echo sr_e(sr_url('/admin/policy-documents/new?document_id=' . (string) $policyDocumentId)); ?>" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('policy_documents::ui.version.new')); ?></a>
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($policyDocumentVersionModalId); ?>"><?php echo sr_e('닫기'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php foreach ($policyDocumentRows as $version) { ?>
            <?php
            $versionStatus = (string) $version['status'];
            $policyDocumentVersionDetailModalId = 'policy-document-version-detail-' . (string) (int) $version['id'];
            $policyDocumentVersionBodyHtml = sr_policy_document_render_body_html($pdo, $version);
            ?>
            <div id="<?php echo sr_e($policyDocumentVersionDetailModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($policyDocumentVersionDetailModalId); ?>-label" aria-hidden="true" inert data-overlay-stack="true">
                <div class="modal-dialog-fluid">
                    <div class="modal-content-fullscreen modal-radius-md">
                        <div class="modal-header">
                            <h3 id="<?php echo sr_e($policyDocumentVersionDetailModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_t('policy_documents::ui.version.body_view')); ?></h3>
                            <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e('닫기'); ?>" data-overlay="#<?php echo sr_e($policyDocumentVersionDetailModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                        </div>
                        <div class="modal-body-fill">
                            <div class="admin-setting-source-line">
                                <h4 class="modal-title"><?php echo sr_e((string) $version['title_snapshot']); ?></h4>
                                <span class="badge-status <?php echo sr_e($policyDocumentStatusClass($versionStatus)); ?>"><?php echo sr_e(sr_admin_code_label($versionStatus, 'content_status')); ?></span>
                            </div>
                            <dl class="card-description-list btn-space-before">
                                <div>
                                    <dt><?php echo sr_e(sr_t('policy_documents::ui.document_key')); ?></dt>
                                    <dd><?php echo sr_e((string) ($version['document_key'] ?? '')); ?></dd>
                                </div>
                                <div>
                                    <dt><?php echo sr_e(sr_t('policy_documents::ui.published_at')); ?></dt>
                                    <dd><?php echo sr_admin_time_html((string) ($version['published_at'] ?? ''), '-'); ?></dd>
                                </div>
                                <div>
                                    <dt><?php echo sr_e(sr_t('policy_documents::ui.effective_from')); ?></dt>
                                    <dd><?php echo sr_admin_time_html((string) ($version['effective_from'] ?? ''), sr_t('policy_documents::ui.effective_from.empty')); ?></dd>
                                </div>
                                <div>
                                    <dt><?php echo sr_e(sr_t('policy_documents::ui.body_hash')); ?></dt>
                                    <dd><?php echo sr_e((string) $version['body_hash']); ?></dd>
                                </div>
                            </dl>
                            <div class="admin-help-modal-body btn-space-before">
                                <?php echo $policyDocumentVersionBodyHtml !== '' ? $policyDocumentVersionBodyHtml : '<p>' . sr_e(sr_t('policy_documents::ui.version.body_empty')) . '</p>'; ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($policyDocumentVersionDetailModalId); ?>"><?php echo sr_e('닫기'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    <?php } ?>

    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('policy_documents::ui.mail_jobs')); ?></h2>
        </div>
        <?php echo sr_admin_pagination_summary_html($mailJobPagination); ?>
        <div class="table-wrapper">
            <table class="table table-list admin-policy-document-mail-job-table">
                <caption class="sr-only"><?php echo sr_e(sr_t('policy_documents::ui.mail_jobs')); ?></caption>
                <thead>
                    <tr>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.document_title')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.status')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.mail_counts')); ?></th>
                        <th class="text-end"><?php echo sr_e(sr_t('policy_documents::ui.action')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($mailJobs === []) { ?>
                        <tr>
                            <td colspan="4" class="admin-empty-state"><?php echo sr_e(sr_t('policy_documents::ui.mail_job_empty')); ?></td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($mailJobs as $mailJob) { ?>
                        <?php $mailJobStatus = (string) $mailJob['status']; ?>
                        <tr>
                            <td class="admin-table-break">
                                <strong><?php echo sr_e((string) ($mailJob['document_title'] ?? $mailJob['document_key'])); ?></strong>
                            </td>
                            <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e($policyDocumentStatusClass($mailJobStatus)); ?>"><?php echo sr_e($policyDocumentMailStatusLabels[$mailJobStatus] ?? $mailJobStatus); ?></span></td>
                            <td class="admin-table-nowrap">
                                <?php echo sr_e('완료 ' . number_format((int) $mailJob['sent_count']) . ' / 전체 ' . number_format((int) $mailJob['delivery_count'])); ?>
                                <p class="form-help">
                                    <?php echo sr_e('대기 ' . number_format((int) $mailJob['queued_count']) . ', 처리중 ' . number_format((int) ($mailJob['processing_count'] ?? 0)) . ', 실패 ' . number_format((int) $mailJob['failed_count']) . ', 건너뜀 ' . number_format((int) ($mailJob['skipped_count'] ?? 0)) . ', 취소 ' . number_format((int) ($mailJob['cancelled_count'] ?? 0))); ?>
                                </p>
                            </td>
                            <td class="admin-table-actions-cell">
                                <?php if ((int) $mailJob['queued_count'] > 0 || (int) ($mailJob['processing_count'] ?? 0) > 0 || (int) $mailJob['failed_count'] > 0) { ?>
                                <div class="admin-row-actions">
                                <?php if ((int) $mailJob['queued_count'] > 0 || (int) ($mailJob['processing_count'] ?? 0) > 0) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/policy-documents')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="action" value="run_mail_batch">
                                        <input type="hidden" name="job_id" value="<?php echo sr_e((string) (int) $mailJob['id']); ?>">
                                        <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/policy-documents')); ?>">
                                        <button class="btn btn-sm btn-solid-primary" type="submit"><?php echo sr_e(sr_t('policy_documents::ui.mail_run')); ?></button>
                                    </form>
                                <?php } ?>
                                <?php if ((int) $mailJob['failed_count'] > 0) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/policy-documents')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="action" value="requeue_mail_failures">
                                        <input type="hidden" name="job_id" value="<?php echo sr_e((string) (int) $mailJob['id']); ?>">
                                        <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/policy-documents')); ?>">
                                        <button class="btn btn-sm btn-solid-light" type="submit"><?php echo sr_e('실패 재대기'); ?></button>
                                    </form>
                                <?php } ?>
                                <?php if ((int) $mailJob['queued_count'] > 0 || (int) ($mailJob['processing_count'] ?? 0) > 0 || (int) $mailJob['failed_count'] > 0) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/policy-documents')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="action" value="cancel_mail_pending">
                                        <input type="hidden" name="job_id" value="<?php echo sr_e((string) (int) $mailJob['id']); ?>">
                                        <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/policy-documents')); ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" data-confirm="<?php echo sr_e('아직 발송 완료되지 않은 안내메일을 취소합니다. 계속할까요?'); ?>"><?php echo sr_e('남은 발송 취소'); ?></button>
                                    </form>
                                <?php } ?>
                                </div>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php echo sr_admin_pagination_html($mailJobPagination, '정책 문서 안내메일 작업 목록 페이지'); ?>
        <?php echo sr_admin_status_description_list_html('policy_document_mail_status', $policyDocumentMailStatusLabels); ?>
    </section>
<?php } ?>

<?php if ($policyDocumentAdminPage === 'form') { ?>
    <?php foreach ($policyDocumentHelp as $policyDocumentHelpModal) { ?>
        <?php echo sr_admin_help_modal_html((string) $policyDocumentHelpModal['id'], (string) $policyDocumentHelpModal['title'], (string) $policyDocumentHelpModal['body']); ?>
    <?php } ?>
<?php } ?>

<?php if ($policyDocumentAdminPage === 'form' && !$creatingDocument && $policyDocumentStandardTemplateHtml !== '') { ?>
<script>
(function () {
    function policyDocumentText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function policyDocumentHtmlToPlain(html) {
        var documentFragment = new DOMParser().parseFromString(String(html || ''), 'text/html');
        var parts = [];
        Array.prototype.slice.call(documentFragment.body.children).forEach(function (node) {
            var tagName = node.tagName ? node.tagName.toLowerCase() : '';
            if (tagName === 'ul' || tagName === 'ol') {
                Array.prototype.slice.call(node.querySelectorAll('li')).forEach(function (item) {
                    var itemText = policyDocumentText(item.textContent);
                    if (itemText !== '') {
                        parts.push('- ' + itemText);
                    }
                });
                return;
            }

            var text = policyDocumentText(node.textContent);
            if (text !== '') {
                parts.push(text);
            }
        });

        return parts.join("\n\n");
    }

    function policyDocumentHtmlToMarkdown(html) {
        var documentFragment = new DOMParser().parseFromString(String(html || ''), 'text/html');
        var parts = [];
        Array.prototype.slice.call(documentFragment.body.children).forEach(function (node) {
            var tagName = node.tagName ? node.tagName.toLowerCase() : '';
            if (/^h[1-6]$/.test(tagName)) {
                var headingText = policyDocumentText(node.textContent);
                if (headingText !== '') {
                    parts.push('## ' + headingText);
                }
                return;
            }

            if (tagName === 'ul' || tagName === 'ol') {
                var listParts = [];
                Array.prototype.slice.call(node.querySelectorAll('li')).forEach(function (item) {
                    var itemText = policyDocumentText(item.textContent);
                    if (itemText !== '') {
                        listParts.push('- ' + itemText);
                    }
                });
                if (listParts.length > 0) {
                    parts.push(listParts.join("\n"));
                }
                return;
            }

            var text = policyDocumentText(node.textContent);
            if (text !== '') {
                parts.push(text);
            }
        });

        return parts.join("\n\n");
    }

    function policyDocumentSetTextarea(textarea, value) {
        if (!textarea) {
            return;
        }

        textarea.value = value;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function policyDocumentCurrentValue(textarea) {
        if (!textarea) {
            return '';
        }

        var instances = window.srCkeditorInstances || {};
        var editor = instances[textarea.id];
        if (editor && typeof editor.getData === 'function') {
            return String(editor.getData() || '');
        }

        return String(textarea.value || '');
    }

    function policyDocumentHasExistingBody(group) {
        return Array.prototype.slice.call(group.querySelectorAll('textarea')).some(function (textarea) {
            return policyDocumentCurrentValue(textarea).trim() !== '';
        });
    }

    function policyDocumentSetCkeditor(textarea, html) {
        policyDocumentSetTextarea(textarea, html);
        if (!textarea) {
            return;
        }

        var instances = window.srCkeditorInstances || {};
        var editor = instances[textarea.id];
        if (editor && typeof editor.setData === 'function') {
            editor.setData(html);
        }
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-policy-document-standard-template]');
        if (!button) {
            return;
        }

        var group = button.closest('[data-admin-body-editor-mode-group]');
        var templateJson = group ? group.querySelector('[data-policy-document-standard-template-json]') : null;
        if (!group || !templateJson) {
            return;
        }

        var html = '';
        try {
            html = JSON.parse(templateJson.textContent || '""');
        } catch (error) {
            html = '';
        }
        if (html === '') {
            return;
        }

        if (policyDocumentHasExistingBody(group) && !window.confirm(button.getAttribute('data-policy-document-standard-template-confirm') || '현재 본문을 표준 문안으로 교체할까요?')) {
            return;
        }

        var plain = policyDocumentHtmlToPlain(html);
        var markdown = policyDocumentHtmlToMarkdown(html);
        policyDocumentSetTextarea(group.querySelector('textarea[name="body_plain"]'), plain);
        policyDocumentSetTextarea(group.querySelector('textarea[name="body_markdown"]'), markdown);
        policyDocumentSetTextarea(group.querySelector('textarea[name="body_html"]'), html);
        policyDocumentSetCkeditor(group.querySelector('textarea[name="body_ckeditor_html"]'), html);
    });
}());
</script>
<?php } ?>
<?php if ($policyDocumentAdminPage === 'form' && $policyDocumentCkeditorAvailable && isset($pdo) && $pdo instanceof PDO) { ?>
    <?php echo sr_editor_assets_html($pdo, 'ckeditor', 'admin_basic'); ?>
<?php } ?>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
