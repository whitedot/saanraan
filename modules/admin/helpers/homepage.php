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

    foreach (sr_admin_homepage_contract_candidates($pdo) as $candidate) {
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

function sr_admin_homepage_contract_candidates(PDO $pdo): array
{
    $candidates = [];
    foreach (sr_enabled_module_contract_files($pdo, 'homepage-candidates.php') as $moduleKey => $file) {
        $contract = sr_module_homepage_contract($moduleKey, $file);
        $candidatesFunction = $contract['candidates_function'] ?? null;
        if (!is_callable($candidatesFunction)) {
            continue;
        }

        try {
            $moduleCandidates = $candidatesFunction($pdo);
        } catch (Throwable) {
            $moduleCandidates = [];
        }

        if (!is_array($moduleCandidates)) {
            continue;
        }

        foreach ($moduleCandidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            if ((string) ($candidate['module_key'] ?? '') === '') {
                $candidate['module_key'] = $moduleKey;
            }
            $candidates[] = $candidate;
        }
    }

    return $candidates;
}
