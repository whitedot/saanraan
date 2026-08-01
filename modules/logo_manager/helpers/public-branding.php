<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_logo_manager_public_clean_position_key(string $positionKey): string
{
    $positionKey = strtolower(trim($positionKey));
    return preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*){1,5}\z/', $positionKey) === 1 ? $positionKey : '';
}

function sr_logo_manager_public_clean_provider_key(string $providerKey): string
{
    $providerKey = strtolower(trim($providerKey));
    if ($providerKey === 'all') {
        return 'all';
    }

    return preg_match('/\A[a-z][a-z0-9_]{0,39}\z/', $providerKey) === 1 ? $providerKey : '';
}

function sr_logo_manager_public_clean_slot_key(string $slotKey): string
{
    $slotKey = strtolower(trim($slotKey));
    return in_array($slotKey, ['top', 'bottom'], true) ? $slotKey : '';
}

function sr_logo_manager_public_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $cacheKey = spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
        $cache[$cacheKey] = true;
    } catch (Throwable) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function sr_logo_manager_public_active_logo(PDO $pdo, string $positionKey, ?string $now = null, array $usageTarget = []): ?array
{
    $positionKey = sr_logo_manager_public_clean_position_key($positionKey);
    if ($positionKey === '' || !sr_logo_manager_public_table_exists($pdo, 'sr_logo_manager_logos')) {
        return null;
    }

    $now = $now !== null ? $now : sr_now();
    $usageProviderKey = sr_logo_manager_public_clean_provider_key((string) ($usageTarget['layout_provider_key'] ?? $usageTarget['provider_key'] ?? ''));
    $usageSlotKey = sr_logo_manager_public_clean_slot_key((string) ($usageTarget['slot_key'] ?? $usageTarget['usage_slot_key'] ?? ''));
    $hasUsageTarget = $usageProviderKey !== '' && $usageSlotKey !== '';
    $hasUsageTable = sr_logo_manager_public_table_exists($pdo, 'sr_logo_manager_logo_usage_targets');
    if ($hasUsageTarget && !$hasUsageTable && $usageSlotKey === 'bottom') {
        return null;
    }

    try {
        $params = [
            'position_key' => $positionKey,
            'now_start' => $now,
            'now_end' => $now,
        ];
        $selectUsageRankSql = '';
        $whereUsageSql = '';
        $orderUsageSql = '';
        if ($hasUsageTarget && $hasUsageTable) {
            $allowUntargetedFallback = array_key_exists('allow_untargeted_fallback', $usageTarget)
                ? !empty($usageTarget['allow_untargeted_fallback'])
                : $usageSlotKey === 'top';
            $selectUsageRankSql = ",
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM sr_logo_manager_logo_usage_targets lm_usage_match
                            WHERE lm_usage_match.logo_id = sr_logo_manager_logos.id
                              AND lm_usage_match.layout_provider_key = :usage_provider_key_rank
                              AND lm_usage_match.slot_key = :usage_slot_key_rank
                        ) THEN 0
                        WHEN EXISTS (
                            SELECT 1
                            FROM sr_logo_manager_logo_usage_targets lm_usage_all_rank
                            WHERE lm_usage_all_rank.logo_id = sr_logo_manager_logos.id
                              AND lm_usage_all_rank.layout_provider_key = 'all'
                              AND lm_usage_all_rank.slot_key = :usage_slot_key_all_rank
                        ) THEN 1
                        WHEN :allow_untargeted_fallback_rank = 1 THEN 2
                        ELSE 1
                    END AS usage_rank";
            $whereUsageSql = "
               AND (
                    EXISTS (
                        SELECT 1
                        FROM sr_logo_manager_logo_usage_targets lm_usage_filter
                        WHERE lm_usage_filter.logo_id = sr_logo_manager_logos.id
                          AND lm_usage_filter.layout_provider_key IN (:usage_provider_key_filter, 'all')
                          AND lm_usage_filter.slot_key = :usage_slot_key_filter
                    )
                    OR (
                        :allow_untargeted_fallback = 1
                        AND NOT EXISTS (
                            SELECT 1
                            FROM sr_logo_manager_logo_usage_targets lm_usage_any
                            WHERE lm_usage_any.logo_id = sr_logo_manager_logos.id
                        )
                    )
               )";
            $orderUsageSql = 'usage_rank ASC,';
            $params['usage_provider_key_rank'] = $usageProviderKey;
            $params['usage_slot_key_rank'] = $usageSlotKey;
            $params['usage_slot_key_all_rank'] = $usageSlotKey;
            $params['allow_untargeted_fallback_rank'] = $allowUntargetedFallback ? 1 : 0;
            $params['usage_provider_key_filter'] = $usageProviderKey;
            $params['usage_slot_key_filter'] = $usageSlotKey;
            $params['allow_untargeted_fallback'] = $allowUntargetedFallback ? 1 : 0;
        }

        $stmt = $pdo->prepare(
            "SELECT id, position_key, title, alt_text, link_url, use_as_public_symbol, starts_at, ends_at, sort_order,
                    storage_driver, storage_key, public_url, mime_type, width, height" . $selectUsageRankSql . "
             FROM sr_logo_manager_logos
             WHERE position_key = :position_key
               AND status = 'active'
               AND (starts_at IS NULL OR starts_at <= :now_start)
               AND (ends_at IS NULL OR ends_at >= :now_end)" . $whereUsageSql . "
             ORDER BY " . $orderUsageSql . " CASE WHEN starts_at IS NULL AND ends_at IS NULL THEN 1 ELSE 0 END ASC,
                      CASE WHEN starts_at IS NOT NULL AND ends_at IS NOT NULL THEN 0 ELSE 1 END ASC,
                      CASE
                          WHEN starts_at IS NOT NULL AND ends_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, starts_at, ends_at)
                          ELSE 2147483647
                      END ASC,
                      sort_order ASC,
                      starts_at DESC,
                      id DESC
             LIMIT 1"
        );
        $stmt->execute($params);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'logo_manager_public_active_logo_failed');
        return null;
    }
}

