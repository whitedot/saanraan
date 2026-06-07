<?php

declare(strict_types=1);

function sr_admin_homepage_candidate_options(PDO $pdo, string $currentPath = '/'): array
{
    $serviceMainCandidates = [];
    $homepageModuleKeys = ['content', 'community', 'quiz'];
    $candidates = [
        '/' => [
            'module_key' => 'core',
            'label' => sr_t('admin::homepage.default.label'),
            'path' => '/',
            'detail' => sr_t('admin::homepage.default.detail'),
            'available' => true,
        ],
    ];

    foreach ($homepageModuleKeys as $moduleKey) {
        if (!sr_module_enabled($pdo, $moduleKey)) {
            continue;
        }

        $metadata = sr_module_metadata($moduleKey);
        $serviceDomain = is_array($metadata['service_domain'] ?? null) ? $metadata['service_domain'] : [];
        $mainPage = is_array($serviceDomain['main_page'] ?? null) ? $serviceDomain['main_page'] : [];
        $path = (string) ($mainPage['path'] ?? '');
        if ($path === '' || $path === '/' || !sr_is_safe_relative_url($path)) {
            continue;
        }

        $adminMetadata = is_array($metadata['admin'] ?? null) ? $metadata['admin'] : [];
        $serviceMainCandidates[] = [
            'module_key' => $moduleKey,
            'label' => (string) ($mainPage['label'] ?? ($metadata['name'] ?? $moduleKey)),
            'path' => $path,
            'detail' => sr_admin_module_name_label((string) ($metadata['name'] ?? $moduleKey)),
            'available' => sr_site_home_path_is_available($pdo, $path),
            '_category_order' => (int) ($adminMetadata['category_order'] ?? 999),
            '_menu_order' => (int) ($adminMetadata['menu_order'] ?? 999),
        ];
    }

    usort($serviceMainCandidates, static function (array $left, array $right): int {
        return [
            (int) ($left['_category_order'] ?? 999),
            (int) ($left['_menu_order'] ?? 999),
            (string) ($left['label'] ?? ''),
            (string) ($left['path'] ?? ''),
        ] <=> [
            (int) ($right['_category_order'] ?? 999),
            (int) ($right['_menu_order'] ?? 999),
            (string) ($right['label'] ?? ''),
            (string) ($right['path'] ?? ''),
        ];
    });

    foreach ($serviceMainCandidates as $candidate) {
        unset($candidate['_category_order'], $candidate['_menu_order']);
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
