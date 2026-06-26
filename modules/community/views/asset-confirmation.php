<?php

$assetConfirmationMessage = (string) ($assetConfirmationMessage ?? '차감 내용을 확인해 주세요.');
$assetConfirmationAction = (string) ($assetConfirmationAction ?? '/community');
$assetConfirmationId = (int) ($assetConfirmationId ?? 0);
$assetConfirmationRequestToken = (string) ($assetConfirmationRequestToken ?? '');
$assetConfirmationCouponIssues = is_array($assetConfirmationCouponIssues ?? null) ? $assetConfirmationCouponIssues : [];
$assetConfirmationTitle = is_array($post ?? null)
    ? (string) (($post['title'] ?? '') ?: sr_t('community::ui.community.4a285775'))
    : sr_t('community::ui.community.4a285775');
$assetConfirmationAssetLabel = (string) ($assetConfirmationAssetLabel ?? ($paidReadResult['asset_label'] ?? $downloadResult['asset_label'] ?? ''));
$assetConfirmationAmount = (int) ($assetConfirmationAmount ?? ($paidReadResult['amount'] ?? $downloadResult['amount'] ?? 0));
$seo = [
    'title' => $assetConfirmationTitle,
    'robots' => 'noindex, nofollow',
];
$assetConfirmationStandaloneLayout = !empty($assetConfirmationStandaloneLayout);
if (!$assetConfirmationStandaloneLayout) {
    include SR_ROOT . '/modules/community/views/asset-confirmation-modal.php';
    return;
}

$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings));
?>
<main class="community-screen">
    <article>
        <?php include SR_ROOT . '/modules/community/views/asset-confirmation-modal.php'; ?>
    </article>
</main>
<?php sr_public_layout_end(); ?>
