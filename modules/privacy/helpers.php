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

function sr_privacy_cookie_consent_optional_items(): array
{
    return [
        'popup_dismissal' => [
            'label' => sr_t('privacy::cookie.item.popup_dismissal.label'),
            'description' => sr_t('privacy::cookie.item.popup_dismissal.description'),
        ],
    ];
}

function sr_privacy_cookie_consent_optional_item_keys(): array
{
    return array_keys(sr_privacy_cookie_consent_optional_items());
}

function sr_privacy_cookie_consent_value(): string
{
    $value = (string) ($_COOKIE[sr_privacy_cookie_consent_cookie_name()] ?? '');
    if (preg_match('/\Aitems:[a-z0-9_,]*\z/', $value) === 1) {
        return $value;
    }

    return in_array($value, sr_privacy_cookie_consent_values(), true) ? $value : '';
}

function sr_privacy_cookie_consent_selected_items(): array
{
    $value = sr_privacy_cookie_consent_value();
    if ($value === 'functional') {
        return sr_privacy_cookie_consent_optional_item_keys();
    }

    if ($value === '' || $value === 'essential') {
        return [];
    }

    if (strpos($value, 'items:') !== 0) {
        return [];
    }

    $allowed = sr_privacy_cookie_consent_optional_item_keys();
    $items = array_filter(explode(',', substr($value, 6)), 'strlen');
    $items = array_values(array_unique(array_filter($items, static function (string $item) use ($allowed): bool {
        return in_array($item, $allowed, true);
    })));
    sort($items, SORT_STRING);

    return $items;
}

function sr_privacy_cookie_consent_allows(string $category): bool
{
    if ($category === 'essential') {
        return true;
    }

    $selectedItems = sr_privacy_cookie_consent_selected_items();
    if ($category === 'functional') {
        return $selectedItems !== [];
    }

    return in_array($category, $selectedItems, true);
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
    if (!in_array($value, sr_privacy_cookie_consent_values(), true) && preg_match('/\Aitems:[a-z0-9_,]*\z/', $value) !== 1) {
        $value = 'essential';
    }

    $_COOKIE[sr_privacy_cookie_consent_cookie_name()] = $value;
    setcookie(
        sr_privacy_cookie_consent_cookie_name(),
        $value,
        sr_privacy_cookie_consent_cookie_options(time() + sr_privacy_cookie_consent_max_age_seconds())
    );
}

function sr_privacy_cookie_consent_value_from_items(array $items): string
{
    $allowed = sr_privacy_cookie_consent_optional_item_keys();
    $items = array_values(array_unique(array_filter($items, static function ($item) use ($allowed): bool {
        return is_string($item) && in_array($item, $allowed, true);
    })));
    sort($items, SORT_STRING);

    return $items === [] ? 'essential' : 'items:' . implode(',', $items);
}

function sr_privacy_cookie_consent_items_fields_html(array $selectedItems): string
{
    $optionalItems = sr_privacy_cookie_consent_optional_items();
    ob_start();
    ?>
    <div class="sr-cookie-consent-items">
        <?php foreach ($optionalItems as $itemKey => $item) { ?>
            <label class="sr-cookie-consent-item">
                <input type="checkbox" name="optional_items[]" value="<?php echo sr_e((string) $itemKey); ?>"<?php echo in_array((string) $itemKey, $selectedItems, true) ? ' checked' : ''; ?>>
                <span>
                    <strong><?php echo sr_e((string) $item['label']); ?></strong>
                    <small><?php echo sr_e((string) $item['description']); ?></small>
                </span>
            </label>
        <?php } ?>
    </div>
    <?php
    $html = ob_get_clean();

    return is_string($html) ? $html : '';
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
                <input type="hidden" name="consent" value="custom">
                <?php echo sr_privacy_cookie_consent_items_fields_html(sr_privacy_cookie_consent_optional_item_keys()); ?>
                <button type="submit" class="sr-cookie-consent-button sr-cookie-consent-button-primary"><?php echo sr_e(sr_t('privacy::cookie.save.selection')); ?></button>
            </form>
        </div>
    </section>
    <?php
    $html = ob_get_clean();

    return is_string($html) ? $html : '';
}
