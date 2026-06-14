<?php

$pageTitle = sr_t('privacy::cookie.manage.title');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'style_profile' => 'kit',
]);
?>
    <main class="public-ui-scope">
        <section class="public-ui-form-panel">
            <h1 class="public-ui-title type-section-title"><?php echo sr_e($pageTitle); ?></h1>
            <div class="public-ui-content-list">
                <p class="public-ui-copy type-body"><?php echo sr_e(sr_t('privacy::cookie.manage.body')); ?></p>
                <form method="post" action="<?php echo sr_e(sr_url('/privacy/cookie-consent')); ?>" class="public-ui-content-list">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="return_to" value="<?php echo sr_e($cookieConsentReturnTo); ?>">
                    <input type="hidden" name="consent" value="selected">
                    <?php echo sr_privacy_cookie_consent_items_fields_html($cookieConsentSelectedItems); ?>
                    <button type="submit" class="public-ui-button"><?php echo sr_e(sr_t('privacy::cookie.save.selection')); ?></button>
                </form>
                <div class="public-ui-actions">
                    <form method="post" action="<?php echo sr_e(sr_url('/privacy/cookie-consent')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="return_to" value="<?php echo sr_e($cookieConsentReturnTo); ?>">
                        <input type="hidden" name="consent" value="reject">
                        <button type="submit" class="public-ui-button public-ui-button-secondary"><?php echo sr_e(sr_t('privacy::cookie.reject')); ?></button>
                    </form>
                    <form method="post" action="<?php echo sr_e(sr_url('/privacy/cookie-consent')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="return_to" value="<?php echo sr_e($cookieConsentReturnTo); ?>">
                        <input type="hidden" name="consent" value="all">
                        <button type="submit" class="public-ui-button"><?php echo sr_e(sr_t('privacy::cookie.all')); ?></button>
                    </form>
                </div>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
