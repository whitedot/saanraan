<?php

$pageTitle = sr_t('privacy::cookie.manage.title');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, []);
?>
    <main class="ui-page">
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>
            <div class="card-body ui-card-body-stack">
                <p class="type-body"><?php echo sr_e(sr_t('privacy::cookie.manage.body')); ?></p>
                <section class="ui-card-body-stack" aria-label="<?php echo sr_e(sr_t('privacy::cookie.essential.group')); ?>">
                    <h2 class="card-title"><?php echo sr_e(sr_t('privacy::cookie.essential.group')); ?></h2>
                    <?php echo sr_privacy_cookie_consent_essential_fields_html(); ?>
                </section>
                <form method="post" action="<?php echo sr_e(sr_url('/privacy/cookie-consent')); ?>" class="ui-card-body-stack">
                    <h2 class="card-title"><?php echo sr_e(sr_t('privacy::cookie.optional.group')); ?></h2>
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="return_to" value="<?php echo sr_e($cookieConsentReturnTo); ?>">
                    <input type="hidden" name="consent" value="selected">
                    <?php echo sr_privacy_cookie_consent_items_fields_html($cookieConsentSelectedItems); ?>
                    <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('privacy::cookie.save.selection')); ?></button>
                </form>
                <div class="ui-page-header">
                    <form method="post" action="<?php echo sr_e(sr_url('/privacy/cookie-consent')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="return_to" value="<?php echo sr_e($cookieConsentReturnTo); ?>">
                        <input type="hidden" name="consent" value="reject">
                        <button type="submit" class="btn btn-outline-default"><?php echo sr_e(sr_t('privacy::cookie.reject')); ?></button>
                    </form>
                    <form method="post" action="<?php echo sr_e(sr_url('/privacy/cookie-consent')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="return_to" value="<?php echo sr_e($cookieConsentReturnTo); ?>">
                        <input type="hidden" name="consent" value="all">
                        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('privacy::cookie.all')); ?></button>
                    </form>
                </div>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
