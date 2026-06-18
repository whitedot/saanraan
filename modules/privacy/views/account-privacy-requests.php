<?php

$pageTitle = sr_t('privacy::ui.privacy.guidance.title');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$cookieConsentSelectedItems = sr_privacy_cookie_consent_selected_items();
$cookieConsentCurrent = $cookieConsentSelectedItems !== []
    ? sr_t('privacy::cookie.manage.current.functional')
    : sr_t('privacy::cookie.manage.current.essential');
$cookieConsentReturnTo = sr_member_safe_next_path((string) ($_SERVER['REQUEST_URI'] ?? '/account/privacy-requests'));
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, []);
?>
    <main class="ui-page">
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>

            <div class="card-body ui-card-body-stack">
                <p class="type-body"><?php echo sr_e(sr_t('privacy::ui.privacy.guidance.body.1')); ?></p>
                <p class="type-body"><?php echo sr_e(sr_t('privacy::ui.privacy.guidance.body.2')); ?></p>
                <p><a href="<?php echo sr_e(sr_url('/account')); ?>"><?php echo sr_e(sr_t('privacy::ui.text.13b28045')); ?></a></p>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title"><?php echo sr_e(sr_t('privacy::cookie.manage.title')); ?></h2>
            </div>
            <div class="card-body ui-card-body-stack">
                <p class="type-body"><?php echo sr_e(sr_t('privacy::cookie.manage.body')); ?></p>
                <p class="ui-feedback type-small"><?php echo sr_e($cookieConsentCurrent); ?></p>
                <div class="ui-page-header">
                    <a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url(sr_privacy_cookie_settings_path($cookieConsentReturnTo))); ?>"><?php echo sr_e(sr_t('privacy::cookie.selected')); ?></a>
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
