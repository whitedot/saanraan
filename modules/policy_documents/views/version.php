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
<main class="policy-document-version-page">
    <section class="policy-document-version-section">
        <div class="policy-document-version-container">
            <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
            <?php if ($policyDocumentDateLabel !== '') { ?>
                <p>
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
