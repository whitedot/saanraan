<?php

$assetConfirmationMessage = (string) ($assetConfirmationMessage ?? sr_community_asset_confirmation_required_message());
$assetConfirmationAction = (string) ($assetConfirmationAction ?? '/community');
$assetConfirmationId = (int) ($assetConfirmationId ?? 0);
$assetConfirmationRequestToken = (string) ($assetConfirmationRequestToken ?? '');
$assetConfirmationTitle = is_array($post ?? null)
    ? (string) (($post['title'] ?? '') ?: sr_t('community::ui.community.4a285775'))
    : sr_t('community::ui.community.4a285775');
$seo = [
    'title' => $assetConfirmationTitle,
    'robots' => 'noindex, nofollow',
];
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings));
?>
<main class="community-screen">
    <article>
        <h1><?php echo sr_e($assetConfirmationTitle); ?></h1>
        <p><?php echo sr_e($assetConfirmationMessage); ?></p>
        <form method="post" action="<?php echo sr_e(sr_url($assetConfirmationAction)); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo sr_e((string) $assetConfirmationId); ?>">
            <input type="hidden" name="asset_request_token" value="<?php echo sr_e($assetConfirmationRequestToken); ?>">
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.text.ac5b575f')); ?></button>
        </form>
    </article>
</main>
<?php sr_public_layout_end(); ?>
