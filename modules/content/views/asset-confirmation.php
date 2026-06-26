<?php

$assetConfirmationMessage = (string) ($assetConfirmationMessage ?? '차감 내용을 확인해 주세요.');
$assetConfirmationAction = (string) ($assetConfirmationAction ?? '/content');
$assetConfirmationId = (int) ($assetConfirmationId ?? 0);
$assetConfirmationContentId = (int) ($assetConfirmationContentId ?? 0);
$assetConfirmationRequestToken = (string) ($assetConfirmationRequestToken ?? '');
$assetConfirmationCouponIssues = is_array($assetConfirmationCouponIssues ?? null) ? $assetConfirmationCouponIssues : [];
$assetConfirmationTitle = is_array($file ?? null)
    ? (string) (($file['title'] ?? '') ?: sr_t('content::ui.text.0a4ca9bc'))
    : sr_t('content::ui.text.0a4ca9bc');
$assetConfirmationAssetLabel = (string) ($assetConfirmationAssetLabel ?? ($downloadAccess['asset_label'] ?? ''));
$assetConfirmationAmount = (int) ($assetConfirmationAmount ?? ($downloadAccess['amount'] ?? 0));
$seo = [
    'title' => $assetConfirmationTitle,
    'robots' => 'noindex, nofollow',
];
$assetConfirmationStandaloneLayout = !empty($assetConfirmationStandaloneLayout);
if (!$assetConfirmationStandaloneLayout) {
    include SR_ROOT . '/modules/content/views/asset-confirmation-modal.php';
    return;
}

$contentLayoutSettings = isset($contentLayoutSettings) && is_array($contentLayoutSettings) ? $contentLayoutSettings : sr_content_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings));
?>
<main class="content-page content-page-basic">
    <article class="content-article">
        <?php include SR_ROOT . '/modules/content/views/asset-confirmation-modal.php'; ?>
    </article>
</main>
<?php sr_public_layout_end(); ?>
