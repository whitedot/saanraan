<?php

$pageTitle = sr_t('policy_documents::ui.policy_documents');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<section class="admin-section">
    <div class="admin-section-header">
        <div>
            <h1><?php echo sr_e($pageTitle); ?></h1>
            <p><?php echo sr_e(sr_t('policy_documents::ui.description')); ?></p>
        </div>
    </div>

    <?php if ($notice !== '') { ?>
        <div class="admin-notice"><?php echo sr_e($notice); ?></div>
    <?php } ?>
    <?php if ($errors !== []) { ?>
        <ul class="admin-error-list">
            <?php foreach ($errors as $error) { ?>
                <li><?php echo sr_e($error); ?></li>
            <?php } ?>
        </ul>
    <?php } ?>

    <div class="admin-grid-2">
        <section class="admin-card">
            <h2><?php echo sr_e(sr_t('policy_documents::ui.document.list')); ?></h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.document_key')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.title')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.published_version')); ?></th>
                        <th><?php echo sr_e(sr_t('policy_documents::ui.status')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $document) { ?>
                        <tr>
                            <td><a href="<?php echo sr_e(sr_url('/admin/policy-documents?document_id=' . (string) (int) $document['id'])); ?>"><?php echo sr_e((string) $document['document_key']); ?></a></td>
                            <td><?php echo sr_e((string) $document['title']); ?></td>
                            <td><?php echo sr_e((string) ($document['published_version_key'] ?? '-')); ?></td>
                            <td><?php echo sr_e((string) $document['status']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </section>

        <section class="admin-card">
            <h2><?php echo sr_e(sr_t('policy_documents::ui.version.create')); ?></h2>
            <?php if (is_array($selectedDocument)) { ?>
                <p><?php echo sr_e((string) $selectedDocument['document_key']); ?> / <?php echo sr_e((string) $selectedDocument['title']); ?></p>
                <form method="post" action="<?php echo sr_e(sr_url('/admin/policy-documents?document_id=' . (string) $selectedDocumentId)); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="action" value="create_version">
                    <input type="hidden" name="document_id" value="<?php echo sr_e((string) $selectedDocumentId); ?>">
                    <p>
                        <label class="form-label" for="policy_document_version_key"><?php echo sr_e(sr_t('policy_documents::ui.version_key')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                        <input id="policy_document_version_key" class="form-control" type="text" name="version_key" maxlength="40" pattern="([0-9]{4}\.[0-9]{2}\.[0-9]{3}|v[0-9][a-z0-9_]{0,38})" required>
                        <small class="admin-form-help"><?php echo sr_e(sr_t('policy_documents::ui.version_key.help')); ?></small>
                    </p>
                    <p>
                        <label class="form-label" for="policy_document_title"><?php echo sr_e(sr_t('policy_documents::ui.title')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                        <input id="policy_document_title" class="form-control" type="text" name="title" maxlength="190" value="<?php echo sr_e((string) $selectedDocument['title']); ?>" required>
                    </p>
                    <p>
                        <label class="form-label" for="policy_document_body_html"><?php echo sr_e(sr_t('policy_documents::ui.body')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                        <textarea id="policy_document_body_html" class="form-control" name="body_html" rows="10" required></textarea>
                        <small class="admin-form-help"><?php echo sr_e(sr_t('policy_documents::ui.body.help')); ?></small>
                    </p>
                    <p>
                        <label class="form-label" for="policy_document_summary_text"><?php echo sr_e(sr_t('policy_documents::ui.summary')); ?></label>
                        <textarea id="policy_document_summary_text" class="form-control" name="summary_text" rows="3" maxlength="1000"></textarea>
                    </p>
                    <p>
                        <label class="form-label" for="policy_document_status"><?php echo sr_e(sr_t('policy_documents::ui.status')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('policy_documents::ui.required')); ?></span></label>
                        <select id="policy_document_status" class="form-select" name="status" required>
                            <option value="draft"><?php echo sr_e(sr_t('policy_documents::ui.status.draft')); ?></option>
                            <option value="published"><?php echo sr_e(sr_t('policy_documents::ui.status.published')); ?></option>
                            <option value="archived"><?php echo sr_e(sr_t('policy_documents::ui.status.archived')); ?></option>
                        </select>
                    </p>
                    <p>
                        <label class="form-label" for="policy_document_effective_from"><?php echo sr_e(sr_t('policy_documents::ui.effective_from')); ?></label>
                        <input id="policy_document_effective_from" class="form-control" type="text" name="effective_from" placeholder="2026-06-14 09:00:00">
                    </p>
                    <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('policy_documents::ui.save')); ?></button>
                </form>
            <?php } else { ?>
                <p><?php echo sr_e(sr_t('policy_documents::ui.document.empty')); ?></p>
            <?php } ?>
        </section>
    </div>

    <section class="admin-card">
        <h2><?php echo sr_e(sr_t('policy_documents::ui.version.list')); ?></h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?php echo sr_e(sr_t('policy_documents::ui.version_key')); ?></th>
                    <th><?php echo sr_e(sr_t('policy_documents::ui.title')); ?></th>
                    <th><?php echo sr_e(sr_t('policy_documents::ui.status')); ?></th>
                    <th><?php echo sr_e(sr_t('policy_documents::ui.body_hash')); ?></th>
                    <th><?php echo sr_e(sr_t('policy_documents::ui.published_at')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($versions as $version) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $version['version_key']); ?></td>
                        <td><?php echo sr_e((string) $version['title_snapshot']); ?></td>
                        <td><?php echo sr_e((string) $version['status']); ?></td>
                        <td><code><?php echo sr_e(substr((string) $version['body_hash'], 0, 16)); ?></code></td>
                        <td><?php echo sr_e((string) ($version['published_at'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
                <?php if ($versions === []) { ?>
                    <tr>
                        <td colspan="5" class="admin-empty-state"><?php echo sr_e(sr_t('policy_documents::ui.version.empty')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </section>
</section>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
