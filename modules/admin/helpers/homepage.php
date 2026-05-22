<?php

declare(strict_types=1);

function sr_admin_homepage_candidate_options(PDO $pdo, string $currentPath = '/'): array
{
    $candidates = [
        '/' => [
            'module_key' => 'core',
            'label' => sr_t('admin::homepage.default.label'),
            'path' => '/',
            'detail' => sr_t('admin::homepage.default.detail'),
            'available' => true,
        ],
    ];

    foreach (sr_enabled_module_keys($pdo) as $moduleKey) {
        $metadata = sr_module_metadata($moduleKey);
        $serviceDomain = is_array($metadata['service_domain'] ?? null) ? $metadata['service_domain'] : [];
        $mainPage = is_array($serviceDomain['main_page'] ?? null) ? $serviceDomain['main_page'] : [];
        $path = (string) ($mainPage['path'] ?? '');
        if ($path === '' || $path === '/' || !sr_is_safe_relative_url($path)) {
            continue;
        }

        $candidates[$path] = [
            'module_key' => $moduleKey,
            'label' => (string) ($mainPage['label'] ?? ($metadata['name'] ?? $moduleKey)),
            'path' => $path,
            'detail' => sr_admin_module_name_label((string) ($metadata['name'] ?? $moduleKey)),
            'available' => sr_site_home_path_is_available($pdo, $path),
        ];
    }

    foreach (sr_admin_homepage_page_candidates($pdo) as $candidate) {
        $path = (string) ($candidate['path'] ?? '');
        if ($path !== '') {
            $candidates[$path] = $candidate;
        }
    }

    if ($currentPath !== '' && !isset($candidates[$currentPath])) {
        $candidates[$currentPath] = [
            'module_key' => '',
            'label' => sr_t('admin::homepage.current.label'),
            'path' => $currentPath,
            'detail' => sr_t('admin::homepage.current.detail'),
            'available' => false,
        ];
    }

    return $candidates;
}

function sr_admin_homepage_page_candidates(PDO $pdo): array
{
    if (!sr_module_enabled($pdo, 'page') || !is_file(SR_ROOT . '/modules/page/helpers.php')) {
        return [];
    }

    require_once SR_ROOT . '/modules/page/helpers.php';
    if (!function_exists('sr_page_homepage_candidates')) {
        return [];
    }

    try {
        return sr_page_homepage_candidates($pdo);
    } catch (Throwable) {
        return [];
    }
}
