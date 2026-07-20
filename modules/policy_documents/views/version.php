<?php

$policyDocumentDateSource = sr_policy_document_history_date_source($policyDocumentVersion);
$policyDocumentDateLabel = $policyDocumentDateSource !== '' ? sr_policy_document_history_date_label($policyDocumentDateSource) : '';
$pageTitle = (string) ($policyDocumentVersion['title_snapshot'] ?? $policyDocumentVersion['document_title'] ?? sr_t('policy_documents::ui.policy_documents'));
if ($policyDocumentDateLabel !== '') {
    $pageTitle .= ' - ' . $policyDocumentDateLabel;
}
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, follow',
];
$policyDocumentBodyHtml = sr_policy_document_render_body_html($pdo, $policyDocumentVersion);

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, []);
?>
<main class="ui-page policy-document-version-page">
    <section class="card policy-document-version-section">
        <div class="card-header">
            <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
        </div>
        <div class="card-body ui-card-body-stack policy-document-version-container">
            <?php if ($policyDocumentDateLabel !== '') { ?>
                <p class="ui-kit-hint">
                    <time datetime="<?php echo sr_e(str_replace(' ', 'T', $policyDocumentDateSource)); ?>"><?php echo sr_e($policyDocumentDateLabel); ?></time>
                </p>
            <?php } ?>
            <div class="policy-document-version-body">
                <?php echo $policyDocumentBodyHtml !== '' ? $policyDocumentBodyHtml : '<p>' . sr_e(sr_t('policy_documents::ui.version.body_empty')) . '</p>'; ?>
            </div>
        </div>
    </section>
</main>
<?php sr_public_layout_end(); ?>
