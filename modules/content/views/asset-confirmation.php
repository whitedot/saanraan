<?php

$assetConfirmationMessage = (string) ($assetConfirmationMessage ?? sr_content_asset_confirmation_required_message());
$assetConfirmationAction = (string) ($assetConfirmationAction ?? '/content');
$assetConfirmationId = (int) ($assetConfirmationId ?? 0);
$assetConfirmationTitle = is_array($file ?? null)
    ? (string) (($file['title'] ?? '') ?: sr_t('content::ui.text.0a4ca9bc'))
    : sr_t('content::ui.text.0a4ca9bc');
$seo = [
    'title' => $assetConfirmationTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
<main>
    <article>
        <h1><?php echo sr_e($assetConfirmationTitle); ?></h1>
        <p><?php echo sr_e($assetConfirmationMessage); ?></p>
        <form method="post" action="<?php echo sr_e(sr_url($assetConfirmationAction)); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo sr_e((string) $assetConfirmationId); ?>">
            <button type="submit"><?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?></button>
        </form>
    </article>
</main>
<?php sr_public_layout_end(); ?>
