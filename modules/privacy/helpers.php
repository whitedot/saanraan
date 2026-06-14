<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/privacy/helpers/requests.php';

function sr_privacy_cookie_consent_cookie_name(): string
{
    return 'sr_cookie_consent';
}

function sr_privacy_cookie_consent_values(): array
{
    return ['essential', 'functional'];
}

function sr_privacy_cookie_consent_value(): string
{
    $value = (string) ($_COOKIE[sr_privacy_cookie_consent_cookie_name()] ?? '');

    return in_array($value, sr_privacy_cookie_consent_values(), true) ? $value : '';
}

function sr_privacy_cookie_consent_allows(string $category): bool
{
    if ($category === 'essential') {
        return true;
    }

    return $category === 'functional' && sr_privacy_cookie_consent_value() === 'functional';
}

function sr_privacy_cookie_consent_max_age_seconds(): int
{
    return 15552000;
}

function sr_privacy_cookie_consent_cookie_options(int $expires): array
{
    $cookiePath = sr_base_path();
    $cookiePath = $cookiePath === '' ? '/' : $cookiePath;

    return [
        'expires' => $expires,
        'path' => $cookiePath,
        'secure' => function_exists('sr_session_cookie_secure') ? sr_session_cookie_secure(sr_runtime_config()) : false,
        'httponly' => false,
        'samesite' => 'Lax',
    ];
}

function sr_privacy_cookie_consent_set(string $value): void
{
    if (!in_array($value, sr_privacy_cookie_consent_values(), true)) {
        $value = 'essential';
    }

    $_COOKIE[sr_privacy_cookie_consent_cookie_name()] = $value;
    setcookie(
        sr_privacy_cookie_consent_cookie_name(),
        $value,
        sr_privacy_cookie_consent_cookie_options(time() + sr_privacy_cookie_consent_max_age_seconds())
    );
}

function sr_privacy_cookie_consent_public_html(?PDO $pdo = null): string
{
    if (sr_privacy_cookie_consent_value() !== '') {
        return '';
    }

    $returnTo = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $returnTo = sr_member_safe_next_path($returnTo);

    ob_start();
    ?>
    <section class="sr-cookie-consent" aria-label="<?php echo sr_e(sr_t('privacy::cookie.title')); ?>" data-sr-cookie-consent>
        <div class="sr-cookie-consent-text">
            <strong><?php echo sr_e(sr_t('privacy::cookie.title')); ?></strong>
            <p><?php echo sr_e(sr_t('privacy::cookie.body')); ?></p>
        </div>
        <div class="sr-cookie-consent-actions">
            <form method="post" action="<?php echo sr_e(sr_url('/privacy/cookie-consent')); ?>">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="return_to" value="<?php echo sr_e($returnTo); ?>">
                <input type="hidden" name="consent" value="essential">
                <button type="submit" class="sr-cookie-consent-button sr-cookie-consent-button-secondary"><?php echo sr_e(sr_t('privacy::cookie.essential')); ?></button>
            </form>
            <form method="post" action="<?php echo sr_e(sr_url('/privacy/cookie-consent')); ?>">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="return_to" value="<?php echo sr_e($returnTo); ?>">
                <input type="hidden" name="consent" value="functional">
                <button type="submit" class="sr-cookie-consent-button sr-cookie-consent-button-primary"><?php echo sr_e(sr_t('privacy::cookie.functional')); ?></button>
            </form>
        </div>
    </section>
    <?php
    $html = ob_get_clean();

    return is_string($html) ? $html : '';
}
