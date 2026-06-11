<?php

$assetConfirmationMessage = (string) ($assetConfirmationMessage ?? sr_content_asset_confirmation_required_message());
$assetConfirmationAction = (string) ($assetConfirmationAction ?? '/content');
$assetConfirmationId = (int) ($assetConfirmationId ?? 0);
$assetConfirmationContentId = (int) ($assetConfirmationContentId ?? 0);
$assetConfirmationRequestToken = (string) ($assetConfirmationRequestToken ?? '');
$assetConfirmationTitle = is_array($file ?? null)
    ? (string) (($file['title'] ?? '') ?: sr_t('content::ui.text.0a4ca9bc'))
    : sr_t('content::ui.text.0a4ca9bc');
$seo = [
    'title' => $assetConfirmationTitle,
    'robots' => 'noindex, nofollow',
];
$contentLayoutSettings = isset($contentLayoutSettings) && is_array($contentLayoutSettings) ? $contentLayoutSettings : sr_content_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings));
?>
<main class="content-public content-public-basic">
    <article class="content-article">
        <header class="content-header">
            <h1><?php echo sr_e($assetConfirmationTitle); ?></h1>
            <p><?php echo sr_e($assetConfirmationMessage); ?></p>
        </header>
        <form method="post" action="<?php echo sr_e(sr_url($assetConfirmationAction)); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo sr_e((string) $assetConfirmationId); ?>">
            <input type="hidden" name="asset_request_token" value="<?php echo sr_e($assetConfirmationRequestToken); ?>">
            <?php if ($assetConfirmationContentId > 0) { ?>
                <input type="hidden" name="content_id" value="<?php echo sr_e((string) $assetConfirmationContentId); ?>">
            <?php } ?>
            <button type="submit"><?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?></button>
        </form>
    </article>
</main>
<?php sr_public_layout_end(); ?>
