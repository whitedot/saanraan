<?php

$pageTitle = sr_t('privacy::ui.privacy.guidance.title');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$cookieConsentValue = sr_privacy_cookie_consent_value();
$cookieConsentSelectedItems = sr_privacy_cookie_consent_selected_items();
$cookieConsentCurrent = $cookieConsentSelectedItems !== []
    ? sr_t('privacy::cookie.manage.current.functional')
    : sr_t('privacy::cookie.manage.current.essential');
$cookieConsentReturnTo = sr_member_safe_next_path((string) ($_SERVER['REQUEST_URI'] ?? '/account/privacy-requests'));
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'style_profile' => 'kit',
]);
?>
    <main class="public-ui-scope">
        <section class="public-ui-form-panel">
            <h1 class="public-ui-title type-section-title"><?php echo sr_e($pageTitle); ?></h1>

            <div class="public-ui-content-list">
                <p class="public-ui-copy type-body"><?php echo sr_e(sr_t('privacy::ui.privacy.guidance.body.1')); ?></p>
                <p class="public-ui-copy type-body"><?php echo sr_e(sr_t('privacy::ui.privacy.guidance.body.2')); ?></p>
                <p><a href="<?php echo sr_e(sr_url('/account')); ?>"><?php echo sr_e(sr_t('privacy::ui.text.13b28045')); ?></a></p>
            </div>
        </section>

        <section class="public-ui-form-panel">
            <h2 class="public-ui-title type-card-title"><?php echo sr_e(sr_t('privacy::cookie.manage.title')); ?></h2>
            <div class="public-ui-content-list">
                <p class="public-ui-copy type-body"><?php echo sr_e(sr_t('privacy::cookie.manage.body')); ?></p>
                <p class="public-ui-feedback type-small"><?php echo sr_e($cookieConsentCurrent); ?></p>
                <div class="public-ui-actions">
                    <form method="post" action="<?php echo sr_e(sr_url('/privacy/cookie-consent')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="return_to" value="<?php echo sr_e($cookieConsentReturnTo); ?>">
                        <input type="hidden" name="consent" value="essential">
                        <button type="submit" class="public-ui-button public-ui-button-secondary"><?php echo sr_e(sr_t('privacy::cookie.essential')); ?></button>
                    </form>
                    <form method="post" action="<?php echo sr_e(sr_url('/privacy/cookie-consent')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="return_to" value="<?php echo sr_e($cookieConsentReturnTo); ?>">
                        <input type="hidden" name="consent" value="custom">
                        <?php echo sr_privacy_cookie_consent_items_fields_html($cookieConsentSelectedItems); ?>
                        <button type="submit" class="public-ui-button"><?php echo sr_e(sr_t('privacy::cookie.save.selection')); ?></button>
                    </form>
                </div>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
