<?php

$policyDocumentAdminPage = isset($policyDocumentAdminPage) ? (string) $policyDocumentAdminPage : 'list';
$policyDocumentFormMode = isset($policyDocumentFormMode) ? (string) $policyDocumentFormMode : 'new';
$editingVersion = $policyDocumentAdminPage === 'form' && $policyDocumentFormMode === 'edit' && is_array($formVersion);
$pageTitle = $policyDocumentAdminPage === 'form'
    ? ($editingVersion ? sr_t('policy_documents::ui.version.edit') : sr_t('policy_documents::ui.version.create'))
    : sr_t('policy_documents::ui.policy_documents');
$adminPageTitle = $pageTitle;
$adminPageSubtitle = $policyDocumentAdminPage === 'form' ? sr_t('policy_documents::ui.version.form_description') : sr_t('policy_documents::ui.description');
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

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($policyDocumentAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/policy-documents/save')); ?>" class="admin-form ui-form-theme" data-sr-validate-form>
        <section class="admin-card card">
            <h2><?php echo sr_e($pageTitle); ?></h2>
            <p class="admin-form-help"><?php echo sr_e(sr_t('policy_documents::ui.version.form_description')); ?></p>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="action" value="save_version">
            <input type="hidden" name="form_mode" value="<?php echo $editingVersion ? 'edit' : 'new'; ?>">
            <input type="hidden" name="document_id" value="<?php echo sr_e((string) $selectedDocumentId); ?>">
            <?php if ($editingVersion) { ?>
                <input type="hidden" name="version_id" value="<?php echo sr_e((string) (int) $formVersion['id']); ?>">
            <?php } ?>

            <?php if (is_array($selectedDocument)) { ?>
                <div class="admin-form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('policy_documents::ui.document.selected')); ?></span>
                    <div class="admin-form-field">
                        <?php echo sr_e((string) $selectedDocument['document_key']); ?>
                        <p class="admin-form-help"><?php echo sr_e((string) $selectedDocument['title']); ?></p>
                    </div>
                </div>
            <?php } ?>

            <?php if ($editingVersion) { ?>
                <div class="admin-form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('policy_documents::ui.version_key')); ?></span>
                    <div class="admin-form-field">
                        <?php echo sr_e((string) $formVersion['version_key']); ?>
                        <p class="admin-form-help"><?php echo sr_e(sr_t('policy_documents::error.version_edit_draft_only')); ?></p>
                    </div>
                </div>
            <?php } else { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="policy_document_version_key"><?php echo sr_e(sr_t('policy_documents::ui.version_key')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="policy_document_version_key" class="form-input" type="text" name="version_key" maxlength="40" pattern="[A-Za-z0-9._-]{1,40}" placeholder="<?php echo sr_e(sr_t('policy_documents::ui.version_key.placeholder')); ?>" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-version-key-input data-validation-message="<?php echo sr_e(sr_t('policy_documents::error.version_key_invalid')); ?>">
                        <p class="admin-form-help"><?php echo sr_e(sr_t('policy_documents::ui.version_key.help')); ?></p>
                    </div>
                </div>
            <?php } ?>

            <div class="admin-form-row">
                <label class="form-label" for="policy_document_title"><?php echo sr_e(sr_t('policy_documents::ui.title')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                <div class="admin-form-field">
                    <input id="policy_document_title" class="form-input form-control-full" type="text" name="title" maxlength="190" value="<?php echo sr_e($policyDocumentVersionValue('title')); ?>" required>
                </div>
            </div>

            <div class="admin-form-row">
                <label class="form-label" for="policy_document_body_html"><?php echo sr_e(sr_t('policy_documents::ui.body')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                <div class="admin-form-field">
                    <textarea id="policy_document_body_html" class="form-textarea form-control-full" name="body_html" rows="14" required><?php echo sr_e($policyDocumentVersionValue('body_html')); ?></textarea>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('policy_documents::ui.body.help')); ?></p>
                </div>
            </div>

            <div class="admin-form-row">
                <label class="form-label" for="policy_document_summary_text"><?php echo sr_e(sr_t('policy_documents::ui.summary')); ?></label>
                <div class="admin-form-field">
                    <textarea id="policy_document_summary_text" class="form-textarea form-control-full" name="summary_text" rows="3" maxlength="1000"><?php echo sr_e($policyDocumentVersionValue('summary_text')); ?></textarea>
                </div>
            </div>

            <?php if (!$editingVersion) { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="policy_document_status"><?php echo sr_e(sr_t('policy_documents::ui.status')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="policy_document_status" class="form-select" name="status" required>
                            <option value="draft"><?php echo sr_e(sr_t('policy_documents::ui.status.draft')); ?></option>
                            <option value="published"><?php echo sr_e(sr_t('policy_documents::ui.status.published')); ?></option>
                            <option value="archived"><?php echo sr_e(sr_t('policy_documents::ui.status.archived')); ?></option>
                        </select>
                    </div>
                </div>
            <?php } ?>

            <div class="admin-form-row">
                <label class="form-label" for="policy_document_effective_from"><?php echo sr_e(sr_t('policy_documents::ui.effective_from')); ?></label>
                <div class="admin-form-field">
                    <input id="policy_document_effective_from" class="form-input" type="datetime-local" name="effective_from" value="<?php echo sr_e(str_replace(' ', 'T', $policyDocumentVersionValue('effective_from'))); ?>">
                </div>
            </div>
        </section>

        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/policy-documents?document_id=' . (string) $selectedDocumentId)); ?>" class="btn btn-solid-light"><?php echo sr_e('취소'); ?></a>
            <button class="btn btn-solid-primary" type="submit"><?php echo sr_e($editingVersion ? sr_t('policy_documents::ui.save_update') : sr_t('policy_documents::ui.save_new_version')); ?></button>
        </div>
    </form>
<?php } else { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('policy_documents::ui.document.list')); ?></h2>
        </div>
        <div class="table-wrapper">
            <table class="table admin-policy-document-table">
                <caption class="sr-only"><?php echo sr_e(sr_t('policy_documents::ui.document.list')); ?></caption>
                <thead class="ui-table-head">
                    <tr>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.document_key')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.title')); ?></th>
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
                            <td class="admin-table-nowrap"><a href="<?php echo sr_e(sr_url('/admin/policy-documents?document_id=' . (string) (int) $document['id'])); ?>"><?php echo sr_e((string) $document['document_key']); ?></a></td>
                            <td class="admin-table-break"><?php echo sr_e((string) $document['title']); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($document['published_version_key'] ?? '-')); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($policyDocumentStatusClass($documentStatus)); ?>"><?php echo sr_e(sr_admin_code_label($documentStatus, 'content_status')); ?></span></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/policy-documents/new?document_id=' . (string) (int) $document['id'])); ?>" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e(sr_t('policy_documents::ui.version.new')); ?>" title="<?php echo sr_e(sr_t('policy_documents::ui.version.new')); ?>"><?php echo sr_material_icon_html('add'); ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('policy_documents::ui.version.list')); ?></h2>
            <?php if (is_array($selectedDocument)) { ?>
                <a href="<?php echo sr_e(sr_url('/admin/policy-documents/new?document_id=' . (string) $selectedDocumentId)); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('policy_documents::ui.version.new')); ?></a>
            <?php } ?>
        </div>
        <?php if (is_array($selectedDocument)) { ?>
            <p class="admin-form-help"><?php echo sr_e((string) $selectedDocument['document_key']); ?> <?php echo sr_e((string) $selectedDocument['title']); ?></p>
        <?php } ?>
        <div class="table-wrapper">
            <table class="table admin-policy-document-version-table">
                <caption class="sr-only"><?php echo sr_e(sr_t('policy_documents::ui.version.list')); ?></caption>
                <thead class="ui-table-head">
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
                    <?php if ($versions === []) { ?>
                        <tr>
                            <td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('policy_documents::ui.version.empty')); ?></td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($versions as $version) { ?>
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
    </section>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('policy_documents::ui.mail_jobs')); ?></h2>
        </div>
        <div class="table-wrapper">
            <table class="table admin-policy-document-mail-job-table">
                <caption class="sr-only"><?php echo sr_e(sr_t('policy_documents::ui.mail_jobs')); ?></caption>
                <thead class="ui-table-head">
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
                            <td class="admin-table-nowrap"><?php echo sr_e((string) (int) $mailJob['sent_count']); ?> / <?php echo sr_e((string) (int) $mailJob['delivery_count']); ?></td>
                            <td class="admin-table-actions-cell">
                                <?php if ((int) $mailJob['queued_count'] > 0) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/policy-documents')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="action" value="run_mail_batch">
                                        <input type="hidden" name="job_id" value="<?php echo sr_e((string) (int) $mailJob['id']); ?>">
                                        <button class="btn btn-sm btn-solid-primary" type="submit"><?php echo sr_e(sr_t('policy_documents::ui.mail_run')); ?></button>
                                    </form>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
