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
        'enabled', 'published' => 'is-normal',
        'draft' => 'is-left',
        'disabled', 'archived' => 'is-blocked',
        default => 'is-left',
    };
};
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
$policyDocumentBodyEditorMode = $policyDocumentCkeditorAvailable ? 'ckeditor' : 'html';
$policyDocumentBodyEditorOptions = [
    'plain' => sr_t('policy_documents::ui.body_mode.plain'),
    'html' => sr_t('policy_documents::ui.body_mode.html'),
];
if ($policyDocumentCkeditorAvailable) {
    $policyDocumentBodyEditorOptions['ckeditor'] = sr_t('policy_documents::ui.body_mode.ckeditor');
}
$policyDocumentCkeditorAttributes = $policyDocumentCkeditorAvailable && isset($pdo) && $pdo instanceof PDO
    ? sr_editor_textarea_attributes($pdo, 'ckeditor', 'admin_basic', 'body_editor_format')
    : '';
$policyDocumentVersionsByDocumentId = [];
foreach ($versions as $policyDocumentVersionRow) {
    $policyDocumentVersionsByDocumentId[(int) $policyDocumentVersionRow['document_id']][] = $policyDocumentVersionRow;
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
                    <label class="form-label" for="policy_document_document_key"><?php echo sr_e(sr_t('policy_documents::ui.document_key')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                    <div class="form-field">
                        <input id="policy_document_document_key" class="form-input" type="text" name="document_key" maxlength="80" pattern="[a-z][a-z0-9_]{2,79}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input data-validation-message="<?php echo sr_e(sr_t('policy_documents::error.document_key_invalid')); ?>">
                        <p class="form-help"><?php echo sr_e(sr_t('policy_documents::ui.document_key.help')); ?></p>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label" for="policy_document_document_description"><?php echo sr_e(sr_t('policy_documents::ui.document_description')); ?></label>
                    <div class="form-field">
                        <textarea id="policy_document_document_description" class="form-textarea form-control-full" name="description" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label" for="policy_document_document_status"><?php echo sr_e(sr_t('policy_documents::ui.status')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                    <div class="form-field">
                        <select id="policy_document_document_status" class="form-select" name="status" required>
                            <option value="enabled"><?php echo sr_e(sr_t('policy_documents::ui.status.enabled')); ?></option>
                            <option value="disabled"><?php echo sr_e(sr_t('policy_documents::ui.status.disabled')); ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label" for="policy_document_document_sort_order"><?php echo sr_e(sr_t('policy_documents::ui.sort_order')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                    <div class="form-field">
                        <input id="policy_document_document_sort_order" class="form-input" type="number" name="sort_order" min="0" max="1000000" value="100" required>
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

            <?php if ($editingVersion) { ?>
                <div class="form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('policy_documents::ui.version_key')); ?></span>
                    <div class="form-field">
                        <?php echo sr_e((string) $formVersion['version_key']); ?>
                        <p class="form-help"><?php echo sr_e(sr_t('policy_documents::error.version_edit_draft_only')); ?></p>
                    </div>
                </div>
            <?php } else { ?>
                <div class="form-row">
                    <label class="form-label" for="policy_document_version_key"><?php echo sr_e(sr_t('policy_documents::ui.version_key')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                    <div class="form-field">
                        <input id="policy_document_version_key" class="form-input" type="text" name="version_key" maxlength="40" pattern="[A-Za-z0-9._-]{1,40}" placeholder="<?php echo sr_e(sr_t('policy_documents::ui.version_key.placeholder')); ?>" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-version-key-input data-validation-message="<?php echo sr_e(sr_t('policy_documents::error.version_key_invalid')); ?>">
                        <p class="form-help"><?php echo sr_e(sr_t('policy_documents::ui.version_key.help')); ?></p>
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
                <label class="form-label" for="policy_document_body_html"><?php echo sr_e(sr_t('policy_documents::ui.body')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                <div class="form-field" data-admin-body-editor-mode-group>
                    <div>
                        <?php echo sr_admin_radio_toggle_group_html('policy_document_body_editor_mode', 'body_editor_mode', $policyDocumentBodyEditorOptions, $policyDocumentBodyEditorMode, true, ' data-admin-body-editor-mode'); ?>
                    </div>
                    <div class="btn-space-before" data-admin-body-editor-panel="plain"<?php echo $policyDocumentBodyEditorMode === 'plain' ? '' : ' hidden'; ?>>
                        <textarea id="policy_document_body_plain" class="form-textarea form-control-full" name="body_plain" rows="14"><?php echo sr_e($policyDocumentBodyTextValue($policyDocumentBodyHtmlValue)); ?></textarea>
                    </div>
                    <div class="btn-space-before" data-admin-body-editor-panel="html"<?php echo $policyDocumentBodyEditorMode === 'html' ? '' : ' hidden'; ?>>
                        <textarea id="policy_document_body_html" class="form-textarea form-control-full" name="body_html" rows="14"><?php echo sr_e($policyDocumentBodyHtmlValue); ?></textarea>
                    </div>
                    <?php if ($policyDocumentCkeditorAvailable) { ?>
                        <div class="btn-space-before" data-admin-body-editor-panel="ckeditor"<?php echo $policyDocumentBodyEditorMode === 'ckeditor' ? '' : ' hidden'; ?>>
                            <textarea id="policy_document_body_ckeditor_html" class="form-textarea form-control-full" name="body_ckeditor_html" rows="14"<?php echo $policyDocumentCkeditorAttributes; ?>><?php echo sr_e($policyDocumentBodyHtmlValue); ?></textarea>
                        </div>
                    <?php } ?>
                    <p class="form-help"><?php echo sr_e(sr_t('policy_documents::ui.body.help')); ?></p>
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="policy_document_summary_text"><?php echo sr_e(sr_t('policy_documents::ui.summary')); ?></label>
                <div class="form-field">
                    <textarea id="policy_document_summary_text" class="form-textarea form-control-full" name="summary_text" rows="3" maxlength="1000"><?php echo sr_e($policyDocumentVersionValue('summary_text')); ?></textarea>
                </div>
            </div>

            <?php if (!$editingVersion) { ?>
                <div class="form-row">
                    <label class="form-label" for="policy_document_status"><?php echo sr_e(sr_t('policy_documents::ui.status')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
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
                <label class="form-label" for="policy_document_effective_from"><?php echo sr_e(sr_t('policy_documents::ui.effective_from')); ?></label>
                <div class="form-field">
                    <input id="policy_document_effective_from" class="form-input" type="datetime-local" name="effective_from" value="<?php echo sr_e(str_replace(' ', 'T', $policyDocumentVersionValue('effective_from'))); ?>">
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
                        <th><?php echo sr_e(sr_t('policy_documents::ui.published_version')); ?></th>
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
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($document['published_version_key'] ?? '-')); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($policyDocumentStatusClass($documentStatus)); ?>"><?php echo sr_e(sr_admin_code_label($documentStatus, 'content_status')); ?></span></td>
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
        <div id="<?php echo sr_e($policyDocumentVersionModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($policyDocumentVersionModalId); ?>-label" aria-hidden="true" inert>
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
                                            <th><?php echo sr_e(sr_t('policy_documents::ui.version_key')); ?></th>
                                            <th><?php echo sr_e(sr_t('policy_documents::ui.title')); ?></th>
                                            <th><?php echo sr_e(sr_t('policy_documents::ui.status')); ?></th>
                                            <th><?php echo sr_e(sr_t('policy_documents::ui.body_hash')); ?></th>
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
                                            <?php $versionStatus = (string) $version['status']; ?>
                                            <tr>
                                                <td class="admin-table-nowrap"><?php echo sr_e((string) $version['version_key']); ?></td>
                                                <td class="admin-table-break"><?php echo sr_e((string) $version['title_snapshot']); ?></td>
                                                <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($policyDocumentStatusClass($versionStatus)); ?>"><?php echo sr_e(sr_admin_code_label($versionStatus, 'content_status')); ?></span></td>
                                                <td class="admin-table-nowrap"><?php echo sr_e(substr((string) $version['body_hash'], 0, 16)); ?></td>
                                                <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($version['published_at'] ?? ''), '-'); ?></td>
                                                <td class="admin-table-actions-cell">
                                                    <?php if ($versionStatus === 'draft') { ?>
                                                        <div class="admin-row-actions">
                                                            <a href="<?php echo sr_e(sr_url('/admin/policy-documents/edit?id=' . (string) (int) $version['id'])); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('policy_documents::ui.edit')); ?>" title="<?php echo sr_e(sr_t('policy_documents::ui.edit')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                                            <form method="post" action="<?php echo sr_e(sr_url('/admin/policy-documents')); ?>" class="admin-inline-form">
                                                                <?php echo sr_csrf_field(); ?>
                                                                <input type="hidden" name="action" value="publish_version">
                                                                <input type="hidden" name="version_id" value="<?php echo sr_e((string) (int) $version['id']); ?>">
                                                                <input type="hidden" name="document_id" value="<?php echo sr_e((string) (int) $version['document_id']); ?>">
                                                                <button type="submit" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e(sr_t('policy_documents::ui.publish')); ?>" title="<?php echo sr_e(sr_t('policy_documents::ui.publish')); ?>" onclick="return confirm('<?php echo sr_e(sr_t('policy_documents::ui.publish.confirm')); ?>');"><?php echo sr_material_icon_html('publish'); ?></button>
                                                            </form>
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
    <?php } ?>

    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('policy_documents::ui.mail_jobs')); ?></h2>
        </div>
        <div class="table-wrapper">
            <table class="table table-list admin-policy-document-mail-job-table">
                <caption class="sr-only"><?php echo sr_e(sr_t('policy_documents::ui.mail_jobs')); ?></caption>
                <thead>
                    <tr>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.document_key')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.version_key')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.status')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.mail_counts')); ?></th>
                        <th class="text-end"><?php echo sr_e(sr_t('policy_documents::ui.action')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($mailJobs === []) { ?>
                        <tr>
                            <td colspan="5" class="admin-empty-state"><?php echo sr_e(sr_t('policy_documents::ui.mail_job_empty')); ?></td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($mailJobs as $mailJob) { ?>
                        <tr>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) $mailJob['document_key']); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) $mailJob['version_key']); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($policyDocumentStatusClass((string) $mailJob['status'])); ?>"><?php echo sr_e((string) $mailJob['status']); ?></span></td>
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
                                        <button class="btn btn-sm btn-solid-primary" type="submit"><?php echo sr_e(sr_t('policy_documents::ui.mail_run')); ?></button>
                                    </form>
                                <?php } ?>
                                <?php if ((int) $mailJob['failed_count'] > 0) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/policy-documents')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="action" value="requeue_mail_failures">
                                        <input type="hidden" name="job_id" value="<?php echo sr_e((string) (int) $mailJob['id']); ?>">
                                        <button class="btn btn-sm btn-solid-light" type="submit"><?php echo sr_e('실패 재대기'); ?></button>
                                    </form>
                                <?php } ?>
                                <?php if ((int) $mailJob['queued_count'] > 0 || (int) ($mailJob['processing_count'] ?? 0) > 0 || (int) $mailJob['failed_count'] > 0) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/policy-documents')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="action" value="cancel_mail_pending">
                                        <input type="hidden" name="job_id" value="<?php echo sr_e((string) (int) $mailJob['id']); ?>">
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
        <?php echo sr_admin_status_description_list_html('policy_document_mail_status', ['queued' => '대기', 'processing' => '처리중', 'sent' => '발송 완료', 'failed' => '실패', 'skipped' => '건너뜀', 'cancelled' => '취소']); ?>
    </section>
<?php } ?>

<?php if ($policyDocumentAdminPage === 'form' && !$creatingDocument && $policyDocumentCkeditorAvailable && isset($pdo) && $pdo instanceof PDO) { ?>
    <?php echo sr_editor_assets_html($pdo, 'ckeditor', 'admin_basic'); ?>
<?php } ?>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