function sr_logo_manager_public_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Alogo_manager/images/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp|svg)\z#', $key) === 1
        || preg_match('#\Alogo_manager/icon-variants/\d{4}/\d{2}/[a-f0-9]{32}\.png\z#', $key) === 1;
}

function sr_logo_manager_public_asset_url(array $asset): string
{
    $publicUrl = trim((string) ($asset['public_url'] ?? ''));
    if ($publicUrl !== '' && (sr_is_safe_relative_url($publicUrl) || sr_is_http_url($publicUrl))) {
        return $publicUrl;
    }

    $driver = (string) ($asset['storage_driver'] ?? 'local');
    $key = (string) ($asset['storage_key'] ?? '');
    if (!in_array($driver, ['local', 's3'], true) || !sr_logo_manager_public_storage_key_is_valid($key)) {
        return '';
    }

    $publicUrl = sr_storage_public_url($driver, $key);
    if ($publicUrl !== '') {
        return $publicUrl;
    }

    return '/logo-manager/image?file=' . rawurlencode(sr_storage_reference($driver, $key));
}

function sr_logo_manager_public_output_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    return sr_is_http_url($url) ? $url : sr_url($url);
}

function sr_logo_manager_render_logo(PDO $pdo, string $positionKey, ?array $site = null, array $attributes = []): string
{
    $usageTarget = [];
    $layoutProviderKey = sr_logo_manager_public_clean_provider_key((string) ($attributes['layout_provider_key'] ?? $attributes['provider_key'] ?? ''));
    $usageSlotKey = sr_logo_manager_public_clean_slot_key((string) ($attributes['usage_slot_key'] ?? $attributes['slot_key'] ?? ''));
    if ($layoutProviderKey !== '' && $usageSlotKey !== '') {
        $usageTarget = [
            'layout_provider_key' => $layoutProviderKey,
            'slot_key' => $usageSlotKey,
        ];
        if (array_key_exists('allow_untargeted_fallback', $attributes)) {
            $usageTarget['allow_untargeted_fallback'] = !empty($attributes['allow_untargeted_fallback']);
        }
    }

    $logo = sr_logo_manager_public_active_logo($pdo, $positionKey, null, $usageTarget);
    if (!is_array($logo) && isset($attributes['fallback_position_key'])) {
        $fallbackPositionKey = sr_logo_manager_public_clean_position_key((string) $attributes['fallback_position_key']);
        if ($fallbackPositionKey !== '' && $fallbackPositionKey !== sr_logo_manager_public_clean_position_key($positionKey)) {
            $logo = sr_logo_manager_public_active_logo($pdo, $fallbackPositionKey, null, $usageTarget);
        }
    }
    if (!is_array($logo)) {
        return '';
    }

    $src = sr_logo_manager_public_asset_url($logo);
    if ($src === '') {
        return '';
    }

    $alt = array_key_exists('alt', $attributes)
        ? sr_clean_single_line((string) $attributes['alt'], 160)
        : trim((string) ($logo['alt_text'] ?? ''));
    if ($alt === '' && is_array($site)) {
        $alt = trim((string) ($site['site_name'] ?? $site['name'] ?? ''));
    }
    $class = sr_clean_single_line((string) ($attributes['class'] ?? 'site-logo-image'), 120);
    $width = (int) ($logo['width'] ?? 0);
    $height = (int) ($logo['height'] ?? 0);

    $html = '<img class="' . sr_e($class) . '" src="' . sr_e(sr_logo_manager_public_output_url($src)) . '" alt="' . sr_e($alt) . '"';
    if ($width > 0 && $height > 0) {
        $html .= ' width="' . sr_e((string) $width) . '" height="' . sr_e((string) $height) . '"';
    }
    $html .= ' loading="eager" decoding="async">';

    return $html;
}

