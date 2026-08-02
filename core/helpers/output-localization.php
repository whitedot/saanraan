<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function sr_set_locale(string $locale): void
{
    if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $locale) !== 1) {
        $locale = 'ko';
    }

    $GLOBALS['sr_locale'] = $locale;
}

function sr_locale(): string
{
    $locale = $GLOBALS['sr_locale'] ?? 'ko';
    return is_string($locale) && $locale !== '' ? $locale : 'ko';
}

function sr_resolve_locale(PDO $pdo, ?array $site): string
{
    $supportedLocales = sr_supported_locales($site);
    $accountId = $_SESSION['sr_account_id'] ?? null;
    if (is_int($accountId) || ctype_digit((string) $accountId)) {
        try {
            $stmt = $pdo->prepare('SELECT locale FROM sr_member_accounts WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $accountId]);
            $account = $stmt->fetch();
            if (
                is_array($account)
                && is_string($account['locale'] ?? null)
                && in_array((string) $account['locale'], $supportedLocales, true)
            ) {
                return (string) $account['locale'];
            }
        } catch (Throwable $exception) {
            return is_array($site) ? (string) ($site['default_locale'] ?? 'ko') : 'ko';
        }
    }

    return is_array($site) ? (string) ($site['default_locale'] ?? 'ko') : 'ko';
}

function sr_supported_locales(?array $site): array
{
    $defaultLocale = is_array($site) ? (string) ($site['default_locale'] ?? 'ko') : 'ko';
    $rawLocales = is_array($site) ? (string) ($site['supported_locales'] ?? '') : '';
    $locales = [];

    foreach (preg_split('/[\s,]+/', $rawLocales) ?: [] as $locale) {
        if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $locale) === 1) {
            $locales[$locale] = $locale;
        }
    }

    if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $defaultLocale) === 1) {
        $locales[$defaultLocale] = $defaultLocale;
    }

    return array_values($locales !== [] ? $locales : ['ko']);
}

function sr_available_locale_options(?array $site = null): array
{
    $locales = [];
    $langDir = SR_ROOT . '/lang';
    if (is_dir($langDir)) {
        foreach (scandir($langDir) ?: [] as $localeDirectory) {
            if (!is_string($localeDirectory) || preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $localeDirectory) !== 1) {
                continue;
            }

            if (is_file($langDir . '/' . $localeDirectory . '/core.php')) {
                $locales[$localeDirectory] = $localeDirectory;
            }
        }
    }

    foreach (sr_supported_locales($site) as $locale) {
        $locales[$locale] = $locale;
    }

    if ($locales === []) {
        $locales['ko'] = 'ko';
    }

    ksort($locales);

    return array_values($locales);
}

function sr_t(string $key, array $params = [], ?string $locale = null): string
{
    $locale = $locale ?? sr_locale();
    $moduleKey = '';
    $translationKey = $key;

    if (strpos($key, '::') !== false) {
        [$moduleKey, $translationKey] = explode('::', $key, 2);
    }

    $translations = sr_load_translations($locale, $moduleKey);
    $message = $translations[$translationKey] ?? null;

    if (!is_string($message) && $locale !== sr_fallback_locale()) {
        $fallbackTranslations = sr_load_translations(sr_fallback_locale(), $moduleKey);
        $message = $fallbackTranslations[$translationKey] ?? null;
        if (is_string($message)) {
            sr_translation_record_fallback($locale, sr_fallback_locale(), $moduleKey, $translationKey, $key);
        }
    }

    if (!is_string($message)) {
        $message = $key;
    }

    foreach ($params as $name => $value) {
        $message = str_replace('{' . $name . '}', (string) $value, $message);
    }

    return $message;
}

function sr_fallback_locale(): string
{
    return 'ko';
}

function sr_translation_record_fallback(string $locale, string $fallbackLocale, string $moduleKey, string $translationKey, string $fullKey): void
{
    $events = $GLOBALS['sr_translation_fallback_events'] ?? [];
    if (!is_array($events)) {
        $events = [];
    }

    if (count($events) >= 500) {
        return;
    }

    $events[] = [
        'locale' => $locale,
        'fallback_locale' => $fallbackLocale,
        'module_key' => $moduleKey,
        'translation_key' => $translationKey,
        'key' => $fullKey,
    ];
    $GLOBALS['sr_translation_fallback_events'] = $events;
}

function sr_translation_fallback_events(): array
{
    $events = $GLOBALS['sr_translation_fallback_events'] ?? [];
    return is_array($events) ? $events : [];
}

function sr_translation_clear_fallback_events(): void
{
    $GLOBALS['sr_translation_fallback_events'] = [];
}

function sr_load_translations(string $locale, string $moduleKey = ''): array
{
    static $cache = [];

    if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $locale) !== 1) {
        $locale = 'ko';
    }

    if ($moduleKey !== '' && !sr_is_safe_module_key($moduleKey)) {
        return [];
    }

    $cacheKey = $moduleKey . '|' . $locale;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $file = $moduleKey === ''
        ? SR_ROOT . '/lang/' . $locale . '/core.php'
        : SR_ROOT . '/modules/' . $moduleKey . '/lang/' . $locale . '.php';

    if (!is_file($file)) {
        $cache[$cacheKey] = [];
        return [];
    }

    $translations = include $file;
    $cache[$cacheKey] = is_array($translations) ? $translations : [];

    return $cache[$cacheKey];
}
