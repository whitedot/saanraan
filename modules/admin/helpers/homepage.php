<?php

declare(strict_types=1);

function sr_admin_homepage_settings(PDO $pdo, ?array $site): array
{
    return [
        'home_path' => (string) ($site['home_path'] ?? '/'),
        'home_title' => (string) sr_site_setting($pdo, 'site.home.title', ''),
        'home_eyebrow' => (string) sr_site_setting($pdo, 'site.home.eyebrow', ''),
        'home_description' => (string) sr_site_setting($pdo, 'site.home.description', ''),
        'home_primary_label' => (string) sr_site_setting($pdo, 'site.home.primary_label', ''),
        'home_primary_url' => (string) sr_site_setting($pdo, 'site.home.primary_url', ''),
        'home_secondary_label' => (string) sr_site_setting($pdo, 'site.home.secondary_label', ''),
        'home_secondary_url' => (string) sr_site_setting($pdo, 'site.home.secondary_url', ''),
    ];
}

function sr_admin_homepage_post_values(?array $site): array
{
    $homePath = sr_post_string('home_path', 255);
    if ($homePath === '') {
        $homePath = (string) ($site['home_path'] ?? '/');
    }

    return [
        'home_path' => $homePath,
        'home_title' => sr_post_string('home_title', 160),
        'home_eyebrow' => sr_post_string('home_eyebrow', 80),
        'home_description' => sr_post_string('home_description', 1000),
        'home_primary_label' => sr_post_string('home_primary_label', 80),
        'home_primary_url' => sr_post_string('home_primary_url', 255),
        'home_secondary_label' => sr_post_string('home_secondary_label', 80),
        'home_secondary_url' => sr_post_string('home_secondary_url', 255),
    ];
}

function sr_admin_homepage_candidate_options(PDO $pdo, string $currentPath = '/'): array
{
    $candidates = [
        '/' => [
            'module_key' => 'core',
            'label' => '기본 홈페이지',
            'path' => '/',
            'detail' => '공개 레이아웃이 제공하는 기본 홈',
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
            'label' => '현재 저장값',
            'path' => $currentPath,
            'detail' => '현재 사용할 수 없는 초기화면입니다.',
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

function sr_admin_homepage_validate_url_field(array $values, string $labelKey, string $urlKey, string $label, array &$errors): void
{
    $buttonLabel = trim((string) ($values[$labelKey] ?? ''));
    $buttonUrl = trim((string) ($values[$urlKey] ?? ''));
    if ($buttonLabel === '' && $buttonUrl === '') {
        return;
    }

    if ($buttonLabel === '' || $buttonUrl === '') {
        $errors[] = $label . ' 버튼은 문구와 URL을 함께 입력하세요.';
        return;
    }

    if (!sr_is_safe_relative_url($buttonUrl) && !sr_is_http_url($buttonUrl)) {
        $errors[] = $label . ' 버튼 URL은 안전한 내부 경로 또는 http/https URL이어야 합니다.';
    }
}

function sr_admin_handle_homepage_post(PDO $pdo, array $account, ?array $site): array
{
    $values = sr_admin_homepage_post_values($site);
    $errors = [];
    $notice = '';
    $candidates = sr_admin_homepage_candidate_options($pdo, (string) ($values['home_path'] ?? '/'));
    $selectedPath = (string) ($values['home_path'] ?? '/');

    if (!isset($candidates[$selectedPath]) || empty($candidates[$selectedPath]['available'])) {
        $errors[] = '초기화면 후보가 올바르지 않거나 현재 사용할 수 없습니다.';
    }

    sr_admin_homepage_validate_url_field($values, 'home_primary_label', 'home_primary_url', '기본', $errors);
    sr_admin_homepage_validate_url_field($values, 'home_secondary_label', 'home_secondary_url', '보조', $errors);

    if ($errors === []) {
        $previousValues = sr_admin_homepage_settings($pdo, $site);
        sr_save_site_settings($pdo, [
            'site.home_path' => ['value' => $selectedPath, 'type' => 'string'],
            'site.home.title' => ['value' => (string) $values['home_title'], 'type' => 'string'],
            'site.home.eyebrow' => ['value' => (string) $values['home_eyebrow'], 'type' => 'string'],
            'site.home.description' => ['value' => (string) $values['home_description'], 'type' => 'string'],
            'site.home.primary_label' => ['value' => (string) $values['home_primary_label'], 'type' => 'string'],
            'site.home.primary_url' => ['value' => (string) $values['home_primary_url'], 'type' => 'string'],
            'site.home.secondary_label' => ['value' => (string) $values['home_secondary_label'], 'type' => 'string'],
            'site.home.secondary_url' => ['value' => (string) $values['home_secondary_url'], 'type' => 'string'],
        ]);

        $site = sr_load_site($pdo);
        $values = sr_admin_homepage_settings($pdo, is_array($site) ? $site : null);
        $notice = '홈페이지 설정을 저장했습니다.';

        sr_audit_log($pdo, [
            'actor_account_id' => (int) ($account['id'] ?? 0),
            'actor_type' => 'admin',
            'event_type' => 'site.homepage.updated',
            'target_type' => 'site_settings',
            'target_id' => 'homepage',
            'result' => 'success',
            'message' => 'Site homepage settings updated.',
            'metadata' => [
                'before' => $previousValues,
                'after' => $values,
            ],
        ]);
    }

    return [
        'errors' => $errors,
        'notice' => $notice,
        'values' => $values,
        'site' => $site,
    ];
}