function sr_logo_manager_public_symbol_logo_row(PDO $pdo, ?string $now = null): ?array
{
    if (!sr_logo_manager_public_table_exists($pdo, 'sr_logo_manager_logos')) {
        return null;
    }

    $now = $now !== null ? $now : sr_now();
    try {
        $stmt = $pdo->prepare(
            "SELECT id, position_key, title, alt_text, link_url, use_as_public_symbol, starts_at, ends_at, sort_order,
                    storage_driver, storage_key, public_url, mime_type, width, height
             FROM sr_logo_manager_logos
             WHERE position_key = 'public.app_icon'
               AND use_as_public_symbol = 1
               AND status = 'active'
               AND (starts_at IS NULL OR starts_at <= :now_start)
               AND (ends_at IS NULL OR ends_at >= :now_end)
             ORDER BY CASE WHEN starts_at IS NULL AND ends_at IS NULL THEN 1 ELSE 0 END ASC,
                      CASE WHEN starts_at IS NOT NULL AND ends_at IS NOT NULL THEN 0 ELSE 1 END ASC,
                      CASE
                          WHEN starts_at IS NOT NULL AND ends_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, starts_at, ends_at)
                          ELSE 2147483647
                      END ASC,
                      sort_order ASC,
                      starts_at DESC,
                      id DESC
             LIMIT 1"
        );
        $stmt->execute(['now_start' => $now, 'now_end' => $now]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'logo_manager_public_symbol_logo_failed');
        return null;
    }
}

function sr_logo_manager_render_public_symbol_logo(PDO $pdo, ?array $site = null, array $attributes = []): string
{
    $logo = sr_logo_manager_public_symbol_logo_row($pdo);
    if (!is_array($logo)) {
        return '';
    }

    $src = sr_logo_manager_public_asset_url($logo);
    if ($src === '') {
        return '';
    }

    $alt = array_key_exists('alt', $attributes)
        ? sr_clean_single_line((string) $attributes['alt'], 160)
        : trim((string) ($logo['alt_text'] ?? ''));
    if ($alt === '' && is_array($site)) {
        $alt = trim((string) ($site['site_name'] ?? $site['name'] ?? ''));
    }
    $class = sr_clean_single_line((string) ($attributes['class'] ?? 'site-logo-image'), 120);
    $width = (int) ($logo['width'] ?? 0);
    $height = (int) ($logo['height'] ?? 0);

    $html = '<img class="' . sr_e($class) . '" src="' . sr_e(sr_logo_manager_public_output_url($src)) . '" alt="' . sr_e($alt) . '"';
    if ($width > 0 && $height > 0) {
        $html .= ' width="' . sr_e((string) $width) . '" height="' . sr_e((string) $height) . '"';
    }
    $html .= ' loading="eager" decoding="async">';

    return $html;
}

function sr_logo_manager_public_favicon_reset_marker(PDO $pdo): string
{
    try {
        $stmt = $pdo->prepare(
            'SELECT s.setting_value
             FROM sr_module_settings s
             INNER JOIN sr_modules m ON m.id = s.module_id
             WHERE m.module_key = :module_key
               AND s.setting_key = :setting_key
             LIMIT 1'
        );
        $stmt->execute(['module_key' => 'logo_manager', 'setting_key' => 'favicon_reset_at']);
        return trim((string) ($stmt->fetchColumn() ?: ''));
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'logo_manager_public_favicon_reset_marker_failed');
        return '';
    }
}

function sr_logo_manager_public_favicon_cache_version(PDO $pdo): string
{
    if (!sr_logo_manager_public_table_exists($pdo, 'sr_logo_manager_logos')) {
        return '0';
    }

    $versionParts = [sr_logo_manager_public_favicon_reset_marker($pdo)];
    try {
        $stmt = $pdo->query("SELECT MAX(updated_at) AS updated_at, MAX(id) AS max_id FROM sr_logo_manager_logos WHERE position_key = 'public.favicon'");
        $row = $stmt !== false ? $stmt->fetch() : false;
        if (is_array($row)) {
            $versionParts[] = (string) ($row['updated_at'] ?? '');
            $versionParts[] = (string) ($row['max_id'] ?? '');
        }
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'logo_manager_public_favicon_logo_version_failed');
    }

    if (sr_logo_manager_public_table_exists($pdo, 'sr_logo_manager_icon_variants')) {
        try {
            $stmt = $pdo->query(
                "SELECT MAX(v.updated_at) AS updated_at, MAX(v.id) AS max_id
                 FROM sr_logo_manager_icon_variants v
                 INNER JOIN sr_logo_manager_logos l ON l.id = v.logo_id
                 WHERE l.position_key = 'public.favicon'"
            );
            $row = $stmt !== false ? $stmt->fetch() : false;
            if (is_array($row)) {
                $versionParts[] = (string) ($row['updated_at'] ?? '');
                $versionParts[] = (string) ($row['max_id'] ?? '');
            }
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'logo_manager_public_favicon_variant_version_failed');
        }
    }

    $versionSource = trim(implode('|', array_filter($versionParts, static fn (string $part): bool => $part !== '')));
    return $versionSource === '' ? '0' : substr(hash('sha256', $versionSource), 0, 16);
}

function sr_logo_manager_public_url_with_cache_version(string $url, string $version): string
{
    $url = trim($url);
    $version = preg_replace('/[^A-Za-z0-9._-]/', '', trim($version)) ?? '';
    if ($url === '' || $version === '' || str_starts_with($url, 'data:')) {
        return $url;
    }

    $fragment = '';
    $fragmentPosition = strpos($url, '#');
    if ($fragmentPosition !== false) {
        $fragment = substr($url, $fragmentPosition);
        $url = substr($url, 0, $fragmentPosition);
    }

    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($version) . $fragment;
}

function sr_logo_manager_favicon_link_tag(PDO $pdo): string
{
    $cacheVersion = sr_logo_manager_public_favicon_cache_version($pdo);
    $logo = sr_logo_manager_public_active_logo($pdo, 'public.favicon');
    if (!is_array($logo)) {
        return '';
    }

    if (sr_logo_manager_public_table_exists($pdo, 'sr_logo_manager_icon_variants')) {
        $stmt = $pdo->prepare(
            "SELECT * FROM sr_logo_manager_icon_variants
             WHERE logo_id = :logo_id AND status = 'active'
             ORDER BY purpose ASC, width ASC, id DESC"
        );
        $stmt->execute(['logo_id' => (int) ($logo['id'] ?? 0)]);
        $html = [];
        foreach ($stmt->fetchAll() as $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $url = sr_logo_manager_public_asset_url($variant);
            if ($url === '') {
                continue;
            }
            $url = sr_logo_manager_public_url_with_cache_version($url, $cacheVersion);
            $sizes = (string) (int) ($variant['width'] ?? 0) . 'x' . (string) (int) ($variant['height'] ?? 0);
            $href = sr_e(sr_logo_manager_public_output_url($url));
            $html[] = (string) ($variant['purpose'] ?? '') === 'apple_touch'
                ? '<link rel="apple-touch-icon" sizes="' . sr_e($sizes) . '" href="' . $href . '">'
                : '<link rel="icon" type="image/png" sizes="' . sr_e($sizes) . '" href="' . $href . '">';
        }
        if ($html !== []) {
            return implode(PHP_EOL, $html);
        }
    }

    $url = sr_logo_manager_public_asset_url($logo);
    if ($url === '') {
        return '';
    }

    $href = sr_e(sr_logo_manager_public_output_url(sr_logo_manager_public_url_with_cache_version($url, $cacheVersion)));
    return '<link rel="icon" href="' . $href . '">' . PHP_EOL
        . '<link rel="apple-touch-icon" href="' . $href . '">';
}
